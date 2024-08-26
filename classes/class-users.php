<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The users class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Users {
	/**
	 * Construct the class.
	 *
	 * @since 0.1.0
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Run the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		add_filter( 'rcp_can_upgrade_subscription',    '__return_false' );
		add_action( 'admin_init',                      [ $this, 'dashboad_redirect' ] );
		add_action( 'after_setup_theme',               [ $this, 'disable_admin_bar' ] );
	}

	/**
	 * Redirect non-admins to the homepage.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function dashboad_redirect() {
		if ( wp_doing_ajax() || current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_safe_redirect( home_url() );
		exit;
	}

	/**
	 * Disable the admin bar for non-contributors.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function disable_admin_bar() {
		if ( current_user_can( 'edit_posts' ) ) {
			return;
		}

		show_admin_bar( false );
	}
}