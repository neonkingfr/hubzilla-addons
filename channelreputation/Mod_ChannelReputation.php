<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

require_once(dirname(__FILE__).'/channelreputation.php');

class Channelreputation extends Controller {

	function post() {
		ChannelReputation_Utils::feature_settings_post();

	}

	function get() {

		return ChannelReputation_Utils::feature_settings();

	}

}
