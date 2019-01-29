<?php
/**
 * Name: Native PHP Sessions
 * Description: Uses the native PHP Session handling tools - allows configuration via .htconfig.php (system.session_save_handler, system.session_save_path, system.session_gc_probability, system.session_gc_divisor)
 * Version: 0.5
 * Depends: Core
 * Author: Matthew Dent <dentm42@dm42.net>
 */

/**
 * Based on Merge Request 1492 by Mark Nowiasz: https://framagit.org/hubzilla/core/merge_requests/1492
 *    See also Issue 1330: https://framagit.org/hubzilla/core/issues/1330
 */

use Zotlabs\Extend\Hook;

function nativephpsessions_load(){
	Hook::register('custom_session_handler', __FILE__ , 'NativePHPSessions::custom_session_handler',1,1);
}

function nativephpsessions_unload(){
	Hook::unregister_by_file(__FILE__);
}

class NativePHPSessions {

  public static function custom_session_handler(&$custom_handler) {
	if ($custom_handler) {
		//There's already a session handler active.
		return;
	}

	/* Get config values */
	$session_save_handler = strval(get_config('system', 'session_save_handler', Null));
	$session_save_path = strval(get_config('system', 'session_save_path', Null));
	$session_gc_probability = intval(get_config('system', 'session_gc_probability', 1));
	$session_gc_divisor = intval(get_config('system', 'session_gc_divisor', 100));
		   
	if (!$session_save_handler || !$session_save_path) {
		logger('Session save handler or path not set - revert to DB session handling.',LOGGER_NORMAL,LOG_ERR);
		return false;
	} else {
		ini_set('session.save_handler', $session_save_handler);
		ini_set('session.save_path', $session_save_path);
		ini_set('session.gc_probability', $session_gc_probability);
		ini_set('session.gc_divisor', $session_gc_divisor);
	}

	return true;

  }
}

