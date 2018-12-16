<?php
/**
 * Name: Photo Cache
 * Description: Local photo cache implementation
 * Version: 0.2.2
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
 * @brief Produce error log entity and return false
 *
 * @param string $msg
 * @return boolean false
 *
 */	
function photocache_ret($msg) {
	
	logger($msg, LOGGER_DEBUG);
	return false;
}


 /*
 * @brief Is this host in the Grid?
 *
 * @param string $url
 * @return boolean
 *
 */	
function photocache_isgrid($url) {
	
	if(strpos($url, z_root()) === 0)
		return true;
	if(photocache_mode_key('grid'))
		return false;
	$r = q("SELECT * FROM hubloc WHERE hubloc_host = '%s' AND hubloc_network LIKE '%s' LIMIT 1",
		dbesc(parse_url($url, PHP_URL_HOST)),
		dbesc('zot%')
	);
	return ($r ? true : false);
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
		return photocache_ret('invalid channel ID received ' . $s['uid']);
	
	$matches = null;
	$cnt = preg_match_all("/\<img(.+?)src=[\"|'](https?\:.*?)[\"|'](.*?)\>/", $s['body'], $matches, PREG_SET_ORDER);
	if($cnt) {
		$ph = photo_factory('');
		$sslify = (strpos(z_root(),'https:') === false ? false : true);
		foreach ($matches as $match) {
			if(photocache_isgrid($match[2]))
				continue;

			logger('uid: ' . $s['uid'] . '; url: ' . $match[2], LOGGER_DEBUG);

			$hash = photocache_hash(preg_replace('|^https?://|' ,'' , $match[2]));
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
					'expires' => '0001-01-01 00:00:00',
					'mimetype' => '',
					'photo_usage' => PHOTO_CACHE,
					'os_storage' => 1,
					'display_path' => ($sslify ? $match[2] : z_root() . '/sslify/' . $filename . '?f=&url=' . urlencode($match[2]))
				);
				if(! $ph->save($r, true))
					logger('can not create new link in database', LOGGER_DEBUG);
			}
			$s['body'] = str_replace($match[2], z_root() . '/photo/' . $resid . '-' . ($r[0]['imgscale'] ? $r[0]['imgscale'] : 0), $s['body']);
			logger('local resource id ' . $resid . '; xchan: ' . $hash . '; url: ' . $match[2], LOGGER_DEBUG);
		}
	}
}


/*
 * @brief Fetch or renew photo using its resource_id
 *
 * @param array $cache 
 * * 'resid' => string
 * * 'uid' => int
 * @return array of results | bool false on error
 * * 'status' => boolean true if no error
 *
 */
