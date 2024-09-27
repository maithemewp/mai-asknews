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
	protected $tie;
	protected $winner;
	protected $return;

	/**
	 * Construct the class.
	 */
	function __construct( $matchup_id ) {
		$this->matchup_id = (int) $matchup_id;
		$this->body       = maiasknews_get_insight_body( $this->matchup_id );
		$this->outcome    = get_post_meta( $this->matchup_id, 'asknews_outcome', true );
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
		// If no body or outcome, return error.
		if ( ! $this->body ) {
			$this->return = $this->get_error( 'No AskNews body data found for post ID: ' . $this->matchup_id . ' ' . get_permalink( $this->matchup_id ) );
			return;
		}

		// If no outcome, return error.
		if ( ! $this->outcome ) {
			$this->return = $this->get_error( 'No AskNews outcome data found for post ID: ' . $this->matchup_id . ' ' . get_permalink( $this->matchup_id ) );
			return;
		}

		// Set vars.
		$prediction   = isset( $this->body['choice'] ) ? $this->sanitize_name( $this->body['choice'] ) : '';
		$winner_team  = isset( $this->outcome['winner']['team'] ) ? $this->sanitize_name( $this->outcome['winner']['team'] ) : '';
		$winner_score = isset( $this->outcome['winner']['score'] ) ? $this->outcome['winner']['score'] : '';
		$loser_team   = isset( $this->outcome['loser']['team'] ) ? $this->sanitize_name( $this->outcome['loser']['team'] ) : '';
		$loser_score  = isset( $this->outcome['loser']['score'] ) ? $this->outcome['loser']['score'] : '';

		// If we don't have prediction and winner/loser, return error.
		if ( ! ( $prediction && $winner_team && $loser_team ) ) {
			$this->return = $this->get_error( 'No prediction or winner/loser found.' );
			return;
		}

		// Set prediction, if tied, and winning team.
		$this->prediction = $prediction;
		$this->tie        = $winner_score && $loser_score && $winner_score === $loser_score;
		$this->winner     = ! $this->tie ? $winner_team : '';

		// Update all votes for this matchup.
		$counts = $this->update_comments();

		// Return success.
		$this->return = $this->get_success( $counts['votes'] . ' votes updated and ' . $counts['users'] . ' users updated for matchup ' . $this->matchup_id . ' ' . get_permalink( $this->matchup_id ) );
	}

	/**
	 * Update all votes for this matchup.
	 * Skips if comment karma is already set,
	 * which means the vote and points were already updated.
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
				'type'    => 'pm_vote',
				'status'  => 'approve',
				'post_id' => $this->matchup_id,
			]
		);

		// Loop through all votes.
		foreach ( $votes as $comment ) {
			// Get the user.
			$user_id  = $comment->user_id;
			$user     = get_user_by( 'ID', $user_id );
			$user_won = null;

			// Skip if no user.
			if ( ! $user ) {
				continue;
			}

			// Bail if comment karma is already set.
			// This prevents us from updating the same comment multiple times,
			// and more importantly, from updating the same user meta multiple times.
			if ( 0 !== (int) $comment->comment_karma ) {
				continue;
			}

			// Get karma. 1 for win, -1 for loss, 2 for a tie. 0 is default for karma.
			// If tie, set karma to 2.
			if ( $this->tie ) {
				$karma = 2;
			}
			// If we have a winner.
			elseif ( $this->winner ) {
				// Get user vote.
				$user_vote = $this->sanitize_name( $comment->comment_content );

				// Bail if no use vote.
				if ( ! $user_vote ) {
					continue;
				}

				// Set karma based on user vote.
				$user_won  = $this->winner === $user_vote;
				$karma     = $user_won ? 1 : -1;
			}

			// Update comment,
			$update = wp_update_comment(
				[
					'comment_ID'    => $comment->comment_ID,
					'comment_karma' => $karma,
				]
			);

			// Maybe update comment count.
			if ( $update && ! is_wp_error( $update ) ) {
				$comments++;
			}

			// Update the user's points.
			$listener = new Mai_AskNews_User_Points( $user );
			$response = $listener->get_response();

			// Increment users.
			$users++;
		}

		return [
			'votes' => $comments,
			'users' => $users,
		];
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
