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
		add_action( 'wp_head',                         [ $this, 'do_timezone_logic' ] );
		add_action( 'wp_enqueue_scripts',              [ $this, 'enqueue' ] );
		add_filter( 'genesis_markup_entry-wrap_open',  [ $this, 'get_datetime' ], 10, 2 );
		add_filter( 'genesis_markup_entry-wrap_close', [ $this, 'get_predictions' ], 10, 2 );
		add_shortcode( 'pm_date',                      [ $this, 'date_shortcode' ] );
		add_shortcode( 'pm_matchup_time',              [ $this, 'matchup_time_shortcode' ] );
		add_shortcode( 'pm_matchup_teams',             [ $this, 'matchup_teams_shortcode' ] );
	}

	function do_timezone_logic() {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				// Get the user's timezone.
				var timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

				// Map of timezones to abbreviations, only using ET and PT for simplicity.
				var timezoneAbbreviations = {
					"America/New_York": "ET",
					"America/Chicago": "ET",
					"America/Denver": "PT",
					"America/Los_Angeles": "PT",
				};

				// Get the abbreviation, default to the full timezone if not found.
				var timezoneAbbreviation = timezoneAbbreviations[timezone] || timezone;

				// Add timezone as a data attribute to the body.
				document.body.setAttribute('data-timezone', timezoneAbbreviation);
			});
		</script>
		<?php
	}

	/**
	 * Enqueue CSS in the header.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function enqueue() {
		if ( ! ( maiasknews_is_archive() || is_singular( 'matchup' ) || is_front_page() ) ) {
			return;
		}

		maiasknews_enqueue_styles();
	}

	/**
	 * Get the datetime markup.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The default content.
	 * @param array  $args    The markup args.
	 *
	 * @return string
	 */
	function get_datetime( $content, $args ) {
		// Bail if not the opening markup.
		if ( ! ( isset( $args['open'] ) && $args['open'] ) ) {
			return $content;
		}

		// Get classes and context.
		$class   = isset( $args['params']['args']['class'] ) ? $args['params']['args']['class'] : '';
		$context = isset( $args['params']['args']['context'] ) ? $args['params']['args']['context'] : '';

		// Bail if not an archive or MPG with custom class..
		if ( ! ( 'archive' === $context || ( 'block' === $context && str_contains( $class, 'pm-matchups' ) ) ) ) {
			return $content;
		}

		// Get day and times.
		$event_date = get_post_meta( get_the_ID(), 'event_date', true );

		// Bail if no date.
		if ( ! $event_date ) {
			return $content;
		}

		// Force timestamp.
		if ( ! is_numeric( $event_date ) ) {
			$event_date = strtotime( $event_date );
		}

		// Get the date and times.
		$day      = date( 'M j ', $event_date );
		$time_utc = new DateTime( "@$event_date", new DateTimeZone( 'UTC' ) );
		$time_est = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'g:i a' ) . ' ET';
		$time_pst = $time_utc->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'g:i a' ) . ' PT';

		// Build the markup.
		$html  = '';
		$html .= '<div class="pm-matchup__date">';
			$html .= sprintf( '<span class="pm-matchup__day">%s</span>', $day );
			$html .= sprintf( '<span class="pm-matchup__time" data-timezone="ET">%s</span>', $time_est );
			$html .= sprintf( '<span class="pm-matchup__time" data-timezone="PT">%s</span>', $time_pst );
		$html .= '</div>';

		return $html . $content;
	}

	/**
	 * Get the predictions markup.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The default content.
	 * @param array  $args    The markup args.
	 *
	 * @return string
	 */
	function get_predictions( $content, $args ) {
		// Bail if not the closing markup.
		if ( ! ( isset( $args['close'] ) && $args['close'] ) ) {
			return $content;
		}

		// Get classes and context.
		$class   = isset( $args['params']['args']['class'] ) ? $args['params']['args']['class'] : '';
		$context = isset( $args['params']['args']['context'] ) ? $args['params']['args']['context'] : '';

		// Bail if not an archive or MPG with custom class..
		if ( ! ( 'archive' === $context || ( 'block' === $context && str_contains( $class, 'pm-matchups' ) ) ) ) {
			return $content;
		}

		// Bail if not an admin.
		if ( ! maiasknews_has_access() ) {
			return $content;
		}

		// Get the data.
		$body = maiasknews_get_insight_body( get_the_ID() );
		$list = maiasknews_get_prediction_list( $body );

		// Bail if no list.
		if ( ! $list ) {
			return $content;
		}

		// TODO: Write "admin only" and color like the singular box.

		return $list . $content;
	}

	/**
	 * Displays the date of the last update.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	function date_shortcode( $atts ) {
		return maiasknews_get_updated_date();
	}

	/**
	 * Displays the game date and time of the matchup.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	function matchup_time_shortcode( $atts ) {
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
	function matchup_teams_shortcode( $atts ) {
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