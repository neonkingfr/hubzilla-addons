<?php
/**
 * Name: Photo Cache
 * Description: Local photo cache implementation
 * Version: 0.2.8
 * Author: Max Kostikov
 * Maintainer: max@kostikov.co
 * MinVersion: 3.9.5
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;

require_once('include/photo/photo_driver.php');
 
function photocache_load() {

	Hook::register('cache_mode_hook', 'addon/photocache/photocache.php', 'photocache_mode');
	Hook::register('cache_url_hook', 'addon/photocache/photocache.php', 'photocache_url');
	Hook::register('cache_body_hook', 'addon/photocache/photocache.php', 'photocache_body');
	Route::register('addon/photocache/Mod_Photocache.php', 'photocache');
	
	logger('Photo Cache is loaded');
}


function photocache_unload() {

	Hook::unregister('cache_mode_hook', 'addon/photocache/photocache.php', 'photocache_mode');
	Hook::unregister('cache_url_hook', 'addon/photocache/photocache.php', 'photocache_url');
	Hook::unregister('cache_body_hook', 'addon/photocache/photocache.php', 'photocache_body');
	Route::unregister('addon/photocache/Mod_Photocache.php', 'photocache');
	
	logger('Photo Cache is unloaded');
}


/*
 * @brief Returns array of current photo cache settings
 *
 * @param string $v
 * @return mixed array
 *
 */
function photocache_mode(&$v) {

	$v['on'] = true;
	$v['age'] = photocache_mode_key('age');
	$v['minres'] = photocache_mode_key('minres');
	$v['grid'] = photocache_mode_key('grid');
	$v['exp'] = photocache_mode_key('exp');
	$v['leak'] = photocache_mode_key('leak');
}


/*
 * @brief Returns current photo cache setting by its key
 *
 * @param string $key
 * @return mixed content | int 0 if not found
 *
 */
function photocache_mode_key($key) {
	switch($key) {
		case 'age':
			$x = intval(get_config('system','photo_cache_time'));
			return ($x ? $x : 86400);
			break;
		case 'minres':
			$x = intval(get_config('system','photo_cache_minres'));
			return ($x ? $x : 1024);
			break;
		case 'grid':
			return boolval(get_config('system','photo_cache_grid', 0));
			break;
		case 'exp':
			return boolval(get_config('system','photo_cache_ownexp', 0));
			break;
		case 'leak':
			return boolval(get_config('system','photo_cache_leak', 0));
			break;
		default:
			return 0;
	}
}


 /*
 * @brief Is this host in the Grid?
 *
 * @param string $url
 * @return boolean
 *
 */	
function photocache_isgrid($url) {

	if(photocache_mode_key('grid'))
		return false;
		
	is_matrix_url($url);
}


/*
 * @brief Returns hash string by URL
 *
 * @param string $str
 * @param string $alg default sha256
 * @return string
 *
 */
function photocache_hash($str, $alg = 'sha256') {

	return hash($alg, $str);
}


/*
 * @brief Proceed message and replace URL for cached photos
 *
 * @param array $s
 * * 'body' => string
 * * 'uid' => int
 * @return array $s 
 *
 */
 function photocache_body(&$s) {
	 
	if(! $s['uid'])
		return;

	if(! Apps::addon_app_installed($s['uid'],'photocache'))
		return;

	$x = channelx_by_n($s['uid']);
	if(! $x)
		return logger('invalid channel ID received ' . $s['uid'], LOGGER_DEBUG);
	
	$matches = null;
	$cnt = preg_match_all("/\<img(.+?)src=([\"|'])(https?\:.*?)\\2(.*?)\>/", $s['body'], $matches, PREG_SET_ORDER);
	if($cnt) {
		$ph = photo_factory('');
		foreach ($matches as $match) {
		    $match[3] = trim($match[3]);
			if(photocache_isgrid($match[3]))
				continue;

			logger('uid: ' . $s['uid'] . '; url: ' . $match[3], LOGGER_DEBUG);

			$hash = photocache_hash(preg_replace('|^https?://|' ,'' , $match[3]));
			$resid = photocache_hash($s['uid'] . $hash);
			$r = q("SELECT * FROM photo WHERE xchan = '%s' AND photo_usage = %d AND uid = %d LIMIT 1",
				dbesc($hash),
				intval(PHOTO_CACHE),
				intval($s['uid'])
			);
			if(! $r) {
				// Create new empty link. Data will be fetched on link open.
				$r = array (
					'aid' => $x['channel_account_id'],
					'uid' => $s['uid'],
					'xchan' => $hash,
					'resource_id' => $resid,
					'created' => datetime_convert(),
					'expires' => dbesc(NULL_DATE),
					'mimetype' => '',
					'photo_usage' => PHOTO_CACHE,
					'os_storage' => 1,
					'display_path' => $match[3]
				);
				if(! $ph->save($r, true))
					logger('can not create new link in database', LOGGER_DEBUG);
			}
			$s['body'] = str_replace($match[3], z_root() . '/photo/' . $resid . '-' . ($r[0]['imgscale'] ? $r[0]['imgscale'] : 0), $s['body']);
			logger('local resource id ' . $resid . '; xchan: ' . $hash . '; url: ' . $match[3]);
		}
	}
}


