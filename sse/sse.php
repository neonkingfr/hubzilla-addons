<?php


/**
 * Name: SSE Notifications
 * Description: Server sent events notifications
 * Version: 1.0
 * Author: Mario Vavti
 * Maintainer: Mario Vavti <mario@hub.somaton.com> 
 * MinVersion: 4.7
 */

use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;
use Zotlabs\Lib\Enotify;
use Zotlabs\Lib\XConfig;

function sse_load() {
	Hook::register('item_stored', 'addon/sse/sse.php', 'sse_item_stored');
	Hook::register('event_store_event_end', 'addon/sse/sse.php', 'sse_event_store_event_end');
	Hook::register('enotify_store_end', 'addon/sse/sse.php', 'sse_enotify_store_end');
	Hook::register('permissions_create', 'addon/sse/sse.php', 'sse_permissions_create');
}

function sse_unload() {
	Hook::unregister('item_stored', 'addon/sse/sse.php', 'sse_item_stored');
	Hook::unregister('event_store_event_end', 'addon/sse/sse.php', 'sse_event_store_event_end');
	Hook::unregister('enotify_store_end', 'addon/sse/sse.php', 'sse_enotify_store_end');
	Hook::unregister('permissions_create', 'addon/sse/sse.php', 'sse_permissions_create');
}

function sse_item_stored($item) {

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

	if(! $hashes)
		return;

	foreach($hashes as $hash) {

		XConfig::Load($hash);

		$t = XConfig::Get($hash, 'sse', 'timestamp', NULL_DATE);

		if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
			XConfig::Set($hash, 'sse', 'notifications', []);
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

		XConfig::Set($hash, 'sse', 'lock', 1);

		$x = XConfig::Get($hash, 'sse', 'notifications', []);

		// this is neccessary for Enotify::format() to calculate the right time and language
		if($sys) {
			$current_channel = channelx_by_hash($hash);
			date_default_timezone_set($current_channel['channel_timezone']);
		}
		else {
			date_default_timezone_set($channel['channel_timezone']);
		}

		push_lang(XConfig::Get($hash, 'sse', 'language', 'en'));

		if($sys) {
			if(is_item_normal($item) && ($vnotify & VNOTIFY_PUBS || $sys))
				$x['pubs']['notifications'][] = Enotify::format($r[0]);
		}
		else {
			if(is_item_normal($item) && $item['item_wall'])
				$x['home']['notifications'][] = Enotify::format($r[0]);

			if(is_item_normal($item) && !$item['item_wall'])
				$x['network']['notifications'][] = Enotify::format($r[0]);

			if($item['obj_type'] === ACTIVITY_OBJ_FILE)
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

		XConfig::Set($hash, 'sse', 'timestamp', datetime_convert());
		XConfig::Set($hash, 'sse', 'notifications', $x);
		XConfig::Set($hash, 'sse', 'lock', 0);

	}

}

function sse_event_store_event_end($item) {

	if(! $item['uid'])
		return;

	$item_uid = $item['uid'];

	$channel = channelx_by_n($item_uid);

	if(! $channel)
		return;

	XConfig::Load($channel['channel_hash']);

	$t = XConfig::Get($channel['channel_hash'], 'sse', 'timestamp', NULL_DATE);

	if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
		XConfig::Set($channel['channel_hash'], 'sse', 'notifications', []);
	}

	$vnotify = get_pconfig($item_uid, 'system', 'vnotify');
	if(!($vnotify & VNOTIFY_EVENT))
		return;

	$xchan = q("SELECT * FROM xchan WHERE xchan_hash = '%s'",
		dbesc($item['event_xchan'])
	);

	$x = XConfig::Get($channel['channel_hash'], 'sse', 'notifications', []);

	$rr = array_merge($item, $xchan[0]);

	// this is neccessary for Enotify::format() to calculate the right time and language
	date_default_timezone_set($channel['channel_timezone']);
	push_lang(XConfig::Get($channel['channel_hash'], 'sse', 'language', 'en'));
	$x['all_events']['notifications'][] = Enotify::format_all_events($rr);
	pop_lang();

	if(is_array($x['all_events']['notifications']))
		$x['all_events']['count'] = count($x['all_events']['notifications']);

	XConfig::Set($channel['channel_hash'], 'sse', 'timestamp', datetime_convert());
	XConfig::Set($channel['channel_hash'], 'sse', 'notifications', $x);

}

function sse_enotify_store_end($item) {

	$channel = channelx_by_n($item['uid']);

	if(! $channel)
		return;

	XConfig::Load($channel['channel_hash']);

	$t = XConfig::Get($channel['channel_hash'], 'sse', 'timestamp', NULL_DATE);

	if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
		XConfig::Set($channel['channel_hash'], 'sse', 'notifications', []);
	}

	$x = XConfig::Get($channel['channel_hash'], 'sse', 'notifications', []);

	// this is neccessary for Enotify::format_notify() to calculate the right time and language
	date_default_timezone_set($channel['channel_timezone']);
	push_lang(XConfig::Get($channel['channel_hash'], 'sse', 'language', 'en'));
	$x['notify']['notifications'][] = Enotify::format_notify($item);
	pop_lang();

	if(is_array($x['notify']['notifications']))
		$x['notify']['count'] = count($x['notify']['notifications']);

	XConfig::Set($channel['channel_hash'], 'sse', 'timestamp', datetime_convert());
	XConfig::Set($channel['channel_hash'], 'sse', 'notifications', $x);

}

function sse_permissions_create($item) {

	$channel = channelx_by_hash($item['recipient']['xchan_hash']);

	if(! $channel)
		return;

	XConfig::Load($channel['channel_hash']);

	$t = XConfig::Get($channel['channel_hash'], 'sse', 'timestamp', NULL_DATE);

	if(datetime_convert('UTC', 'UTC', $t) < datetime_convert('UTC', 'UTC', '- 30 seconds')) {
		XConfig::Set($channel['channel_hash'], 'sse', 'notifications', []);
	}

	$x = XConfig::Get($channel['channel_hash'], 'sse', 'notifications', []);

	// this is neccessary for Enotify::format_notify() to calculate the right time
	date_default_timezone_set($channel['channel_timezone']);
	push_lang(XConfig::Get($channel['channel_hash'], 'sse', 'language', 'en'));
	$x['intros']['notifications'][] = Enotify::format_intros($item['sender']);
	pop_lang();

	if(is_array($x['intros']['notifications']))
		$x['intros']['count'] = count($x['intros']['notifications']);

	XConfig::Set($channel['channel_hash'], 'sse', 'timestamp', datetime_convert());
	XConfig::Set($channel['channel_hash'], 'sse', 'notifications', $x);

}
