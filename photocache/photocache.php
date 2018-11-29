<?php
/**
 * Name: Photo Cache
 * Description: Local photo cache implementation
 * Version: 0.1 
 * Author: Max Kostikov
 * Maintainer: max@kostikov.co
 * MinVersion: 3.9.5
 */

use Zotlabs\Extend\Hook;
 
function photocache_load() {

	Hook::register('cache_mode_hook', 'addon/photocache/photocache.php', 'photocache_mode');
	Hook::register('cache_url_hook', 'addon/photocache/photocache.php', 'photocache_url');
	logger('Photo Cache is loaded');
}


function photocache_unload() {

	Hook::unregister('cache_mode_hook', 'addon/photocache/photocache.php', 'photocache_mode');
	Hook::unregister('cache_url_hook', 'addon/photocache/photocache.php', 'photocache_url');
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

	$v['on'] = photocache_mode_key('on');
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
		case 'on':
			return boolval(get_config('system','photo_cache_enable', 0));
			break;
		case 'age':
			return intval(get_config('system','photo_cache_time', 86400));
			break;
		case 'minres':
			return intval(get_config('system','photo_cache_minres', 1024));
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
 * @brief Checks by hash if this item already cached
 *
 * @param string $hash
 * @return mixed array() | bool false on error
 *
 */
function photocache_exists($hash) {
	
	$r = q("SELECT * FROM photo WHERE xchan = '%s' AND photo_usage = %d LIMIT 1",
		dbesc($hash),
		intval(PHOTO_CACHE)
	);
	return ($r ? $r : false);
}


/*
 * @brief Fetch or renew item using its URL
 *
 * @param array $cache 
 * * 'url' => string
 * * 'uid' => int
 * @return array of results | bool false on error
 * * 'status' => boolean true if no error
 * * 'cached' => boolean true if cached
 * * 'hash' => string resource_id of cached resource
 * * 'width' => int cached image width
 * * 'height' => int cached image height
 * * 'res' => int resolution code 
 *
 */
function photocache_url(&$cache = array()) {

	if(photocache_isgrid($cache['url']))
		return $cache['status'] = photocache_ret('caching is disabled for this host');

	$cache_mode = array();
	photocache_mode($cache_mode);
	
	logger('info: processing ' . $cache['url'] . ' for ' . $cache['uid'] . ', caching is ' . ($cache_mode['on'] ? 'on' : 'off'), LOGGER_DEBUG);
	
	if(empty($cache['url']))
		return $cache['status'] = photocache_ret('URL is empty');
	
	$x = channelx_by_n($cache['uid']);
	if(! $x)
		return $cache['status'] = photocache_ret('invalid channel ID received ' . $cache['uid']);

	require_once('include/photo/photo_driver.php');
	
	$hash = photocache_hash(preg_replace('|^http(s)?://|','',$cache['url']));
	$resource_id = photocache_hash($cache['uid'] . $hash);
	$r = photocache_exists($hash);
	if($r) {
		$width = $r[0]['width'];
		$height = $r[0]['height'];
		$res = $r[0]['imgscale'];
		
		$k = q("SELECT * FROM photo WHERE uid = %d AND xchan = '%s' AND photo_usage = %d LIMIT 1",
			intval($cache['uid']),
			dbesc($hash),
			intval(PHOTO_CACHE)
		);
		
		// Duplicate cached item for current user
		if(! $k) {
			$ph = photo_factory('');
			$p = array (
				'aid' => $x['channel_account_id'],
				'uid' => $cache['uid'], 
				'xchan' => $hash,
				'resource_id' => $resource_id,
				'created' => $r[0]['created'],
				'edited' => $r[0]['edited'],
				'expires' => $r[0]['expires'],
				'filename' => $r[0]['filename'],
				'mimetype' => $r[0]['mimetype'],
				'height' => $height,
				'width' => $width,
				'filesize' => $r[0]['filesize'],
				'os_syspath' => $r[0]['content'],
				'os_storage' => 1,
				'imgscale' => $res,
				'photo_usage' => PHOTO_CACHE,
				'display_path' => $r[0]['display_path']
			);
			if(! $ph->save($p, true))
				return $cache['status'] = photocache_ret('could not duplicate cached URL ' . $cache['url'] . ' for ' . $cache['uid']);
		}
		$fetch = boolval(strtotime($r[0]['expires']) - 60 < time());
	}
	else
		$fetch = true;
	
	if($fetch) {
		// Get data from remote server
		$i = z_fetch_url($cache['url'], true, 0, ($r ? array('headers' => array("If-Modified-Since: " . gmdate("D, d M Y H:i:s", strtotime($r[0]['edited'] . "Z")) . " GMT")) : array()));
	
		if((! $i['success']) && $i['return_code'] != 304)
			return $cache['status'] = photocache_ret($origurl . ' photo could not be fetched (HTTP code ' . $i['return_code'] . ')');
	
		$hdrs = array();
		$h = explode("\n", $i['header']);
		foreach ($h as $l) {
			list($k,$v) = array_map("trim", explode(":", trim($l), 2));
			$hdrs[strtolower($k)] = $v;
		}
	
		if(array_key_exists('expires', $hdrs)) {
			$expires = strtotime($hdrs['expires']);
			if($expires - 60 < time())
				return $cache['status'] = photocache_ret('fetched item expired ' . $hdrs['expires']);
			$maxexp = time() + 86400 * get_config('system','default_expire_days', 30);
			if($expires > $maxexp)
				$expires = $maxexp;
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
	
		$newimg = false;

		if($i['success']) {
			// New data (HTTP 200)
			$type = guess_image_type($cache['url'], $i['header']);
			if(strpos($type, 'image') === false)
				return $cache['status'] = photocache_ret('wrong image type detected ' . $type);

			$ph = photo_factory($i['body'], $type);
		
			if(! is_object($ph))
				return $cache['status'] = photocache_ret('photo processing failure');
		
			if($ph->is_valid()) {
				$orig_width = $ph->getWidth();
				$orig_height = $ph->getHeight();
				$res = PHOTO_RES_ORIG;
			
				if(($orig_width > 1024 || $orig_height > 1024) && $cache_mode['on']) {
					$ph->scaleImage(1024);
					$width = $ph->getWidth();
					$height = $ph->getHeight();
					$res = PHOTO_RES_1024;
					logger('photo resized: ' . $orig_width . '->' . $width . 'w ' . $orig_height . '->' . $height . 'h' . ' match: ' . $mtch[0], LOGGER_DEBUG);
				}
				else {
					$width = $orig_width;
					$height = $orig_height;
				}
			
				if($orig_width > $cache_mode['minres'] || $orig_height > $cache_mode['minres'])
					$newimg = true;	
			}
		}

		// Cache save procedure
		if($cache_mode['on']) {
			$p = array(
				'uid' => $cache['uid'],
				'aid' => $x['channel_account_id'],
				'created' => datetime_convert(),
				'xchan' => $hash,
				'resource_id' => $resource_id,
				'filename' => substr(parse_url($cache['url'], PHP_URL_PATH), 1),
				'width' => $width,
				'height' => $height,
				'imgscale' => $res,
				'photo_usage' => PHOTO_CACHE,
				'os_storage' => 1,
				'display_path' => $cache['url'],
				'expires' => gmdate('Y-m-d H:i:s', $expires)
			);

			if(array_key_exists('last-modified', $hdrs))
				$p['edited'] = gmdate('Y-m-d H:i:s', strtotime($hdrs['last-modified']));
			
			// Save image to disk storage and into database
			if($newimg) {
				// If new
				$path = 'store/[data]/[cache]/' .  substr($hash,0,2) . '/' . substr($hash,2,2);
				$os_path = $path . '/' . $hash;
				$p['os_syspath'] = $os_path;
				if(! is_dir($path))
					if(! os_mkdir($path, STORAGE_DEFAULT_PERMISSIONS, true))
						return $cache['status'] = photocache_ret('could not create path ' . $path);
				if(! $ph->saveImage($os_path))
					return $cache['status'] = photocache_ret('could not save file ' . $os_path);
				if(! $ph->save($p))
					return $cache['status'] = photocache_ret('could not save data to database');
				$r = true;
			}
			
			if($r || $i['return_code'] == 304) {
				// Update all links on any change
				$r = q("UPDATE photo SET edited = '%s', expires = '%s', height = %d, width = %d, filesize =%d, imgscale = %d WHERE xchan = '%s'",
					dbescdate(($p['edited'] ? $p['edited'] : datetime_convert())),
					dbescdate($p['expires']),
					intval($height),
					intval($width),
					intval(($newimg ? strlen($ph->imageString()) : $r[0]['filesize'])),
					intval($res),
					dbesc($hash)
				);
				if(! $r)
					return $cache['status'] = photocache_ret('could not update data in database');
			}
		}
	}
	
	$cache['status'] = true;
	$cache['cached'] = boolval($r);
	$cache['hash'] = $resource_id;
	$cache['width'] = $width;
	$cache['height'] = $height;
	$cache['res'] = $res;

	logger('info: ' . $cache['url'] . ' (res: ' . $res . '; width: ' . $width . '; height: ' . $height . ') is ' . ($r ? 'cached as ' . $resource_id . ' for ' . $cache['uid'] : 'not cached'), LOGGER_DEBUG);
}
