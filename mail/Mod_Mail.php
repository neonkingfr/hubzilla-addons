<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

require_once('include/bbcode.php');

class Mail extends Controller {

	function init() {

	}

	function get() {

		$o = '';
		nav_set_selected('Mail');

		if(! local_channel()) {
			notice( t('Permission denied.') . EOL);
			return login();
		}

		$channel = \App::get_channel();

		head_set_icon($channel['xchan_photo_s']);

		$cipher = get_pconfig(local_channel(),'system','default_cipher');
		if(! $cipher)
			$cipher = 'aes256';

		$tpl = get_markup_template('mail_head.tpl');
		$header = replace_macros($tpl, array(
			'$header' => t('Messages'),
		));

		if(argc() == 3 && intval(argv(1)) && argv(2) === 'download') {

			$r = q("select * from mail left join xchan on xchan_hash = from_xchan where id = %d and channel_id = %d",
				intval(argv(1)),
				intval(local_channel())
			);

			if($r) {
				header('Content-type: ' . $r[0]['mail_mimetype']);
				header('Content-disposition: attachment; filename="' . t('message') . '-' . $r[0]['id'] . '"' );
				$author = $r[0]['xchan_name'] . ' <' . $r[0]['xchan_addr'] . '>' . "\r\n";
				$subject = (($r[0]['mail_obscured']) ? base64url_decode(str_rot47($r[0]['title'])) : $r[0]['title']) . "\r\n\r\n";
				$body = (($r[0]['mail_obscured']) ? base64url_decode(str_rot47($r[0]['body'])) : $r[0]['body']);

				echo $author . $subject . $body;
				killme();
			}

		}

		if((argc() == 4) && (argv(2) === 'drop')) {
			if(! intval(argv(3)))
				return;
			$cmd = argv(2);
			$mailbox = argv(1);
			$r = self::private_messages_drop(local_channel(), argv(3));
			goaway(z_root() . '/mail/' . $mailbox);
		}

		if((argc() == 4) && (argv(2) === 'dropconv')) {
			if(! intval(argv(3)))
				return;
			$cmd = argv(2);
			$mailbox = argv(1);
			$r = self::private_messages_drop(local_channel(), argv(3), true);
			if($r)
				info( t('Conversation removed.') . EOL );
			goaway(z_root() . '/mail/' . $mailbox);
		}

		$direct_mid = 0;

		switch(argv(1)) {
			case 'combined':
				$mailbox = 'combined';
				break;
			case 'inbox':
				$mailbox = 'inbox';
				break;
			case 'outbox':
				$mailbox = 'outbox';
				break;
			default:
				$mailbox = 'combined';

				// notifications direct to mail/nn

				if(intval(argv(1)))
					$direct_mid = intval(argv(1));
				break;
		}

		$c = new \Zotlabs\Widget\Conversations;
		$last_message = $c->private_messages_list(local_channel(), $mailbox, 0, 1);

		$mid = ((argc() > 2) && (intval(argv(2)))) ? argv(2) : $last_message[0]['id'];

		if($direct_mid)
			$mid = $direct_mid;


		$plaintext = true;

		if($mailbox == 'combined') {
			$messages = self::private_messages_fetch_conversation(local_channel(), $mid, true);
		}
		else {
			$messages = self::private_messages_fetch_message(local_channel(), $mid, true);
		}

		if(! $messages) {
			//info( t('Message not found.') . EOL);
			return;
		}

		if($messages[0]['to_xchan'] === $channel['channel_hash'])
			\App::$poi = $messages[0]['from'];
		else
			\App::$poi = $messages[0]['to'];

		$tpl = get_markup_template('msg-header.tpl');

		\App::$page['htmlhead'] .= replace_macros($tpl, array(
			'$nickname' => $channel['channel_address'],
			'$baseurl' => z_root(),
			'$editselect' => (($plaintext) ? 'none' : '/(profile-jot-text|prvmail-text)/'),
			'$linkurl' => t('Please enter a link URL:'),
			'$expireswhen' => t('Expires YYYY-MM-DD HH:MM')
		));

		$mails = array();

		$seen = 0;
		$unknown = false;

		foreach($messages as $message) {

			$s = theme_attachments($message);

			if($message['mail_raw'])
				$message['body'] = self::mail_prepare_binary([ 'id' => $message['id'] ]);
			else
				$message['body'] = zidify_links(smilies(bbcode($message['body'])));

			$mails[] = array(
				'mailbox' => $mailbox,
				'id' => $message['id'],
				'mid' => $message['mid'],
				'from_name' => $message['from']['xchan_name'],
				'from_url' =>  chanlink_hash($message['from_xchan']),
				'from_photo' => $message['from']['xchan_photo_s'],
				'to_name' => $message['to']['xchan_name'],
				'to_url' =>  chanlink_hash($message['to_xchan']),
				'to_photo' => $message['to']['xchan_photo_s'],
				'subject' => $message['title'],
				'body' => $message['body'],
				'attachments' => $s,
				'download' => t('Download'),
				'delete' => t('Delete message'),
				'dreport' => t('Delivery report'),
				'recall' => t('Recall message'),
				'can_recall' => ($channel['channel_hash'] == $message['from_xchan']),
				'is_recalled' => (intval($message['mail_recalled']) ? t('Message has been recalled.') : ''),
				'date' => datetime_convert('UTC',date_default_timezone_get(),$message['created'], 'c'),
				'sig' => base64_encode($message['sig'])
			);

			$seen = $message['seen'];

		}

		$recp = (($message['from_xchan'] === $channel['channel_hash']) ? 'to' : 'from');

		$tpl = get_markup_template('mail_display.tpl');
		$o = replace_macros($tpl, array(
			'$mailbox' => $mailbox,
			'$prvmsg_header' => $message['title'],
			'$thread_id' => $mid,
			'$thread_subject' => $message['title'],
			'$thread_seen' => $seen,
			'$delete' =>  t('Delete Conversation'),
			'$canreply' => (($unknown) ? false : '1'),
			'$unknown_text' => t("No secure communications available. You <strong>may</strong> be able to respond from the sender's profile page."),
			'$mails' => $mails,

			// reply
			'$header' => t('Send Reply'),
			'$to' => t('To:'),
			'$reply' => true,
			'$subject' => t('Subject:'),
			'$subjtxt' => $message['title'],
			'$yourmessage' => sprintf(t('Your message for %s (%s):'), $message[$recp]['xchan_name'], $message[$recp]['xchan_addr']),
			'$text' => '',
			'$parent' => $message['parent_mid'],
			'$recphash' => $message[$recp]['xchan_hash'],
			'$attach' => t('Attach file'),
			'$insert' => t('Insert web link'),
			'$submit' => t('Submit'),
			'$defexpire' => '',
			'$feature_expire' => ((feature_enabled(local_channel(),'content_expire')) ? true : false),
			'$expires' => t('Set expiration date'),
			'$feature_encrypt' => ((feature_enabled(local_channel(),'content_encrypt')) ? true : false),
			'$encrypt' => t('Encrypt text'),
			'$cipher' => $cipher,
		));

		return $o;
	}

