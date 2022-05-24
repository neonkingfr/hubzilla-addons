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
require_once('addon/articles/Widget/Articles_categories.php');

function articles_load() {
	Hook::register('channel_apps', 'addon/articles/articles.php', 'articles_channel_apps');
	Hook::register('module_loaded', 'addon/articles/articles.php', 'articles_load_module');
	Hook::register('display_item', 'addon/articles/articles.php', 'articles_display_item');
	Hook::register('item_custom_display', 'addon/articles/articles.php', 'articles_item_custom_display');
	Hook::register('post_local', 'addon/articles/articles.php', 'articles_post_local');
	Hook::register('construct_page', 'addon/articles/articles.php', 'articles_construct_page');
}

function articles_unload() {
	Hook::unregister('channel_apps', 'addon/articles/articles.php', 'articles_channel_apps');
	Hook::unregister('module_loaded', 'addon/articles/articles.php', 'articles_load_module');
	Hook::unregister('display_item', 'addon/articles/articles.php', 'articles_display_item');
	Hook::unregister('item_custom_display', 'addon/articles/articles.php', 'articles_item_custom_display');
	Hook::unregister('post_local', 'addon/articles/articles.php', 'articles_post_local');
	Hook::unregister('construct_page', 'addon/articles/articles.php', 'articles_construct_page');
}

function articles_channel_apps(&$arr) {
	$uid = ((App::$profile_uid) ? App::$profile_uid : intval(local_channel()));

	if(! Apps::addon_app_installed($uid, 'articles'))
		return;

	$p = get_all_perms($uid, get_observer_hash());

	if (! $p['view_pages'])
		return;

	$arr['tabs'][] = [
		'label' => t('Articles'),
		'url'   => z_root() . '/articles/' . $arr['nickname'],
		'sel'   => ((argv(0) == 'articles') ? 'active' : ''),
		'title' => t('View Articles'),
		'id'    => 'articles-tab',
		'icon'  => 'file-text-o'
	];
}


function articles_load_module(&$arr) {
	if ($arr['module'] === 'article_edit') {
		require_once('addon/articles/Mod_Article_edit.php');
		$arr['controller'] = new Article_edit();
		$arr['installed']  = true;
	}
}

function articles_display_item(&$arr) {
	if ($arr['item']['item_type'] !== ITEM_TYPE_ARTICLE) {
		return;
	}

	// rewrite edit link
	if (isset($arr['output']['edpost'])) {
		$arr['output']['edpost'] = [
			z_root() . '/article_edit/' . $arr['item']['id'],
			t('Edit')
		];
	}

	// rewrite conv link
	if (isset($arr['output']['conv'])) {
		$arr['output']['conv'] = [
			'href' => $arr['item']['plink'],
			'title' => t('View in context')
		];
	}
}

function articles_item_custom_display($target_item) {
	if ($target_item['item_type'] !== ITEM_TYPE_ARTICLE) {
		return;
	}

	$x = channelx_by_n($target_item['uid']);

	$y = q("select iconfig.v from iconfig left join item on iconfig.iid = item.id
		where item.uid = %d and iconfig.cat = 'system' and iconfig.k = 'ARTICLE' and item.id = %d limit 1",
		intval($target_item['uid']),
		intval($target_item['parent'])
	);

	if ($x && $y) {
		goaway(z_root() . '/articles/' . $x['channel_address'] . '/' . $y[0]['v']);
	}

	notice(t('Page not found.') . EOL);
	return EMPTY_STR;
}

function articles_post_local(&$arr) {
	if ($arr['item_type'] !== ITEM_TYPE_ARTICLE) {
		return;
	}

	// rewrite category URLs
	if (is_array($arr['term'])) {
		$i = 0;
		foreach ($arr['term'] as $t) {
			if ($t['ttype'] === TERM_CATEGORY) {
				$arr['term'][$i]['url'] = str_replace('/channel/', '/articles/', $t['url']);
			}
			$i++;
		}
	}
}

function articles_construct_page(&$b){
	$o = new Articles_categories();
	$b['layout']['region_aside'] = $b['layout']['region_aside'] . $o->widget([]);
}
