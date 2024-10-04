<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

use AskNews\AskNewsSDK;

/**
 * The comments class.
 *
 * @since TBD
 */
class Mai_AskNews_AskTheBot {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Run the hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'wp_enqueue_scripts',                        [ $this, 'enqueue_scripts' ] );
		add_action( 'genesis_before_entry_content',              [ $this, 'add_ask_the_bot' ] );
		add_action( 'admin_post_pm_askthebot_submission',        [ $this, 'handle_submission_single' ] );
		add_action( 'admin_post_nopriv_pm_askthebot_submission', [ $this, 'handle_submission_single' ] );
		add_action( 'wp_ajax_pm_askthebot_submission',           [ $this, 'handle_submission_single' ] );
		add_action( 'wp_ajax_nopriv_pm_askthebot_submission',    [ $this, 'handle_submission_single' ] );
		// add_action( 'admin_post_pm_askthebot_submission',        [ $this, 'handle_submission_stream' ] );
		// add_action( 'admin_post_nopriv_pm_askthebot_submission', [ $this, 'handle_submission_stream' ] );
		// add_action( 'wp_ajax_pm_askthebot_submission',           [ $this, 'handle_submission_stream' ] );
		// add_action( 'wp_ajax_nopriv_pm_askthebot_submission',    [ $this, 'handle_submission_stream' ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function enqueue_scripts() {
		// Bail if not the ask the bot page.
		if ( ! is_page( 'ask-the-bot' ) ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style( 'mai-askthebot', maiasknews_get_file_url( 'mai-askthebot', 'css', false ), [], maiasknews_get_file_version( 'mai-askthebot', 'css', false ) );

		// Enqueue JS.
		wp_enqueue_script( 'mai-askthebot', maiasknews_get_file_url( 'mai-askthebot', 'js', false ), [], maiasknews_get_file_version( 'mai-askthebot', 'js', false ), true );
		wp_localize_script( 'mai-askthebot', 'maiAskTheBotVars', [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		] );
	}

	/**
	 * Add the ask the bot form.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function add_ask_the_bot() {
		// Bail if not the ask the bot page.
		if ( ! is_page( 'ask-the-bot' ) ) {
			return;
		}

		// Output the chat placeholder.
		printf( '<div id="askthebot-chat" class="askthebot-chat"></div>' );

		// Output the form.
		printf( '<form id="askthebot-form" class="askthebot-form" action="%s" method="post">', esc_url( admin_url('admin-post.php') ) );
		?>
			<p>
				<label for="askthebot-question"><?php _e( 'Your question', 'mai-asknews' ); ?></label>
				<textarea name="askthebot-question" id="askthebot-question" placeholder="Ask the bot anything" required></textarea>
			</p>
			<button type="submit"><?php _e( 'Ask the Bot', 'mai-asknews' ); ?></button>
			<input type="hidden" name="action" value="pm_askthebot_submission">
			<input type="hidden" name="action" value="pm_askthebot_submission">
			<?php echo wp_nonce_field( 'pm_askthebot_nonce', '_wpnonce', true, false ); ?>
		</form>
		<?php
	}

	/**
	 * Handles the vote submission.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	function handle_submission_single() {
		// Verify nonce for security.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'pm_askthebot_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Vote submission security check failed.', 'mai-asknews' ) ] );
			exit;
		}

		// Get question.
		$question = isset( $_POST['askthebot-question'] ) ? sanitize_textarea_field( $_POST['askthebot-question'] ) : '';

		// Bail if no question.
		if ( ! $question ) {
			wp_send_json_error( [ 'message' => __( 'No question asked.', 'mai-asknews' ) ] );
			exit;
		}

		// Get the client ID and secret.
		$client_id     = defined( 'ASKNEWS_CLIENT_ID' ) ? ASKNEWS_CLIENT_ID : '';
		$client_secret = defined( 'ASKNEWS_CLIENT_SECRET' ) ? ASKNEWS_CLIENT_SECRET : '';

		// Bail if no client ID or secret.
		if ( ! $client_id || ! $client_secret ) {
			wp_send_json_error( [ 'message' => __( 'Missing API keys.', 'mai-asknews' ) ] );
			exit;
		}

		// Set up the SDK.
		$config = new AskNews\Configuration([
			'clientId'     => $client_id,
			'clientSecret' => $client_secret,
			'scopes'       => [ 'chat' ],
		]);

		// Create an instance of the ChatApi and request classes.
		$chat_api = new AskNews\Api\ChatApi( new GuzzleHttp\Client(), $config );
		$request  = new \AskNews\Model\CreateChatCompletionRequest([
			'model'    => 'gpt-4o-mini', // or your preferred model
			'messages' => [
				[
					'role'    => 'user',
					'content' => $question,
				]
			],
			'stream' => false,
		]);

		// Send the request.
		try {
			$response = $chat_api->getChatCompletions($request);
			$chat     = ! is_null( $response->getChoices()[0]->getMessage()->getContent() ) ? $response->getChoices()[0]->getMessage()->getContent() : '';

			// Send success response.
			wp_send_json_success( [ 'message' => $chat ] );
			exit;
		} catch ( Exception $e ) {
			$chat = 'Exception when calling ChatApi->getChatCompletions: ' . $e->getMessage();

			// Send error response.
			wp_send_json_error( [ 'message' => $chat ] );
			exit;
		}

		// Send success response.
		wp_send_json_success( [] );
		exit;
	}

	/**
	 * Handles the vote submission.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	function handle_submission_stream() {
		// Verify nonce for security
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'pm_askthebot_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'mai-asknews' ) ] );
			exit;
		}

		// Verify user capabilities (if applicable)
		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'mai-asknews' ) ] );
			exit;
		}

		// Get the question
		$question = isset( $_POST['askthebot-question'] ) ? sanitize_text_field( $_POST['askthebot-question'] ) : '';

		// Bail if no question is provided
		if ( empty($question) ) {
			wp_send_json_error( [ 'message' => __( 'No question provided.', 'mai-asknews' ) ] );
			exit;
		}

		// Get the client ID and secret.
		$client_id     = defined( 'ASKNEWS_CLIENT_ID' ) ? ASKNEWS_CLIENT_ID : '';
		$client_secret = defined( 'ASKNEWS_CLIENT_SECRET' ) ? ASKNEWS_CLIENT_SECRET : '';

		// Bail if no client ID or secret.
		if ( ! $client_id || ! $client_secret ) {
			wp_send_json_error( [ 'message' => __( 'Missing API keys.', 'mai-asknews' ) ] );
			exit;
		}

		try {
			// Set up the SDK.
			$config = new AskNews\Configuration([
				'clientId'     => $client_id,
				'clientSecret' => $client_secret,
				'scopes'       => [ 'chat' ],
			]);

			// Call the AskNews API (adjust according to your actual API call setup)
			$chat_api = new AskNews\Api\ChatApi(new GuzzleHttp\Client(), $config);
			$ask_request  = new \AskNews\Model\CreateChatCompletionRequest([
				'model'    => 'gpt-4o-mini',
				'messages' => [
					[
						'role'    => 'user',
						'content' => $question,
					],
				],
				'stream'   => true,
			]);

			// Make the API request to get chat completions
			$response = $chat_api->getChatCompletions($ask_request);

			// Stream the response in chunks
			foreach ($response->getChoices() as $choice) {
				// Send each chunk as JSON
				$chunk = !is_null($choice->getMessage()->getContent()) ? $choice->getMessage()->getContent() : '';
			}
		} catch (Exception $e) {
			// Handle any errors
			$error_message = 'Error when calling ChatApi->getChatCompletions: ' . $e->getMessage();

			wp_send_json_error( [ 'message' => $error_message ] );
			exit; // Ensure the script ends here
		}

		// Send success response
		wp_send_json_success( [] );
		exit;
	}
}