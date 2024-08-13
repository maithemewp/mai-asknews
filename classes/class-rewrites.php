<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The rewrites class.
 *
 * Builds single matchup/insight structure.
 * domain.com/{team taxonomy, top level only}/{season taxonomy term}/{post-slug}
 * domain.com/mlb/2024/yankees-vs-red-sox-2024-8-10

 * Builds custom team and season archive structure.
 * domain.com/{sport name (parent)}/
 * domain.com/{sport name (parent}/{team (child)}
 * domain.com/{sport name (parent}/{team (child)}/{season}/
 * domain.com/mlb/
 * domain.com/mlb/yankees/
 * domain.com/mlb/yankees/2024/
 *
 * @since 0.1.0
 */
class Mai_AskNews_Rewrites {
	/**
	 * Construct the class.
	 */
	function __construct() {
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
		add_filter( 'rewrite_rules_array', [ $this, 'add_rewrite_rules' ], 99, 1 );
		add_filter( 'query_vars',          [ $this, 'add_query_vars' ] );
		add_action( 'pre_get_posts',       [ $this, 'modify_queries' ] );
		add_filter( 'post_type_link',      [ $this, 'matchup_links' ], 99, 3 );
		add_filter( 'term_link',           [ $this, 'taxo_links' ], 99, 3 );
		add_action( 'created_term',        [ $this, 'flush_rewrite_rules_term' ], 10, 3 );
		add_action( 'delete_term',         [ $this, 'flush_rewrite_rules_term' ], 10, 3 );
		add_action( 'edited_term',         [ $this, 'flush_rewrite_rules_term_edited' ], 10, 4 );
	}

	/**
	 * Add custom rewrite rules.
	 *
	 * @since 0.1.0
	 *
	 * @param array $the_rules The existing rewrite rules.
	 *
	 * @return array
	 */
	function add_rewrite_rules( $the_rules ) {
		$new_rules        = [];
		$league_structure = $this->get_taxonomy_structure( 'league' );
		$season_structure = $this->get_taxonomy_structure( 'season' );
		$leagues          = implode( '|', array_keys( $league_structure ) );
		$seasons          = implode( '|', array_keys( $season_structure ) );
		$teams            = [];

		// Build teams array.
		foreach( $league_structure as $league => $team_names ) {
			$teams += $team_names;
		}

		// Build teams regex.
		$teams = implode( '|', array_filter( $teams ) );

		// League. /mlb/
		$new_rules["($leagues)/?$"] = 'index.php?taxonomy=league&term=$matches[1]';

		// League with pagination. /mlb/page/2/
		$new_rules["($leagues)/page/?([0-9]{1,})/?$"] = 'index.php?taxonomy=league&term=$matches[1]&paged=$matches[2]';

		// Team. /mlb/yankees/
		$new_rules["($leagues)/($teams)/?$"] = 'index.php?taxonomy=league&term=$matches[2]&league=$matches[1]';

		// Team with pagination. /mlb/yankees/page/2/
		$new_rules["($leagues)/($teams)/page/?([0-9]{1,})/?$"] = 'index.php?taxonomy=league&term=$matches[2]&league=$matches[1]&paged=$matches[3]';

		// League season. /mlb/2024/
		$new_rules["($leagues)/($seasons)/?$"] = 'index.php?taxonomy=season&term=$matches[2]&league=$matches[1]';

		// League season with pagination. /mlb/2024/page/2/
		$new_rules["($leagues)/($seasons)/page/?([0-9]{1,})/?$"] = 'index.php?taxonomy=season&term=$matches[2]&league=$matches[1]&paged=$matches[3]';

		// Team season. /mlb/yankees/2024/
		$new_rules["($leagues)/($teams)/($seasons)/?$"] = 'index.php?taxonomy=season&term=$matches[3]&league=$matches[2]';

		// Team season with pagination. /mlb/yankees/2024/page/2/
		$new_rules["($leagues)/($teams)/($seasons)/page/?([0-9]{1,})/?$"] = 'index.php?taxonomy=season&term=$matches[3]&league=$matches[2]&paged=$matches[4]';

		// Single matchup. /mlb/2024/yankees-vs-red-sox-2024-8-10/
		$new_rules["($leagues)/($seasons)/([^/]+)/?$"] = 'index.php?post_type=matchup&league=$matches[1]&season=$matches[2]&name=$matches[3]';

		// Merge new rules with existing rules
		$the_rules = array_merge( $new_rules, $the_rules );

		return $the_rules;
	}

	/**
	 * Add custom query vars.
	 *
	 * @since 0.1.0
	 *
	 * @param array $vars The existing query vars.
	 *
	 * @return array
	 */
	function add_query_vars( $vars ) {
		$vars[] = 'league';
		$vars[] = 'season';

		return $vars;
	}

	/**
	 * TBD
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Query $query The main query.
	 *
	 * @return void
	 */
	function modify_queries( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Bail if not a taxonomy archive.
		if ( ! $query->is_tax ) {
			return;
		}

		// Get taxonomy.
		$taxonomy = isset( $query->query_vars['taxonomy'] ) ? $query->query_vars['taxonomy'] : '';

		// If not league or season, bail.
		if ( ! in_array( $taxonomy, [ 'league', 'season' ] ) ) {
			return;
		}

		// Get term.
		$slug = isset( $query->query_vars['term'] ) ? $query->query_vars['term'] : '';
		$term = $slug ? get_term_by( 'slug', $slug, $taxonomy ) : '';

		// Bail if no term.
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		// Order by event date.
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'order', 'ASC' );
		$query->set( 'meta_key', 'event_date' );

		// Remove events from yesterday and older, today and future only.
		$query->set( 'meta_query', [
			[
				'key'     => 'event_date',
				'value'   => date( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			],
		] );

		// Bail if not a top level term.
		if ( 0 !== $term->parent ) {
			return;
		}

		// Sort by event date.
		// $query->set( 'orderby', 'meta_value' );
		// $query->set( 'order', 'ASC' );
		// $query->set( 'meta_key', 'event_date' );

		// // Set post type.
		// $query->set( 'post_type', 'matchup' );

		// // Get terms.
		// $slug   = isset( $query->query_vars['term'] ) ? $query->query_vars['term'] : '';
		// $league = isset( $query->query_vars['league'] ) ? $query->query_vars['league'] : '';
		// $season = isset( $query->query_vars['season'] ) ? $query->query_vars['season'] : '';

		// // Bail if no slug.
		// if ( ! $slug ) {
		// 	return;
		// }

		// // Start tax query.
		// $tax_query = [];

		// $tax_query[] = [
		// 	'taxonomy' => $taxonomy,
		// 	'field'    => 'slug',
		// 	'terms'    => $slug,
		// ];

		// if ( 'league' === $taxonomy && $season ) {
		// 	$tax_query[] = [
		// 		'taxonomy' => 'season',
		// 		'field'    => 'slug',
		// 		'terms'    => $season,
		// 	];
		// }

		// if ( 'season' === $taxonomy && $league ) {
		// 	$tax_query[] = [
		// 		'taxonomy' => 'league',
		// 		'field'    => 'slug',
		// 		'terms'    => $league,
		// 	];
		// }

		// // Set tax query.
		// $query->set( 'tax_query', $tax_query );

		// // Get the term object.
		// $term = get_term_by( 'slug', $slug, $taxonomy );

		// // Bail if no term.
		// if ( ! $term ) {
		// 	return;
		// }

		// // Set queried object.
		// $query->queried_object    = $term;
		// $query->queried_object_id = $term->term_id;
	}

	/**
	 * Get taxonomy structure.
	 *
	 * @since 0.1.0
	 *
	 * @param string $taxonomy The taxonomy name.
	 *
	 * @return array
	 */
	function get_taxonomy_structure( $taxonomy ) {
		$structure = [];

		// Get all parent terms in the 'league' taxonomy.
		$parent_terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'parent'     => 0,
			]
		);

		if ( ! is_wp_error( $parent_terms ) && ! empty( $parent_terms ) ) {
			foreach ( $parent_terms as $parent_term ) {
				$structure[ $parent_term->slug ] = [];

				// Get child terms for the current parent term.
				$child_terms = get_terms(
					[
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'parent'     => $parent_term->term_id,
					]
				);

				// Add child terms to the structure.
				if ( ! is_wp_error( $child_terms ) && ! empty( $child_terms ) ) {
					foreach ( $child_terms as $child_term ) {
						$structure[ $parent_term->slug ][] = $child_term->slug;
					}
				}
			}
		}

		return $structure;
	}

	/**
	 * Modify matchup post type links with our new structure.
	 *
	 * @since 0.1.0
	 *
	 * @param string $post_link The post link.
	 * @param WP_Post $post The post object.
	 * @param bool $leavename Whether to keep the post name.
	 *
	 * @return string
	 */
	function matchup_links( $post_link, $post, $leavename ) {
		if ( 'matchup' !== $post->post_type ) {
			return $post_link;
		}

		$parts   = [];
		$league  = '';
		$team    = '';
		$season  = '';
		$teams   = wp_get_post_terms( $post->ID, 'league' );
		$seasons = wp_get_post_terms( $post->ID, 'season' );

		// Set league and team.
		if ( $teams && ! is_wp_error( $teams ) ) {
			foreach ( $teams as $term ) {
				// Break if both league and team are found.
				if ( $league && $team ) {
					break;
				}

				// If league (parent term).
				if ( ! $league && 0 === $term->parent ) {
					$league = $term->slug;
				}
				// Season (child term).
				elseif ( ! $team && $term->parent > 0 ) {
					$team = $term->slug;
				}
			}
		}

		// Set season.
		if ( $seasons && ! is_wp_error( $seasons ) ) {
			$season = $seasons[0]->slug;
		}

		// Get parent/child/grandchild post hierarchy.
		$ancestors = get_post_ancestors( $post->ID );
		$ancestors = array_reverse( $ancestors );
		$ancestors = array_map( function( $ancestor ) {
			return get_post_field( 'post_name', $ancestor );
		}, $ancestors );

		// Build url from parts.
		$parts = array_filter( [ $league, $season, ...$ancestors, $post->post_name ] );

		// Build the post link.
		$post_link = home_url( user_trailingslashit( implode( '/', $parts) ) );

		return $post_link;
	}

	/**
	 * Handle custom taxonomy urls.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $url      The term link url.
	 * @param WP_Term $term     The term object.
	 * @param string  $taxonomy The taxonomy name.
	 *
	 * @return string
	 */
	function taxo_links( $url, $term, $taxonomy ) {
		if ( 'league' !== $taxonomy ) {
			return $url;
		}

		// Remove query string parameters.
		$url = remove_query_arg( 'league', $url );

		// Get the slugs.
		$slug   = $term->slug;
		$parent = get_term( $term->parent, $taxonomy );
		$parent = $parent && ! is_wp_error( $parent ) ? $parent->slug : '';

		// Build parts.
		$parts = array_filter( [ $parent, $slug ] );

		return trailingslashit( $url ) . implode( '/', $parts ) . '/';
	}

	/**
	 * Flush rewrite rules when a term is created or deleted.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy name.
	 *
	 * @return void
	 */
	function flush_rewrite_rules_term( $term_id, $tt_id, $taxonomy ) {
		if ( ! in_array( $taxonomy, [ 'league', 'season' ] ) ) {
			return;
		}

		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules when a term is edited.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy name.
	 * @param array  $args     The term arguments.
	 *
	 * @return void
	 */
	function flush_rewrite_rules_term_edited( $term_id, $tt_id, $taxonomy, $args ) {
		if ( ! in_array( $taxonomy, [ 'league', 'season' ] ) ) {
			return;
		}

		// Get the current term object.
		$term = get_term( $term_id, $taxonomy );

		// Bail if the old slug matches the current slug.
		if ( $args['slug'] === $term->name ) {
			return;
		}

		flush_rewrite_rules();
	}
}