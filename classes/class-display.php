<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The display class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Display {
	protected $can_vote = false;

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
		add_action( 'template_redirect',               [ $this, 'set_vars' ] );
		add_action( 'wp_head',                         [ $this, 'do_timezone_logic' ] );
		add_action( 'wp_enqueue_scripts',              [ $this, 'enqueue' ] );
		add_action( 'after_setup_theme',               [ $this, 'breadcrumbs' ] );
		add_filter( 'get_post_metadata',               [ $this, 'fallback_thumbnail_id' ], 10, 4 );
		add_filter( 'genesis_markup_entry-wrap_open',  [ $this, 'get_datetime' ], 10, 2 );
		add_filter( 'mai_post_grid_query_args',        [ $this, 'mpg_query_args' ], 10, 2 );
		add_filter( 'genesis_markup_entry-wrap_close', [ $this, 'get_predictions' ], 10, 2 );
		add_filter( 'mai_template-parts_config',       [ $this, 'add_ccas' ] );
		add_shortcode( 'pm_date',                      [ $this, 'date_shortcode' ] );
		add_shortcode( 'pm_matchup_time',              [ $this, 'matchup_time_shortcode' ] );
		add_shortcode( 'pm_matchup_teams',             [ $this, 'matchup_teams_shortcode' ] );
		add_shortcode( 'pm_teams',                     [ $this, 'teams_shortcode' ] );
		// add_filter( 'do_shortcode_tag',                [ $this, 'register_form_tag' ], 10, 2 );
		add_filter( 'do_shortcode_tag',                [ $this, 'subscription_details_tag' ], 10, 2 );
	}

	function set_vars() {
		// Bail if not a league-specific archive.
		if ( ! ( is_tax( 'league' ) || is_tax( 'season' ) ) ) {
			return;
		}

		$league = maiasknews_get_page_league();
		$access = maiasknews_has_pro_access( $league );

	}

	/**
	 * Do timezone logic.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
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
		maiasknews_enqueue_styles();
	}

	/**
	 * Swap breadcrumbs.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function breadcrumbs() {
		remove_action( 'genesis_before_content_sidebar_wrap', 'mai_do_breadcrumbs', 12 );
		add_action( 'genesis_before_content_sidebar_wrap', [ $this, 'do_breadcrumbs' ], 12 );
	}

	/**
	 * Maybe swap breadcrumbs.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_breadcrumbs() {
		$is_tax      = is_tax( 'league' ) || is_tax( 'season' );
		$is_singular = is_singular( 'matchup' );

		if ( $is_tax || $is_singular ) {
			maiasknews_do_breadcrumbs();
		} else {
			mai_do_breadcrumbs();
		}
	}

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
	function fallback_thumbnail_id( $value, $post_id, $meta_key, $single ) {
		// Bail if in admin.
		if ( is_admin() ) {
			return $value;
		}

		// Bail if not the key we want.
		if ( '_thumbnail_id' !== $meta_key ) {
			return $value;
		}

		// Remove filter to avoid loopbacks.
		remove_filter( 'get_post_metadata', [ $this, 'fallback_thumbnail_id' ], 10, 4 );

		// Check for an existing featured image.
		$image_id = get_post_thumbnail_id( $post_id );

		// Add back our filter.
		add_filter( 'get_post_metadata', [ $this, 'fallback_thumbnail_id' ], 10, 4 );

		// Bail if we already have a featured image.
		if ( $image_id ) {
			return $image_id;
		}

		// Set fallback image.
		$image_id = 2624;

		return $image_id;
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
		$time_utc = new DateTime( "@$event_date", new DateTimeZone( 'UTC' ) );
		$day_est  = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'M j' );
		$time_est = $time_utc->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( 'g:i a' ) . ' ET';
		$time_pst = $time_utc->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'g:i a' ) . ' PT';

		// Build the markup.
		$html  = '';
		$html .= '<div class="pm-matchup__date">';
			$html .= sprintf( '<span class="pm-matchup__day">%s</span>', $day_est );
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

		// Bail if not an archive or MPG with custom class.
		if ( ! ( 'archive' === $context || ( 'block' === $context && str_contains( $class, 'pm-matchups' ) ) ) ) {
			return $content;
		}

		// Get the data.
		$access = maiasknews_has_access();
		$hidden = ! $access;
		$data   = maiasknews_get_insight_body( get_the_ID() );
		$list   = maiasknews_get_prediction_list( $data, $hidden );
		$vote   = maiasknews_get_archive_vote_box();

		// Build the markup.
		$html = '<div class="pm-archive-content">';
			$html .= $list;
			$html .= $vote;
		$html .= '</div>';

		return $html . $content;
	}

	/**
	 * Add the tax query to the MPG query.
	 *
	 * @param array $query_args WP_Query args.
	 * @param array $args       Mai Post Grid args.
	 *
	 * @return array
	 */
	function mpg_query_args( $query_args, $args ) {
		if ( ! isset( $args['class'] ) || empty( $args['class'] ) ) {
			return $query_args;
		}

		if ( ! mai_has_string( 'pm-upcoming-matchups', $args['class'] ) ) {
			return $query_args;
		}

		$query_args['meta_query'] = [
			[
				'key'     => 'event_date',
				'value'   => strtotime( '-2 hours' ),
				'compare' => '>',
				'type'    => 'NUMERIC',
			],
		];

		// Sort by event date.
		$query_args['orderby']  = 'meta_value_num';
		$query_args['order']    = 'ASC';
		$query_args['meta_key'] = 'event_date';

		return $query_args;
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
	function add_ccas( $ccas ) {
		$ccas['matchup-promo-1'] = [
			'hook' => 'pm_cca',
		];

		$ccas['matchup-promo-2'] = [
			'hook' => 'pm_cca',
		];

		return $ccas;
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
	 * Displays the matchup teams.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	function matchup_teams_shortcode( $atts ) {
		return maiasknews_get_matchup_teams_list( $atts );
	}

	/**
	 * Displays the teams.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	function teams_shortcode( $atts ) {
		return maisknews_get_teams_list( $atts );
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

	/**
	 * Add buttons to the membership levels.
	 *
	 * @since 0.1.0
	 *
	 * @param  string $output The output.
	 * @param  string $tag    The tag.
	 *
	 * @return string
	 */
	function register_form_tag( $output, $tag ) {
		if ( 'register_form' !== $tag ) {
			return $output;
		}

		// Add buttons to the membership levels.
		$button = sprintf( '<div class="button button-secondary button-small">%s</div>', __( 'Choose Option', 'mai-asknews' ) );
		$output = preg_replace( '/(<div\s+class="rcp_level_description">.*?<\/div>)/s', '$1' . $button, $output );

		// Set up tag processor.
		$tags = new WP_HTML_Tag_Processor( $output );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'button', 'class_name' => 'rcp_button' ] ) ) {
			$tags->add_class( 'button button-secondary button-small' );
		}

		$output = $tags->get_updated_html();

		return $output;
	}

	/**
	 * Modify buttons on subscription details table.
	 *
	 * @since 0.1.0
	 *
	 * @param  string $output The output.
	 * @param  string $tag    The tag.
	 *
	 * @return string
	 */
	function subscription_details_tag( $output, $tag ) {
		if ( 'subscription_details' !== $tag ) {
			return $output;
		}

		// Set up tag processor.
		$tags = new WP_HTML_Tag_Processor( $output );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'button' ] ) ) {
			$tags->add_class( 'button button-secondary button-small' );
		}

		$output = $tags->get_updated_html();

		return $output;
	}
}