/*
 * @brief Fetch or renew photo using its resource_id
 *
 * @param array $cache 
 * * 'resid' => string
 * * 'status' => boolean
 * @return array of results | bool false on error
 * * 'status' => true if cached
 *
 */
function photocache_url(&$cache = array()) {
	
	if(! local_channel() && ! photocache_mode_key('leak'))
		return;
	
	$r = q("SELECT * FROM photo WHERE resource_id = '%s' AND photo_usage = %d LIMIT 1",
		dbesc($cache['resid']),
		intval(PHOTO_CACHE)
	);
	if(! $r)
		return logger('unknown resource id ' . $cache['resid'], LOGGER_DEBUG);
	
	$r = $r[0];
	
	$cache_mode = array();
	photocache_mode($cache_mode);
		
	$minres = intval(get_pconfig($r['uid'], 'photocache', 'cache_minres'));
	if($minres == 0)
		$minres = $cache_mode['minres'];

	logger('info: processing ' . $cache['resid'] . ' (' . $r['display_path'] .') for ' . $r['uid']  . ' (min. ' . $minres . ' px)', LOGGER_DEBUG);
	
	if($r['height'] == 0) {
		// If new resource id
		$k = q("SELECT * FROM photo WHERE xchan = '%s' AND photo_usage = %d AND height > %d ORDER BY filesize DESC LIMIT 1",
			dbesc($r['xchan']),
			intval(PHOTO_CACHE),
			0
		);
		if($k) {
			// If photo already was cached for other user just duplicate it
			if(($k[0]['height'] >= $minres || $k[0]['width'] >= $minres) && $k[0]['filesize'] > 0) {
				$r['os_syspath'] = dbunescbin($k[0]['content']);
				$r['filesize'] = $k[0]['filesize'];
			}
			$r['edited'] = $k[0]['edited'];
			$r['expires'] = $k[0]['expires'];
			$r['mimetype'] = $k[0]['mimetype'];
			$r['height'] = $k[0]['height'];
			$r['width'] = $k[0]['width'];
			$ph = photo_factory('');
			if(! $ph->save($r, true))
				return logger('could not duplicate cached URL ' . $r['display_path'] . ' for ' . $r['uid'], LOGGER_DEBUG);
			logger('info: duplicate ' . $cache['resid'] . ' data from cache for ' . $k[0]['uid'], LOGGER_DEBUG);
		}
	}
	
	$exp = strtotime($r['expires']);
	$url = (($exp - 60 < time()) ? htmlspecialchars_decode($r['display_path']) : '');
	
	if($url) {
		// Get data from remote server 		
		$i = z_fetch_url($url, true, 0, ($r['filesize'] > 0 ? array('headers' => array("If-Modified-Since: " . gmdate("D, d M Y H:i:s", $exp . "Z") . " GMT")) : array()));
	
		if((! $i['success']) && $i['return_code'] != 304)
			return logger('photo could not be fetched (HTTP code ' . $i['return_code'] . ')', LOGGER_DEBUG);
	
		$hdrs = array();
		$h = explode("\n", $i['header']);
		foreach ($h as $l) {
			list($t,$v) = array_map("trim", explode(":", trim($l), 2));
			$hdrs[strtolower($t)] = $v;
		}
	
		if(array_key_exists('expires', $hdrs)) {
			$expires = strtotime($hdrs['expires']);
			if($expires - 60 < time())
				return logger('fetched item expired ' . $hdrs['expires'], LOGGER_DEBUG);
		}
	
		$cc = '';
		if(array_key_exists('cache-control', $hdrs))
			$cc = $hdrs['cache-control'];
		if(strpos($cc, 'no-store'))
			return logger('caching prohibited by remote host directive ' . $cc, LOGGER_DEBUG);
		if(strpos($cc, 'no-cache'))
			$expires = time() + 60;
		if(! isset($expires)){
			if($cache_mode['exp'])
				$ttl = $cache_mode['age'];
			else
				$ttl = (preg_match('/max-age=(\d+)/i', $cc, $o) ? intval($o[1]) : $cache_mode['age']);
			$expires = time() + $ttl;
		}

		$maxexp = time() + 86400 * get_config('system','default_expire_days', 30);
		if($expires > $maxexp)
			$expires = $maxexp;
		
		$r['expires'] = gmdate('Y-m-d H:i:s', $expires);
		
		if(array_key_exists('last-modified', $hdrs))
			$r['edited'] = gmdate('Y-m-d H:i:s', strtotime($hdrs['last-modified']));

		if($i['success']) {
			// New data (HTTP 200)
			$type = guess_image_type($r['display_path'], $i['header']);
			if(strpos($type, 'image') === false)
				return logger('wrong image type detected ' . $type, LOGGER_DEBUG);
			$r['mimetype'] = $type;

			$ph = photo_factory($i['body'], $type);
		
			if(! is_object($ph))
				return logger('photo processing failure', LOGGER_DEBUG);

			if($ph->is_valid()) {
				$orig_width = $ph->getWidth();
				$orig_height = $ph->getHeight();
			
				if($orig_width > 1024 || $orig_height > 1024) {
					$ph->scaleImage(1024);
					logger('photo resized: ' . $orig_width . '->' . $ph->getWidth() . 'w ' . $orig_height . '->' . $ph->getHeight() . 'h', LOGGER_DEBUG);
				}

				$r['width'] = $ph->getWidth();
				$r['height'] = $ph->getHeight();

				$oldsize = $r['filesize'];
					
				if($orig_width >= $minres || $orig_height >= $minres) {
					$path = 'store/[data]/[cache]/' .  substr($r['xchan'],0,2) . '/' . substr($r['xchan'],2,2);
					$os_path = $path . '/' . $r['xchan'];
					$r['os_syspath'] = $os_path;
					if(! is_dir($path))
					if(! os_mkdir($path, STORAGE_DEFAULT_PERMISSIONS, true))
						return logger('could not create path ' . $path, LOGGER_DEBUG);
					if(is_file($os_path))
						@unlink($os_path);
					if(! $ph->saveImage($os_path))
						return logger('could not save file ' . $os_path, LOGGER_DEBUG);
				
					if($oldsize == 0)
						if(! $ph->save($r, true))
							logger('can not save image in database', LOGGER_DEBUG);
					
					$r['filesize'] = strlen($ph->imageString());
										
					logger('image saved: ' . $os_path . '; ' . $r['mimetype'] . ', ' . $r['width'] . 'w x ' . $r['height'] . 'h, ' . $r['filesize'] . ' bytes', LOGGER_DEBUG);
				}
				
				if($oldsize != $r['filesize']) {
					// Update image data for cached links on change
					$x = q("UPDATE photo SET filesize = %d, height = %d, width = %d WHERE xchan = '%s' AND photo_usage = %d AND filesize > %d",
						intval($r['filesize']),
						intval($r['height']),
						intval($r['width']),
						dbesc($r['xchan']),
						intval(PHOTO_CACHE),
						0
					);
				}
			}
		}

		// Update metadata on any change
		$x = q("UPDATE photo SET edited = '%s', expires = '%s' WHERE xchan = '%s' AND height > %d AND photo_usage = %d",
			dbescdate(($r['edited'] ? $r['edited'] : datetime_convert())),
			dbescdate($r['expires']),
			dbesc($r['xchan']),
			0,
			intval(PHOTO_CACHE)
		);
	}
	
	if($r['filesize'] > 0)
		$cache['status'] = true;

	logger('info: ' . $r['display_path'] . ($cache['status'] ? ' is cached as ' . $cache['resid'] . ' for ' . $r['uid'] : ' is not cached'));
}
