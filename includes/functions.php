<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * If the user has access to view restricted content.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_has_access() {
	return current_user_can( 'read' );

	// TODO: Is admin or is viewing NFL and has NFL Membership. Need this to hide predictions.
	// TODO: Free account is logged in or has free membership. Right now free account is seeing predictions. This will only be necessary for voting.
}

/**
 * If is a matchup archive page.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_is_archive() {
	return is_post_type_archive( 'matchup' )
		|| is_tax( 'league' )
		|| is_tax( 'season' )
		|| is_tax( 'matchup_tag' )
		|| is_author()
		|| is_search();
}

/**
 * Enqueue the plugin styles.
 *
 * @since 0.1.0
 *
 * @return void
 */
function maiasknews_enqueue_styles() {
	$version   = MAI_ASKNEWS_VERSION;
	$file      = 'assets/css/mai-asknews.css';
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
 * Gets the updated date.
 *
 * @since 0.1.0
 *
 * @return string
 */
function maiasknews_get_updated_date() {
	// Set vars.
	$updated  = '';
	$insights = get_post_meta( get_the_ID(), 'insight_ids', true );

	// Bail if no insights.
	if ( ! $insights ) {
		return $updated;
	}

	// Get the last insight body.
	$body = get_post_meta( reset( $insights ), 'asknews_body', true );

	// Bail if no body.
	if ( ! $body ) {
		return $updated;
	}

	// Get the date.
	$date         = maiasknews_get_key( 'date', $body );
	$date         = ! is_numeric( $date ) ? strtotime( $date ) : $date;
	$time_utc     = new DateTime( "@$date", new DateTimeZone( 'UTC' ) );
	$time_now     = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
	$interval_est = $time_now->setTimezone( new DateTimeZone( 'America/New_York' ) )->diff( $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) ) );
	$interval_pst = $time_now->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->diff( $time_utc->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) ) );

	// If within our range.
	if ( $interval_est->days < 2 || $interval_pst->days < 2 ) {
		if ( $interval_est->days > 0 ) {
			$time_ago_est = $interval_est->days . ' day' . ( $interval_est->days > 1 ? 's' : '' ) . ' ago';
		} elseif ( $interval_est->h > 0 ) {
			$time_ago_est = $interval_est->h . ' hour' . ( $interval_est->h > 1 ? 's' : '' ) . ' ago';
		} elseif ( $interval_est->i > 0 ) {
			$time_ago_est = $interval_est->i . ' minute' . ( $interval_est->i > 1 ? 's' : '' ) . ' ago';
		} else {
			$time_ago_est = __( 'Just now', 'mai-asknews' );
		}

		if ( $interval_pst->days > 0 ) {
			$time_ago_pst = $interval_pst->days . ' day' . ( $interval_pst->days > 1 ? 's' : '' ) . ' ago';
		} elseif ( $interval_pst->h > 0 ) {
			$time_ago_pst = $interval_pst->h . ' hour' . ( $interval_pst->h > 1 ? 's' : '' ) . ' ago';
		} elseif ( $interval_pst->i > 0 ) {
			$time_ago_pst = $interval_pst->i . ' minute' . ( $interval_pst->i > 1 ? 's' : '' ) . ' ago';
		} else {
			$time_ago_pst = __( 'Just now', 'mai-asknews' );
		}

		$updated = sprintf( '<span data-timezone="ET">%s</span><span data-timezonesep> | </span><span data-timezone="PT">%s</span>', $time_ago_est, $time_ago_pst );
	}
	// Older than our range.
	else {
		$date     = $time_utc->setTimezone( new DateTimeZone('America/New_York'))->format( 'M j, Y' );
		$time_est = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'g:i a' ) . ' ET';
		$time_pst = $time_utc->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'g:i a' ) . ' PT';
		$updated  = sprintf( '%s @ <span data-timezone="ET">%s</span><span data-timezonesep> | </span><span data-timezone="PT">%s</span>', $date, $time_est, $time_pst );
	}

	// Display the update.
	return sprintf( '<p class="pm-update">%s %s</p>', __( 'Updated', 'mai-asknews' ), $updated );
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
	// $probability    = maiasknews_get_key( 'probability', $body );
	// $probability    = $probability ? $probability . '%' : '';
	$likelihood     = maiasknews_get_key( 'likelihood', $body );

	// TODO:
	// crystal ball next to prediction
	// dice next to probability
	// thumbs up/down next to likelihood?

	// $confidence     = maiasknews_get_key( 'confidence', $body );
	// $confidence     = $confidence ? maiasknews_format_confidence( $confidence ) : '';
	// $llm_confidence = maiasknews_get_key( 'llm_confidence', $body );

	// Get list body.
	$table = [
		__( 'Prediction', 'mai-asknews' )     => $choice,
		__( 'Probability', 'mai-asknews' )    => $likelihood,
		// __( 'Probability', 'mai-asknews' )    => sprintf( '%s, %s', $probability, $likelihood ),
		// __( 'Confidence', 'mai-asknews' )     => $confidence,
		// __( 'LLM Confidence', 'mai-asknews' ) => $llm_confidence,
		// __( 'Likelihood', 'mai-asknews' )     => $likelihood,
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
 * Get the odds table
 *
 * @since 0.1.0
 *
 * @param array $body The insight body.
 *
 * @return string
 */
function maiasknews_get_odds_table( $body ) {
	// Get the odds data.
	$html      = '';
	$odds_data = maiasknews_get_key( 'odds_info', $body );
	$odds_data = $odds_data && is_array( $odds_data ) ? $odds_data : [];

	// If we have odds data.
	if ( ! $odds_data ) {
		return $html;
	}

	// Start the table data.
	$sites = [];

	// Loop through odds data.
	foreach ( $odds_data as $team => $odds ) {
		// Merge the sites.
		$sites = array_merge( $sites, array_keys( $odds ) );
	}

	// Remove duplicates.
	$sites = array_unique( $sites );

	// Bail if no sites.
	if ( ! $sites ) {
		return $html;
	}

	// Start the averages.
	$averages = [];

	// Get an average of the odds for each team.
	foreach ( $odds_data as $team => $odds ) {
		$sum = 0;

		foreach ( $odds as $site => $odd ) {
			$sum += $odd;
		}

		$averages[ $team ] = $sum / count( $odds );
	}

	// Get home and away teams.
	list( $home_team, $away_team ) = array_keys( $odds_data );

	// Top sites.
	$top_sites = [
		'betmgm (colorado)',
		'fanduel',
		'bovada',
		'pointsbet',
		'hard rock bet',
	];

	// Start the odds.
	$html .= '<div class="pm-odds">';
		// Add a checkbox to expand/collapse the odds.
		$toggle = '<div class="pm-toggle">';
			$toggle .= '<label for="pm-toggle-input" class="pm-toggle_label">';
				$toggle .= __( 'Show All', 'mai-asknews' );
				$toggle .= '<input id="pm-toggle-input" class="pm-toggle__input" type="checkbox" />';
				$toggle .= '<span class="pm-toggle__slider"></span>';
			$toggle .= '</label>';
		$toggle .= '</div>';

		// Build the table
		$html .= '<table>';
			$html .= '<thead>';
				$html .= '<tr>';
					$html .= sprintf( '<th>%s</th>', $toggle );
					$html .= sprintf( '<th>%s</th>', $home_team );
					$html .= sprintf( '<th>%s</th>', $away_team );
				$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			$html .= '<tr class="is-top">';
				$html .= sprintf( '<td><strong>%s</strong></td>', __( 'Average odds', 'mai-asknews' ) );
				foreach ( $averages as $team => $average ) {
					$rounded = round( $average, 2 );
					$html   .= sprintf( '<td>%s%s</td>', $rounded > 0 ? '+' : '', $rounded );
				}
			$html .= '</tr>';

			foreach ( $sites as $maker ) {
				$class = in_array( strtolower( $maker ), $top_sites ) ? 'is-top' : 'is-not-top';

				$html .= sprintf( '<tr class="%s">', $class );
					$html .= sprintf( '<td>%s</td>', ucwords( $maker ) );
					$html .= sprintf( '<td>%s</td>', isset( $odds_data[ $home_team ][ $maker ] ) ? $odds_data[ $home_team ][ $maker ] : 'N/A' );
					$html .= sprintf( '<td>%s</td>', isset( $odds_data[ $away_team ][ $maker ] ) ? $odds_data[ $away_team ][ $maker ] : 'N/A' );
				$html .= '</tr>';
			}

			$html .= '</tbody>';
		$html .= '</table>';
	$html .= '</div>';

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
 * Get the matchup date and time.
 *
 * @since 0.1.0
 *
 * @param int $matchup_id The matchup ID.
 *
 * @return void
 */
function maiasknews_get_matchup_datetime( $matchup_id, $before = '' ) {
	$event_date = get_post_meta( $matchup_id, 'event_date', true );

	// Bail if no date.
	if ( ! $event_date ) {
		return '';
	}

	// Force timestamp.
	if ( ! is_numeric( $event_date ) ) {
		$event_date = strtotime( $event_date );
	}

	// Get the date and times.
	$day      = date( 'l, F j, Y ', $event_date );
	$time_utc = new DateTime( "@$event_date", new DateTimeZone( 'UTC' ) );
	$time_est = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'g:i a' ) . ' ET';
	$time_pst = $time_utc->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'g:i a' ) . ' PT';
	$before   = $before ? sprintf( '<strong>%s</strong> ', $before ) : '';

	return sprintf( '<p class="pm-datetime">%s%s @ <span data-timezone="ET">%s</span> <span data-timezonesep>/</span> <span data-timezone="PT">%s</span></p>', $before, $day, $time_est, $time_pst );
}

