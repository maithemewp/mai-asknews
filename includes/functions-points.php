<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Calculate the points for a matchup.
 *
 * @since TBD
 *
 * @param float $odds The odds for the team.
 *
 * @return float
 */
function maiasknews_get_odds_points( $odds ) {
	// If favorite (negative odds).
	if ( $odds < 0 ) {
		$points = (100 / abs($odds)) * 10;
	}
	// Underdog (positive odds).
	else {
		$points = ($odds / 100) * 10;
	}

	// Return the calculated points, rounded to 2 decimal places.
	return maiasknews_parse_float( round( $points, 2 ) );
}

/**
 * Get the average odds for a matchup.
 * Returns an array with the average odds for the away and home teams.
 * The array is ordered by the away team first.
 *
 * @since TBD
 *
 * @param array $body The insight body.
 *
 * @return array
 */
function maiasknews_get_odds_data( $body ) {
	static $odds = [];

	// Get the forecast UUID.
	$forecast_uuid = maiasknews_get_key( 'forecast_uuid', $body );

	// Maybe return cache.
	if ( ! $forecast_uuid || isset( $odds[ $forecast_uuid ] ) ) {
		return $odds[ $forecast_uuid ];
	}

	// Get the body and odds data.
	$odds_data = maiasknews_get_key( 'odds_info', $body );
	$odds_data = $odds_data && is_array( $odds_data ) ? $odds_data : [];
	$away_long = maiasknews_get_key( 'away_team', $body );
	$home_long = maiasknews_get_key( 'home_team', $body );

	// Bail if no odds data or team names.
	if ( ! ( $odds_data && $away_long && $home_long ) ) {
		$odds[ $forecast_uuid ] = [];
		return $odds[ $forecast_uuid ];
	}

	// Start the new odds and averages.
	$odds[ $forecast_uuid ] = [];

	// Loop through the odds data for each team.
	foreach ( $odds_data as $team_long => $sites ) {
		// Start the decimal sum.
		$decimal_sum = 0;

		// Convert each odd to decimal and sum them
		foreach ( $sites as $site_name => $odd ) {
			$decimal_sum += maiasknews_american_to_decimal( (float) $odd );
		}

		// Find the average of the decimal odds
		$decimal_avg = $decimal_sum / count( $sites );

		// Convert the average decimal odds back to American odds.
		$average = round( maiasknews_decimal_to_american( $decimal_avg ) );

		// Add to $odds array.
		$odds[ $forecast_uuid ][ $team_long ] = [
			'average' => $average,
			'odds'    => $sites,
		];
	}

	// Order by away team first.
	$odds[ $forecast_uuid ] = array_merge(
		[ $away_long => $odds[ $forecast_uuid ][ $away_long ] ],
		[ $home_long => $odds[ $forecast_uuid ][ $home_long ] ]
	);

	return $odds[ $forecast_uuid ];
}
