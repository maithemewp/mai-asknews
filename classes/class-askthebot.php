<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

use AskNews\AskNewsSDK;
use League\CommonMark\CommonMarkConverter;

/**
 * The comments class.
 *
 * @since 0.9.0
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
	 * @since 0.9.0
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'template_redirect',                         [ $this, 'maybe_redirect' ] );
		add_action( 'wp_enqueue_scripts',                        [ $this, 'enqueue_scripts' ] );
		add_filter( 'mai_template-parts_config',                 [ $this, 'add_ccas' ] );
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
	 * Maybe redirect.
	 *
	 * @since 0.9.0
	 *
	 * @return void
	 */
	function maybe_redirect() {
		// If not a single askthebot post.
		if ( ! is_singular( 'askthebot' ) ) {
			return;
		}

		// If admininistrator.
		if ( current_user_can( 'administrator' ) ) {
			return;
		}

		// Redirect to the /dashboard/ page.
		wp_safe_redirect( home_url( '/dashboard/' ) );
		exit;
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since 0.9.0
	 *
	 * @return void
	 */
	function enqueue_scripts() {
		$form_page = is_page( 'ask-the-bot' );
		$singular  = is_singular( 'askthebot' );

		// Bail if not a page we want.
		if ( ! ( $form_page || $singular ) ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style( 'mai-askthebot', maiasknews_get_file_url( 'mai-askthebot', 'css', false ), [], maiasknews_get_file_version( 'mai-askthebot', 'css', false ) );

		// Bail if not form page with access.
		if ( ! $form_page && maiasknews_has_elite_membership() ) {
			return;
		}

		// Enqueue JS.
		wp_enqueue_script( 'mai-askthebot', maiasknews_get_file_url( 'mai-askthebot', 'js', false ), [], maiasknews_get_file_version( 'mai-askthebot', 'js', false ), true );
		wp_localize_script( 'mai-askthebot', 'maiAskTheBotVars', [
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'userAvatar' => get_avatar( get_current_user_id(), 64 ),
		] );
	}

	/**
	 * Register CCAs.
	 *
	 * @since 0.9.0
	 *
	 * @param array $ccas The current CCAs.
	 *
	 * @return array
	 */
	function add_ccas( $ccas ) {
		$ccas['ask-the-bot-promo'] = [
			'hook'     => 'genesis_entry_content',
			'priority' => 15,
			'condition' => function() {
				return is_page( 'ask-the-bot' ) && ! maiasknews_has_elite_membership();
			}
		];

		return $ccas;
	}

	/**
	 * Add the ask the bot chat log and form.
	 *
	 * @since 0.9.0
	 *
	 * @return void
	 */
	function add_ask_the_bot() {
		// Bail if not the ask the bot page.
		if ( ! ( is_page( 'ask-the-bot' ) && maiasknews_has_elite_membership() ) ) {
			return;
		}

		// echo '<div class="askthebot-container">';
			// echo '<div class="askthebot-chats">';
			// 	echo $this->get_chats_list_html();
			// echo '</div>';
			echo '<div id="askthebot-chat" class="askthebot-chat">';
				echo $this->get_chat_html();
				echo $this->get_chat_form();
			echo '</div>';
		// echo '</div>';
	}

	function get_chats_list_html() {

	}

	/**
	 * Get the chat HTML.
	 *
	 * @since 0.9.0
	 *
	 * @return string
	 */
	function get_chat_html() {
		$chat  = [];
		$args  = [
			'post_type'              => 'askthebot',
			'post_status'            => 'publish',
			'posts_per_page'         => 4,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'author'                 => get_current_user_id(),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		// Get the chat messages.
		$query = new WP_Query( $args );

		// Bot avatar.
		// $avatar_bot  = get_avatar( maiasknews_get_bot_user_id(), 64 );
		$avatar_user = get_avatar( get_current_user_id(), 64 );

		// Loop through the chat messages.
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) : $query->the_post();
				// Get the question and answer.
				$question = get_the_title();
				$answer   = get_the_content();

				// Add the chat messages in reverse.
				$chat[] = sprintf( '<div class="askthebot__message askthebot__bot">%s</div>', $answer );
				$chat[] = sprintf( '<div class="askthebot__message askthebot__user">%s%s</div>', $question, $avatar_user );
			endwhile;
		}
		wp_reset_postdata();

		// Reverse the chat messages.
		$chat = array_reverse( $chat );

		// Return the chat messages.
		return implode( '', $chat );
	}

	/**
	 * Get the converted HTML.
	 *
	 * @since 0.9.0
	 *
	 * @param string $content The content to convert.
	 *
	 * @return string
	 */
	function get_converted_html( $content ) {
		// Set up the markdown converter.
		$converter = new CommonMarkConverter([
			'html_input'         => 'strip',
			'allow_unsafe_links' => false,
		]);

		// Convert markdown and dates.
		$content = $converter->convert( $content );
		$content = preg_replace_callback('/Published: (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\+\d{2}:\d{2})/', function($matches) {
			$date = new DateTime( $matches[1] );
			return $date->format('M j, Y @ g:i a');
		}, $content );

		// Set up tag processor.
		$tags = new WP_HTML_Tag_Processor( $content );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'a' ] ) ) {
			$tags->set_attribute( 'target', '_blank' );
			$tags->set_attribute( 'rel', 'noopener' );
		}

		// Get the updated HTML.
		$content = $tags->get_updated_html();

		return $content;
	}

	/**
	 * Get the chat form.
	 *
	 * @since 0.9.0
	 *
	 * @return string
	 */
	function get_chat_form() {
		$html  = '';
		$html .= '<div id="chat-bottom"></div>';
		$html .= sprintf( '<form id="askthebot-form" class="askthebot-form" action="%s" method="post">', esc_url( admin_url('admin-post.php') ) );
			$html .= sprintf( '<p><button id="chat-down" class="button button-secondary button-small" style="display:block;margin-inline:auto;">%s</button></p>', __( 'Scroll to bottom ↓', 'mai-asknews' ) );
			$html .= '<p><textarea name="askthebot-question" id="askthebot-question" placeholder="Ask the bot anything" required></textarea></p>';
			$html .= sprintf( '<button type="submit" class="button button-ajax"><span class="button-text">%s</span></button>', __( 'Ask the Bot', 'mai-asknews' ) );
			$html .= '<input type="hidden" name="action" value="pm_askthebot_submission">';
			$html .= '<input type="hidden" name="action" value="pm_askthebot_submission">';
			$html .= wp_nonce_field( 'pm_askthebot_nonce', '_wpnonce', true, false );
		$html .= '</form>';

		return $html;
	}

	/**
	 * Handles the vote submission.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	function handle_submission_single() {
		$ajax = wp_doing_ajax();

		// Verify nonce for security.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'pm_askthebot_nonce' ) ) {
			$message = __( 'Vote submission security check failed.', 'mai-asknews' );

			if ( $ajax ) {
				wp_send_json_error( [ 'message' => $message ] );
				exit;
			}

			wp_die( $message );
		}

		// Get question.
		$question = isset( $_POST['askthebot-question'] ) ? sanitize_textarea_field( $_POST['askthebot-question'] ) : '';

		// Bail if no question.
		if ( ! $question ) {
			$message = __( 'No question asked.', 'mai-asknews' );

			if ( $ajax ) {
				wp_send_json_error( [ 'message' => $message ] );
				exit;
			}

			wp_die( $message );
		}

		// Get the client ID and secret.
		$client_id     = defined( 'ASKNEWS_CLIENT_ID' ) ? ASKNEWS_CLIENT_ID : '';
		$client_secret = defined( 'ASKNEWS_CLIENT_SECRET' ) ? ASKNEWS_CLIENT_SECRET : '';

		// Bail if no client ID or secret.
		if ( ! $client_id || ! $client_secret ) {
			$message = __( 'Missing API keys.', 'mai-asknews' );

			if ( $ajax ) {
				wp_send_json_error( [ 'message' => $message ] );
				exit;
			}

			wp_die( $message );
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
			// 'model'             => 'gpt-4o-mini',
			'model'             => 'claude-3-5-sonnet-20240620',
			'append_references' => false,
			'asknews_watermark' => false,
			'inline_citations'  => 'markdown_link',
			'messages'          => [
				[
					'role'    => 'user',
					'content' => $question,
				]
			],
			'stream' => false,
		]);

		// Send the request.
		try {
			// Get current logged in user.
			$user     = wp_get_current_user();
			$chat_id  = 0;

			// If user.
			if ( $user->ID ) {
				// Create the chat post.
				$chat_id = wp_insert_post(
					[
						'post_type'   => 'askthebot',
						'post_status' => 'publish',
						'post_author' => $user->ID,
						'post_title'  => $question,
					]
				);

				// Maybe return error.
				if ( is_wp_error( $chat_id ) ) {
					if ( $ajax ) {
						wp_send_json_error( [ 'message' => $chat_id->get_error_message() ] );
						exit;
					}

					wp_die( $chat_id->get_error_message() );
				}
			}

			$response = $chat_api->getChatCompletions( $request );
			$chat     = ! is_null( $response->getChoices()[0]->getMessage()->getContent() ) ? $response->getChoices()[0]->getMessage()->getContent() : '';

			// If user.
			if ( $user->ID && $chat_id && $chat ) {
				// Update the chat post content with the reply.
				$chat_id = wp_update_post(
					[
						'ID'           => $chat_id,
						'post_content' => $this->get_converted_html( $chat ),
					]
				);

				// Maybe return error.
				if ( is_wp_error( $chat_id ) ) {
					if ( $ajax ) {
						wp_send_json_error( [ 'message' => $chat_id->get_error_message() ] );
						exit;
					}

					wp_die( $chat_id->get_error_message() );
				}
			}

			// If AJAX.
			if ( $ajax ) {
				// Send success response.
				wp_send_json_success( [ 'message' => $chat ] );
				exit;
			}

			// Redirect back to the form page.
			wp_safe_redirect( wp_get_referer() );
			exit;
		} catch ( Exception $e ) {
			$chat = 'Exception when calling ChatApi->getChatCompletions: ' . $e->getMessage();

			// If AJAX.
			if ( $ajax ) {
				// Send error response.
				wp_send_json_error( [ 'message' => $chat ] );
				exit;
			}

			wp_die( $chat );
		}

		// If AJAX.
		if ( $ajax ) {
			// Send error response.
			wp_send_json_success( [ 'message' => 'Chat saved.' ] );
			exit;
		}

		// Redirect back to the form page.
		wp_safe_redirect( wp_get_referer() );
	}

	/**
	 * Handles the vote submission.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	function handle_submission_stream() {
	}
}