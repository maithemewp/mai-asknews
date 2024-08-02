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
		 * /maiasknews/v1/matchups/
		 */
		$routes = [
			'matchups' => 'handle_matchups_request',
		];

		// Loop through routes and register them.
		foreach ( $routes as $path => $callback ) {
			register_rest_route( 'maiasknews/v1', $path, [
				'methods'             => 'POST', // I think the testing CLI needs PUT. The API does check for auth cookies and nonces when you make POST or PUT requests, but not GET requests.
				'callback'            => [ $this, $callback ],
				'permission_callback' => current_user_can( 'edit_posts' ),
			] );
		}
	}

	/**
	 * Handle the matchups request.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function handle_matchups_request( $request ) {
		$listener = new Mai_AskNews_Listener( $request->get_body() );
	}
}