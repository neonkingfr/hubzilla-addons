<?php

/**
 * Name: Cards
 * Description: Create interactive personal planning cards
 * Version: 1.0
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Module\Card_edit;

require_once('addon/cards/Mod_Cards.php');
require_once('addon/cards/Widget/Cards_categories.php');


function cards_load() {
	Hook::register('channel_apps', 'addon/cards/cards.php', 'cards_channel_apps');
	Hook::register('module_loaded', 'addon/cards/cards.php', 'cards_load_module');
	Hook::register('display_item', 'addon/cards/cards.php', 'cards_display_item');
	Hook::register('item_custom_display', 'addon/cards/cards.php', 'cards_item_custom_display');
	Hook::register('post_local', 'addon/cards/cards.php', 'cards_post_local');
	Hook::register('construct_page', 'addon/cards/cards.php', 'cards_construct_page');
}

function cards_unload() {
	Hook::unregister('channel_apps', 'addon/cards/cards.php', 'cards_channel_apps');
	Hook::unregister('module_loaded', 'addon/cards/cards.php', 'cards_load_module');
	Hook::unregister('display_item', 'addon/cards/cards.php', 'cards_display_item');
	Hook::unregister('item_custom_display', 'addon/cards/cards.php', 'cards_item_custom_display');
	Hook::unregister('post_local', 'addon/cards/cards.php', 'cards_post_local');
	Hook::unregister('construct_page', 'addon/cards/cards.php', 'cards_construct_page');
}

function cards_channel_apps(&$arr) {
	$uid = ((App::$profile_uid) ? App::$profile_uid : intval(local_channel()));

	if(! Apps::addon_app_installed($uid, 'cards'))
		return;

	$p = get_all_perms($uid, get_observer_hash());

	if (! $p['view_pages'])
		return;

	$arr['tabs'][] = [
		'label' => t('Cards'),
		'url'   => z_root() . '/cards/' . $arr['nickname'],
		'sel'   => ((argv(0) == 'cards') ? 'active' : ''),
		'title' => t('View Cards'),
		'id'    => 'cards-tab',
		'icon'  => 'list'
	];
}

function cards_load_module(&$arr) {
	if ($arr['module'] === 'card_edit') {
		require_once('addon/cards/Mod_Card_edit.php');
		$arr['controller'] = new Card_edit();
		$arr['installed']  = true;
	}
}

function cards_display_item(&$arr) {
	if ($arr['item']['item_type'] !== ITEM_TYPE_CARD) {
		return;
	}

	// rewrite edit link
	if (isset($arr['output']['edpost'])) {
		$arr['output']['edpost'] = [
			z_root() . '/card_edit/' . $arr['item']['id'],
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

function cards_item_custom_display($target_item) {
	if ($target_item['item_type'] !== ITEM_TYPE_CARD) {
		return;
	}

	$x = channelx_by_n($target_item['uid']);

	$y = q("select iconfig.v from iconfig left join item on iconfig.iid = item.id
		where item.uid = %d and iconfig.cat = 'system' and iconfig.k = 'CARD' and item.id = %d limit 1",
		intval($target_item['uid']),
		intval($target_item['parent'])
	);

	if ($x && $y) {
		goaway(z_root() . '/cards/' . $x['channel_address'] . '/' . $y[0]['v']);
	}

	notice(t('Page not found.') . EOL);
	return EMPTY_STR;
}

function cards_post_local(&$arr) {
	if ($arr['item_type'] !== ITEM_TYPE_CARD) {
		return;
	}

	// rewrite category URLs
	if (is_array($arr['term'])) {
		$i = 0;
		foreach ($arr['term'] as $t) {
			if ($t['ttype'] === TERM_CATEGORY) {
				$arr['term'][$i]['url'] = str_replace('/channel/', '/cards/', $t['url']);
			}
			$i++;
		}
	}
}

function cards_construct_page(&$b){
	$o = new Cards_categories();
	$b['layout']['region_aside'] = $b['layout']['region_aside'] . $o->widget([]);
}
