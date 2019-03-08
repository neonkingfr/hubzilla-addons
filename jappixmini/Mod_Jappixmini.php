<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Jappixmini extends Controller {

	function post() {
		// save addon settings for a user

		if(! local_channel())
			return;

		$uid = local_channel();

		$account_id = get_account_id();

		if(! $account_id)
			return;

		if(! Apps::addon_app_installed(local_channel(),'jappixmini'))
			return;

		check_form_security_token_redirectOnErr('jappixmini', 'jappixmini');

		$b = $_POST;

		$encrypt = intval($b['jappixmini-encrypt']);
		if ($encrypt) {
			$valid = false;
			// check that Jabber password was encrypted with correct Friendica password
			$friendica_password = trim($b['jappixmini-friendica-password']);

			$x = q("select * from account where account_id = %d limit 1",
				intval($account_id)
			);

			if($x) {
				foreach($x as $record) {
					if(($record['account_flags'] == ACCOUNT_OK) || ($record['account_flags'] == ACCOUNT_UNVERIFIED) && (hash('whirlpool',$record['account_salt'] . $friendica_password) === $record['account_password'])) {
						$valid = true;
						break;
					}
				}
			}

			if (! $valid) {
				info("Hubzilla password not valid.");
				return;
			}
		}

		$purge = intval($b['jappixmini-purge']);

		$username = trim($b['jappixmini-username']);
		$old_username = get_pconfig($uid,'jappixmini','username');
		if ($username!=$old_username)
			$purge = 1;

		$server = trim($b['jappixmini-server']);
		$old_server = get_pconfig($uid,'jappixmini','server');

		if ($server!=$old_server)
			$purge = 1;

		set_pconfig($uid,'jappixmini','username',$username);
		set_pconfig($uid,'jappixmini','server',$server);
		set_pconfig($uid,'jappixmini','bosh',trim($b['jappixmini-bosh']));
		set_pconfig($uid,'jappixmini','password',trim($b['jappixmini-encrypted-password']));
		set_pconfig($uid,'jappixmini','autosubscribe',intval($b['jappixmini-autosubscribe']));
		set_pconfig($uid,'jappixmini','autoapprove',intval($b['jappixmini-autoapprove']));
		set_pconfig($uid,'jappixmini','dontinsertchat',intval($b['jappixmini-dont-insertchat']));
		set_pconfig($uid,'jappixmini','encrypt',$encrypt);
		info( 'Jappix Mini settings saved.' );

		if ($purge) {
			q("DELETE FROM pconfig WHERE uid=$uid AND cat='jappixmini' AND k LIKE 'id:%%'");
			info( 'List of addresses purged.' );
		}

	}


	function get() {

		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'jappixmini')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>' . t('Jappixmini App') . ' (' . t('Not Installed') . '):</b><br>';
			$o .= t('Provides a Facebook-like chat using Jappix Mini');
			return $o;
		}

		$dontinsertchat = get_pconfig(local_channel(),'jappixmini','dontinsertchat');
		$insertchat = ((intval($dontinsertchat)) ? 1 : false);

		$defaultbosh = get_config("jappixmini", "bosh_address");

		if ($defaultbosh != "")
			set_pconfig(local_channel(),'jappixmini','bosh', $defaultbosh);

		$username = get_pconfig(local_channel(),'jappixmini','username');
		$username = htmlentities($username);
		$server = get_pconfig(local_channel(),'jappixmini','server');
		$server = htmlentities($server);
		$bosh = get_pconfig(local_channel(),'jappixmini','bosh');
		$bosh = htmlentities($bosh);
		$password = get_pconfig(local_channel(),'jappixmini','password');
		$autosubscribe = get_pconfig(local_channel(),'jappixmini','autosubscribe');
		$autosubscribe = intval($autosubscribe) ? 1 : false;
		$autoapprove = get_pconfig(local_channel(),'jappixmini','autoapprove');
		$autoapprove = intval($autoapprove) ? 1 : false;
		$encrypt = intval(get_pconfig(local_channel(),'jappixmini','encrypt'));
		$encrypt_checked = $encrypt ? 1 : false;
		$encrypt_disabled = $encrypt ? '' : ' disabled="disabled"';

		if ($server == "")
			$server = get_config("jappixmini", "default_server");

		if (($username == "") and get_config("jappixmini", "default_user"))
			$username = App::$user["nickname"];

		$info_text = get_config("jappixmini", "infotext");
		$info_text = htmlentities($info_text);
		$info_text = str_replace("\n", "<br />", $info_text);

		// count contacts
		$r = q("SELECT COUNT(1) as cnt FROM pconfig WHERE uid=%d AND cat='jappixmini' AND k LIKE 'id:%%'", local_channel());
		if (count($r))
			$contact_cnt = $r[0]["cnt"];
		else
			$contact_cnt = 0;

		// count jabber addresses
		$r = q("SELECT COUNT(1) as cnt FROM pconfig WHERE uid=%d AND cat='jappixmini' AND k LIKE 'id:%%' AND v LIKE '%%@%%'", local_channel());

		if (count($r))
			$address_cnt = $r[0]["cnt"];
		else
			$address_cnt = 0;

		if (! Apps::addon_app_installed(local_channel(), 'jappixmini')) {
			// load scripts if not yet activated so that password can be saved
			App::$page['htmlhead'] .= '<script type="text/javascript" src="' . z_root() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;g=mini.xml"></script>'."\r\n";
			App::$page['htmlhead'] .= '<script type="text/javascript" src="' . z_root() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;f=presence.js~caps.js~name.js~roster.js"></script>'."\r\n";
			App::$page['htmlhead'] .= '<script type="text/javascript" src="' . z_root() . '/addon/jappixmini/lib.js"></script>'."\r\n";
		}

		$sc .= '<div class="section-content-info-wrapper form-group">';
		$sc .= '<strong>' . t('Status:') . '</strong> Addon knows ' . $address_cnt . ' Jabber addresses of ' . $contact_cnt . ' Hubzilla contacts (takes some time, usually 10 minutes, to update).';
		$sc .= '</div>';

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('jappixmini-dont-insertchat', t('Hide Jappixmini Chat-Widget from the webinterface'), $insertchat, '', array(t('No'),t('Yes')))
		));


		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('jappixmini-username', t('Jabber username'), $username, '')
		));



		$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
			'$field'	=> array('jappixmini-server', t('Jabber server'), $server, '')
		));


		if ($defaultbosh == "") {
			$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
				'$field'	=> array('jappixmini-bosh', t('Jabber BOSH host URL'), $bosh, '')
			));
		}

		$sc .= ' <input type="hidden" id="jappixmini-password" name="jappixmini-encrypted-password" value="'.$password.'" />';

		$sc .= replace_macros(get_markup_template('field_password.tpl'), array(
			'$field'	=> array('jappixmini-password-field', t('Jabber password'), '', '', '', 'onchange="jappixmini_set_password();"')
		));

		$onchange = "document.getElementById('id_jappixmini-friendica-password').disabled = !this.checked;jappixmini_set_password();";

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('jappixmini-encrypt', t('Encrypt Jabber password with Hubzilla password'), $encrypt_checked, t('Recommended'), array(t('No'),t('Yes')), 'onchange="' . $onchange . '"')
		));

		$sc .= replace_macros(get_markup_template('field_password.tpl'), array(
			'$field'	=> array('jappixmini-friendica-password', t('Hubzilla password'), '', '', '', $encrypt_disabled . ' onchange="jappixmini_set_password();"')
		));

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('jappixmini-autoapprove', t('Approve subscription requests from Hubzilla contacts automatically'), $autoapprove, '', array(t('No'),t('Yes')))
		));

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('jappixmini-autosubscribe', t('Approve subscription requests from Hubzilla contacts automatically'), $autosubscribe, '', array(t('No'),t('Yes')))
		));

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field'	=> array('jappixmini-purge', t('Purge internal list of jabber addresses of contacts'), '', '', array(t('No'),t('Yes')))
		));

		if ($info_text) {
			$sc .= '<div class="section-content-warning-wrapper">';
			$sc .= '<strong>' . t('Configuration Help') . '</strong><br>' . $info_text;
			$sc .= '</div>';
		}

		$sc .= ' <button class="btn btn-success pull-right" type="button" onclick="jappixmini_addon_subscribe();">' . t('Add Contact') . '</button>';

		App::$page['htmlhead'] .= "<script type=\"text/javascript\">
			function jappixmini_set_password() {
				encrypt = document.getElementById('id_jappixmini-encrypt').checked;
				password = document.getElementById('jappixmini-password');
				clear_password = document.getElementById('id_jappixmini-password-field');
				if (encrypt) {
					friendica_password = document.getElementById('id_jappixmini-friendica-password');

					if (friendica_password) {
						jappixmini_addon_set_client_secret(friendica_password.value);
						jappixmini_addon_encrypt_password(clear_password.value, function(encrypted_password){
							password.value = encrypted_password;
						});
					}
				}
				else {
					password.value = clear_password.value;
				}
			}

			jQuery(document).ready(function() {
				encrypt = document.getElementById('id_jappixmini-encrypt').checked;
				password = document.getElementById('jappixmini-password');
				clear_password = document.getElementById('id_jappixmini-password-field');
				if (encrypt) {
					jappixmini_addon_decrypt_password(password.value, function(decrypted_password){
						clear_password.value = decrypted_password;
					});
				}
				else {
					clear_password.value = password.value;
				}
			});
		</script>";

		$tpl = get_markup_template("settings_addon.tpl");

		$o = replace_macros($tpl, array(
			'$action_url' => 'jappixmini',
			'$form_security_token' => get_form_security_token("jappixmini"),
			'$title' => t('Jappixmini Settings'),
			'$content'  => $sc,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		));

		return $o;

	}

}
