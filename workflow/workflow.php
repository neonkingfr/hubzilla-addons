<?php
/**
 * Name: Workflow
 * Description: Workflow - Item Tracking (BASE)
 * Version: 1.0
 * Author: DM42.Net, LLC
 * Maintainer: devhubzilla@dm42.net
 * MinVersion: 4.4.2
 */

use \App;
use \Zotlabs\Lib\Apps;
use \Zotlabs\Lib\IConfig;
use \Zotlabs\Lib\PConfig;
use \Zotlabs\Lib\Activity;
use \Zotlabs\Extend\Route;
use \Zotlabs\Extend\Hook;
use \Zotlabs\Daemon\Master;
use \Zotlabs\Access\PermissionLimits;
require_once ('include/items.php');
require_once ('include/text.php');
require_once ('include/conversation.php');

define('WORKFLOW_ACTIVITY_OBJ_TYPE','http://purl.org/dm42/as/workflow#workflow');

function workflow_load() {
	$hookfile = 'addon/workflow/workflow.php';
	Hook::register('item_custom_display',$hookfile,'Workflow_Utils::item_custom_display',1,30000);
	Hook::register('customitem_deliver',$hookfile,'Workflow_Utils::customitem_deliver',1,30000);
	Hook::register('permissions_list',$hookfile,'Workflow_Utils::permissions_list',1,30000);
	Hook::register('permission_limits_get',$hookfile,'Workflow_Utils::permission_limits_get',1,30000);
        Hook::register('dropdown_extras', 'addon/workflow/workflow.php', 'Workflow_Utils::dropdown_extras',1,30000);
        Hook::register('page_header', 'addon/workflow/workflow.php', 'Workflow_Utils::page_header',1,30000);
        Hook::register('page_end', 'addon/workflow/workflow.php', 'Workflow_Utils::page_end',1,30000);
	Hook::register('workflow_get_items_filter',__FILE__,'Workflow_Utils::get_items_filter_related',1,1000);
	Hook::register('activity_mapper',__FILE__,'Workflow_Utils::activity_mapper',1,1000);
	Hook::register('activity_decode_mapper',__FILE__,'Workflow_Utils::activity_mapper',1,1000);
	Hook::register('activity_obj_mapper',__FILE__,'Workflow_Utils::activity_obj_mapper',1,1000);
	Hook::register('activity_obj_decode_mapper',__FILE__,'Workflow_Utils::activity_obj_mapper',1,1000);
	Hook::register('item_store_before',__FILE__,'Workflow_Utils::set_item_type',1,31000);
	Hook::register('item_store_update_before',__FILE__,'Workflow_Utils::set_item_type',1,31000);
	Hook::register('item_store_before',$hookfile,'Workflow_Utils::item_custom_store',1,30999);
	Hook::register('item_store_update_before',$hookfile,'Workflow_Utils::item_custom_store',1,30999);
	Hook::register('content_security_policy',__FILE__,'Workflow_Utils::content_security_policy',1,1000);
	Hook::register('decode_note',__FILE__,'Workflow_Utils::decode_note',1,1000);
	Hook::register('workflow_display_list_headers',__FILE__,'Workflow_Utils::basicfilter_display_header',1,1000);
	Hook::register('workflow_toolbar',__FILE__,'Workflow_Utils::toolbar_header',1,1000);
	Route::register('addon/workflow/Mod_Workflow.php','workflow');
	Route::register('addon/workflow/Settings/Mod_WorkflowSettings.php','settings/workflow');
}

function workflow_unload() {
	Hook::unregister_by_file(__FILE__);
	Route::unregister_by_file(dirname(__FILE__).'/Mod_Workflow.php');
	Route::unregister_by_file(dirname(__FILE__).'/Mod_WorkflowSettings.php');
}

class Workflow_Utils {

	private static $cspframesrcs=[];

	public static function item_stored(&$arr) {
		return;
	}

	public static function content_security_policy(&$csp) {

		$uid = App::$profile_uid;

		if (!$uid) {
			return;
		}

		if (!Apps::addon_app_installed($uid,'workflow')) { return; }

		if (argv(0) != 'workflow') {
			return;
		}

		$newcsp = $csp;
		$wfchannels = q("select hubloc_url,hubloc_addr,xchan_addr from hubloc join xchan on hubloc_hash = xchan_hash join abconfig on xchan=xchan_hash where chan = %d and cat = 'their_perms' and (k = 'workflow_user' or k = 'workflow_additems') and v = '1'",
				intval($uid)
			);

		foreach ($wfchannels as $wfhost) {
			$newcsp['frame-src'][]=$wfhost['hubloc_url'];
		}

		$newcsp['frame-src'][]=z_root();
		$newcsp['frame-src']=array_unique(array_merge($newcsp['frame-src'],self::$cspframesrcs));

		$csp = $newcsp;
	}

	public static function customitem_deliver(&$hookinfo) {

		$target_item = $hookinfo['targetitem'];
		$uid = $target_item['uid'];
		if (!$uid) {
			return;
		}
		if (!Apps::addon_app_installed($uid,'workflow')) { return; }

		$newhookinfo = $hookinfo;
		$newhookinfo['deliver'] = true;

		$hookinfo=$newhookinfo;
	}

	public static function fetch_and_store(&$hookinfo) {
		// For HOOKS:
		// 	fetch_and_store (Zotlabs/Lib/Activity.php)

	}

	public static function encode_object(&$hookinfo) {
		// For HOOKS:
		// 	encode_object (outgoing) (Zotlabs/Lib/Activity.php)
	}

	public static function activity_mapper(&$hookinfo) {
		// For HOOKS:
		// 	activity_mapper
		// 	activity_decode_mapper

		$uid = App::$profile_uid;
		if (!$uid) {
			$uid = local_channel();
		}

		if (!Apps::addon_app_installed($uid,'workflow')) { return; }

		$newinfo = $hookinfo;
		$hookinfo = $newinfo;
	}


	public static function activity_obj_mapper (&$hookinfo) {
		// For HOOKS:
		// 	activity_obj_mapper
		// 	activity_obj_decode_mapper

		$uid = App::$profile_uid;
		if (!$uid) {
			$uid = local_channel();
		}

		if (!Apps::addon_app_installed($uid,'workflow')) { return; }

		$newinfo = $hookinfo;

		//$newinfo[WORKFLOW_ACTIVITY_OBJ_TYPE] = 'dm42:workflow';
		$newinfo[WORKFLOW_ACTIVITY_OBJ_TYPE] = WORKFLOW_ACTIVITY_OBJ_TYPE;
		$hookinfo = $newinfo;
	}

	public static function set_item_type(&$hookinfo) {

		$arr = $hookinfo['item'];

		//$uid = App::$profile_uid;
		$uid = $arr['uid'];

		if (!$uid) { return; }

		if (!Apps::addon_app_installed($uid,'workflow')) { 
			if ($arr['obj_type'] == WORKFLOW_ACTIVITY_OBJ_TYPE) {
				$arrinfo['obj_type'] = ACTIVITY_OBJ_NOTE;
				$arrinfo['item_type'] = ITEM_TYPE_POST;
			}
			return; 
		}

		$arrinfo = $arr;

		if ($arr['obj_type'] == WORKFLOW_ACTIVITY_OBJ_TYPE) {
			$arrinfo['item_type'] = ITEM_TYPE_CUSTOM;
		}

		$arr = $arrinfo;

		$hookinfo = [
			'item'=>$arr,
			'allow_exec'=>$hookinfo['allow_exec']
		];

		return;
	}

	public static function permission_limits_get(&$arr) {
		if ($arr['permission']=='workflow_user') {
			$newarr = $arr;
			$newarr['value']=128;
			$arr=$newarr;
		}
		return;
	}


	public static function permissions_list(&$arr) {
	    $uid = local_channel();
	    if (!Apps::addon_app_installed($uid,'workflow')) { return; }
	    $new = $arr;
    	    $new['permissions']['workflow_user'] = t('Workflow user.');
	    $arr = $new;
	    return;
	}

	public static function get_workflowusers () {
		$wfchannels = q("select hubloc_host,hubloc_addr,xchan_addr,xchan_name,hubloc_primary,xchan_hash from hubloc join xchan on hubloc_hash = xchan_hash join abconfig on xchan=xchan_hash where chan = %d and cat = 'my_perms' and (k = 'workflow_user' or k = 'workflow_additems') and v = '1'",
		intval(App::$profile_uid));

		$wfusers = [];

		foreach ($wfchannels as $c) {
			$wfusers[$c['xchan_hash']]=$c['xchan_name'].' - '.$c['xchan_addr'];
		}

		return $wfusers;

	}

	public static function basicfilter_gatherassigned ($items) {
		foreach ($items as $item) {
			$assigned = self::maybeunjson(IConfig::Get($item,'workflow','contacts:assigned','{}'));
			foreach ($assigned as $c) {
				$users[$c] = [];
			}
		}
		foreach ($users as $hash => $info) {
			$xc = q("select xchan_name,xchan_addr from xchan where xchan_hash = '%s' limit 1",
				dbesc($hash));

			if ($xc) {
				$wfusers[$hash]=$xc[0]['xchan_name'].' - '.$xc[0]['xchan_addr'];
			}
		}
		return $wfusers;
	}


	public static function get_workflowlist () {
		$remoteworkflows=[];
		if(local_channel()) {
			$mychan = channelx_by_n(local_channel());
			$mywfchan['host']=$_SERVER['SERVER_NAME'];
			$mywfchan['id']=substr(get_my_address(),0,strpos(get_my_address(),'@'));
			$mywfchan['name'] = $mychan['xchan_name'];
			$mywfchan['wfaddr']='https://'.$mywfchan['host'].'/workflow/'.$mywfchan['id'];
			$mywfchan['primary'] = 1;
			$mywfchan['name'].=' ('.t('This channel').')';
			$remoteworkflows[$mychan['channel_hash']] = $mywfchan;

			$wfchannels = q("select hubloc_host,hubloc_addr,xchan_addr,xchan_name,hubloc_primary,xchan_hash from hubloc join xchan on hubloc_hash = xchan_hash join abconfig on xchan=xchan_hash where chan = %d and cat = 'their_perms' and (k = 'workflow_user' or k = 'workflow_additems') and v = '1'",
				intval(local_channel()));
			if ($wfchannels) {
				foreach ($wfchannels as $wfchannel) {
					$wfchan['host']=$wfchannel['hubloc_host'];
					$wfchan['id']=substr($wfchannel['xchan_addr'],0,strpos($wfchannel['xchan_addr'],'@'));
					$wfchan['primary'] = (isset($wfchannel['hubloc_primary'])) ? 1 : 0;
					$wfchan['name']=$wfchannel['xchan_name'];
					$wfchan['wfaddr'] = 'https://'.$wfchan['host'].'/workflow/'.$wfchan['id'];
					if ($wfchan['primary']) {
						$wfchan['name'].=' ('.t('Primary').')';
					}
				$remoteworkflows[$wfchannel['xchan_hash']] = $wfchan;
				}
			}

		} else {
			$wfchannels = q("select xchan from abconfig where chan = %d and xchan = '%s' and cat = 'my_perms' and (k = 'workflow_user' or k = 'workflow_additems') and v = '1'",
				App::$profile_uid,
				dbesc(get_observer_hash()));
			
			if ($wfchannels) {
				$channel=channelx_by_hash(get_observer_hash());
				$local=channelx_by_n(App::$profile_uid);
				$wfchan['host']=z_root();
				$wfchan['id']=$channel['address'];
				$wfchan['name']='Local';
				$wfchan['wfaddr'] = z_root().'/workflow/'.substr($local['xchan_addr'],0,strpos($local['xchan_addr'],'@'));
				$remoteworkflows[$local['xchan_hash']] = $wfchan;
			}
		}
		return $remoteworkflows;
	}

