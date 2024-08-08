<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The rewrites class.
 *
 * Builds single insight structure.
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
		add_action( 'pre_get_posts',       [ $this, 'modify_query' ] );
		add_filter( 'post_type_link',      [ $this, 'insights_links' ], 99, 3 );
		add_action( 'created_term',        [ $this, 'flush_rewrite_rules_term' ], 10, 3 );
		add_action( 'delete_term',         [ $this, 'flush_rewrite_rules_term' ], 10, 3 );
		add_action( 'edited_term',         [ $this, 'flush_rewrite_rules_term_edited' ], 10, 4 );
		// add_filter( 'term_link',           [ $this, 'base_link' ], 99, 3 );
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
		$team_structure   = $this->get_taxonomy_structure( 'team' );
		$season_structure = $this->get_taxonomy_structure( 'season' );
		$leagues          = implode( '|', array_keys( $team_structure ) );
		$seasons          = implode( '|', array_keys( $season_structure ) );
		$teams            = [];

		foreach( $team_structure as $league => $team_names ) {
			$teams += $team_names;
		}

		$teams = implode( '|', array_filter( $teams ) );

		// League. /mlb/
		$new_rules["($leagues)/?$"] = 'index.php?taxonomy=team&term=$matches[1]';

		// Team. /mlb/yankees/
		$new_rules["($leagues)/($teams)/?$"] = 'index.php?taxonomy=team&term=$matches[2]&team=$matches[1]';

		// League season. /mlb/2024/
		$new_rules["($leagues)/($seasons)/?$"] = 'index.php?taxonomy=season&term=$matches[2]&team=$matches[1]';

		// Team season. /mlb/yankees/2024/
		$new_rules["($leagues)/($teams)/($seasons)/?$"] = 'index.php?taxonomy=season&term=$matches[3]&team=$matches[2]&team=$matches[1]';

		// Single insight. /mlb/2024/yankees-vs-red-sox-2024-8-10/
		$new_rules["($leagues)/($seasons)/([^/]+)/?$"] = 'index.php?post_type=insight&team=$matches[1]&season=$matches[2]&name=$matches[3]';

		// Merge new rules with existing rules
		$the_rules = array_merge( $new_rules, $the_rules );

		return $the_rules;

		// $new_rules = [];

		// // Get taxonomy structure.
		// $teams   = $this->get_taxonomy_structure( 'team' );
		// $seasons = $this->get_taxonomy_structure( 'season' );

		// // Get to loopin'.
		// foreach ( $teams as $league => $team_names ) {
		// 	// league archive.
		// 	$new_pattern             = "$league/?$";
		// 	$new_rules[$new_pattern] = 'index.php?taxonomy=team&term=' . $league;

		// 	foreach ( $team_names as $index => $team_name ) {
		// 		// team archive.
		// 		$new_pattern             = "$league/$team_name/?$";
		// 		$new_rules[$new_pattern] = 'index.php?taxonomy=team&term=' . $team_name;

		// 		foreach ( $seasons as $season => $empty_children_not_hierarchical ) {
		// 			// season archive.
		// 			$new_pattern             = "$league/$season/?$";
		// 			$new_rules[$new_pattern] = 'index.php?taxonomy=season&term=' . $season;

		// 			// season archive within a team.
		// 			$new_pattern             = "$league/$team_name/$season/?$";
		// 			$new_rules[$new_pattern] = 'index.php?taxonomy=season&term=' . $season . '&team=' . $team_name;

		// 			// insight single post.
		// 			$new_pattern               = "$league/$season/([^/]+)/?$";
		// 			$new_rules[ $new_pattern ] = 'index.php?post_type=insight&name=$matches[1]';
		// 		}
		// 	}
		// }

		// // Merge new rules with existing rules
		// $the_rules = array_merge( $new_rules, $the_rules );

		// return $the_rules;
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
		$vars[] = 'team';
		$vars[] = 'season';

		return $vars;
	}

	/**
	 * Adjust the query for our new structure.
	 * This makes sure is_tax(), is_tax( 'team' ), and is_tax( 'season' ) work.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Query $query The main query.
	 *
	 * @return void
	 */
	function modify_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Bail if not a taxonomy archive.
		if ( ! $query->is_tax ) {
			return;
		}

		// Get vars.
		$taxonomy = isset( $query->query_vars['taxonomy'] ) ? $query->query_vars['taxonomy'] : '';

		// Set main slug and modify post type.
		switch ( $taxonomy ) {
			case 'team':
			case 'season':
				$slug = isset( $query->query_vars[ 'term' ] ) ? $query->query_vars[ 'term' ] : '';
				$query->set( 'post_type', 'matchup' );
			break;
			default:
				$slug = '';
		}

		// Get the term object.
		$term = get_term_by( 'slug', $slug, $taxonomy );

		// Bail if no term.
		if ( ! $term ) {
			return;
		}

		// Set queried object.
		$query->queried_object    = $term;
		$query->queried_object_id = $term->term_id;
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

		// Get all parent terms in the 'team' taxonomy.
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
	 * Modify insight post type links with our new structure.
	 *
	 * @since 0.1.0
	 *
	 * @param string $post_link The post link.
	 * @param WP_Post $post The post object.
	 * @param bool $leavename Whether to keep the post name.
	 *
	 * @return string
	 */
	function insights_links( $post_link, $post, $leavename ) {
		if ( 'insight' !== $post->post_type ) {
			return $post_link;
		}

		$parts   = [];
		$league  = '';
		$team    = '';
		$season  = '';
		$teams   = wp_get_post_terms( $post->ID, 'team' );
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

		// Build url from parts.
		$parts = array_filter( [ $league, $season, $post->post_name ] );

		// Build the post link.
		$post_link = home_url( user_trailingslashit( implode( '/', $parts) ) );

		return $post_link;
	}

	/**
	 * Remove the taxonomy base from term links.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $url      The term link url.
	 * @param WP_Term $term     The term object.
	 * @param string  $taxonomy The taxonomy name.
	 *
	 * @return string
	 */
	function base_link( $url, $term, $taxonomy ) {
		if ( ! in_array( $taxonomy, [ 'team', 'season' ] ) ) {
			return $url;
		}

		$base = sprintf( '%s/', $taxonomy );
		$url  = str_replace( $base, '', $url );

		return $url;
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
		if ( ! in_array( $taxonomy, [ 'team', 'season' ] ) ) {
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
		if ( ! in_array( $taxonomy, [ 'team', 'season' ] ) ) {
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