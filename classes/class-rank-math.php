<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The Rank Match compatibility class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Rank_Math {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Run the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		add_filter( 'rank_math/opengraph/facebook/og_url', [ $this, 'og_url' ] );
		add_filter( 'rank_math/frontend/canonical',        [ $this, 'canonical_url' ] );
		add_filter( 'rank_math/json_ld',                   [ $this, 'breadcrumb_json_ld' ], 99, 2 );
	}

	/**
	 * Return the correct Open Graph url for season archives.
	 *
	 * @param string $url The URL.
	 */
	function og_url( $url ) {
		if ( ! is_tax( 'season' ) ) {
			return $url;
		}

		return home_url( add_query_arg( [] ) );
	}

	/**
	 * Return the correct canonical url for season archives.
	 *
	 * @param string $canonical The canonical URL.
	 */
	function canonical_url( $canonical ) {
		if ( ! is_tax( 'season' ) ) {
			return $canonical;
		}

		return home_url( add_query_arg( [] ) );
	}

	/**
	 * Remove breadcrumb schema from custom taxonomy pages.
	 *
	 * @since 0.1.0
	 *
	 * @param array  $data   The JSON-LD array.
	 * @param object $jsonld The JSON-LD object.
	 *
	 * @return array
	 */
	function breadcrumb_json_ld( $data, $jsonld ) {
		if ( is_tax( 'league' ) || is_tax( 'season' ) ) {
			unset( $data['BreadcrumbList'] );
		}

		return $data;
	}
}
