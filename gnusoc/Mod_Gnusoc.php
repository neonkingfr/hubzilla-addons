<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Gnusoc extends Controller {

	function get() {

		if(! local_channel())
			return;

		$desc = t('The GNU-Social protocol does not support location independence. Connections you make within that network may be unreachable from alternate channel locations.');

		if(! Apps::addon_app_installed(local_channel(), 'gnusoc')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>GNU-Social Protocol App (Not Installed):</b><br>';
			$o .= $desc;
			return $o;
		}

		$content = '<div class="section-content-info-wrapper">' . $desc . '</div>';

		$tpl = get_markup_template("settings_addon.tpl");

		$o .= replace_macros($tpl, array(
			'$action_url' => '',
			'$form_security_token' => '',
			'$title' => t('GNU-Social Protocol'),
			'$content'  => $content,
			'$baseurl'   => z_root(),
			'$submit'    => '',
		));

		return $o;

	}

}
