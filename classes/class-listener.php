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

		// Set matchup title and datetime.
		list( $matchup_title, $matchup_datetime ) = explode( ',', $this->body['matchup'], 2 );

		// Set vars.
		$matchup_title     = trim( $matchup_title );
		$matchup_datetime  = trim( $matchup_datetime );
		$matchup_date      = $this->get_date( $matchup_datetime );
		$matchup_timestamp = $this->get_date_timestamp( $matchup_datetime );
		$insight_timestamp = $this->get_date_timestamp( $this->body['date'] );

		/***************************************************************
		 * Get the matchup post ID.
		 *
		 * Set team vars.
		 * Set matchup title and data.
		 * Check for an existing matchup.
		 * If no matchup, create one.
		 * Set matchup ID.
		 ***************************************************************/

		// Set team vars.
		$teams     = $this->body['sport'] ? maiasknews_get_teams( $this->body['sport'] ) : [];
		$home_team = null;
		$away_team = null;

		// Check if we have a home team in the array.
		if ( $teams && isset( $this->body['home_team'] ) ) {
			$sports_teams = maiasknews_get_teams( $this->body['sport'] );

			// If any of the sports teams keys are in the home_team string.
			foreach ( $sports_teams as $team => $city ) {
				if ( str_contains( $this->body['home_team'], $team ) ) {
					$home_team = $team;
					break;
				}
			}
		} else {
			// Set home team.
			if ( isset( $this->body['home_team_name'] ) ) {
				$home_team = $this->body['home_team_name'];
			} elseif ( isset( $this->body['home_team'] ) ) {
				$home_team = explode( ' ', $this->body['home_team'] );
				$home_team = end( $home_team );
			}
		}

		// Check if we have an away team in the array.
		if ( $teams && isset( $this->body['away_team'] ) ) {
			$sports_teams = maiasknews_get_teams( $this->body['sport'] );

			// If any of the sports teams keys are in the away_team string.
			foreach ( $sports_teams as $team => $city ) {
				if ( str_contains( $this->body['away_team'], $team ) ) {
					$away_team = $team;
					break;
				}
			}
		} else {
			// Set away team.
			if ( isset( $this->body['away_team_name'] ) ) {
				$away_team = $this->body['away_team_name'];
			} elseif ( isset( $this->body['away_team'] ) ) {
				$away_team = explode( ' ', $this->body['away_team'] );
				$away_team = end( $away_team );
			}
		}

		// If home and away, override matchup title. Away vs Home.
		if ( $home_team && $away_team ) {
			$matchup_title = sprintf( '%s vs %s', $away_team, $home_team );
		}

		// Check for an existing matchup.
		$matchup_ids = get_posts(
			[
				'post_type'    => 'matchup',
				'post_status'  => 'any',
				'meta_key'     => 'event_uuid',
				'meta_value'   => $this->body['event_uuid'],
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 1,
			]
		);

		// Existing matchup, get post ID.
		if ( $matchup_ids ) {
			$needs_title   = $matchup_title !== get_the_title( $matchup_ids[0] );
			$needs_summary = ! get_the_excerpt( $matchup_ids[0] ) && isset( $this->body['summary'] ) && $this->body['summary'];

			// If title or summary needs updating.
			if ( $needs_title || $needs_summary ) {
				$update_args = [
					'ID' => $matchup_ids[0]
				];

				// If title needs updating.
				if ( $needs_title ) {
					$update_args['post_title'] = $matchup_title;

					// TODO: Remove this after all matchups are updated. This was to convert `Home vs Away` to `Away vs Home`.
					$update_args['post_name']  = sanitize_title( $matchup_title ) . ' ' . wp_date( 'Y-m-d', $matchup_timestamp );
				}

				// If summary needs updating.
				if ( $needs_summary ) {
					$update_args['post_excerpt'] = $this->body['summary'];
				}

				// Update the matchup.
				$matchup_id = wp_update_post( $update_args );

				// If no post ID, send error.
				if ( ! $matchup_id ) {
					$this->return = $this->get_error( 'Failed during wp_update_post()' );
					return;
				}

				// Bail if there was an error.
				if ( is_wp_error( $matchup_id ) ) {
					$this->return = $matchup_id;
					return;
				}
			}
			// Set the ID.
			else {
				$matchup_id = $matchup_ids[0];
			}
		}
		// If no matchup, create one.
		else {
			$matchup_args = [
				'post_type'    => 'matchup',
				'post_status'  => 'publish',
				'post_author'  => $this->user->ID,
				'post_title'   => $matchup_title,
				'post_name'    => sanitize_title( $matchup_title ) . ' ' . wp_date( 'Y-m-d', $matchup_timestamp ),
				'post_excerpt' => $this->body['summary'],
				'meta_input'   => [
					'event_uuid' => $this->body['event_uuid'], // The id of this specific event.
					'event_date' => $matchup_timestamp,        // The event date timestamp.
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

		// Meta title for Rank Math.
		if ( $home_team && $away_team ) {
			$meta_title = sprintf( '%s vs %s Matchup %s', $away_team, $home_team, wp_date( 'F j, Y', $matchup_timestamp ) );

			update_post_meta( $matchup_id, 'rank_math_title', $meta_title );
		}

		/***************************************************************
		 * Create or update the matchup insights.
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
			'post_type'    => 'insight',
			'post_status'  => 'publish',
			'post_author'  => $this->user->ID,
			'post_title'   => __( 'Insight', 'mai-asknews' ) . ' ' . $this->body['forecast_uuid'], // Updated later.
			'post_name'    => $this->body['forecast_uuid'],
			'post_excerpt' => $this->body['summary'],
			'meta_input'   => [
				'asknews_body'  => $this->body,                    // The full body for reference.
				'forecast_uuid' => $this->body['forecast_uuid'],   // The id of this specific forecast.
				'event_uuid'    => $this->body['event_uuid'],      // The id of the event, if this is a post to update.
			],
		];

		// Set post date.
		if ( $insight_timestamp ) {
			$insight_args['post_date_gmt'] = $this->get_date( $insight_timestamp );
		}

		// Check for an existing insights.
		// This is mostly for reprocessing existing insights via CLI.
		$insight_ids = get_posts(
			[
				'post_type'    => 'insight',
				'post_status'  => 'any',
				'meta_key'     => 'forecast_uuid',
				'meta_value'   => $this->body['forecast_uuid'],
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => -1,
			]
		);

		// If we have an existing post, update it.
		// This is only to fix/alter existing insights.
		if ( $insight_ids ) {
			$update                      = true;
			$insight_args['ID']          = $insight_ids[0];
			$insight_args['post_name']   = $this->body['forecast_uuid'];
			$insight_args['post_status'] = 'publish';

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

		/***************************************************************
		 * Update Matchup Tags.
		 ***************************************************************/

		// Get people.
		$name_ids = [];
		$people   = $this->body['key_people'];

		// If we have people.
		if ( $people ) {
			// Loop through people.
			foreach ( $people as $person ) {
				// Early versions were a string of the person's name.
				if ( is_string( $person ) ) {
					$name = $person;
				}
				// We should be getting dict/array now.
				else {
					$name = isset( $person['name'] ) ? $person['name'] : '';
				}

				// Skip if no name.
				if ( ! $name ) {
					continue;
				}

				// Get or create the tag.
				$name_ids[] = $this->get_term( $name, 'matchup_tag' );
			}
		}

		// Remove empties.
		$name_ids = array_filter( $name_ids );

		// If names.
		if ( $name_ids ) {
			// Set the tags.
			wp_set_object_terms( $matchup_id, $name_ids, 'matchup_tag', $append = true );
		}

		/***************************************************************
		 * Set the league and season taxonomy terms.
		 ***************************************************************/

		// Get the full team names, including city. "New York Yankees".
		$home_team_full = isset( $this->body['home_team'] ) ? $this->body['home_team'] : null;
		$away_team_full = isset( $this->body['away_team'] ) ? $this->body['away_team'] : null;

		// Get teams. This will create them if they don't exist.
		$league_id  = $this->get_term( $this->body['sport'], 'league' );
		$league_ids = [
			$league_id,
			$home_team_full ? $this->get_term( $home_team_full, 'league', $league_id ) : '',
			$away_team_full ? $this->get_term( $away_team_full, 'league', $league_id ) : '',
		];

		// Remove empties.
		$league_ids = array_filter( $league_ids );

		// If we have categories.
		if ( $league_ids ) {
			// Set the league and teams.
			wp_set_object_terms( $matchup_id, $league_ids, 'league', $append = false );
			wp_set_object_terms( $insight_id, $league_ids, 'league', $append = false );
		}

		// Start season va.
		$season_id = null;

		// Check season.
		if ( isset( $this->body['season'] ) ) {
			// Split season. Sometimes we see '2024-2025-preseason'.
			$season = explode( '-', $this->body['season'] );
			$season = reset( $season );

			// Get or create the season term.
			$season_id = $this->get_term( $season, 'season' );
		}
		// No seasons, use timestamp.
		elseif ( $matchup_timestamp ) {
			// Get year from event date.
			$year = wp_date( 'Y', $matchup_timestamp );

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

		/***************************************************************
		 * Update the matchup insights.
		 * Replace the insight titles.
		 ***************************************************************/

		// Gets all insights, sorted by date.
		$insights = get_posts(
			[
				'post_type'    => 'insight',
				'post_status'  => 'any',
				'meta_key'     => 'event_uuid',
				'meta_value'   => $this->body['event_uuid'],
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => -1,
				'orderby'      => 'date',
				'order'        => 'ASC',
			]
		);

		// Update matchup with these insights.
		// update_post_meta( $matchup_id, 'event_forecasts', $insights );

		// Get index of current insight in this list.
		// $current_index = array_search( $insight_id, $insights );

		// Update all insights titles with the update number.
		if ( $insights ) {
			foreach ( $insights as $index => $id ) {
				// Build title with index.
				$updated_title = sprintf( '%s (%s #%s)', $matchup_title, __( 'Update', 'mai-asknews' ), $index + 1 );

				// Update post title.
				wp_update_post(
					[
						'ID'         => $id,
						'post_title' => $updated_title,
					]
				);
			}

			// Update the insights, sorted in reverse order, in the matchup post meta.
			update_post_meta( $matchup_id, 'insight_ids', array_reverse( $insights ) );
		}

		/***************************************************************
		 * Upload images froms sources and attach to the insight.
		 ***************************************************************/

		// Set the threshold for image size.
		$threshold = function( $threshold ) {
			return 600;
		};

		// Limit image sizes.
		$sizes = function( $sizes ) {
			foreach ( $sizes as $name => $values ) {
				if ( 'medium' === $name ) {
					continue;
				}

				unset( $sizes[ $name ] );
			}

			return $sizes;
		};

		// Resize images.
		$resize = function( $metadata, $attachment_id ) {
			$upload_dir    = wp_get_upload_dir();
			$file_path     = $upload_dir['basedir'] . '/' . $metadata['file'];
			$original_path = str_replace( '-scaled', '', $file_path );

			// Check if the original full-size image exists.
			if ( file_exists( $original_path )) {
				// Delete the original full-size image.
				unlink( $original_path );
			}

			return $metadata;
		};

		// Limit max image size.
		add_filter( 'big_image_size_threshold', $threshold, 9999 );

		// Limit image sizes.
		add_filter( 'intermediate_image_sizes_advanced', $sizes, 9999 );

		// Resize images.
		add_filter( 'wp_generate_attachment_metadata', $resize, 9999, 2 );

		// Get sources.
		$has_images = false;
		$sources    = $this->body['sources'];

		// Loop through sources.
		foreach ( $sources as $index => $source ) {
			// Skip if we have an image ID and it's 0 (can't upload image) or there is a file url.
			if ( isset( $source['image_id'] ) && $source['image_id'] && ( 0 === $source['image_id'] || wp_get_attachment_image_url( $source['image_id'] ) ) ) {
				continue;
			}

			// Skip if no image.
			if ( ! ( isset( $source['image_url'] ) && $source['image_url'] ) ) {
				continue;
			}

			// Get file name.
			$file_name = isset( $source['eng_title'] ) && $source['eng_title'] ? sanitize_title_with_dashes( $source['eng_title'] ) : '';

			// Upload the image.
			$image_id = $this->upload_image( $source['image_url'], $matchup_id, $file_name );

			// If not an error, and not already set.
			// We're storing `0` as the ID too, so it doesn't continually try to
			// upload the image every time we reprocess the insight.
			if ( ! is_wp_error( $image_id ) && ! ( isset( $this->body['sources'][ $index ]['image_id'] ) && $image_id === $this->body['sources'][ $index ]['image_id'] ) ) {
				// Set the image ID in the sources array.
				$this->body['sources'][ $index ]['image_id'] = $image_id;

				// Sort the sources array by key.
				ksort( $this->body['sources'][ $index ] );

				// Update the insight body.
				$has_images = true;
			}
		}

		// If we have images, update the insight body.
		if ( $has_images ) {
			// Update the insight body.
			update_post_meta( $insight_id, 'asknews_body', $this->body );
		}

		// Remove the filters.
		remove_filter( 'wp_generate_attachment_metadata', $resize, 9999, 2 );
		remove_filter( 'intermediate_image_sizes_advanced', $sizes, 9999 );
		remove_filter( 'big_image_size_threshold', $threshold, 9999 );

		/***************************************************************
		 * Check and update all source urls for redirects.
		 ***************************************************************/

		// Needs update.
		$has_redirects = false;

		// Loop through sources.
		foreach ( $sources as $index => $source ) {
			// Skip if we already have a final url.
			if ( isset( $source['article_url_final'] ) && $source['article_url_final'] ) {
				continue;
			}

			// Skip if no url.
			if ( ! ( isset( $source['article_url'] ) && $source['article_url'] ) ) {
				continue;
			}

			// Get the final url via curl.
			$url = $source['article_url'];
			$ch  = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt( $ch, CURLOPT_NOBODY, true ); // We don't need to fetch the body.
			curl_exec( $ch );
			$final = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
			curl_close( $ch );

			// Update source. This may be the same url, but we want to store the final url key either way.
			$this->body['sources'][ $index ]['article_url_final'] = $final;

			// Set flag.
			$has_redirects = true;
		}

		// If needs update.
		if ( $has_redirects ) {
			// Update post meta.
			update_post_meta( get_the_ID(), 'asknews_body', $this->body );
		}

		/***************************************************************
		 * Next Step TBD
		 ***************************************************************/

		// // Set post content. This runs after so we can attach images to the post ID.
		// $updated_id = wp_update_post(
		// 	[
		// 		'ID'           => $insight_id,
		// 		'post_content' => $this->handle_content( $content ),
		// 	]
		// );

		/***************************************************************
		 * End.
		 ***************************************************************/

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
		return wp_date( 'Y-m-d H:i:s', $this->get_date_timestamp( $value ) );
	}

	/**
	 * Gets a timestamp from a date string.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value Any date string.
	 *
	 * @return string
	 */
	function get_date_timestamp( $value ) {
		if ( ! is_numeric( $value ) ) {
			$value = strtotime( $value );
		}

		return $value;
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
		$image_url        = urlencode( $image_url ); // Some urls may have `&amp;` instead of just `&`.
		$image_contents   = file_get_contents( $image_url );

		// Bail if no image contents.
		if ( ! $image_contents ) {
			return 0;
		}

		// Get the extension without query params.
		$image_ext = pathinfo( $image_url, PATHINFO_EXTENSION );
		$image_ext = explode( '?', $image_ext );
		$image_ext = reset( $image_ext );

		// Remove query params from the extension
		$image_name  = $file_name ? $file_name : pathinfo( $image_url, PATHINFO_FILENAME );
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
