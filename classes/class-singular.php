<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The singular class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Singular {
	protected $insights;

	/**
	 * Construct the class.
	 */
	function __construct() {
		add_action( 'template_redirect', [ $this, 'run' ] );
	}

	/**
	 * Maybe run the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function run() {
		if ( ! is_singular( 'matchup' ) ) {
			return;
		}

		// Get insights.
		$post_status = maiasknews_has_access() ? [ 'publish', 'pending', 'draft' ] : 'publish';
		$event_uuid  = get_post_meta( get_the_ID(), 'event_uuid', true );

		// If event uuid.
		if ( $event_uuid ) {
			$this->insights = get_posts(
				[
					'post_type'    => 'insight',
					'post_status'  => $post_status,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'meta_key'     => 'event_uuid',
					'meta_value'   => $event_uuid,
					'meta_compare' => '=',
					'fields'       => 'ids',
					'numberposts'  => -1,
				]
			);
		}
		// No event uuid, no insights.
		else {
			$this->insights = [];
		}

		// Add hooks.
		add_action( 'wp_enqueue_scripts',            [ $this, 'enqueue' ] );
		add_action( 'genesis_before_entry_content',  [ $this, 'do_event_info' ] );
		add_action( 'mai_after_entry_content_inner', [ $this, 'do_content' ] );
		add_action( 'mai_after_entry_content_inner', [ $this, 'do_updates' ] );
	}

	/**
	 * Enqueue CSS in the header.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function enqueue() {
		maiasknews_enqueue_styles();
	}

	/**
	 * Do the event info.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_event_info() {
		$event_date = get_post_meta( get_the_ID(), 'event_date', true );

		// Bail if no date.
		if ( ! $event_date ) {
			return;
		}

		// Force timestamp.
		if ( ! is_numeric( $event_date ) ) {
			$event_date = strtotime( $event_date );
		}

		// Get the date and times.
		$day      = date( 'l, F j, Y ', $event_date );
		$time_utc = new DateTime( "@$event_date", new DateTimeZone( 'UTC' ) );
		$time_est = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'g:i a' ) . ' ET';
		$time_pst = $time_utc->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'g:i a' ) . ' PT';

		// Display the date.
		printf( '<p class="pm-datetime">%s @ %s / %s</p>', $day, $time_est, $time_pst );

		// Get count.
		$count = max( 1, count( $this->insights ) );

		// Display the update.
		printf( '<p class="pm-update">%s #%s</p>', __( 'Update', 'mai-asknews' ), $count );
	}

	/**
	 * Do the content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_content() {
		// Get the first insight.
		$insight_id = reset( $this->insights );

		// Bail if no insight.
		if ( ! $insight_id ) {
			return;
		}

		// Get the body.
		$body = get_post_meta( $insight_id, 'asknews_body', true );

		// Bail if no body.
		if ( ! $body ) {
			return;
		}

		// Display the nav.
		echo '<ul class="pm-jumps">';
			if ( maiasknews_has_access() ) {
				printf( '<li class="pm-jump"><a class="pm-jump__link" href="#prediction">%s</a></li>', __( 'Prediction', 'mai-asknews' ) );
			}

			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#people">%s</a></li>', __( 'People', 'mai-asknews' ) );
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#timeline">%s</a></li>', __( 'Timeline', 'mai-asknews' ) );
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#web">%s</a></li>', __( 'Web', 'mai-asknews' ) );
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#sources">%s</a></li>', __( 'Sources', 'mai-asknews' ) );

			if ( $this->insights ) {
				printf( '<li class="pm-jump"><a class="pm-jump__link" href="#updates">%s</a></li>', __( 'Updates', 'mai-asknews' ) );
			}
		echo '</ul>';

		// Do the content.
		$this->do_insight( $body );
	}

	/**
	 * Do the insights.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_updates() {
		// Get all but the first insight.
		$insight_ids = array_slice( $this->insights, 1 );

		// Bail if no insight.
		if ( ! $insight_ids ) {
			return;
		}

		// Heading.
		printf( '<h2 id="updates">%s</h2>', __( 'Previous Updates', 'mai-asknews' ) );

		// Loop through insights.
		foreach ( $insight_ids as $index => $insight_id ) {
			// Get body, and the post date with the time.
			$body = get_post_meta( $insight_id, 'asknews_body', true );
			$date = get_the_date( 'F j, Y @g:m a', $insight_id );

			printf( '<details id="pm-insight-%s" class="pm-insight">', $index );
				printf( '<summary class="pm-insight__summary">%s %s</summary>', get_the_title( $insight_id ), $date );
				echo '<div class="pm-insight__content entry-content">';
					$this->do_insight( $body );
				echo '</div>';
			echo '</details>';
		}
	}

	/**
	 * Do the insight content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_insight( $body ) {
		$has_access = maiasknews_has_access();

		if ( $has_access ) {
			$this->do_prediction( $body );
		}

		$this->do_main( $body );
		$this->do_people( $body );
		$this->do_timeline( $body );
		$this->do_web( $body );
		$this->do_sources( $body );
	}

	/**
	 * Display the prediction info.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_prediction( $data ) {
		// Display the prediction.
		echo '<div id="prediction" class="pm-prediction">';
			printf( '<h2>%s</h2>', __( 'Our Prediction', 'mai-asknews' ) );
			echo maiasknews_get_prediction_list( $data );

			$keys = [
				'forecast'               => __( 'Forecast', 'mai-asknews' ),
				'reasoning'              => __( 'Reasoning', 'mai-asknews' ),
				// 'reconciled_information' => __( 'Reconciled Information', 'mai-asknews' ),
				// 'unique_information'     => __( 'Synopsis', 'mai-asknews' ),
			];

			foreach ( $keys as $key => $value ) {
				$content = maiasknews_get_key( $key, $data );

				if ( ! $content ) {
					continue;
				}

				$classes = '';

				if ( 'forecast' !== $key ) {
					$classes = 'has-lg-margin-top';
				}

				$classes .= ' has-xs-margin-bottom';

				printf( '<p><strong>%s:</strong> %s</p>', $value, $content );
				// printf( '<p>%s</p>', $content );
			}

		echo '</div>';
	}

	/**
	 * Display the general content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_main( $data ) {
		// Get odds table.
		$odds = maiasknews_get_odds_table( $data );

		if ( $odds ) {
			printf( '<p class="has-xs-margin-bottom"><strong>%s:</strong></p>', __( 'Odds', 'mai-asknews' ) );
			echo $odds;
		}

		$keys = [
			// 'forecast'               => __( 'Forecast', 'mai-asknews' ),
			// 'reasoning'              => __( 'Reasoning', 'mai-asknews' ),
			// 'reconciled_information' => __( 'Reconciled Information', 'mai-asknews' ),
			'unique_information'     => __( 'Synopsis', 'mai-asknews' ),
		];

		foreach ( $keys as $key => $value ) {
			$content = maiasknews_get_key( $key, $data );

			if ( ! $content ) {
				continue;
			}

			$classes = '';

			if ( 'forecast' !== $key ) {
				$classes = 'has-lg-margin-top';
			}

			$classes .= ' has-xs-margin-bottom';

			// printf( '<p><strong>%s:</strong> %s</p>', $value, $content );
			printf( '<p>%s</p>', $content );
		}
	}

	/**
	 * Display the people.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_people( $data ) {
		$people = maiasknews_get_key( 'key_people', $data );

		if ( ! $people ) {
			return;
		}

		// Start markup.
		printf( '<h2 id="people" class="is-style-heading">%s</h2>', __( 'Key People', 'mai-asknews' ) );
		echo '<ul class="pm-people">';

		foreach ( $people as $person ) {
			// Early versions were a string of the person's name.
			if ( is_string( $person ) ) {
				printf( '<li class="pm-person">%s</li>', $person );
			}
			// We should be getting dict/array now.
			else {
				// Get the term/name.
				$name = isset( $person['name'] ) ? $person['name'] : '';
				$term = $name ? get_term_by( 'name', $name, 'matchup_tag' ) : '';
				$name = $term ? sprintf( '<strong><a class="pm-person__link" href="%s">%s</a></strong>', get_term_link( $term ), $term->name ) : $name;

				// Build the info.
				$info = [
					$name,
					isset( $person['role'] ) ? $person['role'] : '',
				];

				// Remove empty items.
				$info = array_filter( $info );

				echo '<li>';
					echo implode( ': ', $info );
				echo '</li>';
			}
		}

		echo '</ul>';
	}

	/**
	 * Display the timeline.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_timeline( $data ) {
		$timeline = maiasknews_get_key( 'timeline', $data );

		if ( ! $timeline ) {
			return;
		}

		printf( '<h2 id="timeline" class="is-style-heading">%s</h2>', __( 'Timeline', 'mai-asknews' ) );
		echo '<ul>';

		foreach ( $timeline as $event ) {
			printf( '<li>%s</li>', $event );
		}

		echo '</ul>';
	}

	/**
	 * Display the web search results.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_web( $data ) {
		$web = maiasknews_get_key( 'web_search_results', $data );

		if ( ! $web ) {
			return;
		}

		// Remove reCAPTCHA and Unusual Traffic Detection.
		foreach ( $web as $index => $item ) {
			$title = maiasknews_get_key( 'title', $item );

			if ( in_array( $title, [ '404', 'reCAPTCHA', 'Unusual Traffic Detection' ] ) ) {
				unset( $web[ $index ] );
			}
		}

		if ( ! $web ) {
			return;
		}

		// Reindex.
		$web = array_values( $web );

		printf( '<h2 id="web">%s</h2>', __( 'Around the Web', 'mai-asknews' ) );
		echo '<ul class="pm-results">';

		// Loop through web results.
		foreach ( $web as $item ) {
			$url        = maiasknews_get_key( 'url', $item );
			$name       = maiasknews_get_key( 'source', $item );
			$name       = 'unknown' === strtolower( $name ) ? '' : $name;
			$parsed_url = wp_parse_url( $url );
			$host       = $name ?: $parsed_url['host'];
			$host       = str_replace( 'www.', '', $host );
			$host       = $host ? 'mlb.com' === strtolower( $host ) ? 'MLB.com' : $host : '';
			$host       = $host ? sprintf( '<a class="entry-title-link" href="%s" target="_blank">%s</a>', $url, $host ) : '';
			$title      = maiasknews_get_key( 'title', $item );
			$date       = maiasknews_get_key( 'published', $item );
			$date       = $date ? wp_date( get_option( 'date_format' ), strtotime( $date ) ) : '';
			$meta       = [ trim( $date ), trim( $title ) ];
			$meta       = implode( ' &ndash; ', array_filter( $meta ) );
			$points     = maiasknews_get_key( 'key_points', $item );

			echo '<li class="pm-result">';
				echo '<h3 class="entry-title pm-result__title">';
					echo $host;
				echo '</h3>';
				echo '<div class="pm-result__meta">';
					echo $meta;
				echo '</div>';
				echo '<ul>';
				foreach ( $points as $point ) {
					echo '<li>';
						echo $point;
					echo '</li>';
				}
				echo '</ul>';
			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Display the sources.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_sources( $data ) {
		$sources = maiasknews_get_key( 'sources', $data );

		if ( ! $sources ) {
			return;
		}

		printf( '<h2 id="sources">%s</h2>', __( 'Additional News Sources', 'mai-asknews' ) );
		echo '<ul class="pm-sources">';
			// Loop through sources.
			foreach ( $sources as $source ) {
				$url        = maiasknews_get_key( 'article_url', $source );
				$host       = maiasknews_get_key( 'domain_url', $source );
				$name       = maiasknews_get_key( 'source_id', $source );
				$parsed_url = wp_parse_url( $url );
				$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				$host       = $name ?: $parsed_url['host'];
				$host       = str_replace( 'www.', '', $host );
				$host       = $host ? 'mlb.com' === strtolower( $host ) ? 'MLB.com' : $host : '';
				$host       = $host ? sprintf( '<a class="entry-title-link" href="%s" target="_blank">%s</a>', $url, $host ) : '';
				$date       = maiasknews_get_key( 'pub_date', $source );
				$date       = $date ? wp_date( get_option( 'date_format' ), strtotime( $date ) ) : '';
				$title      = maiasknews_get_key( 'eng_title', $source );
				$image_url  = maiasknews_get_key( 'image_url', $source );
				$summary    = maiasknews_get_key( 'summary', $source );
				$meta       = [ trim( $date ), trim( $title ) ];
				$meta       = implode( ' &ndash; ', array_filter( $meta ) );
				$entities   = maiasknews_get_key( 'entities', $source );
				$persons    = maiasknews_get_key( 'Person', (array) $entities );

				echo '<li class="pm-source">';
					// Image.
					echo '<figure class="pm-source__image">';
						if ( $image_url ) {
							printf( '<img class="pm-source__image-bg" src="%s" alt="%s" />', $image_url, $title );
							printf( '<img class="pm-source__image-img" src="%s" alt="%s" />', $image_url, $title );
						}
					echo '</figure>';

					// Title.
					echo '<h3 class="pm-source__title entry-title">';
						echo $host;
					echo '</h3>';

					// Meta.
					echo '<p class="pm-source__meta">';
						echo $meta;
					echo '</p>';

					// Summary.
					echo '<p class="pm-source__summary">';
						echo $summary;
					echo '</p>';

					// People/Entities.
					if ( $persons ) {
						echo '<ul class="pm-entities">';
						foreach ( $persons as $person ) {
							printf( '<li class="pm-entity">%s</li>', $person );
						}
						echo '</ul>';
					}
				echo '</li>';
			}

		echo '</ul>';
	}
}