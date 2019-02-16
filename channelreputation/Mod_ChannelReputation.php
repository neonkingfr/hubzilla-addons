<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

require_once(dirname(__FILE__).'/channelreputation.php');

class Channelreputation extends Controller {

	function post() {
		if (argv(1) == 'settings') {
			\ChannelReputation_Utils::feature_settings_post();
		} else {
       			$html = \ChannelReputation_Utils::mod_post($_POST);
       			echo $html;
       			killme();
		}

	}

	function get() {

		if (argv(1) == 'settings' ) {
			return \ChannelReputation_Utils::feature_settings();
		} else {
			return '<h1>Page Not Found</h1>';
		}

	}

}
