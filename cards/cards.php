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

function cards_load() {
	Hook::register('module_loaded', 'addon/cards/cards.php', 'cards_load_module');
}

function cards_unload() {
	Hook::unregister('module_loaded', 'addon/cards/cards.php', 'cards_load_module');
}

function cards_load_module(&$b) {
	if ($b['module'] === 'card_edit') {
		require_once('addon/cards/Mod_Card_edit.php');
		$b['controller'] = new Card_edit();
		$b['installed']  = true;
	}
}
