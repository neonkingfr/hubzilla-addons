<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Hsse extends Controller {

	function get() {
		if(! local_channel())
			return;

		$desc = t('WYSIWYG status editor');

		if(! Apps::addon_app_installed(local_channel(), 'hsse')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>' . t('WYSIWYG Status App') . ' (' . t('Not Installed') . '):</b><br>';
			$o .= $desc;
			return $o;
		}

		$content = '<b>' . t('WYSIWYG Status App') . ' (' . t('Installed') . '):</b><br>';
		$content .= $desc;

		$tpl = get_markup_template("settings_addon.tpl");

		$o = replace_macros($tpl, array(
			'$action_url' => '',
			'$form_security_token' => '',
			'$title' => t('WYSIWYG Status'),
			'$content'  => $content,
			'$baseurl'   => z_root(),
			'$submit'    => '',
		));

		return $o;
	}
	
}
