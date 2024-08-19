<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The display class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Display {
	/**
	 * Construct the class.
	 *
	 * @since 0.1.0
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
		add_shortcode( 'pm_matchup_time',  [ $this, 'time_shortcode' ] );
		add_shortcode( 'pm_matchup_teams', [ $this, 'teams_shortcode' ] );
	}

	/**
	 * Displays the game date and time of the matchup.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	function time_shortcode( $atts ) {
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

		return maiasknews_get_matchup_datetime( get_the_ID(), $atts['before'], $atts['after'] );
	}

	/**
	 * Currently unused?
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	function teams_shortcode( $atts ) {
		return maiasknews_get_matchup_teams_list( $atts );
	}

	/**
	 * Remove league parent from league taxonomy.
	 *
	 * @since 0.1.0
	 *
	 * @param  string $output The output.
	 * @param  array  $terms  The terms.
	 * @param  array  $atts   The attributes.
	 *
	 * @return string
	 */
	function remove_league_parent( $output, $terms, $atts ) {
		if ( ! isset( $atts['taxonomy'] ) || 'league' !== $atts['taxonomy'] ) {
			return $output;
		}

		// Remove the <a> tag if it has /mlb/, /nba/, /nhl/, or /nfl/ in the href.
		// $pattern = '#<a[^>]*>(MLB|NFL|NBA|NHL)</a>#i'; // Exact match.
		$pattern = '#<a[^>]*>[^<]*(MLB|NFL|NBA|NHL)[^<]*</a>#i'; // Contains.
		$output  = preg_replace( $pattern, '', $output );
		// $count   = 1;
		// $output  = str_replace( ', <a', '<a', $output, $count ); // Remove leading comma.

		return $output;
	}
}