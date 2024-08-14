<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The endpoints class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Display {
	protected $data;

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

		// Get the data.
		$this->data = get_post_meta( get_the_ID(), 'asknews_body', true );

		// Bail if no data.
		if ( ! $this->data ) {
			return;
		}

		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'mai_after_entry_content_inner', [ $this, 'do_content' ], 10, 2 );
		add_action( 'mai_after_entry_content_inner', [ $this, 'do_people' ], 10, 2 );
		add_action( 'mai_after_entry_content_inner', [ $this, 'do_timeline' ], 10, 2 );
		add_action( 'mai_after_entry_content_inner', [ $this, 'do_related' ], 10, 2 );
		add_action( 'mai_after_entry_content_inner', [ $this, 'do_web' ], 10, 2 );
		add_action( 'mai_after_entry_content_inner', [ $this, 'do_sources' ], 10, 2 );
	}

	/**
	 * Display the general content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_content( $entry, $args ) {
		if ( ! $this->is_single( $args ) ) {
			return;
		}

		$keys = [
			'forecast',
			'reasoning',
			'reconciled_information',
			'unique_information',
		];

		foreach ( $keys as $index => $key ) {
			$content = $this->get_data( $key );

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
	function do_people( $entry, $args ) {
		if ( ! $this->is_single( $args ) ) {
			return;
		}

		$people = $this->get_data( 'key_people' );

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
	function do_timeline( $entry, $args ) {
		if ( ! $this->is_single( $args ) ) {
			return;
		}

		$timeline = $this->get_data( 'timeline' );

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
	 * Display the related insights.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_related( $entry, $args ) {
		if ( ! $this->is_single( $args ) ) {
			return;
		}

		// $matchup_ids = get_posts(
		// 	[
		// 		'post_type'    => 'matchup',
		// 		'post_status'  => 'publish',
		// 		'meta_key'     => 'event_uuid',
		// 		'meta_value'   => $this->get_data( 'event_uuid' ),
		// 		'meta_compare' => '=',
		// 		'fields'       => 'ids',
		// 		'numberposts'  => 1,
		// 	]
		// );

		// if ( ! $matchup_ids ) {
		// 	return;
		// }

		// // Get insights about this event.
		// $matchup_id  = $matchup_ids[0];
		// $insight_ids = get_post_meta( $matchup_id, 'event_forecasts', true );
		// $insight_ids = array_map( 'intval', $insight_ids );

		// Get current post parent.
		$insight_ids = get_posts(
			[
				'post_type'    => 'matchup',
				'post_status'  => 'publish',
				'post_parent'  => wp_get_post_parent_id( get_the_ID() ),
				'post__not_in' => [ get_the_ID() ],
				'fields'       => 'ids',
				'numberposts'  => -1,
				'orderby'      => 'date',
				'order'        => 'DESC',
			]
		);

		// Bail if no insights.
		if ( ! $insight_ids ) {
			return;
		}

		// // Bail if only 1 insight, and it's the current post.
		// if ( 1 === count( $insight_ids ) && get_the_ID() === $insight_ids[0] ) {
		// 	return;
		// }

		printf( '<h2>%s</h2>', __( 'Latest Updates', 'mai-asknews' ) );
		?>
		<style>
		.pm-related {
			--entry-title-font-size: var(--font-size-xl);
			--link-color: var(--color-heading);
		}
		</style>
		<?php

		echo '<ul class="pm-related">';
			foreach ( $insight_ids as $insight_id ) {
				$current = get_the_ID() === $insight_id;
				$insight = get_post( $insight_id );

				if ( ! $insight ) {
					continue;
				}

				// Check if post is published or user has permission to view.
				if ( 'publish' !== $insight->post_status && ! current_user_can( 'edit_post', $insight_id ) ) {
					continue;
				}

				$permalink = get_permalink( $insight_id );
				$title     = get_the_title( $insight_id );

				echo '<li class="pm-related__item">';
					echo '<p class="is-style-heading">';
					if ( ! $current ) {
						printf( '<a href="%s">', $permalink );
					} else {
						echo __( 'Current: ', 'mai-asknews' );
					}
						echo $title;
					if ( ! $current ) {
						echo '</a>';
					}
					echo '</p>';
				echo '</li>';
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
	function do_web( $entry, $args ) {
		if ( ! $this->is_single( $args ) ) {
			return;
		}

		$web = $this->get_data( 'web_search_results' );

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
		</style>
		<?php

		printf( '<h2 class="has-xs-margin-bottom">%s</h2>', __( 'Around the Web', 'mai-asknews' ) );
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
			$date       = $date ? wp_date( get_option( 'date_format' ), strtotime( $date ) ) : '';
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
	function do_sources( $entry, $args ) {
		if ( ! $this->is_single( $args ) ) {
			return;
		}

		$sources = $this->get_data( 'sources' );

		if ( ! $sources ) {
			return;
		}

		?>
		<style>
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
		</style>
		<?php

		printf( '<h2 id="sources" class="has-xs-margin-bottom">%s</h2>', __( 'Sources', 'mai-asknews' ) );
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
				$date       = $date ? wp_date( get_option( 'date_format' ), strtotime( $date ) ) : '';
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

	/**
	 * Get the source data by key.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key The data key.
	 *
	 * @return mixed
	 */
	function get_data( $key ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : '';
	}

	/**
	 * Check if the hook context is singular.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args The element args.
	 *
	 * @return bool
	 */
	function is_single( $args ) {
		return isset( $args['context'] ) && 'single' === $args['context'];
	}
}