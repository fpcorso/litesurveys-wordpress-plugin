<?php
/**
 * Plugin Name: LiteSurveys Integration
 * Description: Adds your LiteSurveys to your WordPress site
 * Version: 1.0.0
 * Requires at least: 6.1
 * Requires PHP: 7.2
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Author: LiteSurveys
 * Author URI: https://litesurveys.com
 *
 * @author LiteSurveys
 */

// Exits if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'LiteSurveys_Integration' ) ) {
	/**
	 * The plugin's main class
	 *
	 * @since 1.0.0
	 */
	class LiteSurveys_Integration {

		/**
		 * Initializes our plugin
		 *
		 * @since 1.0.0
		 */
		public static function init() {
			self::load_hooks();
		}

		/**
		 * Adds in any plugin-wide hooks
		 *
		 * @since 1.0.0
		 */
		public static function load_hooks() {
			add_action( 'admin_menu', array( __CLASS__, 'setup_admin_menu' ) );
		}

		/**
		 * Sets up our page in the admin menu
		 *
		 * @since 1.0.0
		 */
		public static function setup_admin_menu() {
			add_management_page( 'LiteSurveys', 'LiteSurveys', 'manage_options', 'litesurveys', array( __CLASS__, 'generate_admin_page' ) );
		}

		/**
		 * Generates our admin page
		 *
		 * @since 1.0.0
		 */
		public static function generate_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
		}
	}

	LiteSurveys_Integration::init();
}
?>