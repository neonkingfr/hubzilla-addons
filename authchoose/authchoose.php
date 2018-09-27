<?php

/**
 * Name: Auth Choose
 * Description: Allow magic authentication only to websites of your immediate connections.
 *
 */

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;

function authchoose_load() {
	Hook::register('zid','addon/authchoose/authchoose.php','authchoose_zid');
	Route::register('addon/authchoose/Mod_authchoose.php','authchoose');
}

function authchoose_unload() {
	Hook::unregister('zid','addon/authchoose/authchoose.php','authchoose_zid');
	Route::unregister('addon/authchoose/Mod_authchoose.php','authchoose');
}

function authchoose_zid(&$x) {

	if(! Apps::addon_app_installed(local_channel(), 'authchoose'))
		return;

	$c = App::get_channel();
	if(! $c)
		return;

	static $friends = [];

	if(! array_key_exists($c['channel_id'],$friends)) {
		$r = q("select distinct hubloc_url from hubloc left join abook on hubloc_hash = abook_xchan where abook_id = %d",
			intval($c['channel_id'])
		);
		if($r)
			$friends[$c['channel_id']] = $r;
	}
	if($friends[$c['channel_id']]) {
		foreach($friends[$c['channel_id']] as $n) {
			if(strpos($x['url'],$n['hubloc_url']) !== false) {
				return; 
			}
		}
		$x['result'] = $x['url'];
	}
}
