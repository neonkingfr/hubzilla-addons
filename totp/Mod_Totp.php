<?php

namespace Zotlabs\Module;

use Zotlabs\Lib\Apps;

class TOTPController extends \Zotlabs\Web\Controller {
	function totp_installed() {
		$id = local_channel();
		if (!$id) return false;
		return Apps::addon_app_installed($id, 'totp');
		}
	function send_qrcode($account) {
		# generate and deliver QR code png image
		require_once("addon/common/phpqrcode/qrlib.php");
		require_once("addon/totp/class_totp.php");
		$totp = new \TOTP("channels.gnatter.org", "Gnatter Channels",
				$account['account_email'],
				$account['account_2fa_secret'], 30, 6);
		$tmpfile = tempnam(sys_get_temp_dir(), "qr");
		\QRcode::png($totp->uri(), $tmpfile);
		header("content-type: image/png");
		header("content-length: " . filesize($tmpfile));
		echo file_get_contents($tmpfile);
		unlink($tmpfile);
		}
	function get() {
		if (!$this->totp_installed()) return;
		preg_match('/([^\/]+)$/', $_SERVER['REQUEST_URI'], $matches);
		$path = $matches[1];
		$path = preg_replace('/\?.+$/', '', $path);
		$account = \App::get_account();
		if (!$account) goaway(z_root());
		if ($path == "qrcode") {
			$this->send_qrcode($account);
			killme();
			}
		$o .= replace_macros(get_markup_template('totp.tpl','addon/totp'),
			[
			'$header' => t('TOTP Two-Step Verification'),
			'$desc'   => t('Enter the 2-step verification generated by your authenticator app:'),
			'$success' => t('Success!'),
			'$fail' => t('Invalid code, please try again.'),
			'$maxfails' => t('Too many invalid codes...'),
			'$submit' => t('Verify')
			]);
		return $o;
		}
	function post() {
		# AJAX POST handler
		if (!$this->totp_installed())
			json_return_and_die(array("status" => false));
		$account = \App::get_account();
		if (!$account) json_return_and_die(array("status" => false));
		$id = intval($account['account_id']);
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
		json_return_and_die(array("status" => false));
		}
	}
?>
