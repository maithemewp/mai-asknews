<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

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
		'name'       => null,
		'id'         => null,
		'comment_id' => null,
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