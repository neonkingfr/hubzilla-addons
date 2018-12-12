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

use Zotlabs\Lib\Apps;

function totp_module(){};
function totp_load() {
	register_hook('construct_page', 'addon/totp/totp.php',
		'totp_construct_page');
	register_hook('logged_in', 'addon/totp/totp.php',
		'totp_logged_in');
	Zotlabs\Extend\Hook::register('module_loaded',
		'addon/totp/totp.php','totp_module_loaded');
	Zotlabs\Extend\Route::register('addon/totp/Settings/Totp.php',
		'settings/totp');
	}
function totp_unload() {
	unregister_hook('construct_page', 'addon/totp/totp.php',
		'totp_construct_page');
	unregister_hook('logged_in', 'addon/totp/totp.php',
		'totp_logged_in');
	Zotlabs\Extend\Hook::unregister_by_file('addon/totp/totp.php');
	Zotlabs\Extend\Route::unregister('addon/totp/Settings/Totp.php',
		'settings/totp');
	}
function totp_installed() {
	$id = local_channel();
	if (!$id) return false;
	return Apps::addon_app_installed($id, 'totp');
	}
function totp_module_loaded(&$x) {
	if (!totp_installed()) return;
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
	if (!totp_installed()) return;
	if (isset($_SESSION['2FA_VERIFIED'])) return;
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
	if (!totp_installed()) return;

	// Whatever you put in settings, will show up on the left nav of your pages.
	$b['layout']['region_aside'] .= '<div>' .
		#htmlentities($some_setting) .
		#"<pre>" . htmlentities(print_r(App::get_account(), true)) . "</pre>" .
		'</div>';
	}
