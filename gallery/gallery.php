<?php

/**
 * Name: Gallery
 * Description: Image Gallery
 * Version: 0.1
 * MinVersion: 3.4
 * Author: Mario
 * Maintainer: Mario
 */

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;

require_once('addon/gallery/Mod_Gallery.php');

function gallery_module() {}

function gallery_load() {
	Hook::register('load_pdl', 'addon/gallery/gallery.php', 'gallery_load_pdl');
	Hook::register('channel_apps', 'addon/gallery/gallery.php', 'gallery_channel_apps');
}

function gallery_unload() {
	Hook::unregister('load_pdl', 'addon/gallery/gallery.php', 'gallery_load_pdl');
	Hook::unregister('channel_apps', 'addon/gallery/gallery.php', 'gallery_channel_apps');
}

function gallery_load_pdl(&$b) {
	if ($b['module'] === 'gallery') {
		$b['layout'] = '
			[region=aside]
			[widget=vcard][/widget]
			[/region]
			[region=right_aside]
			[widget=notifications][/widget]
			[widget=newmember][/widget]
			[/region]
		';
	}
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

