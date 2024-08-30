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
	$file      = 'assets/css/mai-asknews.css';
	$file_path = MAI_ASKNEWS_DIR . $file;
	$file_url  = MAI_ASKNEWS_URL . $file;
	$version  .= '.' . date( 'njYHi', filemtime( $file_path ) );

	wp_enqueue_style( 'mai-asknews', $file_url, [], $version );
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
	$time_utc = new DateTime( "@$event_date", new DateTimeZone( 'UTC' ) );
	$day_est  = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'l, M j, Y' );
	$time_est = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'g:i a' ) . ' ET';
	$time_pst = $time_utc->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'g:i a' ) . ' PT';
	$before   = $before ? sprintf( '<strong>%s</strong> ', $before ) : '';

	return sprintf( '<p class="pm-datetime">%s%s @ <span data-timezone="ET">%s</span> <span data-timezonesep>/</span> <span data-timezone="PT">%s</span></p>', $before, $day_est, $time_est, $time_pst );
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
 * Get the prediction list.
 *
 * @since 0.1.0
 *
 * @param array $body The insight body.
 * @param bool  $hidden Whether to hide the list.
 *
 * @return array
 */
function maiasknews_get_prediction_list( $body, $hidden = false ) {
	$home        = isset( $body['home_team'] ) ? $body['home_team'] : '';
	$away        = isset( $body['away_team'] ) ? $body['away_team'] : '';
	$choice      = maiasknews_get_key( 'choice', $body );
	$probability = maiasknews_get_key( 'probability', $body );
	$probability = $probability ? $probability . '%' : '';
	$likelihood  = maiasknews_get_key( 'likelihood', $body );

	// TODO:
	// crystal ball next to prediction
	// dice next to probability
	// thumbs up/down next to likelihood?

	// $confidence     = maiasknews_get_key( 'confidence', $body );
	// $confidence     = $confidence ? maiasknews_format_confidence( $confidence ) : '';
	// $llm_confidence = maiasknews_get_key( 'llm_confidence', $body );

	// Get list body.
	$table = [
		__( 'Prediction', 'mai-asknews' ) => [
			// 'hidden'  => sprintf( '%s %s %s', $home, __( 'or', 'mai-asknews' ), $away ),
			'hidden'  => __( 'Members Only', 'mai-asknews' ),
			'visible' => $choice,
		],
		__( 'Probability', 'mai-asknews' ) => [
			'hidden'  => __( 'Members Only', 'mai-asknews' ),
			'visible' => sprintf( '%s, %s', $probability, $likelihood ),
		],
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
	foreach ( $table as $label => $values ) {
		$value = $hidden ? sprintf( '<span class="d">%s</span>', $values['hidden'] ) : $values['visible'];
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
			$sum += (float) $odd;
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
				// Set class and odds.
				$class     = in_array( strtolower( $maker ), $top_sites ) ? 'is-top' : 'is-not-top';
				$home_odds = isset( $odds_data[ $home_team ][ $maker ] ) ? (float) $odds_data[ $home_team ][ $maker ] : '';
				$away_odds = isset( $odds_data[ $away_team ][ $maker ] ) ? (float) $odds_data[ $away_team ][ $maker ] : '';

				// If value, and it's positive, add a plus sign.
				$home_odds = $home_odds ? ( $home_odds > 0 ? '+' : '' ) . $home_odds : 'N/A';
				$away_odds = $away_odds ? ( $away_odds > 0 ? '+' : '' ) . $away_odds : 'N/A';

				// Build the row.
				$html .= sprintf( '<tr class="%s">', $class );
					$html .= sprintf( '<td>%s</td>', ucwords( $maker ) );
					$html .= sprintf( '<td>%s</td>', $home_odds );
					$html .= sprintf( '<td>%s</td>', $away_odds );
				$html .= '</tr>';
			}

			$html .= '</tbody>';
		$html .= '</table>';
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
		$team   = isset( $wp_query->query_vars['league'] ) ? $wp_query->query_vars['league'] : '';
		$team   = $team ? get_term_by( 'slug', $team, 'league' ) : '';
		$league = $team && $team->parent ? get_term( $team->parent, 'league' ) : '';
		$season = isset( $wp_query->query_vars['term'] ) ? $wp_query->query_vars['term'] : '';
		$season = $season ? get_term_by( 'slug', $season, 'season' ) : '';

		// Maybe add league.
		if ( $league ) {
			$breadcumbs[] = [
				'url'  => get_term_link( $league ),
				'text' => $league->name,
			];
		}

		// Maybe add team.
		if ( $team ) {
			$breadcumbs[] = [
				'url'  => get_term_link( $team ),
				'text' => $team->name,
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
