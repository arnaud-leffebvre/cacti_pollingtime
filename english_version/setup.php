<?php
/*
 * Plugin: pollingtime
 * Adds a "Polling Time Analysis" entry to the Cacti Management menu.
 * Compatible with Cacti 1.2.x (tested on 1.2.30 / PHP 8.2 / MariaDB 10.4 / RHEL 8).
 */

function plugin_pollingtime_install() {
	api_plugin_register_hook('pollingtime', 'config_arrays',        'pollingtime_config_arrays',        'setup.php');
	api_plugin_register_hook('pollingtime', 'draw_navigation_text', 'pollingtime_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('pollingtime', 'top_graph_header_tabs', 'pollingtime_show_tab',             'setup.php');

	// Security realm: grants access to the pollingtime.php and pollingtime_help.php pages
	api_plugin_register_realm('pollingtime', 'pollingtime.php,pollingtime_help.php', __('Polling Time Analysis', 'pollingtime'), 1);
}

function plugin_pollingtime_uninstall() {
	// Nothing special to clean up (no table created).
}

function plugin_pollingtime_check_config() {
	return true;
}

function plugin_pollingtime_upgrade() {
	api_plugin_register_hook('pollingtime', 'top_graph_header_tabs', 'pollingtime_show_tab', 'setup.php');

	// Refreshes the "Plugin Name" link on the Plugin Management page to point to the new help page
	db_execute_prepared('UPDATE plugin_config SET webpage = ? WHERE directory = ?', array('plugins/pollingtime/pollingtime_help.php', 'pollingtime'));

	return false;
}

function plugin_pollingtime_version() {
	return pollingtime_version();
}

function pollingtime_version() {
	return array(
		'name'     => 'pollingtime',
		'version'  => '1.0.4',
		'longname' => 'Polling Time Analysis',
		'author'   => 'Arnaud LEFFEBVRE',
		'homepage' => 'plugins/pollingtime/pollingtime_help.php',
		'email'    => '',
		'url'      => ''
	);
}

/* Add the entry to the console's Management menu */
function pollingtime_config_arrays() {
	global $menu;

	if (!isset($menu[__('Management')])) {
		$menu[__('Management')] = array();
	}

	$menu[__('Management')]['plugins/pollingtime/pollingtime.php'] = __('Polling Time Analysis', 'pollingtime');
}

/* Add the tab to the main navigation */
function pollingtime_show_tab() {
	global $config;

	if (api_user_realm_auth('pollingtime.php')) {
		print "<a id='pollingtime' href='" . $config['url_path'] . "plugins/pollingtime/pollingtime.php' alt='" . __esc('Polling Time Analysis', 'pollingtime') . "'>" . __('Polling Time Analysis', 'pollingtime') . '</a>';
	}
}

/* Register the breadcrumb */
function pollingtime_draw_navigation_text($nav) {
	$nav['pollingtime.php:'] = array(
		'title'   => __('Polling Time Analysis', 'pollingtime'),
		'mapping' => 'index.php:',
		'url'     => 'plugins/pollingtime/pollingtime.php',
		'level'   => 1
	);

	return $nav;
}
