<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

global $wpdb;

// Drop tables in reverse order of dependencies
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}litesurveys_responses");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}litesurveys_submissions");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}litesurveys_questions");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}litesurveys_surveys");

// Delete options
delete_option('lsapp_litesurveys_version');
delete_option('LSAPP_litesurveys_settings');