	function private_messages_drop($channel_id, $messageitem_id, $drop_conversation = false) {

		$x = q("select * from mail where id = %d and channel_id = %d limit 1",
			intval($messageitem_id),
			intval($channel_id)
		);
		if(! $x)
			return false;

		$conversation = null;

		if($x[0]['conv_guid']) {
			$y = q("select * from conv where guid = '%s' and uid = %d limit 1",
				dbesc($x[0]['conv_guid']),
				intval($channel_id)
			);
			if($y) {
				$conversation = $y[0];
				$conversation['subject'] = base64url_decode(str_rot47($conversation['subject']));
			}
		}

		if($drop_conversation) {
			$m = array();
			$m['conv'] = array($conversation);
			$m['conv'][0]['deleted'] = 1;

			$z = q("select * from mail where parent_mid = '%s' and channel_id = %d",
				dbesc($x[0]['parent_mid']),
				intval($channel_id)
			);
			if($z) {
				if($x[0]['conv_guid']) {
					q("delete from conv where guid = '%s' and uid = %d",
						dbesc($x[0]['conv_guid']),
						intval($channel_id)
					);
				}
				q("DELETE FROM mail WHERE parent_mid = '%s' AND channel_id = %d ",
					dbesc($x[0]['parent_mid']),
					intval($channel_id)
				);
			}
			return true;
		}
		else {
			$x[0]['mail_deleted'] = true;
			self::msg_drop($messageitem_id, $channel_id, $x[0]['conv_guid']);
			return true;
		}
		return false;

	}

