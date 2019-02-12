<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Nsabait extends Controller {

	function get() {

		if(! local_channel())
			return;

		if(! Apps::addon_app_installed(local_channel(), 'nsabait')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';

			$o = '<b>' . t('NSA Bait App') . ' (' . t('Not Installed') . '):</b><br>';
			return $o;
		}
		else
		    $o = '<b>' . t('NSA Bait App') . ' (' . t('Installed') . '):</b><br>';
            
        $o .= t('Make yourself a political target');
		return $o;


	}
}
