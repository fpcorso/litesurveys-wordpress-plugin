<?php
/**
 * Plugin Name: LiteSurveys
 * Description: Adds simple one-question surveys to your WordPress site
 * Version: 2.1.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Author: LiteSurveys
 * Author URI: https://litesurveys.com
 * Text Domain: litesurveys
 *
 * @package LiteSurveys
 */

// Exits if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main LiteSurveys plugin class.
 *
 * @since 1.0.0
 */
class LSAPP_LiteSurveys {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '2.1.0';

	/**
	 * The single instance of the class.
	 *
	 * @var LSAPP_LiteSurveys
	 */
	private static $instance = null;

	/**
	 * Plugin path.
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Our REST API functionality.
	 */
	private $rest_api;

	/**
	 * Cache time in seconds.
	 *
	 * @var int
	 */
	const CACHE_TIME = 300;

	/**
	 * Main LSAPP_LiteSurveys Instance.
	 *
	 * Ensures only one instance of LSAPP_LiteSurveys is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @return LSAPP_LiteSurveys Main instance
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * LSAPP_LiteSurveys Constructor.
	 */
	private function __construct() {
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->plugin_url  = plugin_dir_url( __FILE__ );

		// Include the REST API class
		require_once $this->plugin_path . 'includes/class-rest-api.php';
		$this->rest_api = new LSAPP_REST_API();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Setting up plugin upon activation.
		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );

		// Load translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Add Admin code.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_save_survey', array( $this, 'handle_save_survey' ) );
		add_action( 'admin_post_delete_survey', array( $this, 'handle_delete_survey' ) );
		add_action( 'admin_post_delete_submission', array( $this, 'handle_delete_submission' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );

		// Add REST API endpoints.
		add_action( 'rest_api_init', array( $this->rest_api, 'register_rest_routes' ) );

		// Add frontend script.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Activate plugin and create database tables.
	 *
	 * @since 1.0.0
	 */
	public function activate_plugin() {
		global $wpdb;

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$this->create_database_tables( $charset_collate );

		add_option( 'lsapp_litesurveys_version', self::VERSION );
	}

	/**
	 * Create plugin database tables.
	 *
	 * @since 1.0.0
	 * @param string $charset_collate Database charset and collation.
	 */
	public function create_database_tables( $charset_collate ) {
		global $wpdb;

		// Create surveys table.
		$sql_surveys = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}litesurveys_surveys (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			active tinyint(1) DEFAULT 0,
			submit_message text NOT NULL,
			targeting_settings json DEFAULT NULL,
			appearance_settings json DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at timestamp NULL DEFAULT NULL,
			PRIMARY KEY (id)
		) $charset_collate;";

		// Create questions table.
		$sql_questions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}litesurveys_questions (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			survey_id bigint(20) NOT NULL,
			type varchar(25) NOT NULL,
			content varchar(100) NOT NULL,
			answers json DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at timestamp NULL DEFAULT NULL,
			PRIMARY KEY (id),
			FOREIGN KEY (survey_id) REFERENCES {$wpdb->prefix}litesurveys_surveys(id) ON DELETE CASCADE
		) $charset_collate;";

