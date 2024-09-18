<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The singular class.
 *
 * @since 0.1.0
 */
class Mai_AskNews_Singular {
	/**
	 * The matchup ID.
	 *
	 * @var int
	 */
	protected $matchup_id;

	/**
	 * The current user.
	 *
	 * @var WP_User|false
	 */
	protected $user;

	/**
	 * The matchup data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * The insight body.
	 *
	 * @var array
	 */
	protected $body;

	/**
	 * The matchup insights.
	 *
	 * @var array
	 */
	protected $insights;

	/**
	 * The current vote team ID.
	 *
	 * @var int
	 */
	protected $vote_id;

	/**
	 * The current vote name.
	 *
	 * @var string
	 */
	protected $vote_name;

	/**
	 * Construct the class.
	 *
	 * @since 0.1.0
	 */
	function __construct() {
		add_action( 'template_redirect', [ $this, 'run' ] );
	}

	/**
	 * Maybe run the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function run() {
		if ( ! is_singular( 'matchup' ) ) {
			return;
		}

		// Set initial data.
		$this->matchup_id = get_the_ID();
		$this->user       = wp_get_current_user();
		$this->data       = maiasknews_get_matchup_data( $this->matchup_id, $this->user );
		$event_uuid       = get_post_meta( $this->matchup_id, 'event_uuid', true );

		// If event uuid.
		if ( $event_uuid ) {
			$this->insights = get_posts(
				[
					'post_type'    => 'insight',
					'orderby'      => 'date',
					'order'        => 'DESC',
					'meta_key'     => 'event_uuid',
					'meta_value'   => $event_uuid,
					'meta_compare' => '=',
					'fields'       => 'ids',
					'numberposts'  => -1,
				]
			);
		}
		// No event uuid, no insights.
		else {
			$this->insights = [];
		}

		// Set body. This needs insights first.
		$this->body = $this->get_body();

		// Add hooks.
		add_filter( 'genesis_markup_entry-title_content', [ $this, 'handle_title' ], 10, 2 );
		add_action( 'mai_after_entry_title',              [ $this, 'handle_descriptive_title' ], 6 );
		add_action( 'mai_after_entry_title',              [ $this, 'do_event_info' ], 8 );
		add_action( 'mai_after_entry_content_inner',      [ $this, 'do_content' ] );
		add_action( 'mai_after_entry_content_inner',      [ $this, 'do_updates' ] );
	}

	/**
	 * Handle the title links and styles.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The default content.
	 * @param array  $args    The markup args.
	 *
	 * @return string
	 */
	function handle_title( $content, $args ) {
		if ( ! isset( $args['params']['args']['context'] ) ||  'single' !== $args['params']['args']['context'] ) {
			return $content;
		}

		// Bail if no league.
		if ( ! $this->data['league'] ) {
			return $content;
		}

		// Get teams.
		$terms = get_the_terms( get_the_ID(), 'league' );
		$terms = $terms ? array_filter( $terms, function( $term ) { return $term->parent > 0; }) : [];
		$teams = $terms ? wp_list_pluck( $terms, 'term_id', 'name' ) : [];

		// Bail if no teams.
		if ( ! $teams ) {
			return $content;
		}

		// Get team data.
		$data = maiasknews_get_teams( $this->data['league'] );

		// Build array from title.
		$array = explode( ' vs ', $content );

		// Loop through teams.
		foreach ( $array as $team ) {
			// Get code and color.
			$city  = isset( $data[ $team ]['city'] ) ? $data[ $team ]['city'] : '';
			$code  = isset( $data[ $team ]['code'] ) ? $data[ $team ]['code'] : '';
			$color = isset( $data[ $team ]['color'] ) ? $data[ $team ]['color'] : '';

			// Skip if no city, code, or color.
			if ( ! ( $city && $code && $color ) || ! isset( $teams[ "$city $team" ] ) ) {
				continue;
			}

			// Replace the team with the code and color.
			$replace = sprintf( '<a class="entry-title-team__link" href="%s" style="--team-color:%s;" data-code="%s"><span class="entry-title-team__name">%s</span></a>', get_term_link( $teams[ "$city $team" ] ), $color, $code, $team );
			$content = str_replace( $team, $replace, $content );

			// Add span to vs.
			$content = str_replace( ' vs ', ' <span class="entry-title__vs">vs</span> ', $content );
		}

		return $content;
	}

