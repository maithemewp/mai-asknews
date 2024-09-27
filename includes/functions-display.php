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
	wp_enqueue_style( 'mai-asknews', maiasknews_get_file_url( 'mai-asknews', 'css' ), [], maiasknews_get_file_version( 'mai-asknews', 'css' ) );
}

/**
 * Enqueue the plugin scripts.
 *
 * @since TBD
 *
 * @param string $selected The selected team.
 *
 * @return void
 */
function maiasknews_enqueue_scripts( $selected ) {
	// Enqueue JS.
	wp_enqueue_script( 'mai-asknews-vote', maiasknews_get_file_url( 'mai-asknews-vote', 'js' ), [], maiasknews_get_file_version( 'mai-asknews-vote', 'js' ), true );
	wp_localize_script( 'mai-asknews-vote', 'maiAskNewsVars', [
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'selected' => $selected,
	] );
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
	$list       = [];
	$home        = isset( $body['home_team'] ) ? $body['home_team'] : '';
	$away        = isset( $body['away_team'] ) ? $body['away_team'] : '';
	$choice      = maiasknews_get_key( 'choice', $body );
	$probability = maiasknews_get_key( 'probability', $body );
	$probability = $probability ? $probability . '%' : '';
	$likelihood  = maiasknews_get_key( 'likelihood', $body );
	$final_score = maiasknews_get_key( 'final_score', $body );

	// $confidence     = maiasknews_get_key( 'confidence', $body );
	// $confidence     = $confidence ? maiasknews_format_confidence( $confidence ) : '';
	// $llm_confidence = maiasknews_get_key( 'llm_confidence', $body );

	// If choice.
	if ( $choice ) {
		$list['choice'] = [
			'hidden'  => __( 'Members Only', 'mai-asknews' ),
			'visible' => $choice,
		];
	}

	// If probability and likelihood.
	if ( $probability && $likelihood ) {
		// $list[ __( 'Chance', 'mai-asknews' ) ] = [
		// 	'hidden'  => __( 'Members Only', 'mai-asknews' ),
		// 	'visible' => sprintf( '%s, %s', $probability, $likelihood ),
		// ];
		$list['probability'] = [
			'hidden'  => __( 'Members Only', 'mai-asknews' ),
			'visible' => sprintf( '%s, %s', $probability, $likelihood ),
		];
	}

	// If final score.
	if ( $final_score ) {
		$team_name_1  = isset( $final_score[0]['team'] ) ? $final_score[0]['team'] : '';
		$team_name_2  = isset( $final_score[1]['team'] ) ? $final_score[1]['team'] : '';
		$team_score_1 = isset( $final_score[0]['score'] ) ? $final_score[0]['score'] : '';
		$team_score_2 = isset( $final_score[1]['score'] ) ? $final_score[1]['score'] : '';

		if ( $team_name_1 && $team_name_2 && $team_score_1 && $team_score_2 ) {
			// Build short names.
			$league      = maiasknews_get_page_league();
			$team_name_1 = maiasknews_get_team_short_name( $team_name_1, $league );
			$team_name_2 = maiasknews_get_team_short_name( $team_name_2, $league );

			// If tie.
			if ( $team_score_1 === $team_score_2 ) {
				$list['score'] = [
					'hidden'  => __( 'Members Only', 'mai-asknews' ),
					'visible' => sprintf( "Tie %s-%s", $team_score_1, $team_score_2 ),
				];
			}
			// If team 1 wins.
			elseif ( $team_score_1 > $team_score_2 ) {
				$list['score'] = [
					'hidden'  => __( 'Members Only', 'mai-asknews' ),
					'visible' => sprintf( "%s win %s-%s", $team_name_1, $team_score_1, $team_score_2 ),
				];
			}
			// If team 2 wins.
			else {
				$list['score'] = [
					'hidden'  => __( 'Members Only', 'mai-asknews' ),
					'visible' => sprintf( "%s win %s-%s", $team_name_2, $team_name_2, $team_score_1 ),
				];
			}
		}
	}

	// 0.3.0.
	// $list[ __( 'Confidence', 'mai-asknews' ) ]     = [ 'hidden' => __( 'Members Only', 'mai-asknews' ), 'visible' => '' ];
	// $list[ __( 'LLM Confidence', 'mai-asknews' ) ] = [ 'hidden' => __( 'Members Only', 'mai-asknews' ), 'visible' => '' ];
	// $list[ __( 'Likelihood', 'mai-asknews' ) ]     = [ 'hidden' => __( 'Members Only', 'mai-asknews' ), 'visible' => '' ];

	// Bail if no data.
	if ( ! array_filter( $list ) ) {
		return;
	}

	$html  = '';
	$html .= '<ul class="pm-prediction__list">';
		if ( ! is_singular( 'matchup' ) ) {
			$html .= sprintf( '<li class="pm-prediction__item label">%s</li>', __( 'Our Prediction', 'mai-asknews' ) );
		}

		// Loop through list.
		foreach ( $list as $class => $values ) {
			$value = $hidden ? $values['hidden'] : $values['visible'];
			// $html .= sprintf( '<li class="pm-prediction__item"><strong>%s:</strong> %s</li>', $values['icon'], $value );
			$html .= sprintf( '<li class="pm-prediction__item %s">%s</li>', $class, $value );
		}
	$html .= '</ul>';

	return $html;
}

