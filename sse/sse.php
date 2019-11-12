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

		$hashes = q("SELECT xchan FROM xconfig WHERE cat = 'sse' AND k ='timestamp' and v > %s - INTERVAL %s UNION SELECT channel_hash FROM channel WHERE channel_removed = 0",
			db_utcnow(),
			db_quoteinterval('15 MINUTE')
		);

		$hashes = flatten_array_recursive($hashes);
	}
	else {
		$channel = channelx_by_n($item_uid);
		$hashes = [$channel['channel_hash']];
	}

	foreach($hashes as $hash) {

		$t = get_xconfig($hash, 'sse', 'timestamp');

		if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
			set_xconfig($hash, 'sse', 'notifications', []);
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

		$x = get_xconfig($hash, 'sse', 'notifications');

		if($x === false)
			$x = [];

		// this is neccessary for Enotify::format() to calculate the right time and language
		if($sys) {
			$current_channel = channelx_by_hash($hash);
			date_default_timezone_set($current_channel['channel_timezone']);
		}
		else {
			date_default_timezone_set($channel['channel_timezone']);
		}

		push_lang(get_xconfig($hash, 'sse', 'language', 'en'));

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

		pop_lang();

		if(is_array($x['network']['notifications']))
			$x['network']['count'] = count($x['network']['notifications']);

		if(is_array($x['home']['notifications']))
			$x['home']['count'] = count($x['home']['notifications']);

		if(is_array($x['pubs']['notifications']))
			$x['pubs']['count'] = count($x['pubs']['notifications']);

		if(is_array($x['files']['notifications']))
			$x['files']['count'] = count($x['files']['notifications']);

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
		set_xconfig($channel['channel_hash'], 'sse', 'notifications', []);
	}

	$vnotify = get_pconfig($item_uid, 'system', 'vnotify');
	if(!($vnotify & VNOTIFY_EVENT))
		return;

	$xchan = q("SELECT * FROM xchan WHERE xchan_hash = '%s'",
		dbesc($item['event_xchan'])
	);

	$x = get_xconfig($channel['channel_hash'], 'sse', 'notifications');

	$rr = array_merge($item, $xchan[0]);

	// this is neccessary for Enotify::format() to calculate the right time and language
	date_default_timezone_set($channel['channel_timezone']);
	push_lang(get_xconfig($channel['channel_hash'], 'sse', 'language', 'en'));
	$x['all_events']['notifications'][] = Enotify::format_all_events($rr);
	pop_lang();

	if(is_array($x['all_events']['notifications']))
		$x['all_events']['count'] = count($x['all_events']['notifications']);

	set_xconfig($channel['channel_hash'], 'sse', 'notifications', $x);

}

function sse_enotify_store_end($item) {

	$channel = channelx_by_n($item['uid']);

	$t = get_xconfig($channel['channel_hash'], 'sse', 'timestamp');

	if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
		set_xconfig($channel['channel_hash'], 'sse', 'notifications', []);
	}

	$x = get_xconfig($channel['channel_hash'], 'sse', 'notifications');

	// this is neccessary for Enotify::format_notify() to calculate the right time and language
	date_default_timezone_set($channel['channel_timezone']);
	push_lang(get_xconfig($channel['channel_hash'], 'sse', 'language', 'en'));
	$x['notify']['notifications'][] = Enotify::format_notify($item);
	pop_lang();

	if(is_array($x['notify']['notifications']))
		$x['notify']['count'] = count($x['notify']['notifications']);

	set_xconfig($channel['channel_hash'], 'sse', 'notifications', $x);

}

function sse_permissions_create($item) {

	$channel = channelx_by_hash($item['recipient']['xchan_hash']);

	$t = get_xconfig($channel['channel_hash'], 'sse', 'timestamp');

	if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
		set_xconfig($channel['channel_hash'], 'sse', 'notifications', []);
	}

	$x = get_xconfig($channel['channel_hash'], 'sse', 'notifications');

	// this is neccessary for Enotify::format_notify() to calculate the right time
	date_default_timezone_set($channel['channel_timezone']);
	push_lang(get_xconfig($channel['channel_hash'], 'sse', 'language', 'en'));
	$x['intros']['notifications'][] = Enotify::format_intros($item['sender']);
	pop_lang();

	if(is_array($x['intros']['notifications']))
		$x['intros']['count'] = count($x['intros']['notifications']);

	set_xconfig($channel['channel_hash'], 'sse', 'notifications', $x);

}
