<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The archives class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Archives {
	/**
	 * Construct the class.
	 */
	function __construct() {
		add_action( 'template_redirect', [ $this, 'hooks' ] );
	}

	/**
	 * Run the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		$league  = is_tax( 'league' );
		$season  = is_tax( 'season' );

		if ( ! ( $league || $season ) ) {
			return;
		}

		// Add hooks.
		add_action( 'wp_enqueue_scripts',              [ $this, 'enqueue' ] );
		add_action( 'genesis_before_loop',             [ $this, 'do_teams' ], 20 );
		add_action( 'genesis_before_loop',             [ $this, 'do_upcoming_heading' ], 20 );
		add_filter( 'genesis_markup_entry-wrap_open',  [ $this, 'get_datetime' ], 10, 2 );
		add_filter( 'genesis_markup_entry-wrap_close', [ $this, 'get_predictions' ], 10, 2 );
		add_action( 'genesis_after_loop',              [ $this, 'do_past_games' ] );
		add_filter( 'genesis_noposts_text',            [ $this, 'get_noposts_text' ] );
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
	 * Do the teams.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_teams() {
		$term_id = get_queried_object_id();

		// Bail if not a top level term.
		if ( ! $term_id || 0 !== wp_get_term_taxonomy_parent_id( $term_id, 'league' ) ) {
			return;
		}

		// Get child terms.
		$teams = get_terms(
			[
				'taxonomy'   => 'league',
				'hide_empty' => false,
				'parent'     => $term_id,
			]
		);

		if ( ! $teams ) {
			return;
		}

		printf( '<h2>%s</h2>', __( 'All Teams', 'mai-asknews' ) );
		echo '<ul class="pm-teams">';
			foreach ( $teams as $term ) {
				printf( '<li><a href="%s">%s</a></li>', get_term_link( $term ), $term->name );
			}
		echo '</ul>';
	}

	/**
	 * Do the upcoming games.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_upcoming_heading() {
		echo '<h2>Upcoming Games</h2>';
	}

	/**
	 * Get the datetime markup.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The default content.
	 * @param array  $args    The markup args.
	 *
	 * @return string
	 */
	function get_datetime( $content, $args ) {
		// Bail if not the opening markup.
		if ( ! ( isset( $args['open'] ) && $args['open'] ) ) {
			return $content;
		}

		// Get classes and context.
		$class   = isset( $args['params']['args']['class'] ) ? $args['params']['args']['class'] : '';
		$context = isset( $args['params']['args']['context'] ) ? $args['params']['args']['context'] : '';

		// Bail if not an archive or MPG with custom class..
		if ( ! ( 'archive' === $context || ( 'block' === $context && str_contains( $class, 'pm-matchups' ) ) ) ) {
			return $content;
		}

		// Get day and times.
		$date = get_post_meta( get_the_ID(), 'event_date', true );

		// Bail if no date.
		if ( ! $date ) {
			return $content;
		}

		// Set day and times.
		$day      = date( 'M j', strtotime( $date ) );
		$time_utc = new DateTime( $date, new DateTimeZone( 'UTC' ) );
		$time_est = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'g:i A' ) . ' ET';
		$time_pst = $time_utc->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'g:i A' ) . ' PT';

		// Build the markup.
		$html  = '';
		$html .= '<div class="pm-matchup__date">';
			$html .= sprintf( '<span class="pm-matchup__day">%s</span>', $day );
			$html .= sprintf( '<span class="pm-matchup__time">%s</span>', $time_est );
			$html .= sprintf( '<span class="pm-matchup__time">%s</span>', $time_pst );
		$html .= '</div>';

		return $html . $content;
	}

	/**
	 * Get the predictions markup.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The default content.
	 * @param array  $args    The markup args.
	 *
	 * @return string
	 */
	function get_predictions( $content, $args ) {
		// Bail if not the closing markup.
		if ( ! ( isset( $args['close'] ) && $args['close'] ) ) {
			return $content;
		}

		// Get classes and context.
		$class   = isset( $args['params']['args']['class'] ) ? $args['params']['args']['class'] : '';
		$context = isset( $args['params']['args']['context'] ) ? $args['params']['args']['context'] : '';

		// Bail if not an archive or MPG with custom class..
		if ( ! ( 'archive' === $context || ( 'block' === $context && str_contains( $class, 'pm-matchups' ) ) ) ) {
			return $content;
		}

		// Get the data.
		$body = maiasknews_get_insight_body( get_the_ID() );
		$list = maiasknews_get_prediction_list( $body );

		// Bail if no list.
		if ( ! $list ) {
			return $content;
		}

		return $list . $content;
	}

	/**
	 * Do the past games.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_past_games() {
		if ( ! function_exists( 'mai_do_post_grid' ) ) {
			return;
		}

		$args = [
			'post_type'      => 'matchup',
			'posts_per_page' => 100,
			'columns'        => 1,
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_key'       => 'event_date',
			'show'           => [ 'title', 'excerpt' ],
			'query_by'       => 'tax_meta',
			'class'          => 'pm-matchups',
			'boxed'          => false,
			'meta_keys'      => [
				[
					'meta_key'     => 'event_date',
					'meta_value'   => date( 'Y-m-d', strtotime( '-1 day' ) ),
					'meta_compare' => '<=',
					'meta_type'    => 'DATE',
				]
			],
		];

		printf( '<h2 class="has-xxl-margin-top">%s</h2>', __( 'Recent Games', 'mai-asknews' ) );
		mai_do_post_grid( $args );
	}

	/**
	 * Change the no posts text.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The default no posts text.
	 *
	 * @return string
	 */
	function get_noposts_text() {
		return '<p>' . __( 'Sorry, we don\'t have any upcoming game insights at this time. Check back soon!', 'mai-asknews' ) . '</p>';
	}
}