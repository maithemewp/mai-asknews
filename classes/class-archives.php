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
		$matchups = is_post_type_archive( 'matchup' );
		$league   = is_tax( 'league' );
		$season   = is_tax( 'season' );
		$tag      = is_tax( 'matchup_tag' );

		if ( ! ( $matchups || $league || $season || $tag ) ) {
			return;
		}

		// Add hooks.
		add_action( 'wp_enqueue_scripts',               [ $this, 'enqueue' ] );
		add_filter( 'genesis_attr_taxonomy-archive-description', [ $this, 'add_archive_title_atts' ], 10, 3 );
		add_action( 'genesis_before_loop',              [ $this, 'do_teams' ], 20 );
		add_action( 'genesis_before_loop',              [ $this, 'do_upcoming_heading' ], 20 );
		add_filter( 'genesis_markup_entry-wrap_open',   [ $this, 'get_datetime' ], 10, 2 );
		add_filter( 'genesis_markup_entry-wrap_close',  [ $this, 'get_predictions' ], 10, 2 );
		add_action( 'genesis_after_loop',               [ $this, 'do_past_games' ] );
		add_filter( 'genesis_noposts_text',             [ $this, 'get_noposts_text' ] );
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
	 * Adds custom class to single entry titles in Mai Theme v2.
	 *
	 * @since 0.1.0
	 *
	 * @param array  $attributes Existing attributes for entry title.
	 * @param string $context    Context where the filter is run.
	 * @param array  $args       Additional arguments passed to the filter.
	 *
	 * @return array
	 */
	function add_archive_title_atts( $attributes, $context, $args ) {
		$object = get_queried_object();

		// Bail if not a league or a top level term.
		if ( ! $object || 'league' !== $object->taxonomy || 0 === wp_get_term_taxonomy_parent_id( $object->term_id, 'league' ) ) {
			return $attributes;
		}

		// Get the team data.
		$parent = get_term( $object->parent, 'league' );
		$teams  = maiasknews_get_teams( $parent->name );
		$team   = isset( $teams[ $object->name ] ) ? $teams[ $object->name ] : null;

		// Bail if no team.
		if ( ! $team ) {
			return $attributes;
		}

		$attributes['style']     = isset( $attributes['style'] ) ? $attributes['style'] : '';
		$attributes['style']    .= '--team-color:' . $team['color'] . ';';
 		$attributes['data-code'] = $teams[ $object->name ]['code'];

		return $attributes;
	}

	/**
	 * Do the teams.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_teams() {
		$object = get_queried_object();

		// Bail if not a top level term.
		if ( ! $object || 0 !== wp_get_term_taxonomy_parent_id( $object->term_id, 'league' ) ) {
			return;
		}

		// Get child terms.
		$terms = get_terms(
			[
				'taxonomy'   => 'league',
				'hide_empty' => false,
				'parent'     => $object->term_id,
			]
		);

		if ( ! $terms ) {
			return;
		}

		$teams = maiasknews_get_teams( $object->name );

		printf( '<h2>%s</h2>', __( 'All Teams', 'mai-asknews' ) );
		echo '<ul class="pm-teams">';
			foreach ( $terms as $term ) {
				$color = '';
				$code  = '';

				if ( $teams && isset( $teams[ $term->name ] ) ) {
					$color = $teams[ $term->name ]['color'];
					$code  = $teams[ $term->name ]['code'];
				}

				printf( '<li class="pm-team" style="--team-color:%s;"><a class="pm-team__link" href="%s" data-code="%s">%s</a></li>', $color, get_term_link( $term ), $code, $term->name );
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
		$event_date = get_post_meta( get_the_ID(), 'event_date', true );

		// Bail if no date.
		if ( ! $event_date ) {
			return $content;
		}

		// Force timestamp.
		if ( ! is_numeric( $event_date ) ) {
			$event_date = strtotime( $event_date );
		}

		// Get day.
		$day = wp_date( 'M j', $event_date ); // Get the day in 'M j' format.

		// Create a DateTime object with the given date and time in EST.
		$time_est = new DateTime( "@$event_date", new DateTimeZone( 'America/New_York' ) );

		// Convert to UTC.
		$time_utc = clone $time_est;
		$time_utc->setTimezone( new DateTimeZone( 'UTC' ) );

		// Convert to Pacific Time (PT).
		$time_pst = clone $time_est;
		$time_pst = $time_pst->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'g:i A' ) . ' PT';

		// Format the EST time (already in EST).
		$time_est = $time_est->format( 'g:i A' ) . ' ET';

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

		// Bail if not an admin.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $content;
		}

		// Get the data.
		$body = maiasknews_get_insight_body( get_the_ID() );
		$list = maiasknews_get_prediction_list( $body );

		// Bail if no list.
		if ( ! $list ) {
			return $content;
		}

		// TODO: Write "admin only" and color like the singular box.

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

		// Bail if paged.
		if ( get_query_var( 'paged' ) ) {
			return;
		}

		// Heading.
		printf( '<h2 class="has-xxl-margin-top">%s</h2>', __( 'Past Games', 'mai-asknews' ) );

		// Filter MPG query.
		add_filter( 'mai_post_grid_query_args', [ $this, 'mpg_query_args' ], 10, 2 );

		// Add the posts.
		mai_do_post_grid(
			[
				'post_type'       => 'matchup',
				'posts_per_page'  => 100,
				'columns'         => 1,
				'show'            => [ 'title', 'excerpt', 'more_link' ],
				'more_link_style' => 'button_link',
				'more_link_text'  => __( 'View Matchup', 'mai-asknews' ),
				'boxed'           => false,
				'class'           => 'pm-matchups',
			]
		);

		// Remove the filter.
		remove_filter( 'mai_post_grid_query_args', [ $this, 'mpg_query_args' ], 10, 2 );
	}

	/**
	 * Add the tax query to the MPG query.
	 *
	 * @param array $query_args WP_Query args.
	 * @param array $args       Mai Post Grid args.
	 *
	 * @return array
	 */
	function mpg_query_args( $query_args, $args ) {
		// Get the current query.
		global $wp_query;

		// If we have a tax query.
		if ( ! $wp_query->tax_query->queries ) {
			return $query_args;
		}

		// Adjust the query.
		$query_args['tax_query']  = $wp_query->tax_query->queries;
		$query_args['meta_query'] = [
			[
				'key'     => 'event_date',
				'value'   => strtotime( 'yesterday' ),
				'compare' => '<=',
				'type'    => 'NUMERIC',
			],
		];

		// Sort by event date.
		$query_args['orderby']  = 'meta_value_num';
		$query_args['order']    = 'DESC';
		$query_args['meta_key'] = 'event_date';

		return $query_args;
	}

	/**
	 * Get term IDs by slugs.
	 *
	 * @since 0.1.0
	 *
	 * @param string[] $slugs    Array of the term slugs.
	 * @param string   $taxonomy The taxonomy.
	 *
	 * @return int[]
	 */
	function get_term_ids_by_slug( $slugs, $taxonomy ) {
		$term_ids = [];

		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );

			if ( $term ) {
				$term_ids[] = $term->term_id;
			}
		}

		return $term_ids;
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