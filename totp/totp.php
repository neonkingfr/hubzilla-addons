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
/**
* @brief Do we need to 2FA-verify?
*
* Determine whether 2FA verification is needed and, if so,
* route user to verification form.
*
*/
function totp_logged_in(&$a, &$user) {
	if (isset($_SESSION['2FA_VERIFIED'])) return;
	if (intval($user['account_2fa_active']) == 0) return;
	$mod = App::$module;
	if (($mod != 'totp') # avoid infinite recursion
			&& ($mod != 'ping') # Don't redirect essential
			&& ($mod != 'view') # system modules.
			&& ($mod != 'acl')
			&& ($mod != 'photo')
			) goaway(z_root() . '/totp');
	return;
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
	require_once("addon/totp/class_totp.php");
	$secret = $account['account_2fa_secret'];
	$totp = new TOTP("channels.gnatter.org", "Gnatter Channels",
			$account['account_email'], $secret == "" ? null : $secret, 30, 6);
	$active_checked =
		($account['account_2fa_active'] == 1
			? "checked=\"checked\""
			: "");
	$sc = replace_macros(get_markup_template('settings.tpl','addon/totp'),
			[
			'$checked' => $active_checked,
			'$secret' => $totp->secret,
			'$qrcode_url' => "/totp/qrcode?s=",
			'$salt' => microtime()
			]);
	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'),
			array(
				     '$addon' => array('',
							 t('TOTP Settings'), '', ''),
				     '$content'	=> $sc));
	}
