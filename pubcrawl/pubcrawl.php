<?php

/**
 * Name: PubCrawl
 * Description: An unapologetically non-compliant ActivityPub Protocol implemention
 *
 */

/**
 * This connector is undergoing heavy development at the moment. If you think some shortcuts were taken
 * - you are probably right. These will be cleaned up and moved to generalised interfaces once we actually
 * get communication flowing.
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Crypto;
use Zotlabs\Lib\Libzot;
use Zotlabs\Web\HTTPSig;
use Zotlabs\Lib\Activity;

require_once('addon/pubcrawl/as.php');

function pubcrawl_load() {
	Hook::register_array('addon/pubcrawl/pubcrawl.php', [
		'module_loaded'              => 'pubcrawl_load_module',
		'webfinger'                  => 'pubcrawl_webfinger',
		'follow_mod_init'            => 'pubcrawl_follow_mod_init',
		'thing_mod_init'             => 'pubcrawl_thing_mod_init',
		'locs_mod_init'              => 'pubcrawl_locs_mod_init',
		'follow_allow'               => 'pubcrawl_follow_allow',
		'discover_channel_webfinger' => 'pubcrawl_discover_channel_webfinger',
		'permissions_create'         => 'pubcrawl_permissions_create',
		'permissions_update'         => 'pubcrawl_permissions_update',
		'permissions_accept'         => 'pubcrawl_permissions_accept',
		'connection_remove'          => 'pubcrawl_connection_remove',
		'post_local_end'             => 'pubcrawl_post_local_end',
		'notifier_process'           => 'pubcrawl_notifier_process',
		'notifier_hub'               => 'pubcrawl_notifier_hub',
		'channel_links'              => 'pubcrawl_channel_links',
		'personal_xrd'               => 'pubcrawl_personal_xrd',
		'queue_deliver'              => 'pubcrawl_queue_deliver',
		'import_author'              => 'pubcrawl_import_author',
		'channel_protocols'          => 'pubcrawl_channel_protocols',
		'federated_transports'       => 'pubcrawl_federated_transports',
		'create_identity'            => 'pubcrawl_create_identity',
		'is_as_request'              => 'pubcrawl_is_as_request',
		'get_accept_header_string'   => 'pubcrawl_get_accept_header_string',
		'encode_person'              => 'pubcrawl_encode_person'
	]);
	Route::register('addon/pubcrawl/Mod_Pubcrawl.php', 'pubcrawl');
}

function pubcrawl_unload() {
	Hook::unregister_by_file('addon/pubcrawl/pubcrawl.php');
	Route::unregister('addon/pubcrawl/Mod_Pubcrawl.php', 'pubcrawl');
}

function pubcrawl_is_as_request(&$arr) {
	if (array_key_exists('channel', $arr)) {
		$channel_id = $arr['channel']['channel_id'];
		$is_sys     = intval($arr['channel']['channel_system']);
		if (!$is_sys && !Apps::addon_app_installed($channel_id, 'pubcrawl'))
			return;
	}
	$arr['data'] = [
		'application/x-zot-activity+json',
		'application/ld+json;profile="https://www.w3.org/ns/activitystreams"',
		'application/activity+json',
		'application/ld+json;profile="http://www.w3.org/ns/activitystreams"',
	];
}

function pubcrawl_get_accept_header_string(&$arr) {
	if (array_key_exists('channel', $arr)) {
		$channel_id = $arr['channel']['channel_id'];
		$is_sys     = intval($arr['channel']['channel_system']);
		if (!$is_sys && !Apps::addon_app_installed($channel_id, 'pubcrawl'))
			return;
	}
	$arr['data'] = 'application/x-zot-activity+json, application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
}

function pubcrawl_encode_person(&$arr) {
	if (isset($arr['xchan']['channel_id']) && Apps::addon_app_installed($arr['xchan']['channel_id'], 'pubcrawl')) {
		$arr['encoded']['inbox']        = z_root() . '/inbox/' . $arr['xchan']['channel_address'];
		$arr['encoded']['outbox']       = z_root() . '/outbox/' . $arr['xchan']['channel_address'];
		$arr['encoded']['followers']    = z_root() . '/followers/' . $arr['xchan']['channel_address'];
		$arr['encoded']['following']    = z_root() . '/following/' . $arr['xchan']['channel_address'];
		$arr['encoded']['endpoints']    = ['sharedInbox' => z_root() . '/inbox'];
		$arr['encoded']['discoverable'] = ((1 - intval($arr['xchan']['xchan_hidden'])) ? true : false);

		// map other nomadic identities linked with this channel
		$locations = [];
		$locs      = Libzot::encode_locations($arr['xchan']);
		if ($locs) {
			foreach ($locs as $loc) {
				if ($loc['url'] !== z_root()) {
					$locations[] = $loc['id_url'];
				}
			}
		}

		if ($locations) {
			if (count($locations) === 1) {
				$locations = array_shift($locations);
			}
			$arr['encoded']['copiedTo']    = $locations;
			$arr['encoded']['alsoKnownAs'] = $locations;
		}

		$cp = get_cover_photo($arr['xchan']['channel_id'], 'array');
		if ($cp) {
			$arr['encoded']['image'] = [
				'type'      => 'Image',
				'mediaType' => $cp['type'],
				'url'       => $cp['url']
			];
		}
		$dp = q("select about from profile where uid = %d and is_default = 1",
			intval($arr['xchan']['channel_id'])
		);
		if ($dp && $dp[0]['about']) {
			$arr['encoded']['summary'] = bbcode($dp[0]['about'], ['export' => true]);
		}
	}
	else {
		$collections = get_xconfig($arr['xchan']['xchan_hash'], 'activitypub', 'collections', []);
		if ($collections) {
			$arr['encoded'] = array_merge($arr['encoded'], $collections);
		}
	}
}

function pubcrawl_channel_protocols(&$b) {

	if (Apps::addon_app_installed($b['channel_id'], 'pubcrawl'))
		$b['protocols'][] = 'activitypub';

}

function pubcrawl_federated_transports(&$x) {
	$x[] = 'ActivityPub';
}

function pubcrawl_follow_allow(&$b) {

	if ($b['xchan']['xchan_network'] !== 'activitypub')
		return;

	$allowed = Apps::addon_app_installed($b['channel_id'], 'pubcrawl');
	if ($allowed === false)
		$allowed = 1;
	$b['allowed']   = $allowed;
	$b['singleton'] = 1;  // this network does not support channel clones

}

function pubcrawl_channel_links(&$b) {
	$c = channelx_by_nick($b['channel_address']);
	if ($c && Apps::addon_app_installed($c['channel_id'], 'pubcrawl')) {
		$b['channel_links'][] = [
			'rel'  => 'alternate',
			'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
			'url'  => z_root() . '/channel/' . $c['channel_address']
		];
		$b['channel_links'][] = [
			'rel'  => 'alternate',
			'type' => 'application/activity+json',
			'url'  => z_root() . '/channel/' . $c['channel_address']
		];
	}
}

function pubcrawl_post_local_end(&$x) {
	$item[] = $x;

	if ($item[0]['mid'] === $item[0]['parent_mid'])
		return;

	if (!Apps::addon_app_installed($item[0]['uid'], 'pubcrawl'))
		return;

	xchan_query($item);

	$channel = channelx_by_hash($item[0]['author_xchan']);

	$s = asencode_activity($item[0]);

	$msg              = array_merge(['@context' => [
		ACTIVITYSTREAMS_JSONLD_REV,
		'https://w3id.org/security/v1',
		z_root() . ZOT_APSCHEMA_REV
	]],
		$s
	);
	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg, $channel);
	$jmsg             = json_encode($msg, JSON_UNESCAPED_SLASHES);

	set_iconfig($item[0]['id'], 'activitypub', 'rawmsg', $jmsg, true);
}

function pubcrawl_webfinger(&$b) {
	if (!$b['channel'])
		return;

	if (!Apps::addon_app_installed($b['channel']['channel_id'], 'pubcrawl'))
		return;

	$b['result']['properties']['http://purl.org/zot/federation'] .= ',activitypub';

	$b['result']['links'][] = [
		'rel'  => 'self',
		'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
		'href' => z_root() . '/channel/' . $b['channel']['channel_address']
	];
	$b['result']['links'][] = [
		'rel'  => 'self',
		'type' => 'application/activity+json',
		'href' => z_root() . '/channel/' . $b['channel']['channel_address']
	];
}

function pubcrawl_personal_xrd(&$b) {

	if (!Apps::addon_app_installed($b['user']['channel_id'], 'pubcrawl'))
		return;

	$s = '<Link rel="self" type="application/ld+json" href="' . z_root() . '/channel/' . $b['user']['channel_address'] . '" />';
	$s = '<Link rel="self" type="application/activity+json" href="' . z_root() . '/channel/' . $b['user']['channel_address'] . '" />';

	$b['xml'] = str_replace('</XRD>', $s . "\n" . '</XRD>', $b['xml']);

}

function pubcrawl_discover_channel_webfinger(&$b) {

	$url      = $b['address'];
	$x        = $b['webfinger'];
	$protocol = $b['protocol'];

	logger('probing: activitypub');

	if ($protocol && strtolower($protocol) !== 'activitypub')
		return;

	if (is_array($x)) {

		$address = EMPTY_STR;

		if (array_key_exists('subject', $x) && strpos($x['subject'], 'acct:') === 0)
			$address = str_replace('acct:', '', $x['subject']);
		if (array_key_exists('aliases', $x) && count($x['aliases'])) {
			foreach ($x['aliases'] as $a) {
				if (strpos($a, 'acct:') === 0) {
					$address = str_replace('acct:', '', $a);
					break;
				}
			}
		}

		if (strpos($url, '@') && $x && array_key_exists('links', $x) && $x['links']) {
			foreach ($x['links'] as $link) {
				if (array_key_exists('rel', $link) && array_key_exists('type', $link)) {
					if ($link['rel'] === 'self' && ($link['type'] === 'application/activity+json' || strpos($link['type'], 'ld+json') !== false)) {
						$url = $link['href'];
					}
				}
			}
		}
	}

	if (($url) && (strpos($url, 'http') === 0)) {
		$x = Activity::fetch($url);
		if (!$x) {
			return;
		}
	}
	else {
		return;
	}

	$AS = new ActivityStreams($x);

	if (!$AS->is_valid()) {
		return;
	}
	// Now find the actor and see if there is something we can follow
	$person_obj = null;
	if (in_array($AS->type, ['Application', 'Group', 'Organization', 'Person', 'Service'])) {
		$person_obj = $AS->data;
	}
	elseif ($AS->obj && (in_array($AS->obj['type'], ['Application', 'Group', 'Organization', 'Person', 'Service']))) {
		$person_obj = $AS->obj;
	}
	elseif (local_channel() && $AS->obj && (in_array($AS->obj['type'], ['Note', 'Article']))) {
		// this implements mastodon remote reply functionality
		$item = Activity::decode_note($AS);
		if ($item) {
			Activity::store(App::get_channel(), get_observer_hash(), $AS, $item, true, true);
			goaway(z_root() . '/display/' . gen_link_id($item['mid']));
		}
	}
	else {
		return;
	}

	as_actor_store($url, $person_obj);

	if ($address) {
		q("update xchan set xchan_addr = '%s' where xchan_hash = '%s' and xchan_network = 'activitypub'",
			dbesc($address),
			dbesc($url)
		);
		q("update hubloc set hubloc_addr = '%s' where hubloc_hash = '%s' and hubloc_network = 'activitypub'",
			dbesc($address),
			dbesc($url)
		);
	}

	$b['xchan']   = $url;
	$b['success'] = true;

}

function pubcrawl_import_author(&$b) {

	$url     = $b['author']['url'];
	$channel = (($b['channel']) ? $b['channel'] : null);

	if (!$url)
		return;

	$r = q("select xchan_hash from xchan where xchan_hash = '%s' and xchan_network = 'activitypub'",
		dbesc($url)
	);
	if ($r) {
		logger('in_cache: ' . $r[0]['xchan_name'], LOGGER_DATA);
		$b['result'] = $r[0]['xchan_hash'];
		return;
	}

	$x = discover_by_webbie($url);

	if ($x) {
		$b['result'] = $x;
		return;
	}

	$x = Activity::fetch($url, $channel);
	if (!$x) {
		return;
	}

	$AS = new ActivityStreams($x);

	if (!$AS->is_valid()) {
		return;
	}

	// Now find the actor

	$person_obj = null;
	if (in_array($AS->type, ['Application', 'Group', 'Organization', 'Person', 'Service'])) {
		$person_obj = $AS->data;
	}
	elseif ($AS->obj && (in_array($AS->obj['type'], ['Application', 'Group', 'Organization', 'Person', 'Service']))) {
		$person_obj = $AS->obj;
	}
	else {
		return;
	}

	as_actor_store($url, $person_obj);

	$b['result'] = $url;

}


function pubcrawl_load_module(&$b) {

	//logger('module: ' . \App::$query_string);

	if ($b['module'] === 'inbox') {
		require_once('addon/pubcrawl/Mod_Inbox.php');
		$b['controller'] = new \Zotlabs\Module\Inbox();
		$b['installed']  = true;
	}
	if ($b['module'] === 'outbox') {
		require_once('addon/pubcrawl/Mod_Outbox.php');
		$b['controller'] = new \Zotlabs\Module\Outbox();
		$b['installed']  = true;
	}
	if ($b['module'] === 'nullbox') {
		require_once('addon/pubcrawl/Mod_Nullbox.php');
		$b['controller'] = new \Zotlabs\Module\Nullbox();
		$b['installed']  = true;
	}
	if ($b['module'] === 'ap_probe') {
		require_once('addon/pubcrawl/Mod_Ap_probe.php');
		$b['controller'] = new \Zotlabs\Module\Ap_probe();
		$b['installed']  = true;
	}
	if ($b['module'] === 'followers') {
		require_once('addon/pubcrawl/Mod_Followers.php');
		$b['controller'] = new \Zotlabs\Module\Followers();
		$b['installed']  = true;
	}
	if ($b['module'] === 'following') {
		require_once('addon/pubcrawl/Mod_Following.php');
		$b['controller'] = new \Zotlabs\Module\Following();
		$b['installed']  = true;
	}
}

function pubcrawl_magic_env_allowed() {

	$x = getBestSupportedMimeType([
		'application/magic-envelope+json'
	]);

	return (($x) ? true : false);
}

function pubcrawl_salmon_sign($data, $channel) {

	$data      = base64url_encode($data, false); // do not strip padding
	$data_type = 'application/activity+json';
	$encoding  = 'base64url';
	$algorithm = 'RSA-SHA256';
	$keyhash   = base64url_encode(z_root() . '/channel/' . $channel['channel_address']);

	$data = str_replace([" ", "\t", "\r", "\n"], ["", "", "", ""], $data);

	// precomputed base64url encoding of data_type, encoding, algorithm concatenated with periods

	$precomputed = '.' . base64url_encode($data_type, false) . '.YmFzZTY0dXJs.UlNBLVNIQTI1Ng==';

	$signature = base64url_encode(Crypto::sign($data . $precomputed, $channel['channel_prvkey']));

	return ([
		'data'      => $data,
		'data_type' => $data_type,
		'encoding'  => $encoding,
		'alg'       => $algorithm,
		'sigs'      => [
			'value'  => $signature,
			'key_id' => $keyhash
		]
	]);

}

function pubcrawl_notifier_process(&$arr) {

	if (!$arr['relay_to_owner'])
		return;

	if ($arr['target_item']['item_private'])
		return;

	if ($arr['parent_item']['owner']['xchan_network'] !== 'activitypub')
		return;

	if ($arr['parent_item']['author']['xchan_network'] !== 'activitypub')
		return;

	if (!Apps::addon_app_installed($arr['channel']['channel_id'], 'pubcrawl'))
		return;

	// If the parent is an announce activity, add the author to the recipients
	if ($arr['parent_item']['verb'] === ACTIVITY_SHARE) {
		$arr['env_recips'][] = [
			'guid'     => $arr['parent_item']['author']['xchan_guid'],
			'guid_sig' => $arr['parent_item']['author']['xchan_guid_sig'],
			'hash'     => $arr['parent_item']['author']['xchan_hash']
		];
		$arr['recipients'][] = '\'' . $arr['parent_item']['author']['xchan_hash'] . '\'';
	}


	// deliver to local subscribers directly
	$sys = get_sys_channel();

	$arr['env_recips'][] = [
		'guid'     => $sys['channel_guid'],
		'guid_sig' => $sys['channel_guid_sig'],
		'hash'     => $sys['channel_hash']
	];
	$arr['recipients'][] = '\'' . $sys['channel_hash'] . '\'';

	$r = q("SELECT channel_guid, channel_guid_sig, channel_hash FROM channel WHERE channel_id IN ( SELECT abook_channel FROM abook WHERE abook_xchan = '%s' AND abook_channel != %d )",
		dbesc($arr['target_item']['owner_xchan']),
		intval($arr['channel']['channel_id'])
	);
	if ($r) {
		foreach ($r as $rr) {
			$arr['env_recips'][] = [
				'guid'     => $rr['channel_guid'],
				'guid_sig' => $rr['channel_guid_sig'],
				'hash'     => $rr['channel_hash']
			];
			$arr['recipients'][] = '\'' . $rr['channel_hash'] . '\'';
		}
	}

}

function pubcrawl_notifier_hub(&$arr) {

	if ($arr['hub']['hubloc_network'] !== 'activitypub')
		return;

	$allowed = Apps::addon_app_installed($arr['channel']['channel_id'], 'pubcrawl');
	if (!$allowed) {
		logger('pubcrawl: disallowed for channel ' . $arr['channel']['channel_name']);
		return;
	}

	logger('upstream: ' . intval($arr['upstream']));
	logger('notifier_array: ' . print_r($arr, true), LOGGER_ALL, LOG_INFO);

	// allow this to be set per message

	if ($arr['mail']) {
		logger('Cannot send mail to activitypub.');
		return;
	}

	if ($arr['location'])
		return;

	$is_profile = false;
	if ($arr['cmd'] == 'refresh_all')
		$is_profile = true;

	$target_item = [];
	if (array_key_exists('target_item', $arr) && is_array($arr['target_item']))
		$target_item = $arr['target_item'];

	if (!$target_item['mid'] && !$is_profile)
		return;

	$signed_msg = null;

	if ($target_item) {
		if (intval($arr['target_item']['item_obscured'])) {
			logger('Cannot send raw data as an activitypub activity.');
			return;
		}

		if (strpos($arr['target_item']['postopts'], 'nopub') !== false) {
			return;
		}

		// don't forward guest comments to activitypub at the moment
		if (strpos($arr['target_item']['author']['xchan_url'], z_root() . '/guest/') !== false) {
			return;
		}

		// If we have an activity already stored with an LD-signature
		// which we are sending downstream, use that signed activity as is.
		// The channel will then sign the HTTP transaction.
		if ($arr['channel']['channel_hash'] != $arr['target_item']['author_xchan']) {
			// Our relayed Likes etc. do not seem to be accepted/displayed by any platform so far.
			// Some return code 200 but do not display it (masto) others return 400 (pleroma).
			// If the return code is 400 or 500 they tend to stuff up the  queue basically for nothing.
			if(in_array($arr['target_item']['verb'], [ACTIVITY_LIKE, ACTIVITY_DISLIKE]))
				return;

			$signed_msg = get_iconfig($arr['target_item'], 'activitypub', 'rawmsg');

			// If we don't have a signed message and we are not the author,
			// the message will be misattributed in mastodon
			if(! $signed_msg) {
				logger('relayed post with no signed message');
				return;
			}
		}

	}

	$prv_recips = $arr['env_recips'];

	if(is_array($signed_msg)) {
		// If it's an array it is probably an encrypted zot6 package
		// which are in the wild due to a bug before 5.4.
		// Probably in this case it's the best to just unset it.
		logger('Signed message is array: ' . print_r($signed_msg, true), LOGGER_DEBUG);
		$signed_msg = '';
	}

	if (is_string($signed_msg)) {
		$jmsg = $signed_msg;
	}

	if ($target_item && !$signed_msg) {
		$ti = asencode_activity($target_item);
		if (!$ti)
			return;

		$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]], $ti);

		$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg, $arr['channel']);
		$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);
	}

	if ($is_profile) {
		$p = asencode_person($arr['channel']);
		if (!$p)
			return;

		$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]],
			[
				'id'     => $arr['channel']['xchan_url'],
				'type'   => 'Update',
				'actor'  => $arr['channel']['xchan_url'],
				'object' => $p,
				'to'     => [z_root() . '/followers/' . $arr['channel']['channel_address']]
			]);

		$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg, $arr);
		$jmsg             = json_encode($msg, JSON_UNESCAPED_SLASHES);
	}

	if ($prv_recips) {
		$hashes = [];

		// re-explode the recipients, but only for this hub/pod

		foreach ($prv_recips as $recip)
			$hashes[] = "'" . dbesc($recip['hash']) . "'";

		$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s'
			and xchan_hash in (" . protect_sprintf(implode(',', $hashes)) . ") and xchan_network = 'activitypub'",
			dbesc($arr['hub']['hubloc_url'])
		);

		if (!$r) {
			logger('activitypub_process_outbound: no recipients');
			return;
		}

		foreach ($r as $contact) {

			// is $contact connected with this channel - and if the channel is cloned, also on this hub?
			$single = deliverable_singleton($arr['channel']['channel_id'], $contact);

			if (!$arr['normal_mode'])
				continue;

			if ($single || $arr['upstream']) {
				$qi = pubcrawl_queue_message($jmsg, $arr['channel'], $contact, $target_item['mid']);
				if ($qi)
					$arr['queued'][] = $qi;
			}
			continue;
		}

	}
	else {

		// public message

		// See if we can deliver all of them at once

		$x = get_xconfig($arr['hub']['hubloc_hash'], 'activitypub', 'collections');
		if ($x && $x['sharedInbox']) {
			logger('using publicInbox delivery for ' . $arr['hub']['hubloc_url'], LOGGER_DEBUG);
			$contact['hubloc_callback'] = $x['sharedInbox'];
			$qi = pubcrawl_queue_message($jmsg, $arr['channel'], $contact, $target_item['mid']);
			if ($qi) {
				$arr['queued'][] = $qi;
			}
		}
		else {

			$r = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_url = '%s' and xchan_network = 'activitypub' ",
				dbesc($arr['hub']['hubloc_url'])
			);

			if (!$r) {
				logger('activitypub_process_outbound: no recipients');
				return;
			}


			foreach ($r as $contact) {

				$single = deliverable_singleton($arr['channel']['channel_id'], $contact);

				if ($single) {
					$qi = pubcrawl_queue_message($jmsg, $arr['channel'], $contact, $target_item['mid']);
					if ($qi)
						$arr['queued'][] = $qi;
				}
			}
		}
	}

	return;

}


function pubcrawl_queue_message($msg, $sender, $recip, $message_id = '') {

	$allowed = Apps::addon_app_installed($sender['channel_id'], 'pubcrawl');

	if (!intval($allowed)) {
		return false;
	}

	$dest_url = $recip['hubloc_callback'];

	logger('URL: ' . $dest_url, LOGGER_DEBUG);
	logger('DATA: ' . jindent($msg), LOGGER_DATA);

	if (intval(get_config('system', 'activitypub_test')) || intval(get_pconfig($sender['channel_id'], 'system', 'activitypub_test'))) {
		logger('test mode - delivery disabled');
		return false;
	}

	$hash = random_string();

	logger('queue: ' . $hash . ' ' . $dest_url, LOGGER_DEBUG);
	logger('queueMsg: ' . jindent($msg));

	queue_insert([
		'hash'       => $hash,
		'account_id' => $sender['channel_account_id'],
		'channel_id' => $sender['channel_id'],
		'driver'     => 'pubcrawl',
		'posturl'    => $dest_url,
		'notify'     => '',
		'msg'        => $msg
	]);

	if ($message_id && (!get_config('system', 'disable_dreport'))) {
		q("insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_name, dreport_result, dreport_time, dreport_xchan, dreport_queue ) values ( '%s','%s','%s','%s','%s','%s','%s', '%s' ) ",
			dbesc($message_id),
			dbesc($dest_url),
			dbesc($dest_url),
			dbesc($dest_url),
			dbesc('queued'),
			dbesc(datetime_convert()),
			dbesc($sender['channel_hash']),
			dbesc($hash)
		);
	}

	return $hash;

}


function pubcrawl_connection_remove(&$x) {

	$recip = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
		intval($x['abook_id'])
	);

	if ((!$recip) || $recip[0]['xchan_network'] !== 'activitypub')
		return;

	$channel = channelx_by_n($recip[0]['abook_channel']);
	if (!$channel)
		return;

	$p = $channel['xchan_url']; // asencode_person($channel);
	if (!$p)
		return;

	// send an unfollow activity to the followee's inbox

	$orig_activity = get_abconfig($recip[0]['abook_channel'], $recip[0]['xchan_hash'], 'pubcrawl', 'follow_id');

	if ($orig_activity && $recip[0]['abook_pending']) {


		// was never approved

		$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV

		]],
			[
				'id'     => z_root() . '/follow/' . $recip[0]['abook_id'] . '#reject',
				'type'   => 'Reject',
				'actor'  => $p,
				'object' => [
					'type'   => 'Follow',
					'id'     => $orig_activity,
					'actor'  => $recip[0]['xchan_hash'],
					'object' => $p
				],
				'to'     => [$recip[0]['xchan_hash']]
			]);
		del_abconfig($recip[0]['abook_channel'], $recip[0]['xchan_hash'], 'pubcrawl', 'follow_id');

	}
	else {

		// send an unfollow

		$msg = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]],
			[
				'id'     => z_root() . '/follow/' . $recip[0]['abook_id'] . '#undo',
				'type'   => 'Undo',
				'actor'  => $p,
				'object' => [
					'id'     => z_root() . '/follow/' . $recip[0]['abook_id'],
					'type'   => 'Follow',
					'actor'  => $p,
					'object' => $recip[0]['xchan_hash']
				],
				'to'     => [$recip[0]['xchan_hash']]
			]
		);
	}

	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg, $channel);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

	// is $contact connected with this channel - and if the channel is cloned, also on this hub?
	$single = deliverable_singleton($channel['channel_id'], $recip[0]);

	$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($recip[0]['xchan_hash'])
	);

	if ($single && $h) {
		$qi = pubcrawl_queue_message($jmsg, $channel, $h[0]);
		if ($qi) {
			\Zotlabs\Daemon\Master::Summon(['Deliver', $qi]);
		}
	}

}


function pubcrawl_permissions_create(&$x) {

	// send a follow activity to the followee's inbox

	if ($x['recipient']['xchan_network'] !== 'activitypub') {
		return;
	}

	$p = $x['sender']['xchan_url']; //asencode_person($x['sender']);
	if (!$p)
		return;

	$msg = array_merge(['@context' => [
		ACTIVITYSTREAMS_JSONLD_REV,
		'https://w3id.org/security/v1',
		z_root() . ZOT_APSCHEMA_REV
	]],
		[
			'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'] . '#follow',
			'type'   => 'Follow',
			'actor'  => $p,
			'object' => $x['recipient']['xchan_url'],
			'to'     => [$x['recipient']['xchan_hash']]
		]);


	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg, $x['sender']);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

	// is $contact connected with this channel - and if the channel is cloned, also on this hub?
	$single = deliverable_singleton($x['sender']['channel_id'], $x['recipient']);

	$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($x['recipient']['xchan_hash'])
	);

	if ($single && $h) {
		$qi = pubcrawl_queue_message($jmsg, $x['sender'], $h[0]);
		if ($qi)
			$x['deliveries'] = $qi;
	}

	$x['success'] = true;

}

function pubcrawl_permissions_update(&$x) {

	if ($x['recipient']['xchan_network'] === 'activitypub') {
		q("update xchan set xchan_name_date = '%s' where xchan_hash = '%s' and xchan_network = '%s'",
			dbescdate(NULL_DATE),
			dbesc($x['recipient']['xchan_hash']),
			dbesc('activitypub')
		);
		discover_by_webbie($x['recipient']['xchan_hash'], 'activitypub');
		$x['success'] = 1;
	}
}

function pubcrawl_permissions_accept(&$x) {

	// send an accept activity to the followee's inbox

	if ($x['recipient']['xchan_network'] !== 'activitypub') {
		return;
	}

	// we currently are not handling send of reject follow activities; this is permitted by protocol

	$accept = get_abconfig($x['recipient']['abook_channel'], $x['recipient']['xchan_hash'], 'pubcrawl', 'their_follow_id');
	if (!$accept)
		return;

	$p = $x['sender']['xchan_url']; //asencode_person($x['sender']);
	if (!$p)
		return;

	$msg = array_merge(['@context' => [
		ACTIVITYSTREAMS_JSONLD_REV,
		'https://w3id.org/security/v1',
		z_root() . ZOT_APSCHEMA_REV
	]],
		[
			'id'     => z_root() . '/follow/' . $x['recipient']['abook_id'] . '#accept',
			'type'   => 'Accept',
			'actor'  => $p,
			'object' => [
				'type'   => 'Follow',
				'id'     => $accept,
				'actor'  => $x['recipient']['xchan_hash'],
				'object' => z_root() . '/channel/' . $x['sender']['channel_address']
			],
			'to'     => [$x['recipient']['xchan_hash']]
		]);

	$msg['signature'] = \Zotlabs\Lib\LDSignatures::dopplesign($msg, $x['sender']);

	$jmsg = json_encode($msg, JSON_UNESCAPED_SLASHES);

	// is $contact connected with this channel - and if the channel is cloned, also on this hub?
	$single = deliverable_singleton($x['sender']['channel_id'], $x['recipient']);

	$h = q("select * from hubloc where hubloc_hash = '%s' limit 1",
		dbesc($x['recipient']['xchan_hash'])
	);

	if ($single && $h) {
		$qi = pubcrawl_queue_message($jmsg, $x['sender'], $h[0]);
		if ($qi)
			$x['deliveries'] = $qi;
	}

	$x['success'] = true;

	$perms       = \Zotlabs\Access\PermissionRoles::role_perms('social');
	$their_perms = \Zotlabs\Access\Permissions::FilledPerms($perms['perms_connect']);

	// We accepted their follow request - set default permissions
	foreach ($their_perms as $k => $v) {
		set_abconfig($x['sender']['channel_id'], $x['recipient']['abook_xchan'], 'their_perms', $k, $v);
	}
}


function pubcrawl_thing_mod_init($x) {

	if (ActivityStreams::is_as_request()) {
		$item_id = argv(1);
		if (!$item_id)
			return;

		$r = q("select * from obj where obj_type = %d and obj_obj = '%s' limit 1",
			intval(TERM_OBJ_THING),
			dbesc($item_id)
		);

		if (!$r)
			return;

		$chan = channelx_by_n($r[0]['obj_channel']);

		if (!$chan)
			http_status_exit(404, 'Not found');

		$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]],
			[
				'type' => 'Object',
				'id'   => z_root() . '/thing/' . $r[0]['obj_obj'],
				'name' => $r[0]['obj_term']
			]
		);

		if ($r[0]['obj_image'])
			$x['image'] = $r[0]['obj_image'];


		$headers                     = [];
		$headers['Content-Type']     = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
		$x['signature']              = \Zotlabs\Lib\LDSignatures::dopplesign($x, $chan);
		$ret                         = json_encode($x, JSON_UNESCAPED_SLASHES);
		$headers['Date']             = datetime_convert('UTC', 'UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T');
		$headers['Digest']           = HTTPSig::generate_digest_header($ret);
		$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];

		$h = HTTPSig::create_sig($headers, $chan['channel_prvkey'], channel_url($chan));
		HTTPSig::set_headers($h);
		echo $ret;
		killme();
	}
}


function pubcrawl_locs_mod_init($x) {

	if (ActivityStreams::is_as_request()) {
		$channel_address = argv(1);
		if (!$channel_address)
			return;

		$chan = channelx_by_nick($channel_address);

		if (!$chan)
			http_status_exit(404, 'Not found');

		$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]],
			[
				'type' => 'nomadicHubs',
				'id'   => z_root() . '/locs/' . $chan['channel_address']
			]
		);

		$locs = zot_encode_locations($chan);
		if ($locs) {
			$x['nomadicLocations'] = [];
			foreach ($locs as $loc) {
				$x['nomadicLocations'][] = [
					'id'              => $loc['url'] . '/locs/' . substr($loc['address'], 0, strpos($loc['address'], '@')),
					'type'            => 'nomadicLocation',
					'locationAddress' => 'acct:' . $loc['address'],
					'locationPrimary' => (boolean)$loc['primary'],
					'locationDeleted' => (boolean)$loc['deleted']
				];
			}
		}

		$headers                     = [];
		$headers['Content-Type']     = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
		$x['signature']              = \Zotlabs\Lib\LDSignatures::dopplesign($x, $chan);
		$ret                         = json_encode($x, JSON_UNESCAPED_SLASHES);
		$headers['Date']             = datetime_convert('UTC', 'UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T');
		$headers['Digest']           = HTTPSig::generate_digest_header($ret);
		$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];

		$h = HTTPSig::create_sig($headers, $chan['channel_prvkey'], channel_url($chan));
		HTTPSig::set_headers($h);
		echo $ret;
		killme();
	}
}


function pubcrawl_follow_mod_init($x) {

	if (ActivityStreams::is_as_request() && argc() == 2) {
		$abook_id = intval(argv(1));
		if (!$abook_id)
			return;
		$r = q("select * from abook left join xchan on abook_xchan = xchan_hash where abook_id = %d",
			intval($abook_id)
		);
		if (!$r)
			return;

		$chan = channelx_by_n($r[0]['abook_channel']);

		if (!$chan)
			http_status_exit(404, 'Not found');

		$actor = $chan['xchan_url']; //asencode_person($chan);
		if (!$actor)
			http_status_exit(404, 'Not found');


		$x = array_merge(['@context' => [
			ACTIVITYSTREAMS_JSONLD_REV,
			'https://w3id.org/security/v1',
			z_root() . ZOT_APSCHEMA_REV
		]],
			[
				'id'     => z_root() . '/follow/' . $r[0]['abook_id'] . '#follow',
				'type'   => 'Follow',
				'actor'  => $actor,
				'object' => $r[0]['xchan_url']
			]);


		$headers                     = [];
		$headers['Content-Type']     = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
		$x['signature']              = \Zotlabs\Lib\LDSignatures::dopplesign($x, $chan);
		$ret                         = json_encode($x, JSON_UNESCAPED_SLASHES);
		$headers['Date']             = datetime_convert('UTC', 'UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T');
		$headers['Digest']           = HTTPSig::generate_digest_header($ret);
		$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];

		$h = HTTPSig::create_sig($headers, $chan['channel_prvkey'], channel_url($chan));
		HTTPSig::set_headers($h);
		echo $ret;
		killme();
	}

}


function pubcrawl_queue_deliver(&$b) {

	$outq      = $b['outq'];
	$base      = $b['base'];
	$immediate = $b['immediate'];


	if ($outq['outq_driver'] === 'pubcrawl') {
		$b['handled'] = true;

		$chan = channelx_by_n($outq['outq_channel']);

		$retries = 0;
		$m       = parse_url($outq['outq_posturl']);

		$headers                     = [];
		$headers['Content-Type']     = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
		$ret                         = $outq['outq_msg'];
		$headers['Date']             = datetime_convert('UTC', 'UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T');
		$headers['Host']             = $m['host'];
		$headers['Digest']           = HTTPSig::generate_digest_header($ret);
		$headers['(request-target)'] = 'post ' . get_request_string($outq['outq_posturl']);

		$xhead = HTTPSig::create_sig($headers, $chan['channel_prvkey'], channel_url($chan));

		$result = z_post_url($outq['outq_posturl'], $outq['outq_msg'], $retries, ['headers' => $xhead]);

		if ($result['success'] && $result['return_code'] < 300) {
			logger('deliver: queue post success to ' . $outq['outq_posturl'], LOGGER_DEBUG);
			if ($base) {
				q("update site set site_update = '%s', site_dead = 0 where site_url = '%s' ",
					dbesc(datetime_convert()),
					dbesc($base)
				);
			}
			q("update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s'",
				dbesc('accepted for delivery'),
				dbesc(datetime_convert()),
				dbesc($outq['outq_hash'])
			);
			remove_queue_item($outq['outq_hash']);

			// server is responding - see if anything else is going to this destination and is piled up
			// and try to send some more. We're relying on the fact that do_delivery() results in an
			// immediate delivery otherwise we could get into a queue loop.

			if (!$immediate) {
				$x = q("select outq_hash from outq where outq_posturl = '%s' and outq_delivered = 0",
					dbesc($outq['outq_posturl'])
				);

				$piled_up = [];
				if ($x) {
					foreach ($x as $xx) {
						$piled_up[] = $xx['outq_hash'];
					}
				}
				if ($piled_up) {
					do_delivery($piled_up, true);
				}
			}
		}
		else {
			logger('pubcrawl_queue_deliver: queue post returned ' . $result['return_code'] . ' from ' . $outq['outq_posturl'], LOGGER_DEBUG);
			update_queue_item($outq['outq_hash'], 10);
		}
	}
}

function pubcrawl_create_identity($b) {

	if (get_config('system', 'activitypub_allowed')) {
		Apps::app_install($b, 'Activitypub Protocol');
	}

}

