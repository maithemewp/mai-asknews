<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

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
	$confidence     = maiasknews_get_key( 'confidence', $body );
	$confidence     = $confidence ? maiasknews_format_confidence( $confidence ) : '';
	// $llm_confidence = maiasknews_get_key( 'llm_confidence', $body );

	// Get list body.
	$table = [
		__( 'Prediction', 'mai-asknews' )     => $choice,
		__( 'Probability', 'mai-asknews' )    => $probability,
		__( 'Confidence', 'mai-asknews' )     => $confidence,
		// __( 'LLM Confidence', 'mai-asknews' ) => $llm_confidence,
		__( 'Likelihood', 'mai-asknews' )     => $likelihood,
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
 * Get formatted confidence.
 *
 * @param float|mixed $confidence
 *
 * @return string
 */
function maiasknews_format_confidence( $confidence ) {
	return $confidence ? round( (float) $confidence * 100 ) . '%' : '';
}