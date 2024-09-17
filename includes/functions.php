<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Get the matchup data, including team names, outcome, bot choice, and user vote.
 *
 * @since TBD
 *
 * @param int     $matchup_id
 * @param WP_User $user
 *
 * @return array
 */
function maiasknews_get_matchup_data( $matchup_id, $user = null ) {
	static $cache = [];

	if ( isset( $cache[ $matchup_id ] ) ) {
		return $cache[ $matchup_id ];
	}

	// Get data.
	$league  = maiasknews_get_page_league();
	$data    = maiasknews_get_insight_body( $matchup_id );
	$vote    = maiasknews_get_user_vote( $matchup_id, $user );
	$outcome = (array) get_post_meta( $matchup_id, 'asknews_outcome', true );
	$winner  = isset( $outcome['winner']['team'] ) ? $outcome['winner']['team'] : '';
	$loser   = isset( $outcome['loser']['team'] ) ? $outcome['loser']['team'] : '';

	// Store in cache.
	$cache[ $matchup_id ] = [
		'home_short'   => isset( $data['home_team_name'] ) && $data['home_team_name'] ? $data['home_team_name'] : maiasknews_get_team_short_name( $data['home_team'], $league ),
		'away_short'   => isset( $data['away_team_name'] ) && $data['away_team_name'] ? $data['away_team_name'] : maiasknews_get_team_short_name( $data['away_team'], $league ),
		'home_full'    => $data['home_team'],
		'away_full'    => $data['away_team'],
		'winner'       => $winner,
		'loser'        => $loser,
		'winner_score' => isset( $outcome['winner']['score'] ) ? $outcome['winner']['score'] : '',
		'loser_score'  => isset( $outcome['loser']['score'] ) ? $outcome['loser']['score'] : '',
		'winner_home'  => $winner && $loser ? $winner === $data['home_team'] : '',
		'league'       => $league,
		'choice'       => maiasknews_get_key( 'choice', $data ),
		'vote'         => $vote['name'],
	];

	return $cache[ $matchup_id ];
}

/**
 * Get the league of the current page.
 *
 * @since 0.1.0
 *
 * @return string
 */
function maiasknews_get_page_league() {
	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$term = null;

	// Single matchup.
	if ( is_singular( 'matchup' ) ) {
		$terms = get_the_terms( get_the_ID(), 'league' );
		$top   = array_filter( $terms, function( $term ) { return 0 === $term->parent; });
		$term  = $top ? reset( $top ) : reset( $terms );

	}
	// League archive.
	elseif ( is_tax( 'league' ) ) {
		$term = get_queried_object();

	}
	// Season archive.
	elseif ( is_tax( 'season' ) ) {
		$league = get_query_var( 'league' );
		$term   = $league ? get_term_by( 'slug', $league, 'league' ) : null;
	}

	// If a WP_Term object.
	if ( ! ( $term && is_a( $term, 'WP_Term' ) ) ) {
		$term = $term->parent ? get_term( $term->parent, 'league' ) : $term;
	}

	$cache = $term ? $term->name : '';

	return $cache;
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
 * Get the URL of a file in the plugin.
 * Checks if script debug is enabled.
 *
 * @since 0.4.0
 *
 * @param string $filename The file name. Example: `dapper`.
 * @param string $type     The file type. Example: `css`.
 *
 * @return string
 */
function maiasknews_get_file_url( $filename, $type ) {
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	return MAI_ASKNEWS_URL . "build/{$type}/{$filename}{$suffix}.{$type}";
}

/**
 * Prevent post_modified update.
 *
 * @since 0.1.0
 *
 * @param array $data                An array of slashed, sanitized, and processed post data.
 * @param array $postarr             An array of sanitized (and slashed) but otherwise unmodified post data.
 * @param array $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed post data as originally passed to wp_insert_post() .
 * @param bool  $update              Whether this is an existing post being updated.
 *
 * @return array
 */
function maiasknews_prevent_post_modified_update( $data, $postarr, $unsanitized_postarr, $update ) {
	if ( $update && ! empty( $postarr['ID'] ) ) {
		// Get the existing post.
		$existing = get_post( $postarr['ID'] );

		// Preserve the current modified dates.
		if ( $existing ) {
			$data['post_modified']     = $existing->post_modified;
			$data['post_modified_gmt'] = $existing->post_modified_gmt;
		}
	}

	return $data;
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

	return isset( $cache[ $sport ][ $team ] ) ? $cache[ $sport ][ $team ] : '';
}

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
	static $cache = null;

	if ( ! is_null( $cache ) ) {
		if ( $sport ) {
			return isset( $cache[ $sport ] ) ? $cache[ $sport ] : [];
		}

		return $cache;
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