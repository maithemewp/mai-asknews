<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The archives class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Archives_Leagues {
	/**
	 * Construct the class.
	 */
	function __construct() {
		add_action( 'template_redirect', [ $this, 'hooks' ] );
	}

	/**
	 * Run the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		if ( ! is_tax( 'league' ) ) {
			return;
		}
	}
}