	public static function dropdown_extras (&$extras) {

		// @todo: Needs to be check for observer, not just local.
		$uid = App::$profile_uid;
		if (!$uid) {
			$uid = local_channel();
		}

		if (!Apps::addon_app_installed($uid,'workflow')) { return; }

		// @todo: Need to check that observer can add workflow items
		$channel = channelx_by_n($uid);

		$posturl = '/workflow/'.$channel['channel_address'];

                $arr = $extras;

                $item_link = $extras['item']['plink'];
                $arr['dropdown_extras'] .= '<a class="dropdown-item" href="#" onclick="workflowShowNewItemForm(\''.$item_link.'\',\''.$posturl.'\'); return false;" title="Workflow"><i class="generic-icons-nav fa fa-fw fa-tasks"></i> Create New Workflow Item</a>';
                $extras = $arr;
        }

	public static function item_custom_display($target_item) {
		$uid = isset($target_item['uid']) ? intval($target_item['uid']) : false;

		if (!Apps::addon_app_installed($uid,'workflow')) { return; }

		if ($uid) {
			$channel=channelx_by_n($uid);
			$item = q("select * from item where uid = %d and mid ='%s'",
				intval($target_item['uid']),
				dbesc($target_item['mid']));

			if (!$item) { return; }
			goaway(z_root().'/workflow/'.$channel['channel_address'].'/display/'.$item[0]['uuid']);
		}
	}


	public static function display() {

		$uid = App::$profile_uid;
		if (!Apps::addon_app_installed($uid,'workflow')) { return; }

		$channel = channelx_by_n($uid);

		$item_normal = "item.item_deleted = 0 and item.item_pending_remove = 0
				and item.item_blocked = 0
				and item.obj_type ='".WORKFLOW_ACTIVITY_OBJ_TYPE."'";

		$uuid = argv(3);

		if (!$uuid) { return; }

		$observer = get_observer_hash();

		$permissions = permissions_sql($uid,$observer,'item');

		$sql_extra = $permissions;

		$item = q("Select item.*, item.id as item_id
			from item
			where item.uid = %d and $item_normal
			and item.uuid = '%s'
			$sql_extra ",
				$uid,
				dbesc($uuid)
			);

		if (!$item) { return; }

		$body=prepare_body($item[0],true);

                $child_items = q("SELECT item.*, item.id AS item_id
			FROM item
			WHERE item.uid = %d and $item_normal
			AND item.parent = %d
			$sql_extra ",
				$uid,
				dbesc($item[0]['id'])
			);


		if ($child_items) {
			xchan_query($child_items);
			$child_items = fetch_post_tags($child_items, true);
			$items = conv_sort($child_items,'sort_thr_created_rev');
		}
		else {
			$items = [];
		}

		if (!$items) {
			return;
		}

		$hookinfo=['items'=>$items,'observer'=>$observer];
		call_hooks('workflow_get_items_filter',$hookinfo);
		$items = $hookinfo['items'];

		$hubinfo = q("select hubloc_addr,hubloc_url,xchan_addr from hubloc left join xchan on (hubloc_hash = xchan_hash) where hubloc_hash = '%s' ",
			dbesc($item['owner_xchan'])
		);
		if (!$hubinfo) {
			$iframeurl = '/workflow/'.$channel['channel_address'];
		} else {
			$hubinfo = $hubinfo[0];
			$addr = substr($hubinfo['hubloc_addr'], 0, strpos($hubinfo['hubloc_addr'], '@'));
			$iframeurl = $hubinfo['hubloc_url'].'/workflow/'.$addr;
		}

		$posturl = z_root().'/workflow/'.$channel['channel_address'];


		foreach($items as $itemidx => $item) {
			foreach ($item['related'] as $idx => $related) {

				$rellink = preg_replace("/^http:/i", "https:", $related['relatedlink']);
				if (strpos($rellink,'?') === false) {
					$relurl = $rellink.'?zid='.get_my_address();
				} else {
				        $relurl = $rellink.'&zid='.get_my_address();
				}
				$items[$itemidx]['related'][$idx]['jsondata'] = json_encode(['iframeurl'=>$relurl,'action'=>'getmodal_getiframe']);
				$items[$itemidx]['related'][$idx]['jsoneditdata'] = json_encode(['action'=>'form_addlink','uuid'=>$uuid,'iframeurl'=>$posturl,'relatedlink'=>$related['relatedlink']]);
				$items[$itemidx]['related'][$idx]['action'] = 'getmodal_getiframe';
				$items[$itemidx]['related'][$idx]['relurl'] = $relurl;
				$items[$itemidx]['related'][$idx]['uniq'] = md5($relurl);

				$urlinfo = parse_url($relurl);
				$urlhost = $urlinfo["scheme"]."://".$urlinfo["host"];
				self::$cspframesrcs[]=$urlhost;
			}
		}

		$items = fetch_post_tags($items);

		$item=$items[0];
		$itemmeta = [];

		$hookinfo=[
			'item'=>$item,
			'itemmeta'=>$itemmeta,
			'uuid'=>$uuid,
			'iframeurl'=>$iframeurl,
			'posturl'=>$posturl
		];

		Hook::insert('dm42workflow_meta_display','Workflow_Utils::basicmeta_meta_display',1,30000);
		Hook::insert('dm42workflow_meta_display','Workflow_Utils::contact_meta_display',1,30000);

		call_hooks('dm42workflow_meta_display',$hookinfo);

		usort($hookinfo['itemmeta'],function($a,$b) {
			if (intval(@$a['order']) == intval(@$b['order'])) {
				return 0;
			}

			$ret = (intval(@$a['order']) > intval(@$b['order'])) ? 1 : -1;
			return $ret;
		});

		$itemmeta=$hookinfo['itemmeta'];

		$tpl = get_markup_template('workflow_display.tpl','addon/workflow');
		$vars = [
			'$posturl' => z_root().'/workflow/'.$channel['channel_address'],
			//'$posturl' => $posturl,
			'$body' =>$body,
			'$itemmeta'=>$itemmeta,
			'$items'=>$items,
			'$myzid'=>get_my_address(),
			'$addlinkmiscdata'=>json_encode(['action'=>'form_addlink','uuid'=>$uuid,'iframeurl'=>$posturl]),
			'$addlinkaction'=>'getmodal_getiframe',
			'$edittaskjsondata' => json_encode(['action'=>'form_edittask','uuid'=>$uuid,'iframeurl'=>$iframeurl])
		];

        	$o = replace_macros($tpl,$vars);
		return $o;
	}

