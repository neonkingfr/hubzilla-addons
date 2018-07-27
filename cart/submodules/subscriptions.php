<?php
/**
 * Name: subscriptions
 * Description: Submodule for the Hubzilla Cart system to track subscriptions.
 * Version: 0.2
 * MinCartVersion: 0.8
 * Author: Matthew Dent <dentm42@dm42.net>
 * MinVersion: 2.8
 */

class Cart_subscriptions {

    public function __construct() {
      load_config("cart-subscriptions");
    }

    static public function load (){
      Zotlabs\Extend\Hook::register('feature_settings', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::settings',1);
      Zotlabs\Extend\Hook::register('feature_settings_post', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::settings_post',1);
      //Zotlabs\Extend\Hook::register('cart_myshop_menufilter', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::myshop_menuitems',1,1000);
      //Zotlabs\Extend\Hook::register('cart_myshop_subscriptions', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::itemadmin',1,1000);
      //Zotlabs\Extend\Hook::register('cart_fulfill_subscriptions', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::fulfill_subscriptions',1,1000);
      //Zotlabs\Extend\Hook::register('cart_cancel_subscriptions', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::rollback_subscriptions',1,1000);
      //Zotlabs\Extend\Hook::register('cart_get_catalog', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::get_catalog',1,1000);
      //Zotlabs\Extend\Hook::register('cart_filter_catalog_display', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::filter_catalog_display',1,1000);
      Zotlabs\Extend\Hook::register('cart_post_subscriptions_itemedit', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::itemedit_post',1,1000);
      Zotlabs\Extend\Hook::register('cart_post_subscriptions_itemactivation', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::itemedit_activation_post',1,1000);
      Zotlabs\Extend\Hook::register('cart_post_subscriptions_itemdeactivation', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::itemedit_deactivation_post',1,1000);
      Zotlabs\Extend\Hook::register('cart_submodule_activation', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::module_activation',1,1000);
      Zotlabs\Extend\Hook::register('cart_submodule_deactivation', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::module_deactivation',1,1000);
      Zotlabs\Extend\Hook::register('cart_post_subedit', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::subedit_post',1,1000);
      Zotlabs\Extend\Hook::register('cart_dbcleanup', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::dbCleanup',1,1000);
      Zotlabs\Extend\Hook::register('cart_dbupgrade', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::dbUpgrade',1,1000);
      Zotlabs\Extend\Hook::register('itemedit_formextras', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::subscriptionadmin',1,1000);

    }

    static public function unload () {
      Zotlabs\Extend\Hook::unregister('feature_settings', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::settings');
      Zotlabs\Extend\Hook::unregister('feature_settings_post', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::settings_post');
      //Zotlabs\Extend\Hook::unregister('cart_myshop_menufilter', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::myshop_menuitems');
      //Zotlabs\Extend\Hook::unregister('cart_myshop_subscriptions', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::itemadmin');
      //Zotlabs\Extend\Hook::unregister('cart_fulfill_subscriptions', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::fulfill_subscriptions');
      //Zotlabs\Extend\Hook::unregister('cart_cancel_subscriptions', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::rollback_subscriptions');
      //Zotlabs\Extend\Hook::unregister('cart_get_catalog', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::get_catalog');
      //Zotlabs\Extend\Hook::unregister('cart_filter_catalog_display', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::filter_catalog_display');
      Zotlabs\Extend\Hook::unregister('cart_post_subscriptions_itemedit', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::itemedit_post');
      Zotlabs\Extend\Hook::unregister('cart_post_subscriptions_itemactivation', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::itemedit_activation_post');
      Zotlabs\Extend\Hook::unregister('cart_post_subscriptions_itemdeactivation', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::itemedit_deactivation_post');
      Zotlabs\Extend\Hook::unregister('cart_submodule_activation', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::module_activation');
      Zotlabs\Extend\Hook::unregister('cart_submodule_deactivation', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::module_deactivation');
      Zotlabs\Extend\Hook::unregister('cart_post_subedit', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::subedit_post');
      Zotlabs\Extend\Hook::unregister('cart_dbcleanup', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::dbCleanup');
      Zotlabs\Extend\Hook::unregister('cart_dbupgrade', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::dbUpgrade');
      Zotlabs\Extend\Hook::unregister('itemedit_formextras', 'addon/cart/submodules/subscriptions.php', 'Cart_subscriptions::subscriptionadmin');
    }

    static public function module_activation (&$hookdata) {

    }

    static public function module_deactivation (&$hookdata) {

    }

    static public function dbCleanup (&$success) {
    	$dbverconfig = cart_getsysconfig("subscription-dbver");

    	$dbver = $dbverconfig ? $dbverconfig : 0;

    	$dbsql[DBTYPE_MYSQL] = Array (
        );
      $dbsql[DBTYPE_POSTGRES] = Array (
        );
      $dbsql=$dbsql[ACTIVE_DBTYPE];

      $sql = $dbsql[$dbver];
    	foreach ($sql as $query) {
    		$r = q($query);
    		if (!$r) {
    			logger ('[cart] Error running dbCleanup. sql query: '.$query,LOGGER_NORMAL);
          $success=UPDATE_FAILED;
    		}
    	}
    	cart_delsysconfig("subscription-dbver");

      return;
    }

    static public function dbUpgrade (&$success) {
    	$dbverconfig = cart_getsysconfig("subscription-dbver");
    	logger ('[cart-subscription] Current dbver:'.$dbverconfig,LOGGER_NORMAL);

    	$dbver = $dbverconfig ? $dbverconfig : 0;

    	$dbsql[DBTYPE_MYSQL] = Array (
        1 => Array (
          "CREATE TABLE cart_subscriptions (
    				id int(10) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    				master_order_hash varchar(191) NOT NULL,
            master_itemid int(10) UNSIGNED NOT NULL,
            sub_order_hash varchar(191) NOT NULL,
            sub_itemid int(10) UNSIGNED NOT NULL,
    				sub_expires datetime,
            sub_nexttrigger datetime,
    				sub_meta mediumtext
    				) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;
            "
          )
    	);

    	$dbsql[DBTYPE_POSTGRES] = Array (
        1 => Array (
          "CREATE TABLE cart_subscriptions (
            id serial NOT NULL,
            master_order_hash varchar(255),
            master_itemid int,
            sub_order_hash varchar(255),
            sub_itemid int,
    				sub_expires timestamp,
            sub_nexttrigger timestamp,
    				sub_meta mediumtext,
            PRIMARY KEY (id)
          );"
          )
    	);

    	foreach ($dbsql[ACTIVE_DBTYPE] as $ver => $sql) {
    		if ($ver <= $dbver) {
    			continue;
    		}
    		foreach ($sql as $query) {
    	    logger ('[cart-subscription] dbSetup:'.$query,LOGGER_DATA);
    			$r = q($query);
    			if (!$r) {
    				logger ('[cart] Error running dbUpgrade. sql query: '.$query);
    				$success = UPDATE_FAILED;
    			}
    		}
    		cart_setsysconfig("subscription-dbver",$ver);
    	}
    }

    static public function settings (&$s) {
      $id = local_channel();
      if (! $id)
        return;

      $enablecart = cart_getcartconfig ('enable');
      if (!isset($enablecart) || $enablecart != 1) {
         return;
      }
      $enable_subscriptions = cart_getcartconfig ('subscriptions-enable');
      $sc = replace_macros(get_markup_template('field_checkbox.tpl'), array(
                 '$field'	=> array('enable_cart_subscriptions', t('Enable Subscription Management Module'),
                   (isset($enable_subscriptions) ? intval($enable_subscriptions) : 0),
                   '',array(t('No'),t('Yes')))));

      $s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
                 '$addon' 	=> array('cart-subscriptions',
                   t('Cart - Subscription Management'), '',
                   t('Submit')),
                 '$content'	=> $sc));
    }

    static public function settings_post () {
      if(!local_channel())
        return;

      if (!isset($_POST['enable_cart']) || $_POST['enable_cart'] != 1 || !isset($_POST['enable_cart_subscriptions'])) {
        return;
      }

      $prev_enable = cart_getcartconfig('subscriptions-enable');
      $enable_cart_subscriptions = isset($_POST['enable_cart_subscriptions']) ? intval($_POST['enable_cart_subscriptions']) : 0;
      cart_setcartconfig('subscriptions-enable', $enable_cart_subscriptions );

      Cart_subscriptions::unload();
      Cart_subscriptions::load();
    }

    static public function get_subinfo($sku) {
      $configparam = "subs-".$sku;
      $subinfo = cart_getcartconfig($configparam);
      return $subinfo;
    }

    static public function set_subinfo($sku,$subinfo) {
      $subskus = cart_maybeunjson(cart_getcartconfig("subskus"));
      if (!is_array($subskus)) { $subskus = Array(); }
      if (!isset($subskus[$sku])) {
        $subskus[$sku]=$sku;
        cart_setcartconfig("subskus",cart_maybejson($subskus));
      }
      $json = cart_maybejson($subinfo);
      $configparam = "subs-".$sku;
      cart_setcartconfig($configparam,$json);
    }

    static public function del_subinfo($sku) {
      $subskus = cart_maybeunjson(cart_getcartconfig("subskus"));
      if (!is_array($subskus)) { $subskus = Array(); }
      if (isset($subskus[$sku])) {
        unset($subskus[$sku]);
        cart_setcartconfig("subskus",cart_maybejson($subskus));
      }
      cart_delcartconfig($configparam);
    }

    static public function before_additem(&$hookdata)  {
      $item = $hookdata["item"];
      $ordermeta = $hookdata["order_meta"];
      $ordersub = isset($ordermeta["subinfo"]) ? $ordermeta["subinfo"] : Array ();

      $subinfo = Cart_subscriptions::get_subinfo($item["sku"]);

      if (!$subinfo) { return; }

      if (!isset($ordersub["term"])) {
        $hookdata["order_meta"]["subinfo"]["term"]=subinfo["term"];
        $hookdata["order_meta"]["subinfo"]["term"]=subinfo["termcount"];
        cart_updateorder_meta($hookdata["order_hash"],$hookdata["order_meta"]);
        return;
      }

      if (($ordersub["term"] != $subinfo["term"]) ||
          ($ordersub["termcount"] != $subinfo["termcount"])) {
            $hookdata["error"]=t("Cannot include subscription items with different terms in the same order.");
            unset($hookdata["item"]);
      }
    }

    static public function item_fulfill(&$orderitem) {
      // LOCK SKU from future edits.
      $subinfo = Cart_subscriptions::get_subinfo($orderitem["sku"]);
      if (!$subinfo) { return; }

      if (isset($orderitem["item_meta"]["subscription"])) {
        $master_order = $orderitem["item_meta"]["subscription"]["master_order"];
        $master_itemid = $orderitem["item_meta"]["subscription"]["master_itemid"];
      }

      $r = q("select * from cart_subscriptions where master_order_hash = '%s'
                    and master_itemid = %d order by id desc limit 1;",
                    $master_order,$master_itemid
                  );

      if (!r) {  // FIRST order in subscription.
        switch (ACTIVE_DBTYPE) {
          case DBTYPE_MYSQL:
              $interval=Cart_subscriptions::get_mysqlinterval($subinfo);
              $r = q("insert into cart_subscriptions
                            (master_order_hash,master_itemid,sub_order_hash,
                                sub_order_id,sub_expires) values
                            ('%s','%s','%s','%s',NOW() + interval '%s'));
                                ",$orderitem["order_hash"],$orderitem["id"],
                                  $orderitem["order_hash"],$orderitem["id"],
                                  $interval);
              break;
          case DBTYPE_POSTGRES:
                  $interval=Cart_subscriptions::get_pginterval($subinfo);
                  $r = q("insert into cart_subscriptions
                        (master_order_hash,master_itemid,sub_order_hash,
                            sub_order_id,sub_expires) values
                        ('%s','%s','%s','%s',NOW() + interval '%s'));
                            ",$orderitem["order_hash"],$orderitem["id"],
                              $orderitem["order_hash"],$orderitem["id"],
                              $interval);
              break;
          default:
        }
      } else { // Subscription is being extended
        $current_subscription = $r[0];
        switch (ACTIVE_DBTYPE) {
          case DBTYPE_MYSQL:
              $prev_expires=$current_subscription["sub_expires"];
              $interval=Cart_subscriptions::get_mysqlinterval($subinfo);
              $r = q("update cart_subscriptions set sub_expires=null, sub_nexttrigger=null
                            where id=%d;",$current_subscription["id"]);
              if (!$r) {
                  logger("[cart-subscription] WARNING: Could not remove subscription timestamps on subscription id ("
                               .$current_subscription["id"].")",LOGGER_NORMAL);
              }
              $r = q("insert into cart_subscriptions
                        (master_order_hash,master_itemid,sub_order_hash,
                            sub_order_id,sub_expires) values
                        ('%s','%s','%s','%s','%s' + interval '%s');
                            ",$master_order,$master_itemid,
                              $orderitem["order_hash"],$orderitem["id"],
                              $current_subscription['sub_expires'],$interval);
              break;
          case DBTYPE_POSTGRES:
              $prev_expires=$current_subscription["sub_expires"];
              $interval=Cart_subscriptions::get_pginterval($subinfo);
              $r = q("update cart_subscriptions set sub_expires=null, sub_nexttrigger=null
                            where id=%d;",$current_subscription["id"]);
              if (!$r) {
                logger("[cart-subscription] WARNING: Could not remove subscription timestamps on subscription id ("
                                 .$current_subscription["id"].")",LOGGER_NORMAL);
              }
              $r = q("insert into cart_subscriptions
                        (master_order_hash,master_itemid,sub_order_hash,
                            sub_order_id,sub_expires) values
                        ('%s','%s','%s','%s','%s' + interval '%s'));
                            ",$master_order,$master_itemid,
                              $orderitem["order_hash"],$orderitem["id"],
                              $current_subscription['sub_expires'],$interval);
              break;
          default:
        }
      }

      if (!$r) {
        $orderitem["error"] = cart_add_error($orderitem["error"],"Subscription could not be added/extended.");
        //$orderitem["error"]=$orderitem["error"]." Subscription could not be added/extended.";
      }
    }

    static public function get_catalog(&$catalog) {
      // 		"sku-1"=>Array("item_sku"=>"sku-1","item_desc"=>"Description Item 1","item_price"=>5.55),
      // @TODO: Possibly cull catalog of items with subscription terms not
      //               matching the current order's subscription term.
      return ; // For now, unimplemented. We do have a check when items are added.
      /*
      foreach ($catalog as $sku=>$data) {
        $subinfo = Cart_subscriptions::get_subinfo($orderitem["sku"]);
        if (!$subinfo) { continue; }
      }
      */
    }

    static public function subscriptionadmin(&$pagecontent) {

      $is_seller = ((local_channel()) && (local_channel() == \App::$profile['profile_uid']) ? true : false);
      if (!$is_seller) {
        return;
      }

      /*have SKU - display edit*/
      $sku = isset($_REQUEST["SKU"]) ? preg_replace("[^a-zA-Z0-9\-]",'',$_REQUEST["SKU"]) : null;
      if ($sku) {
        $pagecontent.=Cart_subscriptions::subscriptionadmin_form($sku);
        return;
      }
    }

    static public function subscriptionadmin_form($sku) {
      $subinfo = Cart_subscriptions::get_subinfo($sku);
      $formelements["submit"]=t("Submit");
      $formelements["uri"]=strtok($_SERVER["REQUEST_URI"],'?').'?SKU='.$sku;
      //$formelements[""]='';
      $formelements["itemdetails"] .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
                 '$field'	=> array('subscription_enable', t('Subscription Item'),
                   (($subinfo!=null) ? 1 : 0),
                   '',array(t('No'),t('Yes')))));
      if ($subinfo) {
        $formelements["itemdetails"].= replace_macros(get_markup_template('field_input.tpl'), array(
                  '$field'	=> array('subscription_interval', t('Quantity'),
                  (isset($subinfo["interval"]) ? intval($subinfo["interval"]) : 1))));


        $formelements["itemdetails"] .= replace_macros(get_markup_template('field_select.tpl'), array(
          "field" => Array ("subscription_term", t('Term'),
          isset($subinfo["term"]) ? $subinfo ["term"] : "month", "",
          Array("minute"=>"Minutes","hour"=>"Hours","day"=>"Days","week"=>"Weeks",
                            "month"=>"Months","year"=>"Years")
          )
        ));
      }
      $macrosubstitutes=Array("security_token"=>get_form_security_token(),"sku"=>$sku,"formelements"=>$formelements);
      return $pagecontent.=replace_macros(get_markup_template('subscription.itemedit.tpl','addon/cart/submodules/'), $macrosubstitutes);
      
    }

    static public function subedit_post() {

      if (!check_form_security_token()) {
    		notice (check_form_security_std_err_msg());
    		return;
    	}
      $is_seller = ((local_channel()) && (local_channel() == \App::$profile['profile_uid']) ? true : false);
      if (!$is_seller) {
        notice ("Access Denied.".EOL);
        return;
      }

      $sku = isset($_POST["SKU"]) ? preg_replace("[^a-zA-Z0-9\-]",'',$_POST["SKU"]) : null;
      if (trim($sku)=='') {
        return;
      }
      $subinfo = Cart_subscriptions::get_subinfo($sku);
      if (!$subinfo && $_POST["subscription_enable"]==1) {
        $subinfo=Array("SKU"=>$sku);
        Cart_subscriptions::set_subinfo($sku,$subinfo);
        return;
      }
  
      if(!isset($_POST["subscription_enable"]) || $_POST["subscription_enable"]==0) {
        notice("TEST".EOL);
        Cart_subscriptions::del_subinfo($sku);
        return;
      }

      if ($subinfo) {
        $term = isset($_POST["subscription_term"]) ?
                           preg_replace("[^a-zA-Z0-9\-]",'',$_POST["subscription_term"])
                           : null;
        if (!in_array($term,Array("minute","hour","day","week","month","year"))) {
          $term=null;
        }
        $interval = intval($_POST["subscription_interval"]);

        if ($term && $interval) {
          $subinfo["term"]=$term;
          $subinfo["inteval"]=$interval;
          Cart_subscriptions::set_subinfo($sku,$subinfo);
        }

        // @TODO: ACTIONS: Before expire, On expire, After expire
      }
    }

    static public function cron () {
      $r = q("select * from cart_subscriptions where sub_expires is not null
                        and sub_nexttrigger < NOW();"
                  );
      if (!$r) { return; }

      foreach ($r as $subscription) {

        $v = q("select * from cart_subscriptions where sub_expires > NOW() AND
                    master_itemid = %d",$subscription["master_itemid"]);

        if (!$v) { //Don't cancel items with active subscriptions
          $s = q("select * from cart_orderitems where id=%d",$subscription["master_itemid"]);
          if (!$s) { continue; }
          $cancelsub = $s[0];
          cart_do_cancelitem($s[0]);
          cart_item_note("Subscription Cancelled",$s["id"],$s["order_hash"]);
        }

        $t = q("update cart_subscriptions set sub_expires=null where master_itemid = %d and
                              sub_expires < NOW()",
                       $subscription["master_itemid"]);
      }
    }
}
