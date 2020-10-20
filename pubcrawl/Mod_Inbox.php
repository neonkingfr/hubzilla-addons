<?php
namespace Zotlabs\Module;

use Zotlabs\Web\HTTPSig;


class Inbox extends \Zotlabs\Web\Controller {

	function post() {

		logger('Inbox: ' . \App::$query_string);

		$sys_disabled = false;

		if(get_config('system','disable_discover_tab') || get_config('system','disable_activitypub_discover_tab')) {
			$sys_disabled = true;
		}

		$data = file_get_contents('php://input');
		if(! $data)
			return;

		logger('inbox_activity: ' . jindent($data), LOGGER_DATA);

		$hsig = HTTPSig::verify($data);

		$AS = new \Zotlabs\Lib\ActivityStreams($data);

		if ($AS->is_valid() && $AS->type === 'Announce' && is_array($AS->obj)
			&& array_key_exists('object',$AS->obj) && array_key_exists('actor',$AS->obj)) {
			// This is a relayed/forwarded Activity (as opposed to a shared/boosted object)
			// Reparse the encapsulated Activity and use that instead
			logger('relayed activity',LOGGER_DEBUG);
			$AS = new ActivityStreams($AS->obj);
		}

		// logger('debug: ' . $AS->debug());

		if (! $AS->is_valid()) {
			if ($AS->deleted) {
				// process mastodon user deletion activities, but only if we can validate the signature
				if ($hsig['header_valid'] && $hsig['content_valid'] && $hsig['portable_id']) {
					logger('removing deleted actor');
					remove_all_xchan_resources($hsig['portable_id']);
				}
				else {
					logger('ignoring deleted actor', LOGGER_DEBUG, LOG_INFO);
				}
			}
			return;
		}


		// $observer_hash in this case is the sender

		if ($hsig['header_valid'] && $hsig['content_valid'] && $hsig['portable_id']) {
			$observer_hash = $hsig['portable_id'];
			// fetch the portable_id for the actor, which may or may not be the sender
			$v = q("select hubloc_hash from hubloc where hubloc_id_url = '%s' or hubloc_hash = '%s'",
				dbesc($AS->actor['id']),
				dbesc($AS->actor['id'])
			);
			// only allow relayed activities if the activity is signed with LDSigs
			// AND the signature is valid AND the signer is the actor.
			if ($v && $v[0]['hubloc_hash'] !== $observer_hash) {
				if ($AS->signer && $AS->signer !== $AS->actor['id']) {
					return;
				}
				if (! $AS->sigok) {
					return;
				}
			}
		}
		else {
			$observer_hash = $AS->actor['id'];
		}

		if (! $observer_hash) {
			return;
		}

		// update the hubloc_connected timestamp, ignore failures
		q("update hubloc set hubloc_connected = '%s' where hubloc_hash = '%s' and hubloc_network = 'activitypub'",
			dbesc(datetime_convert()),
			dbesc($observer_hash)
		);

		$m = parse_url($observer_hash);
		if ($m['scheme'] && $m['host']) {
			q("update site set site_dead = 0 where site_dead = 1 and site_url = '%s'",
				dbesc($m['scheme'] . '://' . $m['host'])
			);
		}

		if($AS->type == 'Update' && $AS->obj['type'] == 'Person') {
			$x['recipient']['xchan_network'] = 'activitypub';
			$x['recipient']['xchan_hash'] = $observer_hash;
			pubcrawl_permissions_update($x);
			return;
		}

		if(is_array($AS->actor) && array_key_exists('id',$AS->actor))
			as_actor_store($AS->actor['id'],$AS->actor);

		if($AS->type == 'Announce' && is_array($AS->obj) && array_key_exists('attributedTo',$AS->obj)) {
			$arr = [];
			$arr['author']['url'] = as_get_attributed_to_person($AS);
			pubcrawl_import_author($arr);
		}

		$is_public = false;

		if(argc() == 1 || argv(1) === '[public]') {
			$is_public = true;
		}
		else {
			$channels = [ channelx_by_nick(argv(1)) ];
		}


		if($is_public) {
			$parent = ((is_array($AS->obj) && array_key_exists('inReplyTo',$AS->obj)) ? urldecode($AS->obj['inReplyTo']) : '');

			if($parent) {
				// this is a comment - deliver to everybody who owns the parent 
				$channels = q("SELECT * FROM channel WHERE channel_id IN ( SELECT uid FROM item WHERE mid = '%s' OR mid = '%s' )",
					dbesc($parent),
					dbesc(basename($parent))
				);
				// in case we receive a comment to a parent we do not have yet
				// deliver to anybody following $AS->actor and let it fetch the parent
				if(! $channels) {
					$channels = q("SELECT * from channel where channel_id in ( SELECT abook_channel from abook left join xchan on abook_xchan = xchan_hash WHERE xchan_network = 'activitypub' and xchan_hash = '%s' ) and channel_removed = 0 ",
						dbesc($observer_hash)
					);
				}
			}
			else {
				// Pleroma sends follow activities to the publicInbox and therefore requires special handling.

				if($AS->type === 'Follow' && $AS->obj && $AS->obj['type'] === 'Person') {
					$channels = q("SELECT * from channel where channel_address = '%s' and channel_removed = 0 ",
						dbesc(basename($AS->obj['id']))
					);
				}
				else {
					// deliver to anybody following $AS->actor
					$channels = q("SELECT * from channel where channel_id in ( SELECT abook_channel from abook left join xchan on abook_xchan = xchan_hash WHERE xchan_network = 'activitypub' and xchan_hash = '%s' ) and channel_removed = 0 ",
						dbesc($observer_hash)
					);
				}
			}
			if($channels === false)
				$channels = [];


			if(in_array(ACTIVITY_PUBLIC_INBOX,$AS->recips)) {

				// look for channels with send_stream = PERMS_PUBLIC

				$r = q("select * from channel where channel_id in (select uid from pconfig where cat = 'perm_limits' and k = 'send_stream' and v = '1' ) and channel_removed = 0 ");
				if($r) {
					$channels = array_merge($channels,$r);
				}

				if(! $sys_disabled) {
					$channels[] = get_sys_channel();
				}

			}

		}

		if(! $channels)
			return;

		$saved_recips = [];
		foreach( [ 'to', 'cc', 'audience' ] as $x ) {
			if(array_key_exists($x,$AS->data)) {
				$saved_recips[$x] = $AS->data[$x];
			}
		}
		$AS->set_recips($saved_recips);

		foreach($channels as $channel) {

			if(($AS->obj) && (! is_array($AS->obj))) {
				// fetch object using current credentials
				$o = \Zotlabs\Lib\Activity::fetch($AS->obj,$channel);
				if(is_array($o)) {
					$AS->obj = $o;
				}
				else {
					logger('could not fetch object: ' . print_r($AS, true));
					continue;
				}
			}

			switch($AS->type) {
				case 'Follow':
					if($AS->obj && $AS->obj['type'] === 'Person') {
						// do follow activity
						as_follow($channel,$AS);
						break;
					}
					break;
				case 'Accept':
					if($AS->obj && $AS->obj['type'] === 'Follow') {
						// do follow activity
						as_follow($channel,$AS);
						break;
					}
					break;

				case 'Reject':

				default:
					break;

			}


			// These activities require permissions		

			switch($AS->type) {
				case 'Create':
				case 'Update':
				case 'Announce':
					as_create_action($channel,$observer_hash,$AS);
					break;
				case 'Like':
				case 'Dislike':
//				case 'Accept':
//				case 'Reject':
//				case 'TentativeAccept':
//				case 'TentativeReject':
					as_like_action($channel,$observer_hash,$AS);
					break;
				case 'Undo':
					if($AS->obj && $AS->obj['type'] === 'Follow') {
						// do unfollow activity
						as_unfollow($channel,$AS);
						break;
					}
				case 'Delete':
					as_delete_action($channel,$observer_hash,$AS);
					break;
				case 'Add':
				case 'Remove':
					break;

				default:
					break;

			}

		}
		http_status_exit(200,'OK');
	}

	function get() {

	}

}



