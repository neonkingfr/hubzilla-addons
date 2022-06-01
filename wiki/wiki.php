<?php

/**
 * Name: Wiki
 * Description: A simple yet powerful wiki
 * Version: 1.0
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;

require_once('addon/wiki/Mod_Wiki.php');
require_once('addon/wiki/Lib/NativeWiki.php');
require_once('addon/wiki/Lib/NativeWikiPage.php');
require_once('addon/wiki/Widget/Wiki_pages.php');

function wiki_load() {
	Hook::register('construct_page', 'addon/wiki/wiki.php', 'wiki_construct_page');
}

function wiki_unload() {
	Hook::unregister('construct_page', 'addon/wiki/wiki.php', 'wiki_construct_page');
}

function wiki_construct_page(&$b){
	$o = new Wiki_pages();
	$b['layout']['region_aside'] = $b['layout']['region_aside'] . $o->widget([]);
}
