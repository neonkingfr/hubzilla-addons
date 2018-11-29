<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Photocache extends Controller {
	
	function post() {
			
		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(),'photocache'))
			return;

		check_form_security_token_redirectOnErr('photocache', 'photocache');

		set_pconfig(local_channel(), 'photocache', 'cache_enable', intval($_POST['cache_enable']));

		info(t('Photo Cache settings saved.') . EOL);
	}
	
	
	function get() {

		if(! local_channel())
			return;
		
		$desc = t('Photo Cache addon saves a copy of images from external sites locally to increase your anonymity in the web.');
		
		if(! Apps::addon_app_installed(local_channel(), 'photocache')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>Photo Cache App (Not Installed):</b><br>';
			$o .= $desc;
			
			return $o;
		}
		
		$sc = '<div class="section-content-info-wrapper">' . $desc . '</div><br>';

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field' => array(
				'cache_enable', 
				t('Enable Photo Cache for this channel'), 
				get_pconfig(local_channel(),'photocache','cache_enable'), 
				t('Current cache policy is defined by hub adminstrator'), 
				array(t('No'),t('Yes'))
			),
		));

		$tpl = get_markup_template("settings_addon.tpl");

		$o .= replace_macros($tpl, array(
			'$action_url' => 'photocache',
			'$form_security_token' => get_form_security_token('photocache'),
			'$title' => t('Photo Cache'),
			'$content'  => $sc,
			'$baseurl'   => z_root(),
			'$submit'    => t('Submit'),
		));

		return $o;
	}
}