		// Create submissions table.
		$sql_submissions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}litesurveys_submissions (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			survey_id bigint(20) NOT NULL,
			page varchar(255) DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			deleted_at timestamp NULL DEFAULT NULL,
			PRIMARY KEY (id),
			FOREIGN KEY (survey_id) REFERENCES {$wpdb->prefix}litesurveys_surveys(id) ON DELETE CASCADE
		) $charset_collate;";

		// Create responses table.
		$sql_responses = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}litesurveys_responses (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) NOT NULL,
			question_id bigint(20) NOT NULL,
			content text NOT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			deleted_at timestamp NULL DEFAULT NULL,
			PRIMARY KEY (id),
			FOREIGN KEY (submission_id) REFERENCES {$wpdb->prefix}litesurveys_submissions(id) ON DELETE CASCADE,
			FOREIGN KEY (question_id) REFERENCES {$wpdb->prefix}litesurveys_questions(id) ON DELETE CASCADE
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_surveys );
		dbDelta( $sql_questions );
		dbDelta( $sql_submissions );
		dbDelta( $sql_responses );
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'litesurveys',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 1.0.0
	 * @param string $hook The current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, 'litesurveys' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		if ( 'edit' === $action || 'new' === $action ) {
			$suffix = $this->get_asset_suffix();
			wp_enqueue_style(
				'litesurveys-admin',
				$this->plugin_url . "resources/css/admin{$suffix}.css",
				array(),
				self::VERSION
			);

			wp_enqueue_script(
				'litesurveys-admin',
				$this->plugin_url . "resources/js/admin{$suffix}.js",
				array( 'jquery' ),
				self::VERSION,
				true
			);
		}
	}

	/**
	 * Add admin menu items.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			'LiteSurveys',
			'LiteSurveys',
			'manage_options',
			'LSAPP_litesurveys',
			array( $this, 'render_admin_page' ),
			'dashicons-chart-bar',
			30
		);
	}

	/**
	 * Render the admin page content.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		$this->verify_admin_access();

		global $wpdb;

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		switch ( $action ) {
			case 'view-responses':
				$survey_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
				if ( ! $survey_id ) {
					wp_die( esc_html__( 'Invalid survey ID.', 'litesurveys' ) );
				}

				// Get survey data with question.
				$survey = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT s.*, q.content as question_content 
						FROM {$wpdb->prefix}litesurveys_surveys s 
						LEFT JOIN {$wpdb->prefix}litesurveys_questions q ON s.id = q.survey_id 
						WHERE s.id = %d AND s.deleted_at IS NULL",
						$survey_id
					)
				);

				if ( ! $survey ) {
					wp_die( esc_html__( 'Survey not found.', 'litesurveys' ) );
				}

				// Pagination settings.
				$per_page     = 20;
				$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
				$offset       = ( $current_page - 1 ) * $per_page;

				// Get search term if present
                $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
                $search_where = '';
                $search_params = array($survey_id);

                if (!empty($search)) {
                    $search_where = " AND (r.content LIKE %s OR s.page LIKE %s)";
                    $search_wild = '%' . $wpdb->esc_like($search) . '%';
                    $search_params[] = $search_wild;
                    $search_params[] = $search_wild;
                }

				// Get total count for pagination.
				$total_items = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT s.id)
                        FROM {$wpdb->prefix}litesurveys_submissions s
                        LEFT JOIN {$wpdb->prefix}litesurveys_responses r ON s.id = r.submission_id
                        WHERE s.survey_id = %d 
                        AND s.deleted_at IS NULL" . $search_where,
                        $search_params
                    )
                );

				// Add pagination parameters
                $search_params[] = $per_page;
                $search_params[] = $offset;

				// Get submissions with responses.
				$submissions = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT s.id, s.created_at, s.page, r.content as response
                        FROM {$wpdb->prefix}litesurveys_submissions s
                        LEFT JOIN {$wpdb->prefix}litesurveys_responses r ON s.id = r.submission_id
                        WHERE s.survey_id = %d 
                        AND s.deleted_at IS NULL" . 
                        $search_where . 
                        " ORDER BY s.created_at DESC
                        LIMIT %d OFFSET %d",
                        $search_params
                    )
                );

				// Calculate pagination values.
				$total_pages = ceil( $total_items / $per_page );

				include $this->plugin_path . 'views/admin/survey-submissions.php';
				break;

			case 'edit':
			case 'new':
				// Get survey data if editing.
				$survey_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
				$survey    = null;

				if ( $survey_id ) {
					$survey = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}litesurveys_surveys WHERE id = %d AND deleted_at IS NULL",
							$survey_id
						)
					);

					if ( $survey ) {
						$question = $wpdb->get_row(
							$wpdb->prepare(
								"SELECT * FROM {$wpdb->prefix}litesurveys_questions WHERE survey_id = %d AND deleted_at IS NULL",
								$survey_id
							)
						);

						if ( $question ) {
							$survey->question         = $question;
							$survey->question->answers = json_decode( $question->answers );
						}

						$survey->targeting_settings   = json_decode( $survey->targeting_settings );
						$survey->appearance_settings = json_decode( $survey->appearance_settings );
					}
				}

				// Set defaults for new survey.
				if ( ! $survey ) {
					$survey = (object) array(
						'name'                => '',
						'active'             => false,
						'submit_message'     => 'Thanks! I appreciate you taking the time to respond.',
						'targeting_settings' => (object) array(
							'targets'  => (object) array(
								'show'     => 'all',
								'includes' => array(),
								'excludes' => array(),
							),
							'trigger'  => array(
								(object) array(
									'type'        => 'auto',
									'auto_timing' => 5,
								),
							),
						),
						'appearance_settings' => (object) array(
							'horizontal_position' => 'right',
						),
						'question'           => (object) array(
							'type'    => 'multiple-choice',
							'content' => '',
							'answers' => array( '', '', '' ),
						),
					);
				}
				include $this->plugin_path . 'views/admin/survey-edit.php';
				break;

			default:
				$surveys = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}litesurveys_surveys WHERE deleted_at IS NULL ORDER BY created_at DESC"
				);
				include $this->plugin_path . 'views/admin/surveys-admin.php';
				break;
		}
	}

	/**
	 * Handle saving survey data.
	 *
	 * @since 1.0.0
	 */
	public function handle_save_survey() {
		$this->verify_admin_access();

		check_admin_referer( 'save_survey', 'survey_nonce' );

		global $wpdb;

		$survey_id = isset( $_POST['survey_id'] ) ? absint( $_POST['survey_id'] ) : 0;
		$save_type = isset( $_POST['save_type'] ) ? sanitize_text_field( wp_unslash( $_POST['save_type'] ) ) : 'draft';

		try {
			// Validate required fields.
			if ( empty( $_POST['survey_name'] ) ) {
				throw new Exception( __( 'Survey name is required.', 'litesurveys' ) );
			}

			if ( empty( $_POST['question_content'] ) ) {
				throw new Exception( __( 'Survey question is required.', 'litesurveys' ) );
			}

			// Prepare targeting settings.
			$targeting_settings = array(
				'targets' => array(
					'show'     => sanitize_text_field( wp_unslash( $_POST['targeting_show'] ) ),
					'includes' => isset( $_POST['includes'] ) ?
						array_map( 'sanitize_text_field', wp_unslash( $_POST['includes'] ) ) : array(),
					'excludes' => isset( $_POST['excludes'] ) ?
						array_map( 'sanitize_text_field', wp_unslash( $_POST['excludes'] ) ) : array(),
				),
				'trigger' => array(
					array(
						'type'        => sanitize_text_field( wp_unslash( $_POST['trigger_type'] ) ),
						'auto_timing' => absint( $_POST['auto_timing'] ),
					),
				),
			);

			// Prepare appearance settings.
			$appearance_settings = array(
				'horizontal_position' => sanitize_text_field( wp_unslash( $_POST['horizontal_position'] ) ),
			);

			// Sanitize JSON data.
			$targeting_json   = $this->sanitize_json_data( $targeting_settings );
			$appearance_json = $this->sanitize_json_data( $appearance_settings );

			if ( false === $targeting_json || false === $appearance_json ) {
				throw new Exception( __( 'Invalid settings data.', 'litesurveys' ) );
			}

			// Prepare survey data.
			$survey_data = array(
				'name'                => sanitize_text_field( wp_unslash( $_POST['survey_name'] ) ),
				'submit_message'      => sanitize_textarea_field( wp_unslash( $_POST['submit_message'] ) ),
				'active'             => ( 'publish' === $save_type ),
				'targeting_settings'  => $targeting_json,
				'appearance_settings' => $appearance_json,
			);

			// Prepare answers for multiple choice.
			$answers = array();
			if ( 'multiple-choice' === $_POST['question_type'] && ! empty( $_POST['answers'] ) ) {
				$answers = array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST['answers'] ) ) );
				if ( count( $answers ) < 2 ) {
					throw new Exception( __( 'Multiple choice questions must have at least 2 answers.', 'litesurveys' ) );
				}
			}

			// Prepare question data.
			$question_data = array(
				'type'    => sanitize_text_field( wp_unslash( $_POST['question_type'] ) ),
				'content' => sanitize_textarea_field( wp_unslash( $_POST['question_content'] ) ),
				'answers' => $this->sanitize_json_data( $answers ),
			);

			// Start transaction.
			$wpdb->query( 'START TRANSACTION' );

			if ( $survey_id ) {
				// Update existing survey.
				$result = $wpdb->update(
					$wpdb->prefix . 'litesurveys_surveys',
					$survey_data,
					array( 'id' => $survey_id )
				);

				if ( false === $result ) {
					throw new Exception( __( 'Failed to update survey.', 'litesurveys' ) );
				}

				// Update or insert question.
				$existing_question = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}litesurveys_questions WHERE survey_id = %d AND deleted_at IS NULL",
						$survey_id
					)
				);

				if ( $existing_question ) {
					$result = $wpdb->update(
						$wpdb->prefix . 'litesurveys_questions',
						$question_data,
						array( 'id' => $existing_question->id )
					);
					if ( false === $result ) {
						throw new Exception( __( 'Failed to update survey question.', 'litesurveys' ) );
					}
				} else {
					$question_data['survey_id'] = $survey_id;
					$result = $wpdb->insert( $wpdb->prefix . 'litesurveys_questions', $question_data );
					if ( ! $result ) {
						throw new Exception( __( 'Failed to create survey question.', 'litesurveys' ) );
					}
				}
			} else {
				// Insert new survey.
				$result = $wpdb->insert( $wpdb->prefix . 'litesurveys_surveys', $survey_data );
				if ( ! $result ) {
					throw new Exception( __( 'Failed to create survey.', 'litesurveys' ) );
				}
				$survey_id = $wpdb->insert_id;

				// Insert question.
				$question_data['survey_id'] = $survey_id;
				$result = $wpdb->insert( $wpdb->prefix . 'litesurveys_questions', $question_data );
				if ( ! $result ) {
					throw new Exception( __( 'Failed to create survey question.', 'litesurveys' ) );
				}
			}

			// Commit transaction.
			$wpdb->query( 'COMMIT' );

			// Clear caches after successful save.
			$this->clear_survey_caches();

			// Build redirect arguments.
			$redirect_args = array(
				'page'    => 'LSAPP_litesurveys',
				'action'  => 'edit',
				'id'      => $survey_id,
				'message' => 'publish' === $save_type ? 'survey-published' : 'survey-saved',
			);

			// Redirect with success message.
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;

		} catch ( Exception $e ) {
			// Rollback transaction.
			$wpdb->query( 'ROLLBACK' );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'LSAPP_litesurveys',
						'action'  => $survey_id ? 'edit' : 'new',
						'id'      => $survey_id,
						'message' => 'error',
						'error'   => rawurlencode( $e->getMessage() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	public function handle_delete_survey() {
		$this->verify_admin_access();

		// Verify nonce
		$nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
		$survey_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

		if (!wp_verify_nonce($nonce, 'delete-survey_' . $survey_id)) {
			wp_die(__('Security check failed.', 'litesurveys'));
		}

		if (!$survey_id) {
			wp_die(__('Invalid survey ID.', 'litesurveys'));
		}

		global $wpdb;

		try {
			// Start transaction
			$wpdb->query('START TRANSACTION');

			// Soft delete the survey
			$result = $wpdb->update(
				$wpdb->prefix . 'litesurveys_surveys',
				array('deleted_at' => current_time('mysql')),
				array('id' => $survey_id),
				array('%s'),
				array('%d')
			);

			if ($result === false) {
				throw new Exception(__('Failed to delete survey.', 'litesurveys'));
			}

			// Soft delete associated questions
			$wpdb->update(
				$wpdb->prefix . 'litesurveys_questions',
				array('deleted_at' => current_time('mysql')),
				array('survey_id' => $survey_id),
				array('%s'),
				array('%d')
			);

			// Commit transaction
			$wpdb->query('COMMIT');

			// Clear caches after successful save
			$this->clear_survey_caches();

			// Redirect with success message
			wp_safe_redirect(add_query_arg(
				array(
					'page' => 'LSAPP_litesurveys',
					'message' => 'survey-deleted'
				),
				admin_url('admin.php')
			));
			exit;

		} catch (Exception $e) {
			// Rollback transaction
			$wpdb->query('ROLLBACK');

			// Redirect with error message
			wp_safe_redirect(add_query_arg(
				array(
					'page' => 'LSAPP_litesurveys',
					'message' => 'error',
					'error' => urlencode($e->getMessage())
				),
				admin_url('admin.php')
			));
			exit;
		}
	}

	public function handle_delete_submission() {
		$this->verify_admin_access();
	
		// Verify nonce
		$nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
		$submission_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
		$survey_id = isset($_REQUEST['survey_id']) ? intval($_REQUEST['survey_id']) : 0;
	
		if (!wp_verify_nonce($nonce, 'delete-submission_' . $submission_id)) {
			wp_die(__('Security check failed.', 'litesurveys'));
		}
	
		if (!$submission_id || !$survey_id) {
			wp_die(__('Invalid submission ID.', 'litesurveys'));
		}
	
		global $wpdb;
	
		try {
			// Start transaction
			$wpdb->query('START TRANSACTION');
	
			// Soft delete the submission
			$result = $wpdb->update(
				$wpdb->prefix . 'litesurveys_submissions',
				array('deleted_at' => current_time('mysql')),
				array('id' => $submission_id),
				array('%s'),
				array('%d')
			);
	
			if ($result === false) {
				throw new Exception(__('Failed to delete submission.', 'litesurveys'));
			}
	
			// Soft delete associated responses
			$wpdb->update(
				$wpdb->prefix . 'litesurveys_responses',
				array('deleted_at' => current_time('mysql')),
				array('submission_id' => $submission_id),
				array('%s'),
				array('%d')
			);
	
			// Commit transaction
			$wpdb->query('COMMIT');
	
			// Redirect with success message
			wp_safe_redirect(add_query_arg(
				array(
					'page' => 'LSAPP_litesurveys',
					'action' => 'view-responses',
					'id' => $survey_id,
					'message' => 'submission-deleted'
				),
				admin_url('admin.php')
			));
			exit;
	
		} catch (Exception $e) {
			// Rollback transaction
			$wpdb->query('ROLLBACK');
	
			// Redirect with error message
			wp_safe_redirect(add_query_arg(
				array(
					'page' => 'LSAPP_litesurveys',
					'action' => 'view-responses',
					'id' => $survey_id,
					'message' => 'error',
					'error' => urlencode($e->getMessage())
				),
				admin_url('admin.php')
			));
			exit;
		}
	}
	
	/**
	 * Display admin notices with proper escaping.
	 */
	public function display_admin_notices() {
		if (!isset($_GET['page']) || 'LSAPP_litesurveys' !== $_GET['page']) {
			return;
		}

		if (isset($_GET['message'])) {
			$message_type = sanitize_text_field($_GET['message']);
			$class = 'notice ';
			$message = '';

			switch ($message_type) {
				case 'survey-published':
					$class .= 'notice-success';
					$message = __('Survey published successfully.', 'litesurveys');
					break;
				case 'survey-unpublished':
					$class .= 'notice-warning';
					$message = __('Survey unpublished.', 'litesurveys');
					break;
				case 'survey-saved':
					$class .= 'notice-success';
					$message = __('Survey saved successfully.', 'litesurveys');
					break;
				case 'survey-deleted':
					$class .= 'notice-success';
					$message = __('Survey deleted successfully.', 'litesurveys');
					break;
				case 'submission-deleted':
					$class .= 'notice-success';
					$message = __('Submission deleted successfully.', 'litesurveys');
					break;
				case 'error':
					$class .= 'notice-error';
					$message = isset($_GET['error']) ? 
						sanitize_text_field(urldecode($_GET['error'])) : 
						__('An error occurred while processing your request.', 'litesurveys');
					break;
				default:
					return; // Unknown message type
			}

			printf(
				'<div class="%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr($class),
				esc_html($message)
			);
		}
	}

	public function enqueue_frontend_assets() {
		// Only load if there are active surveys
		if (!$this->has_active_surveys()) {
			return;
		}
		
		$suffix = $this->get_asset_suffix();
		
		wp_enqueue_style(
			'litesurveys-frontend',
			$this->plugin_url . "resources/css/frontend{$suffix}.css",
			array(),
			self::VERSION
		);
		
		wp_enqueue_script(
			'litesurveys-frontend',
			$this->plugin_url . "resources/js/frontend{$suffix}.js",
			array(),
			self::VERSION,
			true
		);
		
		wp_localize_script('litesurveys-frontend', 'liteSurveysSettings', array(
			'ajaxUrl' => rest_url('litesurveys/v1/')
		));
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
		if ( in_array( $plugin_file, $plugin_files, true ) ) {
			$surveys_url = esc_url( admin_url( 'admin.php?page=LSAPP_litesurveys' ) );
			$surveys_link = sprintf( '<a href="%s">%s</a>', $surveys_url, __( 'Surveys', 'litesurveys' ) );
			$actions = array_merge( array( 'surveys' => $surveys_link ), $actions );
		}
		return $actions;
	}

	/**
	 * Get the REST API instance.
	 *
	 * @since 2.0.0
	 * @return LSAPP_REST_API The REST API instance.
	 */
	public function get_rest_api() {
		return $this->rest_api;
	}

	/**
	 * Check if there are any active surveys.
	 *
	 * @return bool True if there are active surveys, false otherwise.
	 */
	private function has_active_surveys() {
		$cache_key = 'litesurveys_has_active';
		$has_active = get_transient($cache_key);

		if (false === $has_active) {
			global $wpdb;
			
			$has_active = (bool)$wpdb->get_var($wpdb->prepare(
				"SELECT EXISTS(
					SELECT 1 FROM {$wpdb->prefix}litesurveys_surveys 
					WHERE active = %d AND deleted_at IS NULL
				)",
				1
			));
			
			set_transient($cache_key, $has_active ? '1' : '0', self::CACHE_TIME);
		}

		return $has_active === '1';
	}

	private function verify_admin_access() {
		if (!current_user_can('manage_options')) {
			wp_die(
				esc_html__('You do not have sufficient permissions to access this page.', 'litesurveys'),
				403
			);
		}
	}

	/**
	 * Sanitize JSON data before storage.
	 *
	 * @param array $data The data to be sanitized and stored as JSON.
	 * @return string|false Sanitized JSON string or false on failure.
	 */
	private function sanitize_json_data($data) {
		if (!is_array($data)) {
			return false;
		}

		array_walk_recursive($data, function(&$value) {
			if (is_string($value)) {
				$value = sanitize_text_field($value);
			} elseif (is_int($value)) {
				$value = intval($value);
			} elseif (is_float($value)) {
				$value = floatval($value);
			} elseif (is_bool($value)) {
				$value = (bool)$value;
			} else {
				$value = '';
			}
		});

		return wp_json_encode($data);
	}

	/**
	 * Get the suffix for asset files (.min in production, empty in debug).
	 *
	 * @return string
	 */
	private function get_asset_suffix() {
		return defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Clear survey caches when a survey is updated.
	 */
	private function clear_survey_caches() {
		delete_transient('litesurveys_active_surveys');
		delete_transient('litesurveys_has_active');
	}
}

// Initialize plugin
LSAPP_LiteSurveys::get_instance();
?>