/**
 * Get the odds table
 *
 * @since 0.1.0
 *
 * @param array $body   The insight body.
 * @param bool  $hidden Whether to obfuscate the table.
 *
 * @return string
 */
function maiasknews_get_odds_table( $body, $hidden = false ) {
	// Get the odds data.
	$html      = '';
	$league    = maiasknews_get_key( 'sport', $body );
	$odds_data = maiasknews_get_odds_data( $body );

	// If we have odds data.
	if ( ! $odds_data ) {
		return $html;
	}

	// Get home and away teams.
	list( $away_team, $home_team ) = array_keys( $odds_data );

	// Get short names.
	$away_short = maiasknews_get_team_short_name( $away_team, $league );
	$home_short = maiasknews_get_team_short_name( $home_team, $league );

	// Start the table data.
	$sites = [];

	// Loop through odds data.
	foreach ( $odds_data as $team => $data ) {
		// Merge the sites.
		$sites = array_merge( $sites, array_keys( $data['odds'] ) );
	}

	// Remove duplicates.
	$sites = array_unique( $sites );

	// Bail if no sites.
	if ( ! $sites ) {
		return $html;
	}

	// Top sites.
	$top_sites = [
		'draftkings',
		'betmgm',
		'fanduel',
		'bovada',
		'pointsbet',
		'hard rock bet',
	];

	// Start the odds.
	// $html .= sprintf( '<div class="pm-odds%s">', $hidden ? ' pm-obfuscated' : '' );
	$html .= '<div class="pm-odds">';
		// Heading.
		$html .= sprintf( '<p id="odds" class="has-xs-margin-bottom"><strong>%s:</strong></p>', __( 'Odds', 'mai-asknews' ) );

		// Add a checkbox to expand/collapse the odds.
		$toggle = '<div class="pm-toggle">';
			$toggle .= '<label class="pm-toggle_label">';
				$toggle .= __( 'Show All', 'mai-asknews' );
				$toggle .= '<input class="pm-toggle__input" name="pm-toggle__input" type="checkbox" />';
				$toggle .= '<span class="pm-toggle__slider"></span>';
			$toggle .= '</label>';
		$toggle .= '</div>';

		// Build the table
		$html .= '<table>';
			$html .= '<thead>';
				$html .= '<tr>';
					$html .= sprintf( '<th>%s</th>', $toggle );
					$html .= sprintf( '<th>%s</th>', $away_short );
					$html .= sprintf( '<th>%s</th>', $home_short );
				$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			$html .= '<tr class="is-top">';
				$html .= sprintf( '<td class="pm-odds__average">%s</td>', __( 'Average odds', 'mai-asknews' ) );

				// Loop through the odds.
				foreach ( $odds_data as $team => $values ) {
					// If hidden, show N/A.
					if ( $hidden ) {
						$rounded = 'N/A';
						$html   .= sprintf( '<td class="pm-odds__average">%s</td>', $rounded );
					}
					// Otherwise, show the average.
					else {
						$rounded = round( $values['average'], 2 );
						$html   .= sprintf( '<td class="pm-odds__average">%s%s</td>', $rounded > 0 ? '+' : '', $rounded );
					}
				}
			$html .= '</tr>';

			// Loop through the sites.
			foreach ( $sites as $maker ) {
				// Set class and odds.
				$class     = in_array( strtolower( $maker ), $top_sites ) ? 'is-top' : 'is-not-top';
				$away_odds = isset( $odds_data[ $away_team ]['odds'][ $maker ] ) ? (float) $odds_data[ $away_team ]['odds'][ $maker ] : '';
				$home_odds = isset( $odds_data[ $home_team ]['odds'][ $maker ] ) ? (float) $odds_data[ $home_team ]['odds'][ $maker ] : '';

				// If value, and it's positive, add a plus sign.
				$away_odds = $away_odds ? ( $away_odds > 0 ? '+' : '' ) . $away_odds : 'N/A';
				$home_odds = $home_odds ? ( $home_odds > 0 ? '+' : '' ) . $home_odds : 'N/A';

				// Build the row.
				$html .= sprintf( '<tr class="%s">', $class );
					$html .= sprintf( '<td>%s</td>', ucwords( $maker ) );

					// If hidden, show N/A.
					if ( $hidden ) {
						$html .= sprintf( '<td>%s</td>', __( 'N/A', 'mai-asknews' ) );
						$html .= sprintf( '<td>%s</td>', __( 'N/A', 'mai-asknews' ) );
					}
					// Otherwise, show the odds.
					else {
						$html .= sprintf( '<td>%s</td>', $away_odds );
						$html .= sprintf( '<td>%s</td>', $home_odds );
					}
				$html .= '</tr>';
			}

			$html .= '</tbody>';
		$html .= '</table>';
	$html .= '</div>';

	return $html;
}

