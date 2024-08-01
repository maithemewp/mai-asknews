<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The endpoints class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Endpoints {
	// protected $token;
	protected $request;
	protected $body;

	/**
	 * Construct the class.
	 */
	function __construct() {
		// $this->token = defined( 'MAI_UNITED_ROBOTS_TOKEN' ) ? MAI_UNITED_ROBOTS_TOKEN : false;
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		add_filter( 'rest_api_init', [ $this, 'register_endpoint' ] );
	}

	/**
	 * Register the endpoints.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function register_endpoint() {
		/**
		 * /maiasknews/v1/sports/
		 */
		$routes = [
			'sports' => 'handle_sports_request',
		];

		// Loop through routes and register them.
		foreach ( $routes as $path => $callback ) {
			register_rest_route( 'maiasknews/v1', $path, [
				'methods'             => 'POST', // I think the testing CLI needs PUT. The API does check for auth cookies and nonces when you make POST or PUT requests, but not GET requests.
				'callback'            => [ $this, $callback ],
				'permission_callback' => [ $this, 'validate_request' ],
			] );
		}
	}

	/**
	 * Handle the hurricane request.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function handle_sports_request( $request ) {
		$listener = new Mai_AskNews_Listener( $request->get_body() );
	}

	/**
	 * Validate the request.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function validate_request( $request ) {
		// Get the headers
		$headers = $request->get_headers();

		// Bail if no headers.
		if ( ! isset( $headers['authorization'] ) ) {
			// If authorization header is missing
			return new WP_Error( 'rest_forbidden', 'Authorization header missing.', [ 'status' => 403 ] );
		}

		// Extract the application password from the Authorization header.
		$auth_header = $headers['authorization'];
		list( $type, $credentials ) = explode( ' ', reset( $auth_header ), 2 );

		// Basic Authentication should start with 'Basic'
		if ( 'Basic' !== $type ) {
			return new WP_Error( 'rest_forbidden', 'Invalid authentication method.', [ 'status' => 403 ] );
		}

		// Decode the credentials
		$decoded_credentials = base64_decode( $credentials );
		list( $username, $password ) = explode( ':', $decoded_credentials, 2 );

		// Validate the application password
		if ( ! $username || ! $password ) {
			return new WP_Error( 'rest_forbidden', 'Invalid credentials.', [ 'status' => 403 ] );
		}

		// Authenticate the user
		$user = wp_authenticate_application_password( $password, $username, $password );

		// If the authentication failed.
		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'rest_forbidden', 'Invalid application password.', [ 'status' => 403 ] );
		}

		// Show errory if no body.
		if ( ! $request->get_body() ) {
			return new WP_Error( 'rest_forbidden', 'No body found.', [ 'status' => 403 ] );
		}

		return true;
	}
}