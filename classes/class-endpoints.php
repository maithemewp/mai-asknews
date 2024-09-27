<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The endpoints class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Endpoints {
	protected $user;
	protected $request;
	protected $body;

	/**
	 * Construct the class.
	 */
	function __construct() {
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
		add_filter( 'rest_api_init',  [ $this, 'register_endpoints' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
	}

	/**
	 * Register the endpoints.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function register_endpoints() {
		/**
		 * /maiasknews/v1/matchups/
		 * /maiasknews/v1/outcome/
		 */
		$routes = [
			'matchups' => 'handle_matchups_request',
			'outcome'  => 'handle_outcome_request',
		];

		// Loop through routes and register them.
		foreach ( $routes as $path => $callback ) {
			register_rest_route( 'maiasknews/v1', $path, [
				'methods'             => 'POST', // I think the testing CLI needs PUT. The API does check for auth cookies and nonces when you make POST or PUT requests, but not GET requests.
				'callback'            => [ $this, $callback ],
				'permission_callback' => [ $this, 'authenticate_request' ],
			] );
		}
	}

	/**
	 * Handle the matchups request.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	function handle_matchups_request( $request ) {
		$listener = new Mai_AskNews_Matchup_Listener( $request->get_body(), $this->user );
		$response = $listener->get_response();

		return $response;
	}

	/**
	 * Handle the outcome request.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	function handle_outcome_request( $request ) {
		$listener = new Mai_AskNews_Outcome_Listener( $request->get_body(), $this->user );
		$response = $listener->get_response();

		return $response;
	}

	/**
	 * Authenticate and validate the request.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function authenticate_request( $request ) {
		// Get the headers
		$headers = $request->get_headers();

		// Bail if no headers.
		if ( ! isset( $headers['authorization'] ) ) {
			// If authorization header is missing
			return new WP_Error( 'rest_forbidden', 'Authorization header missing.', [ 'status' => 403 ] );
		}

		// Extract the application password from the Authorization header.
		$auth_header                = $headers['authorization'];
		list( $type, $credentials ) = explode( ' ', reset( $auth_header ), 2 );

		// Basic Authentication should start with 'Basic'
		if ( 'Basic' !== $type ) {
			return new WP_Error( 'rest_forbidden', 'Invalid authentication method.', [ 'status' => 403 ] );
		}

		// Decode the credentials
		$decoded_credentials         = base64_decode( $credentials );
		list( $username, $password ) = explode( ':', $decoded_credentials, 2 );

		// Validate the application password
		if ( ! $username || ! $password ) {
			return new WP_Error( 'rest_forbidden', 'Invalid credentials.', [ 'status' => 403 ] );
		}

		// Authenticate the user
		$this->user = wp_authenticate_application_password( $password, $username, $password );

		// If the authentication failed.
		if ( is_wp_error( $this->user ) ) {
			return new WP_Error( 'rest_forbidden', 'Invalid application password.', [ 'status' => 403 ] );
		}

		// Show errory if no body.
		if ( ! $request->get_body() ) {
			return new WP_Error( 'rest_forbidden', 'No body found.', [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Register the metaboxes.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	function register_metaboxes() {
		// Matchup.
		add_meta_box(
			'matchup_outcome_asknews_body', // ID of the meta box
			__( 'AskNews Outcome (JSON)', 'mai-asknews' ), // Title of the meta box
			[ $this, 'display_matchup_outcome_metabox' ], // Callback function to display the field
			'matchup', // Post type to add the meta box
			'normal', // Context where the box will be displayed
			'high' // Priority
		);

		// Matchup Latest Insight.
		add_meta_box(
			'matchup_insight_asknews_body', // ID of the meta box
			__( 'AskNews Latest Insight (JSON)', 'mai-asknews' ), // Title of the meta box
			[ $this, 'display_matchup_insight_metabox' ], // Callback function to display the field
			'matchup', // Post type to add the meta box
			'normal', // Context where the box will be displayed
			'high' // Priority
		);

		// Insight.
		add_meta_box(
			'insight_asknews_body', // ID of the meta box
			__( 'AskNews Insight (JSON)', 'mai-asknews' ), // Title of the meta box
			[ $this, 'display_insight_metabox' ], // Callback function to display the field
			'insight', // Post type to add the meta box
			'normal', // Context where the box will be displayed
			'high' // Priority
		);
	}

	/**
	 * Display the matchup outcome metabox.
	 *
	 * @since 0.8.0
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	function display_matchup_outcome_metabox( $post ) {
		echo $this->get_metabox_content( $post->ID, 'asknews_outcome', false );
	}

	/**
	 * Display the matchup insight metabox.
	 *
	 * @since 0.8.0
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	function display_matchup_insight_metabox( $post ) {
		printf( '<p>%s</p>', __( 'Click to show nested data. `Shift + click` or `CMD + click` to show all nested data of an item.', 'mai-asknews' ) );
		echo $this->get_metabox_content( maiasknews_get_insight_id( $post->ID ), 'asknews_body' );
		echo $this->get_toggle_script();
	}

	/**
	 * Display the insight metabox.
	 *
	 * @since 0.8.0
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	function display_insight_metabox( $post ) {
		printf( '<p>%s</p>', __( 'Click to show nested data. `Shift + click` or `CMD + click` to show all nested data of an item.', 'mai-asknews' ) );
		echo $this->get_metabox_content( $post->ID, 'asknews_body' );
		echo $this->get_toggle_script();
	}

	/**
	 * Get the metabox content.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $post_id The post ID.
	 * @param string $key     The post meta key for the data.
	 * @param string $toggles Whether to use the JS toggle script.
	 *
	 * @return string
	 */
	function get_metabox_content( $post_id, $key, $toggles = true ) {
		$data  = get_post_meta( $post_id, $key, true );
		$data  = maybe_unserialize( $data );
		$data  = is_array( $data ) ? $data : [];
		$html  = $this->get_list_html( $data, false, $toggles );

		return $html;
	}

	/**
	 * Get the HTML for the list.
	 *
	 * @since 0.8.0
	 *
	 * @param array $data   The data to display.
	 * @param bool  $nested Whether the list is nested.
	 *
	 * @return string
	 */
	function get_list_html( $data, $nested = false, $toggles = true ) {
		$classes = [];

		// Build class list.
		if ( ! $nested ) {
			if ( $toggles ) {
				$classes[] = 'pm-json-has-toggles';
			}
			$classes[] = 'pm-json-list';
		} else {
			$classes[] = 'pm-json-nested';
		}

		// Start HTML.
		$html  = sprintf( '<ul class="%s">', implode( ' ', $classes ) );

		// Loop through the data.
		foreach ( $data as $key => $value ) {
			// For nested arrays, output the key and recursively call the function.
			if ( is_array( $value ) ) {
				$html .= sprintf( '<li class="pm-json-has-nested"><strong>%s:</strong> <span class="pm-json-arrow"></span>', esc_html( $key ) );
					$html .= $this->get_list_html( $value, true );
				$html .= '</li>';
			}
			// For non-arrays, output the key and value.
			else {
				$html .= sprintf( '<li><strong>%s:</strong> %s</li>', esc_html( $key ), esc_html( $value ) );
			}
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Get the toggle script.
	 *
	 * @since 0.8.0
	 *
	 * @return string
	 */
	function get_toggle_script() {
		ob_start();
		?>
		<style>
		.pm-json-list,
		.pm-json-nested {
			list-style-type: disc;
			padding-left: 12px;
		}
		.pm-json-nested {
			position: relative;
			margin: 4px 0 0 4px;
		}
		.pm-json-has-toggles {
			.pm-json-has-nested {
				cursor: pointer;
			}
			.pm-json-has-nested:not(.open) > .pm-json-arrow::before {
				content: "{▶}";
			}
			.pm-json-has-nested.open > .pm-json-arrow::before {
				content: "{▼}";
			}
			.pm-json-nested:not(.open) {
				display: none;
			}
			.pm-json-nested.open {
				display: block;
			}
			.pm-json-nested.open::before {
				position: absolute;
				top: 0;
				left: -14px;
				width: 0;
				height: 100%;
				border-left: 1px dotted rgba(0,0,0,0.2);
				content: "";
			}
		}
		</style>
		<script>
		document.addEventListener('DOMContentLoaded', () => {
			// Get all lis with nested lists.
			const elements = document.querySelectorAll('.pm-json-has-toggles .pm-json-has-nested');

			// Loop through each element.
			elements.forEach(element => {
				// Add click event listener to toggle nested lists
				element.addEventListener('click', (e) => {
					// Stop propagation to avoid parent toggling
					e.stopPropagation();

					// Set vars.
					const childUls = element.querySelectorAll('.pm-json-nested');
					const firstUl  = childUls[0];
					const isOpen   = element.classList.contains('open');

					// Toggle open class on the main element.
					toggleClass(element, isOpen);

					// Shift-click or Alt-click to toggle all nested lists.
					if (e.shiftKey || e.altKey) {
						const childLis = element.querySelectorAll('.pm-json-has-nested');
						childLis.forEach(childLi => {
							toggleClass(childLi, isOpen);
						});
						childUls.forEach(childUl => {
							toggleClass(childUl, isOpen);
						});
					}
					// Normal click toggles just this child.
					else {
						toggleClass(firstUl, isOpen);
					}
				});
			});

			function toggleClass(element, isOpen) {
				if (isOpen) {
					element.classList.remove('open');
				} else {
					element.classList.add('open');
				}
			}
		});
		</script>
		<?php
		return ob_get_clean();
	}
}