	public static function get() {

		$uid = App::$profile_uid;
		if (!Apps::addon_app_installed($uid,'workflow')) { return; }

		if (argc()<3) {
			logger("Workflow argc < 3",LOGGER_DEBUG);
			return self::display_list();
		}

		switch (argv(2)) {


			case 'display':
				logger("Workflow Display",LOGGER_DEBUG);
				if (argc()>3) {
					return self::display();
				}
				break;

			case 'login':
				header("Access-Control-Allow-Origin: *");
				if ((perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_user')) ||
				   (perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_additems'))) {
					json_return_and_die(["success"=>1]);
				} else {
					json_return_and_die(["success"=>0]);
				}
				killme();
				break;

			default:
				logger("Workflow bad command: ".argv(2));
				if (argc()==3) {
					return self::display_list();
				}
		}
	}

	private static function get_items($searchvars,$observer = null,$do_callhooks = true) {

		$ownersearch = '';
		$itemtype = '';
		$objtype = "item.obj_type = '".WORKFLOW_ACTIVITY_OBJ_TYPE."'";
		if (isset($searchvars['itemtype'])) {
			if ($searchvars['itemtype'] == 'all') {
				$itemtype = "";
			} elseif (is_array($searchvars['itemtype'])) {
				$itemtype = "and item.item_type in (".implode(",",$searchvars['itemtype']).")";
			} else {
				$itemtype = "and item.item_type = ".intval($searchvars['itemtype']);
			}
		}

		$uuids = '';
		if (isset($searchvars['uuid'])) {

			if (is_array($searchvars['uuid'])) {
				$uuids = 'and item.uuid in '.implode(',',implode($searchvars['uuid']));
			} else {
				$uuids = 'and item.uuid = \''.dbesc($searchvars['uuid']).'\'';
			}
		}
		if (isset($searchvars['owner'])) {
			if (is_array($searchvars['owner'])) {
				$owners = [];
				foreach ($searchvars['owner'] as $owner) {
					$owners[] = $owner;
				}
			}

			if (count($owners)) {
				$owners = implode('','',$owners);
				$ownersearch = "item.owner in ('".$owners."')";
			}
		}

		$limit = '';
		if (isset($searchvars['count'])) {
			if (is_int($searchvars['count'])) {
				$limit = "limit ".intval($searchvars['count']);
			}
		}

		$offset = '';
		if (isset($searchvars['offset'])) {
			$offset = 'offset '.intval($searchvars['offset']);
		}

		if (isset($searchvars['retcols'])) {
			//@TODO: allow customized return columns
			$retcols = '*';
		} else {
			$retcols = '*';
		}

		$deleted = (isset($searchvars['deleted'])) ? '' : 'AND item_deleted = 0';

		$uid = isset($searchvars['uid']) ? intval($searchvars['uid']) : App::$profile_uid;

		$iconfigjoins = '';
		$iconfigwhere = '';

		if (isset($searchvars['iconfig'])) {
			$iconfigtables = [];
			$valid_comparitors = ['like'=>'like','gt'=>'>','lt'=>'<','eq'=>'=','in'=>'in','notin'=>'not in','gteq'=>'>=','lteq'=>'<=','neq'=>'<>','nlike'=>'not like'];
			if (is_array($searchvars['iconfig'])) {
				foreach ($searchvars['iconfig'] as $key => $params) {

					$astable = "iconfig_".random_string(8);
					while (in_array($astable,$iconfigtables)) {
						$astable = "iconfig_".random_string(8);
					}

					$catkey = $astable.'.cat = "workflow" and '.$astable.".k ='".dbesc($key)."'";
					$val = '';

					if (isset($params['comparison'])) {
						$comparitor = $params['comparison'];
						if (!in_array($comparitor,array_keys($valid_comparitors))) {
							logger ('Invalid comparitor: '.$comparitor,LOGGER_DEBUG);
							continue;
						}

						$comparitor = $valid_comparitors[$comparitor];
						$value = $params['value'];


						if (isset($params['type']) && $params['type']=='int') {
							$val = ' and CAST('.$astable.'.v as INT) '.$comparitor.' CAST("'.dbesc($value).'" as INT)';
						} else {
							$val = ' and '.$astable.'.v '.$comparitor.' "'.dbesc($value).'"';
						}

					}

					//$iconfigwhere .= ' and '.$catkey.$val;

					$iconfigjoins .= ' left outer join iconfig as '.$astable.' on (item.id = '.$astable.'.iid and '.$astable.".cat='workflow' and ".$astable.".k='".$key."')";
					if (isset($params['orderby'])) {
						$orderinfo='';
						$default=$params['orderby']['default'];
						if (isset($params['orderby']['type']) && strtolower($params['orderby']['type']) == 'int') {
							$orderinfo = 'COALESCE(CAST ('.$astable.'.'.dbesc($key).' as int),'.intval($default).')';
						} else {
							$orderinfo = 'COALESCE('.$astable.'.v,'.dbesc($default).')';
						}

						switch (strtolower($params['orderby']['order'])) {
							case 'desc':
								$order = $orderinfo.' desc';
								break;
							default:
								$order = $orderinfo;
						}

						if (isset($searchvars['orderby']) && $searchvars['orderby']!='') {
							$searchvars['orderby'] .= ' , '.$order;
						} else {
							$searchvars['orderby'] = $order;
						}
					}
				}
			}
		}

		if (isset($searchvars['orderby'])) {
			$orderby = 'order by '.$searchvars['orderby'];
		}
		else {
			$orderby = 'order by item.commented';
		}

		$permissions = permissions_sql($uid,$observer,'item');
		$query = "select item.id as item_id from item $iconfigjoins where item.uid = $uid and item.mid=item.parent_mid and $objtype $itemtype $uuids $permissions $ownersearch $iconfigwhere $deleted $orderby $limit $offset";

		$itemids = q($query);

		if ($itemids) {
			$ids = [];
			foreach ($itemids as $item) {
				$ids[]=$item['item_id'];
			}
			$idlist = implode(',',$ids);
			//$items = q("select * from item left join abook on ( item.owner_xchan = abook.abook_xchan and item.owner_xchan = abook.abook_xchan and abook.abook_channel = ".$uid." ) where id in ($idlist)");
			$items = q("select *,id as item_id from item where id in ($idlist)");
			$items=fetch_post_tags($items);
			if ($do_callhooks == true) {
				$hookinfo=['items'=>$items,'observer'=>$observer];
				call_hooks('workflow_get_items_filter',$hookinfo);
				$items = $hookinfo['items'];
			}

			return $items;
		}
		else {
			return false;
		}
	}

	public static function get_items_filter_related(&$hookinfo) {
		if (!isset($hookinfo['items'])) { return; }
		$observer = (isset($hookinfo['observer'])) ? $hookinfo['observer'] : null;
		$newitems = [];
		while ($item = array_shift($hookinfo['items'])) {
			$related_links=[];
			if (isset($item['iconfig'])) {
				foreach($item['iconfig'] as $icfg) {
					if (strpos($icfg['k'],'link:related:') === 0) {
						$related_links[$icfg['k']]=json_decode($icfg['v'],true);
					}
				}
				if (count($related_links)) {
					$item['related'] = $related_links;
				}

			}
			$newitems[] = $item;
		}
		$newhookinfo = $hookinfo;
		$newhookinfo['items'] = $newitems;
		$hookinfo=$newhookinfo;
	}

	public static function display_list_basicfilter(&$hookinfo) {

		$items = $hookinfo['items'];

		$availworkflows = self::get_workflowlist();
		$workflowcount = count($availworkflows);

		$assigned = isset($_REQUEST["assigned"]) ? $_REQUEST["assigned"] : null;
		$minpriority = isset($_REQUEST["minpriority"]) ? intval($_REQUEST["minpriority"]) : 1;

		if ($workflowcount > 1) {
			$workflows = isset($_REQUEST["workflows"]) ? $_REQUEST["workflows"] : null;
		} else {
			$workflows = array_keys($availworkflows);
		}

		// NOTE: fetch_post_tags does not appear to add iconfig data properly
		//       so at this point, we do not use the iconfig data in the item itself
                //       but make a separate request - this will slow things down - but better
                //       safe than sorry at this point.  @TODO look into it for later
		foreach ($items as $key => $item) {

			$priority = intval(IConfig::Get($item['id'],'workflow','priority',1));

			if ($priority < $minpriority) {
				unset($items[$key]);
				continue;
			}

			if (is_array($assigned)) {
				$assignments = self::maybeunjson(IConfig::Get($item['id'],'workflow','contacts:assigned','{}'));
				if (!count(array_intersect($assignments,$assigned))) {
					unset($items[$key]);
					continue;
				}
			}

			if (is_array($workflows)) {
				if (!in_array($item['owner_xchan'],$workflows)) {
					unset($items[$key]);
					continue;
				}
			}
		}

		$hookinfo = [
			'items' => $items
		];
	}

	public static function display_list() {

		$ownerchan = channelx_by_n(App::$profile_uid);
		$searchvars = [
			'uid'=>App::$profile_uid
		];

		$vars=[];
		$items = self::get_items($searchvars,get_observer_hash());
		$itemlist = [];
		$channels =[];
		if (!$items) { $items = []; }
		$items=fetch_post_tags($items);

		$hookinfo = [
			'items' => $items
		];

		Hook::insert('dm42workflow_display_list','Workflow_Utils::display_list_basicfilter',1,30000);

		call_hooks('dm42workflow_display_list',$hookinfo);

		$items = $hookinfo['items'];
	
		foreach ($items as $item) {
			$newitem = [];
			$newitem['url']=$item['mid'].'?zid='.get_my_address();
			$newitem['target']='new';
			$newitem['title']=$item['title'];
			$newitem['body']=prepare_body($item,true);
			$newitem['body']=$newitem['body']['html'];
			$newitem['status']='';
			$ownerx = $item['owner_xchan'];
			$newitem['channelname']="CHANNEL";
			if (!isset($channels[$ownerx])) {
		 		$ch = q("select xchan_name,xchan_addr,xchan_url from xchan where xchan_hash = '%s'",
					$item['owner_xchan']);
				if ($ch) {
					$newitem['channelname']=$ch[0]['xchan_name'];
				}
			}

			$itemstatus=IConfig::Get($item,'workflow','status','Open');
			$itempriority=IConfig::Get($item,'workflow','priority',1);
			$newitem['priority']=$itempriority;
			$thisextra = '<div class="workflow wfmeta-item">Status: '.$itemstatus.' ('.$itempriority.')</div>';
			$listextras = [$thisextra];

			$hookinfo = [
				'item'=>$newitem,
				'listextras'=>$listextras
			];

			call_hooks('dm42workflow_display_list_item_extras',$hookinfo);

			$newitem['listextras'] = '';
			foreach ($hookinfo['listextras'] as $extra) {
				$newitem['listextras'] .= $extra;
			}
			$itemlist[]=$newitem;
		}

		$vars['title']="Current Items";
		usort($itemlist,function($a,$b) {
			if (intval(@$a['priority']) == intval(@$b['priority'])) {
				return 0;
			}

			$ret = (intval(@$a['priority']) < intval(@$b['priority'])) ? 1 : -1;
			return $ret;
		});
		$vars['items']=$itemlist;

		$headerrows=[ 
			'items' => $items,
			'rows' => []
		];
		$vars['toolbar'] = self::get_toolbar($items);
		call_hooks('workflow_display_headers',$headerrows);
		call_hooks('workflow_display_list_headers',$headerrows);
		$rows = $headerrows['rows'];

		$vars['headerextras']='';
		foreach($rows as $row) {
			if (is_array($row)) {
				foreach($row as $head) {
					$vars['headerextras'].="<div class='row'>".$head."</div>";
				}
			} else {
				$vars['headerextras'].="<div class='row'>".$row."</div>";
			}
		}

		$tpl = get_markup_template('workflow_list.tpl','addon/workflow');
        	$o = replace_macros($tpl,$vars);
		return $o;
	}

	public static function get_toolbar($items) {
		$hookinfo = [
			'items' => $items,
			'tools' => []
			];

		call_hooks('workflow_toolbar',$hookinfo);

		usort($hookinfo['tools'],function($a,$b) {
			$aprio = isset($a['priority']) ? intval($a['priority']) : 1000;
			$bprio = isset($b['priority']) ? intval($b['priority']) : 1000;

			if (intval(@$aprio) == intval(@$bprio)) {
				return 0;
			}

			$ret = (intval(@$aprio) > intval(@$bprio)) ? 1 : -1;
			return $ret;
		});

		$tools = $hookinfo['tools'];
		$toolhtml = '';
		foreach($tools as $tool) {
				$toolhtml .= $tool['tool'];
		}

		return "<div class='col-12' style='background-color:#000;font-color:#fff;'>".$toolhtml."</div>";
	}

	public static function basicfilter_display_header (&$rows) {

		$basicfilters = "<div class='panel'>";
		$basicfilters .= "<div role='tab' id='basicfilters'>";
		$basicfilters .= "<h4><a data-toggle='collapse' data-target='#basicfilters-collapse' href='#' class='collapsed' aria-expanded='false'>Search Parameters</a></h4>";
		$basicfilters .= "</div>";
		$basicfilters .= "<div id='basicfilters-collapse' class='collapse' role='tabpanel' aria-labelledby='basicfilters' data-parent='#basicfilters' style='z-index:100;position:absolute;background-color:#fff;padding:4px 20px 4px 20px;border:solid 4px black;'>";
		$basicfilters .= "<form method='get'>";
		$minprio = isset($_REQUEST['minpriority']) ? intval($_REQUEST['minpriority']) : 1;
		$assigned = (isset($_REQUEST['assigned']) && is_array($_REQUEST['assigned'])) ? $_REQUEST['assigned'] : [];
		$workflowlist = (isset($_REQUEST['workflows']) && is_array($_REQUEST['workflows'])) ? $_REQUEST['workflows'] : [];

		$basicfilters .= "Minimum Priority: <input type='input' name='minpriority' size='2' value='".$minprio."'>";
		
		$basicfilters .= "<input type='submit' value='Search'>";

		$wfusers = self::basicfilter_gatherassigned($items);

		$workflows = self::get_workflowlist();
		if (count($workflows) > 1) {
			$basicfilters .= "<br>Workflows:<br><ul>";
			foreach ($workflows as $hash => $c) {
				$basicfilters .= " <span style='white-space:nowrap;'>";
				$basicfilters .= "<input type='checkbox' name='workflows[]' value='".$hash."'";
		        	if (in_array($hash,$workflowlist)) { $basicfilters .= " checked"; }
				$basicfilters .= ">";
				$basicfilters .= $c['name']."</span>";
			}
			$basicfilters .= "</ul>";
		}

		$basicfilters .= "<br>Assigned:<br><ul>";
		foreach ($wfusers as $hash => $c) {
			$basicfilters .= " <span style='white-space:nowrap;'>";
			$basicfilters .= "<input type='checkbox' name='assigned[]' value='".$hash."'";
	        	if (in_array($hash,$assigned)) { $basicfilters .= " checked"; }
			$basicfilters .= ">";
			$basicfilters .= $c."</span>";
		}
		$basicfilters .= "</ul>";

		$basicfilters .= "</form>";
		$basicfilters .= "</div>";
		$basicfilters .= "</div>";

		$newrows = $rows['rows'];
		$newrows[500][] = $basicfilters;
		$rows = [
			'items' => $rows['items'],
			'rows' => $newrows
			];
	}

	static public function toolbar_header(&$hookinfo) {
		$newhookinfo = $hookinfo;
		$tools = $hookinfo['tools'];

		$tool = '';
		$tool .= "<div class='workflow-toolbar-item'><a href='#' onclick='workflowShowNewItemForm(\"\",\"\"); return false;' title='Add Issue'><i class='generic-icons-nav fa fa-fw fa-plus'></i>Add Issue</a></div>";

		$newhookinfo['tools'][] = [
			'tool' => $tool,
			'priority' => 50
		];

		$hookinfo = $newhookinfo;
		return;
	}

	static public function maybeunjson ($value) {

    		if (is_array($value)) {
        	return $value;
    		}

    		if ($value!=null) {
        		$decoded=json_decode($value,true);
    		} else {
        		return null;
    		}

    		if (json_last_error() == JSON_ERROR_NONE) {
        		return ($decoded);
    		} else {
        		return ($value);
    		}
	}

	public function maybejson ($value,$options=0) {

    		if ($value!=null) {
        		if (!is_array($value)) {
            			$decoded=json_decode($value,true);
        		}
    		} else {
        		return null;
    		}

    		if (is_array($value) || json_last_error() != JSON_ERROR_NONE) {
                	$encoded = json_encode($value,$options);
        		return ($encoded);
    		} else {
        		return ($value);
    		}
	}

	public static function getmodal_getiframe($arr,$data = null) {

		$channel = channelx_by_n(App::$profile_uid);

		if (!Apps::addon_app_installed(App::$profile_uid,'workflow')) {
			return ['html' => '<h2>Workflow addon not installed.</h2>'];
		}

		$workflows = self::get_workflowlist();
		$data=self::maybeunjson($arr['jsondata']['datastore']);
		//$data = self::datastore($data,self::maybeunjson($arr['jsondata']),['linkeditem','iframeurl','posturl','action','uuid','iframeaction','relatedlink']);
		$data = self::datastore($data,self::maybeunjson($arr['jsondata']),null);
		//$data = self::datastore($data,$arr['jsondata'],['linkeditem','iframeurl','posturl','action','uuid','iframeaction','relatedlink']);
		$data = self::datastore($data,$arr['jsondata'],null);
		$itemurl = $data['linkeditem'];

		if (!isset($data['iframeurl']) && count($workflows) == 1) {
			$data['iframeurl'] = $workflows[0]['wfaddr'];
		} elseif (count($workflows) ==0) {
			return ['html' => '<h2>No workflows available.</h2>'];
		}

		if (isset($data['iframeurl'])) {
			$jsondata=urlencode(self::maybejson($data));
			$urlvars = 'action=getmodal_getiframecontent&jsondata='.$jsondata.'&zid='.get_my_address();
			$contentvars = [
				'iframeurl' => $data['iframeurl'].'?'.$urlvars,
				'$title' => t('Workflow'),
			];

			$contentvars = ['content' => replace_macros(get_markup_template('workflowiframe.tpl','addon/workflow'), $contentvars)];
			$retval = ['html' => replace_macros(get_markup_template('workflowmodal_skel.tpl','addon/workflow'), $contentvars)];

			return $retval;
		}

		$itemurl = (isset($data['linkeditem'])) ? $data['linkeditem'] : null;

		$jsondata = self::maybejson($data);
		$contentvars = [
			'$label' => t('Add item to which workflow').':',
			'$title' => t('Workflow'),
			'$posturl' => z_root().'/workflow/'.$channel['channel_address'],
			'$security_token' => get_form_security_token("workflow"),
			'$datastore' => $jsondata,
			'$workflows' => $workflows,
			'$submit' => t("Submit")
		];

		$contentvars = ['content' => replace_macros(get_markup_template('workflow_workflowlist.tpl','addon/workflow'), $contentvars)];
		$retval = ['html' => replace_macros(get_markup_template('workflowmodal_skel.tpl','addon/workflow'), $contentvars)];
		return $retval;

	}

	private static function iframecontent_new($data) {

	    	$uid = App::$profile_uid;

		if (!Apps::addon_app_installed($uid,'workflow')) {
			echo '<h2>Workflow addon not installed.</h2>';
			killme();
		}

		$channel = channelx_by_n(App::$profile_uid);

		$itemurl = $data['linkeditem'];

		$basecontent = (isset($data['workflowBody'])) ? $data['workflowBody'] : '';

		$modal_extras='';

		if ($itemurl) {
			$basecontent .= "\n\n\nRelated info: [url=".$itemurl."]Original Post[/url]";
			$modal_extras .= '<input type="hidden" name="linkeditem" value="'.$itemurl.'">';
		}

		call_hooks('workflow_create_item_extras',$modal_extras);


		$contentvars = [
			'$wforiginurl' => $_SERVER["HTTP_REFERER"],
			'$head_css' => head_get_css(),
			'$head_js' => head_get_js(),
			'posturl' => z_root().'/workflow/'.$channel['channel_address'],
			'$security_token' => get_form_security_token("workflow"),
			'$source_xchan' => get_observer_hash(),
			'$title' => t('Create Workflow Item'),
			'$subject' => (isset($data['workflowSubject'])) ? $data['workflowSubject'] : '',
			'$content' => $basecontent,
			'$modal_extras' => $modal_extras,
			'$jsondata' => $jsondata,
			'$submit' => t("Submit")
		];

		return replace_macros(get_markup_template('workflowiframecontent_new.tpl','addon/workflow'), $contentvars);

	}

	public static function getmodal_getiframecontent($arr,$data = []) {
		//@todo: add permissions
		if ((!perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_user')) &&
		    (!perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_additems'))) {
			echo replace_macros(get_markup_template('workflowiframepermissiondenied.tpl','addon/workflow'), []);
			killme();
		}


		$data=self::maybeunjson($arr['datastore']);

		//$data = self::datastore($data,self::maybeunjson($arr['jsondata']),['workflowBody','workflowSubject','linkeditem','iframeurl','posturl','action','uuid','iframeaction','relatedlink']);
		$data = self::datastore($data,self::maybeunjson($arr['jsondata']),null);
		//$data = self::datastore($data,$arr['jsondata'],['workflowBody','workflowSubject','linkeditem','iframeurl','posturl','action','uuid','iframeaction','relatedlink']);
		$data = self::datastore($data,$arr['jsondata'],null);

		require_once(theme_include('theme_init.php'));
		$jsondata = self::maybejson($data);

		App::$js_sources=[];
		head_add_js('/view/js/jquery.js');
		head_add_js('/addon/workflow/view/js/workflow.iframe.js');

		$action = isset($data['action']) ? $data['action'] : 'newitem';
		$action = isset($data['iframeaction']) ? $data['iframeaction'] : $action;


		switch ($action) {
			case 'form_edittask':
				$html = self::form_edittask($data);
				break;
			case 'form_addlink':
				$html = self::form_addlink($data);
				break;

			default:
				Hook::insert('dm42workflow_modal_iframecontent','Workflow_Utils::item_basicmeta',1,30000);
				Hook::insert('dm42workflow_modal_iframecontent','Workflow_Utils::item_basiccontacts',1,30000);

				$hookinfo = [
					'data'=>$data,
					'action'=>$action,
					'success'=>false,
					'returndata'=>''
				];

				call_hooks('dm42workflow_modal_iframecontent',$hookinfo);

				if ($hookinfo['success'] && is_array($hookinfo['returndata'])) {
					$html=$hookinfo['returndata'];
				} else {
					$html = self::iframecontent_new($data);
				}
		}
		echo $html;
		killme();
	}


	public static function getmodal_createitem($arr,$data = []) {
		//@todo: add permissions
		$itemurl = '';

	    	$uid = App::$profile_uid;
		$channel = channelx_by_n(App::$profile_uid);

		$data=self::maybeunjson($arr['datastore']);

		//$data = self::datastore($data,self::maybeunjson($arr['jsondata']),['workflowBody','workflowSubject','linkeditem','iframeurl','posturl']);
		//$data = self::datastore($data,$arr['jsondata'],['workflowBody','workflowSubject','linkeditem','iframeurl','posturl']);
		$data = self::datastore($data,self::maybeunjson($arr['jsondata']),null);
		$data = self::datastore($data,$arr['jsondata'],null);

		$basecontent = (isset($data['workflowBody'])) ? $data['workflowBody'] : '';

		$modal_extras='';

		call_hooks('workflow_create_item_extras',$modal_extras);

		$jsondata = self::maybejson($data);

		$contentvars = [
			'posturl' => z_root().'/workflow/'.$channel['channel_address'],
			'$security_token' => get_form_security_token("workflow"),
			'$source_xchan' => get_observer_hash(),
			'$title' => t('Create Workflow Item'),
			'$subject' => (isset($data['workflowSubject'])) ? $data['workflowSubject'] : '',
			'$content' => $basecontent,
			'$modal_extras' => $modal_extras,
			'$jsondata' => $jsondata,
			'$submit' => t("Submit")
		];
		return ['html' => replace_macros(get_markup_template('workflowmodal.tpl','addon/workflow'), $contentvars)];
	}

	public static function fetch_workflow($x) {

	}

	public static function object_mapper(&$objs) {

	    	$uid = App::$profile_uid;

		if (!Apps::addon_app_installed($uid,'workflow')) { return false; }

		$newobjs = $objs;
		$newobjs[WORKFLOW_ACTIVITY_OBJ_TYPE] = "dm42:workflow";
		$objs=$newobjs;
	}

	private static function create_workflowitem($arr,$owner_portid = null) {

		if (($owner_portid) && !($owner_portid == \App::$channel['channel_portable_id'])) {
			return false;
		}

	    	$uid = App::$profile_uid;

		if (!Apps::addon_app_installed($uid,'workflow')) { return false; }

		$channelinfo = channelx_by_n($uid);
		$itemuuid = item_message_id();
		$data = self::maybeunjson($arr['datastore']);
		$data = self::datastore($data,self::maybeunjson($arr['jsondata']),['workflowBody','workflowSubject','linkeditem','iframeurl','posturl']);
		$data = self::datastore($data,$arr['jsondata'],['workflowBody','workflowSubject','linkeditem','iframeurl','posturl']);
		$mid = z_root().'/workflow/'.$channelinfo['channel_address'].'/display/'.$itemuuid;
		$arr = [
			'aid'=>$channelinfo['channel_account_id'],
			'uid'=>$channelinfo['channel_id'],
        		'owner_xchan'=>$channelinfo['channel_hash'],
        		'author_xchan'=>get_observer_hash(),
			'item_type'=>ITEM_TYPE_CUSTOM,
			'body'=>$data['workflowBody'],
			'item_nocomment'=>1,
			'comment_policy'=>'contacts',
			'comments_closed'=>'2001-01-01 00:00:01',
			'title'=>($data['workflowSubject']) ? $data['workflowSubject'] : 'Workflow Item',
			'uuid'=>$itemuuid,
			'mid'=>$mid,
			'llink'=>z_root().'/display/'.gen_link_id($mid),
			'plink'=>z_root().'/display/'.gen_link_id($mid),
			'iconfig'=>[
				['cat'=>'system','k'=>'custom-item-type','v'=>'workflow','sharing'=>1]
			]];

		$arr['public_policy'] = map_scope(PermissionLimits::Get($channelinfo['channel_id'],'view_stream'),true);

		$allow_cid = [];
		$allow_gid = [];

		$allow = q("select xchan from abconfig where chan = %d and cat = 'my_perms' and k = 'workflow_user' and v = '1'",
			intval($uid)
		);

		foreach($allow as $allowid) {
			$allow_cid[] = $allowid['xchan'];
		}

		$allow_cid = perms2str($allow_cid);

		$hookinfo=[
			'allow_cid'=>$allow_cid,
			'allow_gid'=>'',
			'deny_cid'=>'',
			'deny_gid'=>'',
		];

		call_hooks("workflow_set_wfitem_permissions",$hookinfo);

		if ($hookinfo['allow_cid'] || $hookinfo['allow_gid'] ||
			$hookinfo['deny_cid'] || $hookinfo['deny_gid'] ) {
				$arr['allow_cid']=$hookinfo['allow_cid'];
				$arr['allow_gid']=$hookinfo['allow_gid'];
				$arr['deny_cid']=$hookinfo['deny_cid'];
				$arr['deny_gid']=$hookinfo['deny_gid'];
		}
		//$r = q("select item.*, item.id as item_id from item where item.uid = %d and mid = '%s' limit 1",
			//intval($uid),
			//dbesc($item['report_mid']));

		// fetch if we don't have it, then test if it was fetched
//
		//if ($r) {
		$reportinfo = [];
		$arr['iconfig'][]=['cat'=>'workflow','k'=>'report_mid','v'=>$item['report_mid'],'sharing'=>1];
		//}

		$arr['iconfig'][]=['cat'=>'workflow','k'=>'creator_xchan','v'=>get_observer_hash(),'sharing'=>1];
		if ($data['linkeditem']) {
			$rlink = self::relate_link($uid,get_observer_hash(),null,$data['linkeditem'],'Precipitating Item',true);
			$arr['iconfig'][]=$rlink;
		}

		$arr['obj_type'] = WORKFLOW_ACTIVITY_OBJ_TYPE;
		$arr['mimetype'] = 'text/bbcode';
		$obj = Activity::encode_item($arr);
		//$arr['obj_type'] = WORKFLOW_ACTIVITY_OBJ_TYPE;
		unset($arr['obj']);

		$wfusers = self::get_workflowusers();

		$allow = [];
		foreach ($wfusers as $hash=>$wfu) {
			$allow[] = $hash;
		}
		$arr['allow_cid'] = '<'.implode('><',$allow).'>';

		$arr['obj']=json_encode(self::encode_workflow_object($arr));

		if ($owner_xchannel === null || $channel['channel_hash'] == $owner_xchannel) {
			$post = post_activity_item($arr,false,true);

			if (!$post['success']) {
				return false;
			}
			sync_an_item($uid,$post['item_id']);
			Master::Summon([ 'Notifier','activity',$post['item_id'] ]);
		}

		return $post;
	}

	private static function encode_workflow_object($item) {


		$json = [];
		$ret = [];
		foreach ($item['iconfig'] as $icfg) {
			if ($icfg['cat'] == 'workflow' && $icfg['sharing']) {
				$json[$icfg['k']] = $icfg['v'];
			}
		}
		if (count($json)) {
			$wfmeta = [
				'@id' => $item['plink'].'#workflowmeta',
				'@type' => '@json',
				'@value' => $json
			];
		}

		$author = q("select * from xchan where xchan_hash = '%s' limit 1",
			dbesc($item['author_xchan'])
		);

		if ($author)
			$item['author'] = $author[0];
		else
			return [];

		$item['mimetype'] = 'text/bbcode';
		$ret = Activity::encode_item($item);
		unset($ret['obj']);
		$ret['type'] = WORKFLOW_ACTIVITY_OBJ_TYPE;
		$ret['https://purl.org/dm42/as/workflow#workflowmeta'] = $wfmeta;

/*

		$ret = [
			'type' 		=> WORKFLOW_ACTIVITY_OBJ_TYPE,
			'id'		=> $item['mid'],
			'parent'	=> $item['parent_mid'],
			'link'		=> [ ['rel' => 'alternate','type' => 'text/html', 'href' => $item['plink'] ] ],
			'title'		=> $item['title'],
			'content'	=> $item['body'],
			'edited'	=> $item['edited'],
			'commentPolicy' => 'contacts until=2001-01-01 00:00:01',
			'author'	=> [
				'name' 		=> $item_author['xchan_name'],
				'address'	=> $item_author['xchan_addr'],
				'guid'		=> $item_author['xchan_guid'],
				'guid_sig'	=> $item_author['xchan_guid_sig'],
				'link'		=> [
					[
						'rel' => 'alternate',
						'type' => 'text/html',
						'href' => $item_author['xchan_url']
					],
					[
						'rel' => 'photo',
						'type' => $item_author['xchan_photo_mimetype'],
						'href' => $item_author['xchan_photo_m']
					]
				]
			],
			'https://purl.org/dm42/as/workflow#workflowmeta' => $wfmeta
		];
*/
		return $ret;
	}

	public static function decode_note(&$hookinfo) {

	    	$uid = App::$profile_uid;
		if (!Apps::addon_app_installed($uid,'workflow')) { return false; }

		$act = $hookinfo['act'];
		$s = $hookinfo['s'];

		if ($act->obj['type'] != WORKFLOW_ACTIVITY_OBJ_TYPE) {
			return;
		}

		if (isset($act->obj['https://purl.org/dm42/as/workflow#workflowmeta']) && is_array($act->obj['https://purl.org/dm42/as/workflow#workflowmeta'])) {

			foreach($act->obj['https://purl.org/dm42/as/workflow#workflowmeta']['@value'] as $k=>$v) {
				IConfig::Set($s,'workflow',$k,$v,1);
			}

		} else {
			return;
		}

		$hookinfo = [
			'act' => $act,
			's' => $s
		];

	}

	public static function posterror(&$extras) {
		$extras .= '<h4 class="text-danger">Error creating post.</h4>';
	}

	public static function relate_link($itemowneruid,$observer,$itemuuid,$relatedlink,$notes='',$getarray=false) {

		if ((!perm_is_allowed($itemowneruid, $observer, 'workflow_user')) &&
		    (!perm_is_allowed($itemowneruid, $observer, 'workflow_additems'))) {
			return false;
		}

		$linkkey=md5($relatedlink);
		$linkinfo = [
			'relatedlink'=>$relatedlink,
			'addedby'=>$observer,
			'title'=>$title,
			'notes'=>$notes
		];
		$linkinfo = json_encode($linkinfo);

		if ($getarray) {
			return ['cat'=>'workflow','k'=>'link:related:'.$linkkey,'v'=>$linkinfo,'sharing'=>1];
		}

		$items = q("select *,id as item_id from item where uid = %d and
				uuid = '%s' %s limit 1",
					intval($itemowneruid),
					dbesc($itemuuid),
					item_permissions_sql($itemowneruid,$observer)
				);
		if (!$items) {
			logger("LINK FAILED: Can't find item: ".$itemuuid." linkdata:".$linkinfo,LOGGER_DEBUG);
			return false;
		}

		$items = fetch_post_tags($items);

		if (IConfig::Set($items[0], 'workflow', 'link:related:'.$linkkey, $linkinfo, true) === false) {
			return;
		}

		unset($items[0]['obj']);
		$items[0]['obj']=json_encode(self::encode_workflow_object($items[0]));

		$wfusers = self::get_workflowusers();
		$allow = [];
		foreach ($wfusers as $hash=>$wfu) {
			$allow[] = $hash;
		}
		$items[0]['allow_cid'] = '<'.implode('><',$allow).'>';

		$itemstore=item_store_update($items[0]);

		sync_an_item($itemowneruid,$itemstore['item_id']);
		Master::Summon([ 'Notifier','activity',$itemstore['item_id'] ]);

		return true;
	}

	public static function refresh_item($observer,$uuid) {

		$channel = \App::$channel;

		if (!$channel) {
			return [];
		}

		$permsql = item_permissions_sql($channel['channel_id'],$observer);

		$items = q("select *,id as item_id from item where uid = %d AND uuid = '%s' %s LIMIT 1",
			intval($channel['channel_id']),
			dbesc($uuid),
			$permsql
			);

		if (!$items) {
			return [];
		}

		$items = fetch_post_tags($items);

		return $items;

	}

	public static function post_edittask($data) {
		if ((!perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_user'))  &&
		    (!perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_additems'))) {
			echo replace_macros(get_markup_template('workflowiframepermissiondenied.tpl','addon/workflow'), []);
			killme();
		}

	    	$uid = App::$profile_uid;
		$channel = channelx_by_n(App::$profile_uid);

		$uuid = $data['uuid'];
		$observer = get_observer_hash();

		$items = self::get_items(['uuid'=>$uuid],get_observer_hash(),false);

		if(!$items) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to create/update relation.']);
		}

		$items = fetch_post_tags($items);

		$history = IConfig::Get($items[0],'workflow','task_history','');

		$history=self::maybeunjson($history);
		$history[]=[
			'title'=>$items[0]['title'],
			'body'=>$items[0]['body'],
			'author_xchan'=>$items[0]['author_xchan'],
			'timestamp' => datetime_convert()
			];

		$historyinfo = json_encode($history);

		$items[0]['body']=$data['taskbody'];
		$items[0]['title']=$data['tasktitle'];

		if (IConfig::Set($items[0], 'workflow', 'task_history', $historyinfo, false) === false) {
			logger("Unable to save workflow history");
		}

		$items[0]['revision']++;
		$items[0]['edited']=datetime_convert();
		unset($items[0]['author_xchan']);
        	$items[0]['author_xchan']=get_observer_hash();
		unset($items[0]['obj']);
		$items[0]['obj']=json_encode(self::encode_workflow_object($items[0]));
		unset($items[0]['item_id']);
		unset($items[0]['cancel']);

		$wfusers = self::get_workflowusers();
		$allow = [];
		foreach ($wfusers as $hash=>$wfu) {
			$allow[] = $hash;
		}
		$items[0]['allow_cid'] = '<'.implode('><',$allow).'>';

		$itemstore=item_store_update($items[0],false,true);

		sync_an_item($uid,$itemstore['item_id']);

		Master::Summon([ 'Notifier','activity',$itemstore['item_id'] ]);

		return true;
	}

	public static function post_addlink($data) {
		if ((!perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_user'))  &&
		    (!perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_additems'))) {
			echo replace_macros(get_markup_template('workflowiframepermissiondenied.tpl','addon/workflow'), []);
			killme();
		}

	    	$uid = App::$profile_uid;
		$channel = channelx_by_n(App::$profile_uid);

		$uuid = $data['uuid'];
		$observer = get_observer_hash();
		$linktitle = $data['linktitle'];
		$linknotes = $data['linknotes'];
		$relatedlink = $data['relatedlink'];

		$linkkey=md5($relatedlink);

		$items = self::get_items(['uuid'=>$uuid],get_observer_hash(),false);

		if(!$items) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to create/update relation.']);
		}
		$items = fetch_post_tags($items);

		$curlinkinfo = IConfig::Get($items[0],'workflow','link:related:'.$linkkey,[]);

		if (isset($curlinkinfo['history'])) {
			$linkhistory=$curlinkinfo['history'];
			unset($curlinkinfo['history']);
			$linkhistory[]=$curlinkinfo;
		}

		$linkinfo = [
			'relatedlink'=>$relatedlink,
			'addedby'=>get_observer_hash(),
			'title'=>$linktitle,
			'notes'=>$linknotes,
			'timestamp' => datetime_convert(),
			'history' => $linkhistory
		];

		$linkinfo = json_encode($linkinfo);

		if (IConfig::Set($items[0], 'workflow', 'link:related:'.$linkkey, $linkinfo, true) === false) {
			return false;
		}

		$items[0]['revision']++;
		$items[0]['edited']=datetime_convert();

		unset($items[0]['obj']);
		$items[0]['obj']=json_encode(self::encode_workflow_object($items[0]));

		unset($items[0]['author']);
		unset($items[0]['owner']);
		unset($items[0]['item_id']);
		unset($items[0]['cancel']);

		$wfusers = self::get_workflowusers();
		$allow = [];
		foreach ($wfusers as $hash=>$wfu) {
			$allow[] = $hash;
		}
		$items[0]['allow_cid'] = '<'.implode('><',$allow).'>';

		$itemstore=item_store_update($items[0],false,true);

		sync_an_item($uid,$itemstore['item_id']);

		Master::Summon([ 'Notifier','activity',$itemstore['item_id'] ]);

		return true;
	}

	public static function contact_meta_display(&$hookinfo) {

		$newhookinfo = $hookinfo;

		$item = $hookinfo['item'];
		$itemmeta = $hookinfo['itemmeta'];
		$uuid = $hookinfo['uuid'];
		$iframeurl = $hookinfo['iframeurl'];
		$posturl = $hookinfo['posturl'];

		$assigned = IConfig::Get($item,'workflow','contacts:assigned','{}');

		$assigned=self::maybeunjson($assigned);
		$wfusers = self::get_workflowusers();

		$contacts = "";
		foreach ($assigned as $c) {
			if (!isset($wfusers[$c])) { return; }
			if ($contacts != "" ) { $contacts.= ", "; }
			$contacts .= "<span style='white-space:nowrap;'>".$wfusers[$c]."</span>";
		}

                $thismeta = '<b>Assigned:</b>';
                $miscdata = json_encode(['action'=>'item_basiccontacts','uuid'=>$uuid,'iframeurl'=>$iframeurl]);
                $thismeta .= "<a href='#' onclick='return false;' class='workflow-showmodal-iframe' data-posturl='".$posturl."' data-action='getmodal_getiframe' data-miscdata='".$miscdata."' data-toggle='tooltip' title='edit'><i class='fa fa-pencil'></i></a>";
 		$thismeta .= $contacts;

		$newhookinfo['itemmeta'][] = [
			'order' => 1,
			'cols' => "col-xs-12 col-sm-6 col-md-3",
			'html'=> $thismeta
		];

		$hookinfo=$newhookinfo;
	}

	public static function item_basiccontacts($hookinfo) {

		if (!Apps::addon_app_installed(App::$profile_uid,'workflow')) {
			json_return_and_die(['html'=>'<h2>Workflow addon not installed.</h2>']);
		}

		$uid = App::$profile_uid;
		$data = $hookinfo['data'];

		if ($data['iframeaction']!='item_basiccontacts' && $data['action']!='item_basiccontacts') { return; }

		$uuid= isset($data['uuid']) ? $data['uuid'] : null;
		if (!$uuid) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to update meta information.']);
		}

		$step = isset($data['step']) ? $data['step'] : 'step1';

		$items = self::get_items(['uuid'=>$uuid],get_observer_hash(),false);

		if (!$items) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to create/update relation.']);
		}

		$items = fetch_post_tags($items);

		$item = $items[0];

		$formname = "contact";
		$action = "item_basiccontacts";
		$content = '';

		$content .= "<input type='hidden' name='uuid' value='".$uuid."'>\n";
		$content .= "<input type='hidden' name='iframeaction' value='".$action."'>\n";
		$content .= "<input type='hidden' name='step' value='final'>\n";

		//ADD CONTENT

		//Select Current Status

		$wfusers = self::get_workflowusers();

		$curusers = self::maybeunjson(IConfig::Get($item,'workflow','contacts:assigned','{}'));
		$curusers = (is_array($curusers)) ? $curusers : [];

		foreach ($wfusers as $hash=>$name) {
			$templatevars = [
				'$field' => [
				'contact:'.$hash,
				$name,
				in_array($hash,$curusers),
				'',
				'Yes',
				'No',
				''
				]
			];

			$content .= replace_macros(get_markup_template('field_checkbox.tpl'),$templatevars);
		}

		//@TODO: Add "alarm" and/or "milestone"

		if ($data['step']=='final'){

			$success = false;
			//    [dm42wf_status_select] => Closed
			//SET ICONFIG - change success to true
			$newusers = [];
			foreach($data as $var => $val) {
				if (strpos($var,'contact:') === 0) {
					$hash=substr($var,8);
					if (isset($wfusers[$hash])) {
						$newusers[]=$hash;
					}
				}
			}

			IConfig::Set($item, 'workflow', 'contacts:assigned', self::maybejson($newusers), true);

			$history = IConfig::Get($item,'workflow','task_history','');

			$history=self::maybeunjson($history);
			$body = "";
			$bodyuserlist = "";
			foreach ($newusers as $hash) {
				$bodyuserlist .= "" ? "" : ', ';
				$bodyuserlist .= $wfusers[$hash].' ('.$hash.')';
			}
			$body .= $bodyuserlist;

			$history[]=[
				'title'=>'New Contact Assignments',
				'body'=>$body,
				'author_xchan'=>get_observer_hash(),
				'timestamp' => datetime_convert()
				];

			$historyinfo = json_encode($history);

			if (IConfig::Set($items[0], 'workflow', 'task_history', $historyinfo, false) === false) {
				logger("Unable to save workflow history: ".$historyinfo);
			}

			$items = [$item];

			$items[0]['revision']++;
			$items[0]['edited']=datetime_convert();

			IConfig::Set($items[0], 'workflow','itemupdate:'.time(),json_encode(['observer'=>$observer,'details'=>$updatedetails]));
			$items[0]['mimetype'] = 'text/bbcode';
			unset($items[0]['obj']);
			$obj = self::encode_workflow_object($items[0]);
			//$obj = Activity::encode_item($items[0]);
			//$obj['https://purl.org/dm42/as/workflow#workflowmeta'] = self::encode_workflow_meta_jsonld($items[0]);
			$items[0]['obj']=json_encode($items[0]);


			unset($items[0]['author']);
			unset($items[0]['owner']);
			unset($items[0]['item_id']);
			unset($items[0]['cancel']);

			$wfusers = self::get_workflowusers();
			$allow = [];
			foreach ($wfusers as $hash=>$wfu) {
				$allow[] = $hash;
			}
			$items[0]['allow_cid'] = '<'.implode('><',$allow).'>';

			$itemstore = item_store_update($items[0],false,true);
			$success = true;

			if ($success) {
				sync_an_item($uid,$itemstore['item_id']);
				Master::Summon([ 'Notifier','activity',$itemstore['item_id'] ]);
				json_return_and_die(['html'=>'<script>window.workflowiframeCloseModal();</script>']);
			} else {
				json_return_and_die(['html'=>'<h2>Error</h2>There was an error processing your request.']);
			}
		}

		json_return_and_die(['html'=> self::basic_form($action,'getmodal_getiframecontent',$content,false,$data)]);
	}



	public static function basicmeta_meta_display(&$hookinfo) {

		$newhookinfo = $hookinfo;

		$item = $hookinfo['item'];
		$itemmeta = $hookinfo['itemmeta'];
		$uuid = $hookinfo['uuid'];
		$iframeurl = $hookinfo['iframeurl'];
		$posturl = $hookinfo['posturl'];

		$itemstatus = IConfig::Get($item,'workflow','status','Open');
		$itempriority = IConfig::Get($item,'workflow','priority',1);

                $thismeta = 'Status: '.$itemstatus.' (Priority: '.$itempriority.')';
                $miscdata = json_encode(['action'=>'item_basicmeta','uuid'=>$uuid,'iframeurl'=>$iframeurl]);
                $thismeta .= "<a href='#' onclick='return false;' class='workflow-showmodal-iframe' data-posturl='".$posturl."' data-action='getmodal_getiframe' data-miscdata='".$miscdata."' data-toggle='tooltip' title='edit'><i class='fa fa-pencil'></i></a>";

		$newhookinfo['itemmeta'][] = [
			'order' => 1,
			'cols' => "col-xs-12 col-sm-6 col-md-3",
			'html'=> $thismeta
		];

		$hookinfo=$newhookinfo;

	}

	public static function item_basicmeta($hookinfo) {

		if (!Apps::addon_app_installed(App::$profile_uid,'workflow')) {
			json_return_and_die(['html'=>'<h2>Workflow addon not installed.</h2>']);
		}

		$uid = App::$profile_uid;
		$data = $hookinfo['data'];

		if ($data['iframeaction']!='item_basicmeta' && $data['action']!='item_basicmeta') { return; }

		$uuid= isset($data['uuid']) ? $data['uuid'] : null;
		if (!$uuid) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to update meta information.']);
		}

		$step = isset($data['step']) ? $data['step'] : 'step1';

		$items = self::get_items(['uuid'=>$uuid],get_observer_hash(),false);

		if (!$items) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to create/update relation.']);
		}

		$items = fetch_post_tags($items);

		$item = $items[0];

		$formname = "basicmeta";
		$action = "item_basicmeta";
		$content = '';

		$content .= "<input type='hidden' name='uuid' value='".$uuid."'>\n";
		$content .= "<input type='hidden' name='iframeaction' value='".$action."'>\n";
		$content .= "<input type='hidden' name='step' value='final'>\n";

		//ADD CONTENT

		//Select Current Status

		$statuses=PConfig::Get(App::$profile_uid,'workflow','statusconfig');
		$statuses=json_decode($statuses,true);

		$newstatuses = [];
		foreach ($statuses as $status) {
			$statuslist[$status['status']]= $status['status'].' (Priority: '.$status['priority'].')';
			$newstatuses[$status['status']] = $status;
		}
		$statuses = $newstatuses;

		$curstat = IConfig::Get($item,'workflow','status','Open');

		$templatevars = [
			'$field' => [
			'dm42wf_status_select',
			'New Status:',
			$curstat,
			'',
			$statuslist
			]
		];

		$content .= replace_macros(get_markup_template('field_select.tpl'),$templatevars);

		//@TODO: Add "alarm" and/or "milestone"

		if ($data['step']=='final'){

			$success = false;
			//    [dm42wf_status_select] => Closed
			//SET ICONFIG - change success to true
			$newstatus = isset ($data['dm42wf_status_select']) ? $data['dm42wf_status_select'] : 'Open';
			$newstatus = isset ($statuses[$newstatus]['status']) ? $statuses[$newstatus]['status'] : 'Open';
			$newpriority = isset ($statuses[$newstatus]) ? intval($statuses[$newstatus]['priority']) : 1;
			$oldstatus = IConfig::Get($item,'workflow','status');
			$oldpriority = IConfig::Get($item,'workflow','priority');

			IConfig::Set($item, 'workflow', 'status', $newstatus, true);
			IConfig::Set($item, 'workflow', 'priority', $newpriority, true);

			$history = IConfig::Get($item,'workflow','task_history','');

			$history=self::maybeunjson($history);
			$body = "Previous: ".$oldstatus." (Priority: ".$oldpriority.")\n";
			$body .= "New: ".$newstatus." (Priority: ".$newpriority.")";
			$history[]=[
				'title'=>'Update Status',
				'body'=>$body,
				'author_xchan'=>get_observer_hash(),
				'timestamp' => datetime_convert()
				];

			$historyinfo = json_encode($history);

			if (IConfig::Set($items[0], 'workflow', 'task_history', $historyinfo, false) === false) {
				logger("Unable to save workflow history: ".$historyinfo);
			}
			$success = self::update_item($item);

			if ($success) {
				json_return_and_die(['html'=>'<script>window.workflowiframeCloseModal();</script>']);
			} else {
				json_return_and_die(['html'=>'<h2>Error</h2>There was an error processing your request.']);
			}
		}

		json_return_and_die(['html'=> self::basic_form($action,'getmodal_getiframecontent',$content,false,$data)]);
	}


	public static function update_item($item) {

			$items = [$item];

			$items[0]['revision']++;
			$items[0]['edited']=datetime_convert();

			IConfig::Set($items[0], 'workflow','itemupdate:'.time(),json_encode(['observer'=>$observer,'details'=>$updatedetails]));
			$items[0]['mimetype'] = 'text/bbcode';
			unset($items[0]['obj']);
			$obj = self::encode_workflow_object($items[0]);
			//$obj = Activity::encode_item($items[0]);
			//$obj['https://purl.org/dm42/as/workflow#workflowmeta'] = self::encode_workflow_meta_jsonld($items[0]);
			$items[0]['obj']=$obj;


			unset($items[0]['author']);
			unset($items[0]['owner']);
			unset($items[0]['item_id']);
			unset($items[0]['cancel']);

			$wfusers = self::get_workflowusers();
			$allow = [];
			foreach ($wfusers as $hash=>$wfu) {
				$allow[] = $hash;
			}
			$items[0]['allow_cid'] = '<'.implode('><',$allow).'>';

			$itemstore = item_store_update($items[0],false,true);

			$success = $itemstore['success'];

			if ($success) {
				logger("Item update succeeded.");
				sync_an_item(App::$profile_uid,$itemstore['item_id']);
				Master::Summon([ 'Notifier','activity',$itemstore['item_id'] ]);
			} else {
				logger("Item Update Failed.");
			}

			return $success;
	}

	public static function form_addlink($data) {

		if (!Apps::addon_app_installed(App::$profile_uid,'workflow')) {
			json_return_and_die(['html'=>'<h2>Workflow addon not installed.</h2>']);
		}

		$uuid= isset($data['uuid']) ? $data['uuid'] : null;
		if (!$uuid) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to add a link.']);
		}

		$formname = "addlink";
		$action = "form_addlink";
		$content = '';
		$relatedlink = isset($data['relatedlink']) ? $data['relatedlink'] : '';
		$linkkey=md5($relatedlink);

		$items = self::get_items(['uuid'=>$uuid],get_observer_hash(),false);

		if (!$items) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to create/update relation.']);
		}

		$content .= "<input type='hidden' name='uuid' value='".$uuid."'>\n";
		$content .= "<input type='hidden' name='iframeaction' value='form_addlink'>\n";

		if ($relatedlink) {

			//$content .= "<input type='hidden' name='relatedlink' value='".$relatedlink."'>\n";
			$curlinkinfo = IConfig::Get($items[0],'workflow','link:related:'.$linkkey,[]);
			$curlinkinfo = self::maybeunjson($curlinkinfo);
			if (isset($curlinkinfo['history'])) {
				$linkhistory=$curlinkinfo['history'];
				unset($curlinkinfo['history']);
				$linkhistory[]=$curlinkinfo;
			}

			$linkinfo = [
				'title'=>$curlinkinfo['title'],
				'notes'=>$curlinkinfo['notes'],
				'history' => $linkhistory
			];
		} else {

			$content .= replace_macros(get_markup_template('field_input.tpl'),[ '$field' => [
				"relatedlink",
				t("Link").":",
				"",
				t("Web link.").'.',
				'',
				''
			]]);
			json_return_and_die(['html'=> self::basic_form('form_addlink','getmodal_getiframecontent',$content)]);
		}

		if ($data['step']=='final'){
			if (self::post_addlink($data)) {
				json_return_and_die(['html'=>'<script>window.workflowiframeCloseModal();</script>']);
			} else {
				json_return_and_die(['html'=>'<h2>Error</h2>There was an error processing your request.']);
			}
		} else {

			$content .= "<input type=hidden name='relatedlink' value='".$data['relatedlink']."'>\n";
			$content .= "<input type=hidden name='step' value='final'>\n";
			$content .= replace_macros(get_markup_template('field_input.tpl'),[ '$field' => [
				"linktitle",
				t("Title").":",
				$linkinfo['title'],
				t("Brief description or title").'.',
				'',
				''
			]]);
			$content .= replace_macros(get_markup_template('field_textarea.tpl'),[ '$field' => [
				"linknotes",
				t("Notes").":",
				$linkinfo['notes'],
				t("Notes and Info"),
				''
			]]);
		}
		json_return_and_die(['html'=> self::basic_form('form_addlink','getmodal_getiframecontent',$content)]);
	}

