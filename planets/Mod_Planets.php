<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Planets extends Controller {

	function get() {

		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'planets')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>' . t('Random Planet App') . ' (' . t('Not Installed') . '):</b><br>';
		}
		else
		    $o = '<b>' . t('Random Planet App') . ' (' . t('Installed') . '):</b><br>';
		    
		$o .= t('Set a random planet from the Star Wars Empire as your location when posting');
		return $o;

	}

}
