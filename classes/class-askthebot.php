<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

use AskNews\AskNewsSDK;
use Ramsey\Uuid\Uuid;
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

		// Display the chat list, current chat, and chat form.
		echo '<div class="askthebot-container">';
			echo '<div class="askthebot-chats">';
				echo $this->get_chat_list_html();
			echo '</div>';
			echo '<div id="askthebot-chat" class="askthebot-chat">';
				$avatar_url = get_avatar_url( get_current_user_id() );
				$avatar_url = str_replace( '.local', '.com', $avatar_url );
				printf( '<style>.askthebot-chat { --user-avatar-url:url(\'%s\'); }</style>', $avatar_url );
				echo $this->get_chat_html();
				echo $this->get_chat_form();
				printf( '<p class="has-xl-margin-top has-sm-font-size" style="opacity:.75;"><em>%s</em></p>', __( 'Please review the SportsDesk Bot’s chats to make sure they’re accurate.', 'mai-asknews' ) );
			echo '</div>';
		echo '</div>';
	}

	/**
	 * Get the chat list HTML.
	 *
	 * @since 0.12.0
	 *
	 * @return string
	 */
	function get_chat_list_html() {
		$html = '';
		$args = [
			'post_type'              => 'askthebot',
			'post_status'            => 'publish',
			'posts_per_page'         => 10,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'author'                 => get_current_user_id(),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		// Get the chat messages.
		$query = new WP_Query( $args );

		// Loop through the chat messages.
		if ( $query->have_posts() ) {
			// Get the loaded chat ID.
			$loaded_id = isset( $_GET['chat'] ) ? absint( $_GET['chat'] ) : 0;

			// Get current page url.
			$current_url = get_permalink();

			// Start list.
			$html .= '<ul class="askthebot-chatlist is-sticky">';

			// Loop through the chats.
			while ( $query->have_posts() ) : $query->the_post();
				// Set vars.
				$class       = $loaded_id === get_the_ID() ? ' current' : '';
				$current_url = add_query_arg(
					[
						'chat' => get_the_ID()
					],
					esc_url( $current_url )
				);

				// Add the chat item.
				$html .= '<li class="askthebot-chatlist__item">';
					$html .= sprintf( '<a class="askthebot-chatlist__link%s" href="%s">', $class, $current_url );
						$html .= do_shortcode( '[post_date format="relative" relative_depth="1"]' );
						$html .= sprintf( '<p class="askthebot-chatlist__title">%s</p>', get_the_title() );
					$html .'</a>';
				$html .= '</li>';

			endwhile;

			// End list.
			$html .= '</ul>';
		}
		// No posts.
		else {
			$html .= sprintf( '<div class="askthebot-chatlist">%s</div>', __( 'No chat messages yet.', 'mai-asknews' ) );
		}

		wp_reset_postdata();

		return $html;
	}

	/**
	 * Get the chat HTML.
	 *
	 * @since 0.9.0
	 *
	 * @return string
	 */
	function get_chat_html() {
		$chat_id = isset( $_GET['chat'] ) ? absint( $_GET['chat'] ) : 0;

		// Bail if no chat.
		if ( ! $chat_id ) {
			return '';
		}

		// Get the chat post.
		$chat = get_post( $chat_id );

		// Bail if no post.
		if ( ! $chat ) {
			return '';
		}

		// Bail if not an askthebot post.
		if ( 'askthebot' !== $chat->post_type ) {
			return '';
		}

		// Bail if the current user is not the author.
		if ( (int) get_current_user_id() !== (int) $chat->post_author ) {
			return '';
		}

		// Bail if the chat is not published.
		if ( 'publish' !== $chat->post_status ) {
			return '';
		}

		// Get the chat post.
		printf( '<h2>%s</h2>', get_the_title( $chat_id ) );
		echo get_post_field( 'post_content', $chat_id );
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
			$html .= sprintf( '<p><button id="chat-down" class="button button-secondary button-small" style="display:none;margin-inline:auto;">%s</button></p>', __( 'Scroll to bottom ↓', 'mai-asknews' ) );
			$html .= sprintf( '<p><textarea name="askthebot_question" id="askthebot-question" placeholder="%s" required></textarea></p>', __( 'Ask the bot anything...', 'mai-asknews' ) );
			$html .= '<div class="askthebot-form__buttons">';
				$html .= sprintf( '<button type="submit" class="button button-ajax"><span class="button-text">%s</span></button>', __( 'Send Message', 'mai-asknews' ) );
				$html .= sprintf( '<a id="chat-new" class="button button-link" href="%s">%s</a>', get_permalink(), __( 'Start a new chat', 'mai-asknews' ) );
			$html .= '</div>';
			$html .= sprintf( '<input type="hidden" name="askthebot_user_id" value="%s">', get_current_user_id() );
			$html .= sprintf( '<input type="hidden" name="askthebot_post_id" value="%s">', isset( $_GET['chat'] ) ? absint( $_GET['chat'] ) : 0 );
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
			$message = __( 'Ask the Bot security check failed.', 'mai-asknews' );

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

		// Get data.
		$user_id  = isset( $_POST['askthebot_user_id'] ) ? absint( $_POST['askthebot_user_id'] ) : 0;
		$post_id  = isset( $_POST['askthebot_post_id'] ) ? absint( $_POST['askthebot_post_id'] ) : 0;
		$question = isset( $_POST['askthebot_question'] ) ? sanitize_textarea_field( $_POST['askthebot_question'] ) : '';

		// Bail if no user ID.
		if ( ! $user_id ) {
			$message = __( 'No user ID.', 'mai-asknews' );

			if ( $ajax ) {
				wp_send_json_error( [ 'message' => $message ] );
				exit;
			}

			wp_die( $message );
		}

		// Bail if no question.
		if ( ! $question ) {
			$message = __( 'No question asked.', 'mai-asknews' );

			if ( $ajax ) {
				wp_send_json_error( [ 'message' => $message ] );
				exit;
			}

			wp_die( $message );
		}

		// If there's a post.
		if ( $post_id ) {
			// If not a post, or not askthebot post type.
			if ( ! get_post( $post_id ) || 'askthebot' !== get_post_type( $post_id ) ) {
				$message = __( 'Invalid post ID.', 'mai-asknews' );

				if ( $ajax ) {
					wp_send_json_error( [ 'message' => $message ] );
					exit;
				}

				wp_die( $message );
			}

			// Bail if user is not the post author.
			if ( $user_id !== (int) get_post_field( 'post_author', $post_id ) ) {
				$message = __( 'You are not the author of this chat.', 'mai-asknews' );

				if ( $ajax ) {
					wp_send_json_error( [ 'message' => $message ] );
					exit;
				}

				wp_die( $message );
			}

			// Get the post name.
			$post_name = trim( get_post_field( 'post_name', $post_id ) );

			// Not a new post.
			$new_post = false;
		}
		// No post, create one first.
		else {
			// Generate a UUID for the post name.
			$uuid      = Uuid::uuid4();
			$post_name = $uuid->toString();

			// Create the chat post.
			$post_id = wp_insert_post(
				[
					'post_type'   => 'askthebot',
					'post_status' => 'publish',
					'post_author' => $user_id,
					'post_title'  => $question,
					'post_name'   => $post_name,
				]
			);

			// Maybe return error.
			if ( is_wp_error( $post_id ) ) {
				if ( $ajax ) {
					wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );
					exit;
				}

				wp_die( $post_id->get_error_message() );
			}

			// New post.
			$new_post = true;
		}

		// Set up the SDK.
		$config = new AskNews\Configuration([
			'clientId'     => $client_id,
			'clientSecret' => $client_secret,
			'scopes'       => [ 'chat' ],
		]);

		// Conversational awareness can be controlled via the `user` param in the request body,
		// and optionally a `thread_id` in the request body.
		// If no user is specified, then it defaults to the identity making the request, in this case you guys.
		// Then, if no thread_id is explicitly passed, it will try and find the most recent thread and continue it, up to 1 hour since the last message.
		// If it has been 1 hour past the last message in a thread then it makes a new thread.
		// Given this, if you want to have separate "chats" for each user,
		// send their user ID string prefixed with something like `promatchups:user_id`,
		// and keep track of their `thread_id`s, and send them when sending a chat.

		// Set up the request args.
		$request_args = [
			// 'model'                    => 'gpt-4o-mini',
			'model'                    => 'claude-3-5-sonnet-20240620',
			'append_references'        => false,
			'asknews_watermark'        => false,
			'inline_citations'         => 'markdown_link',
			'conversational_awareness' => true,
			'user'                     => "promatchups:{$user_id}",
			'thread_id'                => $post_name,
			'filter_params'            => [
				'categories' => [ 'Sports' ],
				'method'     => 'kw',
				'hours_back' => 744, // 31 days.
			],
			'messages'                 => [
				[
					'role'    => 'user',
					'content' => $question,
				]
			],
			'stream' => false,
		];

		// Create an instance of the ChatApi and request classes.
		$chat_api = new AskNews\Api\ChatApi( new GuzzleHttp\Client(), $config );
		$request  = new AskNews\Model\CreateChatCompletionRequest( $request_args );

		// Send the request.
		try {
			// Get the response chat.
			$response = $chat_api->getChatCompletions( $request );
			$chat     = ! is_null( $response->getChoices()[0]->getMessage()->getContent() ) ? $response->getChoices()[0]->getMessage()->getContent() : '';
			$chat     = $this->get_converted_html( $chat );

			// // Set up the post args.
			// $post_args = [
			// 	'post_type'   => 'askthebot',
			// 	'post_name'   => $post_id ? trim( get_post_field( 'post_name', $post_id ) ) : $response->getThreadId(),
			// 	'post_status' => 'publish',
			// 	'post_author' => $user_id,
			// 	'post_title'  => $question,
			// 	// 'post_content' => trim( $content ) . trim( $chat ),
			// 	'post_content' => $chat,
			// ];

			// If it's a new post, only update the content with the chat answer.
			// We have already created the post, but question is the title and not in the content.
			if ( $new_post ) {
				$chat_id  = wp_update_post(
					[
						'ID'           => $post_id,
						'post_content' => $chat,
					]
				);
			}
			// Not a new post, update the content with the question and answer.
			else {
				$content  = get_post_field( 'post_content', $post_id );
				$content  = trim( $content );
				$content .= "<h2>{$question}</h2>";
				$content .= $chat;
				$chat_id  = wp_update_post(
					[
						'ID'           => $post_id,
						'post_content' => trim( $content ),
					]
				);
			}

			// Maybe return error.
			if ( is_wp_error( $chat_id ) ) {
				if ( $ajax ) {
					wp_send_json_error( [ 'message' => $chat_id->get_error_message() ] );
					exit;
				}

				wp_die( $chat_id->get_error_message() );
			}

			// If AJAX.
			if ( $ajax ) {
				// Send success response.
				wp_send_json_success( [ 'message' => $chat, 'chatId' => $chat_id ] );
				exit;
			}

			// Redirect back to the form page.
			wp_safe_redirect( add_query_arg( [ 'chat' => $chat_id ], wp_get_referer() ) );
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
			wp_send_json_success( [ 'message' => $chat, 'chat_id' => $chat_id ] );
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