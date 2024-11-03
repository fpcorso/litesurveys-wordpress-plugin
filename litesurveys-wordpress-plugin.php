<?php
/**
 * Plugin Name: LiteSurveys
 * Description: Adds your LiteSurveys to your WordPress site
 * Version: 1.0.3
 * Requires at least: 6.1
 * Requires PHP: 8.0
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Author: LiteSurveys
 * Author URI: https://litesurveys.com
 * Text Domain: litesurveys
 *
 * @author LiteSurveys
 */

// Exits if accessed directly.
defined('ABSPATH') or die('Direct access not permitted.');

// Define plugin version constant.
define( 'LSAPP_PLUGIN_VERSION', '1.0.3' );


/**
 * The plugin's main class
 *
 * @since 1.0.0
 */
class LSAPP_LiteSurveys {
	private static $instance = null;
	private $plugin_path;
	private $plugin_url;

	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->plugin_path = plugin_dir_path(__FILE__);
		$this->plugin_url = plugin_dir_url(__FILE__);
		
		// Initialize hooks
		add_action('admin_menu', array($this, 'addAdminMenu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueueAdminAssets'));
		register_activation_hook(__FILE__, array($this, 'activatePlugin'));
		register_deactivation_hook(__FILE__, array($this, 'deactivatePlugin'));
		register_uninstall_hook(__FILE__, array('LiteSurveys', 'uninstallPlugin'));
	}

	/**
	 * Runs our DB setup code upon plugin activation.
	 * 
	 * @since 2.0.0
	 */
	public function activatePlugin() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Create surveys table
		$sql_surveys = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}litesurveys_surveys (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			active tinyint(1) DEFAULT 0,
			submit_message text NOT NULL,
			targeting_settings json DEFAULT NULL,
			appearance_settings json DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Create questions table
		$sql_questions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}litesurveys_questions (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			survey_id bigint(20) NOT NULL,
			type varchar(25) NOT NULL,
			content varchar(100) NOT NULL,
			answers json DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			FOREIGN KEY (survey_id) REFERENCES {$wpdb->prefix}litesurveys_surveys(id) ON DELETE CASCADE
		) $charset_collate;";

		// Create submissions table
		$sql_submissions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}litesurveys_submissions (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			survey_id bigint(20) NOT NULL,
			page varchar(255) DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			FOREIGN KEY (survey_id) REFERENCES {$wpdb->prefix}litesurveys_surveys(id) ON DELETE CASCADE
		) $charset_collate;";

		// Create responses table
		$sql_responses = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}litesurveys_responses (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) NOT NULL,
			question_id bigint(20) NOT NULL,
			content text NOT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			FOREIGN KEY (submission_id) REFERENCES {$wpdb->prefix}litesurveys_submissions(id) ON DELETE CASCADE,
			FOREIGN KEY (question_id) REFERENCES {$wpdb->prefix}litesurveys_questions(id) ON DELETE CASCADE
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql_surveys);
		dbDelta($sql_questions);
		dbDelta($sql_submissions);
		dbDelta($sql_responses);

		// Set version
		add_option('lsapp_litesurveys_version', '2.0.0');
	}

	public static function uninstallPlugin() {
		global $wpdb;
		
		// Drop tables in reverse order of dependencies
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}litesurveys_responses");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}litesurveys_submissions");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}litesurveys_questions");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}litesurveys_surveys");
		
		delete_option('lsapp_litesurveys_version');

		// Delete settings from 1.0.0 version if present
		delete_option( 'LSAPP_litesurveys_settings' );
	}

	/**
	 * Sets up our page in the admin menu
	 *
	 * @since 1.0.0
	 */
	public static function setup_admin_menu() {
		add_options_page( 'LiteSurveys', 'LiteSurveys', 'manage_options', 'LSAPP_litesurveys', array( __CLASS__, 'generate_admin_page' ) );
	}

	/**
	 * Retrieves the saved site id setting
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private static function get_site_id() {
		return self::get_setting('site_id', '');
	}

	/**
	 * Retrieves a specific plugin setting
	 *
	 * @since 1.0.0
	 * @param string $setting Which setting to retrieve.
	 * @param mixed  $default The value to return if setting does not exist.
	 * @return mixed
	 */
	private static function get_setting($setting, $default = false) {
		$settings = self::get_settings();
		if ( isset( $settings[$setting] ) ) {
			return $settings[$setting];
		}

		return $default;
	}

	/**
	 * Retrieves our plugin settings
	 *
	 * @since 1.0.0
	 * @return array Our settings
	 */
	private static function get_settings() {
		$settings = get_option( 'LSAPP_litesurveys_settings', [] );
		if (! is_array( $settings ) ) {
			$settings = [];
		}

		return $settings;
	}

	/**
	 * Adds a settings link for the plugin when on the plugins page
	 *
	 * @since 1.0.2
	 */
	public static function plugin_action_links($actions, $plugin_file) {
		// Check both the slug when installing from WP plugin repo and
		// slug when installing directly from GitHub repo.
		$plugin_files = [
			'litesurveys/litesurveys-wordpress-plugin.php',
			'litesurveys-wordpress-plugin/litesurveys-wordpress-plugin.php'
		];
		if (in_array($plugin_file, $plugin_files)) {
			$settings_url = sprintf( '<a href="%s">Settings</a>', esc_url( admin_url( 'options-general.php?page=LSAPP_litesurveys' ) ) );
			$actions = array_merge( ['litesurveys_settings' => $settings_url], $actions) ;
		}
		return $actions;
	}
}

// Initialize plugin
LSAPP_LiteSurveys::getInstance();
?>
