<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The listener class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Listener {

	/**
	 * Get user.
	 *
	 * @since TBD
	 *
	 * @param int|WP_User|null $user The user ID or object. Null for current user.
	 *
	 * @return WP_User
	 */
	function get_user( $user ) {
		$user = is_numeric( $user ) ? get_user_by( 'ID', $user ) : $user;
		$user = ! $user && is_null( $user ) ? wp_get_current_user() : $user;

		return $user;
	}

	/**
	 * Maybe send json error.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The error message.
	 * @param int    $code    The error code.
	 *
	 * @return
	 */
	function get_error( $message, $code = null ) {
		return new WP_Error( 'mai-asknews error', $message, [ 'status' => $code ] );
	}

	/**
	 * Maybe send json success.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The success message.
	 *
	 * @return JSON|void
	 */
	function get_success( $message ) {
		return $message;
	}

	/**
	 * Get the response.
	 *
	 * @since 0.1.0
	 *
	 * @return WP_REST_Response
	 */
	function get_response() {
		if ( is_wp_error( $this->return ) ) {
			$response = $this->return;
		} else {
			$response = new WP_REST_Response( $this->return );
			$response->set_status( 200 );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Downloads a remote file and inserts it into the WP Media Library.
	 *
	 * @access private
	 *
	 * @see https://developer.wordpress.org/reference/functions/media_handle_sideload/
	 *
	 * @param string $url       HTTP URL address of a remote file.
	 * @param int    $post_id   The post ID the media is associated with.
	 * @param string $file_name The name of the file to use.
	 *
	 * @return int|WP_Error The ID of the attachment or a WP_Error on failure.
	 */
	function upload_image( $image_url, $post_id, $file_name = '' ) {
		// Make sure we have the functions we need.
		if ( ! function_exists( 'download_url' ) || ! function_exists( 'media_handle_sideload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}

		// Save original image url.
		$original_url = $image_url;

		// Check if there is an attachment with original_url meta key and value of $image_url.
		$existing_ids = get_posts(
			[
				'post_type'    => 'attachment',
				'post_status'  => 'any',
				'meta_key'     => 'original_url',
				'meta_value'   => $original_url,
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 1,
			]
		);

		// Bail if the image already exists.
		if ( $existing_ids ) {
			return $existing_ids[0];
		}

		// Set vars.
		$destination_file = null;
		$image_contents   = file_get_contents( $image_url );

		// Bail if no image contents.
		if ( ! $image_contents ) {
			return 0;
		}

		// Get the extension without query params.
		$image_ext = pathinfo( $image_url, PATHINFO_EXTENSION );
		$image_ext = explode( '?', $image_ext );
		$image_ext = reset( $image_ext );
		$image_ext = $image_ext && in_array( $image_ext, [ 'jpg', 'jpeg', 'png', 'gif' ] ) ? $image_ext : 'jpg';

		// Remove query params from the extension
		$image_name  = $file_name ? $file_name : pathinfo( $image_url, PATHINFO_FILENAME );
		$image_name  = urldecode( $image_name );
		$image_name  = preg_replace( '/[^a-zA-Z0-9\-]/', '', $image_name );
		$image_name  = sanitize_title_with_dashes( $image_name );
		$image_name .= '.' . $image_ext;

		// Get the uploads directory.
		$upload_dir = wp_get_upload_dir();
		$upload_url = $upload_dir['baseurl'];

		// If image contents.
		if ( $image_contents ) {
			// Specify the path to the destination directory within uploads.
			$destination_dir = $upload_dir['basedir'] . '/mai-asknews/';

			// Create the destination directory if it doesn't exist.
			if ( ! file_exists( $destination_dir ) ) {
				mkdir( $destination_dir, 0755, true );
			}

			// Specify the path to the destination file.
			$destination_file = $destination_dir . $image_name;

			// Save the image to the destination file.
			file_put_contents( $destination_file, $image_contents );

			// Bail if the file doesn't exist.
			if ( ! file_exists( $destination_file ) ) {
				return 0;
			}

			// Build the image url.
			$image_url = untrailingslashit( $upload_url ) . '/mai-asknews/' . $image_name;
		}

		// Filter to disable SSL verification on local.
		$ssl_verify = function( $args, $url ) {
			$args['sslverify'] = 'local' !== wp_get_environment_type();
			return $args;
		};

		// Add the filter.
		add_filter( 'http_request_args', $ssl_verify, 10, 2 );

		// Build a temp url.
		$tmp = download_url( $image_url );

		// Remove the filter.
		remove_filter( 'http_request_args', $ssl_verify, 10, 2 );

		// If a destination file.
		if ( $destination_file ) {
			// Remove the temp file.
			@unlink( $destination_file );
		}

		// Bail if error.
		if ( is_wp_error( $tmp ) ) {
			return 0;
		}

		// Build the file array.
		$file_array = [
			'name'     => basename( $image_url ),
			'tmp_name' => $tmp,
		];

		// Add the image to the media library.
		$image_id = media_handle_sideload( $file_array, $post_id );

		// Bail if error.
		if ( is_wp_error( $image_id ) ) {
			// Remove the original image and return the error.
			@unlink( $file_array[ 'tmp_name' ] );
			return $image_id;
		}

		// Remove the original image.
		@unlink( $file_array[ 'tmp_name' ] );

		// Set image meta for reference later, and allyinteractive block importer.
		update_post_meta( $image_id, 'original_url', $original_url );

		return $image_id;
	}
}