	/**
	 * Handle the descriptive title.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function handle_descriptive_title() {
		$title = maiasknews_get_key( 'descriptive_title', $this->body );

		// Bail if no title.
		if ( ! $title ) {
			return;
		}

		printf( '<h2 class="pm-title">%s</h2>', $title );
	}

	/**
	 * Do the event info.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_event_info() {
		// Get the first insight.
		$insight_id = reset( $this->insights );

		// Bail if no insight.
		if ( ! $insight_id ) {
			return;
		}

		// Get count.
		// $count = max( 1, count( $this->insights ) );

		echo maiasknews_get_updated_date();
	}

	/**
	 * Do the content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_content() {
		// Bail if no body.
		if ( ! $this->body ) {
			return;
		}

		// Do the content.
		$this->do_insight( $this->body );
	}

	/**
	 * Do the insights.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_updates() {
		// Get all but the first insight.
		$insight_ids = array_slice( $this->insights, 1 );

		// Bail if no insight.
		if ( ! $insight_ids ) {
			return;
		}

		// Heading.
		printf( '<h2 id="updates">%s</h2>', __( 'Previous Updates', 'mai-asknews' ) );

		// Loop through insights.
		foreach ( $insight_ids as $index => $insight_id ) {
			// Get body, and the post date with the time.
			$data = get_post_meta( $insight_id, 'asknews_body', true );
			$date = get_the_date( 'F j, Y @g:m a', $insight_id );

			printf( '<details id="pm-insight-%s" class="pm-insight">', $index );
				printf( '<summary class="pm-insight__summary">%s %s</summary>', get_the_title( $insight_id ), $date );
				echo '<div class="pm-insight__content entry-content">';
					$this->do_insight( $data );
				echo '</div>';
			echo '</details>';
		}
	}

	/**
	 * Do the insight content.
	 *
	 * @since 0.1.0
	 *
	 * @param array $data The insight data.
	 *
	 * @return void
	 */
	function do_insight( $data ) {
		// Nav links.
		$this->do_jumps( $data );

		// Only admins can vote.
		$this->do_votes( $data );
		$this->do_prediction( $data, ! maiasknews_has_access() );
		$this->do_people( $data );
		$this->do_injuries( $data );
		$this->do_timeline( $data );

		// Do CCA hook.
		do_action( 'pm_cca', $data );

		$this->do_sources( $data );
		$this->do_web( $data );
	}

	/**
	 * Display the vote box.
	 *
	 * @since 0.1.0
	 *
	 * @param array $data The insight data.
	 *
	 * @return void
	 */
	function do_votes( $data ) {
		static $first = true;

		if ( ! $first ) {
			return;
		}

		// Display the vote box.
		echo maiasknews_get_singular_vote_box();

		$first = false;
	}

