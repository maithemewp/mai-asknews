<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Get the archive vote box for a matchup.
 *
 * @since TBD
 *
 * @return string
 */
function maiasknews_get_archive_vote_box() {
	$html = '';

	// Get the user and matchup data.
	$matchup_id = get_the_ID();
	$user       = maiasknews_get_user();
	$data       = maiasknews_get_matchup_data( $matchup_id );

	// Bail if no matchup data.
	if ( ! ( $data && array_values( $data ) ) ) {
		return $html;
	}

	// Get start timestamp.
	$timestamp = get_post_meta( $matchup_id, 'event_date', true );

	// Bail if no timestamp, we can't vote if we don't know when the game starts.
	if ( ! $timestamp ) {
		return $html;
	}

	// Set vars.
	$has_access   = $user && $user->ID;
	$started      = time() > $timestamp;
	$show_outcome = $started && $data['winner_full'] && $data['loser_full'];
	$show_vote    = ! $started && $has_access;

	// Bail if conditions are not met.
	if ( ! ( $show_outcome || $show_vote ) ) {
		return $html;
	}

	// Add data.
	$data['redirect']        = home_url( add_query_arg( [] ) );
	$data['show_prediction'] = maiasknews_has_access( $data['league'] );

	// Start vote box.
	$html .= '<div class="pm-vote pm-vote-archive">';
		// If showing outcome.
		if ( $show_outcome ) {
			// Heading.
			$html .= sprintf( '<p class="pm-vote__heading">%s</p>', __( 'Game Results', 'mai-asknews' ) );

			// Outcome box.
			$html .= maiasknews_get_outcome_box( $data );
		}
		// If not started and they have access to vote.
		elseif ( $has_access ) {
			// Set first.
			static $first = true;

			// Maybe enqueue JS.
			if ( $first ) {
				// Enqueue.
				maiasknews_enqueue_scripts( maiasknews_get_vote_elements( 'selected' ) );

				// Set first to false.
				$first = false;
			}

			// Heading.
			$html .= sprintf( '<p class="pm-vote__heading">%s</p>', __( 'Make Your Pick', 'mai-asknews' ) );

			// Vote form.
			$html .= maiasknews_get_vote_form( $data );
		}
		// Not started, and no access to vote.
		else {
			// Heading.
			$html .= sprintf( '<p class="pm-vote__heading">%s</p>', __( 'Make Your Pick', 'mai-asknews' ) );

			// Faux vote form.
			$html .= maiasknews_get_faux_vote_form( $data );
		}

	$html .= '</div>';

	return $html;
}

/**
 * Get the singular vote box for a matchup.
 *
 * @since TBD
 *
 * @return string
 */
