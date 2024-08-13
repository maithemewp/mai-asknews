<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Enqueue the plugin styles.
 *
 * @since 0.1.0
 *
 * @return void
 */
function maiasknews_enqueue_styles() {
	$version   = MAI_ASKNEWS_VERSION;
	$file      = '/assets/css/mai-asknews.css';
	$file_path = MAI_ASKNEWS_DIR . $file;
	$file_url  = MAI_ASKNEWS_URL . $file;
	$version  .= '.' . date( 'njYHi', filemtime( $file_path ) );

	wp_enqueue_style( 'mai-asknews', $file_url, [], $version );
}

/**
 * Get the insight body.
 *
 * @since 0.1.0
 *
 * @param int|string $matchup The matchup ID or UUID.
 *
 * @return array
 */
function maiasknews_get_insight_body( $matchup ) {
	$insight_id = maiasknews_get_insight_id( $matchup );

	return $insight_id ? (array) get_post_meta( $insight_id, 'asknews_body', true ) : [];
}

/**
 * Get the insight ID by matchup ID or event UUID.
 *
 * @since 0.1.0
 *
 * @param int|string $matchup The matchup ID or event UUID.
 *
 * @return int|null
 */
function maiasknews_get_insight_id( $matchup ) {
	$uuid = is_numeric( $matchup ) ? get_post_meta( $matchup, 'event_uuid', true ) : $matchup;

	// Bail if no UUID.
	if ( ! $uuid ) {
		return null;
	}

	// Get the insight ID by UUID.
	$insights = get_posts(
		[
			'post_type'    => 'insight',
			'post_status'  => 'all',
			'meta_key'     => 'event_uuid',
			'meta_value'   => $uuid,
			'meta_compare' => '=',
			'fields'       => 'ids',
			'numberposts'  => 1,
		]
	);

	return $insights && isset( $insights[0] ) ? $insights[0] : null;
}

/**
 * Get the prediction list.
 *
 * @since 0.1.0
 *
 * @param array $body The insight body.
 *
 * @return array
 */
