<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

use Alley\WP\Block_Converter\Block_Converter;

/**
 * The listener class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Listener {
	protected $body;
	protected $user;
	protected $return;

	/**
	 * Construct the class.
	 */
	function __construct( $body, $user = null ) {
		$this->body = is_string( $body ) ? json_decode( $body, true ) : $body;
		$this->user  = $user ?: wp_get_current_user();
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
		// If no capabilities.
		if ( ! user_can( $this->user, 'edit_posts' ) ) {
			$this->return = $this->get_error( 'User cannot edit posts.' );
			return;
		}

		// Prevent post_modified update.
		add_filter( 'wp_insert_post_data', [ $this, 'prevent_post_modified_update' ], 10, 4 );

		// Set the update flag.
		$update = false;

		// Get title and event date.
		list( $matchup_title, $matchup_datetime ) = explode( ',', $this->body['matchup'], 2 );
		$matchup_title = $this->body['game'];

		/***************************************************************
		 * Step 1 - Get the matchup post ID.
		 *
		 * Check for an existing matchup.
		 * If no matchup, create one.
		 * Set matchup ID.
		 ***************************************************************/

		// Check for an existing matchup.
		$matchup_ids = get_posts(
			[
				'post_type'    => 'matchup',
				'post_status'  => 'any',
				'post_parent'  => 0,
				'meta_key'     => 'event_uuid',
				'meta_value'   => $this->body['event_uuid'],
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 1,
			]
		);

		// Existing matchup, get post ID.
		if ( $matchup_ids ) {
			$matchup_id = $matchup_ids[0];
		}
		// If no matchup, create one.
		else {
			$matchup_args = [
				'post_type'   => 'matchup',
				'post_status' => 'publish',
				'post_parent' => 0,
				'post_author' => $this->user->ID,
				'post_title'  => $matchup_title,
				'post_name'   => sanitize_title( $matchup_title ) . ' ' . wp_date( 'Y-m-d', strtotime( $matchup_datetime ) ),
				'meta_input'  => [
					'event_uuid' => $this->body['event_uuid'],              // The id of this specific event.
					'event_date' => $this->get_date( $matchup_datetime ),   // The event date, formatted for WP.
				],
			];

			// Insert the matchup post.
			$matchup_id = wp_insert_post( $matchup_args );

			// If no post ID, send error.
			if ( ! $matchup_id ) {
				$this->return = $this->get_error( 'Failed during wp_insert_post()' );
				return;
			}

			// Bail if there was an error.
			if ( is_wp_error( $matchup_id ) ) {
				$this->return = $matchup_id;
				return;
			}
		}

		/***************************************************************
		 * Step 2 - Create or update the matchup insights.
		 *
		 * Builds the new insight post args.
		 * Check for existing insight to update.
		 * Creates or updates the insight.
		 * Set the matchup post ID as post meta.
		 * Set the league and season taxonomy terms.
		 * Set the related insights in the matchup post meta.
		 ***************************************************************/

		// Set default post args.
		$insight_args = [
			'post_type'    => 'matchup',
			'post_status'  => 'draft',
			'post_author'  => $this->user->ID,
			'post_parent'  => $matchup_id,
			'post_title'   => __( 'Insight', 'mai-asknews' ) . ' ' . $this->body['forecast_uuid'], // Updated later with update number.
			'post_excerpt' => $this->body['summary'],
			'meta_input'   => [
				'asknews_body'  => $this->body,                    // The full body for reference.
				'forecast_uuid' => $this->body['forecast_uuid'],   // The id of this specific forecast.
				// 'event_uuid'    => $this->body['event_uuid'],      // The id of the event, if this is a post to update.
			],
		];

		// Set post date.
		if ( isset( $this->body['date'] ) && $this->body['date'] ) {
			$post_date                     = $this->get_date( $this->body['date'] );
			$insight_args['post_date']     = $post_date;
			$insight_args['post_date_gmt'] = get_gmt_from_date( $post_date );
		}

		// Check for an existing insights.
		// This is mostly for reprocessing existing insights via CLI.
		$insight_ids = get_posts(
			[
				'post_type'    => 'matchup',
				'post_status'  => 'any',
				'post_parent'  => $matchup_id,
				'meta_key'     => 'forecast_uuid',
				'meta_value'   => $this->body['forecast_uuid'],
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 1,
			]
		);

		// If we have an existing post, update it.
		if ( $insight_ids ) {
			$update                      = true;
			$insight_args['ID']          = $insight_ids[0];
			$insight_args['post_status'] = get_post_status( $insight_ids[0] );
		}

		// Insert or update the post.
		$insight_id = wp_insert_post( $insight_args );

		// If no post ID, send error.
		if ( ! $insight_id ) {
			$this->return = $this->get_error( 'Failed during insight wp_insert_post()' );
			return;
		}

		// Bail if there was an error.
		if ( is_wp_error( $insight_id ) ) {
			$this->return = $insight_id;
			return;
		}

		// if ( '018907d3-5ec6-4dea-ab30-4f05e378ea8f' === $this->body['forecast_uuid'] ) {
		// 	ray( $this->body );
		// }

		// Gets all insights, sorted by date.
		$children_ids = get_posts(
			[
				'post_type'    => 'matchup',
				'post_status'  => 'any',
				'post_parent'  => $matchup_id,
				// 'meta_key'     => 'event_uuid',
				// 'meta_value'   => $this->body['event_uuid'],
				// 'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => -1,
			]
		);

		// Update matchup with these insights.
		// update_post_meta( $matchup_id, 'event_forecasts', $insights );

		// Get index of current insight in this list.
		// $current_index = array_search( $insight_id, $children_ids );

		// ray( $insight_id, $insights, $current_index );

		// if ( ! $current_index && 'Arizona Diamondbacks vs Cleveland Guardians' === $matchup_title ) {
		// 	ray( $this->body );
		// }

		// ray( $matchup_id . ', ' . $insight_id . ', ' . $current_index . ', ' . $this->body['forecast_uuid'] . ', ' . $matchup_title );

		// Updated title.
		// $updated_title = sprintf( '%s #%s', __( 'Update', 'mai-asknews' ), $current_index + 1 );

		// Update all insights titles with the update number.
		if ( $children_ids ) {
			foreach ( $children_ids as $index => $child_id ) {
				// Build title with index.
				$updated_title = sprintf( '%s #%s', __( 'Update', 'mai-asknews' ), $index + 1 );

				// Update post title.
				wp_update_post(
					[
						'ID'         => $child_id,
						'post_title' => $updated_title,
					]
				);
			}
		}

		// // Gets all insights, sorted by date.
		// $children_ids = get_posts(
		// 	[
		// 		'post_type'    => 'matchup',
		// 		'post_status'  => 'any',
		// 		'post_parent'  => 5750,
		// 		// 'meta_key'     => 'event_uuid',
		// 		// 'meta_value'   => $this->body['event_uuid'],
		// 		// 'meta_compare' => '=',
		// 		'fields'       => 'ids',
		// 		'numberposts'  => -1,
		// 	]
		// );

		// ray( $children_ids );

		// if ( in_array( 5972, $children_ids ) ) {
		// 	ray( $this->body );
		// }

		// Update post title with index.
		wp_update_post(
			[
				'ID'         => $insight_id,
				'post_title' => $updated_title,
				// 'post_name'  => sanitize_title_with_dashes( $updated_title ),
			]
		);

		// // Set post content. This runs after so we can attach images to the post ID.
		// $updated_id = wp_update_post(
		// 	[
		// 		'ID'           => $insight_id,
		// 		'post_content' => $this->handle_content( $content ),
		// 	]
		// );

		/***************************************************************
		 * Step 3 - Set the matchup and insight custom taxonomy terms.
		 *
		 * Set the league and season taxonomy terms.
		 ***************************************************************/

		// Set team vars.
		$home_team = $away_team = null;

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

		// Get teams. This will create them if they don't exist.
		$league_id  = $this->get_term( $this->body['sport'], 'league' );
		$league_ids = [
			$league_id,
			$home_team ? $this->get_term( $home_team, 'league', $league_id ) : '',
			$away_team ? $this->get_term( $away_team, 'league', $league_id ) : '',
		];

		// Remove empties.
		$league_ids = array_filter( $league_ids );

		// If we have categories.
		if ( $league_ids ) {
			// Set the post categories.
			wp_set_object_terms( $matchup_id, $league_ids, 'league', $append = false );
			wp_set_object_terms( $insight_id, $league_ids, 'league', $append = false );
		}

		// Start season va.
		$season_id = null;

		// Check season.
		if ( isset( $this->body['season'] ) ) {
			// Get or create the season term.
			$season_id = $this->get_term( $this->body['season'], 'season' );
		}
		// No seasons, use datetime.
		elseif ( $matchup_datetime ) {
			// Get year from event date.
			$year = wp_date( 'Y', strtotime( $matchup_datetime ) );

			// If we have a year.
			if ( $year ) {
				// Get or create the year term.
				$season_id = $this->get_term( $year, 'season' );
			}
		}

		// If we have a season term.
		if ( $season_id ) {
			// Set the post season.
			wp_set_object_terms( $matchup_id, $season_id, 'season', $append = false );
			wp_set_object_terms( $insight_id, $season_id, 'season', $append = false );
		}

		// Remove post_modified update filter.
		remove_filter( 'wp_insert_post_data', [ $this, 'prevent_post_modified_update' ], 10, 4 );

		$text         = $update ? ' updated successfully' : ' imported successfully';
		$this->return = $this->get_success( get_permalink( $insight_id ) . $text );
		return;
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
	 * @param string $value Any date string.
	 *
	 * @return string
	 */
	function get_date( $value ) {
		// Check if the value is already in 'Y-m-d H:i:s' format.
		if ( is_string( $value ) && false !== strtotime( $value ) && $value === date( 'Y-m-d H:i:s', strtotime( $value ) ) ) {
			return $value;
		}

		// If it's not numeric, likely not a timestamp.
		if ( ! is_numeric( $value ) ) {
			$value = strtotime( $value );
		}

		// Format the date.
		return wp_date( 'Y-m-d H:i:s', $value );
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