	/**
	 * Display the prediction info.
	 *
	 * @since 0.1.0
	 *
	 * @param array $data   The asknews data.
	 * @param bool  $hidden Whether the prediction is hidden.
	 *
	 * @return void
	 */
	function do_prediction( $data, $hidden = false ) {
		// Get teams.
		$home = isset( $data['home_team'] ) ? $data['home_team'] : '';
		$away = isset( $data['away_team'] ) ? $data['away_team'] : '';

		// Display the prediction.
		echo '<div id="prediction" class="pm-prediction">';
			// Display the header.
			echo '<div class="pm-prediction__header">';
				// Get image.
				$image = (string) wp_get_attachment_image( 9592, 'medium', false, [ 'class' => 'pm-prediction__img' ] );

				// Display the image. No conditions, always show, even if empty because of CSS grid.
				printf( '<div class="pm-prediction__image">%s</div>', $image );

				// Display the heading and prediction list.
				echo '<div class="pm-prediction__bubble">';
					printf( '<h2>%s</h2>', __( 'My Prediction', 'mai-asknews' ) );
					echo maiasknews_get_prediction_list( $data, $hidden );
				echo '</div>';
			echo '</div>';

			// Get reasoning and build keys.
			$reasoning = sprintf( __( 'Either the %s or the %s are predicted to win this game. You do not have access to our predictions.', 'mai-asknews' ), $home, $away );
			$keys      = [
				'forecast'               => [
					'label'  => __( 'Forecast', 'mai-asknews' ),
					'hidden' => sprintf( '%s %s %s', $home, __( 'or', 'mai-asknews' ), $away ),
				],
				'reasoning'              => [
					'label'  => __( 'Reasoning', 'mai-asknews' ),
					'hidden' => $reasoning,
				],
				// Disabled per Rob's request. "reconciled info is not really meant for anything besides internal thinking of the LLM".
				// 'reconciled_information' => [
				// 	'label'  => __( 'Reconciled Info', 'mai-asknews' ),
				// 	'hidden' => $reasoning,
				// ],
				'unique_prediction'      => [
					'label'  => __( 'Unique Prediction', 'mai-asknews' ),
					'hidden' => $reasoning,
				],
				'unique_information'     => [
					'label'  => __( 'Unique Info', 'mai-asknews' ),
					'hidden' => $reasoning,
				],
			];

			// Display the inner content.
			printf( '<div class="pm-prediction__inner%s">', $hidden ? ' pm-prediction__inner--obfuscated' : '' );
				// Loop through and display the key data.
				foreach ( $keys as $key => $value ) {
					$content = $hidden ? $value['hidden'] : maiasknews_get_key( $key, $data );

					if ( ! $content ) {
						continue;
					}

					$heading = $value['label'] ? sprintf( '<strong>%s:</strong> ', $value['label'] ) : '';

					printf( '<p>%s%s</p>', $heading, $content );
					// printf( '<p>%s</p>', $content );
				}

				// If hidden, show CTA.
				if ( $hidden ) {
					echo '<div class="pm-prediction__cta">';
						echo '<div class="pm-prediction__cta-inner">';
							printf( '<h3>%s</h3>', __( 'Advanced Insights', 'mai-asknews' ) );
							printf( '<p>%s</p>', __( 'Advanced insights and predictions available to members.', 'mai-asknews' ) );
							printf( '<a class="button" href="%s">%s</a>', get_permalink( 41 ), __( 'Get Access', 'mai-asknews' ) );
						echo '</div>';
					echo '</div>';

				}
				// Show stat, fantasy tip, and odds.
				else {
					// Get the interesting stat.
					$stat = $hidden ? $reasoning : maiasknews_get_key( 'interesting_statistic', $data );

					// Display the interesting stat.
					if ( $stat ) {
						$heading = sprintf( '<strong>%s:</strong> ', __( 'Interesting Statistic', 'mai-asknews' ) );

						printf( '<p>%s%s</p>', $heading, $stat );
					}

					// Get the fantasy tip.
					$fantasy = $hidden ? $reasoning : maiasknews_get_key( 'fantasy_tip', $data );

					// Display the fantasy tip.
					if ( $fantasy ) {
						$heading = sprintf( '<strong>%s!</strong> ', __( 'Fantasy Tip', 'mai-asknews' ) );

						printf( '<div class="pm-prediction__fantasy">%s%s</div>', $heading, wpautop( $fantasy, false ) );
					}

					// Get odds table.
					$odds = maiasknews_get_odds_table( $data, $hidden );

					// Display the odds.
					if ( $odds ) {
						echo $odds;
					}
				}

			echo '</div>';
		echo '</div>';
	}

