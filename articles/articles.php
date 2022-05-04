<?php

/**
 * Name: Articles
 * Description: Create interactive articles
 * Version: 1.0
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Module\Article_edit;

require_once('addon/articles/Mod_Articles.php');

function articles_load() {
	Hook::register('module_loaded', 'addon/articles/articles.php', 'articles_load_module');
}

function articles_unload() {
	Hook::unregister('module_loaded', 'addon/articles/articles.php', 'articles_load_module');
}

function articles_load_module(&$b) {
	if ($b['module'] === 'article_edit') {
		require_once('addon/articles/Mod_Article_edit.php');
		$b['controller'] = new Article_edit();
		$b['installed']  = true;
	}
}
