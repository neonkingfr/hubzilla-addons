<?php
/**
 * Name: TOTP
 * Description: TOTP two-factor authentication
 * Version: 0.1
 * Depends: Core
 * Recommends: None
 * Category: authentication
 * Author: Pete Yadlowsky <pm@yadlowsky.us>
 * Maintainer: Pete Yadlowsky <pm@yadlowsky.us>
 */

function totp_module(){};
function totp_load() {
	register_hook('construct_page', 'addon/totp/totp.php',
		'totp_construct_page');
	register_hook('feature_settings', 'addon/totp/totp.php',
		'totp_settings');
	register_hook('feature_settings_post', 'addon/totp/totp.php',
		'totp_settings_post');
	register_hook('logged_in', 'addon/totp/totp.php',
		'totp_logged_in');
	Zotlabs\Extend\Hook::register('module_loaded',
		'addon/totp/totp.php','totp_module_loaded');
	}
function totp_unload() {
	unregister_hook('construct_page', 'addon/totp/totp.php',
		'totp_construct_page');
	unregister_hook('feature_settings', 'addon/totp/totp.php',
		'totp_settings');
	unregister_hook('feature_settings_post', 'addon/totp/totp.php',
		'totp_settings_post');
	unregister_hook('logged_in', 'addon/totp/totp.php',
		'totp_logged_in');
	Zotlabs\Extend\Hook::unregister_by_file('addon/totp/totp.php');
	}
function totp_module_loaded(&$x) {
	if ($x['module'] == 'totp') {
		require_once('addon/totp/Mod_Totp.php');
		$x['controller'] = new \Zotlabs\Module\TOTPController();
		$x['installed'] = true;
		}
	}
function totp_logged_in(&$a, &$user) {
	$file = fopen("/tmp/logged_in", "w");
	fwrite($file, print_r($user, true));
	fclose($file);
	}
function totp_qrcode_png($uri) {
	# generate QR code png file, return relative URL
	require_once("library/phpqrcode/qrlib.php");
	$subdir = "images/qr";
	$tmpfile = tempnam($subdir, "qr");
	unlink($tmpfile);
	$tmpfile .= ".png";
	QRcode::png($uri, $tmpfile);
	preg_match('/([^\/]+)$/', $tmpfile, $matches);
	return "/" . $subdir . "/" . $matches[1];
	}
function totp_post() {
	# AJAX POST handler
	if (!local_channel()) return;
	$account = App::get_account();
	if (!$account) return;
	$id = intval($account['account_id']);
	if (isset($_POST['active'])) {
		$active = intval($_POST['active']);
		$r = q("update account set account_2fa_active=%d where account_id=%d",
			$active, $id);
		json_return_and_die(array("active" => $active));
		}
	if (isset($_POST['secret'])) {
		require_once("library/totp.php");
		$totp = new TOTP("channels.gnatter.org", "Gnatter Channels",
				$account['account_email'], null, 30, 6);
		$r = q("update account set account_2fa_secret='%s' where account_id=%d",
				$totp->secret, $id);
		json_return_and_die(
			array(
				"secret" => $totp->secret,
				"pngurl" => totp_qrcode_png($totp->uri())
				)
			);
		}
	if (isset($_POST['test'])) {
		require_once("library/totp.php");
		$ref = intval($_POST['code']);
		$totp = new TOTP("channels.gnatter.org", "Gnatter Channels",
				$account['account_email'],
				$account['account_2fa_secret'], 30, 6);
		$code = $totp->authcode($totp->timestamp());
		json_return_and_die(array("match" => ($code == $ref ? "1" : "0")));
		}
	}

function totp_construct_page(&$a, &$b){
	if(!local_channel()) return;

	// Whatever you put in settings, will show up on the left nav of your pages.
	$b['layout']['region_aside'] .= '<div>' .
		#htmlentities($some_setting) .
		#"<pre>" . htmlentities(print_r(App::get_account(), true)) . "</pre>" .
		'</div>';
	}

function totp_settings_post($a,$s) {
	# possibly drop, as there's no settings submit event for this addon
	if (!local_channel()) return;
	}
function totp_settings(&$a, &$s) {
	$id = local_channel();
	if (!$id) return;
	$account = App::get_account();
	if (!$account) return;
	$acct_id = $account['account_id'];
	require_once("library/totp.php");
	$secret = $account['account_2fa_secret'];
	$totp = new TOTP("channels.gnatter.org", "Gnatter Channels",
			$account['account_email'], $secret == "" ? null : $secret, 30, 6);
	$qr_url = totp_qrcode_png($totp->uri());
	$sc = "";
	$sc .= "2FA Active <input type=\"checkbox\" name=\"2fa_active\" id=\"id_2fa_active\" value=\"1\" onclick=\"$.post('totp', {'active':(this.checked ? '1' : '0')})\"";
	if ($account['account_2fa_active'] == 1)
		$sc .= " checked=\"checked\"";
	$sc .= "/>";
	$sc .= "<br/>Your shared secret is <b><span id=\"id_totp_secret\">" . $totp->secret . "</span></b>";
	$sc .= "<br/>Be sure to save it somewhere in case you lose or replace your mobile device.";
	$sc .= "<br/>QR code provided for your convenience:";
	$sc .= "<p><img id=\"id_totp_qrcode\" src=\"$qr_url\" alt=\"QR code\"/></p>";
	$sc .= "<div>";
	$sc .= "<input title=\"enter TOTP code from your device\" type=\"text\" style=\"width: 16em\" id=\"id_totp_test\" onfocus=\"this.value='';document.getElementById('id_totp_testres').innerHTML=''\"/>";
	$sc .= " <input type=\"button\" value=\"Test\" onclick=\"$.post('totp',{'test':'1', 'code':document.getElementById('id_totp_test').value},function(data){document.getElementById('id_totp_testres').innerHTML = (data['match'] == '1' ? 'Pass!' : 'Fail')})\"/>";
	$sc .= " <b><span id=\"id_totp_testres\"></span></b>";
	$sc .= "</div>";
	$sc .= "<div><input type=\"button\" style=\"width: 16em; margin-top: 3px\" value=\"Generate New Secret\" onclick=\"$.post('totp',{'secret':'1'},function(data){document.getElementById('id_totp_secret').innerHTML=data['secret'];document.getElementById('id_totp_qrcode').src=data['pngurl']; document.getElementById('id_totp_remind').style.display='block'})\"/></div>";
	$sc .= "<div id=\"id_totp_remind\" style=\"display:none\">Record your new TOTP secret and rescan the QR code above.</div>";
	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'),
			array(
				     '$addon' => array('',
							 t('TOTP Settings'), '', ''),
				     '$content'	=> $sc));
	}