/**
 * Get the matchup teams list.
 *
 * @since 0.1.0
 *
 * @param array $atts The shortcode attributes.
 *
 * @return string
 */
function maiasknews_get_matchup_teams_list( $atts = [] ) {
	// Atts.
	$atts = shortcode_atts(
		[
			'before' => '',
			'after'  => '',
		],
		$atts,
		'pm_matchup_teams'
	);

	// Sanitize.
	$atts = [
		'before' => esc_html( $atts['before'] ),
		'after'  => esc_html( $atts['after'] ),
	];

	$terms = get_the_terms( get_the_ID(), 'league' );

	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}

	// Remove top level terms.
	$terms = array_filter( $terms, function( $term ) {
		return 0 !== $term->parent;
	} );

	// Bail if no terms.
	if ( ! $terms ) {
		return '';
	}

	// Get teams.
	$teams = maiasknews_get_teams( 'MLB' );

	// Build the output.
	$html = '<div class="pm-matchup-teams">';
		$html .= '<ul class="pm-matchup-teams__list">';
		foreach ( $terms as $term ) {
			$code  = isset( $teams[ $term->name ]['code'] ) ? $teams[ $term->name ]['code'] : '';
			$color = isset( $teams[ $term->name ]['color'] ) ? $teams[ $term->name ]['color'] : '';

			// These class names match the archive team list, minus the team name span.
			$html .= sprintf( '<li class="pm-team"><a class="pm-team__link" href="%s" style="--team-color:%s;" data-code="%s">%s</a></li>', get_term_link( $term ), $color, $code, $term->name );
		}
		$html .= '</ul>';
	$html .= '</div>';

	return $html;
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

