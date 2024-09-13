<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * If is a matchup archive page.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function maiasknews_is_archive() {
	return is_post_type_archive( 'matchup' )
		|| is_tax( 'league' )
		|| is_tax( 'season' )
		|| is_tax( 'matchup_tag' )
		|| is_author()
		|| is_search();
}