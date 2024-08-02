<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

use Alley\WP\Block_Converter\Block_Converter;

// add_action( 'genesis_before_loop', function() {
// 	// $meta = get_post_meta( get_the_ID() );
// 	$meta = get_post_meta( get_the_ID(), 'asknews_body', true );
// 	dump( $meta );
// });

class Mai_AskNews_Listener {
	protected $body;

	/**
	 * Construct the class.
	 */
	function __construct( $body ) {
		$this->body = is_string( $body ) ? json_decode( $body, true ) : $body;
		$this->run();
	}

	/**
	 * Run the logic.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function run() {
		// Prevent post_modified update.
		add_filter( 'wp_insert_post_data', [ $this, 'prevent_post_modified_update' ], 10, 4 );

		// Start update as false.
		$update = false;

		// Get title and event date.
		list( $title, $datetime ) = explode( ',', $this->body['matchup'] );

		// Set default post args.
		$post_args = [
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_excerpt' => $this->body['summary'],
			'meta_input'   => [
				'asknews_body'  => $this->body,   // The full body for reference.
				'forecast_uuid' => $this->body['forecast_uuid'],     // The id of this specific forecast.
				'event_uuid'    => $this->body['event_uuid'],        // The id of the event, if this is a post to update.
				'event_date'    => $this->get_date( $datetime ),     // The event date, formatted for WP.
			],
		];

		// Check for an existing post.
		$forecast_ids = get_posts(
			[
				'post_type'    => 'post',
				'post_status'  => 'any',
				'meta_key'     => 'forecast_uuid',
				'meta_value'   => $this->body['forecast_uuid'],
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 1,
			]
		);

		// If we have an existing post, update it.
		if ( $forecast_ids ) {
			$update                   = true;
			$post_args['ID']          = $forecast_ids[0];
			$post_args['post_status'] = get_post_status( $forecast_ids[0] );
		}
		// New post, set slug.
		else {
			$post_args['post_name'] = sanitize_title( $title ) . ' ' . wp_date( 'Y-m-d', strtotime( $datetime ) );
		}

		// Insert or update the post.
		$post_id = wp_insert_post( $post_args );

		// Bail if we don't have a post ID or there was an error.
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			if ( is_wp_error( $post_id ) ) {
				return $this->send_json_error( $post_id->get_error_message(), $post_id->get_error_code() );
			}

			return $this->send_json_error( 'Failed during wp_insert_post()' );
		}

		// // Set post content. This runs after so we can attach images to the post ID.
		// $updated_id = wp_update_post(
		// 	[
		// 		'ID'           => $post_id,
		// 		'post_content' => $this->handle_content( $content ),
		// 	]
		// );

		// Set team vars.
		$home_team = $away_team = '';

		// Set home team.
		if ( isset( $this->body['home_team_name'] ) ) {
			$home_team = $this->body['home_team_name'];
		} elseif ( isset( $this->body['home_team'] ) ) {
			$home_team = explode( ' ', $this->body['home_team'] );
			$home_team = end( $home_team );
		}

		// Set away team.
		if ( isset( $this->body['away_team_name'] ) ) {
			$away_team = $this->body['away_team_name'];
		} elseif ( isset( $this->body['away_team'] ) ) {
			$away_team = explode( ' ', $this->body['away_team'] );
			$away_team = end( $away_team );
		}

		// Get categories. This will create them if they don't exist.
		$category_id  = $this->get_term( $this->body['sport'], 'category' );
		$category_ids = [
			$category_id,
			$home_team ? $this->get_term( $home_team, 'category', $category_id ) : '',
			$away_team ? $this->get_term( $away_team, 'category', $category_id ) : '',
		];

		// Remove empty categories.
		$category_ids = array_filter( $category_ids );

		// If we have categories.
		if ( $category_ids ) {
			// Set the post categories.
			wp_set_object_terms( $post_id, $category_ids, 'category', $append = false );
		}

		// TODO: Check for season in JSON, if we can get it added.

		// If we have a datetime.
		if ( $datetime ) {
			// Get year from event date.
			$year = wp_date( 'Y', strtotime( $datetime ) );

			// If we have a year.
			if ( $year ) {
				// Get or create the year term.
				$year_id = $this->get_term( $year, 'season' );

				// If we have a year term.
				if ( $year_id ) {
					// Set the post season.
					$season_ids = wp_set_object_terms( $post_id, $year_id, 'season', $append = false );
				}
			}
		}

		// Check for other posts about this event.
		$related_ids = get_posts(
			[
				'post_type'    => 'post',
				'post_status'  => 'any',
				'meta_key'     => 'event_uuid',
				'meta_value'   => $this->body['event_uuid'],
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 100,
			]
		);

		// If we have existing posts, update the related.
		if ( $related_ids ) {
			$related_ids[] = $post_id;

			// Update the related posts.
			foreach( $related_ids as $related_id ) {
				// Remove the current post from the forecast ids array.
				$to_update = array_diff( $related_ids, [ $related_id ] );

				// Skip if no other posts.
				if ( ! $to_update ) {
					continue;
				}

				// Update post meta.
				update_post_meta( $related_id, 'event_forecasts', $to_update );
			}
		}

		// Remove post_modified update filter.
		remove_filter( 'wp_insert_post_data', [ $this, 'prevent_post_modified_update' ], 10, 4 );

		$text = $update ? ' updated successfully' : ' imported successfully';
		return $this->send_json_success( get_permalink( $post_id ) . $text );
	}

	/**
	 * Gets a term ID by name. If it doesn't exist, it creates it.
	 *
	 * @since 0.1.0
	 *
	 * @param string $term_name The term name.
	 * @param string $taxonomy  The taxonomy.
	 * @param int    $parent_id The parent term ID.
	 *
	 * @return int|null The term ID.
	 */
	function get_term( $term_name, $taxonomy, $parent_id = 0 ) {
		// Check if the term already exists.
		$term    = get_term_by( 'name', $term_name, $taxonomy );
		$term_id = $term ? $term->term_id : null;

		// If the term doesn't exist, create it.
		if ( ! $term_id ) {
			$args    = is_taxonomy_hierarchical( $taxonomy ) ? [ 'parent' => $parent_id ] : [];
			$term    = wp_insert_term( $term_name, $taxonomy, $args );
			$term_id = ! is_wp_error( $term ) ? $term['term_id'] : null;
		}

		return $term_id;
	}