	/**
	 * Display the general content.
	 *
	 * @since 0.1.0
	 *
	 * @param array $data The insight data.
	 *
	 * @return void
	 */
	function do_jumps( $data ) {
		// If odds data.
		$odds = maiasknews_has_access() ? maiasknews_get_key( 'odds_info', $data ) : false;

		// Display the nav.
		echo '<ul class="pm-jumps">';
			if ( $odds ) {
				printf( '<li class="pm-jump"><a class="pm-jump__link" href="#odds">%s</a></li>', __( 'Odds', 'mai-asknews' ) );
			}
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#people">%s</a></li>', __( 'People', 'mai-asknews' ) );
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#timeline">%s</a></li>', __( 'Timeline', 'mai-asknews' ) );
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#sources">%s</a></li>', __( 'Latest News', 'mai-asknews' ) );

			// TODO: Better name for external sources and sites talking about this.
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#web">%s</a></li>', __( 'Mentions', 'mai-asknews' ) );

			// If comments open.
			if ( comments_open() ) {
				printf( '<li class="pm-jump"><a class="pm-jump__link" href="#comments">%s</a></li>', __( 'Comments', 'mai-asknews' ) );
			}

			// if ( $this->insights ) {
			// 	printf( '<li class="pm-jump"><a class="pm-jump__link" href="#updates">%s</a></li>', __( 'Updates', 'mai-asknews' ) );
			// }
		echo '</ul>';
	}

	/**
	 * Display the people.
	 *
	 * @since 0.1.0
	 *
	 * @param array $data The insight data.
	 *
	 * @return void
	 */
	function do_people( $data ) {
		$people = maiasknews_get_key( 'key_people', $data );

		if ( ! $people ) {
			return;
		}

		// Start markup.
		printf( '<h2 id="people" class="is-style-heading">%s</h2>', __( 'Key People', 'mai-asknews' ) );
		printf( '<p>%s</p>', __( 'Key people highlighted in this matchup. Click to follow.', 'mai-asknews' ) );

		echo '<ul class="pm-people">';

		// Loop through people.
		foreach ( $people as $person ) {
			// Early versions were a string of the person's name.
			if ( is_string( $person ) ) {
				printf( '<li class="pm-person">%s</li>', $person );
			}
			// We should be getting dict/array now.
			else {
				// Get the term/name.
				$name = isset( $person['name'] ) ? $person['name'] : '';
				$term = $name ? get_term_by( 'name', $name, 'matchup_tag' ) : '';
				$name = $term ? sprintf( '<strong><a class="pm-person__link" href="%s">%s</a></strong>', get_term_link( $term ), $term->name ) : $name;

				// Build the info.
				$info = [
					$name,
					isset( $person['role'] ) ? $person['role'] : '',
				];

				// Remove empty items.
				$info = array_filter( $info );

				echo '<li>';
					echo implode( ': ', $info );
				echo '</li>';
			}
		}

		echo '</ul>';
	}

	function do_injuries( $data ) {
		$injuries = maiasknews_get_key( 'relevant_injuries', $data );

		// Bail if no injuries.
		if ( ! $injuries ) {
			return;
		}

		// Start lis.
		$lis = [];

		// Loop through injuries.
		foreach ( $injuries as $index => $injury ) {
			$name   = maiasknews_get_key( 'name', $injury );
			$team   = maiasknews_get_key( 'team', $injury );
			$status = maiasknews_get_key( 'status', $injury );

			// Skip if no data.
			if ( ! ( $name && $team && $status ) ) {
				continue;
			}

			$lis[] = sprintf('<li><strong>%s</strong> (%s): %s</li>', $name, $team, $status );
		}

		// Bail if no lis.
		if ( ! $lis ) {
			return;
		}

		printf( '<h2 id="injuries" class="is-style-heading">%s</h2>', __( 'Injuries', 'mai-asknews' ) );
		printf( '<p>%s</p>', __( 'Injuries that may affect the outcome of this matchup.', 'mai-asknews' ) );

		echo '<ul class="pm-injuries">';
			foreach ( $lis as $li ) {
				echo $li;
			}
		echo '</ul>';
	}

