<?php
/**
 * Plugin Name: LiteSurveys
 * Description: Adds your LiteSurveys to your WordPress site
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * License: GPLv3
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
			if (is_admin()) {
				add_action( 'admin_menu', array( __CLASS__, 'setup_admin_menu' ) );
				add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
			}
			
			add_action('wp_footer', array( __CLASS__, 'add_script' ), 50);
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
					'label_for'         => 'site_id',
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
			?>
			<p>You will need to have an active <a href="https://litesurveys.com" target="_blank">LiteSurveys</a> account to use this plugin. Within your LiteSurveys account, go to the "Connect Website" page to get your Website ID needed below.</p>
			<?php
		}

		/**
		 * Callback for site ID settings field
		 *
		 * @since 1.0.0
		 */
		public static function litesurveys_settings_site_id_callback($args) {
			$site_id = self::get_site_id();
			?>
			<input id="<?php echo esc_attr( $args['label_for'] ); ?>" name="litesurveys_settings[<?php echo esc_attr( $args['label_for'] ); ?>]" type="text" value="<?php echo esc_attr( $site_id ); ?>">
			<p class="description">(Leave blank to disable)</p>
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

		/**
		 * Adds the LiteSurveys script to the website
		 *
		 * @since 1.0.0
		 */
		public static function add_script() {
			$site_id = self::get_site_id();
			if ( empty( $site_id ) ) {
				return;
			}

			?>
			<script src="https://embeds.litesurveys.com/litesurveys.min.js" id="litesurveys-js" data-site-id="<?php echo esc_attr($site_id); ?>"></script>
			<?php
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
			$settings = get_option( 'litesurveys_settings', [] );
			if (! is_array( $settings ) ) {
				$settings = [];
			}

			return $settings;
		}
	}

	LiteSurveys_Integration::init();
}
?>
