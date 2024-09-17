<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The admin post voting class.
 *
 * @since TBD
 */
class Mai_AskNews_Admin_Post_Vote {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'admin_post_pm_vote_submission', [ $this, 'handle_submission' ] );
	}

	/**
	 * Handles the vote submission.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function handle_submission() {
		// Verify nonce for security.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'pm_vote_nonce' ) ) {
			return new WP_Error( 'security', __( 'Vote submission security check failed.', 'mai-asknews' ) );
		}

		// Get the post data.
		$args = wp_parse_args(
			$_POST,
			[
				'team'       => null,
				'user_id'    => null,
				'matchup_id' => null,
				'redirect'   => null,
			]
		);

		// Sanitize.
		$team       = sanitize_text_field( $args['team'] );
		$user_id    = absint( $args['user_id'] );
		$matchup_id = absint( $args['matchup_id'] );
		$redirect   = esc_url( $args['redirect'] );

		// Bail if no team.
		if ( ! $team ) {
			return new WP_Error( 'team', __( 'No team selected.', 'mai-asknews' ) );
			// wp_die( __( 'No team selected.', 'mai-asknews' ) );
		}

		// Bail if no user ID.
		if ( ! $user_id ) {
			return new WP_Error( 'user_id', __( 'No user ID found.', 'mai-asknews' ) );
			// wp_die( __( 'No user ID found.', 'mai-asknews' ) );
		}

		// Bail if no matchup ID.
		if ( ! $matchup_id ) {
			return new WP_Error( 'matchup_id', __( 'No matchup ID found.', 'mai-asknews' ) );
			// wp_die( __( 'No matchup ID found.', 'mai-asknews' ) );
		}

		// Run listener and get response.
		$listener = new Mai_AskNews_Vote_Listener( $matchup_id, $team, $user_id );
		$response = $listener->get_response();

		// If redirecting.
		if ( $args['redirect'] ) {
			// Redirect.
			wp_safe_redirect( $redirect );
			exit;
		}

		ray( $response );

		// Return the response.
		return $response;
	}
}