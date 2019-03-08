<?php
/* Name: Flattr Widget
 * Description: Add a Flattr Button to the left/right aside are to allow the flattring of one thing (e.g. the for a blog)
 * Version: 0.1
 * Screenshot: img/red-flattr-widget.png
 * Depends: Core
 * Recommends: None
 * Category: Widget, flattr, Payment
 * Author: Tobias Diekershoff <https://diekershoff.de/channel/bavatar>
 * Maintainer: Tobias Diekershoff <https://diekershoff.de/channel/bavatar>
 */

use Zotlabs\Lib\Apps;
use Zotlabs\Extend\Hook;
use Zotlabs\Extend\Route;

function flattrwidget_load() {
	register_hook('construct_page', 'addon/flattrwidget/flattrwidget.php', 'flattrwidget_construct_page');
	Route::register('addon/flattrwidget/Mod_Flattrwidget.php','flattrwidget');
}

function flattrwidget_unload() {
	unregister_hook('construct_page', 'addon/flattrwidget/flattrwidget.php', 'flattrwidget_construct_page');
	Route::unregister('addon/flattrwidget/Mod_Flattrwidget.php','flattrwidget');
}

function flattrwidget_construct_page(&$a,&$b) {

    if($b['module'] !== 'channel')
	return;

    $id = App::$profile['profile_uid'];

    if(! Apps::addon_app_installed($id, 'flattrwidget'))
         return;

    App::$page['htmlhead'] .= '<link rel="stylesheet" href="'.z_root().'/addon/flattrwidget/style.css'.'" media="all" />';
    //  get alignment and static/dynamic from the settings
    //  align is either "aside" or "right_aside"
    //  sd is either static or dynamic
    $lr = get_pconfig( $id, 'flattrwidget', 'align');
    $sd = get_pconfig( $id, 'flattrwidget', 'sd');
    //  title of the thing for the things page on flattr
    $ftitle = get_pconfig( $id, 'flattrwidget', 'title');
    //  URL of the thing
    $thing = get_pconfig( $id, 'flattrwidget', 'thing');
    //  flattr user the thing belongs to
    $user = get_pconfig( $id, 'flattrwidget', 'user');
    //  title for the flattr button itself
    $title = t('Flattr this!');
    //  construct the link for the button
    $link = 'https://flattr.com/submit/auto?user_id='.$user.'&url=' . rawurlencode($thing).'&title='.rawurlencode($ftitle);
    if ($sd == 'static') {
	//  static button graphic from the img folder
	$img = z_root() .'/addon/flattrwidget/img/flattr-badge-large.png';
	$code = '<a href="'.$link.'" target="_blank"><img src="'.$img.'" alt="'.$title.'" title="'.$title.'" border="0"></a>';
    } else {
	$code = '<script id=\'fbdu5zs\'>(function(i){var f,s=document.getElementById(i);f=document.createElement(\'iframe\');f.src=\'//api.flattr.com/button/view/?uid='.$user.'&url='.rawurlencode($thing).'&title='.rawurlencode($ftitle).'\';f.title=\''.$title.'\';f.height=72;f.width=65;f.style.borderWidth=0;s.parentNode.insertBefore(f,s);})(\'fbdu5zs\');</script>';
	//  dynamic button from flattr API
    }
    //  put the widget content together
    $flattrwidget = '<div id="flattr-widget">'.$code.'</div>';
    //  place the widget into the selected aside area
    if ($lr=='right_aside') {
	$b['layout']['region_right_aside'] = $flattrwidget . $b['layout']['region_right_aside'];
    } else {
	$b['layout']['region_aside'] = $flattrwidget . $b['layout']['region_aside'];
    }
}
