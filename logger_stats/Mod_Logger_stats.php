<?php

namespace Zotlabs\Module;

use App;
use Zotlabs\Lib\Apps;
use Zotlabs\Web\Controller;

class Logger_stats extends Controller {

	function get() {
		if(!local_channel() && !is_site_admin())
			return;

		if(!Apps::addon_app_installed(local_channel(), 'logger_stats')) {
			//Do not display any associated widgets at this point
			App::$pdl = '';
			$papp = Apps::get_papp('Logger Statistics');
			return Apps::app_render($papp, 'module');
		}

		$content = '';
		$raw_data = [];
		$hours = ((isset($_REQUEST['h'])) ? floatval($_REQUEST['h']) : 1);
		$i = 0;

		$logfile = get_config('system', 'logfile');
		$handle = (($logfile) ? @fopen($logfile, 'r') : null);

		if ($handle) {
			while (!feof($handle)) {

				$buffer = fgets($handle, 1024);
				if (str_contains($buffer, 'logger_stats_data')) {

					preg_match('/(?<=cmd:).*?(?=\s)/', $buffer, $match);
					$cmd = $match[0] ?? '';

					preg_match('/(?<=start:).*?(?=\s)/', $buffer, $match);
					$start = $match[0] ?? '';

					preg_match('/(?<=end:).*?(?=\s)/', $buffer, $match);
					$end = $match[0] ?? '';

					preg_match('/(?<=meta:).*?(?=\s)/', $buffer, $match);
					$meta = $match[0] ?? '';

					// restrict sample size
					if ($hours && $start < time() - 3600 * $hours) {
						continue;
					}

					$raw_data[$cmd][] = [
						'start' => $start,
						'end' => $end,
						'meta' => $meta
					];
				}
				$i++;
			}
		}

		$i = 0;

		foreach ($raw_data as $cmd => $data) {
			$dataset[$i]['label'] = $cmd;

			if (!in_array($cmd, ['Cron']))
				$dataset[$i]['hidden'] = true;

		//	$dataset[$i]['borderWidth'] = 1;
		//	$dataset[$i]['pointRadius'] = 2;

			$dataset[$i]['barThickness'] = 3;

			foreach ($data as $d) {
				$dataset[$i]['data'][] = [
					'x' => intval($d['start'] * 1000),
					'y' => $d['end'] - $d['start'],
					'meta' => $d['meta']
				];
			}

			// The start times might not be in order - fix that
			$key = array_column($dataset[$i]['data'], 'x');
			array_multisort($key, SORT_ASC, $dataset[$i]['data']);

			$i++;
		}

		$json_dataset = json_encode($dataset);

		head_add_js('/addon/logger_stats/view/js/chartjs/dist/chart.umd.js');
		head_add_js('/addon/logger_stats/view/js/chartjs/zoom/chartjs-plugin-zoom.min.js');
		head_add_js('/addon/logger_stats/view/js/momentjs/min/moment.min.js');
		head_add_js('/addon/logger_stats/view/js/chartjs/moment-adapter.js');

		$content .= '<div id="stats-wrapper">';
		$content .= '	<canvas id="stats"></canvas>';
		$content .= '</div>';

		$content .= <<<EOF
<script>
	const ctx = document.getElementById('stats');
	let chart = new Chart(ctx, {
		type: 'scatter',
		data: {
		  datasets: $json_dataset,
		},

		options: {
			scales: {
				x: {
					type: 'time',
					time: {
						unit: 'second'
					}
				},
				y: {
					beginAtZero: true,
					ticks: {
						stepSize: 1,
					},
					title: {
						display: true,
						text: 'Seconds'
					}
				}
			},
			plugins: {
				decimation: {
					enabled: true,
					algorithm: 'lttb',
					samples: 50,
					threshold: 1
				},
				zoom: {
					zoom: {
						wheel: {
							enabled: true,
							modifierKey: 'ctrl'
						},
						drag: {
							enabled: true,
							modifierKey: 'ctrl'
						},
						mode: 'x',
					},
				},
				tooltip: {
					callbacks: {
						footer: function(i) {
							navigator.clipboard.writeText(i[0].raw.meta);
							return 'meta: ' + i[0].raw.meta;
						}
					}
				},
				legend: {
					onClick: function(e, legendItem, legend) {
						const index = legendItem.datasetIndex;
						const ci = legend.chart;
						if (ci.isDatasetVisible(index)) {
							ci.hide(index);
							legendItem.hidden = true;

							//make sure we change the data so that we will not loose hidden states on updates
							ci.config._config.data.datasets[index].hidden = true;
						} else {
							ci.show(index);
							legendItem.hidden = false;

							//make sure we change the data so that we will not loose hidden states on updates
							ci.config._config.data.datasets[index].hidden = false;
						}
					}
				}
			}
		}
	});

	$('#stats-wrapper').on('dblclick', function () { $('#stats-wrapper').toggleClass('fs'); });
	$('#reset-zoom-btn').on('click', function () { chart.resetZoom(); });
	$('#line-btn').on('click', function () { chart.config.type = 'line'; chart.update(); });
	$('#scatter-btn').on('click', function () { chart.config.type = 'scatter'; chart.update(); });
	$('#bar-btn').on('click', function () { chart.config.type = 'bar'; chart.update(); });
	$('#fs-btn').on('click', function () { $('#stats-wrapper').toggleClass('fs'); });

	$(document).on('keypress', function(e) {

		if (e.target.tagName !== 'BODY') {
			return;
		}

		e.preventDefault();

		if(e.originalEvent.charCode == 98){
			chart.config.type = 'bar';
			chart.update();
		}
		if(e.originalEvent.charCode == 102){
			$('#stats-wrapper').toggleClass('fs');
		}
		if(e.originalEvent.charCode == 108){
			chart.config.type = 'line';
			chart.update();
		}
		if(e.originalEvent.charCode == 114){
			chart.resetZoom();
		}
		if(e.originalEvent.charCode == 115){
			chart.config.type = 'scatter';
			chart.update();
		}
	});
</script>
EOF;

		return $content;
	}

}
