<?php
/**
 * REST API functionality for LiteSurveys
 *
 * @package LiteSurveys
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class to handle all REST API endpoints.
 *
 * @since 1.0.0
 */
class LSAPP_REST_API {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'litesurveys/v1';

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/surveys',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_active_surveys' ),
				'permission_callback' => '__return_true', // Public endpoint is fine as it only returns active surveys.
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/surveys/(?P<id>\d+)/submissions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_submission' ),
				'permission_callback' => '__return_true', // Public endpoint needed for frontend submissions.
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Get active surveys.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function get_active_surveys( $request ) {
		$cache_key = 'litesurveys_active_surveys';
		$surveys   = get_transient( $cache_key );

		if ( false === $surveys ) {
			global $wpdb;

			$surveys = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT s.*, 
							q.id as question_id, 
							q.type as question_type, 
							q.content as question_content,
							q.answers as question_answers
					FROM {$wpdb->prefix}litesurveys_surveys s
					LEFT JOIN {$wpdb->prefix}litesurveys_questions q ON s.id = q.survey_id
					WHERE s.active = %d 
					AND s.deleted_at IS NULL 
					AND q.deleted_at IS NULL
					ORDER BY s.created_at DESC",
					1
				)
			);

			if ( empty( $surveys ) ) {
				return new WP_REST_Response( array(), 200 );
			}

			// Safely encode response data.
			$response = array_map(
				function( $survey ) {
					return array(
						'id'                 => (int) $survey->id,
						'name'               => sanitize_text_field( $survey->name ),
						'active'             => (bool) $survey->active,
						'submit_message'     => wp_kses_post( $survey->submit_message ),
						'targeting_settings' => json_decode( $survey->targeting_settings ),
						'appearance_settings' => json_decode( $survey->appearance_settings ),
						'questions'          => array(
							array(
								'id'      => (int) $survey->question_id,
								'type'    => sanitize_text_field( $survey->question_type ),
								'content' => wp_kses_post( $survey->question_content ),
								'answers' => json_decode( $survey->question_answers ),
							),
						),
					);
				},
				$surveys
			);

			// Cache the formatted data.
			set_transient( $cache_key, $surveys, LSAPP_LiteSurveys::CACHE_TIME );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Save survey submission.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function save_submission( $request ) {
		global $wpdb;

		$survey_id = (int) $request->get_param( 'id' );
		$body      = json_decode( $request->get_body(), true );

		// Validate required fields.
		if ( ! isset( $body['responses'] ) || ! is_array( $body['responses'] ) ||
			empty( $body['responses'] ) || ! isset( $body['page'] ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => 'Invalid submission data',
				),
				400
			);
		}

		try {
			// Validate survey exists and is active.
			$survey = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT q.id as question_id, q.type, q.answers 
					FROM {$wpdb->prefix}litesurveys_surveys s
					JOIN {$wpdb->prefix}litesurveys_questions q ON s.id = q.survey_id
					WHERE s.id = %d AND s.active = 1 
					AND s.deleted_at IS NULL AND q.deleted_at IS NULL",
					$survey_id
				)
			);

			if ( ! $survey ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => 'Survey not found or inactive',
					),
					404
				);
			}

			// Validate response data.
			$response = $body['responses'][0];
			if ( ! isset( $response['question_id'] ) || ! isset( $response['content'] ) ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => 'Invalid response data',
					),
					400
				);
			}

			// Validate question ID matches.
			if ( (int) $response['question_id'] !== (int) $survey->question_id ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => 'Invalid question ID',
					),
					400
				);
			}

			// Validate response content.
			$content = sanitize_textarea_field( $response['content'] );
			if ( 'multiple-choice' === $survey->type ) {
				$valid_answers = json_decode( $survey->answers, true );
				if ( ! in_array( $content, $valid_answers, true ) ) {
					return new WP_REST_Response( array( 'status' => 'success' ), 200 );
				}
			} else {
				if ( empty( trim( $content ) ) ) {
					return new WP_REST_Response( array( 'status' => 'success' ), 200 );
				}
			}

			// Process submission with validated data.
			$wpdb->query( 'START TRANSACTION' );

			$page_path = $this->get_path_from_url( esc_url_raw( $body['page'] ) );
			$wpdb->insert(
				$wpdb->prefix . 'litesurveys_submissions',
				array(
					'survey_id' => $survey_id,
					'page'      => $page_path,
				),
				array( '%d', '%s' )
			);

			$submission_id = $wpdb->insert_id;

			$wpdb->insert(
				$wpdb->prefix . 'litesurveys_responses',
				array(
					'submission_id' => $submission_id,
					'question_id'   => (int) $response['question_id'],
					'content'      => $content,
				),
				array( '%d', '%d', '%s' )
			);

			$wpdb->query( 'COMMIT' );
			return new WP_REST_Response( array( 'status' => 'success' ), 200 );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => 'Server error',
				),
				500
			);
		}
	}

	/**
	 * Gets the path from a URL.
	 *
	 * @since 1.0.0
	 * @param string $url The full URL.
	 * @return string The path component of the URL.
	 */
	private function get_path_from_url( $url ) {
		$path = parse_url( $url, PHP_URL_PATH );
		return empty( $path ) ? '/' : $path;
	}
}