	/**
	 * Gets a formmatted date string in the current website timezone,
	 * for use in `wp_insert_post()`.
	 *
	 * @since 0.1.0
	 *
	 * @param string $date_time Any date string that works with `strtotime()`.
	 *
	 * @return string
	 */
	function get_date( $date_time ) {
		return wp_date( 'Y-m-d H:i:s', strtotime( $date_time ) );
	}

	/**
	 * Maybe send json error.
	 *
	 * @since 0.1.0
	 *
	 * @return JSON|void
	 */
	function send_json_error( $message, $code = null ) {
		return wp_send_json_error( $message, $code );
	}

	/**
	 * Maybe send json success.
	 *
	 * @since 0.1.0
	 *
	 * @return JSON|void
	 */
	function send_json_success( $message, $code = null ) {
		return wp_send_json_success( $message, $code );
	}

	/**
	 * Convert blocks.
	 *
	 * @since 0.1.0
	 *
	 * @param array $content The array of items.
	 *
	 * @return string The converted content.
	 */
	function handle_content( $content ) {
		// Convert to blocks.
		if ( $content && ! has_blocks( $content ) && class_exists( 'Alley\WP\Block_Converter\Block_Converter' ) ) {
			$converter = new Block_Converter( $content );
			$content   = $converter->convert();
		}

		return $content;
	}

