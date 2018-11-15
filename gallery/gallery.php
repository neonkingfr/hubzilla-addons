<?php

/**
 * Name: Gallery
 * Description: Image Gallery
 * Version: 0.1
 * MinVersion: 3.4
 * Author: Mario
 * Maintainer: Mario
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;

require_once('addon/gallery/Mod_Gallery.php');

function gallery_load() {
	Hook::register('channel_apps', 'addon/gallery/gallery.php', 'gallery_channel_apps');
}

function gallery_unload() {
	Hook::unregister('channel_apps', 'addon/gallery/gallery.php', 'gallery_channel_apps');
}

function gallery_channel_apps(&$b) {
	if(Apps::system_app_installed(App::$profile_uid, 'Gallery')) {
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

