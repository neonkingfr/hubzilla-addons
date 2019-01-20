<?php

namespace Zotlabs\Module;

use \App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

require_once(dirname(__FILE__).'/queueworker.php');

class Queueworker extends Controller {

	function init() {
	}

	function post() {

		$content = "<H1>ERROR: Page not found</H1>";
		App::$error = 404;

		return $content;
	}

	function get() {

		$content = "<H1>ERROR: Page not found</H1>";
		App::$error = 404;


		if (!local_channel()) {
			return $content;
		}

		if (!(is_site_admin())) {
			return $content;
    		}


		$content = "<H1>Queue Status</H1>";

		$r = q('select count(*) as qentries from workerq');

		if (!$r) {
			$content = "<H4>There was an error querying the database.</H4>";
			return $content;
		}

		$content = "<H4>There are ".$r[0]['qentries']." queue items to be processed.</H4>";
		return $content;

	}
}
