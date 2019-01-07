<?php

/**
 * Name: Gallery
 * Description: Image Gallery
 * Version: 0.4
 * MinVersion: 3.8.8
 * Author: Mario
 * Maintainer: Mario
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;

require_once('addon/gallery/Mod_Gallery.php');

function gallery_load() {
	Hook::register('channel_apps', 'addon/gallery/gallery.php', 'gallery_channel_apps');
	Hook::register('photo_view_filter', 'addon/gallery/gallery.php', 'gallery_photo_view_filter');
	Hook::register('page_end', 'addon/gallery/gallery.php', 'gallery_page_end');
}

function gallery_unload() {
	Hook::unregister('channel_apps', 'addon/gallery/gallery.php', 'gallery_channel_apps');
	Hook::unregister('photo_view_filter', 'addon/gallery/gallery.php', 'gallery_photo_view_filter');
	Hook::unregister('page_end', 'addon/gallery/gallery.php', 'gallery_page_end');
}

function gallery_channel_apps(&$b) {
	$uid = ((App::$profile_uid) ? App::$profile_uid : local_channel());

	if(Apps::addon_app_installed($uid, 'gallery')) {
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

function gallery_supported_modules() {
	$modules = [
		'gallery',
		'photos',
		'network',
		'channel',
		'display',
		'hq',
		'pubstream'
	];

	return $modules;
}

function gallery_photo_view_filter(&$arr) {
	$uid = ((App::$profile_uid) ? App::$profile_uid : local_channel());

	if(Apps::addon_app_installed($uid, 'gallery')) {
		$arr['onclick'] = '$.get(\'gallery/' . $arr['nickname'] . '?f=&photo=' . $arr['raw_photo']['resource_id'] . '&type=' . $arr['raw_photo']['mimetype'] . '&width=' . $arr['raw_photo']['width'] . '&height=' . $arr['raw_photo']['height'] . '&title=' . (($arr['raw_photo']['description']) ? $arr['raw_photo']['description'] : $arr['raw_photo']['filename']) . '\',  function(data) { if(! $(\'#gallery-fullscreen-view\').length) { $(\'<div></div>\').attr(\'id\', \'gallery-fullscreen-view\').appendTo(\'body\'); } $(\'#gallery-fullscreen-view\').html(data); }); return false;';
	}
}

function gallery_page_end(&$str) {
	$uid = ((App::$profile_uid) ? App::$profile_uid : local_channel());

	if(Apps::addon_app_installed($uid, 'gallery') && in_array(argv(0), gallery_supported_modules())) {
		head_add_js('/addon/gallery/lib/photoswipe/dist/photoswipe.js', 1);
		head_add_js('/addon/gallery/lib/photoswipe/dist/photoswipe-ui-default.js', 1);
		head_add_js('/addon/gallery/view/js/gallery.js', 1);

		head_add_css('/addon/gallery/lib/photoswipe/dist/photoswipe.css');
		head_add_css('/addon/gallery/lib/photoswipe/dist/default-skin/default-skin.css');

		$tpl = get_markup_template('gallery_dom.tpl', 'addon/gallery');
		$str .= replace_macros($tpl, []);
	}
}