function photocache_url(&$cache = array()) {
	
	if(! local_channel())
		return $cache['status'] = photocache_mode_key('leak');
	
	if(! Apps::addon_app_installed($cache['uid'],'photocache'))
		return $cache['status'] = photocache_ret('caching for channel ' . $cache['uid'] . ' is disabled');
	
	$r = q("SELECT * FROM photo WHERE resource_id = '%s' AND photo_usage = %d AND uid = %d LIMIT 1",
		dbesc($cache['resid']),
		intval(PHOTO_CACHE),
		intval($cache['uid'])
	);
	if(! $r)
		return $cache['status'] = photocache_ret('unknown resource id ' . $cache['resid']);
	
	$r = $r[0];

	$cache_mode = array();
	photocache_mode($cache_mode);
	
	logger('info: processing ' . $cache['resid'] . ' (' . $r['display_path'] .') for ' . $cache['uid'], LOGGER_DEBUG);

	if(empty($r['mimetype'])) {
		// If new resource id
		$k = q("SELECT * FROM photo WHERE xchan = '%s' AND photo_usage = %d AND filesize > %d LIMIT 1",
			dbesc($r['xchan']),
			intval(PHOTO_CACHE),
			0
		);
		if($k) {
			// If photo already was cached for other user just duplicate it
			$r['edited'] = $k[0]['edited'];
			$r['expires'] = $k[0]['expires'];
			$r['filename'] = $k[0]['filename'];
			$r['mimetype'] = $k[0]['mimetype'];
			$r['height'] = $k[0]['height'];
			$r['width'] = $k[0]['width'];
			$r['os_syspath'] = dbunescbin($k[0]['content']);
			$ph = photo_factory('');
			if(! $ph->save($r, true))
				return $cache['status'] = photocache_ret('could not duplicate cached URL ' . $cache['url'] . ' for ' . $cache['uid']);
			$r['filesize'] = $k[0]['filesize'];
			logger('info: duplicate ' . $cache['resid'] . ' data from cache for ' . $k[0]['uid'], LOGGER_DEBUG);
		}
	}
	else
		$r['os_syspath'] = dbunescbin($r['content']);
	
	$exp = strtotime($r['expires']);
	$url = (($exp - 60 < time()) ? htmlspecialchars_decode($r['display_path']) : '');
	
	if($url) {
		// Get data from remote server 		
		$i = z_fetch_url($url, true, 0, ($r['filesize'] > 0 ? array('headers' => array("If-Modified-Since: " . gmdate("D, d M Y H:i:s", $exp . "Z") . " GMT")) : array()));
	
		if((! $i['success']) && $i['return_code'] != 304)
			return $cache['status'] = photocache_ret('photo could not be fetched (HTTP code ' . $i['return_code'] . ')');
	
		$hdrs = array();
		$h = explode("\n", $i['header']);
		foreach ($h as $l) {
			list($t,$v) = array_map("trim", explode(":", trim($l), 2));
			$hdrs[strtolower($t)] = $v;
		}
	
		if(array_key_exists('expires', $hdrs)) {
			$expires = strtotime($hdrs['expires']);
			if($expires - 60 < time())
				return $cache['status'] = photocache_ret('fetched item expired ' . $hdrs['expires']);
		}
	
		$cc = '';
		if(array_key_exists('cache-control', $hdrs))
			$cc = $hdrs['cache-control'];
		if(strpos($cc, 'no-store'))
			return $cache['status'] = photocache_ret('caching prohibited by remote host directive ' . $cc);
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
		
		$newimg = false;
		if($i['success']) {
			// New data (HTTP 200)
			$type = guess_image_type($r['display_path'], $i['header']);
			if(strpos($type, 'image') === false)
				return $cache['status'] = photocache_ret('wrong image type detected ' . $type);
			$r['mimetype'] = $type;

			$ph = photo_factory($i['body'], $type);
		
			if(! is_object($ph))
				return $cache['status'] = photocache_ret('photo processing failure');

			if($ph->is_valid()) {
				$orig_width = $ph->getWidth();
				$orig_height = $ph->getHeight();
			
				if($orig_width > 1024 || $orig_height > 1024) {
					$ph->scaleImage(1024);
					logger('photo resized: ' . $orig_width . '->' . $ph->getWidth() . 'w ' . $orig_height . '->' . $ph->getHeight() . 'h', LOGGER_DEBUG);
				}

				$minres = intval(get_pconfig($cache['uid'], 'photocache', 'cache_minres'));
				if($minres == 0)
					$minres = $cache_mode['minres'];
	
				$newimg = ($orig_width >= $minres || $orig_height >= $minres);
				
				$r['width'] = $ph->getWidth();
				$r['height'] = $ph->getHeight();
			}
		}
		
		$r['filename'] = basename(parse_url($url, PHP_URL_PATH));
		
		// Save image to disk storage
		if($newimg) {
			$path = 'store/[data]/[cache]/' .  substr($r['xchan'],0,2) . '/' . substr($r['xchan'],2,2);
			$os_path = $path . '/' . $r['xchan'];
			$r['os_syspath'] = $os_path;
			$r['filesize'] = strlen($ph->imageString());
			if(! is_dir($path))
				if(! os_mkdir($path, STORAGE_DEFAULT_PERMISSIONS, true))
					return $cache['status'] = photocache_ret('could not create path ' . $path);
			if(is_file($os_path))
				@unlink($os_path);
			if(! $ph->saveImage($os_path))
				return $cache['status'] = photocache_ret('could not save file ' . $os_path);
			logger('new image saved: ' . $os_path . '; ' . $r['mimetype'] . ', ' . $r['width'] . 'w x ' . $r['height'] . 'h, ' . $r['filesize'] . ' bytes', LOGGER_DEBUG);
		}

		// Update all links in database on any change
		if(isset($minres) || $i['return_code'] == 304) {
			$x = q("UPDATE photo SET edited = '%s', expires = '%s', height = %d, width = %d, mimetype = '%s', filesize = %d, filename = '%s', content = '%s' WHERE xchan = '%s' AND photo_usage = %d",
				dbescdate(($r['edited'] ? $r['edited'] : datetime_convert())),
				dbescdate($r['expires']),
				intval($r['height']),
				intval($r['width']),
				dbesc($r['mimetype']),
				intval($r['filesize']),
				dbesc($r['filename']),
				dbesc($r['os_syspath']),
				dbesc($r['xchan']),
				intval(PHOTO_CACHE)
			);
			if(! $x)
				return $cache['status'] = photocache_ret('could not save data to database');
		}
	}
	
	$cache['status'] = ($r['filesize'] > 0 ? true : false);

	logger('info: ' . $r['display_path'] . ' is cached as ' . $cache['resid'] . ' for ' . $cache['uid'], LOGGER_DEBUG);
}
