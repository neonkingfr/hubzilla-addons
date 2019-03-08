<?php

namespace Zotlabs\Module\Settings;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

require_once(dirname(__FILE__).'/channelreputation.php');

class Channelreputation extends Controller {

	function post() {

		if (!local_channel()) {
			return;
		}
		if (!Apps::addon_app_installed(local_channel(), 'channelreputation')) {
				return;
		}

			\ChannelReputation_Utils::feature_settings_post();

	}

	function get() {

		if (!local_channel()) {
			return;
		}

		if (!Apps::addon_app_installed(local_channel(), 'channelreputation')) {
				return '<h2>The Channel Reputation Addon is not installed on this channel.</h2>';
		}

		return \ChannelReputation_Utils::feature_settings();

	}

}
