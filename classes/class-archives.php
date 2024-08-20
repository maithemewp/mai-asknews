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
		add_filter( 'mai_archive_args_name', [ $this, 'handle_archive_name' ] );
		add_action( 'pre_get_posts',         [ $this, 'handle_archive_queries' ] );
		add_action( 'template_redirect',     [ $this, 'hooks' ] );
	}

	/**
	 * Force search results to use Matchup customizer args.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	function handle_archive_name( $name ) {
		if ( is_author() || is_search() ) {
			$name = 'matchup';
		}

		return $name;
	}

	/**
	 * Handle archive queries.
	 *
	 * @since 0.1.0
	 *
	 * @param object $query The main query.
	 *
	 * @return void
	 */
	function handle_archive_queries( $query ) {
		// Bail if in the Dashboard.
		if ( is_admin() ) {
			return;
		}

		// Bail if not the main query.
		if ( ! $query->is_main_query() ) {
			return;
		}

		// If author or search results.
		if ( is_author() || is_search() ) {
			$query->set( 'post_type', 'matchup' );
		}
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
		$author   = is_author();
		$search   = is_search();

		if ( ! ( $matchups || $league || $season || $tag || $author || $search ) ) {
			return;
		}

		// Add hooks.
		add_filter( 'genesis_attr_taxonomy-archive-description', [ $this, 'add_archive_title_atts' ], 10, 3 );
		add_action( 'genesis_before_loop',                       [ $this, 'do_teams' ], 20 );
		add_action( 'genesis_before_loop',                       [ $this, 'do_upcoming_heading' ], 20 );
		add_action( 'genesis_after_loop',                        [ $this, 'do_past_games' ] );
		add_filter( 'genesis_noposts_text',                      [ $this, 'get_noposts_text' ] );
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

		// Bail if not a league or season.
		if ( ! $object || ! in_array( $object->taxonomy, [ 'league', 'season' ] ) ) {
			return;
		}

		// Bail if we're on a league archive.
		if ( 'league' === $object->taxonomy ) {
			// Bail if it's not a top level league.
			if ( 0 !== wp_get_term_taxonomy_parent_id( $object->term_id, 'league' ) ) {
				return;
			}
		}
		// Season archive.
		else {
			// Get the league.
			$league = get_query_var( 'league' );

			// Bail if no league.
			if ( ! $league ) {
				return;
			}

			// Get the league object.
			$object = get_term_by( 'slug', $league, 'league' );

			// Bail if no league object.
			if ( ! $object ) {
				return;
			}
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

				// These class names match the pm_matchup_teams shortcode, minus the team name span.
				printf( '<li class="pm-team" style="--team-color:%s;"><a class="pm-team__link" href="%s" data-code="%s"><span class="pm-team__name">%s</span></a></li>', $color, get_term_link( $term ), $code, $term->name );
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
		printf( '<h2>%s</h2>', __( 'Upcoming Games', 'mai-asknews' ) );
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
				'show'            => [ 'title', 'custom_content', 'excerpt', 'more_link' ],
				'custom_content'  => '[pm_date]',
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
		if ( is_tax() && $wp_query->tax_query->queries ) {
			// Adjust the query.
			$query_args['tax_query']  = $wp_query->tax_query->queries;
		}

		// If author.
		if ( is_author() ) {
			$query_args['author'] = get_queried_object_id();
		}

		// If search.
		if ( is_search() ) {
			$query_args['s'] = get_search_query();
		}

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