	public static function form_edittask($data) {

		if (!Apps::addon_app_installed(App::$profile_uid,'workflow')) {
			json_return_and_die(['html'=>'<h2>Workflow addon not installed.</h2>']);
		}

		$uuid= isset($data['uuid']) ? $data['uuid'] : null;
		if (!$uuid) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to add a link.']);
		}

		$formname = "addlink";
		$action = "form_edittask";

		$content = '';

		$items = self::get_items(['uuid'=>$uuid],get_observer_hash(),false);

		if (!$items) {
			json_return_and_die(['html'=>'<h2>Item not found.</h2>Unable to create/update relation.']);
		}

		$content .= "<input type='hidden' name='uuid' value='".$uuid."'>\n";
		$content .= "<input type='hidden' name='iframeaction' value='form_edittask'>\n";

		if ($data['step']=='final'){
			if (self::post_edittask($data)) {
				json_return_and_die(['html'=>'<script>window.workflowiframeCloseModal();</script>']);
			} else {
				json_return_and_die(['html'=>'<h2>Error</h2>There was an error processing your request.']);
			}
		} else {

			$content .= "<input type=hidden name='step' value='final'>\n";
			$content .= replace_macros(get_markup_template('field_input.tpl'),[ '$field' => [
				"tasktitle",
				t("Title").":",
				$items[0]['title'],
				t("Brief description or title").'.',
				'',
				''
			]]);
			$content .= replace_macros(get_markup_template('field_textarea.tpl'),[ '$field' => [
				"taskbody",
				t("Body").":",
				$items[0]['body'],
				t("Notes and Info"),
				''
			]]);
		}
		json_return_and_die(['html'=> self::basic_form('form_addlink','getmodal_getiframecontent',$content)]);
	}

	public static function basic_form($formname,$action,$content,$returntext=false,$data=[]) {
		//@todo: add permissions
	 	if ((!perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_user')) &&
	 	    (!perm_is_allowed(App::$profile_uid,get_observer_hash(),'workflow_additems'))) {
			if ($returntext) {
				return replace_macros(get_markup_template('workflowiframepermissiondenied.tpl','addon/workflow'), []);
			}
			if ($returntext) {
				return replace_macros(get_markup_template('workflowiframepermissiondenied.tpl','addon/workflow'), []);
			}  else {
				echo replace_macros(get_markup_template('workflowiframepermissiondenied.tpl','addon/workflow'), []);
				killme();
			}

		}

	    	$uid = App::$profile_uid;
		$channel = channelx_by_n(App::$profile_uid);

		require_once(theme_include('theme_init.php'));
		App::$js_sources=[];
		head_add_js('/view/js/jquery.js');
		head_add_js('/addon/workflow/view/js/workflow.iframe.js');

		$parentwindowid = (isset($data['parentwindowid'])) ? $data['parentwindowid'] : '';

		$contentvars = [
			'$wforiginurl' => $_SERVER["HTTP_REFERER"],
			'$parentwindowid' => $parentwindowid,
			'$head_css' => head_get_css(),
			'$head_js' => head_get_js(),
			'$formname' => $formname,
			'$action' => $action,
			'posturl' => z_root().'/workflow/'.$channel['channel_address'],
			'$security_token' => get_form_security_token("workflow"),
			'$title' => '',
			'$content' => $content,
			'$submit' => t("Submit"),
			'$onclick' => 'return false;'
		];
		if ($returntext) {
			return replace_macros(get_markup_template('workflowiframecontent_form.tpl','addon/workflow'), $contentvars);
		}
		echo replace_macros(get_markup_template('workflowiframecontent_form.tpl','addon/workflow'), $contentvars);
		killme();
	}

	public static function update($observer,$uuid,$data) {
		// Submit updates and queue item to send updates
		// Return updated version

		$channel = \App::$channel;

		if (!$channel) {
			return false;
		}

		$permsql = item_permissions_sql($channel['channel_id'],$observer);

		$orig = q("select *,id as item_id from item where uid = %d AND uuid = '%s' %s LIMIT 1",
			intval($channel['channel_id']),
			dbesc($uuid),
			$permsql
			);

		if (!$orig) {
			return ['error'=>401,'errmsg'=>'Unauthorized'];
		}

		$channelinfo = channelx_by_n($channel);

		if ($channelinfo['channel_hash'] != $orig['owner_xchan']) {
			return ['error'=>253,'errmsg'=>'Error in update: Item not owned by this channel.'];
		}

		$orig = fetch_post_tags($orig);
		$orig = $orig[0];

		$updates = $data['updates'];
		$updated = 0;
		$err = null;
		$updatedetails=[];
		foreach ($updates as $update) {
			$parameter = (isset($update['parameter'])) ? $update['parameter'] : 'unknown';
			switch ($parameter) {
				case 'title':
					if ($orig['title']!=$update['title']) {
						$updatedetails[]=[
							'action' => $parameter,
							'details' => ["prevtitle"=>$orig['title']]
						];
						$orig['title']=$update['title'];
						$updated = 1;
					}
					break;
				case 'body':
					if ($orig['body']!=$update['body']) {
						$updatedetails[]=[
							'action' => $parameter,
							'details' => ["prevbody"=>$orig['body']]
						];
						$orig['body']=$update['body'];
						$updated = 1;
					}
					break;
				case 'addrelation':
					$updatedetails[]=[
						'action' => $parameter,
						'details' => ['link'=>$update['relatedlink']]
					];
					$updated = 1;
					$linkkey=md5($update['relatedlink']);
					$linkinfo = [
						'relatedlink'=>$update['relatedlink'],
						'addedby'=>$observer,
						'title'=>$update['title'],
						'notes'=>$update['notes']
					];
					$linkinfo = json_encode($linkinfo);
					IConfig::Set($orig, 'workflow', 'link:related:'.$linkkey, $linkinfo, true);
					break;
				default:
					$safeparam = preg_replace('[^A-Za-z0-9\_]','',$parameter);
					$hookinfo = [
						'item' => $orig,
						'update' => $update,
						'updated' => $updated,
						'success' => true,
						'error' => '',
						'updatedetails'=>''
					];
					$update['success']=false;
					call_hooks("workflow_api_update_".$safeparam,$hookinfo);
					if (!$update['success']) {
						logger("Error updating: ".$safeparm.' ('.$hookinfo['error'].') ('.$update.')',LOGGER_DEBUG);
						$err = ['error'=>252,'errmsg'=>'Error in update.'];
					} else {
						$updatedetails[]=[
							'action' => $parameter,
							'details'=> $hookinfo['updatedetails']
						];
						$orig = $hookinfo['item'];
						$updated = 1;
					}
			}

		if ($updated) {
			IConfig::Set($orig, 'workflow','itemupdate:'.time(),json_encode(['observer'=>$observer,'details'=>$updatedetails]));
			$orig['mimetype'] = 'text/bbcode';
			unset($orig['obj']);
			$obj = self::encode_workflow_object($orig);
			//$obj = Activity::encode_item($orig);
			//$obj['https://purl.org/dm42/as/workflow#workflowmeta'] = self::encode_workflow_meta_jsonld($orig);
			$orig['obj']=json_encode($obj);
			}

			$wfusers = self::get_workflowusers();
			$allow = [];
			foreach ($wfusers as $hash=>$wfu) {
				$allow[] = $hash;
			}
			$items[0]['allow_cid'] = '<'.implode('><',$allow).'>';

			$itemstore = item_store_update($orig);
		}

		if ($err) {
			return $err;
		}

		sync_an_item($channel['channel_id'],$itemstore['item_id']);
		Master::Summon([ 'Notifier','activity',$itemstore['item_id'] ]);
	}

	private static function datastore($datastore,$requestdata,$parameters=[]) {

		$dskeys = is_array($datastore) ? array_keys($datastore) : [];
		$rdkeys = is_array($requestdata) ? array_keys($requestdata) : [];
		if (!is_array($parameters) || !count($parameters)) {
			$parameters = array_merge($dskeys,$rdkeys);
		}

		foreach ($parameters as $param) {
			$orig = isset($datastore[$param]) ? $datastore[$param] : null;
			$datastore[$param]=isset($requestdata[$param]) ? $requestdata[$param] : $orig;
		}

                return $datastore;
	}

	public static function json_receiver($requestdata) {


		if (!App::$profile_uid) {
			App::$error = 400;
			return ['error'=>102,'errmsg'=>'Invalid Channel'];
		}

		if (!Apps::addon_app_installed($uid,'workflow')) {
			return ['error'=>102,'errmsg'=>'Invalid Channel'];
		}

		$action =  preg_replace('/[^0-9a-zA-Z\_]/','',$requestdata['action']);

		if (!$action) {
			App::$error = 400;
			return ['error'=>100,'errmsg'=>'No valid action'];
		}

		header("Access-Control-Allow-Origin: *");

		switch ($action) {
			case 'update':
				$uuid = (isset($requestdata['uuid'])) ? $requestdata['uuid'] : null;
				if (!$uuid) {
					App::$error = 400;
					return ['error'=>150,'errmsg'=>'Update missing UUID'];
				}
				$data = self::maybeunjson($requestdata['jsondata']);
				self::update(App::$profile_uid,$requestdata['observer'],$data);
				break;
			case 'getmodal_getiframe':
				return self::getmodal_getiframe($requestdata);
				break;
			case 'getmodal_linkiframe':
				return self::getmodal_linkiframe($requestdata['url']);
				break;

			case 'getmodal_getiframecontent':
				return self::getmodal_getiframecontent($requestdata);
				break;

			case 'getmodal_createitem':
				return self::getmodal_createitem($requestdata);
				break;

			case 'newitem':
				if (!$item=self::create_workflowitem($requestdata)) {
					Hook::insert('workflow_create_item_extras','Workflow_Utils::posterror',1,30000);
					return self::getmodal_createitem($requestdata);
				}

				json_return_and_die(['success'=>1,'html'=>'Item Created.<br><a href="#" onclick="window.open(\''.$item['activity']['plink'].'\');">Go to item</a>']);
				break;

			default:
				App::$error = 400;
				return ['error'=>100,'errmsg'=>'No valid action'];
		}

	}

        public static function page_header(&$header) {
                $id = App::$profile_uid;
                if (!$id) { return; }

		if (!Apps::addon_app_installed($uid,'workflow')) { return; }

                //$header .= '<link href="addon/channelreputation/view/css/channelreputation.css" rel="stylesheet">';
		$mywindow = new_uuid();
		$parentwindowid = '';

		if (isset($_REQUEST['jsondata'])) {
			$data = self::maybeunjson($_REQUEST['jsondata']);
			$parentwindowid = (isset($data['parentwindowid'])) ? $data['parentwindowid'] : '';
		}

                head_add_js('/addon/workflow/view/js/workflow.js');
                head_add_css('/addon/workflow/view/css/workflow.css');
		$header .= replace_macros(get_markup_template('workflow_header.tpl','addon/workflow'), array(
			'$myzid' => get_my_address(),
		));
        }

        public static function page_end(&$footer) {
                $uid = App::$profile_uid;
                if (!$uid) { return; }
		if (!Apps::addon_app_installed($uid,'workflow')) { return; }
		$footer .= replace_macros(get_markup_template('workflow_footer.tpl','addon/workflow'), [
		]);
        }

	public static function item_custom_store(&$hookinfo) {

		if ($hookinfo['item']['item_type'] !== ITEM_TYPE_CUSTOM) {
			return;
		}

		$new = $hookinfo['item'];
		//$new['cancel']=true;
		foreach ($new['iconfig'] as $iconfig) {
			if ($iconfig['cat'] == 'system' && $iconfig['k'] == 'custom-item-type' && $iconfig['v'] == 'workflow') {
				unset($new['cancel']);
				break;
			}
		}
		$new['llink']=z_root().'/display/'.gen_link_id($mid);
		$new['plink']= isset($new['plink']) ? $new['plink'] : $new['mid'];
		$hookinfo = [
			'item' => $new,
			'allow_exec' => $hookinfo['allow_exec']
		];

	}

	public static function group_select($uid,$group = '',$label = '',$name = '') {

        	$grps = array();
        	$o = '';

        	$r = q("SELECT * FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname ASC",
                	intval($uid)
        	);
        	$grps[] = array('name' => '', 'hash' => '0', 'selected' => '');
        	if($r) {
                	foreach($r as $rr) {
                        	$grps[] = array('name' => $rr['gname'], 'id' => $rr['hash'], 'selected' => (($group == $rr['hash']) ? 'true' : ''));
                	}

        	}
		$name = (isset($name)) ? $name : 'group-select';
        	$o = replace_macros(get_markup_template('group_select.tpl','addon/workflow'), array(
                	'$label' => t(''),
                	'$groups' => $grps,
			'$name' => $name
        	));
        	return $o;
	}

}
