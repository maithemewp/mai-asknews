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
		$league = is_tax( 'league' );
		$season = is_tax( 'season' );

		if ( ! ( $league || $season ) ) {
			return;
		}

		if ( $league ) {
			$this->run_league();
		}

		if ( $season ) {
			$this->run_season();
		}
	}

	function run_league() {
		add_action( 'genesis_before_loop',   [ $this, 'do_upcoming' ], 20 );
		add_action( 'genesis_entry_content', [ $this, 'do_matchup_date' ] );
		add_action( 'genesis_after_loop',    [ $this, 'do_past_games' ] );
	}

	function run_season() {

	}

	function do_upcoming() {
		// Bail if we don't have any posts.
		if ( ! have_posts() ) {
			return;
		}

		echo '<h2>Upcoming Games</h2>';
	}

	function do_matchup_date() {
		$date = get_post_meta( get_the_ID(), 'event_date', true );

		if ( ! $date ) {
			return;
		}

		$date = date( 'F j, Y @ g:i a', strtotime( $date ) );

		echo '<p>' . $date . '</p>';
	}

	function do_past_games() {
		global $wp_query;

		// $object = get_queried_object();
		// $league = get_query_var( 'league' );
		// $season = get_query_var( 'season' );

		// Set initial args.
		$args = [
			'post_type'      => 'matchup',
			'posts_per_page' => 200,
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_key'       => 'event_date',
			'meta_query'     => [
				[
					'key'     => 'event_date',
					'value'   => date( 'Y-m-d', strtotime( '-1 day' ) ),
					'compare' => '<=',
					'type'    => 'DATE',
				],
			],
		];

		// Build tax query.
		$tax_query = [];
		$existing  = $wp_query->tax_query;

		// If existing tax query.
		if ( $existing->queries ) {
			// Loop through each taxonomy query
			foreach ( $existing->queries as $query ) {
				$taxonomy = $query['taxonomy'];
				$terms    = $query['terms'];
				$field    = $query['field'];
				$operator = $query['operator'];

				$tax_query[] = [
					'taxonomy' => $taxonomy,
					'field'    => $field,
					'terms'    => $terms,
					'operator' => $operator,
				];
			}
		}

		if ( $tax_query ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query;
		}

		// Get past games.
		$query = new WP_Query( $args );

		// If we have posts.
		if ( $query->have_posts() ) {
			echo '<h2 class="has-xxl-margin-top">Past Games</h2>';
			echo '<ul class="pm-pastgames">';
				while ( $query->have_posts() ) : $query->the_post();
					$date = get_post_meta( get_the_ID(), 'event_date', true );
					$date = $date ? date( 'F j, Y @ g:i a', strtotime( $date ) ) : '';

					echo '<li class="pm-pastgame has-md-margin-bottom">';
						printf( '<h3 class="has-xxs-margin-bottom has-md-font-size"><a class="entry-title-link" href="%s" title="%s">%s</a></h3>', get_permalink(), esc_attr( get_the_title() ), esc_html( get_the_title() ) );
						printf( '<p class="has-sm-font-size">%s</p>', $date );
					echo '</li>';
				endwhile;
			echo '</duliv>';
		}
		wp_reset_postdata();
	}
}