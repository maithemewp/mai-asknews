<?php

/**
 * Parse a float value.
 * If the float value is an integer, return it as an integer.
 *
 * @param int|float $value The value to parse.
 *
 * @return int|float
 */
function maiasknews_parse_float( $value ) {
	$float = (float) $value;
	$int   = (int) $float;

	return $int === $float ? $int : $float;
}

/**
 * Get formatted confidence.
 * I think this is unused?
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
 * Convert American odds to decimal.
 *
 * @access private
 *
 * @since 0.1.0
 *
 * @param float $value
 *
 * @return float
 */
function maiasknews_american_to_decimal( $value ) {
	// If the odds are positive.
	if ( $value > 0 ) {
		return 1 + ( $value / 100 );
	}

	// The odds are negative.
	return 1 + ( 100 / abs( $value ) );
}

/**
 * Convert decimal odds to American odds.
 *
 * @access private
 *
 * @since 0.1.0
 *
 * @param float $value
 *
 * @return float
 */
function maiasknews_decimal_to_american( $value ) {
	// If the decimal odds are 2.00 or greater.
	if ( $value >= 2 ) {
		return ( $value - 1 ) * 100;
	}

	// The decimal odds are less than 2.00.
	return -100 / ( $value - 1 );
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
 * Get a file version based on last modified date.
 *
 * @since 0.4.0
 *
 * @param string $filename The file name. Example: `dapper`.
 * @param string $type     The file type. Example: `css`.
 * @param bool   $debug    Whether to use the debug version.
 *
 * @return string
 */
function maiasknews_get_file_version( $filename, $type, $debug = null ) {
	$version   = MAI_ASKNEWS_VERSION;
	$debug     = is_null( $debug ) ? defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG : false;
	$path      = $debug ? 'src' : 'build';
	$suffix    = $debug ? '' : '.min';
	$filepath  = MAI_ASKNEWS_DIR . "{$path}/{$type}/{$filename}{$suffix}.{$type}";
	$version  .= '.' . date( 'njYHi', filemtime( $filepath ) );

	return $version;
}

/**
 * Get the URL of a file in the plugin.
 * Checks if script debug is enabled.
 *
 * @since 0.4.0
 *
 * @param string $filename The file name. Example: `dapper`.
 * @param string $type     The file type. Example: `css`.
 * @param bool   $debug    Whether to use the debug version.
 *
 * @return string
 */
function maiasknews_get_file_url( $filename, $type, $debug = null ) {
	$debug  = is_null( $debug ) ? defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG : false;
	$path   = $debug ? 'src' : 'build';
	$suffix = $debug ? '' : '.min';

	return MAI_ASKNEWS_URL . "{$path}/{$type}/{$filename}{$suffix}.{$type}";
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