/**
 * Get the teams list.
 *
 * @since TBD
 *
 * @param array $args The shortcode attributes.
 *
 * @return string
 */
function maisknews_get_teams_list( $args = [] ) {
	// Atts.
	$args = shortcode_atts(
		[
			'league' => '',
			'before' => '',
			'after'  => '',
		],
		$args,
		'pm_teams'
	);

	// Sanitize.
	$args = [
		'league' => sanitize_text_field( $args['league'] ),
		'before' => esc_html( $args['before'] ),
		'after'  => esc_html( $args['after'] ),
	];

	// If no league, get current.
	if ( ! $args['league'] ) {
		$args['league'] = maiasknews_get_page_league();
	}

	// Bail if no league.
	if ( ! $args['league'] ) {
		return '';
	}

	// Get the league object.
	$object = get_term_by( 'slug', strtolower( $args['league'] ), 'league' );

	// Bail if no league object.
	if ( ! $object ) {
		return '';
	}

	// Get child terms.
	$terms = get_terms(
		[
			'taxonomy'   => 'league',
			'hide_empty' => false,
			'parent'     => $object->term_id,
		]
	);

	// Bail if no terms.
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}

	// Get the teams.
	$list  = [];
	$new   = [];
	$teams = maiasknews_get_teams( $object->name );

	// Format teams array.
	foreach( $teams as $name => $values ) {
		$new[ $values['city'] . ' ' . $name ] = [
			'name'  => $name,
			'color' => $values['color'],
			'code'  => $values['code'],
		];
	}

	// Format the list.
	foreach ( $terms as $term ) {
		// Bail if no team.
		if ( ! ( $new && isset( $new[ $term->name ] ) ) ) {
			continue;
		}

		// Add to the list.
		$list[ $new[ $term->name ]['name'] ] = [
			'color' => $new[ $term->name ]['color'],
			'code'  => $new[ $term->name ]['code'],
			'link'  => get_term_link( $term ),
		];
	}

	// Order alphabetically by key.
	ksort( $list );

	// Get the HTML.
	$html  = '';
	$html .= '<ul class="pm-teams">';
		foreach ( $list as $name => $item ) {
			// These class names match the pm_matchup_teams shortcode, minus the team name span.
			$html .= sprintf( '<li class="pm-team" style="--team-color:%s;">', $item['color'] );
				$html .= sprintf( '<a class="pm-team__link" href="%s" data-code="%s"><span class="pm-team__name">%s</span></a>', $item['link'], $item['code'], $name );
			$html .= '</li>';
		}
	$html .= '</ul>';

	return $html;
}

/**
 * Get the team name from the league/team archive.
 *
 * @since TBD
 *
 * @param array $atts The shortcode attributes.
 *
 * @return string
 */
function maisknews_get_team_name( $atts ) {
	if ( ! is_tax( 'league' ) ) {
		return '';
	}

	// Atts.
	$atts = shortcode_atts(
		[
			'full_name' => false,
			'fallback'  => '',      // Accepts 'league'.
		],
		$atts,
		'pm_team'
	);

	// Sanitize.
	$atts = [
		'full_name' => rest_sanitize_boolean( $atts['full_name'] ),
		'fallback'  => sanitize_text_field( $atts['fallback'] ),
	];

	// Hash the args.
	$hash = md5( serialize( $atts ) );

	// Cache the results.
	static $cache = [];

	if ( isset( $cache[ $hash ] ) ) {
		return $cache[ $hash ];
	}

	// Set vars.
	$league = maiasknews_get_page_league();
	$term   = get_queried_object();
	$name   = $term ? $term->name : '';

	// If not showing full name.
	if ( ! $atts['full_name'] ) {
		$short = maiasknews_get_team_short_name( $name, $league );
		$name  = $short ?: $name;
	}

	// If no name and we have a fallback.
	if ( ! $name && $atts['fallback'] ) {
		// If falling back to league.
		if ( 'league' === $atts['fallback'] ) {
			$name = $league;
		}
		// Not league, use string.
		else {
			$name = $atts['fallback'];
		}
	}

	// Cache the results.
	$cache[ $hash ] = $name;

	return $cache[ $hash ];
}

/**
 * Get the short name of a team.
 *
 * @since TBD
 *
 * @param string $team  The team name.
 * @param string $sport The sport.
 *
 * @return string
 */
function maiasknews_get_team_short_name( $team, $sport ) {
	static $cache = [];

	if ( $cache && isset( $cache[ $sport ][ $team ] ) ) {
		return $cache[ $sport ][ $team ];
	}

	$teams = maiasknews_get_teams( $sport );

	foreach( $teams as $name => $values ) {
		$cache[ $sport ][ $values['city'] . ' ' . $name ] = $name;
	}

	return isset( $cache[ $sport ][ $team ] ) ? $cache[ $sport ][ $team ] : $team;
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
