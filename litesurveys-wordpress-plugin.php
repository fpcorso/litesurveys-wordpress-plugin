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
define('LSAPP_PLUGIN_VERSION', '1.0.3');

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

		register_activation_hook(__FILE__, array($this, 'activatePlugin'));
		
		// Add Admin code
		add_action('admin_menu', array($this, 'addAdminMenu'));
		add_action('admin_post_save_survey', array($this, 'handleSaveSurvey'));
		add_action('admin_post_delete_survey', array($this, 'handleDeleteSurvey'));
		add_action('admin_notices', array($this, 'displayAdminNotices'));
		add_action('admin_enqueue_scripts', array($this, 'enqueueAdminAssets'));
		add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);

		// Add REST API endpoints
		add_action('rest_api_init', array($this, 'register_rest_routes'));

		// Add frontend script
		add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
	}

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
			deleted_at timestamp NULL DEFAULT NULL,
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
			deleted_at timestamp NULL DEFAULT NULL,
			PRIMARY KEY (id),
			FOREIGN KEY (survey_id) REFERENCES {$wpdb->prefix}litesurveys_surveys(id) ON DELETE CASCADE
		) $charset_collate;";

		// Create submissions table
		$sql_submissions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}litesurveys_submissions (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			survey_id bigint(20) NOT NULL,
			page varchar(255) DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			deleted_at timestamp NULL DEFAULT NULL,
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
			deleted_at timestamp NULL DEFAULT NULL,
			PRIMARY KEY (id),
			FOREIGN KEY (submission_id) REFERENCES {$wpdb->prefix}litesurveys_submissions(id) ON DELETE CASCADE,
			FOREIGN KEY (question_id) REFERENCES {$wpdb->prefix}litesurveys_questions(id) ON DELETE CASCADE
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql_surveys);
		dbDelta($sql_questions);
		dbDelta($sql_submissions);
		dbDelta($sql_responses);

		add_option('lsapp_litesurveys_version', '2.0.0');
	}

	public function enqueueAdminAssets($hook) {
		// Only load on our plugin pages
		if (strpos($hook, 'litesurveys') === false) {
			return;
		}

		// Get the current admin page action
		$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

		// Only load on edit/new survey pages
		if ($action === 'edit' || $action === 'new') {
			wp_enqueue_style(
				'litesurveys-admin-edit',
				plugin_dir_url(__FILE__) . 'admin/css/survey-edit.css',
				array(),
				LSAPP_PLUGIN_VERSION
			);

			wp_enqueue_script(
				'litesurveys-admin-edit',
				plugin_dir_url(__FILE__) . 'admin/js/survey-edit.js',
				array('jquery'),
				LSAPP_PLUGIN_VERSION,
				true
			);
		}
	}

	public function addAdminMenu() {
		add_menu_page(
			'LiteSurveys', 
			'LiteSurveys', 
			'manage_options',
			'LSAPP_litesurveys',
			array($this, 'renderAdminPage'),
			'dashicons-chart-bar',
			30
		);
	}

	public function renderAdminPage() {
		global $wpdb;

		$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
		
		switch ($action) {
			case 'view-responses':
				$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
				if (!$survey_id) {
					wp_die(__('Invalid survey ID.', 'litesurveys'));
				}

				// Get survey data with question
				$survey = $wpdb->get_row($wpdb->prepare(
					"SELECT s.*, q.content as question_content 
					FROM {$wpdb->prefix}litesurveys_surveys s 
					LEFT JOIN {$wpdb->prefix}litesurveys_questions q ON s.id = q.survey_id 
					WHERE s.id = %d AND s.deleted_at IS NULL",
					$survey_id
				));

				if (!$survey) {
					wp_die(__('Survey not found.', 'litesurveys'));
				}

				// Pagination settings
				$per_page = 20;
				$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
				$offset = ($current_page - 1) * $per_page;

				// Get total count for pagination
				$total_items = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) 
					FROM {$wpdb->prefix}litesurveys_submissions s
					WHERE s.survey_id = %d AND s.deleted_at IS NULL",
					$survey_id
				));

				// Get submissions with responses
				$submissions = $wpdb->get_results($wpdb->prepare(
					"SELECT s.created_at, s.page, r.content as response
					FROM {$wpdb->prefix}litesurveys_submissions s
					LEFT JOIN {$wpdb->prefix}litesurveys_responses r ON s.id = r.submission_id
					WHERE s.survey_id = %d AND s.deleted_at IS NULL
					ORDER BY s.created_at DESC
					LIMIT %d OFFSET %d",
					$survey_id, $per_page, $offset
				));

				// Calculate pagination values
				$total_pages = ceil($total_items / $per_page);

				include($this->plugin_path . 'views/admin/survey-submissions.php');  // Updated filename
				break;
			case 'edit':
			case 'new':
				// Get survey data if editing
				$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
				$survey = null;

				if ($survey_id) {
					$survey = $wpdb->get_row($wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}litesurveys_surveys WHERE id = %d AND deleted_at IS NULL",
						$survey_id
					));
					
					if ($survey) {
						$question = $wpdb->get_row($wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}litesurveys_questions WHERE survey_id = %d AND deleted_at IS NULL",
							$survey_id
						));
						
						if ($question) {
							$survey->question = $question;
							$survey->question->answers = json_decode($question->answers);
						}
						
						$survey->targeting_settings = json_decode($survey->targeting_settings);
						$survey->appearance_settings = json_decode($survey->appearance_settings);
					}
				}

				// Set defaults for new survey
				if (!$survey) {
					$survey = (object)[
						'name' => '',
						'active' => false,
						'submit_message' => 'Thanks! I appreciate you taking the time to respond.',
						'targeting_settings' => (object)[
							'targets' => (object)[
								'show' => 'all',
								'includes' => [],
								'excludes' => []
							],
							'trigger' => [(object)[
								'type' => 'auto',
								'auto_timing' => 5
							]]
						],
						'appearance_settings' => (object)[
							'horizontal_position' => 'right'
						],
						'question' => (object)[
							'type' => 'multiple-choice',
							'content' => '',
							'answers' => ['', '', '']
						]
					];
				}
				include($this->plugin_path . 'views/admin/survey-edit.php');
				break;

			default:
				$surveys = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}litesurveys_surveys WHERE deleted_at IS NULL ORDER BY created_at DESC"
				);
				include($this->plugin_path . 'views/admin/surveys-admin.php');
				break;
		}
	}

	public function handleSaveSurvey() {
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.', 'litesurveys'));
		}
	
		check_admin_referer('save_survey', 'survey_nonce');
		
		global $wpdb;
		
		$survey_id = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : 0;
		$save_type = isset($_POST['save_type']) ? sanitize_text_field($_POST['save_type']) : 'draft';
		
		try {
			// Validate required fields
			if (empty($_POST['survey_name'])) {
				throw new Exception(__('Survey name is required.', 'litesurveys'));
			}
	
			if (empty($_POST['question_content'])) {
				throw new Exception(__('Survey question is required.', 'litesurveys'));
			}
	
			// Prepare survey data
			$survey_data = array(
				'name' => sanitize_text_field($_POST['survey_name']),
				'submit_message' => sanitize_textarea_field($_POST['submit_message']),
				'active' => ($save_type === 'publish'),
				'targeting_settings' => json_encode([
					'targets' => [
						'show' => sanitize_text_field($_POST['targeting_show']),
						'includes' => isset($_POST['includes']) ? array_map('sanitize_text_field', $_POST['includes']) : [],
						'excludes' => isset($_POST['excludes']) ? array_map('sanitize_text_field', $_POST['excludes']) : []
					],
					'trigger' => [[
						'type' => sanitize_text_field($_POST['trigger_type']),
						'auto_timing' => intval($_POST['auto_timing'])
					]]
				]),
				'appearance_settings' => json_encode([
					'horizontal_position' => sanitize_text_field($_POST['horizontal_position'])
				])
			);
	
			// Prepare question data
			$question_data = array(
				'type' => sanitize_text_field($_POST['question_type']),
				'content' => sanitize_textarea_field($_POST['question_content']),
				'answers' => $_POST['question_type'] === 'multiple-choice' ? 
							json_encode(array_map('sanitize_text_field', array_filter($_POST['answers']))) : 
							json_encode([])
			);
	
			// Start transaction
			$wpdb->query('START TRANSACTION');
	
			if ($survey_id) {
				// Update existing survey
				$result = $wpdb->update(
					$wpdb->prefix . 'litesurveys_surveys',
					$survey_data,
					['id' => $survey_id]
				);
	
				if ($result === false) {
					throw new Exception(__('Failed to update survey.', 'litesurveys'));
				}
	
				// Update or insert question
				$existing_question = $wpdb->get_row($wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}litesurveys_questions WHERE survey_id = %d AND deleted_at IS NULL",
					$survey_id
				));
	
				if ($existing_question) {
					$result = $wpdb->update(
						$wpdb->prefix . 'litesurveys_questions',
						$question_data,
						['id' => $existing_question->id]
					);
					if ($result === false) {
						throw new Exception(__('Failed to update survey question.', 'litesurveys'));
					}
				} else {
					$question_data['survey_id'] = $survey_id;
					$result = $wpdb->insert($wpdb->prefix . 'litesurveys_questions', $question_data);
					if (!$result) {
						throw new Exception(__('Failed to create survey question.', 'litesurveys'));
					}
				}
			} else {
				// Insert new survey
				$result = $wpdb->insert($wpdb->prefix . 'litesurveys_surveys', $survey_data);
				if (!$result) {
					throw new Exception(__('Failed to create survey.', 'litesurveys'));
				}
				$survey_id = $wpdb->insert_id;
	
				// Insert question
				$question_data['survey_id'] = $survey_id;
				$result = $wpdb->insert($wpdb->prefix . 'litesurveys_questions', $question_data);
				if (!$result) {
					throw new Exception(__('Failed to create survey question.', 'litesurveys'));
				}
			}
	
			// Commit transaction
			$wpdb->query('COMMIT');
	
			// Build redirect arguments
			$redirect_args = array(
				'page' => 'LSAPP_litesurveys',
				'action' => 'edit',
				'id' => $survey_id,
				'message' => $save_type === 'publish' ? 'survey-published' : 'survey-saved'
			);
	
			// Redirect with success message
			wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
			exit;
	
		} catch (Exception $e) {
			// Rollback transaction
			$wpdb->query('ROLLBACK');
	
			wp_safe_redirect(add_query_arg(
				array(
					'page' => 'LSAPP_litesurveys',
					'action' => $survey_id ? 'edit' : 'new',
					'id' => $survey_id,
					'message' => 'error',
					'error' => urlencode($e->getMessage())
				),
				admin_url('admin.php')
			));
			exit;
		}
	}

	public function handleDeleteSurvey() {
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.', 'litesurveys'));
		}

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

	public function displayAdminNotices() {
		if (!isset($_GET['page']) || $_GET['page'] !== 'LSAPP_litesurveys') {
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
				case 'error':
					$class .= 'notice-error';
					$message = isset($_GET['error']) ? 
						urldecode($_GET['error']) : 
						__('An error occurred while processing your request.', 'litesurveys');
					break;
			}

			if ($message) {
				printf(
					'<div class="%1$s is-dismissible"><p>%2$s</p></div>',
					esc_attr($class),
					esc_html($message)
				);
			}
		}
	}

	public function register_rest_routes() {
		register_rest_route('litesurveys/v1', '/surveys', array(
			'methods' => 'GET',
			'callback' => array($this, 'get_active_surveys'),
			'permission_callback' => '__return_true'
		));
	
		register_rest_route('litesurveys/v1', '/surveys/(?P<id>\d+)/submissions', array(
			'methods' => 'POST',
			'callback' => array($this, 'save_submission'),
			'permission_callback' => '__return_true'
		));
	}
	
	public function get_active_surveys(WP_REST_Request $request) {
		global $wpdb;
		
		// Get active survey with its question
		$survey = $wpdb->get_row(
			"SELECT s.*, 
					q.id as question_id, 
					q.type as question_type, 
					q.content as question_content,
					q.answers as question_answers
			 FROM {$wpdb->prefix}litesurveys_surveys s
			 LEFT JOIN {$wpdb->prefix}litesurveys_questions q ON s.id = q.survey_id
			 WHERE s.active = 1 
			 AND s.deleted_at IS NULL 
			 AND q.deleted_at IS NULL
			 LIMIT 1"
		);
		
		if (!$survey) {
			return new WP_REST_Response([], 200);
		}
		
		// Format the response to match the expected schema
		$response = array(
			'id' => $survey->id,
			'name' => $survey->name,
			'active' => (bool)$survey->active,
			'submit_message' => $survey->submit_message,
			'targeting_settings' => json_decode($survey->targeting_settings),
			'appearance_settings' => json_decode($survey->appearance_settings),
			'questions' => array(
				array(
					'id' => $survey->question_id,
					'type' => $survey->question_type,
					'content' => $survey->question_content,
					'answers' => json_decode($survey->question_answers)
				)
			)
		);
		
		return new WP_REST_Response([$response], 200);
	}
	
	public function save_submission(WP_REST_Request $request) {
		global $wpdb;
		
		$survey_id = $request->get_param('id');
		$body = json_decode($request->get_body(), true);
		
		try {
			$wpdb->query('START TRANSACTION');
			
			// Create submission record
			$page_path = $this->get_path_from_url($body['page']);
			$wpdb->insert(
				$wpdb->prefix . 'litesurveys_submissions',
				array(
					'survey_id' => $survey_id,
					'page' => $page_path
				)
			);
			
			$submission_id = $wpdb->insert_id;
			
			// Create response records
			foreach ($body['responses'] as $response) {
				$wpdb->insert(
					$wpdb->prefix . 'litesurveys_responses',
					array(
						'submission_id' => $submission_id,
						'question_id' => $response['question_id'],
						'content' => $response['content']
					)
				);
			}
			
			$wpdb->query('COMMIT');
			return new WP_REST_Response(['status' => 'success'], 200);
			
		} catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			return new WP_REST_Response(['status' => 'error'], 500);
		}
	}

	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'litesurveys-frontend',
			plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
			array(),
			LSAPP_PLUGIN_VERSION
		);
		
		wp_enqueue_script(
			'litesurveys-frontend',
			plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
			array(),
			LSAPP_PLUGIN_VERSION,
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
		if (in_array($plugin_file, $plugin_files)) {
			$settings_url = sprintf( '<a href="%s">Settings</a>', esc_url( admin_url( 'options-general.php?page=LSAPP_litesurveys' ) ) );
			$actions = array_merge( ['litesurveys_settings' => $settings_url], $actions) ;
		}
		return $actions;
	}

	/**
	 * Gets the path from a URL.
	 *
	 * @param string $url The full URL
	 * @return string The path component of the URL
	 */
	private function get_path_from_url($url) {
		$path = parse_url($url, PHP_URL_PATH);
		return empty($path) ? '/' : $path;
	}
}

// Initialize plugin
LSAPP_LiteSurveys::getInstance();
?>