	/**
	 * Display the timeline.
	 *
	 * @since 0.1.0
	 *
	 * @param array $data The insight data.
	 *
	 * @return void
	 */
	function do_timeline( $data ) {
		$timeline = maiasknews_get_key( 'timeline', $data );

		if ( ! $timeline ) {
			return;
		}

		printf( '<h2 id="timeline" class="is-style-heading">%s</h2>', __( 'Timeline', 'mai-asknews' ) );
		printf( '<p>%s</p>', __( 'Timeline of relevant events and news articles.', 'mai-asknews' ) );

		echo '<ul>';

		foreach ( $timeline as $event ) {
			printf( '<li>%s</li>', $event );
		}

		echo '</ul>';
	}

	/**
	 * Display the sources.
	 *
	 * @since 0.1.0
	 *
	 * @param array $data The insight data.
	 *
	 * @return void
	 */
	function do_sources( $data ) {
		$sources = maiasknews_get_key( 'sources', $data );

		if ( ! $sources ) {
			return;
		}

		// printf( '<h2 id="sources" class="is-style-heading">%s <span class="by-asknews">%s</span></h2>', __( 'Latest News Sources', 'mai-asknews' ), __( 'by Asknews.app', 'mai-asknews' ) );
		printf( '<h2 id="sources" class="is-style-heading">%s</h2>', __( 'Latest News by AskNews.app', 'mai-asknews' ) );
		// printf( '<p>%s</p>', __( 'We summarized the best articles for you, powered by <a href="https://asknews.app/en" target="_blank" rel="nofollow">AskNews.app</a>.', 'mai-asknews' ) );

		echo '<ul class="pm-sources">';
			// Loop through sources.
			foreach ( $sources as $source ) {
				$url        = maiasknews_get_key( 'article_url_final', $source );
				$url        = $url ?: maiasknews_get_key( 'article_url', $source );
				$host       = maiasknews_get_key( 'domain_url', $source );
				$name       = maiasknews_get_key( 'source_id', $source );
				$parsed_url = wp_parse_url( $url );
				$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				$host       = $name ?: $parsed_url['host'];
				$host       = str_replace( 'www.', '', $host );
				$host       = $host ? 'mlb.com' === strtolower( $host ) ? 'MLB.com' : $host : '';
				$host       = $host ? sprintf( '<a class="entry-title-link" href="%s" target="_blank" rel="nofollow">%s</a>', $url, $host ) : '';
				$date       = maiasknews_get_key( 'pub_date', $source );
				$date       = $date ? wp_date( get_option( 'date_format' ), strtotime( $date ) ) : '';
				$title      = maiasknews_get_key( 'eng_title', $source );
				$image_id   = maiasknews_get_key( 'image_id', $source );
				$image_id   = $image_id ?: 4078;
				$image_url  = $image_id && ! is_wp_error( $image_id ) ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
				// $image_url  = maiasknews_get_key( 'image_url', $source );
				$summary    = maiasknews_get_key( 'summary', $source );
				$meta       = [ trim( $date ), trim( $title ) ];
				$meta       = implode( ' &ndash; ', array_filter( $meta ) );
				$entities   = maiasknews_get_key( 'entities', $source );
				$persons    = maiasknews_get_key( 'Person', (array) $entities );

				echo '<li class="pm-source">';
					// Image.
					echo '<figure class="pm-source__image">';
						if ( $image_url ) {
							printf( '<img class="pm-source__image-bg" src="%s" alt="%s" />', $image_url, $title );
							printf( '<img class="pm-source__image-img" src="%s" alt="%s" />', $image_url, $title );
						}
					echo '</figure>';

					// Title.
					echo '<h3 class="pm-source__title entry-title">';
						echo $host;
					echo '</h3>';

					// Meta.
					echo '<p class="pm-source__meta">';
						echo $meta;
					echo '</p>';

					// Summary.
					echo '<p class="pm-source__summary">';
						echo $summary;
					echo '</p>';

					// People/Entities.
					if ( $persons ) {
						echo '<ul class="pm-entities">';
						foreach ( $persons as $person ) {
							printf( '<li class="pm-entity">%s</li>', $person );
						}
						echo '</ul>';
					}
				echo '</li>';

				// Add hook.
				do_action( 'pm_after_source', $source );
			}

		echo '</ul>';
	}

