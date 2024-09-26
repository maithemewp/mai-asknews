<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The listener class.
 *
 * @since TBD
 */
class Mai_AskNews_User_Points extends Mai_AskNews_Listener {
	protected $keys;
	protected $user;
	protected $points;
	protected $confidence;
	protected $return;

	/**
	 * Construct the class.
	 */
	function __construct( $user = null ) {
		$this->keys       = $this->get_all_keys();
		$this->user       = $this->get_user( $user );
		$this->points     = array_flip( $this->keys );
		$this->confidence = [];

		// Set all point values to 0.
		foreach ( $this->points as $key => $value ) {
			$this->points[ $key ] = 0;
		}

		// Run.
		$this->run();
	}

	/**
	 * Run the class.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function run() {
		// Bail if not a valid user.
		if ( ! $this->user ) {
			$this->return = $this->get_error( 'User not found.' );
			return;
		}

		// Loop through and delete them all.
		foreach ( $this->keys as $key ) {
			delete_user_meta( $this->user->ID, $key );
		}

		// Get all comments.
		$user_id  = $this->user->ID;
		$comments = get_comments(
			[
				'type'    => 'pm_vote',
				'status'  => 'approve',
				'user_id' => $user_id,
				'orderby' => 'comment_date',
				'order'   => 'ASC',
			]
		);

		// Skip if no comments.
		if ( ! $comments ) {
			$this->return = 'No comments found for user ID: ' . get_the_author_meta( 'display_name', $user_id );
			return;
		}

		// Filter by pm_vote comment type.
		$votes = array_filter( $comments, function( $comment ) {
			return 'pm_vote' === $comment->comment_type;
		});

		// Skip if no votes.
		if ( ! $votes ) {
			$this->return = 'No votes found for user ID: ' . get_the_author_meta( 'display_name', $user_id );
			return;
		}

		// Leagues.
		$leagues = maiasknews_get_all_leagues();
		$leagues = array_map( 'strtolower', $leagues );

		// Start total votes.
		// Can't use count() here because some votes may not count because matchups/outcomes may be missing, etc.
		$total_votes = [ 'all' => 0 ];

		// Loop through leagues and add to total votes.
		foreach ( $leagues as $league ) {
			$total_votes[ $league ] = 0;
		}

		// Get required minimum votes. ~80% of the total games per team, per season.
		$req_min = [
			'all' => 200, // Random.
			'mlb' => 130, // ~80% of the total games per team, per season (162 games).
			'nba' => 66,  // ~80% of the total games per team, per season (82 games).
			'nfl' => 26,  // 1.5x the total games per team, per season (17 games).
			'nhl' => 66,  // ~80% of the total games per team, per season (82 games).
		];

		// Loop through votes.
		foreach ( $votes as $comment ) {
			// Get the matchup ID.
			$matchup_id = $comment->comment_post_ID;

			// Get the matchup.
			$matchup = get_post( $matchup_id );

			// Skip if no matchup.
			if ( ! $matchup ) {
				$this->return = 'No matchup found for comment ID: ' . $comment->comment_ID;
				return;
			}

			// Get the matchup outcome.
			$outcome = get_post_meta( $matchup_id, 'asknews_outcome', true );

			// Skip if no outcome.
			if ( ! $outcome ) {
				continue;
			}

			// Get body and league.
			$body   = maiasknews_get_insight_body( $matchup_id );
			$league = strtolower( maiasknews_get_key( 'sport', $body ) );

			// Bail if no body or league.
			if ( ! ( $body && $league ) ) {
				continue;
			}

			// Get the user's vote.
			$user_vote = sanitize_text_field( $comment->comment_content );

			// Skip if no vote.
			if ( ! $user_vote ) {
				continue;
			}

			// Maybe add to leagues.
			if ( ! in_array( $league, $leagues ) ) {
				$leagues[] = $league;
			}

			// Maybe add to votes.
			if ( ! isset( $total_votes[ $league ] ) ) {
				$total_votes[ $league ] = 0;
			}

			// Add to total votes.
			$total_votes['all']++;
			$total_votes[ $league ]++;

			// Get karma.
			$karma = (int) $comment->comment_karma;

			// Handle win/loss/tie.
			switch ( $karma ) {
				// Win.
				case 1:
					$this->points['total_wins']++;
					$this->points["total_wins_{$league}"]++;

					// Get points.
					$odds_data        = maiasknews_get_odds_data( $body );
					$odds_average     = $user_vote && isset( $odds_data[ $user_vote ]['average'] ) ? $odds_data[ $user_vote ]['average'] : null;
					$user_vote_points = ! is_null( $odds_average ) ? maiasknews_get_odds_points( $odds_average ) : null;

					// If we have points, update them.
					if ( ! is_null( $user_vote_points ) ) {
						$this->points['total_points'] += $user_vote_points;
						$this->points["total_points_{$league}"] += $user_vote_points;
					}
				break;
				// Loss.
				case -1:
					$this->points['total_losses']++;
					$this->points["total_losses_{$league}"]++;
				break;
				// Tie.
				case 2:
					$this->points['total_ties']++;
					$this->points["total_ties_{$league}"]++;
				break;
			}

			// Skip if no total votes.
			if ( ! isset( $total_votes[ $league ] ) ) {
				continue;
			}

			// Skip if no required minimum.
			if ( ! isset( $req_min[ $league ] ) ) {
				continue;
			}
		}

		// Get confidence.
		$this->confidence['confidence'] = $this->get_confidence( $total_votes['all'], $req_min['all'] );

		// Loop through leagues and get confidence.
		foreach ( $leagues as $league ) {
			$this->confidence[ "confidence_{$league}" ] = $this->get_confidence( $total_votes[ $league ], $req_min[ $league ] );
		}

		// Get XP. Total points x confidence.
		$xp = [
			'xp_points' => $this->get_xp( $this->points['total_points'], $this->confidence['confidence'] ),
		];

		// Loop through leagues and get XP.
		foreach ( $leagues as $league ) {
			$xp[ "xp_points_{$league}" ] = $this->get_xp( $this->points[ "total_points_{$league}" ], $this->confidence[ "confidence_{$league}" ] );
		}

		// Loop through and update them all.
		foreach ( $this->points as $key => $value ) {
			update_user_meta( $this->user->ID, $key, $value );
		}

		// Loop through confidence and update them all.
		foreach ( $this->confidence as $key => $value ) {
			update_user_meta( $this->user->ID, $key, $value );
		}

		// Loop through XP and update them all.
		foreach ( $xp as $key => $value ) {
			update_user_meta( $this->user->ID, $key, $value );
		}
	}

	/**
	 * Get the confidence score.
	 *
	 * @since TBD
	 *
	 * @return float|int Between 0-1.
	 */
	function get_confidence( $votes, $req_min ) {
		$confidence = tanh( (2 * $votes) / $req_min );
		$confidence = round( $confidence, 3 );
		$confidence = maiasknews_parse_float( $confidence );

		return $confidence;
	}

	/**
	 * Get the XP.
	 *
	 * @since TBD
	 *
	 * @return float|int
	 */
	function get_xp( $points, $confidence ) {
		$xp = $points * $confidence;
		$xp = round( $xp, 2 );
		$xp = maiasknews_parse_float( $xp );

		return $xp;
	}

	/**
	 * Get all keys.
	 *
	 * @since TBD
	 *
	 * @return array
	 */
	function get_all_keys() {
		// Get leagues.
		$leagues = maiasknews_get_all_leagues();
		$leagues = array_map( 'strtolower', $leagues );

		// Get base keys.
		$keys = [
			'total_ties',
			'total_wins',
			'total_losses',
			'total_points',
		];

		// Loop through leagues and add keys.
		foreach ( $leagues as $league ) {
			$keys[] = "total_ties_{$league}";
			$keys[] = "total_wins_{$league}";
			$keys[] = "total_losses_{$league}";
			$keys[] = "total_points_{$league}";
		}

		return $keys;
	}
}
