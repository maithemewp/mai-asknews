<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

use League\CommonMark\CommonMarkConverter;

/**
 * Gets it started.
 *
 * @since 0.1.0
 *
 * @link https://docs.wpvip.com/how-tos/write-custom-wp-cli-commands/
 * @link https://webdevstudios.com/2019/10/08/making-wp-cli-commands/
 *
 * @return void
 */
add_action( 'cli_init', function() {
	WP_CLI::add_command( 'maiasknews', 'Mai_AskNews_CLI' );
});

/**
 * Main Mai_AskNews_CLI Class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_CLI {
	protected $user;

	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->user = get_user_by( 'login', defined( 'MAI_ASKNEWS_AUTH_UN' ) ? MAI_ASKNEWS_AUTH_UN : null );
	}

	/**
	 * Gets environment.
	 *
	 * Usage: wp maiasknews get_environment
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function get_environment() {
		WP_CLI::log( sprintf( 'Environment: %s', wp_get_environment_type() ) );
	}

	/**
	 * Deletes matchup tags that contain '('.
	 * This is leftover from when we tried creating tags from key people,
	 * when key people had a description in the string.
	 *
	 * TODO: Possibly remove this after all tags are cleaned up on the live site.
	 *       Need to check if they are back after a full update_insights first.
	 *
	 * Usage: wp maiasknews cleanup_tags
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function cleanup_tags( $args, $assoc_args ) {
		$terms = get_terms(
			[
				'taxonomy'   => 'matchup_tag',
				'number'     => 0,
				'hide_empty' => false,
			]
		);

		foreach ( $terms as $term ) {
			if ( str_contains( $term->name, '(' ) ) {
				wp_delete_term( $term->term_id, 'matchup_tag' );
			}
		}

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Cleans up AskTheBot posts.
	 *
	 * Usage: wp maiasknews cleanup_askthebot --posts_per_page=10 --offset=0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function cleanup_askthebot( $args, $assoc_args ) {
		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'              => 'askthebot',
				'post_status'            => 'any',
				'posts_per_page'         => 100,
				'offset'                 => 0,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// Get posts.
		$query = new WP_Query( $assoc_args );

		// If we have posts.
		if ( $query->have_posts() ) {
			// Log how many total posts found.
			WP_CLI::log( 'Posts found: ' . $query->post_count );

			// Loop through posts.
			while ( $query->have_posts() ) : $query->the_post();
				// Get the content.
				$content = get_post_field( 'post_content', get_the_ID() );

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

				// Update the post.
				$post_id = wp_update_post(
					[
						'ID'           => get_the_ID(),
						'post_content' => $content,
					]
				);

				// Log if updated.
				WP_CLI::log( 'Cleaned up askthebot: ' . $post_id . ' ' . get_permalink() );

			endwhile;

		} else {
			WP_CLI::log( 'No posts found.' );
		}

		wp_reset_postdata();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Update bot votes from matchups.
	 *
	 * Usage: wp maiasknews update_bot_votes --posts_per_page=10 --offset=0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function update_bot_votes( $args, $assoc_args ) {
		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'              => 'matchup',
				'post_status'            => 'any',
				'posts_per_page'         => 10,
				'offset'                 => 0,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// Get posts.
		$query = new WP_Query( $assoc_args );

		// If we have posts.
		if ( $query->have_posts() ) {
			// Log how many total posts found.
			WP_CLI::log( 'Posts found: ' . $query->post_count );

			// Loop through posts.
			while ( $query->have_posts() ) : $query->the_post();
				$bot_id     = maiasknews_get_bot_user_id();
				$matchup_id = get_the_ID();
				$existing   = maiasknews_get_user_vote( $matchup_id, $bot_id );

				// Skip if bot has already voted.
				if ( array_filter( array_values( $existing ) ) ) {
					continue;
				}

				$data = maiasknews_get_matchup_data( $matchup_id );
				$team = isset( $data['prediction'] ) ? $data['prediction'] : '';

				// Skip if no team.
				if ( ! $team ) {
					continue;
				}

				// Add bot vote.
				$comment_id = maiasknews_add_user_vote( $matchup_id, $team, $bot_id );

				// Skip if no comment ID.
				if ( ! $comment_id ) {
					continue;
				}

				// Log if updated.
				WP_CLI::log( 'Bot vote added for post ID: ' . $matchup_id . ' ' . get_permalink() );

			endwhile;

		} else {
			WP_CLI::log( 'No posts found.' );
		}

		wp_reset_postdata();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Updates user points.
	 *
	 * Usage: wp maiasknews update_user_points --number=10 --offset=0
	 * Usage: wp maiasknews update_user_points --number=10 --include=2
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --number, --offset and --include.
	 *
	 * @return void
	 */
	function update_user_points( $args, $assoc_args ) {
		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'number' => 10,
				'offset' => 0,
				'fields' => 'ID',
			]
		);

		// Get users.
		$users = get_users( $assoc_args );

		// If users.
		if ( $users ) {
			// Log how many total users found.
			WP_CLI::log( 'Users found: ' . count( $users ) );

			// Loop through users.
			foreach ( $users as $user_id ) {
				// Log user display name.
				WP_CLI::log( 'Calculating totals for: ' . get_the_author_meta( 'display_name', $user_id ) );

				// Get listener response.
				$listener = new Mai_AskNews_User_Points( $user_id );
				$response = $listener->get_response();

				// Log response.
				if ( is_wp_error( $response ) ) {
					WP_CLI::log( 'Error: ' . $response->get_error_message() );
				} else {
					WP_CLI::log( 'Success: ' . $response->get_data() );
				}
			}
		}
		// No users.
		else {
			WP_CLI::log( 'No users found.' );
		}

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Update votes from matchups.
	 *
	 * Usage: wp maiasknews update_matchup_votes --posts_per_page=10 --offset=0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function update_matchup_votes( $args, $assoc_args ) {
		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'              => 'matchup',
				'post_status'            => 'any',
				'posts_per_page'         => 10,
				'offset'                 => 0,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'comment_count'          => [
					'value'   => 1,
					'compare' => '>=',
				],
			]
		);

		// Get posts.
		$query = new WP_Query( $assoc_args );

		// If we have posts.
		if ( $query->have_posts() ) {
			// Log how many total posts found.
			WP_CLI::log( 'Posts found: ' . $query->post_count );

			// Loop through posts.
			while ( $query->have_posts() ) : $query->the_post();
				$matchup_id = get_the_ID();
				$comments   = get_comments(
					[
						'type'    => 'pm_vote',
						'post_id' => $matchup_id,
						'approve' => 1,
					]
				);

				if ( ! $comments ) {
					WP_CLI::log( 'No votes found for post ID: ' . $matchup_id . ' ' . get_permalink() );
					continue;
				}

				// Loop through comments.
				foreach ( $comments as $comment ) {
					// Skip if karma is 1, -1, or 2.
					if ( in_array( $comment->comment_karma, [ 1, -1, 2 ] ) ) {
						continue;
					}

					// Remove karma.
					wp_update_comment(
						[
							'comment_ID'    => $comment->comment_ID,
							'comment_karma' => 0,
						]
					);
				}

				// Matchup updated.
				WP_CLI::log( 'Matchup votes updated for post ID: ' . $matchup_id . ' ' . get_permalink() );

			endwhile;
		} else {
			WP_CLI::log( 'No posts found.' );
		}

		wp_reset_postdata();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Processes outcomes from stored AskNews data.
	 *
	 * Usage: wp maiasknews update_matchup_outcomes --posts_per_page=10 --offset=0
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function update_matchup_outcomes( $args, $assoc_args ) {
		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'              => 'matchup',
				'post_status'            => 'any',
				'posts_per_page'         => 10,
				'offset'                 => 0,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'comment_count'          => [
					'value'   => 1,
					'compare' => '>=',
				],
				// Do we need to check for past games?
				// The outcome should not even be here if the game has not been played.

				// Make sure the outcome is not empty.
				'meta_query'             => [
					[
						'key'     => 'asknews_outcome',
						'value'   => '',
						'compare' => '!=',
					],
				],
			]
		);

		// Get posts.
		$query = new WP_Query( $assoc_args );

		// If we have posts.
		if ( $query->have_posts() ) {
			// Log how many total posts found.
			WP_CLI::log( 'Posts found: ' . $query->post_count );

			// Loop through posts.
			while ( $query->have_posts() ) : $query->the_post();
				// Get matchup listener response.
				$matchup_id = get_the_ID();
				$listener   = new Mai_AskNews_Matchup_Outcome_Listener( $matchup_id );
				$response   = $listener->get_response();

				if ( is_wp_error( $response ) ) {
					WP_CLI::log( 'Error: ' . $response->get_error_message() );
				} else {
					WP_CLI::log( 'Success: ' . $response->get_data() );
				}

			endwhile;
		} else {
			WP_CLI::log( 'No posts found.' );
		}

		wp_reset_postdata();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Updates posts from stored AskNews data.
	 *
	 * Usage: wp maiasknews update_insights --posts_per_page=10 --offset=0
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function update_insights( $args, $assoc_args ) {
		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'              => 'insight',
				'post_status'            => 'any',
				'posts_per_page'         => 10,
				'offset'                 => 0,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		// Get posts.
		$query = new WP_Query( $assoc_args );

		// If we have posts.
		if ( $query->have_posts() ) {
			// Log how many total posts found.
			WP_CLI::log( 'Posts found: ' . $query->post_count );

			// Loop through posts.
			while ( $query->have_posts() ) : $query->the_post();
				$asknews_body = get_post_meta( get_the_ID(), 'asknews_body', true );

				if ( ! $asknews_body ) {
					WP_CLI::log( 'No AskNews data found for post ID: ' . get_the_ID() . ' ' . get_permalink() );
					continue;
				}

				$listener = new Mai_AskNews_Matchup_Listener( $asknews_body, $this->user );
				$response = $listener->get_response();

				if ( is_wp_error( $response ) ) {
					WP_CLI::log( 'Error: ' . $response->get_error_message() );
				} else {
					WP_CLI::log( 'Success: ' . $response->get_data() );
				}

			endwhile;
		} else {
			WP_CLI::log( 'No posts found.' );
		}

		wp_reset_postdata();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Gets example json files from /examples/*.json and hits our endpoint.
	 *
	 * 1. Create an application un/pw via your WP user account.
	 * 2. Set un/pw in wp-config.php via `MAI_ASKNEWS_AUTH_UN` (user login name) and `MAI_ASKNEWS_AUTH_PW` (application password) constants.
	 * 3. Copy the path to this file.
	 * 4. Execute via command line:
	 *    wp maiasknews test_feed --max=2
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --max.
	 *
	 * @return void
	 */
	function test_feed( $args, $assoc_args ) {
		WP_CLI::log( 'Starting...' );

		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'max' => 10,
			]
		);

		// Get all json files in examples directory.
		$files = glob( MAI_ASKNEWS_DIR . 'examples/*.json' );

		// Start count.
		$i = 1;

		// Loop through files.
		foreach ( $files as $file ) {
			// Break if max reached.
			if ( $i > $assoc_args['max'] ) {
				break;
			}

			// Increment count.
			$i++;

			// Set data.
			$url      = home_url( '/wp-json/maiasknews/v1/matchups/' );
			$name     = defined( 'MAI_ASKNEWS_AUTH_UN' ) ? MAI_ASKNEWS_AUTH_UN : '';
			$password = defined( 'MAI_ASKNEWS_AUTH_PW' ) ? MAI_ASKNEWS_AUTH_PW : '';

			if ( ! $name ) {
				WP_CLI::log( 'No name found via MAI_ASKNEWS_AUTH_UN constant.' );
				return;
			}

			if ( ! $password ) {
				WP_CLI::log( 'No password found via MAI_ASKNEWS_AUTH_PW constant.' );
				return;
			}

			if ( ! file_exists( $file ) ) {
				WP_CLI::log( 'File does not exists via ' . $file );
				return;
			}

			// Data to be sent in the JSON packet.
			// Get content from json file.
			$data = file_get_contents( $file );

			// Bail if no test data.
			if ( ! $data ) {
				WP_CLI::log( 'No data found via ' . $file );
				return;
			}

			// Prepare the request arguments.
			$args = [
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( $name . ':' . $password ),
				],
				'body'      => $data,
				'sslverify' => 'local' !== wp_get_environment_type(),
			];

			// Make the POST request.
			$response = wp_remote_post( $url, $args );

			// If error.
			if ( is_wp_error( $response ) ) {
				WP_CLI::log( 'Error: ' . $response->get_error_message() );
			}
			// Success.
			else {
				// Get code and decode the response body.
				$code = wp_remote_retrieve_response_code( $response );
				$body = wp_remote_retrieve_body( $response );
				$body = json_decode( $body, true );

				// If error.
				if ( 200 !== $code ) {
					$message = $body && isset( $body['message'] ) ? $body['message'] : '';

					WP_CLI::log( 'Error: ' . $message );
					continue;
				}

				// If success.
				WP_CLI::log( $code . ' : ' . $body );
			}
		}

		WP_CLI::success( 'Done.' );
	}
}
