<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The listener class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Outcome_Listener {
	protected $body;
	protected $user;
	protected $return;

	/**
	 * Construct the class.
	 */
	function __construct( $body, $user = null ) {
		$this->body = is_string( $body ) ? json_decode( $body, true ) : $body;
		$this->user  = $user ?: wp_get_current_user();
		$this->run();
	}

	/**
	 * Run the logic.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function run() {
		// If no capabilities.
		if ( ! user_can( $this->user, 'edit_posts' ) ) {
			$this->return = $this->get_error( 'User cannot edit posts.' );
			return;
		}

		// No event_uuid, return error.
		if ( ! isset( $this->body['event_uuid'] ) || ! $this->body['event_uuid'] ) {
			$this->return = $this->get_error( 'No event_uuid found in request body.' );
			return;
		}

		/***************************************************************
		 * Step 1 - Get the matchup post ID.
		 *
		 * Check for an existing matchup.
		 ***************************************************************/

		// Check for an existing matchup.
		$matchup_ids = get_posts(
			[
				'post_type'    => 'matchup',
				'post_status'  => 'any',
				'post_parent'  => 0,
				'meta_key'     => 'event_uuid',
				'meta_value'   => $this->body['event_uuid'],
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 1,
			]
		);

		// No matchup, return error.
		if ( ! $matchup_ids ) {
			$this->return = $this->get_error( 'No matchup found for event_uuid: ' . $this->body['event_uuid'] );
			return;
		}

		// Get the matchup ID.
		$matchup_id = reset( $matchup_ids );

		// Update the outcome.
		update_post_meta( $matchup_id, 'asknews_outcome', $this->body );

		// Set return message.
		$this->return = $this->get_success( get_permalink( $insight_id ) . ' updated successfully' );
		return;
	}

	/**
	 * Maybe send json error.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The error message.
	 * @param int    $code    The error code.
	 *
	 * @return
	 */
	function get_error( $message, $code = null ) {
		return new WP_Error( 'mai-asknews error', $message, [ 'status' => $code ] );
	}

	/**
	 * Maybe send json success.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The success message.
	 *
	 * @return JSON|void
	 */
	function get_success( $message ) {
		return $message;
	}

	/**
	 * Get the response.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response
	 */
	function get_response() {
		if ( is_wp_error( $this->return ) ) {
			$response = $this->return;
		} else {
			$response = new WP_REST_Response( $this->return );
			$response->set_status( 200 );
		}

		return rest_ensure_response( $response );
	}
}