function maiasknews_get_prediction_list( $body ) {
	$choice         = maiasknews_get_key( 'choice', $body );
	$probability    = maiasknews_get_key( 'probability', $body );
	$probability    = $probability ? $probability . '%' : '';
	$likelihood     = maiasknews_get_key( 'likelihood', $body );
	$confidence     = maiasknews_get_key( 'confidence', $body );
	$confidence     = $confidence ? maiasknews_format_confidence( $confidence ) : '';
	// $llm_confidence = maiasknews_get_key( 'llm_confidence', $body );

	// Get list body.
	$table = [
		__( 'Prediction', 'mai-asknews' )     => $choice,
		__( 'Probability', 'mai-asknews' )    => $probability,
		__( 'Confidence', 'mai-asknews' )     => $confidence,
		// __( 'LLM Confidence', 'mai-asknews' ) => $llm_confidence,
		__( 'Likelihood', 'mai-asknews' )     => $likelihood,
	];

	// Bail if no data.
	if ( ! array_filter( $table ) ) {
		return;
	}

	$html  = '';
	$html .= '<ul class="pm-prediction__list">';
	foreach ( $table as $label => $value ) {
		$html .= sprintf( '<li class="pm-prediction__item"><strong>%s:</strong> %s</li>', $label, $value );
	}
	$html .= '</ul>';

	return $html;
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
function maiasknews_get_key( $key, $array ) {
	return isset( $array[ $key ] ) ? $array[ $key ] : '';
}

/**
 * Get formatted confidence.
 *
 * @since 0.1.0
 *
 * @param float|mixed $confidence
 *
 * @return string
 */
function maiasknews_format_confidence( $confidence ) {
	return $confidence ? round( (float) $confidence * 100 ) . '%' : '';
}

/**
 * Swap breadcrumbs.
 *
 * @since 0.1.0
 *
 * @return void
 */
add_action( 'after_setup_theme', function() {
	remove_action( 'genesis_before_content_sidebar_wrap', 'mai_do_breadcrumbs', 12 );
	add_action( 'genesis_before_content_sidebar_wrap', 'maiasknews_maybe_do_breadcrumbs', 12 );
});

/**
 * Maybe swap breadcrumbs.
 *
 * @since 0.1.0
 *
 * @return void
 */
function maiasknews_maybe_do_breadcrumbs() {
	$is_tax      = is_tax( 'league' ) || is_tax( 'season' );
	$is_singular = is_singular( 'matchup' );

	if ( $is_tax || $is_singular ) {
		maiasknews_do_breadcrumbs();
	} else {
		mai_do_breadcrumbs();
	}
}

/**
 * Displays breadcrumbs if not hidden.
 *
 * @since 0.1.0
 *
 * @return void
 */
function maiasknews_do_breadcrumbs() {
	if ( mai_is_element_hidden( 'breadcrumbs' ) ) {
		return;
	}

	$is_league   = is_tax( 'league' );
	$is_season   = is_tax( 'season' );
	$is_singular = is_singular( 'matchup' );

	if ( ! ( $is_league || $is_season || $is_singular ) ) {
		return;
	}

	// Archive.
	// <div class="breadcrumb" itemscope="" itemtype="https://schema.org/BreadcrumbList">
	// 	<span class="breadcrumb-link-wrap" itemprop="itemListElement" itemscope="" itemtype="https://schema.org/ListItem">
	// 		<a class="breadcrumb-link" href="https://promatchups.local/" itemprop="item">
	// 			<span class="breadcrumb-link-text-wrap" itemprop="name">Home</span>
	// 		</a>
	// 		<meta itemprop="position" content="1">
	// 	</span>
	// 	<span aria-label="breadcrumb separator">/</span>
	// 	Archives for MLB
	// </div>

	// Singular.
	// <div class="breadcrumb" itemprop="breadcrumb" itemscope="" itemtype="https://schema.org/BreadcrumbList">
	// 	<span class="breadcrumb-link-wrap" itemprop="itemListElement" itemscope="" itemtype="https://schema.org/ListItem">
	// 		<a class="breadcrumb-link" href="https://promatchups.local/" itemprop="item">
	// 			<span class="breadcrumb-link-text-wrap" itemprop="name">Home</span>
	// 		</a>
	// 		<meta itemprop="position" content="1">
	// 	</span>
	// 	<span aria-label="breadcrumb separator">/</span>
	// 	Matchups
	// 	<span aria-label="breadcrumb separator">/</span>
	// 	Orioles vs Nationals
	// </div>

	// Get the global query.
	global $wp_query;

	// Set vars.
	$separator  = '/';
	$breadcumbs = [
		[
			'url'  => home_url(),
			'text' => __( 'Home', 'mai-asknews' ),
		],
	];

	// If league/team.
	if ( $is_league ) {
		// Get taxonomy.
		$taxonomy = isset( $wp_query->query_vars['taxonomy'] ) ? $wp_query->query_vars['taxonomy'] : '';

		// If not league or season, bail.
		if ( ! in_array( $taxonomy, [ 'league', 'season' ] ) ) {
			return;
		}

		// Get term.
		$slug = isset( $wp_query->query_vars['term'] ) ? $wp_query->query_vars['term'] : '';
		$term = $slug ? get_term_by( 'slug', $slug, $taxonomy ) : '';

		// Get parent term.
		$parent      = $term && $term->parent ? get_term( $term->parent, $taxonomy ) : '';
		$grandparent = $parent && $parent->parent ? get_term( $parent->parent, $taxonomy ) : '';

		// Maybe add grandparent.
		if ( $grandparent ) {
			$breadcumbs[] = [
				'url'  => get_term_link( $grandparent ),
				'text' => $grandparent->name,
			];
		}

		// Maybe add parent.
		if ( $parent ) {
			$breadcumbs[] = [
				'url'  => get_term_link( $parent ),
				'text' => $parent->name,
			];
		}

		// Add term.
		$breadcumbs[] = [
			'url'  => get_term_link( $term ),
			'text' => $term->name,
		];
	}
	// If season.
	elseif ( $is_season ) {
		// Get the terms.
		$league = isset( $wp_query->query_vars['league'] ) ? $wp_query->query_vars['league'] : '';
		$league = $league ? get_term_by( 'slug', $league, 'league' ) : '';
		$season = isset( $wp_query->query_vars['term'] ) ? $wp_query->query_vars['term'] : '';
		$season = $season ? get_term_by( 'slug', $season, 'season' ) : '';

		// Maybe add league.
		if ( $league ) {
			$breadcumbs[] = [
				'url'  => get_term_link( $league ),
				'text' => $league->name,
			];
		}

		// Maybe add season.
		if ( $season ) {
			$breadcumbs[] = [
				'url'  => get_term_link( $season ),
				'text' => $season->name,
			];
		}
	}
	// If singular.
	elseif ( $is_singular ) {
		// Get the terms.
		$league      = isset( $wp_query->query_vars['league'] ) ? $wp_query->query_vars['league'] : '';
		$league      = $league ? get_term_by( 'slug', $league, 'league' ) : '';
		$parent      = $league && $league->parent ? get_term( $league->parent, 'league' ) : '';
		$grandparent = $parent && $parent->parent ? get_term( $parent->parent, 'league' ) : '';
		$season      = isset( $wp_query->query_vars['season'] ) ? $wp_query->query_vars['season'] : '';
		$season      = $season ? get_term_by( 'slug', $season, 'season' ) : '';

		// Maybe add grandparent.
		if ( $grandparent ) {
			$breadcumbs[] = [
				'url'  => get_term_link( $grandparent ),
				'text' => $grandparent->name,
			];
		}

		// Maybe add parent.
		if ( $parent ) {
			$breadcumbs[] = [
				'url'  => get_term_link( $parent ),
				'text' => $parent->name,
			];
		}

		// Maybe add league.
		if ( $league ) {
			$breadcumbs[] = [
				'url'  => get_term_link( $league ),
				'text' => $league->name,
			];
		}

		// Maybe add season.
		if ( $season && $league ) {
			$breadcumbs[] = [
				'url'  => trailingslashit( get_term_link( $league ) ) . $season->slug . '/',
				'text' => $season->name,
			];
		}

		// Add matchup.
		$breadcumbs[] = [
			'url'  => get_permalink(),
			'text' => get_the_title(),
		];
	}

	// Bail if no breadcrumbs.
	if ( ! $breadcumbs ) {
		return;
	}

	// Output breadcrumbs.
	echo '<div class="breadcrumb">';
		foreach ( $breadcumbs as $i => $crumb ) {
			$last = $i === count( $breadcumbs ) - 1;

			echo '<span class="breadcrumb__item">';
				if ( $crumb['url'] && ! $last ) {
					printf( '<a class="breadcrumb__link" href="%s">', esc_url( $crumb['url'] ) );
				}

				echo esc_html( $crumb['text'] );

				if ( $crumb['url'] && ! $last ) {
					echo '</a>';
				}
			echo '</span>';

			if ( ! $last ) {
				echo '<span class="breadcrumb__separator"> ' . esc_html( $separator ) . ' </span>';
			}
		}
	echo '</div>';
}