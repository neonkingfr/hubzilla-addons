<?php

/**
 *   * Name: Wiki pages
 *   * Description: Display a menu with links to the pages of a wiki
 *   * Requires: wiki
 */

namespace Zotlabs\Widget;

require_once('addon/wiki/Lib/NativeWiki.php');
require_once('addon/wiki/Lib/NativeWikiPage.php');

use NativeWiki;
use NativeWikiPage;

class Wiki_pages {

	function widget($arr) {

		if(argv(0) !== 'wiki' || argc() < 3)
			return;

		if(! $arr['resource_id']) {
			$c = channelx_by_nick(argv(1));
			$w = NativeWiki::exists_by_name($c['channel_id'],NativeWiki::name_decode(argv(2)));
			$arr = array(
				'resource_id' => $w['resource_id'],
				'channel_id' => $c['channel_id'],
				'channel_address' => $c['channel_address'],
				'refresh' => false
			);
		}

		$wikiname = '';

		$pages = array();

		$p = NativeWikiPage::page_list($arr['channel_id'],get_observer_hash(),$arr['resource_id']);

		if($p['pages']) {
			$pages = $p['pages'];
			$w = $p['wiki'];
			// Wiki item record is $w['wiki']
			$wikiname = $w['urlName'];
			if (!$wikiname) {
				$wikiname = '';
			}
			$typelock = $w['typelock'];
		}

		$can_create = perm_is_allowed(\App::$profile['uid'],get_observer_hash(),'write_wiki');

		$can_delete = ((local_channel() && (local_channel() == \App::$profile['uid'])) ? true : false);

		return replace_macros(get_markup_template('wiki_page_list.tpl', 'addon/wiki'), array(
				'$resource_id' => $arr['resource_id'],
				'$header' => t('Wiki Pages'),
				'$channel_address' => $arr['channel_address'],
				'$wikiname' => $wikiname,
				'$pages' => $pages,
				'$canadd' => $can_create,
				'$candel' => $can_delete,
				'$addnew' => t('Add new page'),
				'$typelock' => $typelock,
				'$lockedtype' => $w['mimeType'],
				'$mimetype' => mimetype_select(0,$w['mimeType'],
					[ 'text/markdown' => t('Markdown'), 'text/bbcode' => t('BBcode'), 'text/plain' => t('Text') ]),
				'$pageName' => array('pageName', t('Page name')),
				'$refresh' => $arr['refresh'],
				'$options' => t('Options'),
				'$submit' => t('Submit')
		));
	}
}