add_filter( 'get_post_metadata', 'pm_fallback_thumbnail_id', 10, 4 );
/**
 * Set fallback image(s) for featured images.
 *
 * @param mixed  $value     The value to return, either a single metadata value or an array of values depending on the value of $single. Default null.
 * @param int    $object_id ID of the object metadata is for.
 * @param string $meta_key  Metadata key.
 * @param bool   $single    Whether to return only the first value of the specified $meta_key.
 * @param string $meta_type Type of object metadata is for. Accepts 'post', 'comment', 'term', 'user', or any other object type with an associated meta table.
 *
 * @return mixed
 */
function pm_fallback_thumbnail_id( $value, $post_id, $meta_key, $single ) {
	// Bail if in admin.
	if ( is_admin() ) {
		return $value;
	}

	// Bail if not the key we want.
	if ( '_thumbnail_id' !== $meta_key ) {
		return $value;
	}

	// Remove filter to avoid loopbacks.
	remove_filter( 'get_post_metadata', 'pm_fallback_thumbnail_id', 10, 4 );

	// Check for an existing featured image.
	$image_id = get_post_thumbnail_id( $post_id );

	// Add back our filter.
	add_filter( 'get_post_metadata', 'pm_fallback_thumbnail_id', 10, 4 );

	// Bail if we already have a featured image.
	if ( $image_id ) {
		return $image_id;
	}

	// Set fallback image.
	$image_id = 2624;

	return $image_id;
}

/**
 * Register CCAs.
 *
 * @since 0.1.0
 *
 * @param array $ccas The current CCAs.
 *
 * @return array
 */
add_filter( 'mai_template-parts_config', function( $ccas ) {
	$ccas['matchup-promo-1'] = [
		'hook' => 'pm_before_prediction',
	];

	$ccas['matchup-promo-2'] = [
		'hook' => 'pm_before_prediction',
	];

	return $ccas;
});

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

// function maiasknews_get_team( $sport, $team, $key = '' ) {
// 	$teams = maiasknews_get_teams( $sport );

// 	if ( isset( $array[ $sport ][ $team ] ) ) {
// 		if ( $key ) {
// 			return isset( $array[ $sport ][ $team ][ $key ] ) ? $array[ $sport ][ $team ][ $key ] : null;
// 		}

// 		return $array[ $sport ][ $team ];
// 	}

// 	if ( $key ) {
// 		$array[ $sport ][ $team ][ $key ] = null;

// 		return $teams[ $team ][ $key ];
// 	}

// 	$array[ $sport ][ $team ] = null;

// 	return $array[ $sport ][ $team ];
// }

/**
 * Get the teams and data.
 *
 * @since 0.1.0
 *
 * @link https://github.com/cdcrabtree/colorr/blob/master/R/eplcolors.r
 *
 * @param string $sport The sport.
 *
 * @return array
 */
