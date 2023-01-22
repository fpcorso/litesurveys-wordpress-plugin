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
			add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		}

		/**
		 * Code to be run during admin_init
		 *
		 * @since 1.0.0
		 */
		public static function admin_init() {
			register_setting( 'litesurveys', 'litesurveys_settings' );
			add_settings_section(
				'litesurveys_settings_section',
				'',
				array( __CLASS__, 'litesurveys_settings_section_callback' ),
				'litesurveys'
			);
			add_settings_field(
				'litesurveys_settings_site_id',
				'Site ID',
				array( __CLASS__, 'litesurveys_settings_site_id_callback' ),
				'litesurveys',
				'litesurveys_settings_section',
				array(
					'label_for'         => 'litesurveys_field_site_id',
					'class'             => 'wporg_row',
				)
			);
		}

		/**
		 * Sets up our page in the admin menu
		 *
		 * @since 1.0.0
		 */
		public static function setup_admin_menu() {
			add_options_page( 'LiteSurveys', 'LiteSurveys', 'manage_options', 'litesurveys', array( __CLASS__, 'generate_admin_page' ) );
		}

		/**
		 * Callback for our main settings section
		 *
		 * @since 1.0.0
		 */
		public static function litesurveys_settings_section_callback() {

		}

		/**
		 * Callback for site ID settings field
		 *
		 * @since 1.0.0
		 */
		public static function litesurveys_settings_site_id_callback() {
			$options = get_option( 'litesurveys_settings' );
			?>
			<input type="text" value="<?php esc_attr($options['site_id']); ?>">
			<?php
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

			if ( isset( $_GET['settings-updated'] ) ) {
				add_settings_error( 'litesurveys_messages', 'litesurveys_messages', 'Settings Saved', 'updated' );
			}
		
			// show error/update messages
			settings_errors( 'litesurveys_messages' );
			?>
			<div class="wrap">
				<h1>LiteSurveys Integration</h1>
				<form action="options.php" method="post">
					<?php
					settings_fields( 'litesurveys' );
					do_settings_sections( 'litesurveys' );
					submit_button( 'Save Settings' );
					?>
				</form>
			</div>
			<?php
		}
	}

	LiteSurveys_Integration::init();
}
?>