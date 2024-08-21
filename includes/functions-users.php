<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

// rcp_user_has_active_membership( $user_id = 0 );
// rcp_user_has_free_membership( $user_id = 0 )
// rcp_user_has_paid_membership( $user_id = 0 );
// rcp_user_has_expired_membership( $user_id = 0 );
// rcp_user_has_access( $user_id = 0, $access_level_needed = 0 )

/**
 * If the user has access to view restricted content.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_is_user() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$cache = current_user_can( 'read' )
		|| maiasknews_has_free_membership()
		|| maiasknews_has_paid_membership()
		|| maiasknews_has_pro_membership();

	return $cache;
}

/**
 * If the user has access to non-pro-level restricted content.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_has_access( $league = '' ) {
	// Get current page leage and user levels.
	$league = $league ?: maiasknews_get_page_league();
	$levels = maiasknews_get_membership_ids();

	// If no league or no levels, bail.
	if ( ! ( $league && $levels ) ) {
		return false;
	}

	// Hardcoded paid membership IDs, including pro.
	$paid = [
		'MLB' => [
			1, // Monthly,
			2, // Season,
			3, // Pro,
		],
		'NBA' => [
			10, // Monthly,
			11, // Season,
			12, // Pro,
		],
		'NFL' => [
			4, // Monthly,
			5, // Season,
			6, // Pro,
		],
		'NHL' => [
			7, // Monthly,
			8, // Season,
			9, // Pro,
		],
	];

	// If they have any of these ids.
	return (bool) isset( $paid[ $league ] ) ? array_intersect( $levels, $paid[ $league ] ) : false;
}

/**
 * If the user has access to pro-level restricted content.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_has_pro_access( $league ) {
	// Get current page leage and user levels.
	$league = $league ?: maiasknews_get_page_league();
	$levels = maiasknews_get_membership_ids();

	// If no league or no levels, bail.
	if ( ! ( $league && $levels ) ) {
		return false;
	}

	// Hardcoded pro membership IDs.
	$pro = [
		'MLB' => [ 3 ],
		'NBA' => [ 12 ],
		'NFL' => [ 6 ],
		'NHL' => [ 9 ],
	];

	// If they have any of these ids.
	return (bool) isset( $pro[ $league ] ) ? array_intersect( $levels, $pro[ $league ] ) : false;
}

/**
 * If the current user has a free membership.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_has_free_membership() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$cache = function_exists( 'rcp_user_has_free_membership' ) && rcp_user_has_free_membership();

	return $cache;
}

/**
 * If the current user has a paid membership.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_has_paid_membership() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$cache = current_user_can( 'manage_options' ) || ( function_exists( 'rcp_user_has_paid_membership' ) && rcp_user_has_paid_membership() );

	return $cache;
}

/**
 * If the current user has a pro membership.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_has_pro_membership() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$cache = current_user_can( 'manage_options' ) || ( maiasknews_has_paid_membership() && maiasknews_has_access( 8 ) );

	return $cache;
}

/**
 * If the current user has a specific access level.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_has_access_level( $level ) {
	static $cache = [];

	if ( isset( $cache[ $level ] ) ) {
		return $cache[ $level ];
	}

	$cache[ $level ] = current_user_can( 'manage_options' ) || ( function_exists( 'rcp_user_has_access' ) && rcp_user_has_access( get_current_user_id(), $level ) );

	return $cache[ $level ];
}

/**
 * If the current user has an active membership.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_has_active_membership() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$cache = current_user_can( 'manage_options' ) || ( function_exists( 'rcp_user_has_active_membership' ) && rcp_user_has_active_membership() );

	return $cache;
}

/**
 * If the current user has an expired membership.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_has_expired_membership() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$cache = function_exists( 'rcp_user_has_expired_membership' ) && rcp_user_has_expired_membership();

	return $cache;
}

/**
 * Get the user current and active membership level IDs.
 *
 * @since 0.1.0
 *
 * @return string
 */
function maiasknews_get_membership_ids() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$cache = function_exists( 'rcp_get_customer_membership_level_ids' ) ? rcp_get_customer_membership_level_ids() : [];

	return $cache;
}