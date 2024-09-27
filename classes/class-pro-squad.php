<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The Pro Squad class.
 *
 * @since 0.8.0
 */
class Mai_AskNews_Pro_Squad {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'init', [ $this, 'maybe_add_role' ] );
	}

	/**
	 * Add user role if it doesn't exist.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	function maybe_add_role() {
		if ( get_role( 'pro_squad' ) ) {
			return;
		}

		// Add role.
		add_role( 'pro_squad', __( 'Pro Squad', 'mai-asknews' ), get_role( 'subscriber' )->capabilities );
	}
}