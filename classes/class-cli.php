<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

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
	 * Updates teams to show city and name instead of the original way of just name.
	 * TODO: Remove this after all teams are updated on the live site.
	 *
	 * Usage: wp maiasknews convert_teams
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function convert_teams() {
		$leagues = maiasknews_get_teams();

		foreach ( $leagues as $sport => $teams ) {
			$sport_term = get_term_by( 'name', $sport, 'league' );

			foreach ( $teams as $name => $values ) {
				$team_term = get_term_by( 'name', $name, 'league' );
				$city      = $values['city'];

				if ( $team_term ) {
					// Update name and slug with $city .  ' ' . $name.
					wp_update_term(
						$team_term->term_id,
						'league',
						[
							'name' => $city . ' ' . $name,
							'slug' => sanitize_title( $city . ' ' . $name ),
						]
					);
				}
			}
		}
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
			while ( $query->have_posts() ) : $query->the_post();
				$asknews_body = get_post_meta( get_the_ID(), 'asknews_body', true );

				if ( ! $asknews_body ) {
					WP_CLI::log( 'No AskNews data found for post ID: ' . get_the_ID() . ' ' . get_permalink() );
					continue;
				}

				$listener = new Mai_AskNews_Listener( $asknews_body, $this->user );
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
