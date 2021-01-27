<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Hideaside extends Controller {

	function post() {

	}

	function get() {
		if(! local_channel())
			return;

		//Do not display any associated widgets at this point
		App::$pdl = '';

		if(! Apps::addon_app_installed(local_channel(), 'hideaside')) {
			$o = '<b>' . t('Hide Aside App') . ' (' . t('Not Installed') . '):</b><br>';
			$o .= t('Fade out aside areas after a while when using endless scroll');
			return $o;
		}
		$o = '<b>' . t('Hide Aside App') . ' (' . t('Installed') . '):</b><br>';
		$o .= t('Fade out aside areas after a while when using endless scroll');
		return $o;
	}
	
}
