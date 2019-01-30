<?php

use Zotlabs\Lib\Apps;

require_once('include/crypto.php');
require_once('include/items.php');
require_once('include/follow.php');

if(defined('SALMON_TEST')) {
	function salmon_init(&$a) {
		$testing = ((argc() > 1 && argv(1) === 'test') ? true : false);
		if($testing) {
			App::$data['salmon_test'] = true;
			salmon_post($a);
		}
	}
}

function salmon_post(&$a) {

    $sys_disabled = true;

    if(! get_config('system','disable_discover_tab')) {
        $sys_disabled = get_config('system','disable_diaspora_discover_tab');
    }
    $sys = (($sys_disabled) ? null : get_sys_channel());

	if(App::$data['salmon_test']) {
		$xml = file_get_contents('test.xml');
		App::$argv[1] = 'gnusoc';
	}
	else {
		$xml = file_get_contents('php://input');
	}
	
	logger('mod-salmon: new salmon ' . $xml, LOGGER_DATA);

	$nick       = ((argc() > 1) ? trim(argv(1)) : '');
	
	$importer = channelx_by_nick($nick);

	if(! $importer)
		http_status_exit(500);

	if(! Apps::addon_app_installed($importer['channel_id'], 'gnusoc'))
		http_status_exit(500);

	// parse the xml

	$dom = simplexml_load_string($xml,'SimpleXMLElement',0,NAMESPACE_SALMON_ME);

	// figure out where in the DOM tree our data is hiding

	if($dom->provenance->data)
		$base = $dom->provenance;
	elseif($dom->env->data)
		$base = $dom->env;
	elseif($dom->data)
		$base = $dom;

	if(! $base) {
		logger('mod-salmon: unable to locate salmon data in xml ');
		http_status_exit(400);
	}

	logger('data: ' . $xml, LOGGER_DATA);

	// Stash the signature away for now. We have to find their key or it won't be good for anything.

	logger('sig: ' . $base->sig);

	$signature = base64url_decode($base->sig);

	logger('sig: ' . $base->sig . ' decoded length: ' . strlen($signature));


	// unpack the  data

	// strip whitespace so our data element will return to one big base64 blob
	$data = str_replace(array(" ","\t","\r","\n"),array("","","",""),$base->data);


	// stash away some other stuff for later

	$type = $base->data[0]->attributes()->type[0];
	$keyhash = $base->sig[0]->attributes()->keyhash[0];
	if(! $keyhash)
		$keyhash = $base->sig[0]->attributes()->key_id[0];
	$encoding = $base->encoding;
	$alg = $base->alg;

	// Salmon magic signatures have evolved and there is no way of knowing ahead of time which
	// flavour we have. We'll try and verify it regardless.

	$stnet_signed_data = $data;

	$signed_data = $data  . '.' . base64url_encode($type, false) . '.' . base64url_encode($encoding, false) . '.' . base64url_encode($alg, false);

	$compliant_format = str_replace('=','',$signed_data);


	// decode the data
	$data = base64url_decode($data);

	logger('decoded: ' . $data, LOGGER_DATA);

	// GNU-Social doesn't send a legal Atom feed over salmon, only an Atom entry. Unfortunately
	// our parser is a bit strict about compliance so we'll insert just enough of a feed 
	// tag to trick it into believing it's a compliant feed. 

	if(! strstr($data,'<feed')) {
		$data = str_replace('<entry ','<feed xmlns="http://www.w3.org/2005/Atom"><entry ',$data); 
		$data .= '</feed>';
	} 
 
	$datarray = process_salmon_feed($data,$importer);

	if((! is_array($datarray)) || (empty($datarray))) {
		logger('feed parse error');
		http_status_exit(400);
	}

	$author_link = $datarray['author']['author_link'];
	$item = $datarray['item'];

	if(! $author_link) {
		logger('mod-salmon: Could not retrieve author URI.');
		http_status_exit(400);
	}

	$r = q("select xchan_pubkey from xchan where xchan_guid = '%s' limit 1",
		dbesc($author_link)
	);

	if($r) {
		$pubkey = $r[0]['xchan_pubkey'];
	}
	else {

		// Once we have the author URI, go to the web and try to find their public key

		logger('mod-salmon: Fetching key for ' . $author_link);

		$pubkey = get_salmon_key($author_link,$keyhash);

		if(! $pubkey) {
			logger('mod-salmon: Could not retrieve author key.');
			http_status_exit(400);
		}

		logger('mod-salmon: key details: ' . print_r($pubkey,true), LOGGER_DEBUG);

	}

	$pubkey = rtrim($pubkey);

	// We should have everything we need now. Let's see if it verifies.

	$verify = rsa_verify($signed_data,$signature,$pubkey);

	if(! $verify) {
		logger('mod-salmon: message did not verify using protocol. Trying padding hack.');
		$verify = rsa_verify($compliant_format,$signature,$pubkey);
	}

	if(! $verify) {
		logger('mod-salmon: message did not verify using padding. Trying old statusnet hack.');
		$verify = rsa_verify($stnet_signed_data,$signature,$pubkey);
	}

	if(! $verify) {
		logger('mod-salmon: Message did not verify. Discarding.');
		http_status_exit(400);
	}

	logger('mod-salmon: Message verified.');

	/* lookup the author */

	if(! $datarray['author']['author_link']) {
		logger('unable to probe - no author identifier');
		http_status_exit(400);
	}

	$r = q("select * from xchan where xchan_guid = '%s' limit 1",
	   	dbesc($datarray['author']['author_link'])
	);
	if(! $r) {
		if(discover_by_webbie($datarray['author']['author_link'])) {
			$r = q("select * from xchan where xchan_guid = '%s' limit 1",
				dbesc($datarray['author']['author_link'])
	   		);
			if(! $r) {
				logger('discovery failed');
				http_status_exit(400);
			}
		}
	}

	$xchan = $r[0];

	if(! (check_siteallowed($xchan['xchan_guid']) && check_channelallowed($xchan['xchan_hash']))) {
		logger('site or channel is blocked.');
		http_status_exit(403);
	}


	/*
	 *
	 * If we reached this point, the message is good. Now let's figure out if the author is allowed to send us stuff.
	 *
	 */

	// First check for and process follow activity

	if(activity_match($item['verb'],ACTIVITY_FOLLOW) && $item['obj_type'] === ACTIVITY_OBJ_PERSON) {

		$cb = [
			'item'    => $item,
			'channel' => $importer, 
			'xchan'   => $xchan, 
			'author'  => $datarray['author'], 
			'caught'  => false
		];

		call_hooks('follow_from_feed',$cb);
		if($cb['caught'])
			http_status_exit(200);

	}


	if(activity_match($item['verb'],ACTIVITY_UNFOLLOW) && $item['obj_type'] === ACTIVITY_OBJ_PERSON) {

		$cb = [
			'item'    => $item,
			'channel' => $importer, 
			'xchan'   => $xchan, 
			'author'  => $datarray['author'], 
			'caught'  => false
		];

		call_hooks('unfollow_from_feed',$cb);
		if($cb['caught'])
			http_status_exit(200);

	}
		

	$m = parse_url($xchan['xchan_url']);
	if($m) {
		$host = $m['scheme'] . '://' . $m['host'] . (($m['port']) ? ':' . $m['port'] : '');
		
    	q("update site set site_dead = 0, site_update = '%s' where site_type = %d and site_url = '%s'",
        	dbesc(datetime_convert()),
	        intval(SITE_TYPE_NOTZOT),
    	    dbesc($url)
    	);
		if(! check_siteallowed($host)) {
			logger('blacklisted site: ' . $host);
			http_status_exit(403, 'permission denied.');
		}
	}

	$importer_arr = array($importer);
	if(! $sys_disabled) {
		$sys['system'] = true;
		$importer_arr[] = $sys;
	}

	unset($datarray['author']);

	// we will only set and return the status code for operations 
	// on an importer channel and not for the sys channel

	$status = 200;

	foreach($importer_arr as $importer) {

		if(! $importer['system']) {
			$allowed = Apps::addon_app_installed($importer['channel_id'], 'gnusoc');
			if($allowed) {
        		logger('mod-salmon: disallowed for channel ' . $importer['channel_name']);
				$status = 202;
        		continue;
			}
			$r = q("update abook set abook_connected = '%s' where abook_xchan = '%s' and abook_channel = %d",
				dbesc(datetime_convert()),
				dbesc($xchan['xchan_hash']),
				intval($importer['channel_id'])
			);
			$importer['send_downstream'] = true;
		}
		
		consume_feed($data,$importer,$xchan,1);
		consume_feed($data,$importer,$xchan,2);

		if(! $importer['system'])
			$status = 200;
		continue;

	}

	http_status_exit($status);
	
}

