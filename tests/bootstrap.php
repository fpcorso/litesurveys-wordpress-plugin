<?php
/**
 * PHPUnit bootstrap file
 */

// Composer autoloader must be loaded before WP setup
require_once dirname(__DIR__) . '/vendor/autoload.php';

$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
	$_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load plugin and create tables
 */
function _manually_load_plugin() {
	global $wpdb;
	require dirname(__DIR__) . '/litesurveys-wordpress-plugin.php';
	
	// Get plugin instance and create tables
	$plugin = LSAPP_LiteSurveys::get_instance();
	$plugin->create_database_tables($wpdb->get_charset_collate());
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';