	/**
	 * Downloads a remote file and inserts it into the WP Media Library.
	 *
	 * @access private
	 *
	 * @see https://developer.wordpress.org/reference/functions/media_handle_sideload/
	 *
	 * @param string $url     HTTP URL address of a remote file.
	 * @param int    $post_id The post ID the media is associated with.
	 *
	 * @return int|WP_Error The ID of the attachment or a WP_Error on failure.
	 */
	function upload_image( $image_url, $post_id ) {
		// Make sure we have the functions we need.
		if ( ! function_exists( 'download_url' ) || ! function_exists( 'media_handle_sideload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}

		// Check if there is an attachment with asknews_url meta key and value of $image_url.
		$existing_ids = get_posts(
			[
				'post_type'    => 'attachment',
				'post_status'  => 'any',
				'meta_key'     => 'asknews_url',
				'meta_value'   => $image_url,
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 1,
			]
		);

		// Bail if the image already exists.
		if ( $existing_ids ) {
			return $existing_ids[0];
		}

		// Set the unitedrobots URL.
		$asknews_url = $image_url;

		// Check if the image is a streetview image.
		$streetview_url   = str_contains( $image_url, 'maps.googleapis.com/maps/api/streetview' );
		$destination_file = null;

		// If streetview.
		if ( $streetview_url ) {
			$image_url      = html_entity_decode( $image_url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ); // Some urls had `&amp;` instead of just `&`.
			$image_url      = str_replace( ' ', '%20', $image_url ); // Some urls had spaces in the location, like `streetview?location=33.58829796031562, -78.98837933325625`.
			$image_contents = file_get_contents( $image_url );
			$image_hashed   = md5( $image_url ) . '.jpg';

			// Get the uploads directory.
			$upload_dir = wp_get_upload_dir();
			$upload_url = $upload_dir['baseurl'];

			if ( $image_contents ) {
				// Specify the path to the destination directory within uploads.
				$destination_dir = $upload_dir['basedir'] . '/mai-asknews/';

				// Create the destination directory if it doesn't exist.
				if ( ! file_exists( $destination_dir ) ) {
					mkdir( $destination_dir, 0755, true );
				}

				// Specify the path to the destination file.
				$destination_file = $destination_dir . $image_hashed;

				// Save the image to the destination file.
				file_put_contents( $destination_file, $image_contents );

				// Bail if the file doesn't exist.
				if ( ! file_exists( $destination_file ) ) {
					return 0;
				}

				$image_url = $image_hashed;
			}

			// Build the image url.
			$image_url = untrailingslashit( $upload_url ) . '/mai-asknews/' . $image_hashed;
		}

		// Build a temp url.
		$tmp = download_url( $image_url );

		// If streetview and we have a destination file.
		if ( $streetview_url && $destination_file ) {
			// Remove the temp file.
			@unlink( $destination_file );
		}

		// Bail if error.
		if ( is_wp_error( $tmp ) ) {
			// mai_asknews_logger( $tmp->get_error_code() . ': upload_image() 1 ' . $image_url . ' ' . $tmp->get_error_message() );
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
			// mai_asknews_logger( $image_id->get_error_code() . ': upload_image() 2 ' . $image_url . ' ' . $image_id->get_error_message() );

			// Remove the original image and return the error.
			@unlink( $file_array[ 'tmp_name' ] );
			return $image_id;
		}

		// Remove the original image.
		@unlink( $file_array[ 'tmp_name' ] );

		// Set the external url for possible reference later.
		update_post_meta( $image_id, 'asknews_url', $asknews_url );

		// Set image meta for allyinteractive block importer.
		update_post_meta( $image_id, 'original_url', wp_get_attachment_image_url( $image_id, 'full' ) );

		return $image_id;
	}

	/**
	 * Prevent post_modified update.
	 *
	 * @since 0.1.0
	 *
	 * @param array $data                An array of slashed, sanitized, and processed post data.
	 * @param array $postarr             An array of sanitized (and slashed) but otherwise unmodified post data.
	 * @param array $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed post data as originally passed to wp_insert_post() .
	 * @param bool  $update              Whether this is an existing post being updated.
	 *
	 * @return array
	 */
	function prevent_post_modified_update( $data, $postarr, $unsanitized_postarr, $update ) {
		if ( $update && ! empty( $postarr['ID'] ) ) {
			// Get the existing post.
			$existing = get_post( $postarr['ID'] );

			// Preserve the current modified dates.
			if ( $existing ) {
				$data['post_modified']     = $existing->post_modified;
				$data['post_modified_gmt'] = $existing->post_modified_gmt;
			}
		}

		return $data;
	}
}