	/**
	 * Display the web search results.
	 *
	 * @since 0.1.0
	 *
	 * @param array $data The insight data.
	 *
	 * @return void
	 */
	function do_web( $data ) {
		$web = maiasknews_get_key( 'web_search_results', $data );
		$web = $web ?: maiasknews_get_key( 'web_seach_results', $data ); // Temp fix for mispelled.

		if ( ! $web ) {
			return;
		}

		// Remove reCAPTCHA and Unusual Traffic Detection.
		foreach ( $web as $index => $item ) {
			$title = maiasknews_get_key( 'title', $item );

			if ( in_array( $title, [ '404', 'reCAPTCHA', 'Unusual Traffic Detection' ] ) ) {
				unset( $web[ $index ] );
			}
		}

		if ( ! $web ) {
			return;
		}

		// Reindex.
		$web = array_values( $web );

		// Possible TODOs:
		// Order by date. Only show the last 3 days before the most recent insight date.
		// For subscription, show the last 2 weeks?

		printf( '<h2 id="web">%s</h2>', __( 'Around the Web', 'mai-asknews' ) );
		echo '<ul class="pm-results">';

		// Loop through web results.
		foreach ( $web as $item ) {
			$url        = maiasknews_get_key( 'url', $item );
			$name       = maiasknews_get_key( 'source', $item );
			$name       = 'unknown' === strtolower( $name ) ? '' : $name;
			$parsed_url = wp_parse_url( $url );
			$host       = $name ?: $parsed_url['host'];
			$host       = str_replace( 'www.', '', $host );
			$host       = $host ? 'mlb.com' === strtolower( $host ) ? 'MLB.com' : $host : '';
			$host       = $host ? sprintf( '<a class="entry-title-link" href="%s" target="_blank" rel="nofollow">%s</a>', $url, $host ) : '';
			$title      = maiasknews_get_key( 'title', $item );
			$date       = maiasknews_get_key( 'published', $item );
			$date       = $date ? wp_date( get_option( 'date_format' ), strtotime( $date ) ) : '';
			$meta       = [ trim( $date ), trim( $title ) ];
			$meta       = implode( ' &ndash; ', array_filter( $meta ) );
			$points     = maiasknews_get_key( 'key_points', $item );

			echo '<li class="pm-result">';
				echo '<h3 class="entry-title pm-result__title">';
					echo $host;
				echo '</h3>';
				echo '<div class="pm-result__meta">';
					echo $meta;
				echo '</div>';
				echo '<ul>';
				foreach ( $points as $point ) {
					echo '<li>';
						echo $point;
					echo '</li>';
				}
				echo '</ul>';
			echo '</li>';

			// Add hook.
			do_action( 'pm_after_web_result', $item );
		}

		echo '</ul>';
	}

	/**
	 * Get the body.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	function get_body() {
		static $cache = null;

		if ( ! is_null( $cache ) ) {
			return $cache;
		}

		// Get the first insight.
		$cache      = [];
		$insight_id = reset( $this->insights );

		// Bail if no insight.
		if ( ! $insight_id ) {
			return $cache;
		}

		// Get the body.
		$cache = (array) get_post_meta( $insight_id, 'asknews_body', true );

		return $cache;
	}
}
