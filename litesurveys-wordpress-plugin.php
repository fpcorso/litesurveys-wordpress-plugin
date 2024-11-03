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
