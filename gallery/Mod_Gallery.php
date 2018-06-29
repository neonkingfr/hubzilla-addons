<?php

namespace Zotlabs\Module;

class Gallery extends \Zotlabs\Web\Controller {

	function init() {
	
		if(observer_prohibited()) {
			return;
		}
	
		if(argc() > 1) {
			$nick = argv(1);
	
			profile_load($nick);
	
			$channelx = channelx_by_nick($nick);
	
			if(! $channelx)
				return;
	
			\App::$data['channel'] = $channelx;
	
			$observer = \App::get_observer();
			\App::$data['observer'] = $observer;
	
			\App::$page['htmlhead'] .= "<script> var profile_uid = " . ((\App::$data['channel']) ? \App::$data['channel']['channel_id'] : 0) . "; </script>" ;
	
		}
	
		return;
	}

	function post() {

		$owner_uid = \App::$data['channel']['channel_id'];

		$sql_extra = permissions_sql($owner_uid, get_observer_hash(), 'photo');

		$unsafe = ((array_key_exists('unsafe', $_POST) && $_POST['unsafe']) ? 1 : 0);

		$r = q("SELECT resource_id, width, height, description, display_path 
			FROM photo WHERE uid = %d AND album = '%s' AND photo_usage = %d  
			AND is_nsfw = %d  AND imgscale = 1 $sql_extra 
			ORDER by created DESC",
			intval($owner_uid),
			dbesc($_POST['album']),
			intval(PHOTO_NORMAL),
			intval($unsafe)
		);

		$i = 0;
		foreach($r as $rr) {
			$title = (($rr['description']) ? '<strong>' . $rr['description'] . '</strong><br>' . $rr['display_path'] : $rr['display_path']);

			$items[$i]['resource_id'] = $rr['resource_id'];
			$items[$i]['src'] = z_root() . '/photo/' . $rr['resource_id'] . '-1';
			$items[$i]['msrc'] = z_root() . '/photo/' . $rr['resource_id'] . '-3';
			$items[$i]['w'] = $rr['width'];
			$items[$i]['h'] = $rr['height'];
			$items[$i]['title'] = $title;
			$i++;
		}

		json_return_and_die($items);

	}

	function get() {

		head_add_js('/addon/gallery/lib/photoswipe/dist/photoswipe.js');
		head_add_js('/addon/gallery/lib/photoswipe/dist/photoswipe-ui-default.js');

		head_add_css('/addon/gallery/lib/photoswipe/dist/photoswipe.css');
		head_add_css('/addon/gallery/lib/photoswipe/dist/default-skin/default-skin.css');

		nav_set_selected('Gallery');

		$owner_uid = \App::$data['channel']['channel_id'];
		$sql_extra = permissions_sql($owner_uid, get_observer_hash(), 'photo');

		$unsafe = ((array_key_exists('unsafe', $_GET) && $_GET['unsafe']) ? 1 : 0);

		$albums = q("SELECT DISTINCT album FROM photo
			WHERE uid = %d AND photo_usage = %d  
			AND is_nsfw = %d $sql_extra 
			ORDER BY created DESC",
			intval($owner_uid),
			intval(PHOTO_NORMAL),
			intval($unsafe)
		);

		foreach($albums as $album) {
			$r = q("SELECT album, resource_id, width, height 
				FROM photo WHERE uid = %d AND photo_usage = %d  
				AND is_nsfw = %d AND imgscale = 3 AND album = '%s' $sql_extra 
				ORDER BY created DESC LIMIT 1",
				intval($owner_uid),
				intval(PHOTO_NORMAL),
				intval($unsafe),
				dbesc($album['album'])

			);

			$items[] = $r[0];
		}

		$tpl = get_markup_template('gallery.tpl', 'addon/gallery');
		$o = replace_macros($tpl, [
			'$title' => t('Gallery'),
			'$albums' => $items,
			'$nick' => \App::$data['channel']['channel_address'],
			'$unsafe' => $unsafe
		]);

		return $o;
	}

}
