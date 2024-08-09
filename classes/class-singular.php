<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The singular class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Singular {
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

		// Get matchup uuid.
		$event_uuid = get_post_meta( get_the_ID(), 'event_uuid', true );

		// Get post status to check.
		$post_status = current_user_can( 'edit_posts' ) ? [ 'publish', 'pending', 'draft' ] : 'publish';

		// Get insights.
		$insights = get_posts(
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

		// Bail if no insights.
		if ( ! $insights ) {
			return;
		}

		// Add custom CSS.
		?>
		<style>
		.pm-results {
			--heading-margin-bottom: var(--spacing-xs);
			--entry-title-font-size: var(--font-size-lg);
			display: grid;
			gap: var(--spacing-xxl);
			list-style-type: none;
			margin: 0 0 var(--spacing-xxl);
		}

		.pm-result {
			margin: 0;
		}

		.pm-result__source {
			margin-bottom: var(--spacing-xxxs);
			font-size: var(--font-size-sm);
			opacity: 0.6;
		}

		.pm-sources {
			--heading-margin-bottom: var(--spacing-xs);
			--entry-title-font-size: var(--font-size-lg);
			display: grid;
			gap: var(--spacing-xxl);
			list-style-type: none;
			margin: 0 0 var(--spacing-xxl);
		}

		.pm-source {
			margin: 0;
		}

		.pm-source__image {
			position: relative;
			float: right;
			width: clamp(80px, 33.3333%, 200px);
			margin: 0 0 var(--spacing-sm) var(--spacing-sm);
			aspect-ratio: 16/9;
			background: var(--color-alt);
			overflow: hidden;
		}

		.pm-source__image-bg {
			position: absolute;
			inset: 0;
			width: 100%;
			height: 100%;
			object-fit: cover;
			z-index: 1;
			filter: blur(6px) brightness(150%);
		}

		.pm-source__image-img {
			position: relative;
			object-fit: contain;
			width: 100%;
			height: 100%;
			z-index: 2;
		}

		.pm-source__meta {
			--link-color: var(--color-body);
			margin-bottom: var(--spacing-xs);
			color: var(--color-body);
			font-size: var(--font-size-sm);
			opacity: 0.6;
		}

		.pm-source__summary {
			overflow: hidden;
			display: -webkit-box;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
		}

		.pm-insight {
			margin: 0 0 var(--row-gap);
			padding: 0;
			background: var(--color-white);
			color: var(--color-body);
			border: var(--border);
		}

		.pm-insight__summary {
			position: sticky;
			top: var(--body-top);
			min-height: 2rem;
			margin: 0;
			padding: var(--spacing-xs) var(--spacing-md);
			background: white;
			border-bottom: var(--border);
			cursor: pointer;
			z-index: 1;
		}

		.pm-insight[open] .pm-insight__summary {
			background: var(--color-alt);
		}

		.pm-insight__content {
			padding: var(--spacing-md) var(--spacing-lg);
		}
		</style>
		<?php

		/**
		 * Add info after the title.
		 */
		add_action( 'genesis_before_entry_content', function() {
			$event_date = get_post_meta( get_the_ID(), 'event_date', true );

			if ( ! $event_date ) {
				return;
			}

			// Format the date like Saturday, July 17, 2021 @ 7:05 pm.
			$event_date = date_i18n( 'l, F j, Y @g:i a', strtotime( $event_date ) );

			// Display the date.
			printf( '<p><strong>%s:</strong> %s</p>', __( 'Game Time', 'mai-asknews' ), $event_date );
		});

		// Loop insights.
		foreach ( $insights as $index => $insight_id ) {
			$data = get_post_meta( $insight_id, 'asknews_body', true );

			if ( ! $data ) {
				continue;
			}

			/**
			 * Add the content.
			 *
			 * @since 0.1.0
			 *
			 * @param WP_Post $entry The entry post object.
			 * @param array   $args  The entry args.
			 *
			 * @return void
			 */
			add_action( 'mai_after_entry_content_inner', function( $entry, $args ) use ( $index, $insight_id, $data ) {
				if ( $index > 0 ) {
					// If first.
					if ( 1 === $index ) {
						// Heading.
						printf( '<h2 id="insights">%s</h2>', __( 'Previous Updates', 'mai-asknews' ) );
					}

					// Get post date with the time.
					$date = get_the_date( 'F j, Y @g:m a', $insight_id );

					printf( '<details id="pm-insight-%s" class="pm-insight">', $index );
						printf( '<summary class="pm-insight__summary">%s %s</summary>', get_the_title( $insight_id ), $date );
						echo '<div class="pm-insight__content entry-content">';
				}

				$this->do_content( $data );
				$this->do_people( $data );
				$this->do_timeline( $data );
				$this->do_web( $data );
				$this->do_sources( $data );

				if ( $index > 0 ) {
						echo '</div>';
					echo '</details>';
				}
			}, 10, 2 );
		}
	}

	/**
	 * Display the general content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_content( $data ) {
		$keys = [
			'forecast',
			'reasoning',
			'reconciled_information',
			'unique_information',
		];

		foreach ( $keys as $index => $key ) {
			$content = $this->get_key( $key, $data );

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
		$people = $this->get_key( 'key_people', $data );

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
		$timeline = $this->get_key( 'timeline', $data );

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
		$web = $this->get_key( 'web_search_results', $data );

		if ( ! $web ) {
			return;
		}

		// Remove reCAPTCHA and Unusual Traffic Detection.
		foreach ( $web as $index => $item ) {
			$title = $this->get_key( 'title', $item );

			if ( in_array( $title, [ 'reCAPTCHA', 'Unusual Traffic Detection' ] ) ) {
				unset( $web[ $index ] );
			}
		}

		if ( ! $web ) {
			return;
		}

		// Reindex.
		$web = array_values( $web );

		printf( '<h2 class="has-xs-margin-bottom">%s</h2>', __( 'Around the Web', 'mai-asknews' ) );
		echo '<ul class="pm-results">';

		foreach ( $web as $item ) {
			$url        = $this->get_key( 'url', $item );
			$name       = $this->get_key( 'source', $item );
			$name       = 'unknown' === strtolower( $name ) ? '' : $name;
			$parsed_url = wp_parse_url( $url );
			$host       = $name ?: $parsed_url['host'];
			$host       = str_replace( 'www.', '', $host );
			$host       = $host ? 'mlb.com' === strtolower( $host ) ? 'MLB.com' : $host : '';
			$host       = $host ? sprintf( '<a href="%s" target="_blank">%s</a>', $url, $host ) : '';
			$title      = $this->get_key( 'title', $item );
			$date       = $this->get_key( 'published', $item );
			$date       = $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '';
			$meta       = sprintf( '%s %s %s', $date, __( 'via', 'mai-asknews' ), $host );
			$meta       = trim( $meta );
			$points     = $this->get_key( 'key_points', $item );

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
		$sources = $this->get_key( 'sources', $data );

		if ( ! $sources ) {
			return;
		}

		printf( '<h2 id="sources" class="has-xs-margin-bottom">%s</h2>', __( 'Sources', 'mai-asknews' ) );
		echo '<ul class="pm-sources">';

			foreach ( $sources as $source ) {
				$url        = $this->get_key( 'article_url', $source );
				$host       = $this->get_key( 'domain_url', $source );
				$name       = $this->get_key( 'source_id', $source );
				$parsed_url = wp_parse_url( $url );
				$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				$host       = $name ?: $parsed_url['host'];
				$host       = str_replace( 'www.', '', $host );
				$host       = $host ? 'mlb.com' === strtolower( $host ) ? 'MLB.com' : $host : '';
				$host       = $host ? sprintf( '<a href="%s" target="_blank">%s</a>', $url, $host ) : '';
				$date       = $this->get_key( 'pub_date', $source );
				$date       = $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '';
				$title      = $this->get_key( 'eng_title', $source );
				$image_url  = $this->get_key( 'image_url', $source );
				$summary    = $this->get_key( 'summary', $source );
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

	/**
	 * Get the source data by key.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key    The data key.
	 * @param array  $array  The data array.
	 *
	 * @return mixed
	 */
	function get_key( $key, $array ) {
		return isset( $array[ $key ] ) ? $array[ $key ] : '';
	}
}