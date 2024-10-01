<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The listener class.
 *
 * @since 0.8.0
 */
class Mai_AskNews_User_Points extends Mai_AskNews_Listener {
	protected $keys;
	protected $user;
	protected $points;
	protected $win_percent;
	protected $confidence;
	protected $return;

	/**
	 * Construct the class.
	 */
	function __construct( $user = null ) {
		$this->keys        = $this->get_all_keys();
		$this->user        = $this->get_user( $user );
		$this->points      = array_flip( $this->keys );
		$this->win_percent = [];
		$this->confidence  = [];

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
	 * @since 0.8.0
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
		$votes    = get_comments(
			[
				'type'    => 'pm_vote',
				'status'  => 'approve',
				'user_id' => $user_id,
				'orderby' => 'comment_date',
				'order'   => 'ASC',
			]
		);

		// Skip if no votes.
		if ( ! $votes ) {
			$this->return = 'No comments found for user ID: ' . get_the_author_meta( 'display_name', $user_id );
			return;
		}

		// Leagues.
		$leagues = maiasknews_get_all_leagues();
		$leagues = array_map( 'strtolower', $leagues );

		// // Start total votes.
		// // Can't use count() here because some votes may not count because matchups/outcomes may be missing, etc.
		// $total_votes = [ 'all' => 0 ];

		// // Loop through leagues and add to total votes.
		// foreach ( $leagues as $league ) {
		// 	$total_votes[ $league ] = 0;
		// }

		// Get required minimum votes. ~80% of the total games per team, per season.
		$req_min = [
			'all' => 200, // Random.
			'mlb' => 130, // ~80% of the total games per team, per season (162 games).
			'nba' => 66,  // ~80% of the total games per team, per season (82 games).
			'nfl' => 26,  // 1.5x the total games per team, per season (17 games).
			'nhl' => 66,  // ~80% of the total games per team, per season (82 games).
		];

		// Loop through and add leagues,
		// so we don't have to check isset later.
		foreach ( $leagues as $league ) {
			$req_min[ $league ] = 200; // Random.
		}

		// Loop through votes.
		foreach ( $votes as $comment ) {
			// Get the matchup ID.
			$matchup_id = $comment->comment_post_ID;

			// Get the matchup.
			$matchup = get_post( $matchup_id );

			// Skip if no matchup.
			if ( ! $matchup ) {
				$this->return = 'No matchup found for comment ID: ' . $comment->comment_ID;
				continue;
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



			// Add to total votes.
			// $total_votes['all']++;
			// $total_votes[ $league ]++;

			// Add total votes to the $th

			// Get karma.
			$karma = (int) $comment->comment_karma;

			// Handle win/loss/tie.
			switch ( $karma ) {
				// Win.
				case 1:
					$this->points['total_votes']++;
					$this->points["total_votes_{$league}"]++;
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
					$this->points['total_votes']++;
					$this->points["total_votes_{$league}"]++;
					$this->points['total_losses']++;
					$this->points["total_losses_{$league}"]++;
				break;
				// Tie.
				case 2:
					$this->points['total_votes']++;
					$this->points["total_votes_{$league}"]++;
					$this->points['total_ties']++;
					$this->points["total_ties_{$league}"]++;
				break;
			}
		}

		// Calculate win percent.
		$win_percent = $this->points['total_wins'] / $this->points['total_votes'] * 100;
		$win_percent = round( $win_percent, 2 ); // Round to 2 decimal places.

		// Set win percent.
		$this->win_percent['win_percent'] = $win_percent;

		// Loop through leagues and calculate win percent.
		foreach ( $leagues as $league ) {
			// // Skip if total votes not set.
			// if ( ! isset( $total_votes[ $league ] ) ) {
			// 	continue;
			// }

			// // Skip if total wins not set.
			// if ( ! isset( $this->points["total_wins_{$league}"] ) ) {
			// 	continue;
			// }

			// Skip if total votes is 0 or less.
			if ( $this->points["total_votes_{$league}"] <= 0 ) {
				continue;
			}

			// Calculate win percent.
			$league_win_percent = $this->points["total_wins_{$league}"] / $this->points["total_votes_{$league}"] * 100;
			$league_win_percent = round( $league_win_percent, 2 ); // Round to 2 decimal places.

			// Set win percent.
			$this->win_percent["win_percent_{$league}"] = $league_win_percent;
		}

		// Skipping all time confidence until we can figure out a formula.
		// Get confidence.
		// $this->confidence['confidence'] = $this->get_confidence( $total_votes['all'], $req_min['all'] );

		// Loop through leagues and get confidence.
		foreach ( $leagues as $league ) {
			// // Skip if total votes not set.
			// if ( ! isset( $total_votes[ $league ] ) ) {
			// 	continue;
			// }

			// // Skip if required minimum not set.
			// if ( ! isset( $req_min[ $league ] ) ) {
			// 	continue;
			// }

			// Set confidence.
			$this->confidence[ "confidence_{$league}" ] = $this->get_confidence( $this->points["total_votes_{$league}"], $req_min[ $league ] );
		}

		// Get XP. Total points x confidence.
		// Skipping all time xp until we can figure out a formula.
		// And cause we don't have confidence cause that is skipped too.
		// $xp = [
		// 	'xp_points' => $this->get_xp( $this->points['total_points'], $this->confidence['confidence'] ),
		// ];

		$xp = [];

		// Loop through leagues and get XP.
		foreach ( $leagues as $league ) {
			// // Skip if points not set.
			// if ( ! isset( $this->points["total_points_{$league}" ] ) ) {
			// 	continue;
			// }

			// // Skip if confidence not set.
			// if ( ! isset( $this->confidence[ "confidence_{$league}" ] ) ) {
			// 	continue;
			// }

			// Set XP.
			$xp[ "xp_points_{$league}" ] = $this->get_xp( $this->points[ "total_points_{$league}" ], $this->confidence[ "confidence_{$league}" ] );
		}

		// Loop through points and update them all.
		foreach ( $this->points as $key => $value ) {
			update_user_meta( $this->user->ID, $key, round( $value ) );
		}

		// Loop through win percent and update them all.
		foreach ( $this->win_percent as $key => $value ) {
			update_user_meta( $this->user->ID, $key, round( $value ) );
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
	 * @since 0.8.0
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
	 * @since 0.8.0
	 *
	 * @return float|int
	 */
	function get_xp( $points, $confidence ) {
		$xp = $points * $confidence;
		$xp = round( $xp );

		return $xp;
	}

	/**
	 * Get all keys.
	 *
	 * @since 0.8.0
	 *
	 * @return array
	 */
	function get_all_keys() {
		// Get leagues.
		$leagues = maiasknews_get_all_leagues();
		$leagues = array_map( 'strtolower', $leagues );

		// Get base keys.
		$keys = [
			'total_votes',
			'total_ties',
			'total_wins',
			'total_losses',
			'total_points',
		];

		// Loop through leagues and add keys.
		foreach ( $leagues as $league ) {
			$keys[] = "total_votes_{$league}";
			$keys[] = "total_ties_{$league}";
			$keys[] = "total_wins_{$league}";
			$keys[] = "total_losses_{$league}";
			$keys[] = "total_points_{$league}";
		}

		return $keys;
	}
}