function maiasknews_get_singular_vote_box() {
	$html = '';

	// Get the user and matchup data.
	$matchup_id = get_the_ID();
	$user       = maiasknews_get_user();
	$data       = maiasknews_get_matchup_data( $matchup_id );

	// Bail if no matchup data.
	if ( ! ( $data && array_values( $data ) ) ) {
		return $html;
	}

	// Get start timestamp.
	$timestamp = get_post_meta( $matchup_id, 'event_date', true );

	// Bail if no timestamp, we can't vote if we don't know when the game starts.
	if ( ! $timestamp ) {
		return $html;
	}

	// Set vars.
	$has_access   = $user && $user->ID;
	$started      = time() > $timestamp;
	$show_outcome = $started && $data['winner_full'] && $data['loser_full'];
	$show_vote    = ! $started && $has_access;

	// Add data.
	$data['redirect']        = get_permalink( $matchup_id ) . '#vote';
	$data['show_prediction'] = maiasknews_has_access( $data['league'] );

	// If the game has started.
	if ( $started ) {
		// If we have an outcome.
		if ( $data['winner_full'] && $data['loser_full'] ) {
			$heading = sprintf( '<h2 class="pm-vote__heading">%s</h2>', __( 'Game Results', 'mai-asknews' ) );
			$desc    = [];
			$desc[]  = __( 'The game has ended.', 'mai-asknews' );

			// If we have both scores.
			if ( $data['winner_score'] && $data['loser_score'] ) {
				$scores = sprintf( " %s - %s", $data['winner_score'], $data['loser_score'] );
				$desc[] = sprintf( __( 'The %s defeated the %s%s.', 'mai-asknews' ), $data['winner_short'], $data['loser_short'], $scores );
			} else {
				$desc[] = __( 'Sorry, we don\'t have scores at this time.', 'mai-asknews' );
			}

			// To string.
			$desc = $desc ? sprintf( '<p>%s</p>', implode( ' ', $desc ) ) : '';
		}
		// No outcome.
		else {
			$heading = sprintf( '<h2 class="pm-vote__heading">%s</h2>', __( 'Game Info', 'mai-asknews' ) );
			$desc    = sprintf( '<p>%s</p>', __( 'Voting is closed after the game starts. Once we analyze the results and calculate your points we\'ll update here.', 'mai-asknews' ) );
		}
	}
	// If they have already voted.
	elseif ( $data['vote'] ) {
		$heading = sprintf( '<h2 class="pm-vote__heading">%s</h2>', __( 'Make Your Pick', 'mai-asknews' ) );
		$desc    = sprintf( '<p>%s</p>', sprintf( __( 'You have already voted for the %s; leave it as is or change your vote before game time.', 'mai-asknews' ), $data['vote'] ) );
	}
	// Fallback for voting.
	else {
		$heading = sprintf( '<h2 class="pm-vote__heading">%s</h2>', __( 'Make Your Pick', 'mai-asknews' ) );
		$desc    = sprintf( '<p>%s</p>', __( 'Compete with others to beat the SportsDesk Bot.<br>Who do you think will win?', 'mai-asknews' ) );
	}

	// Start vote box.
	$html .= '<div id="vote" class="pm-vote pm-vote-single">';
		// Get user avatar.
		$avatar = get_avatar( get_current_user_id(), 128 );

		// Display the avatar.
		$html .= sprintf( '<div class="pm-vote__avatar">%s</div>', $avatar );

		// If showing outcome.
		if ( $show_outcome ) {
			// Heading.
			$html .= $heading;

			// Outcome box.
			$html .= maiasknews_get_outcome_box( $data );
		}
		// If not started and they have access to vote.
		elseif ( $has_access ) {
			// Enqueue JS.
			maiasknews_enqueue_scripts( maiasknews_get_vote_elements( 'selected' ) );

			// Heading.
			$html .= $heading;

			// Vote form.
			$html .= maiasknews_get_vote_form( $data );
		}
		// Not started, and no access to vote.
		else {
			// Heading.
			$html .= $heading;

			// Faux vote form.
			$html .= maiasknews_get_faux_vote_form( $data );
		}

		// Description.
		$html .= $desc;

	$html .= '</div>';

	return $html;
}

/**
 * Get the outcome box.
 *
 * @since TBD
 *
 * @param array $data The matchup data.
 *
 * @return string
 */
function maiasknews_get_outcome_box( $data ) {
	$html       = '';
	// $prediction = $data['show_prediction'] ? maiasknews_get_vote_elements( 'prediction' ) : '';
	$prediction = $data['user_id'] ? maiasknews_get_vote_elements( 'prediction' ) : '';
	$selected   = maiasknews_get_vote_elements( 'selected' );
	$status     = maiasknews_get_vote_elements( 'winner' );
	$home_class = $data['winner_home'] ? 'winner' : 'loser';
	$away_class = ! $data['winner_home'] ? 'winner' : 'loser';

	// Build the markup.
	$html .= '<div class="pm-outcome pm-actions">';
		// Away team first.
		$html .= '<div class="pm-outcome__col pm-action__col away">';
			// Status.
			if ( ! $data['winner_home'] ) {
				$html .= $status;
			}

			// Content.
			$html .= '<div class="pm-outcome__content">';
				$html .= sprintf( '<p class="pm-outcome__team %s">%s</p>', $away_class, $data['away_short'] );
				$html .= sprintf( '<p class="pm-outcome__score %s">%s</p>', $away_class, ! $data['winner_home'] ? $data['winner_score'] : $data['loser_score'] );
			$html .= '</div>';

			// Bot prediction.
			if ( $data['away_full'] && $data['prediction'] && $data['away_full'] === $data['prediction'] ) {
				$html .= $prediction;
			}

			// User vote.
			if ( $data['away_full'] && $data['vote'] && $data['away_full'] === $data['vote'] ) {
				$html .= $selected;
			}
		$html .= '</div>';

		// Home team second.
		$html .= '<div class="pm-outcome__col pm-action__col home">';
			// Status.
			if ( $data['winner_home'] ) {
				$html .= $status;
			}

			// Content.
			$html .= '<div class="pm-outcome__content">';
				$html .= sprintf( '<p class="pm-outcome__team %s">%s</p>', $home_class, $data['home_short'] );
				$html .= sprintf( '<p class="pm-outcome__score %s">%s</p>', $home_class, $data['winner_home'] ? $data['winner_score'] : $data['loser_score'] );
			$html .= '</div>';

			// Bot prediction.
			if ( $data['home_full'] && $data['prediction'] && $data['home_full'] === $data['prediction'] ) {
				$html .= $prediction;
			}

			// User vote.
			if ( $data['home_full'] && $data['vote'] && $data['home_full'] === $data['vote'] ) {
				$html .= $selected;
			}
		$html .= '</div>';
	$html .= '</div>';

	return $html;
}

