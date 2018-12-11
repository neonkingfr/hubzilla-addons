<?php

namespace Zotlabs\Module\Settings;

use Zotlabs\Lib\Apps;

class Totp {
	function post() {
		if (!local_channel())
			json_return_and_die(array("status" => false));
		$account = \App::get_account();
		if (!$account) json_return_and_die(array("status" => false));
		$id = intval($account['account_id']);
		if (isset($_POST['active'])) {
			$active = intval($_POST['active']);
			$r = q("update account set account_2fa_active=%d where account_id=%d",
				$active, $id);
			json_return_and_die(array("active" => $active));
			}
		if (isset($_POST['secret'])) {
			require_once("addon/totp/class_totp.php");
			$totp = new \TOTP("channels.gnatter.org", "Gnatter Channels",
					$account['account_email'], null, 30, 6);
			$r = q("update account set account_2fa_secret='%s' where account_id=%d",
					$totp->secret, $id);
			json_return_and_die(array("secret" => $totp->secret));
			}
		if (isset($_POST['totp_code'])) {
			require_once("addon/totp/class_totp.php");
			$ref = intval($_POST['totp_code']);
			$totp = new \TOTP("channels.gnatter.org", "Gnatter Channels",
					$account['account_email'],
					$account['account_2fa_secret'], 30, 6);
			$match = ($totp->authcode($totp->timestamp()) == $ref);
			if ($match) $_SESSION['2FA_VERIFIED'] = true;
			json_return_and_die(array("match" => ($match ? "1" : "0")));
			}
		}
	function get() {
		$id = local_channel();
		if (!$id) return;
		$account = \App::get_account();
		if (!$account) return;
		$acct_id = $account['account_id'];
		require_once("addon/totp/class_totp.php");
		$secret = $account['account_2fa_secret'];
		$totp = new TOTP("channels.gnatter.org", "Gnatter Channels",
				$account['account_email'],
				$secret == "" ? null : $secret, 30, 6);
		$active_checked =
			($account['account_2fa_active'] == 1
				? "checked=\"checked\""
				: "");
		$sc = replace_macros(get_markup_template('settings.tpl',
								'addon/totp'),
				[
				'$checked' => $active_checked,
				'$secret' => $totp->secret,
				'$qrcode_url' => "/totp/qrcode?s=",
				'$salt' => microtime()
				]);
		return replace_macros(get_markup_template('settings_addon.tpl'),
				array(
					'$action_url' => 'settings/totp',
					'$form_security_token' =>
						get_form_security_token("totp"),
					'$title' => t('TOTP Settings'),
					'$content'  => $sc,
					'$baseurl'   => z_root(),
					'$submit'    => '',
					)
			);
		}
	}