	function msg_drop($message_id, $channel_id, $conv_guid) {

		// Delete message
		$r = q("DELETE FROM mail WHERE id = %d AND channel_id = %d",
			intval($message_id),
			intval($channel_id)
		);

		// Get new first message...
		$r = q("SELECT mid, parent_mid FROM mail WHERE conv_guid = '%s' AND channel_id = %d ORDER BY id ASC LIMIT 1",
			dbesc($conv_guid),
			intval($channel_id)
		);
		// ...and if wasn't first before...
		if ($r[0]['mid'] != $r[0]['parent_mid']) {
			// ...refer whole thread to it
			q("UPDATE mail SET parent_mid = '%s', mail_isreply = abs(mail_isreply - 1) WHERE conv_guid = '%s' AND channel_id = %d",
				dbesc($r[0]['mid']),
				dbesc($conv_guid),
				intval($channel_id)
			);
		}

	}

	function private_messages_fetch_message($channel_id, $messageitem_id, $updateseen = false) {

		$messages = q("select * from mail where id = %d and channel_id = %d order by created asc",
			dbesc($messageitem_id),
			intval($channel_id)
		);

		if(! $messages)
			return array();

		$chans = array();
		foreach($messages as $rr) {
			$s = "'" . dbesc(trim($rr['from_xchan'])) . "'";
			if(! in_array($s,$chans))
				$chans[] = $s;
			$s = "'" . dbesc(trim($rr['to_xchan'])) . "'";
			if(! in_array($s,$chans))
				$chans[] = $s;
		}

		$c = q("select * from xchan where xchan_hash in (" . protect_sprintf(implode(',',$chans)) . ")");

		foreach($messages as $k => $message) {
			$messages[$k]['from'] = find_xchan_in_array($message['from_xchan'],$c);
			$messages[$k]['to']   = find_xchan_in_array($message['to_xchan'],$c);
			if(intval($messages[$k]['mail_obscured'])) {
				if($messages[$k]['title'])
					$messages[$k]['title'] = base64url_decode(str_rot47($messages[$k]['title']));
				if($messages[$k]['body'])
					$messages[$k]['body'] = base64url_decode(str_rot47($messages[$k]['body']));
			}
		}

		if($updateseen) {
			$r = q("UPDATE mail SET mail_seen = 1 where mail_seen = 0 and id = %d AND channel_id = %d",
				dbesc($messageitem_id),
				intval($channel_id)
			);
		}

		return $messages;

	}

	function private_messages_fetch_conversation($channel_id, $messageitem_id, $updateseen = false) {

		// find the parent_mid of the message being requested

		$r = q("SELECT parent_mid from mail WHERE channel_id = %d and id = %d limit 1",
			intval($channel_id),
			intval($messageitem_id)
		);

		if(! $r)
			return array();

		$messages = q("select * from mail where parent_mid = '%s' and channel_id = %d order by created asc",
			dbesc($r[0]['parent_mid']),
			intval($channel_id)
		);

		if(! $messages)
			return array();

		$chans = array();
		foreach($messages as $rr) {
			$s = "'" . dbesc(trim($rr['from_xchan'])) . "'";
			if(! in_array($s,$chans))
				$chans[] = $s;
			$s = "'" . dbesc(trim($rr['to_xchan'])) . "'";
			if(! in_array($s,$chans))
				$chans[] = $s;
		}

		$c = q("select * from xchan where xchan_hash in (" . protect_sprintf(implode(',',$chans)) . ")");

		foreach($messages as $k => $message) {
			$messages[$k]['from'] = find_xchan_in_array($message['from_xchan'],$c);
			$messages[$k]['to']   = find_xchan_in_array($message['to_xchan'],$c);
			if(intval($messages[$k]['mail_obscured'])) {
				if($messages[$k]['title'])
					$messages[$k]['title'] = base64url_decode(str_rot47($messages[$k]['title']));
				if($messages[$k]['body'])
					$messages[$k]['body'] = base64url_decode(str_rot47($messages[$k]['body']));
			}
			if($messages[$k]['mail_raw'])
				$messages[$k]['body'] = self::mail_prepare_binary([ 'id' => $messages[$k]['id'] ]);

		}

		if($updateseen) {
			$r = q("UPDATE mail SET mail_seen = 1 where mail_seen = 0 and parent_mid = '%s' AND channel_id = %d",
				dbesc($r[0]['parent_mid']),
				intval($channel_id)
			);
		}

		return $messages;

	}

function mail_prepare_binary($item) {

	return replace_macros(get_markup_template('item_binary.tpl'), [
		'$download'  => t('Download binary/encrypted content'),
		'$url'       => z_root() . '/mail/' . $item['id'] . '/download'
	]);
}

}
