<?php

/**
 *   * Name: Logger stats help
 *   * Description: Display controls and help for mod logger_stats
 *   * Requires: logger_stats
 */

namespace Zotlabs\Widget;

use Zotlabs\Extend\Widget;

class Logger_stats_help {

	function widget($arr) {
		if(!local_channel() && !is_site_admin())
			return;

		$content = '';

		if (!get_config('system', 'logfile')) {
			$content .= <<<EOF
<div class="alert alert-warning" role="alert">
	No logfile defined!
</div>
EOF;
		}

		if (!get_config('system', 'debugging')) {
			$content .= <<<EOF
<div class="alert alert-warning" role="alert">
	Debugging is disabled!
</div>
EOF;
		}

		$content .= <<<EOF
<div class="widget">
	<h3>Controls</h3>
	<div class="d-grid gap-2">
		<button id="line-btn" class="btn btn-outline-secondary" type="button">Line chart (l)</button>
		<button id="scatter-btn" class="btn btn-outline-secondary" type="button">Scatter chart (s)</button>
		<button id="bar-btn" class="btn btn-outline-secondary" type="button">Bar chart (b)</button>
		<button id="fs-btn" class="btn btn-outline-secondary" type="button">Toggle fullscreen (f or dblclick chart)</button>
		<button id="zoom-btn" class="btn btn-outline-secondary disabled" type="button">Press ctrl to zoom with mouse</button>
		<button id="reset-zoom-btn" class="btn btn-outline-secondary" type="button">Reset zoom (r)</button>
	</div>
</div>
EOF;

		$content .= <<<EOF
<div class="widget">
	<h3>Help</h3>
	<div class="">
		For additional data sets you need to add logging statements to the code like this:<br>
		<br>
		<code>
		\$start = microtime(true);<br>
		my_function();<br>
		logger('logger_stats_data cmd:my_function start:' . \$start . ' ' . 'end:' . microtime(true) . ' meta:' . random_string(16));
		</code>
		<br>
		cmd: data set label<br>
		start: start timestamp in ms<br>
		end: end timestamp in ms<br>
		meta: additional info (optional)
	</div>
</div>
EOF;

		return $content;
	}
}
