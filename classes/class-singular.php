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
	 * The matchup insights.
	 *
	 * @var array
	 */
	protected $insights;

	/**
	 * The current user.
	 *
	 * @var WP_User|false
	 */
	protected $user;

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
		$this->vote_id    = null;
		$this->vote_name  = '';

		// If user is logged in.
		if ( $this->user ) {
			// Check if the user has already voted.
			$comments = get_comments(
				[
					'comment_type' => 'pm_vote',
					'post_id'      => $this->matchup_id,
					'user_id'      => $this->user->ID,
				]
			);

			// If user has voted.
			if ( $comments ) {
				$existing        = reset( $comments );
				$this->vote_id   = $existing ? $existing->comment_karma : $this->vote_id;
				$term            = $this->vote_id ? get_term( $this->vote_id, 'league' ) : null;
				$this->vote_name = $term ? $term->name : '';
			}

			// Add vote listener.
			$this->vote_listener();
		}

		// Get insights.
		$event_uuid = get_post_meta( $this->matchup_id, 'event_uuid', true );

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

		// Add hooks.
		add_filter( 'genesis_markup_entry-title_content', [ $this, 'handle_title' ], 10, 2 );
		add_action( 'mai_after_entry_title',              [ $this, 'do_event_info' ], 8 );
		add_action( 'mai_after_entry_content_inner',      [ $this, 'do_content' ] );
		add_action( 'mai_after_entry_content_inner',      [ $this, 'do_updates' ] );
	}

	/**
	 * Add vote listener.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function vote_listener() {
		// Bail if not a POST request.
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		// Get team.
		$team = isset( $_POST['team'] ) ? sanitize_text_field( $_POST['team'] ) : '';

		// Bail if no team.
		if ( ! $team ) {
			return;
		}

		// Build comment data.
		$update  = false;
		$term    = get_term_by( 'name', $team, 'league' );
		$term_id = $term ? $term->term_id : null;
		$args    = [
			'comment_type'         => 'pm_vote',
			'comment_post_ID'      => $this->matchup_id,
			'comment_approved'     => 1,
			'comment_content'      => $team,
			'comment_karma'        => $term_id,
			'user_id'              => $this->user->ID,
			'comment_author'       => $this->user->user_login,
			'comment_author_email' => $this->user->user_email,
			'comment_author_url'   => $this->user->user_url,
		];

		// If already voted.
		if ( $this->vote_name ) {
			// If the same team.
			if ( $team === $this->vote_name ) {
				// Display message.
				add_action( 'genesis_before_loop', function() {
					printf( '<p class="pm-notice error">%s %s.</p>', __( 'Your vote has not been saved. You have already voted for', 'mai-asknews' ), $this->vote_name );
				});
				return;
			}
			// Delete the old votes.
			else {
				// Delete the old vote.
				$comments = get_comments(
					[
						'comment_type' => 'pm_vote',
						'post_id'      => $this->matchup_id,
						'user_id'      => $this->user->ID,
					]
				);

				// If comments.
				if ( $comments ) {
					foreach ( $comments as $index => $comment ) {
						// If first vote.
						if ( 0 === $index ) {
							// Update the team and term ID.
							$update                  = true;
							$args['comment_ID']      = $comment->comment_ID;
							$args['comment_content'] = $team;
							$args['comment_karma']   = $term_id;
						}
						// We shouldn't have more than 1 vote, so delete it.
						else {
							wp_delete_comment( $comment->comment_ID, true );
						}
					}
				}
			}
		}

		// If updating.
		if ( $update ) {
			// Update the comment.
			wp_update_comment( $args );
		}
		// New vote.
		else {
			// Insert the comment.
			wp_insert_comment( $args );
		}

		// Refresh to show the new vote.
		wp_safe_redirect( get_permalink() );
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

		// Get leagues.
		$leagues = get_the_terms( get_the_ID(), 'league' );

		// Bail if no leagues.
		if ( ! $leagues ) {
			return $content;
		}

		// Filter terms that don't have a parent.
		$sports = array_filter( $leagues, function( $league ) {
			return 0 === $league->parent;
		});

		// Get the first sport. There should only be one anyway.
		$sport = reset( $sports );
		$sport = $sport ? $sport->name : null;

		// Bail if no sport.
		if ( ! $sport ) {
			return $content;
		}

		// Get team data.
		$data = maiasknews_get_teams( $sport );

		// Build teams array.
		$teams = array_filter( $leagues, function( $league ) { return $league->parent > 0; });
		$teams = wp_list_pluck( $teams, 'term_id', 'name' );

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
		// Get the first insight.
		$insight_id = reset( $this->insights );

		// Bail if no insight.
		if ( ! $insight_id ) {
			return;
		}

		// Get the body.
		$body = get_post_meta( $insight_id, 'asknews_body', true );

		// Bail if no body.
		if ( ! $body ) {
			return;
		}

		// Do the content.
		$this->do_insight( $body );
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
			$body = get_post_meta( $insight_id, 'asknews_body', true );
			$date = get_the_date( 'F j, Y @g:m a', $insight_id );

			printf( '<details id="pm-insight-%s" class="pm-insight">', $index );
				printf( '<summary class="pm-insight__summary">%s %s</summary>', get_the_title( $insight_id ), $date );
				echo '<div class="pm-insight__content entry-content">';
					$this->do_insight( $body );
				echo '</div>';
			echo '</details>';
		}
	}

	/**
	 * Do the insight content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_insight( $body ) {
		// Only admins can vote.
		if ( current_user_can( 'manage_options' ) ) {
			$this->do_votes( $body );
		}

		// If they have access.
		if ( maiasknews_has_access() ) {
			$this->do_prediction( $body );
		}

		$this->do_main( $body );
		$this->do_people( $body );
		$this->do_timeline( $body );

		// Do CCA hook.
		do_action( 'pm_cca', $body );

		$this->do_sources( $body );
		$this->do_web( $body );
	}

	/**
	 * Display the vote box.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_votes( $data ) {
		static $first = true;

		if ( ! $first ) {
			return;
		}

		$has_access = current_user_can( 'manage_options' );
		$home       = isset( $data['home_team_name'] ) ? $data['home_team_name'] : '';
		$away       = isset( $data['away_team_name'] ) ? $data['away_team_name'] : '';

		// If home.
		if ( ! $home ) {
			// Old data didn't have name separate, so parse it from city team string.
			$home = isset( $data['home_team'] ) ? $data['home_team'] : '';
			$home = $home ? explode( ' ', $home ) : '';
			$home = $home ? end( $home ) : '';
		}

		// If home.
		if ( ! $away ) {
			// Old data didn't have name separate, so parse it from city team string.
			$away = isset( $data['away_team'] ) ? $data['away_team'] : '';
			$away = $away ? explode( ' ', $away ) : '';
			$away = $away ? end( $away ) : '';
		}

		// Bail if no teams.
		if ( ! ( $home && $away ) ) {
			return;
		}

		echo '<div id="vote" class="pm-vote">';
			$text = $this->vote_id ? __( 'Change Your Vote', 'mai-asknews' ) : __( 'Cast Your Vote!', 'mai-asknews' );
			printf( '<h2>%s</h2>', $text );

			if ( $has_access ) {
				if ( $this->vote_name ) {
					printf( '<p>%s %s. %s.</p>', __( 'You have already voted for', 'mai-asknews' ), $this->vote_name, __( 'Change your vote below.', 'mai-asknews' ) );
				} else {
					printf( '<p>%s</p>', __( 'Compete with others and beat the ProMatchups Robot.<br>Who do you think will win?', 'mai-asknews' ) );
				}
				echo '<form class="pm-vote__form" method="post" action="">';
					printf( '<button class="button%s" type="submit" name="team" value="%s">%s</button>', $home === $this->vote_name ? ' selected' : '', $home, $home );
					printf( '<button class="button%s" type="submit" name="team" value="%s">%s</button>', $away === $this->vote_name ? ' selected' : '', $away, $away );
				echo '</form>';
			} else {
				printf( '<p>%s</p>', __( 'You must be logged in to vote.', 'mai-asknews' ) );
			}
		echo '</div>';

		$first = false;
	}

	/**
	 * Display the prediction info.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_prediction( $data ) {
		// Display the prediction.
		echo '<div id="prediction" class="pm-prediction">';
			printf( '<h2>%s</h2>', __( 'Our Prediction', 'mai-asknews' ) );
			echo maiasknews_get_prediction_list( $data );

			$keys = [
				'forecast'               => __( 'Forecast', 'mai-asknews' ),
				'reasoning'              => __( 'Reasoning', 'mai-asknews' ),
				'reconciled_information' => '',
				'unique_information'     => '',
			];

			foreach ( $keys as $key => $value ) {
				$content = maiasknews_get_key( $key, $data );

				if ( ! $content ) {
					continue;
				}

				$heading = $value ? sprintf( '<strong>%s:</strong><br>', $value ) : '';

				printf( '<p>%s%s</p>', $heading, $content );
				// printf( '<p>%s</p>', $content );
			}

		echo '</div>';
	}

	/**
	 * Display the general content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_main( $data ) {
		// Get odds table.
		$odds = maiasknews_get_odds_table( $data );

		// Display the nav.
		echo '<ul class="pm-jumps">';
			if ( $odds ) {
				printf( '<li class="pm-jump"><a class="pm-jump__link" href="#odds">%s</a></li>', __( 'Odds', 'mai-asknews' ) );
			}
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#people">%s</a></li>', __( 'People', 'mai-asknews' ) );
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#timeline">%s</a></li>', __( 'Timeline', 'mai-asknews' ) );
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#web">%s</a></li>', __( 'Web', 'mai-asknews' ) );
			printf( '<li class="pm-jump"><a class="pm-jump__link" href="#sources">%s</a></li>', __( 'Sources', 'mai-asknews' ) );

			if ( $this->insights ) {
				printf( '<li class="pm-jump"><a class="pm-jump__link" href="#updates">%s</a></li>', __( 'Updates', 'mai-asknews' ) );
			}
		echo '</ul>';

		if ( $odds ) {
			printf( '<p id="odds" class="has-xs-margin-bottom"><strong>%s:</strong></p>', __( 'Odds', 'mai-asknews' ) );
			echo $odds;
		}
	}

	/**
	 * Display the people.
	 *
	 * @since 0.1.0
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
		echo '<ul class="pm-people">';

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

	/**
	 * Display the timeline.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function do_timeline( $data ) {
		$timeline = maiasknews_get_key( 'timeline', $data );

		if ( ! $timeline ) {
			return;
		}

		printf( '<h2 id="timeline" class="is-style-heading">%s</h2>', __( 'Timeline', 'mai-asknews' ) );

		// TODO: Add short description here.

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
	 * @return void
	 */
	function do_sources( $data ) {
		$sources = maiasknews_get_key( 'sources', $data );

		if ( ! $sources ) {
			return;
		}

		printf( '<h2 id="sources">%s</h2>', __( 'Latest News Sources', 'mai-asknews' ) );
		echo '<ul class="pm-sources">';
			// Loop through sources.
			foreach ( $sources as $source ) {
				$url        = maiasknews_get_key( 'article_url', $source );
				$host       = maiasknews_get_key( 'domain_url', $source );
				$name       = maiasknews_get_key( 'source_id', $source );
				$parsed_url = wp_parse_url( $url );
				$base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				$host       = $name ?: $parsed_url['host'];
				$host       = str_replace( 'www.', '', $host );
				$host       = $host ? 'mlb.com' === strtolower( $host ) ? 'MLB.com' : $host : '';
				$host       = $host ? sprintf( '<a class="entry-title-link" href="%s" target="_blank">%s</a>', $url, $host ) : '';
				$date       = maiasknews_get_key( 'pub_date', $source );
				$date       = $date ? wp_date( get_option( 'date_format' ), strtotime( $date ) ) : '';
				$title      = maiasknews_get_key( 'eng_title', $source );
				$image_url  = maiasknews_get_key( 'image_url', $source );
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
	 * @return void
	 */
	function do_web( $data ) {
		$web = maiasknews_get_key( 'web_search_results', $data );

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
			$host       = $host ? sprintf( '<a class="entry-title-link" href="%s" target="_blank">%s</a>', $url, $host ) : '';
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
}