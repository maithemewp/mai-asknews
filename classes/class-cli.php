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
	 * Updates posts from stored AskNews data.
	 *
	 * Usage: wp maiasknews update_posts --post_type=post --posts_per_page=10 --offset=0
	 * Usage: wp maiasknews update_posts --post_type=post --cat=6 --posts_per_page=10 --offset=0
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function update_posts( $args, $assoc_args ) {}

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
			$url      = home_url( '/wp-json/maiasknews/v1/sports/' );
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

			// WP_CLI::log( $url );

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
				// Decode the response body.
				$code = wp_remote_retrieve_response_code( $response );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				// Log the response.
				WP_CLI::log( $code . ' : ' . $body['data'] );
			}
		}

		WP_CLI::success( 'Done.' );
	}
}
