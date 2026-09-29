<?php
/*
 * Plugin: pollingtime
 * Ajoute une entree "Analyse Polling Time" dans le menu Management de Cacti.
 * Compatible Cacti 1.2.x (teste sur 1.2.30 / PHP 8.2 / MariaDB 10.4 / RHEL 8).
 */

function plugin_pollingtime_install() {
	api_plugin_register_hook('pollingtime', 'config_arrays',        'pollingtime_config_arrays',        'setup.php');
	api_plugin_register_hook('pollingtime', 'draw_navigation_text', 'pollingtime_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('pollingtime', 'top_graph_header_tabs', 'pollingtime_show_tab',             'setup.php');

	// Realm de securite : donne acces aux pages pollingtime.php et pollingtime_help.php
	api_plugin_register_realm('pollingtime', 'pollingtime.php,pollingtime_help.php', __('Analyse Polling Time', 'pollingtime'), 1);
}

function plugin_pollingtime_uninstall() {
	// Rien de particulier a nettoyer (aucune table creee).
}

function plugin_pollingtime_check_config() {
	return true;
}

function plugin_pollingtime_upgrade() {
	api_plugin_register_hook('pollingtime', 'top_graph_header_tabs', 'pollingtime_show_tab', 'setup.php');

	// Rafraichit le lien "Plugin Name" de la page Plugin Management vers la nouvelle page d'aide
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
		'longname' => 'Analyse Polling Time',
		'author'   => 'Arnaud LEFFEBVRE',
		'homepage' => 'plugins/pollingtime/pollingtime_help.php',
		'email'    => '',
		'url'      => ''
	);
}

/* Ajout de l'entree dans le menu Management de la console */
function pollingtime_config_arrays() {
	global $menu;

	if (!isset($menu[__('Management')])) {
		$menu[__('Management')] = array();
	}

	$menu[__('Management')]['plugins/pollingtime/pollingtime.php'] = __('Analyse Polling Time', 'pollingtime');
}

/* Ajout de l'onglet dans la navigation principale */
function pollingtime_show_tab() {
	global $config;

	if (api_user_realm_auth('pollingtime.php')) {
		print "<a id='pollingtime' href='" . $config['url_path'] . "plugins/pollingtime/pollingtime.php' alt='" . __esc('Analyse Polling Time', 'pollingtime') . "'>" . __('Analyse Polling Time', 'pollingtime') . '</a>';
	}
}

/* Enregistrement du fil d'ariane (breadcrumb) */
function pollingtime_draw_navigation_text($nav) {
	$nav['pollingtime.php:'] = array(
		'title'   => __('Analyse Polling Time', 'pollingtime'),
		'mapping' => 'index.php:',
		'url'     => 'plugins/pollingtime/pollingtime.php',
		'level'   => 1
	);

	return $nav;
}
