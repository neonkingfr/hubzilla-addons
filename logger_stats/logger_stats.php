<?php

/**
 * Name: Logger Statistics
 * Description: Parses the log and creates a visual representation of it
 * Version: 1.0
 * Author: Mario Vavti
 */


use Zotlabs\Extend\Widget;
use Zotlabs\Extend\Route;

function logger_stats_install() {
	Route::register('addon/logger_stats/Mod_Logger_stats.php', 'logger_stats');
	Widget::register('addon/logger_stats/Widget/Logger_stats_help.php', 'logger_stats_help');
}

function logger_stats_uninstall() {
	Route::unregister('addon/logger_stats/Mod_Logger_stats.php', 'logger_stats');
	Widget::unregister('addon/logger_stats/Widget/Logger_stats_help.php', 'logger_stats_help');
}
