<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The admin post commentary class.
 *
 * TODO: Make a AjaxPost class and extend it here.
 *
 * @since TBD
 */
class Mai_AskNews_Ajax_Post_Commentary {
	protected $ajax = null;

	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'admin_post_pm_commentary_submission',        [ $this, 'handle_submission' ] );
		add_action( 'admin_post_nopriv_pm_commentary_submission', [ $this, 'handle_submission' ] );
		add_action( 'wp_ajax_pm_commentary_submission',           [ $this, 'handle_submission' ] );
		add_action( 'wp_ajax_nopriv_pm_commentary_submission',    [ $this, 'handle_submission' ] );
	}

	/**
	 * Handles the vote submission.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function handle_submission() {
		// Get the post data.
		$args = wp_parse_args(
			$_POST,
			[
				'commentary_user_id'    => null,
				'commentary_matchup_id' => null,
				'commentary_text'       => null,
				'ajax'                  => null,
			]
		);

		// Sanitize.
		$this->ajax = rest_sanitize_boolean( $args['ajax'] );
		$user_id    = absint( $args['commentary_user_id'] );
		$matchup_id = absint( $args['commentary_matchup_id'] );
		$commentary = wp_kses_post( $args['commentary_text'] );
		$commentary = str_replace( '<p><br></p>', '', $commentary ); // For Quill extra line breaks.
		$commentary = preg_replace( '/<(\w+)(\s+[^>]+)>/', '<$1>', $commentary ); // Remove all attributes.
		$commentary = preg_replace( '/<\/?span[^>]*>/', '', $commentary ); // Remove <span> tags but keep the content

		ray( $args );

		// Verify nonce for security.
		if ( ! isset( $_POST['commentary_nonce'] ) || ! wp_verify_nonce( $_POST['commentary_nonce'], 'pm_commentary_submission' ) ) {
			$this->handle_error( __( 'Commentary submission security check failed.', 'mai-asknews' ) );
		}

		// Bail if no user ID.
		if ( ! $user_id ) {
			$this->handle_error( __( 'No user ID found.', 'mai-asknews' ) );
		}

		// Get user.
		$user = get_user_by( 'ID', $user_id );

		// Bail if no user.
		if ( ! $user ) {
			$this->handle_error( __( 'No user found.', 'mai-asknews' ) );
		}

		// Bail if no matchup ID.
		if ( ! $matchup_id ) {
			$this->handle_error( 'No matchup ID found.', 'mai-asknews' );
		}

		// Bail if no commentary.
		if ( ! $commentary ) {
			$this->handle_error( 'No commentary found.', 'mai-asknews' );
		}

		// Insert the commentary.
		$comment_id = wp_insert_comment(
			[
				'comment_type'         => 'pm_commentary',
				'comment_approved'     => 1,
				'comment_post_ID'      => $matchup_id,
				'comment_content'      => $commentary,
				'user_id'              => $user->ID,
				'comment_author'       => $user->user_login,
				'comment_author_email' => $user->user_email,
				'comment_author_url'   => $user->user_url,
			]
		);

		// Bail if no comment ID.
		if ( ! $comment_id ) {
			$this->handle_error( __( 'Commentary could not be added.', 'mai-asknews' ) );
		}

		// Bail if error.
		if ( is_wp_error( $comment_id ) ) {
			$this->handle_error( $comment_id->get_error_message() );
		}

		// If ajax, send it.
		if ( $this->ajax ) {
			// Send success response.
			wp_send_json_success( [ 'message' => __( 'Your commentary was successfully added!', 'mai-asknews' ) ] );
			exit;
		}

		// Redirect to the matchup with the commentary.
		wp_safe_redirect( get_permalink( $matchup_id ) . '#commentary' );
		exit;
	}

	/**
	 * Handles errors.
	 *
	 * @since TBD
	 *
	 * @param string $message The error message.
	 *
	 * @return void
	 */
	function handle_error( $message ) {
		if ( $this->ajax ) {
			wp_send_json_error( [ 'message' => $message ] );
		} else {
			wp_die( $message );
		}
		exit;
	}
}