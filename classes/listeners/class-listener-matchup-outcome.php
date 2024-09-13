<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The listener class.
 *
 * @since 0.2.0
 */
class Mai_AskNews_Matchup_Outcome_Listener extends Mai_AskNews_Listener {
	protected $matchup_id;
	protected $body;
	protected $outcome;
	protected $prediction;
	protected $winner;
	protected $return;

	/**
	 * Construct the class.
	 */
	function __construct( $matchup_id, $body, $outcome ) {
		$this->matchup_id = (int) $matchup_id;
		$this->body       = $body;
		$this->outcome    = $outcome;
		$this->prediction = '';
		$this->winner     = '';

		$this->run();
	}

	/**
	 * Run the logic.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	function run() {
		// Get prediction and winning teams.
		$this->prediction = isset( $this->body['choice'] ) ? $this->sanitize_name( $this->body['choice'] ) : '';
		$this->winner     = isset( $this->outcome['winner']['team'] ) ? $this->sanitize_name( $this->outcome['winner']['team'] ) : '';

		// If we don't have prediction and winner, return error.
		if ( ! ( $this->prediction && $this->winner ) ) {
			$this->return = $this->get_error( 'No prediction or winner found.' );
			return;
		}

		// Update all votes for this matchup.
		$count = $this->update_comments();

		// Return success.
		$this->return = $this->get_success( $count . ' votes updated for matchup ' . $this->matchup_id . ' ' . get_permalink( $this->matchup_id ) );
	}

	/**
	 * Update all votes for this matchup.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	function update_comments() {
		$updated = 0;

		// Get all votes for this matchup.
		$votes = get_comments(
			[
				'comment_type' => 'pm_vote',
				'post_id'      => $this->matchup_id,
				'approve'      => 1,
			]
		);

		// Loop through all votes.
		foreach ( $votes as $comment ) {
			// Update comment, 1 for win, -1 for loss.
			$update = wp_update_comment(
				[
					'comment_ID'    => $comment->comment_ID,
					'comment_karma' => $this->winner === $this->sanitize_name( $comment->comment_content ) ? 1 : -1,
				]
			);

			if ( $update ) {
				$updated++;
			}
		}

		return $updated;
	}

	/**
	 * Sanitize a team name.
	 *
	 * @param string $team
	 *
	 * @return string
	 */
	function sanitize_name( $team ) {
		$team = trim( $team );
		$team = sanitize_text_field( $team );

		return $team;
	}
}
