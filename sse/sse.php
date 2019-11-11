<?php


/**
 * Name: SSE Notifications
 * Description: Server sent events notifications
 * Version: 1.0
 * Author: Mario Vavti
 * Maintainer: Mario Vavti <mario@hub.somaton.com> 
 * MinVersion: 4.1
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;
use Zotlabs\Lib\Enotify;

function sse_load() {
	Hook::register('item_store', 'addon/sse/sse.php', 'sse_item_store');
	Hook::register('event_store_event_end', 'addon/sse/sse.php', 'sse_event_store_event_end');
	Hook::register('enotify_store_end', 'addon/sse/sse.php', 'sse_enotify_store_end');
	Hook::register('permissions_create', 'addon/sse/sse.php', 'sse_permissions_create');
}


function sse_unload() {
	Hook::unregister('item_store', 'addon/sse/sse.php', 'sse_item_store');
	Hook::unregister('event_store_event_end', 'addon/sse/sse.php', 'sse_event_store_event_end');
	Hook::unregister('enotify_store_end', 'addon/sse/sse.php', 'sse_enotify_store_end');
	Hook::unregister('permissions_create', 'addon/sse/sse.php', 'sse_permissions_create');
}



function sse_item_store($item) {

	if(! $item['uid'])
		return;

	$item_uid = $item['uid'];

	$sys = false;

	if(is_sys_channel($item_uid)) {
		$sys = true;
		$hashes = get_channel_hashes();
	}
	else {
		$channel = channelx_by_n($item_uid);
		$hashes = [$channel['channel_hash']];
	}

	foreach($hashes as $hash) {

		$t = get_xconfig($hash, 'sse', 'timestamp');

		if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
			del_xconfig($hash, 'sse', 'notifications');
		}


		$vnotify = get_pconfig($item_uid, 'system', 'vnotify');
		if($item['verb'] === ACTIVITY_LIKE && !($vnotify & VNOTIFY_LIKE))
			continue;

		if($item['obj_type'] === ACTIVITY_OBJ_FILE && !($vnotify & VNOTIFY_FILES))
			continue;

		if($hash === $item['author_xchan'])
			continue;

		$r[0] = $item;
		xchan_query($r);

		// this is neccessary for Enotify::format() to calculate the right time.
		if($sys) {
			$current_channel = channelx_by_hash($hash);
			date_default_timezone_set($current_channel['channel_timezone']);
		}
		else {
			date_default_timezone_set($channel['channel_timezone']);
		}

		$x = get_xconfig($hash, 'sse', 'notifications');

		if($x === false)
			$x = [];

		if(is_sys_channel($item_uid)) {
			if(is_item_normal($item) && ($vnotify & VNOTIFY_PUBS || is_sys_channel($item_uid)))
				$x['pubs']['notifications'][] = Enotify::format($r[0]);
		}
		else {
			if(is_item_normal($item) && $item['item_wall'])
				$x['home']['notifications'][] = Enotify::format($r[0]);

			elseif(is_item_normal($item) && !$item['item_wall'])
				$x['network']['notifications'][] = Enotify::format($r[0]);

			elseif($item['obj_type'] === ACTIVITY_OBJ_FILE)
				$x['files']['notifications'][] = Enotify::format_files($r[0]);
		}

		if(is_array($x['network']['notifications']))
			$x['network']['count'] = count($x['network']['notifications']);

		if(is_array($x['home']['notifications']))
			$x['home']['count'] = count($x['home']['notifications']);

		if(is_array($x['pubs']['notifications']))
			$x['pubs']['count'] = count($x['pubs']['notifications']);

		if(is_array($x['files']['notifications']))
			$x['files']['count'] = count($x['files']['notifications']);

		set_xconfig($hash, 'sse', 'timestamp', datetime_convert());
		set_xconfig($hash, 'sse', 'notifications', $x);

	}

}

function sse_event_store_event_end($item) {

	if(! $item['uid'])
		return;

	$item_uid = $item['uid'];

	$channel = channelx_by_n($item_uid);

	$t = get_xconfig($channel['channel_hash'], 'sse', 'timestamp');

	if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
		del_xconfig($channel['channel_hash'], 'sse', 'notifications');
	}

	$vnotify = get_pconfig($item_uid, 'system', 'vnotify');
	if(!($vnotify & VNOTIFY_EVENT))
		return;

	$xchan = q("SELECT * FROM xchan WHERE xchan_hash = '%s'",
		dbesc($item['event_xchan'])
	);

	// this is neccessary for Enotify::format() to calculate the right time.
	date_default_timezone_set($channel['channel_timezone']);

	$x = get_xconfig($channel['channel_hash'], 'sse', 'notifications');

	$rr = array_merge($item, $xchan[0]);
	$x['all_events']['notifications'][] = Enotify::format_all_events($rr);

	if(is_array($x['all_events']['notifications']))
		$x['all_events']['count'] = count($x['all_events']['notifications']);

	set_xconfig($channel['channel_hash'], 'sse', 'timestamp', datetime_convert());
	set_xconfig($channel['channel_hash'], 'sse', 'notifications', $x);

}

function sse_enotify_store_end($item) {

	$channel = channelx_by_n($item['uid']);

	// this is neccessary for Enotify::format_notify() to calculate the right time
	date_default_timezone_set($channel['channel_timezone']);

	$t = get_xconfig($channel['channel_hash'], 'sse', 'timestamp');

	if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
		del_xconfig($channel['channel_hash'], 'sse', 'notifications');
	}

	$x = get_xconfig($channel['channel_hash'], 'sse', 'notifications');

	$x['notify']['notifications'][] = Enotify::format_notify($item);

	if(is_array($x['notify']['notifications']))
		$x['notify']['count'] = count($x['notify']['notifications']);

	set_xconfig($channel['channel_hash'], 'sse', 'timestamp', datetime_convert());
	set_xconfig($channel['channel_hash'], 'sse', 'notifications', $x);

}

function sse_permissions_create($item) {

	$channel = channelx_by_hash($item['recipient']['xchan_hash']);

	// this is neccessary for Enotify::format_notify() to calculate the right time
	date_default_timezone_set($channel['channel_timezone']);

	$t = get_xconfig($channel['channel_hash'], 'sse', 'timestamp');

	if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
		del_xconfig($channel['channel_hash'], 'sse', 'notifications');
	}

	$x = get_xconfig($channel['channel_hash'], 'sse', 'notifications');

	$x['intros']['notifications'][] = Enotify::format_intros($item['sender']);

	if(is_array($x['intros']['notifications']))
		$x['intros']['count'] = count($x['intros']['notifications']);

	set_xconfig($channel['channel_hash'], 'sse', 'timestamp', datetime_convert());
	set_xconfig($channel['channel_hash'], 'sse', 'notifications', $x);

}
