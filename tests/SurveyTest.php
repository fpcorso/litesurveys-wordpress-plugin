<?php
class SurveyTest extends WP_UnitTestCase {
	private $plugin;

	private static $admin_id;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		
		// Create an admin user once for all tests
		self::$admin_id = self::factory()->user->create(array(
			'role' => 'administrator'
		));
	}

	public function setUp(): void {
		parent::setUp();
		
		// Get plugin instance
		$this->plugin = LSAPP_LiteSurveys::get_instance();
		
		// Set current user to admin for each test
		wp_set_current_user(self::$admin_id);
	}

	public static function tearDownAfterClass(): void {
		if (self::$admin_id) {
			wp_delete_user(self::$admin_id);
		}
		parent::tearDownAfterClass();
	}

	public function test_create_survey() {
		// Set up test data
		$_POST = array(
			'survey_name' => 'Test Survey',
			'question_type' => 'multiple-choice',
			'question_content' => 'Test Question',
			'answers' => array('Answer 1', 'Answer 2'),
			'submit_message' => 'Thank you!',
			'targeting_show' => 'all',
			'trigger_type' => 'auto',
			'auto_timing' => 5,
			'horizontal_position' => 'right',
			'save_type' => 'publish'
		);

		// Set up valid nonce
		$_REQUEST['_wpnonce'] = $_POST['survey_nonce'] = wp_create_nonce('save_survey');
		$_POST['action'] = 'save_survey';

		// Call the handler
		try {
			$this->plugin->handle_save_survey();
		} catch (WPDieException $e) {
			// We expect a wp_redirect() which throws WPDieException in tests
			// Verify it was a success redirect
			$this->assertStringContainsString('survey-published', $e->getMessage());
		}

		// Get the created survey from the database
		global $wpdb;
		$survey = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}litesurveys_surveys ORDER BY id DESC LIMIT 1"
		);

		$this->assertNotNull($survey);
		$this->assertEquals('Test Survey', $survey->name);
		$this->assertEquals(1, $survey->active);

		// Verify question creation
		$question = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}litesurveys_questions WHERE survey_id = %d",
				$survey->id
			)
		);

		$this->assertNotNull($question);
		$this->assertEquals('Test Question', $question->content);
		$this->assertEquals('multiple-choice', $question->type);
		
		$answers = json_decode($question->answers);
		$this->assertCount(2, $answers);
		$this->assertEquals('Answer 1', $answers[0]);
		$this->assertEquals('Answer 2', $answers[1]);
	}

	public function test_save_survey_validation() {
		$_POST = array(
			'survey_name' => '', // Empty name should fail
			'question_type' => 'multiple-choice',
			'question_content' => 'Test Question',
			'answers' => array('Answer 1'),  // Only one answer should fail
			'submit_message' => 'Thank you!',
			'targeting_show' => 'all',
			'trigger_type' => 'auto',
			'auto_timing' => 5,
			'horizontal_position' => 'right',
			'save_type' => 'publish'
		);

		$_REQUEST['_wpnonce'] = $_POST['survey_nonce'] = wp_create_nonce('save_survey');
		$_POST['action'] = 'save_survey';

		try {
			$this->plugin->handle_save_survey();
			$this->fail('Expected validation to fail');
		} catch (WPDieException $e) {
			$this->assertStringContainsString('error', $e->getMessage());
		}
	}

	public function test_save_survey_as_draft() {
		$_POST = array(
			'survey_name' => 'Draft Survey',
			'question_type' => 'open-answer',
			'question_content' => 'Test Question',
			'submit_message' => 'Thank you!',
			'targeting_show' => 'all',
			'trigger_type' => 'auto',
			'auto_timing' => 5,
			'horizontal_position' => 'right',
			'save_type' => 'draft'
		);

		$_REQUEST['_wpnonce'] = $_POST['survey_nonce'] = wp_create_nonce('save_survey');
		$_POST['action'] = 'save_survey';

		try {
			$this->plugin->handle_save_survey();
		} catch (WPDieException $e) {
			$this->assertStringContainsString('survey-saved', $e->getMessage());
		}

		global $wpdb;
		$survey = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}litesurveys_surveys ORDER BY id DESC LIMIT 1"
		);

		$this->assertEquals(0, $survey->active);
	}

	public function test_delete_survey() {
		// First create a survey
		$survey_id = $this->create_test_survey();
		
		// Set up delete request
		$_REQUEST['_wpnonce'] = wp_create_nonce('delete-survey_' . $survey_id);
		$_REQUEST['action'] = 'delete_survey';
		$_REQUEST['id'] = $survey_id;

		try {
			$this->plugin->handle_delete_survey();
		} catch (WPDieException $e) {
			$this->assertStringContainsString('survey-deleted', $e->getMessage());
		}

		global $wpdb;
		$survey = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}litesurveys_surveys WHERE id = %d",
				$survey_id
			)
		);

		$this->assertNotNull($survey->deleted_at);
	}

	public function test_rest_api_submission() {
		$survey_id = $this->create_test_survey();
		
		$request = new WP_REST_Request('POST', "/litesurveys/v1/surveys/{$survey_id}/submissions");
		$request->set_body(json_encode([
			'responses' => [
				[
					'question_id' => 1,
					'content' => 'Answer 1'
				]
			],
			'page' => 'https://example.com/test-page'
		]));

		$rest_api = $this->plugin->get_rest_api();
		$response = $rest_api->save_submission($request);
		
		$this->assertEquals(200, $response->get_status());

		global $wpdb;
		$submission = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}litesurveys_submissions ORDER BY id DESC LIMIT 1"
		);
		
		$this->assertEquals('/test-page', $submission->page);
	}

	public function test_sanitize_json_data() {
		$reflection = new ReflectionClass( $this->plugin );
		$method = $reflection->getMethod( 'sanitize_json_data' );
		$method->setAccessible( true );

		// Test with valid data
		$input = array(
			'string' => 'Test String <script>alert("xss")</script>',
			'int' => '42',
			'float' => '3.14',
			'bool' => true,
			'nested' => array(
				'key' => 'value <script>alert("xss")</script>'
			)
		);

		$json = $method->invoke( $this->plugin, $input );
		$output = json_decode( $json, true );

		// Updated expectation to match new sanitization
		$this->assertEquals( 'Test String', $output['string'] );
		$this->assertEquals( 42, $output['int'] );
		$this->assertEquals( 3.14, $output['float'] );
		$this->assertTrue( $output['bool'] );
		$this->assertEquals( 'value', $output['nested']['key'] );
	}

	public function test_get_active_surveys() {
		// Create a test survey
		$survey_id = $this->create_test_survey();

		// Create WP_REST_Request mock
		$request = new WP_REST_Request( 'GET', '/litesurveys/v1/surveys' );

		// Get REST API instance from plugin
		$rest_api = $this->plugin->get_rest_api();

		// Get response
		$response = $rest_api->get_active_surveys( $request );
		$data = $response->get_data();

		// Assert response structure
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );
		
		$survey = $data[0];
		$this->assertArrayHasKey( 'id', $survey );
		$this->assertArrayHasKey( 'name', $survey );
		$this->assertArrayHasKey( 'questions', $survey );
		$this->assertTrue( $survey['active'] );
	}

	private function create_test_survey() {
		global $wpdb;

		// Insert test survey
		$wpdb->insert(
			$wpdb->prefix . 'litesurveys_surveys',
			array(
				'name' => 'Test Survey',
				'active' => 1,
				'submit_message' => 'Thank you!',
				'targeting_settings' => json_encode( array( 'show' => 'all' ) ),
				'appearance_settings' => json_encode( array( 'position' => 'right' ) )
			)
		);

		$survey_id = $wpdb->insert_id;

		// Insert test question
		$wpdb->insert(
			$wpdb->prefix . 'litesurveys_questions',
			array(
				'survey_id' => $survey_id,
				'type' => 'multiple-choice',
				'content' => 'Test Question',
				'answers' => json_encode( array( 'Answer 1', 'Answer 2' ) )
			)
		);

		return $survey_id;
	}
}