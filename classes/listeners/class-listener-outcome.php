<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The listener class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Outcome_Listener extends Mai_AskNews_Listener {
	protected $outcome;
	protected $body;
	protected $user;
	protected $return;

	/**
	 * Construct the class.
	 */
	function __construct( $outcome, $user = null ) {
		$this->outcome = is_string( $outcome ) ? json_decode( $outcome, true ) : $outcome;
		$this->user    = $this->get_user( $user );
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
		// Bail if not a valid user.
		if ( ! $this->user ) {
			$this->return = $this->get_error( 'User not found.' );
			return;
		}

		// If no capabilities.
		if ( ! user_can( $this->user, 'edit_posts' ) ) {
			$this->return = $this->get_error( 'User cannot edit posts.' );
			return;
		}

		// No event_uuid, return error.
		if ( ! isset( $this->outcome['event_uuid'] ) || ! $this->outcome['event_uuid'] ) {
			$this->return = $this->get_error( 'No event_uuid found in request body.' );
			return;
		}

		/***************************************************************
		 * Get the matchup post ID.
		 *
		 * Find the existing matchup by event_uuid.
		 * Update the matchup with the outcome data.
		 ***************************************************************/

		// Check for an existing matchup.
		$matchup_ids = get_posts(
			[
				'post_type'    => 'matchup',
				'post_status'  => 'any',
				'post_parent'  => 0,
				'meta_key'     => 'event_uuid',
				'meta_value'   => $this->outcome['event_uuid'],
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 1,
			]
		);

		// No matchup, return error.
		if ( ! $matchup_ids ) {
			$this->return = $this->get_error( 'No matchup found for event_uuid: ' . $this->outcome['event_uuid'] );
			return;
		}

		// Get the matchup ID and body.
		$matchup_id = reset( $matchup_ids );
		$this->body = maiasknews_get_insight_body( $matchup_id );

		// Get UUIDs.
		$body_uuid    = isset( $this->body['event_uuid'] ) ? $this->body['event_uuid'] : '';
		$outcome_uuid = isset( $this->outcome['event_uuid'] ) ? $this->outcome['event_uuid'] : '';

		// If UUIDs don't match.
		if ( $body_uuid !== $outcome_uuid ) {
			$this->return = $this->get_error( 'UUIDs do not match.' );
			return;
		}

		// Update the matchup with the outcome data.
		update_post_meta( $matchup_id, 'asknews_outcome', $this->outcome );

		/***************************************************************
		 * Run the matchup outcome listener.
		 ***************************************************************/

		// Get matchup listener response.
		$listener = new Mai_AskNews_Matchup_Outcome_Listener( $matchup_id );
		$response = $listener->get_response();

		// If error.
		if ( is_wp_error( $response ) ) {
			$this->return = $response;
			return;
		}

		// Set return message.
		$this->return = $this->get_success( get_permalink( $matchup_id ) . ' updated successfully' );
		return;
	}
}