/**
 * Get the vote form.
 *
 * @since TBD
 *
 * @param array $data The matchup data.
 *
 * @param string $home_name  The home team name.
 * @param string $away_name  The away team name.
 * @param string $prediction The prediction markup.
 * @param string $vote       The vote markup.
 * @param int    $matchup_id The matchup ID.
 * @param int    $user_id    The user ID.
 * @param string $redirect   The redirect URL.
 *
 * @return string
 */
function maiasknews_get_vote_form( $data ) {
	$html       = '';
	$prediction = $data['show_prediction'] ? maiasknews_get_vote_elements( 'prediction' ) : '';
	$selected   = maiasknews_get_vote_elements( 'selected' );

	// Get the vote form markup.
	$html .= sprintf( '<form class="pm-vote__form" action="%s" method="post">', esc_url( admin_url('admin-post.php') ) );
		// Team buttons.
		$html .= '<div class="pm-vote__buttons pm-actions">';
			$html .= '<div class="pm-vote__button pm-action__col away">';
				$html .= sprintf( '<button class="button button-small" type="submit" name="team" value="%s"%s>%s</button>', $data['away_full'], $data['vote'] === $data['away_full'] ? ' disabled' : '', $data['away_short'] );

				// Bot prediction.
				if ( $data['away_full'] && $data['prediction'] && $data['away_full'] === $data['prediction'] ) {
					$html .= $prediction;
				}

				// User vote.
				if ( $data['away_full'] && $data['vote'] && $data['away_full'] === $data['vote'] ) {
					$html .= $selected;
				}
			$html .= '</div>';
			$html .= '<div class="pm-vote__button pm-action__col home">';
				$html .= sprintf( '<button class="button button-small" type="submit" name="team" value="%s"%s>%s</button>', $data['home_full'], $data['vote'] === $data['home_full'] ? ' disabled' : '', $data['home_short'] );

				// Bot prediction.
				if ( $data['home_full'] && $data['prediction'] && $data['home_full'] === $data['prediction'] ) {
					$html .= $prediction;
				}

				// User vote.
				if ( $data['home_full'] && $data['vote'] && $data['home_full'] === $data['vote'] ) {
					$html .= $selected;
				}
			$html .= '</div>';
		$html .= '</div>';

		// Hidden inputs.
		$html .= '<input type="hidden" name="action" value="pm_vote_submission">';
		$html .= sprintf( '<input type="hidden" name="user_id" value="%s">', $data['user_id'] );
		$html .= sprintf( '<input type="hidden" name="matchup_id" value="%s">', $data['matchup_id'] );
		$html .= sprintf( '<input type="hidden" name="redirect" value="%s">', esc_url( $data['redirect'] ) );
		$html .= wp_nonce_field( 'pm_vote_nonce', '_wpnonce', true, false );
	$html .= '</form>';

	return $html;
}

/**
 * Get the faux vote form.
 *
 * @since TBD
 *
 * @param array $data The matchup data.
 *
 * @return string
 */
