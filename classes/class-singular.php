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
		$post_status    = current_user_can( 'edit_posts' ) ? [ 'publish', 'pending', 'draft' ] : 'publish';
		$event_uuid     = get_post_meta( get_the_ID(), 'event_uuid', true );
		$this->insights = get_posts(
			[
				'post_type'    => 'insight',
				'post_status'  => $post_status,
				'meta_key'     => 'event_uuid',
				'meta_value'   => $event_uuid,
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => -1,
			]
		);

		// Add hooks.
		add_action( 'wp_enqueue_scripts',           [ $this, 'enqueue' ] );
		add_action( 'genesis_before_entry_content', [ $this, 'do_event_info' ] );
		add_action( 'genesis_after_entry_content',  [ $this, 'do_content' ] );
		add_action( 'genesis_after_entry_content',  [ $this, 'do_insights' ] );
	}

	/**
	 * Enqueue CSS in the header.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function enqueue() {
		wp_enqueue_style( 'mai-asknews', MAI_ASKNEWS_URL . 'assets/css/mai-asknews.css', [], MAI_ASKNEWS_VERSION );
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

		if ( ! $event_date ) {
			return;
		}


		$day      = date( 'l, F j, Y ', strtotime( $event_date ) );
		$time_utc = new DateTime( $event_date, new DateTimeZone( 'UTC' ) );
		$time_est = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'g:i A' ) . ' ET';
		$time_pst = $time_utc->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'g:i A' ) . ' PT';

		// Format the date like Saturday, July 17, 2021 @ 7:05 pm.
		// $event_date = date_i18n( 'l, F j, Y @g:i a', strtotime( $event_date ) );

		// Display the date.
		printf( '<p><strong>%s:</strong> %s @ %s / %s</p>', __( 'Game Time', 'mai-asknews' ), $day, $time_est, $time_pst );
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
	function do_insights() {
		// Get all but the first insight.
		$insight_ids = array_slice( $this->insights, 1 );

		// Bail if no insight.
		if ( ! $insight_ids ) {
			return;
		}

		// Heading.
		printf( '<h2 id="insights">%s</h2>', __( 'Previous Updates', 'mai-asknews' ) );

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
		$this->do_prediction( $body );
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
	function do_prediction( $body ) {
		// Get the list.
		$list = maiasknews_get_prediction_list( $body );

		// Bail if no list.
		if ( ! $list ) {
			return;
		}

		echo '<div class="pm-prediction">';
			printf( '<h2>%s</h2>', __( 'Our Prediction', 'mai-asknews' ) );
			echo $list;
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
		$keys = [
			'forecast',
			'reasoning',
			'reconciled_information',
			'unique_information',
		];

		foreach ( $keys as $index => $key ) {
			$content = maiasknews_get_key( $key, $data );

			if ( ! $content ) {
				continue;
			}

			$classes = '';

			if ( 0 !== $index ) {
				$classes = 'has-lg-margin-top';
			}

			$classes .= ' has-xs-margin-bottom';

			// printf( '<p class="%s"><strong>%s</strong></p>', $classes, ucfirst( $key ) );
			printf( '<p><strong>%s:</strong> %s</p>', $key, $content );
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

		printf( '<p class="has-lg-margin-top has-xs-margin-bottom"><strong>%s</strong></p>', __( 'Key Players', 'mai-asknews' ) );
		echo '<ul>';

		foreach ( $people as $person ) {
			// Early versions were a string of the person's name.
			if ( is_string( $person ) ) {
				printf( '<li>%s</li>', $person );
			}
			// We should be getting dict/array now.
			else {
				$info = [
					isset( $person['name'] ) ? sprintf( '<strong>%s</strong>', $person['name'] ) : '',
					isset( $person['role'] ) ? $person['role'] : '',
				];

				echo '<li>';
					echo implode( ' - ', $info );
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

		printf( '<p class="has-lg-margin-top has-xs-margin-bottom"><strong>%s</strong></p>', __( 'Timeline', 'mai-asknews' ) );
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

			if ( in_array( $title, [ 'reCAPTCHA', 'Unusual Traffic Detection' ] ) ) {
				unset( $web[ $index ] );
			}
		}

		if ( ! $web ) {
			return;
		}

		// Reindex.
		$web = array_values( $web );

		printf( '<h2 class="has-xxl-margin-top">%s</h2>', __( 'Around the Web', 'mai-asknews' ) );
		echo '<ul class="pm-results">';

		foreach ( $web as $item ) {
			$url        = maiasknews_get_key( 'url', $item );
			$name       = maiasknews_get_key( 'source', $item );
			$name       = 'unknown' === strtolower( $name ) ? '' : $name;
			$parsed_url = wp_parse_url( $url );
			$host       = $name ?: $parsed_url['host'];
			$host       = str_replace( 'www.', '', $host );
			$host       = $host ? 'mlb.com' === strtolower( $host ) ? 'MLB.com' : $host : '';
			$host       = $host ? sprintf( '<a href="%s" target="_blank">%s</a>', $url, $host ) : '';
			$title      = maiasknews_get_key( 'title', $item );
			$date       = maiasknews_get_key( 'published', $item );
			$date       = $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '';
			$meta       = sprintf( '%s %s %s', $date, __( 'via', 'mai-asknews' ), $host );
			$meta       = trim( $meta );
			$points     = maiasknews_get_key( 'key_points', $item );

			echo '<li class="pm-result">';
				echo '<h3 class="entry-title">';
					echo $title;
				echo '</h3>';
				echo '<div class="pm-result__source">';
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

		printf( '<h2 id="sources" class="has-xxl-margin-top">%s</h2>', __( 'Sources', 'mai-asknews' ) );
		echo '<ul class="pm-sources">';

			foreach ( $sources as $source ) {
				$url        = maiasknews_get_key( 'article_url', $source );
				$host       = maiasknews_get_key( 'domain_url', $source );
				$name       = maiasknews_get_key( 'source_id', $source );
				$parsed_url = wp_parse_url( $url );
				$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				$host       = $name ?: $parsed_url['host'];
				$host       = str_replace( 'www.', '', $host );
				$host       = $host ? 'mlb.com' === strtolower( $host ) ? 'MLB.com' : $host : '';
				$host       = $host ? sprintf( '<a href="%s" target="_blank">%s</a>', $url, $host ) : '';
				$date       = maiasknews_get_key( 'pub_date', $source );
				$date       = $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '';
				$title      = maiasknews_get_key( 'eng_title', $source );
				$image_url  = maiasknews_get_key( 'image_url', $source );
				$summary    = maiasknews_get_key( 'summary', $source );
				$meta       = sprintf( '%s %s %s', $date, __( 'via', 'mai-asknews' ), $host );
				$meta       = trim( $meta );

				echo '<li class="pm-source">';
					echo '<figure class="pm-source__image">';
						if ( $image_url ) {
							printf( '<img class="pm-source__image-bg" src="%s" alt="%s" />', $image_url, $title );
							printf( '<img class="pm-source__image-img" src="%s" alt="%s" />', $image_url, $title );
						}
					echo '</figure>';
					echo '<h3 class="pm-source__title entry-title">';
						echo $title;
					echo '</h3>';
					echo '<p class="pm-source__meta">';
						echo $meta;
					echo '</p>';
					// echo '<label class="pm-source__more">';
					// 	echo '<input type="checkbox" id="toggle">';
					// 	echo '<span class="pm-source__more-text">';
					// 		echo __( 'More', 'mai-asknews' );
					// 	echo '</span>';
					// echo '</label>';
					echo '<p class="pm-source__summary">';
						echo $summary;
					echo '</p>';
				echo '</li>';
			}

		echo '</ul>';
	}
}