<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The comments class.
 *
 * @since 0.8.0
 */
class Mai_AskNews_Comments {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Run the hooks.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'pre_get_comments',             [ $this, 'filter_comment_type' ] );
		add_filter( 'admin_comment_types_dropdown', [ $this, 'add_comment_type' ] );
	}

	/**
	 * Modify the comment query to handle the custom comment types.
	 *
	 * @since 0.8.0
	 *
	 * @param WP_Comment_Query $query The comment query.
	 *
	 * @return void
	 */
	function filter_comment_type( $query ) {
		// Bail if not in the dashboard.
		if ( ! is_admin() ) {
			return;
		}

		global $pagenow;

		// Bail if not on the comments page.
		if ( 'edit-comments.php' !== $pagenow ) {
			return;
		}

		// Get comment type.
		$type = isset( $_GET['comment_type'] ) ? sanitize_text_field( $_GET['comment_type'] ) : '';

		// Bail if not the custom comment type.
		if ( ! ( $type && in_array( $type, [ 'pm_vote', 'pm_commentary' ] ) ) ) {
			return;
		}

		// Set the comment type query var.
		$query->query_vars['type'] = $type;
	}

	/**
	 * Add custom comment types to the comment type dropdown.
	 *
	 * @since 0.8.0
	 *
	 * @param array $types The comment types.
	 *
	 * @return array
	 */
	function add_comment_type( $types ) {
		$types['pm_vote']       = __( 'Votes', 'mai-asknews' );
		$types['pm_commentary'] = __( 'Commentary', 'mai-asknews' );

		return $types;
	}
}