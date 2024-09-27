<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The dashboard class.
 *
 * @since TBD
 */
class Mai_AskNews_Dashboard {
	protected $dashboard_id;

	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Run the hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function hooks() {
		add_filter( 'template_redirect', [ $this, 'maybe_redirect_dashboard' ] );
	}

	/**
	 * Redirect to dashboard if not logged in and trying to access a dashboard page.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function maybe_redirect_dashboard() {
		// Bail if user is not logged in.
		if ( is_user_logged_in() ) {
			return;
		}

		// Get global RCP options.
		global $rcp_options;

		// Set the dashboard ID.
		$this->dashboard_id = isset( $rcp_options['account_page'] ) ? $rcp_options['account_page'] : 0;

		// Bail if not a child of /dashboard/.
		if ( ! $this->is_dashboard_page() ) {
			return;
		}

		// Redirect to main dashboard page.
		wp_safe_redirect( get_permalink( $this->dashboard_id ) );
		exit;
	}

	/**
	 * Check if the current page is a dashboard page.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	function is_dashboard_page() {
		// Bail if we don't have a dashboard or we're not viewing a page.
		if ( ! ( $this->dashboard_id && is_page() ) ) {
			return false;
		}

		return in_array( $this->dashboard_id, (array) get_post_ancestors( get_the_ID() ) );
	}
}