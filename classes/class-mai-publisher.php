<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The Mai Publisher compatibility class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Mai_Publisher {
	/**
	 * Construct the class.
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
		add_filter( 'mai_publisher_location_choices', [ $this, 'add_location_choices' ] );
		add_filter( 'mai_publisher_locations',        [ $this, 'add_locations' ] );
	}

	/**
	 * Add location choices.
	 *
	 * @since 0.1.0
	 *
	 * @param  array $choices The existing location choices.
	 *
	 * @return array The modified location choices.
	 */
	function add_location_choices( $choices ) {
		$choices['single']['in_web_results'] = __( 'In Matchup "Around the Web"', 'mai-asknews' );
		$choices['single']['in_sources']     = __( 'In Matchup "Latest News Sources"', 'mai-asknews' );

		return $choices;
	}

	/**
	 * Add locations.
	 *
	 * @since 0.1.0
	 *
	 * @param  array $locations The existing locations.
	 *
	 * @return array The modified locations.
	 */
	function add_locations( $locations ) {
		$locations['in_web_results'] = [
			'hook'          => 'pm_after_web_result',
			'content_count' => '3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36, 39, 42, 45, 48, 51, 54, 57, 60',
			'open'          => '<li class="pm-result">',
			'close'         => '</li>',
			'priority'      => 10,
			'target'        => 'bf',
		];

		$locations['in_sources'] = [
			'hook'          => 'pm_after_source',
			'content_count' => '3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36, 39, 42, 45, 48, 51, 54, 57, 60',
			'open'          => '<li class="pm-source">',
			'close'         => '</li>',
			'priority'      => 10,
			'target'        => 'bf',
		];

		return $locations;
	}
}

// add_filter( 'acf/location/rule_match/taxonomy', 'modify_acf_location_rule_for_taxonomies', 10, 3 );
// add_filter( 'acf/location/rule_match', 'modify_acf_location_rule_for_taxonomies', 10, 4 );
function modify_acf_location_rule_for_taxonomies( $match, $rule, $options, $field_group ) {
	if ( ! isset( $field_group['key'] ) || 'maipub_categories_field_group' !== $field_group['key'] ) {
		return $match;
	}

	// Get current screen.
	$screen = get_current_screen();

	// Bail if not on a league or season taxonomy term edit screen.
	if ( ! $screen || ! in_array( $screen->taxonomy, [ 'league', 'season' ] ) ) {
		return $match;
	}

	return true;
}