function maiasknews_get_teams( $sport = '' ) {
	static $cache = [];

	if ( $sport && isset( $cache[ $sport ] ) ) {
		return $cache[ $sport ];
	}

	$cache = [
		'MLB' => [
			'Angels' => [
				'city'   => 'Los Angeles',
				'code'   => 'LAA',
				'color'  => '#BA0021',       // Red
				// 'color' => '#003263',       // Blue
			],
			'Astros' => [
				'city'   => 'Houston',
				'code'   => 'HOU',
				'color'  => '#002D62',   // Blue
				// 'color' => '#EB6E1F',   // Orange
			],
			'Athletics' => [
				'city'   => 'Oakland',
				'code'   => 'OAK',
				'color'  => '#003831',   // Green
				// 'color' => '#EFB21E',   // Yellow
			],
			'Blue Jays' => [
				'city'   => 'Toronto',
				'code'   => 'TOR',
				'color'  => '#134A8E',   // Blue
				// 'color' => '#1D2D5C',   // Dark Blue
				// 'color' => '#E8291C', // Red
			],
			'Braves' => [
				'city'   => 'Atlanta',
				'code'   => 'ATL',
				'color'  => '#13274F',   // Blue
				// 'color' => '#CE1141',   // Red
			],
			'Brewers' => [
				'city'   => 'Milwaukee',
				'code'   => 'MIL',
				'color'  => '#0A2351',     // Navy Blue
				// 'color' => '#B6922E',     // Gold
			],
			'Cardinals' => [
				'city'   => 'St. Louis',
				'code'   => 'STL',
				'color'  => '#C41E3A',     // Red
				// 'color' => '#000066',     // Blue
				// 'color' => '#FEDB00', // Yellow
			],
			'Cubs' => [
				'city'   => 'Chicago',
				'code'   => 'CHC',
				'color'  => '#0E3386',   // Blue
				// 'color' => '#CC3433',   // Red
			],
			'Diamondbacks' => [
				'city'   => 'Arizona',
				'code'   => 'ARI',
				'color'  => '#A71930',   // Red
				// 'color' => '#000000',   // Black
				// 'color' => '#E3D4AD', // Tan
			],
			'Dodgers' => [
				'city'   => 'Los Angeles',
				'code'   => 'LAD',
				'color'  => '#005A9C',       // Blue
				// 'color' => '#EF3E42',       // Red
			],
			'Giants' => [
				'city'   => 'San Francisco',
				'code'   => 'SFG',
				'color'  => '#FD5A1E',         // Orange
				// 'color' => '#000000',         // Black
				// 'color' => '#8B6F4E', // Gold
			],
			'Guardians' => [
				'city'   => 'Cleveland',
				'code'   => 'CLE',
				'color'  => '#E31937',     // Red
				// 'color' => '#002B5C',     // Blue
			],
			'Mariners' => [
				'city'   => 'Seattle',
				'code'   => 'SEA',
				'color'  => '#0C2C56',   // Blue
				// 'color' => '#005C5C',   // Green
				// 'color' => '#C4CED4', // Silver
			],
			'Marlins' => [
				'city'   => 'Miami',
				'code'   => 'MIA',
				'color'  => '#0077C8',   // Blue
				// 'color' => '#FF6600',   // Orange
				// 'color' => '#FFD100', // Yellow
				// 'color' => '#000000', // Black
			],
			'Mets' => [
				'city'   => 'New York',
				'code'   => 'NYM',
				'color'  => '#002D72',    // Blue
				// 'color' => '#FF5910',    // Orange
			],
			'Nationals' => [
				'city'   => 'Washington',
				'code'   => 'WSN',
				'color'  => '#AB0003',      // Red
				// 'color' => '#11225B',      // Blue
			],
			'Orioles' => [
				'city'   => 'Baltimore',
				'code'   => 'BAL',
				'color'  => '#DF4601',     // Orange
				// 'color' => '#000000',     // Black
			],
			'Padres' => [
				'city'   => 'San Diego',
				'code'   => 'SDP',
				'color'  => '#7F411C',     // Brown
				// 'color' => '#002D62',     // Blue
				// 'color' => '#FEC325', // Yellow
				// 'color' => '#A0AAB2', // Silver
			],
			'Phillies' => [
				'city'   => 'Philadelphia',
				'code'   => 'PHI',
				'color'  => '#E81828',        // Red
				// 'color' => '#284898',        // Blue
			],
			'Pirates' => [
				'city'   => 'Pittsburgh',
				'code'   => 'PIT',
				'color'  => '#FDB827',      // Yellow
				// 'color' => '#000000',      // Black
			],
			'Rangers' => [
				'city'   => 'Texas',
				'code'   => 'TEX',
				'color'  => '#003278',   // Blue
				// 'color' => '#C0111F',   // Red
			],
			'Rays' => [
				'city'   => 'Tampa Bay',
				'code'   => 'TBR',
				'color'  => '#092C5C',     // Navy Blue
				// 'color' => '#8FBCE6',     // Light Blue
				// 'color' => '#F5D130', // Yellow
			],
			'Red Sox' => [
				'city'   => 'Boston',
				'code'   => 'BOS',
				'color'  => '#BD3039',   // Red
				// 'color' => '#0D2B56',   // Blue
			],
			'Reds' => [
				'city'   => 'Cincinnati',
				'code'   => 'CIN',
				'color'  => '#C6011F',      // Red
				// 'color' => '#000000',      // Black
			],
			'Rockies' => [
				'city'   => 'Colorado',
				'code'   => 'COL',
				'color'  => '#333366',    // Purple
				// 'color' => '#231F20',    // Black
				// 'color' => '#C4CED4', // Silver
			],
			'Royals' => [
				'city'   => 'Kansas City',
				'code'   => 'KCR',
				'color'  => '#004687',       // Blue
				// 'color' => '#C09A5B',       // Gold
			],
			'Tigers' => [
				'city'   => 'Detroit',
				'code'   => 'DET',
				'color'  => '#0C2C56',   // Blue
				// 'color' => '#FFFFFF',   // White
			],
			'Twins' => [
				'city'   => 'Minnesota',
				'code'   => 'MIN',
				'color'  => '#002B5C',     // Blue
				// 'color' => '#D31145',     // Red
			],
			'White Sox' => [
				'city'   => 'Chicago',
				'code'   => 'CHW',
				'color'  => '#000000',   // Black
				// 'color' => '#C4CED4',   // Silver
			],
			'Yankees' => [
				'city'   => 'New York',
				'code'   => 'NYY',
				'color'  => '#003087',    // Blue
				// 'color' => '#E4002B',    // Red
			],
		],
		'NFL' => [
			'49ers' => [
				'city'  => 'San Francisco',
				'code'  => 'SF',
				'color' => '#AA0000', // Red
				// 'color' => '#B3995D', // Gold
				// 'color' => '#000000', // Black
				// 'color' => '#A5ACAF', // Silver
			],
			'Bears' => [
				'city'  => 'Chicago',
				'code'  => 'CHI',
				'color' => '#0B162A', // Navy Blue
				// 'color' => '#C83803', // Orange
			],
			'Bengals' => [
				'city'  => 'Cincinnati',
				'code'  => 'CIN',
				'color' => '#FB4F14', // Orange
				// 'color' => '#000000', // Black
			],
			'Bills' => [
				'city'  => 'Buffalo',
				'code'  => 'BUF',
				'color' => '#00338D', // Blue
				// 'color' => '#C60C30', // Red
			],
			'Broncos' => [
				'city'  => 'Denver',
				'code'  => 'DEN',
				'color' => '#002244', // Navy Blue
				// 'color' => '#FB4F14', // Orange
			],
			'Browns' => [
				'city'  => 'Cleveland',
				'code'  => 'CLE',
				'color' => '#FB4F14', // Orange
				// 'color' => '#22150C', // Brown
				// 'color' => '#A5ACAF', // Silver
			],
			'Buccaneers' => [
				'city'  => 'Tampa Bay',
				'code'  => 'TB',
				'color' => '#D50A0A', // Red
				// 'color' => '#34302B', // Dark Silver
				// 'color' => '#000000', // Black
				// 'color' => '#FF7900', // Orange
				// 'color' => '#B1BABF', // Silver
			],
			'Cardinals' => [
				'city'  => 'Arizona',
				'code'  => 'ARI',
				'color' => '#97233F', // Burgundy
				// 'color' => '#000000', // Black
				// 'color' => '#FFB612', // Yellow
				// 'color' => '#A5ACAF', // Silver
			],
			'Chargers' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAC',
				'color' => '#0073CF', // Light Blue
				// 'color' => '#002244', // Navy Blue
				// 'color' => '#FFB612', // Yellow
			],
			'Chiefs' => [
				'city'  => 'Kansas City',
				'code'  => 'KC',
				'color' => '#E31837', // Red
				// 'color' => '#FFB612', // Yellow
				// 'color' => '#000000', // Black
			],
			'Colts' => [
				'city'  => 'Indianapolis',
				'code'  => 'IND',
				'color' => '#002C5F', // Blue
				// 'color' => '#A5ACAF', // Silver
			],
			'Commanders' => [
				'city'  => 'Washington',
				'code'  => 'WAS',
				'color' => '#773141', // Merlot
				// 'color' => '#FFB612', // Yellow
				// 'color' => '#000000', // Black
				// 'color' => '#5B2B2F', // Dark Merlot
			],
			'Cowboys' => [
				'city'  => 'Dallas',
				'code'  => 'DAL',
				'color' => '#002244', // Dark Blue
				// 'color' => '#B0B7BC', // Light Silver
				// 'color' => '#ACC0C6', // Light Blue
				// 'color' => '#A5ACAF', // Silver
				// 'color' => '#00338D', // Blue
				// 'color' => '#000000', // Black
			],
			'Dolphins' => [
				'city'  => 'Miami',
				'code'  => 'MIA',
				'color' => '#008E97', // Teal
				// 'color' => '#F58220', // Orange
				// 'color' => '#005778', // Blue
			],
			'Eagles' => [
				'city'  => 'Philadelphia',
				'code'  => 'PHI',
				'color' => '#004953', // Dark Green
				// 'color' => '#A5ACAF', // Silver
				// 'color' => '#ACC0C6', // Light Blue
				// 'color' => '#000000', // Black
				// 'color' => '#565A5C', // Dark Silver
			],
			'Falcons' => [
				'city'  => 'Atlanta',
				'code'  => 'ATL',
				'color' => '#A71930', // Red
				// 'color' => '#000000', // Black
				// 'color' = '#A5ACAF', // Silver
			],
			'Giants' => [
				'city'  => 'New York',
				'code'  => 'NYG',
				'color' => '#0B2265', // Blue
				// 'color' => '#A71930', // Red
				// 'color' => '#A5ACAF', // Silver
			],
			'Jaguars' => [
				'city'  => 'Jacksonville',
				'code'  => 'JAX',
				'color' => '#006778', // Teal
				// 'color' => '#000000', // Black
				// 'color' => '#9F792C', // Dark Gold
				// 'color' => '#D7A22A', // Light Gold
			],
			'Jets' => [
				'city'  => 'New York',
				'code'  => 'NYJ',
				'color' => '#203731', // Green
			],
			'Lions' => [
				'city'  => 'Detroit',
				'code'  => 'DET',
				'color' => '#005A8B', // Blue
				// 'color' => '#B0B7BC', // Silver
				// 'color' => '#000000', // Black
			],
			'Packers' => [
				'city'  => 'Green Bay',
				'code'  => 'GB',
				'color' => '#203731', // Dark Green
				// 'color' => '#FFB612', // Yellow
			],
			'Panthers' => [
				'city'  => 'Carolina',
				'code'  => 'CAR',
				'color' => '#0085CA', // Blue
				// 'color' => '#000000', // Black
				// 'color' => '#BFC0BF', // Silver
			],
			'Patriots' => [
				'city'  => 'New England',
				'code'  => 'NE',
				'color' => '#002244', // Navy Blue
				// 'color' => '#C60C30', // Red
				// 'color' => '#B0B7BC', // Silver
			],
			'Raiders' => [
				'city'  => 'Las Vegas',
				'code'  => 'LV',
				'color' => '#A5ACAF', // Silver
				// 'color' => '#000000', // Black
			],
			'Rams' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAR',
				'color' => '#002244', // Navy Blue
				// 'color' => '#B3995D', // Gold
			],
			'Ravens' => [
				'city'  => 'Baltimore',
				'code'  => 'BAL',
				'color' => '#241773', // Purple
				// 'color' => '#000000', // Black
				// 'color' => '#9E7C0C', // Gold
				// 'color' => '#C60C30', // Red
			],
			'Saints' => [
				'city'  => 'New Orleans',
				'code'  => 'NO',
				'color' => '#9F8958', // Gold
				// 'color' => '#000000', // Black
			],
			'Seahawks' => [
				'city'  => 'Seattle',
				'code'  => 'SEA',
				'color' => '#002244', // Navy Blue
				// 'color' => '#69BE28', // Green
				// 'color' => '#A5ACAF', // Silver
			],
			'Steelers' => [
				'city'  => 'Pittsburgh',
				'code'  => 'PIT',
				'color' => '#000000', // Black
				// 'color' => '#FFB612', // Yellow
				// 'color' => '#C60C30', // Red
				// 'color' => '#00539B', // Blue
				// 'color' => '#A5ACAF', // Silver
			],
			'Texans' => [
				'city'  => 'Houston',
				'code'  => 'HOU',
				'color' => '#03202F', // Navy Blue
				// 'color' => '#A71930', // Red
			],
			'Titans' => [
				'city'  => 'Tennessee',
				'code'  => 'TEN',
				'color' => '#4B92DB', // Bright Blue
				// 'color' => '#002244', // Navy Blue
				// 'color' => '#C60C30', // Red
				// 'color' => '#A5ACAF', // Silver
			],
			'Vikings' => [
				'city'  => 'Minnesota',
				'code'  => 'MIN',
				'color' => '#4F2683', // Purple
				// 'color' => '#FFC62F', // Yellow
				// 'color' => '#E9BF9B', // Tan
				// 'color' => '#000000', // Black
			],
		],
		'NBA' => [
			'76ers' => [
				'city'  => 'Philadelphia',
				'code'  => 'PHI',
				'color' => '#006BB6', // 76ers Blue
				// 'color' => '#ED174C', // 76ers Red
			],
			'Bucks' => [
				'city'  => 'Milwaukee',
				'code'  => 'MIL',
				'color' => '#00471B', // Dark Green
				// 'color' => '#EEE1C6', // Bucks Cream
				// 'color' => '#0077C0', // Light Royal Blue
				// 'color' => '#000000', // Black
			],
			'Bulls' => [
				'city'  => 'Chicago',
				'code'  => 'CHI',
				'color' => '#CE1141', // Bulls Red
				// 'color' => '#000000', // Black
			],
			'Cavaliers' => [
				'city'  => 'Cleveland',
				'code'  => 'CLE',
				'color' => '#860038', // Cavaliers Wine
				// 'color' => '#FDBB30', // Cavaliers Gold
				// 'color' => '#002D62', // Cavaliers Navy
			],
			'Celtics' => [
				'city'  => 'Boston',
				'code'  => 'BOS',
				'color' => '#008348', // Celtics Green
				// 'color' => '#FFD700', // Gold
				// 'color' => '#C0C0C0', // Silver
				// 'color' => '#000000', // Black
			],
			'Clippers' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAC',
				'color' => '#ED174C', // Clippers Red
				// 'color' => '#006BB6', // Royal Blue
				// 'color' => '#A1A1A4', // Gray
				// 'color' => '#00285D', // Navy
			],
			'Grizzlies' => [
				'city'  => 'Memphis',
				'code'  => 'MEM',
				'color' => '#23375B', // Memphis Midnight Blue
				// 'color' => '#6189B9', // Beale Street Blue
				// 'color' => '#BBD1E4', // Smoke Blue
				// 'color' => '#FFD432', // Grizzlies Gold
			],
			'Hawks' => [
				'city'  => 'Atlanta',
				'code'  => 'ATL',
				'color' => '#E03A3E', // Hawks Red
				// 'color' => '#C3D600', // Green Volt
				// 'color' => '#FFFFFF', // White
				// 'color' => '#000000', // Black
			],
			'Heat' => [
				'city'  => 'Miami',
				'code'  => 'MIA',
				'color' => '#98002E', // Heat Red
				// 'color' => '#F9A01B', // Heat Yellow
				// 'color' => '#000000', // Black
			],
			'Hornets' => [
				'city'  => 'Charlotte',
				'code'  => 'CHA',
				'color' => '#1D1160', // Hornets Purple
				// 'color' => '#008CA8', // Teal
				// 'color' => '#A1A1A4', // Gray
			],
			'Jazz' => [
				'city'  => 'Utah',
				'code'  => 'UTA',
				'color' => '#002B5C', // Jazz Navy
				// 'color' => '#F9A01B', // Jazz Yellow
				// 'color' => '#00471B', // Jazz Green
				// 'color' => '#BEC0C2', // Jazz Gray
			],
			'Kings' => [
				'city'  => 'Sacramento',
				'code'  => 'SAC',
				'color' => '#724C9F', // Kings Purple
				// 'color' => '#8E9090', // Kings Silver
				// 'color' => '#000000', // Black
			],
			'Knicks' => [
				'city'  => 'New York',
				'code'  => 'NYK',
				'color' => '#006BB6', // Knicks Blue
				// 'color' => '#F58426', // Orange
				// 'color' => '#BEC0C2', // Silver
			],
			'Lakers' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAL',
				'color' => '#552582', // Lakers Purple
				// 'color' => '#FDB927', // Lakers Gold
			],
			'Magic' => [
				'city'  => 'Orlando',
				'code'  => 'ORL',
				'color' => '#007DC5', // Magic Blue
				// 'color' => '#C4CED3', // Silver
				// 'color' => '#000000', // Black
			],
			'Mavericks' => [
				'city'  => 'Dallas',
				'code'  => 'DAL',
				'color' => '#007DC5', // Mavericks Blue
				// 'color' => '#C4CED3', // Mavericks Silver
				// 'color' => '#20385B', // Mavericks Navy
				// 'color' => '#000000', // Black
			],
			'Nets' => [
				'city'  => 'Brooklyn',
				'code'  => 'BKN',
				'color' => '#000000', // Black
			],
			'Nuggets' => [
				'city'  => 'Denver',
				'code'  => 'DEN',
				'color' => '#4FA8FF', // Nuggets Light Blue
				// 'color' => '#FFB20F', // Nuggets Gold
				// 'color' => '#004770', // Nuggets Navy
			],
			'Pacers' => [
				'city'  => 'Indiana',
				'code'  => 'IND',
				'color' => '#00275D', // Pacers Blue
				// 'color' => '#FFC633', // Gold
				// 'color' => '#BEC0C2', // Silver
			],
			'Pelicans' => [
				'city'  => 'New Orleans',
				'code'  => 'NOP',
				'color' => '#002B5C', // Pelicans Blue
				// 'color' => '#B4975A', // Pelicans Gold
				// 'color' => '#E31937', // Pelicans Red
			],
			'Pistons' => [
				'city'  => 'Detroit',
				'code'  => 'DET',
				'color' => '#006BB6', // Pistons Blue
				// 'color' => '#ED174C', // Pistons Red
				// 'color' => '#001F70', // Pistons Navy
			],
			'Raptors' => [
				'city'  => 'Toronto',
				'code'  => 'TOR',
				'color' => '#CE1141', // Raptors Red
				// 'color' => '#C4CED3', // Raptors Silver
				// 'color' => '#000000', // Black
			],
			'Rockets' => [
				'city'  => 'Houston',
				'code'  => 'HOU',
				'color' => '#CE1141', // Rockets Red
				// 'color' => '#C4CED3', // Silver
				// 'color' => '#FDB927', // Mustard
				// 'color' => '#000000', // Black
			],
			'Sonics' => [
				'city'  => 'Seattle',
				'code'  => 'SEA',
				'color' => '#016332', // Sonics Green
				// 'color' => '#FCE10C', // Sonics Yellow
			],
			'Spurs' => [
				'city'  => 'San Antonio',
				'code'  => 'SAS',
				'color' => '#B6BFBF', // Silver
				// 'color' => '#000000', // Black
			],
			'Suns' => [
				'city'  => 'Phoenix',
				'code'  => 'PHX',
				'color' => '#E56020', // Suns Orange
				// 'color' => '#1D1160', // Suns Purple
				// 'color' => '#63717A', // Suns Gray
				// 'color' => '#000000', // Black
			],
			'Thunder' => [
				'city'  => 'Oklahoma City',
				'code'  => 'OKC',
				'color' => '#007DC3', // Thunder Blue
				// 'color' => '#F05133', // Orange
				// 'color' => '#FDBB30', // Yellow
				// 'color' => '#002D62', // Dark Blue
			],
			'Timberwolves' => [
				'city'  => 'Minnesota',
				'code'  => 'MIN',
				'color' => '#005083', // Timberwolves Blue
				// 'color' => '#C4CED3', // Silver
				// 'color' => '#00A94F', // Green
				// 'color' => '#000000', // Black
			],
			'Trail Blazers' => [
				'city'  => 'Portland',
				'code'  => 'POR',
				'color' => '#E03A3E', // Blazers Red
				// 'color' => '#B6BFBF', // Silver
				// 'color' => '#000000', // Black
			],
			'Warriors' => [
				'city'  => 'Golden State',
				'code'  => 'GSW',
				'color' => '#006BB6', // Warriors Royal Blue
				// 'color' => '#FDB927', // Golden Yellow
			],
			'Wizards' => [
				'city'  => 'Washington',
				'code'  => 'WAS',
				'color' => '#002B5C', // Navy
				// 'color' => '#F5002F', // Red
				// 'color' => '#C2CCCC', // Silver
			],
		],
		'NHL' => [
			'Blackhawks' => [
				'city'  => 'Chicago',
				'code'  => 'CHI',
				'color' => '#C8102E', // Red
				// 'color' => '#010101', // Black
				// 'color' => '#FF671F', // Orange
				// 'color' => '#FFD100', // Yellow
				// 'color' => '#001871', // Blue
				// 'color' => '#00843D', // Green
				// 'color' => '#CC8A00', // Gold
			],
			'Blue Jackets' => [
				'city'  => 'Columbus',
				'code'  => 'CBJ',
				'color' => '#041E42', // Navy Blue
				// 'color' => '#A4A9AD', // Silver
				// 'color' => '#C8102E', // Red
			],
			'Blues' => [
				'city'  => 'St. Louis',
				'code'  => 'STL',
				'color' => '#002F87', // Blue
				// 'color' => '#041E42', // Navy Blue
				// 'color' => '#FFB81C', // Yellow
			],
			'Bruins' => [
				'city'  => 'Boston',
				'code'  => 'BOS',
				'color' => '#FFB81C', // Yellow
				// 'color' => '#010101', // Black
			],
			'Canadiens' => [
				'city'  => 'Montreal',
				'code'  => 'MTL',
				'color' => '#A6192E', // Red
				// 'color' => '#001E62', // Navy Blue
			],
			'Canucks' => [
				'city'  => 'Vancouver',
				'code'  => 'VAN',
				'color' => '#00205B', // Blue
				// 'color' => '#97999B', // Silver
				// 'color' => '#041C2C', // Dark Blue
			],
			'Capitals' => [
				'city'  => 'Washington',
				'code'  => 'WSH',
				'color' => '#A6192E', // Red
				// 'color' => '#041E42', // Navy Blue
				// 'color' => '#A2AAAD', // Silver
				// 'color' => '#782F40', // Purple
				// 'color' => '#53565A', // Gray
			],
			'Coyotes' => [
				'city'  => 'Arizona',
				'code'  => 'ARI',
				'color' => '#862633', // Maroon
				// 'color' => '#010101', // Black
				// 'color' => '#DDCBA4', // Tan
			],
			'Devils' => [
				'city'  => 'New Jersey',
				'code'  => 'NJD',
				'color' => '#C8102E', // Red
				// 'color' => '#010101', // Black
			],
			'Ducks' => [
				'city'  => 'Anaheim',
				'code'  => 'ANA',
				'color' => '#FC4C02', // Orange
				// 'color' => '#010101', // Black
				// 'color' => '#A2AAAD', // Silver
				// 'color' => '#85714D', // Gold
			],
			'Flames' => [
				'city'  => 'Calgary',
				'code'  => 'CGY',
				'color' => '#C8102E', // Red
				// 'color' => '#010101', // Black
				// 'color' => '#F1BE48', // Yellow
			],
			'Flyers' => [
				'city'  => 'Philadelphia',
				'code'  => 'PHI',
				'color' => '#FA4616', // Orange
				// 'color' => '#010101', // Black
			],
			'Golden Knights' => [
				'city'  => 'Vegas',
				'code'  => 'VGK',
				'color' => '#B4975A', // Gold
				// 'color' => '#010101', // Black
				// 'color' => '#333F42', // Gray
			],
			'Hurricanes' => [
				'city'  => 'Carolina',
				'code'  => 'CAR',
				'color' => '#C8102E', // Red
				// 'color' => '#010101', // Black
				// 'color' => '#A2AAAD', // Silver
			],
			'Islanders' => [
				'city'  => 'New York',
				'code'  => 'NYI',
				'color' => '#003087', // Blue
				// 'color' => '#FC4C02', // Orange
			],
			'Jets' => [
				'city'  => 'Winnipeg',
				'code'  => 'WPG',
				'color' => '#041E42', // Navy Blue
				// 'color' => '#C8102E', // Red
			],
			'Kings' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAK',
				'color' => '#010101', // Black
				// 'color' => '#A2AAAD', // Silver
			],
			'Kraken' => [
				'city'  => 'Seattle',
				'code'  => 'SEA',
				'color' => '#355464', // Teal
			],
			'Lightning' => [
				'city'  => 'Tampa Bay',
				'code'  => 'TBL',
				'color' => '#002868', // Blue
			],
			'Maple Leafs' => [
				'city'  => 'Toronto',
				'code'  => 'TOR',
				'color' => '#00205B', // Blue
			],
			'Oilers' => [
				'city'  => 'Edmonton',
				'code'  => 'EDM',
				'color' => '#CF4520', // Orange
				// 'color' => '#00205B', // Navy Blue
			],
			'Panthers' => [
				'city'  => 'Florida',
				'code'  => 'FLA',
				'color' => '#C8102E', // Red
				// 'color' => '#041E42', // Navy Blue
				// 'color' => '#B9975B', // Gold
			],
			'Penguins' => [
				'city'  => 'Pittsburgh',
				'code'  => 'PIT',
				'color' => '#FFB81C', // Yellow
				// 'color' => '#010101', // Black
			],
			'Predators' => [
				'city'  => 'Nashville',
				'code'  => 'NSH',
				'color' => '#FFB81C', // Yellow
				// 'color' => '#041E42', // Navy Blue
			],
			'Rangers' => [
				'city'  => 'New York',
				'code'  => 'NYR',
				'color' => '#0033A0', // Blue
				// 'color' => '#C8102E', // Red
			],
			'Red Wings' => [
				'city'  => 'Detroit',
				'code'  => 'DET',
				'color' => '#CE1126', // Red
			],
			'Sabres' => [
				'city'  => 'Buffalo',
				'code'  => 'BUF',
				'color' => '#041E42', // Navy Blue
				// 'color' => '#A2AAAD', // Silver
				// 'color' => '#FFB81C', // Yellow
				// 'color' => '#C8102E', // Red
			],
			'Senators' => [
				'city'  => 'Ottawa',
				'code'  => 'OTT',
				'color' => '#C8102E', // Red
				// 'color' => '#010101', // Black
				// 'color' => '#C69214', // Gold
			],
			'Sharks' => [
				'city'  => 'San Jose',
				'code'  => 'SJS',
				'color' => '#006D75', // Teal
				// 'color' => '#010101', // Black
				// 'color' => '#E57200', // Orange
			],
			'Stars' => [
				'city'  => 'Dallas',
				'code'  => 'DAL',
				'color' => '#006847', // Green
				// 'color' => '#010101', // Black
				// 'color' => '#8A8D8F', // Silver
			],
			'Wild' => [
				'city'  => 'Minnesota',
				'code'  => 'MIN',
				'color' => '#154734', // Green
				// 'color' => '#DDCBA4', // Tan
				// 'color' => '#EAAA00', // Yellow
				// 'color' => '#A6192E', // Red
			],
		],
	];

	return $sport && isset( $cache[ $sport ] ) ? $cache[ $sport ] : $cache;
}