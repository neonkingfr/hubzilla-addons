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

		$switch = intval($_POST['cache_enable']);
		
		if(! $switch) {
			$r = q("SELECT xchan, content, COUNT(*) AS qty FROM photo WHERE xchan IN 
				(SELECT DISTINCT xchan FROM photo WHERE photo_usage = %d AND uid = %d) 
				GROUP BY xchan, content",
				intval(PHOTO_CACHE),
				intval(local_channel())
			);
			if($r) {
				foreach($r as $rr) {
					$file = dbunescbin($rr['content']);
					if($rr['qty'] == 1 && $file) {
						if(is_file($file)) {
							@unlink($file);
							logger('deleted cached photo file ' . $file, LOGGER_DEBUG);
						}
					}
				}
			}
	
			$r = q("DELETE FROM photo WHERE photo_usage = %d AND uid = %d",
				intval(PHOTO_CACHE),
				intval(local_channel())
			);
		}
		
		$minres = intval($_POST['cache_minres']);
		if($minres > 1024)
			$minres = 1024;

		set_pconfig(local_channel(), 'photocache', 'cache_enable', $switch);
		set_pconfig(local_channel(), 'photocache', 'cache_minres', $minres);

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
		
		$switch = get_pconfig(local_channel(),'photocache','cache_enable', 0);

		$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
			'$field' => array(
				'cache_enable', 
				t('Enable Photo Cache for this channel'), 
				$switch, 
				t('Current cache policy is defined by hub adminstrator.'), 
				array(t('No'),t('Yes'))
			),
		));
		
		if($switch != 0) {
			$cache_minres = get_pconfig(local_channel(),'photocache','cache_minres', 0);
			if($cache_minres == 0)
				$cache_minres = get_config('system','photo_cache_minres', 1024);
		
			$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
				'$field' => array(
					'cache_minres', 
					t('Minimum image resolution for caching'), 
					$cache_minres, 
					t('In pixels. 0 will be replaced with system default, from 1 up to 1024 (large images will be scaled to this value).')
				),
			));	
		}

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
