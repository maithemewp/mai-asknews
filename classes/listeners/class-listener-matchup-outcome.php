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
		$counts = $this->update_comments();

		// Return success.
		$this->return = $this->get_success( $counts['votes'] . ' votes updated and ' . $counts['users'] . ' users updated for matchup ' . $this->matchup_id . ' ' . get_permalink( $this->matchup_id ) );
	}

	/**
	 * Update all votes for this matchup.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	function update_comments() {
		$comments = 0;
		$users    = 0;

		// Get all votes for this matchup.
		$votes = get_comments(
			[
				'comment_type' => 'pm_vote',
				'status'       => 'approve',
				'post_id'      => $this->matchup_id,
			]
		);

		// Loop through all votes.
		foreach ( $votes as $comment ) {
			// Get teh user data.
			$user_id   = $comment->user_id;
			$user_vote = $this->sanitize_name( $comment->comment_content );
			$user_won  = $this->winner === $user_vote;

			// Update comment, 1 for win, -1 for loss.
			$update = wp_update_comment(
				[
					'comment_ID'    => $comment->comment_ID,
					'comment_karma' => $user_won ? 1 : -1,
				]
			);

			// Maybe update comment count.
			if ( $update ) {
				$comments++;
			}

			// Get user.
			$user = get_user_by( 'ID', $user_id );

			// If we have a user, update their wins, losses, and points.
			if ( $user ) {
				// Get existing values.
				$wins   = (int) get_user_meta( $user_id, 'total_wins', true );
				$losses = (int) get_user_meta( $user_id, 'total_losses', true );
				$points = (int) get_user_meta( $user_id, 'total_points', true );

				// If user won.
				if ( $user_won ) {
					update_user_meta( $user_id, 'total_wins', $wins + 1 );
					update_user_meta( $user_id, 'total_points', $points + 1 );
				} else {
					update_user_meta( $user_id, 'total_losses', $losses + 1 );
				}

				$users++;
			}
		}

		return [
			'votes' => $comments,
			'users' => $users,
		];
	}

	// function get_vote_counts($matchup_id) {
	// 	global $wpdb;
	// 	$results = $wpdb->get_results($wpdb->prepare(
	// 		"SELECT comment_content AS vote_option, COUNT(*) AS vote_count
	// 		 FROM $wpdb->comments
	// 		 WHERE comment_post_ID = %d AND comment_type = 'vote' AND comment_approved = 1
	// 		 GROUP BY comment_content",
	// 		$matchup_id
	// 	));
	// 	return $results;
	// }

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
