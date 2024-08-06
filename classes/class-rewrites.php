<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The rewrites class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Rewrites {
	protected $taxonomies = [ 'team' ];

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
		add_filter( 'rewrite_rules_array', [ $this, 'insights_rewrite_rules' ], 99, 1 );
		// add_filter( 'post_type_link',      [ $this, 'insights_links' ], 99, 3 );
		add_filter( 'term_link',           [ $this, 'base_link' ], 99, 3 );
	}

	function insights_rewrite_rules( $the_rules ) {
		$structure = $this->get_team_taxonomy_structure( 'team' );

		// Loop through the rules.
		foreach ( $the_rules as $pattern => $val ) {
			if ( str_contains( $pattern, "%league%/%team%/(.+?)" ) ) {
				foreach ( $structure as $parent => $children ) {
					foreach ( $children as $term ) {
						$new_pattern               = str_replace( "%league%/%team%/(.+?)", "$parent/$child(.+?)", $pattern );
						$new_rules[ $new_pattern ] = str_replace( "team=", "taxonomy=team&term=" . $term, $val );
					}
				}
			} elseif ( str_contains( $pattern, "%league%/(.+?)" ) ) {
				foreach ( $structure as $parent => $children ) {
					$new_pattern               = str_replace( "%league%/(.+?)", "$parent/(.+?)", $pattern );
					$new_rules[ $new_pattern ] = str_replace( "team=", "taxonomy=team", $val );
				}
			}
		}
	}

	function get_team_taxonomy_structure( $taxonomy ) {
		$structure = [];

		// Get all parent terms in the 'team' taxonomy
		$parent_terms = get_terms([
			'taxonomy'   => 'team',
			'hide_empty' => false,
			'parent'     => 0,
		]);

		if (!is_wp_error($parent_terms) && !empty($parent_terms)) {
			foreach ($parent_terms as $parent_term) {
				// Initialize an empty array for each parent term
				$structure[$parent_term->slug] = [];

				// Get child terms for the current parent term
				$child_terms = get_terms([
					'taxonomy'   => 'team',
					'hide_empty' => false,
					'parent'     => $parent_term->term_id,
				]);

				if (!is_wp_error($child_terms) && !empty($child_terms)) {
					foreach ($child_terms as $child_term) {
						$structure[$parent_term->slug][] = $child_term->slug;
					}
				}
			}
		}

		return $structure;
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
	function rewrite_rules( $the_rules ) {
		$new_rules = [];

		// Remove taxonomy base from term links.
		foreach ( $this->taxonomies as $taxonomy ) {
			// Get the taxonomy object.
			$taxo = get_taxonomy( $taxonomy );

			// Skip if no taxonomy.
			if ( ! $taxo ) {
				continue;
			}

			// Get the base.
			$base = isset( $taxo->rewrite['slug'] ) ? $taxo->rewrite['slug'] : $taxonomy;

			// Skip if no base.
			if ( ! $base ) {
				continue;
			}

			// Check if hierarchy is enabled.
			$hierarchical = is_taxonomy_hierarchical( $taxonomy );

			// Get all terms of the taxonomy.
			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'number'     => 0, // Show all.
					'hide_empty' => false,
					'fields'     => 'all',
				]
			);

			// Loop through terms.
			foreach ( $terms as $term ) {
				// If hierarchical, get patterm with hierarchy.
				if ( $hierarchical ) {
					// Generate rewrite pattern for each term and its parents.
					$parent_slugs = $this->get_term_parents_slugs( $term, $base );

					// Create a regex pattern from the slugs.
					$term_pattern = implode( '/', array_reverse( $parent_slugs ) ) . '/' . $term->slug;
					$term_pattern = trim( $term_pattern, '/' );
				}
				// Not hierarchical, just use the term slug.
				else {
					$term_pattern = $term->slug;
				}

				// Loop through the rules.
				foreach ( $the_rules as $pattern => $val ) {
					// Skip if not this taxonomy.
					if ( ! str_contains( $pattern, "$base/(.+?)" ) ) {
						continue;
					}

					// Create new pattern and rule.
					$new_pattern               = str_replace( "$base/(.+?)", $term_pattern, $pattern );
					$new_rules[ $new_pattern ] = str_replace( "$taxonomy=", "taxonomy=$taxonomy&term=" . $term->slug, $val );
				}
			}
		}

		// Bail if no new rules.
		if ( ! $new_rules ) {
			return $the_rules;
		}

		// Add new rules.
		return $new_rules + $the_rules;
	}

	/**
	 * Get term parents slugs.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Term $term     The term object.
	 * @param string  $taxonomy The taxonomy name.
	 *
	 * @return array
	 */
	function get_term_parents_slugs( $term, $taxonomy ) {
		$parent_slugs = [];

		while ( $term->parent ) {
			$term = get_term( $term->parent, $taxonomy );
			array_push( $parent_slugs, $term->slug );
		}

		return $parent_slugs;
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
		if ( ! in_array( $taxonomy, $this->taxonomies ) ) {
			return $url;
		}

		$base = sprintf( '%s/', $taxonomy );
		$url  = str_replace( $base, '', $url );

		return $url;
	}
}