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
		if ( ! is_singular( 'insight' ) ) {
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
		add_action( 'genesis_entry_content', [ $this, 'do_content' ], 12 );
		add_action( 'genesis_entry_content', [ $this, 'do_people' ], 12 );
		add_action( 'genesis_entry_content', [ $this, 'do_timeline' ], 12 );
		add_action( 'genesis_entry_content', [ $this, 'do_sources' ], 12 );
		add_action( 'genesis_entry_content', [ $this, 'do_web' ], 12 );
	}

	/**
	 * Display the general content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_content() {
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
	function do_people() {
		$people = $this->get_data( 'key_people' );

		if ( ! $people ) {
			return;
		}

		printf( '<p class="has-lg-margin-top has-xs-margin-bottom"><strong>%s</strong></p>', __( 'Key Players', 'mai-asknews' ) );
		echo '<ul>';

		foreach ( $people as $person ) {
			if ( is_string( $person ) ) {
				printf( '<li>%s</li>', $person );
			}
			// TODO: When they send arrays/dicts.
			else {
				// ray( $person );
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
	function do_timeline() {
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
	 * Display the sources.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_sources() {
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

			.pm-source__name {
				margin-bottom: var(--spacing-xs);
				font-size: var(--font-size-xs);
				text-transform: uppercase;
				opacity: 0.6;
			}

			.pm-source__image {
				float: right;
				max-width: max(80px, 25%);
				margin: 0 0 var(--spacing-sm) var(--spacing-sm);
			}

			.pm-source__meta {
				--link-color: var(--color-body);
				margin-bottom: var(--spacing-xs);
				color: var(--color-body);
				font-size: var(--font-size-sm);
				opacity: 0.6;
			}

			@media only screen and (min-width: 800px) {
				.pm-source {
					display: grid;
					gap: var(--spacing-lg);
					grid-template-columns: 100px 1fr;
					margin: 0;
				}

				.pm-source__name {
					margin-top: var(--spacing-xxs);
				}
			}

			.pm-results {
				margin-bottom: var(--spacing-xxl);
			}
		</style>
		<?php

		printf( '<p id="sources" class="has-lg-margin-top has-xs-margin-bottom"><strong>%s</strong></p>', __( 'Sources', 'mai-asknews' ) );
		echo '<ul class="pm-sources">';

			foreach ( $sources as $source ) {
				$url        = $this->get_key( 'article_url', $source );
				$host       = $this->get_key( 'domain_url', $source );
				$parsed_url = wp_parse_url( $url );
				$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				$host       = $parsed_url['host'];
				$host       = str_replace( 'www.', '', $host );
				$host       = $host ? sprintf( '<a href="%s" target="_blank">%s</a>', $host, $host ) : '';
				$name       = $this->get_key( 'source_id', $source );
				$date       = $this->get_key( 'pub_date', $source );
				$date       = $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '';
				$title      = $this->get_key( 'eng_title', $source );
				$image_url  = $this->get_key( 'image_url', $source );
				$summary    = $this->get_key( 'summary', $source );
				$meta       = sprintf( '%s %s %s', $date, __( 'via', 'mai-asknews' ), $host );
				$meta       = trim( $meta );

				echo '<li class="pm-source">';
				echo '<div class="pm-source__name">';
					echo $name;
				echo '</div>';
				echo '<div class="pm-source__content">';
						echo '<div class="pm-source__image">';
							if ( $image_url ) {
								printf( '<a href="%s" title="%s" target="_blank"><img src="%s" alt="%s" /></a>', $url, $title, $image_url, $title );
							}
						echo '</div>';
						echo '<h3 class="pm-source__title entry-title">';
							printf( '<a class="entry-title-link" href="%s" target="_blank">%s</a>', $url, $title );
						echo '</h3>';
						echo '<p class="pm-source__meta">';
							echo $meta;
						echo '</p>';
						echo '<p class="pm-source__summary">';
							echo $summary;
						echo '</p>';
				echo '</li>';
			}

		echo '</ul>';

		// printf( '<pre>%s</pre>', print_r( $source, true ) );
	}

	function do_web() {
		$web = $this->get_data( 'web_search_results' );

		if ( ! $web ) {
			return;
		}

		printf( '<p class="has-lg-margin-top has-xs-margin-bottom"><strong>%s</strong></p>', __( 'Around the Web (Coming Soon)', 'mai-asknews' ) );
		echo '<ul class="pm-results">';

		foreach ( $web as $item ) {
			$url    = $this->get_key( 'url', $item );
			$name   = $this->get_key( 'source', $item );
			$title  = $this->get_key( 'title', $item );
			$date   = $this->get_key( 'published', $item );
			$points = $this->get_key( 'points', $item );

			printf( '<li><a class="entry-title-link" href="%s" target="_blank">%s</a></li>', $url, $title );
		}

		echo '</ul>';
	}

	function get_key( $key, $source ) {
		return isset( $source[ $key ] ) ? $source[ $key ] : '';
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
}