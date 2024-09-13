<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The vote listener class.
 * This class saves a user's vote for a matchup.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Vote_Listener extends Mai_AskNews_Listener {
	protected $matchup_id;
	protected $team;
	protected $user;
	protected $return;

	/**
	 * Construct the class.
	 */
	function __construct( $matchup_id, $team, $user = null ) {
		$this->matchup_id = absint( $matchup_id );
		$this->team       = sanitize_text_field( $team );
		$this->user       = $user ?: wp_get_current_user();
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
		if ( ! is_user_logged_in( $this->user ) ) {
			$this->return = $this->get_error( 'User is not logged in.' );
			return;
		}

		// Get the user's vote data.
		$existing = maiasknews_get_user_vote( $this->matchup_id, $this->user );

		// If already voted.
		if ( $existing['id'] && $existing['name'] ) {
			// If the same team.
			if ( $this->team === $existing['name'] ) {
				$this->return = $this->get_success( sprintf( __( 'Vote has not been saved. User has already voted for %s.', 'mai-asknews' ), $this->vote_name ) );
				return;
			}
		}

		// Add the vote.
		$comment_id = maiasknews_add_user_vote( $this->matchup_id, $this->team, $this->user );

		// If error.
		if ( is_wp_error( $comment_id ) ) {
			$this->return = $this->get_error( $comment_id->get_error_message() );
			return;
		}

		// Set the return message.
		$this->return = $this->get_success( sprintf( __( 'Vote saved for %s. Comment ID: %s', 'mai-asknews' ), $this->team, $comment_id ) );
	}
}