function maiasknews_get_faux_vote_form( $data ) {
	$html = '';

	// Display the faux vote form.
	$html .= '<div class="pm-vote__form">';
		// Build url.
		$url = add_query_arg(
			[
				'rcp_redirect' => home_url( add_query_arg( [] ) ),
			],
			get_permalink( 7049 )
		);

		// Build the markup.
		$html .= '<div class="pm-vote__buttons pm-actions">';
			$html .= '<div class="pm-vote__button pm-action__col away">';
				$html .= sprintf( '<a class="button button-small" href="%s">%s</a>', esc_url( $url ), $data['away_short'] );
			$html .= '</div>';
			$html .= '<div class="pm-vote__button pm-action__col home">';
				$html .= sprintf( '<a class="button button-small" href="%s">%s</a>', esc_url( $url ), $data['home_short'] );
			$html .= '</div>';
		$html .= '</div>';
	$html .= '</div>';

	return $html;
}

/**
 * Enqueue the vote scripts.
 *
 * @since TBD
 *
 * @param string $selected The selected markup.
 *
 * @return string
 */
function maiasknews_get_vote_elements( $element ) {
	static $cache = [];

	// If we have cache.
	if ( $cache ) {
		if ( $element ) {
			return isset( $cache[ $element ] ) ? $cache[ $element ] : '';
		}

		return $cache;
	}

	// Set cache.
	$cache = [
		'prediction' => sprintf( '<span class="pm-outcome__prediction">%s</span>', __( 'Bot pick', 'promatchups' ) ),
		'selected'   => sprintf( '<span class="pm-outcome__selected">%s</span>', __( 'Your pick', 'promatchups' ) ),
		'winner'     => sprintf( '<span class="pm-outcome__status">%s</span>', __( 'Winner', 'mai-asknews' ) ),
	];

	if ( $element ) {
		return isset( $cache[ $element ] ) ? $cache[ $element ] : '';
	}

	return $cache;
}

/**
 * Add a user's vote for a matchup.
 * Checks for and updates existing votes.
 *
 * @since 0.2.0
 *
 * @param int         $matchup_id The matchup ID or object.
 * @param string      $team       The team name.
 * @param int|WP_User $user       The user ID or object.
 *
 * @return WP_Error|int The comment ID.
 */
function maiasknews_add_user_vote( $matchup_id, $team, $user = null ) {
	$comment_id = 0;

	// If no user, get current.
	if ( ! $user ) {
		$user = wp_get_current_user();
	}
	// If user is ID, get object.
	elseif ( is_numeric( $user ) ) {
		$user = get_user_by( 'ID', $user );
	}

	// Bail if no user.
	if ( ! $user ) {
		return $comment_id;
	}

	// Build comment data.
	$args = [
		'comment_type'         => 'pm_vote',
		'comment_approved'     => 1,
		'comment_post_ID'      => $matchup_id,
		'comment_content'      => $team,
		'user_id'              => $user->ID,
		'comment_author'       => $user->user_login,
		'comment_author_email' => $user->user_email,
		'comment_author_url'   => $user->user_url,
	];

	// Get existing vote.
	$existing = maiasknews_get_user_vote( $matchup_id, $user );

	// If user has voted, update.
	if ( $existing['id'] ) {
		// Set comment ID.
		$args['comment_ID'] = $existing['id'];

		// Update the comment.
		$comment_id = wp_update_comment( $args );
	}
	// New vote.
	else {
		// Insert the comment.
		$comment_id = wp_insert_comment( $args );
	}

	return $comment_id;
}

/**
 * Get the user's vote for a matchup.
 *
 * @since 0.2.0
 *
 * @param int         $matchup_id The matchup ID or object.
 * @param int|WP_User $user       The user ID or object.
 *
 * @return array
 */
function maiasknews_get_user_vote( $matchup_id, $user = null ) {
	$vote = [
		'id'   => null,
		'name' => null,
	];

	// If no user, get current.
	if ( ! $user ) {
		$user = wp_get_current_user();
	}
	// If user is ID, get object.
	elseif ( is_numeric( $user ) ) {
		$user = get_user_by( 'ID', $user );
	}

	// Bail if no user.
	if ( ! $user ) {
		return $vote;
	}

	// Get user votes.
	$comments = get_comments(
		[
			'comment_type' => 'pm_vote',
			'post_id'      => $matchup_id,
			'user_id'      => $user->ID,
			'number'       => 1,
		]
	);

	// If user has voted.
	if ( $comments ) {
		$existing = reset( $comments );
		$vote     = [
			'id'   => $existing->comment_ID,
			'name' => $existing->comment_content,
		];
	}

	return $vote;
}