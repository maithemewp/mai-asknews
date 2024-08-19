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
		__( 'Probability', 'mai-asknews' )    => sprintf( '%s, %s', $probability, $likelihood ),
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

			foreach ( $sites as $maker ) {
				$class = in_array( strtolower( $maker ), $top_sites ) ? 'is-top' : 'is-not-top';

				$html .= sprintf( '<tr class="%s">', $class );
					$html .= sprintf( '<td>%s</td>', $maker );
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

	return sprintf( '<p class="pm-datetime">%s%s @ %s / %s</p>', $before, $day, $time_est, $time_pst );
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

function maiasknews_get_teams( $sport ) {
	static $cache = [];

	if ( isset( $cache[ $sport ] ) ) {
		return $cache[ $sport ];
	}

	$cache = [
		'MLB' => [
			// 'Angels' => [
			// 	'city'   => 'Los Angeles',
			// 	'code'   => 'LAA',
			// 	'accent' => '#BA0021',
			// 	'base'   => '#013263',
			// ],
			'Angels' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAA',
				'color' => '#BA0021'
			],
			'Astros' => [
				'city'  => 'Houston',
				'code'  => 'HOU',
				'color' => '#002D62'
			],
			'Athletics' => [
				'city'  => 'Oakland',
				'code'  => 'OAK',
				'color' => '#003831'
			],
			'Blue Jays' => [
				'city'  => 'Toronto',
				'code'  => 'TOR',
				'color' => '#134A8E'
			],
			'Braves' => [
				'city'  => 'Atlanta',
				'code'  => 'ATL',
				'color' => '#002855'
			],
			'Brewers' => [
				'city'  => 'Milwaukee',
				'code'  => 'MIL',
				'color' => '#FFC52F'
			],
			'Cardinals' => [
				'city'  => 'St. Louis',
				'code'  => 'STL',
				'color' => '#C41E3A'
			],
			'Cubs' => [
				'city'  => 'Chicago',
				'code'  => 'CHC',
				'color' => '#022F6D'
			],
			'Diamondbacks' => [
				'city'  => 'Arizona',
				'code'  => 'ARI',
				'color' => '#A71930'
			],
			'Dodgers' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAD',
				'color' => '#022F6D'
			],
			'Giants' => [
				'city'  => 'San Francisco',
				'code'  => 'SF',
				'color' => '#FD5A1E'
			],
			'Guardians' => [
				'city'  => 'Cleveland',
				'code'  => 'CLE',
				'color' => '#0F223E'
			],
			'Mariners' => [
				'city'  => 'Seattle',
				'code'  => 'SEA',
				'color' => '#0C2C56'
			],
			'Marlins' => [
				'city'  => 'Miami',
				'code'  => 'MIA',
				'color' => '#00A3E0'
			],
			'Mets' => [
				'city'  => 'New York',
				'code'  => 'NYM',
				'color' => '#002D72'
			],
			'Nationals' => [
				'city'  => 'Washington',
				'code'  => 'WSH',
				'color' => '#AB0003'
			],
			'Orioles' => [
				'city'  => 'Baltimore',
				'code'  => 'BAL',
				'color' => '#FC4D03'
			],
			'Padres' => [
				'city'  => 'San Diego',
				'code'  => 'SD',
				'color' => '#2F241D'
			],
			'Phillies' => [
				'city'  => 'Philadelphia',
				'code'  => 'PHI',
				'color' => '#E81828'
			],
			'Pirates' => [
				'city'  => 'Pittsburgh',
				'code'  => 'PIT',
				'color' => '#FDB827'
			],
			'Rangers' => [
				'city'  => 'Texas',
				'code'  => 'TEX',
				'color' => '#003278'
			],
			'Rays' => [
				'city'  => 'Tampa Bay',
				'code'  => 'TB',
				'color' => '#092C5C'
			],
			'Reds' => [
				'city'  => 'Cincinnati',
				'code'  => 'CIN',
				'color' => '#D50032'
			],
			'Red Sox' => [
				'city'  => 'Boston',
				'code'  => 'BOS',
				'color' => '#C8112E'
			],
			'Rockies' => [
				'city'  => 'Colorado',
				'code'  => 'COL',
				'color' => '#333366'
			],
			'Royals' => [
				'city'  => 'Kansas City',
				'code'  => 'KC',
				'color' => '#004687'
			],
			'Tigers' => [
				'city'  => 'Detroit',
				'code'  => 'DET',
				'color' => '#0C2340'
			],
			'Twins' => [
				'city'  => 'Minnesota',
				'code'  => 'MIN',
				'color' => '#002B5C'
			],
			'White Sox' => [
				'city'  => 'Chicago',
				'code'  => 'CWS',
				'color' => '#28241F'
			],
			'Yankees' => [
				'city'  => 'New York',
				'code'  => 'NYY',
				'color' => '#003087'
			],
		],
		'NFL' => [
			'49ers' => [
				'city'  => 'San Francisco',
				'code'  => 'SF',
				'color' => '#AA0000'
			],
			'Bears' => [
				'city'  => 'Chicago',
				'code'  => 'CHI',
				'color' => '#0B162A'
			],
			'Bengals' => [
				'city'  => 'Cincinnati',
				'code'  => 'CIN',
				'color' => '#FB4F14'
			],
			'Bills' => [
				'city'  => 'Buffalo',
				'code'  => 'BUF',
				'color' => '#00338D'
			],
			'Broncos' => [
				'city'  => 'Denver',
				'code'  => 'DEN',
				'color' => '#FB4F14'
			],
			'Browns' => [
				'city'  => 'Cleveland',
				'code'  => 'CLE',
				'color' => '#311D00'
			],
			'Buccaneers' => [
				'city'  => 'Tampa Bay',
				'code'  => 'TB',
				'color' => '#D50A0A'
			],
			'Cardinals' => [
				'city'  => 'Arizona',
				'code'  => 'ARI',
				'color' => '#97233F'
			],
			'Chargers' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAC',
				'color' => '#0080C6'
			],
			'Chiefs' => [
				'city'  => 'Kansas City',
				'code'  => 'KC',
				'color' => '#E31837'
			],
			'Colts' => [
				'city'  => 'Indianapolis',
				'code'  => 'IND',
				'color' => '#002C5F'
			],
			'Commanders' => [
				'city'  => 'Washington',
				'code'  => 'WAS',
				'color' => '#773141'
			],
			'Cowboys' => [
				'city'  => 'Dallas',
				'code'  => 'DAL',
				'color' => '#041E42'
			],
			'Dolphins' => [
				'city'  => 'Miami',
				'code'  => 'MIA',
				'color' => '#008E97'
			],
			'Eagles' => [
				'city'  => 'Philadelphia',
				'code'  => 'PHI',
				'color' => '#004C54'
			],
			'Falcons' => [
				'city'  => 'Atlanta',
				'code'  => 'ATL',
				'color' => '#A71930'
			],
			'Giants' => [
				'city'  => 'New York',
				'code'  => 'NYG',
				'color' => '#0B2265'
			],
			'Jaguars' => [
				'city'  => 'Jacksonville',
				'code'  => 'JAX',
				'color' => '#006778'
			],
			'Jets' => [
				'city'  => 'New York',
				'code'  => 'NYJ',
				'color' => '#125740'
			],
			'Lions' => [
				'city'  => 'Detroit',
				'code'  => 'DET',
				'color' => '#0076B6'
			],
			'Packers' => [
				'city'  => 'Green Bay',
				'code'  => 'GB',
				'color' => '#203731'
			],
			'Panthers' => [
				'city'  => 'Carolina',
				'code'  => 'CAR',
				'color' => '#0085CA'
			],
			'Patriots' => [
				'city'  => 'New England',
				'code'  => 'NE',
				'color' => '#002244'
			],
			'Raiders' => [
				'city'  => 'Las Vegas',
				'code'  => 'LV',
				'color' => '#A5ACAF'
			],
			'Rams' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAR',
				'color' => '#003594'
			],
			'Ravens' => [
				'city'  => 'Baltimore',
				'code'  => 'BAL',
				'color' => '#241773'
			],
			'Saints' => [
				'city'  => 'New Orleans',
				'code'  => 'NO',
				'color' => '#D3BC8D'
			],
			'Seahawks' => [
				'city'  => 'Seattle',
				'code'  => 'SEA',
				'color' => '#002244'
			],
			'Steelers' => [
				'city'  => 'Pittsburgh',
				'code'  => 'PIT',
				'color' => '#FFB612'
			],
			'Texans' => [
				'city'  => 'Houston',
				'code'  => 'HOU',
				'color' => '#03202F'
			],
			'Titans' => [
				'city'  => 'Tennessee',
				'code'  => 'TEN',
				'color' => '#4B92DB'
			],
			'Vikings' => [
				'city'  => 'Minnesota',
				'code'  => 'MIN',
				'color' => '#4F2683'
			],
		],
		'NBA' => [
			'76ers' => [
				'city'  => 'Philadelphia',
				'code'  => 'PHI',
				'color' => '#006BB6'
			],
			'Bucks' => [
				'city'  => 'Milwaukee',
				'code'  => 'MIL',
				'color' => '#00471B'
			],
			'Bulls' => [
				'city'  => 'Chicago',
				'code'  => 'CHI',
				'color' => '#CE1141'
			],
			'Cavaliers' => [
				'city'  => 'Cleveland',
				'code'  => 'CLE',
				'color' => '#6F263D'
			],
			'Celtics' => [
				'city'  => 'Boston',
				'code'  => 'BOS',
				'color' => '#007A33'
			],
			'Clippers' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAC',
				'color' => '#C8102E'
			],
			'Grizzlies' => [
				'city'  => 'Memphis',
				'code'  => 'MEM',
				'color' => '#5D76A9'
			],
			'Hawks' => [
				'city'  => 'Atlanta',
				'code'  => 'ATL',
				'color' => '#E03A3E'
			],
			'Heat' => [
				'city'  => 'Miami',
				'code'  => 'MIA',
				'color' => '#98002E'
			],
			'Hornets' => [
				'city'  => 'Charlotte',
				'code'  => 'CHA',
				'color' => '#1D1160'
			],
			'Jazz' => [
				'city'  => 'Utah',
				'code'  => 'UTA',
				'color' => '#002B5C'
			],
			'Kings' => [
				'city'  => 'Sacramento',
				'code'  => 'SAC',
				'color' => '#5A2D81'
			],
			'Knicks' => [
				'city'  => 'New York',
				'code'  => 'NYK',
				'color' => '#006BB6'
			],
			'Lakers' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAL',
				'color' => '#552583'
			],
			'Magic' => [
				'city'  => 'Orlando',
				'code'  => 'ORL',
				'color' => '#0077C0'
			],
			'Mavericks' => [
				'city'  => 'Dallas',
				'code'  => 'DAL',
				'color' => '#00538C'
			],
			'Nets' => [
				'city'  => 'Brooklyn',
				'code'  => 'BKN',
				'color' => '#000000'
			],
			'Nuggets' => [
				'city'  => 'Denver',
				'code'  => 'DEN',
				'color' => '#0E2240'
			],
			'Pacers' => [
				'city'  => 'Indiana',
				'code'  => 'IND',
				'color' => '#002D62'
			],
			'Pelicans' => [
				'city'  => 'New Orleans',
				'code'  => 'NOP',
				'color' => '#0C2340'
			],
			'Pistons' => [
				'city'  => 'Detroit',
				'code'  => 'DET',
				'color' => '#C8102E'
			],
			'Raptors' => [
				'city'  => 'Toronto',
				'code'  => 'TOR',
				'color' => '#CE1141'
			],
			'Rockets' => [
				'city'  => 'Houston',
				'code'  => 'HOU',
				'color' => '#CE1141'
			],
			'Spurs' => [
				'city'  => 'San Antonio',
				'code'  => 'SAS',
				'color' => '#C4CED4'
			],
			'Suns' => [
				'city'  => 'Phoenix',
				'code'  => 'PHX',
				'color' => '#1D1160'
			],
			'Thunder' => [
				'city'  => 'Oklahoma City',
				'code'  => 'OKC',
				'color' => '#007AC1'
			],
			'Timberwolves' => [
				'city'  => 'Minnesota',
				'code'  => 'MIN',
				'color' => '#0C2340'
			],
			'Trail Blazers' => [
				'city'  => 'Portland',
				'code'  => 'POR',
				'color' => '#E03A3E'
			],
			'Warriors' => [
				'city'  => 'Golden State',
				'code'  => 'GSW',
				'color' => '#1D428A'
			],
			'Wizards' => [
				'city'  => 'Washington',
				'code'  => 'WAS',
				'color' => '#002B5C'
			],
		],
		'NHL' => [
			'Blackhawks' => [
				'city'  => 'Chicago',
				'code'  => 'CHI',
				'color' => '#CF0A2C'
			],
			'Blue Jackets' => [
				'city'  => 'Columbus',
				'code'  => 'CBJ',
				'color' => '#002654'
			],
			'Blues' => [
				'city'  => 'St. Louis',
				'code'  => 'STL',
				'color' => '#002F87'
			],
			'Bruins' => [
				'city'  => 'Boston',
				'code'  => 'BOS',
				'color' => '#FFB81C'
			],
			'Canadiens' => [
				'city'  => 'Montreal',
				'code'  => 'MTL',
				'color' => '#AF1E2D'
			],
			'Canucks' => [
				'city'  => 'Vancouver',
				'code'  => 'VAN',
				'color' => '#00205B'
			],
			'Capitals' => [
				'city'  => 'Washington',
				'code'  => 'WSH',
				'color' => '#C8102E'
			],
			'Coyotes' => [
				'city'  => 'Arizona',
				'code'  => 'ARI',
				'color' => '#8C2633'
			],
			'Devils' => [
				'city'  => 'New Jersey',
				'code'  => 'NJD',
				'color' => '#CE1126'
			],
			'Ducks' => [
				'city'  => 'Anaheim',
				'code'  => 'ANA',
				'color' => '#F47A38'
			],
			'Flames' => [
				'city'  => 'Calgary',
				'code'  => 'CGY',
				'color' => '#C8102E'
			],
			'Flyers' => [
				'city'  => 'Philadelphia',
				'code'  => 'PHI',
				'color' => '#F74902'
			],
			'Golden Knights' => [
				'city'  => 'Vegas',
				'code'  => 'VGK',
				'color' => '#B4975A'
			],
			'Hurricanes' => [
				'city'  => 'Carolina',
				'code'  => 'CAR',
				'color' => '#CC0000'
			],
			'Islanders' => [
				'city'  => 'New York',
				'code'  => 'NYI',
				'color' => '#00539B'
			],
			'Jets' => [
				'city'  => 'Winnipeg',
				'code'  => 'WPG',
				'color' => '#041E42'
			],
			'Kings' => [
				'city'  => 'Los Angeles',
				'code'  => 'LAK',
				'color' => '#111111'
			],
			'Kraken' => [
				'city'  => 'Seattle',
				'code'  => 'SEA',
				'color' => '#355464'
			],
			'Lightning' => [
				'city'  => 'Tampa Bay',
				'code'  => 'TBL',
				'color' => '#002868'
			],
			'Maple Leafs' => [
				'city'  => 'Toronto',
				'code'  => 'TOR',
				'color' => '#003E7E'
			],
			'Oilers' => [
				'city'  => 'Edmonton',
				'code'  => 'EDM',
				'color' => '#FF4C00'
			],
			'Panthers' => [
				'city'  => 'Florida',
				'code'  => 'FLA',
				'color' => '#C8102E'
			],
			'Penguins' => [
				'city'  => 'Pittsburgh',
				'code'  => 'PIT',
				'color' => '#FFB81C'
			],
			'Predators' => [
				'city'  => 'Nashville',
				'code'  => 'NSH',
				'color' => '#FFB81C'
			],
			'Rangers' => [
				'city'  => 'New York',
				'code'  => 'NYR',
				'color' => '#0038A8'
			],
			'Red Wings' => [
				'city'  => 'Detroit',
				'code'  => 'DET',
				'color' => '#CE1126'
			],
			'Sabres' => [
				'city'  => 'Buffalo',
				'code'  => 'BUF',
				'color' => '#002654'
			],
			'Senators' => [
				'city'  => 'Ottawa',
				'code'  => 'OTT',
				'color' => '#C52032'
			],
			'Sharks' => [
				'city'  => 'San Jose',
				'code'  => 'SJS',
				'color' => '#006D75'
			],
			'Stars' => [
				'city'  => 'Dallas',
				'code'  => 'DAL',
				'color' => '#006847'
			],
			'Wild' => [
				'city'  => 'Minnesota',
				'code'  => 'MIN',
				'color' => '#154734'
			],
		],
	];

	return isset( $cache[ $sport ] ) ? $cache[ $sport ] : [];
}