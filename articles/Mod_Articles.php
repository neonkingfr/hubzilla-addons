<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;
use Zotlabs\Lib\PermissionDescription;

require_once('include/channel.php');
require_once('include/conversation.php');
require_once('include/acl_selectors.php');
require_once('include/opengraph.php');


class Articles extends Controller {

	function init() {

		$which = '';
		if (argc() > 1)
			$which = argv(1);

		if (!$which) {
			if (local_channel()) {
				$channel = App::get_channel();
				if ($channel && $channel['channel_address'])
					$which = $channel['channel_address'];
			}
			else {
				return;
			}
		}

		profile_load($which);

	}

	function get($update = 0, $load = false) {

		if (observer_prohibited(true)) {
			return login();
		}

		if (!App::$profile) {
			notice(t('Requested profile is not available.') . EOL);
			App::$error = 404;
			return;
		}

		if (!Apps::addon_app_installed(App::$profile_uid, 'articles')) {
			//Do not display any associated widgets at this point
			App::$pdl = EMPTY_STR;

			if (App::$profile_uid !== local_channel()) {
				return EMPTY_STR;
			}

			$papp     = Apps::get_papp('Articles');
			return Apps::app_render($papp, 'module');
		}

		nav_set_selected('Articles');

		head_add_link([
			'rel'   => 'alternate',
			'type'  => 'application/json+oembed',
			'href'  => z_root() . '/oep?f=&url=' . urlencode(z_root() . '/' . App::$query_string),
			'title' => 'oembed'
		]);


		$category   = (($_REQUEST['cat']) ? escape_tags(trim($_REQUEST['cat'])) : '');
		$sql_extra2 = '';

		if ($category) {
			$sql_extra2 .= protect_sprintf(term_item_parent_query(App::$profile['profile_uid'], 'item', $category, TERM_CATEGORY));
		}

		$datequery  = ((x($_GET, 'dend') && is_a_date_arg($_GET['dend'])) ? notags($_GET['dend']) : '');
		$datequery2 = ((x($_GET, 'dbegin') && is_a_date_arg($_GET['dbegin'])) ? notags($_GET['dbegin']) : '');

		$selected_card = ((argc() > 2) ? argv(2) : '');

		$_SESSION['return_url'] = App::$query_string;

		$uid      = local_channel();
		$owner    = App::$profile_uid;
		$observer = App::get_observer();

		$ob_hash = (($observer) ? $observer['xchan_hash'] : '');

		if (!perm_is_allowed($owner, $ob_hash, 'view_pages')) {
			notice(t('Permission denied.') . EOL);
			return;
		}

		$is_owner = ($uid && $uid == $owner);

		$channel = channelx_by_n($owner);

		if ($channel) {
			$channel_acl = [
				'allow_cid' => $channel['channel_allow_cid'],
				'allow_gid' => $channel['channel_allow_gid'],
				'deny_cid'  => $channel['channel_deny_cid'],
				'deny_gid'  => $channel['channel_deny_gid']
			];
		}
		else {
			$channel_acl = ['allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => ''];
		}

		if (perm_is_allowed($owner, $ob_hash, 'write_pages')) {

			$x = [
				'webpage'           => ITEM_TYPE_ARTICLE,
				'is_owner'          => true,
				'content_label'     => t('Add Article'),
				'button'            => t('Save'),
				'nickname'          => $channel['channel_address'],
				'lockstate'         => (($channel['channel_allow_cid'] || $channel['channel_allow_gid']
					|| $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
				'acl'               => (($is_owner) ? populate_acl($channel_acl, false,
					PermissionDescription::fromGlobalPermission('view_pages')) : ''),
				'permissions'       => $channel_acl,
				'showacl'           => (($is_owner) ? true : false),
				'visitor'           => true,
				'hide_location'     => false,
				'hide_voting'       => false,
				'profile_uid'       => intval($owner),
				'mimetype'          => 'text/bbcode',
				'mimeselect'        => false,
				'layoutselect'      => false,
				'expanded'          => false,
				'novoting'          => false,
				'catsenabled'       => feature_enabled($owner, 'categories'),
				'bbco_autocomplete' => 'bbcode',
				'bbcode'            => true
			];

			if ($_REQUEST['title'])
				$x['title'] = $_REQUEST['title'];
			if ($_REQUEST['body'])
				$x['body'] = $_REQUEST['body'];

			$editor = status_editor($x, false, 'Articles');
		}
		else {
			$editor = '';
		}

		$itemspage = get_pconfig(local_channel(), 'system', 'itemspage');
		App::set_pager_itemspage(((intval($itemspage)) ? $itemspage : 10));
		$pager_sql = sprintf(" LIMIT %d OFFSET %d ", intval(App::$pager['itemspage']), intval(App::$pager['start']));


		$sql_extra = item_permissions_sql($owner);
		$sql_item  = '';

		if ($selected_card) {
			$r = q("select * from iconfig where iconfig.cat = 'system' and iconfig.k = 'ARTICLE' and iconfig.v = '%s' limit 1",
				dbesc($selected_card)
			);
			if ($r) {
				$sql_item = "and item.id = " . intval($r[0]['iid']) . " ";
			}
		}
		if ($datequery) {
			$sql_extra2 .= protect_sprintf(sprintf(" AND item.created <= '%s' ", dbesc(datetime_convert(date_default_timezone_get(), '', $datequery))));
			$order      = 'post';
		}
		if ($datequery2) {
			$sql_extra2 .= protect_sprintf(sprintf(" AND item.created >= '%s' ", dbesc(datetime_convert(date_default_timezone_get(), '', $datequery2))));
		}

		if ($datequery || $datequery2) {
			$sql_extra2 .= " and item.item_thread_top != 0 ";
		}

		$item_normal = " and item.item_hidden = 0 and item.item_type in (0,7) and item.item_deleted = 0
			and item.item_unpublished = 0 and item.item_delayed = 0 and item.item_pending_remove = 0
			and item.item_blocked = 0 ";

		$r = q("select id from item
			where item.uid = %d and item_type = %d and item_thread_top = 1
			$sql_extra $sql_extra2 $sql_item $item_normal order by item.created desc $pager_sql",
			intval($owner),
			intval(ITEM_TYPE_ARTICLE)
		);

		if ($r) {

			$pager_total = count($r);

			$parents_str = ids_to_querystr($r, 'id');

			$r = q("SELECT item.*, item.id AS item_id
				FROM item
				WHERE item.uid = %d $item_normal
				AND item.parent IN ( %s )
				$sql_extra",
				intval(App::$profile['profile_uid']),
				dbesc($parents_str)
			);
			if ($r) {
				xchan_query($r);
				$items = fetch_post_tags($r, true);
				$items = conv_sort($items, 'updated');
			}
			else
				$items = [];
		}

		// Add Opengraph markup
		opengraph_add_meta((!empty($items) ? $r[0] : []), $channel);

		$mode = 'articles';

		if (get_pconfig(local_channel(), 'system', 'articles_list_mode') && (!$selected_card))
			$page_mode = 'pager_list';
		else
			$page_mode = 'traditional';

		$content = conversation($items, $mode, false, $page_mode);

		$o = replace_macros(get_markup_template('articles.tpl', 'addon/articles'), [
			'$title'   => t('Articles'),
			'$editor'  => $editor,
			'$content' => $content,
			'$pager'   => alt_pager($pager_total)
		]);

		return $o;
	}

}
