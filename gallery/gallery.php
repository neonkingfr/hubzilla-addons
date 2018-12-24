<?php

/**
 * Name: Gallery
 * Description: Image Gallery
 * Version: 0.3
 * MinVersion: 3.8.7
 * Author: Mario
 * Maintainer: Mario
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;

require_once('addon/gallery/Mod_Gallery.php');

function gallery_load() {
	Hook::register('channel_apps', 'addon/gallery/gallery.php', 'gallery_channel_apps');
	Hook::register('photo_view_filter', 'addon/gallery/gallery.php', 'gallery_photo_view_filter');
	Hook::register('build_pagehead', 'addon/gallery/gallery.php', 'gallery_build_pagehead');
}

function gallery_unload() {
	Hook::unregister('channel_apps', 'addon/gallery/gallery.php', 'gallery_channel_apps');
	Hook::unregister('photo_view_filter', 'addon/gallery/gallery.php', 'gallery_photo_view_filter');
	Hook::unregister('build_pagehead', 'addon/gallery/gallery.php', 'gallery_build_pagehead');
}

function gallery_channel_apps(&$b) {
	if(Apps::addon_app_installed(App::$profile_uid, 'gallery')) {
		$b['tabs'][] = [
			'label' => t('Gallery'),
			'url'   => z_root() . '/gallery/' . $b['nickname'],
			'sel'   => ((argv(0) == 'gallery') ? 'active' : ''),
			'title' => t('Photo Gallery'),
			'id'    => 'gallery-tab',
			'icon'  => 'image'
		];
	}
}

function gallery_photo_view_filter(&$arr) {
	if(Apps::addon_app_installed(App::$profile_uid, 'gallery')) {
		$arr['onclick'] = '$.get(\'gallery/' . $arr['nickname'] . '?f=&photo=' . $arr['raw_photo']['resource_id'] . '&type=' . $arr['raw_photo']['mimetype'] . '&width=' . $arr['raw_photo']['width'] . '&height=' . $arr['raw_photo']['height'] . '&title=' . (($arr['raw_photo']['description']) ? $arr['raw_photo']['description'] : $arr['raw_photo']['filename']) . '\',  function(data) { if(! $(\'#gallery-fullscreen-view\').length) { $(\'<div></div>\').attr(\'id\', \'gallery-fullscreen-view\').appendTo(\'body\'); } $(\'#gallery-fullscreen-view\').html(data); }); return false;';
	}
}

function gallery_build_pagehead(&$arr) {
	if(Apps::addon_app_installed(App::$profile_uid, 'gallery') && in_array(argv(0), ['gallery', 'photos'])) {
		head_add_js('/addon/gallery/lib/photoswipe/dist/photoswipe.js');
		head_add_js('/addon/gallery/lib/photoswipe/dist/photoswipe-ui-default.js');
		head_add_css('/addon/gallery/lib/photoswipe/dist/photoswipe.css');
		head_add_css('/addon/gallery/lib/photoswipe/dist/default-skin/default-skin.css');
	}
}

