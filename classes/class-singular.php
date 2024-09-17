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
	 * The insight body.
	 *
	 * @var array
	 */
	protected $body;

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
		$this->vote_name  = '';

		// If user is logged in.
		if ( $this->user ) {
			// Check if the user has already voted.
			$comments = get_comments(
				[
					'comment_type' => 'pm_vote',
					'post_id'      => $this->matchup_id,
					'user_id'      => $this->user->ID,
					'approve'      => 1,
				]
			);

			// If user has voted.
			if ( $comments ) {
				$existing        = reset( $comments );
				$this->vote_name = trim( $existing->comment_content );
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

		// Set the body.
		$this->body = $this->get_body();

		// Add hooks.
		add_filter( 'genesis_markup_entry-title_content', [ $this, 'handle_title' ], 10, 2 );
		add_action( 'mai_after_entry_title',              [ $this, 'handle_descriptive_title' ], 6 );
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

		// Run listener and get response.
		$listener = new Mai_AskNews_Vote_Listener( $this->matchup_id, $team, $this->user );
		$response = $listener->get_response();

		// Refresh to show the new vote.
		wp_safe_redirect( get_permalink() . '#vote' );
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

		// Get start timestamp.
		$timestamp = get_post_meta( $this->matchup_id, 'event_date', true );

		// Bail if no timestamp.
		// We can't vote if we don't know when the game starts.
		if ( ! $timestamp ) {
			return;
		}

		// Bail if no timestamp or current timestamp is greater than the start timestamp.
		// if ( ! $timestamp || time() > $timestamp ) {
		// 	return;
		// }

		$has_access   = is_user_logged_in();
		$home_full    = isset( $data['home_team'] ) ? $data['home_team'] : '';
		$away_full    = isset( $data['away_team'] ) ? $data['away_team'] : '';
		$home_name    = isset( $data['home_team_name'] ) ? $data['home_team_name'] : $home_full;
		$away_name    = isset( $data['away_team_name'] ) ? $data['away_team_name'] : $away_full;
		$started      = time() > $timestamp;
		$choice       = maiasknews_get_key( 'choice', $data );
		$outcome      = $started ? (array) get_post_meta( $this->matchup_id, 'asknews_outcome', true ) : [];
		$winner_team  = isset( $outcome['winner']['team'] ) ? $outcome['winner']['team'] : '';
		$winner_score = isset( $outcome['winner']['score'] ) ? $outcome['winner']['score'] : '';
		$loser_team   = isset( $outcome['loser']['team'] ) ? $outcome['loser']['team'] : '';
		$loser_score  = isset( $outcome['loser']['score'] ) ? $outcome['loser']['score'] : '';
		$league       = maiasknews_get_page_league();

		// Bail if no teams.
		if ( ! ( $home_full && $away_full ) ) {
			return;
		}

		// Get user avatar.
		$avatar = get_avatar( get_current_user_id(), 128 );

		// Start vote box.
		echo '<div id="vote" class="pm-vote">';
			// // Display the avatar.
			// printf( '<div class="pm-vote__avatar">%s</div>', $avatar );

			// If the game has started.
			if ( $started ) {
				// If we have an outcome.
				if ( $outcome ) {
					// $heading = '';
					$heading = sprintf( '<h2>%s</h2>', __( 'Game Results', 'mai-asknews' ) );
					$desc    = [];
					$desc[]  = __( 'The game has ended.', 'mai-asknews' );

					// If we have both scores.
					if ( $winner_score && $loser_score ) {
						$scores = " {$winner_score} - {$loser_score}";
					} else {
						$scores = '';
					}

					// If we have a winner and loser.
					if ( $winner_team && $loser_team ) {
						$desc[] = sprintf( __( 'The %s defeated the %s%s.', 'mai-asknews' ), $winner_team, $loser_team, $scores );
					} else {
						$desc[] = __( 'Sorry, we don\'t have scores at this time.', 'mai-asknews' );
					}

					// To string.
					$desc = $desc ? sprintf( '<p>%s</p>', implode( ' ', $desc ) ) : '';
				}
				// No outcome.
				else {
					$heading = sprintf( '<h2>%s</h2>', __( 'Game Info', 'mai-asknews' ) );
					$desc    = sprintf( '<p>%s</p>', __( 'Voting is closed after the game starts. Once we analyze the results and calculate your points we\'ll update here.', 'mai-asknews' ) );
				}
			}
			// If they have already voted.
			elseif ( $this->vote_name ) {
				$heading = sprintf( '<h2>%s</h2>', __( 'Make Your Pick', 'mai-asknews' ) );
				$desc    = sprintf( '<p>%s</p>', sprintf( __( 'You have already voted for the %s; leave it as is or change your vote before game time.', 'mai-asknews' ), $this->vote_name ) );
			}
			// Fallback for voting.
			else {
				$heading = sprintf( '<h2>%s</h2>', __( 'Make Your Pick', 'mai-asknews' ) );
				$desc    = sprintf( '<p>%s</p>', __( 'Compete with others to beat the SportsDesk Bot.<br>Who do you think will win?', 'mai-asknews' ) );
			}

			// If game started.
			if ( $started ) {
				// Display the title.
				echo $heading;

				// If we have an outcome.
				if ( $outcome ) {
					// $icon_win        = '<svg class="pm-outcome__icon winner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M552 64H448V24c0-13.3-10.7-24-24-24H152c-13.3 0-24 10.7-24 24v40H24C10.7 64 0 74.7 0 88v56c0 35.7 22.5 72.4 61.9 100.7 31.5 22.7 69.8 37.1 110 41.7C203.3 338.5 240 360 240 360v72h-48c-35.3 0-64 20.7-64 56v12c0 6.6 5.4 12 12 12h296c6.6 0 12-5.4 12-12v-12c0-35.3-28.7-56-64-56h-48v-72s36.7-21.5 68.1-73.6c40.3-4.6 78.6-19 110-41.7 39.3-28.3 61.9-65 61.9-100.7V88c0-13.3-10.7-24-24-24zM99.3 192.8C74.9 175.2 64 155.6 64 144v-16h64.2c1 32.6 5.8 61.2 12.8 86.2-15.1-5.2-29.2-12.4-41.7-21.4zM512 144c0 16.1-17.7 36.1-35.3 48.8-12.5 9-26.7 16.2-41.8 21.4 7-25 11.8-53.6 12.8-86.2H512v16z"/></svg>';
					// $icon_win        = '<svg class="pm-outcome__icon winner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 8C119.033 8 8 119.033 8 256s111.033 248 248 248 248-111.033 248-248S392.967 8 256 8zm0 48c110.532 0 200 89.451 200 200 0 110.532-89.451 200-200 200-110.532 0-200-89.451-200-200 0-110.532 89.451-200 200-200m140.204 130.267l-22.536-22.718c-4.667-4.705-12.265-4.736-16.97-.068L215.346 303.697l-59.792-60.277c-4.667-4.705-12.265-4.736-16.97-.069l-22.719 22.536c-4.705 4.667-4.736 12.265-.068 16.971l90.781 91.516c4.667 4.705 12.265 4.736 16.97.068l172.589-171.204c4.704-4.668 4.734-12.266.067-16.971z"/></svg>';
					// $icon_win        = '<svg class="pm-outcome__icon winner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M104 224H24c-13.3 0-24 10.7-24 24v240c0 13.3 10.7 24 24 24h80c13.3 0 24-10.7 24-24V248c0-13.3-10.7-24-24-24zM64 472c-13.3 0-24-10.7-24-24s10.7-24 24-24 24 10.7 24 24-10.7 24-24 24zM384 81.5c0 42.4-26 66.2-33.3 94.5h101.7c33.4 0 59.4 27.7 59.6 58.1 .1 17.9-7.5 37.2-19.4 49.2l-.1 .1c9.8 23.3 8.2 56-9.3 79.5 8.7 25.9-.1 57.7-16.4 74.8 4.3 17.6 2.2 32.6-6.1 44.6C440.2 511.6 389.6 512 346.8 512l-2.8 0c-48.3 0-87.8-17.6-119.6-31.7-16-7.1-36.8-15.9-52.7-16.2-6.5-.1-11.8-5.5-11.8-12v-213.8c0-3.2 1.3-6.3 3.6-8.5 39.6-39.1 56.6-80.6 89.1-113.1 14.8-14.8 20.2-37.2 25.4-58.9C282.5 39.3 291.8 0 312 0c24 0 72 8 72 81.5z"/></svg>';
					// $icon_win        = '<svg class="pm-outcome__icon winner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm0 48c110.5 0 200 89.5 200 200 0 110.5-89.5 200-200 200-110.5 0-200-89.5-200-200 0-110.5 89.5-200 200-200m140.2 130.3l-22.5-22.7c-4.7-4.7-12.3-4.7-17-.1L215.3 303.7l-59.8-60.3c-4.7-4.7-12.3-4.7-17-.1l-22.7 22.5c-4.7 4.7-4.7 12.3-.1 17l90.8 91.5c4.7 4.7 12.3 4.7 17 .1l172.6-171.2c4.7-4.7 4.7-12.3 .1-17z"/></svg>';
					$icon_win        = '<svg class="pm-outcome__icon winner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M504 256c0 137-111 248-248 248S8 393 8 256 119 8 256 8s248 111 248 248zM227.3 387.3l184-184c6.2-6.2 6.2-16.4 0-22.6l-22.6-22.6c-6.2-6.2-16.4-6.2-22.6 0L216 308.1l-70.1-70.1c-6.2-6.2-16.4-6.2-22.6 0l-22.6 22.6c-6.2 6.2-6.2 16.4 0 22.6l104 104c6.2 6.2 16.4 6.2 22.6 0z"/></svg>';
					// $icon_lose       = '<svg class="pm-outcome__icon loser" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 496 512"><path d="M248 8C111 8 0 119 0 256s111 248 248 248 248-111 248-248S385 8 248 8zm0 448c-110.3 0-200-89.7-200-200S137.7 56 248 56s200 89.7 200 200-89.7 200-200 200zm8-152c-13.2 0-24 10.8-24 24s10.8 24 24 24c23.8 0 46.3 10.5 61.6 28.8 8.1 9.8 23.2 11.9 33.8 3.1 10.2-8.5 11.6-23.6 3.1-33.8C330 320.8 294.1 304 256 304zm-88-64c17.7 0 32-14.3 32-32s-14.3-32-32-32-32 14.3-32 32 14.3 32 32 32zm160-64c-17.7 0-32 14.3-32 32s14.3 32 32 32 32-14.3 32-32-14.3-32-32-32zm-165.6 98.8C151 290.1 126 325.4 126 342.9c0 22.7 18.8 41.1 42 41.1s42-18.4 42-41.1c0-17.5-25-52.8-36.4-68.1-2.8-3.7-8.4-3.7-11.2 0z"/></svg>';
					// $icon_lose       = '<svg class="pm-outcome__icon loser" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M0 56v240c0 13.3 10.7 24 24 24h80c13.3 0 24-10.7 24-24V56c0-13.3-10.7-24-24-24H24C10.7 32 0 42.7 0 56zm40 200c0-13.3 10.7-24 24-24s24 10.7 24 24-10.7 24-24 24-24-10.7-24-24zm272 256c-20.2 0-29.5-39.3-33.9-57.8-5.2-21.7-10.6-44.1-25.4-58.9-32.5-32.5-49.5-74-89.1-113.1a12 12 0 0 1 -3.6-8.5V59.9c0-6.5 5.2-11.9 11.8-12 15.8-.3 36.7-9.1 52.7-16.2C256.2 17.6 295.7 0 344 0h2.8c42.8 0 93.4 .4 113.8 29.7 8.4 12.1 10.4 27 6.1 44.6 16.3 17.1 25.1 48.9 16.4 74.8 17.5 23.4 19.1 56.1 9.3 79.5l.1 .1c11.9 11.9 19.5 31.3 19.4 49.2-.2 30.4-26.2 58.1-59.6 58.1H350.7C358 364.3 384 388.1 384 430.5 384 504 336 512 312 512z"/></svg>';
					// $icon_lose       = '<svg class="pm-outcome__icon loser" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm0 448c-110.5 0-200-89.5-200-200S145.5 56 256 56s200 89.5 200 200-89.5 200-200 200zm101.8-262.2L295.6 256l62.2 62.2c4.7 4.7 4.7 12.3 0 17l-22.6 22.6c-4.7 4.7-12.3 4.7-17 0L256 295.6l-62.2 62.2c-4.7 4.7-12.3 4.7-17 0l-22.6-22.6c-4.7-4.7-4.7-12.3 0-17l62.2-62.2-62.2-62.2c-4.7-4.7-4.7-12.3 0-17l22.6-22.6c4.7-4.7 12.3-4.7 17 0l62.2 62.2 62.2-62.2c4.7-4.7 12.3-4.7 17 0l22.6 22.6c4.7 4.7 4.7 12.3 0 17z"/></svg>';
					$icon_lose       = '<svg class="pm-outcome__icon loser" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm121.6 313.1c4.7 4.7 4.7 12.3 0 17L338 377.6c-4.7 4.7-12.3 4.7-17 0L256 312l-65.1 65.6c-4.7 4.7-12.3 4.7-17 0L134.4 338c-4.7-4.7-4.7-12.3 0-17l65.6-65-65.6-65.1c-4.7-4.7-4.7-12.3 0-17l39.6-39.6c4.7-4.7 12.3-4.7 17 0l65 65.7 65.1-65.6c4.7-4.7 12.3-4.7 17 0l39.6 39.6c4.7 4.7 4.7 12.3 0 17L312 256l65.6 65.1z"/></svg>';

					$selected_icon   = $this->vote_name === $winner_team ? $icon_win : $icon_lose;
					$prediction_icon = $choice === $winner_team ? $icon_win : $icon_lose;
					$home_class      = $home_full === $winner_team ? 'winner' : 'loser';
					$away_class      = $away_full === $winner_team ? 'winner' : 'loser';
					$winner_short    = maiasknews_get_team_short_name( $winner_team, $league );
					$winner_team     = $winner_short ? $winner_short : $winner_team;
					$loser_short     = maiasknews_get_team_short_name( $loser_team, $league );
					$loser_team      = $loser_short ? $loser_short : $loser_team;
					$selected        = sprintf( '<span class="pm-outcome__selected">%s%s</span>', __( 'Your pick', 'promatchups' ), $selected_icon );
					$prediction      = sprintf( '<span class="pm-outcome__prediction">%s%s</span>', __( 'Bot pick', 'promatchups' ), $prediction_icon );
					$home_name       = $home_full === $choice ? $home_name . $prediction : $home_name;
					$away_name       = $away_full === $choice ? $away_name . $prediction : $away_name;
					$home_name       = $home_full === $this->vote_name ? $home_name . $selected : $home_name;
					$away_name       = $away_full === $this->vote_name ? $away_name . $selected : $away_name;

					// If we have a winner and loser.
					if ( $winner_team && $winner_score ) {
						// Do the outcome.
						echo '<div class="pm-outcome">';
							// Away team first
							echo '<div class="pm-outcome__col away">';
								if ( 'winner'  === $away_class ) {
									printf( '<p class="pm-outcome__status">%s</p>', __( 'Winner', 'mai-asknews' ) );
								}
								printf( '<p class="pm-outcome__team %s">%s</p>', $away_class, $away_name );
								printf( '<p class="pm-outcome__score %s">%s</p>', $away_class, 'winner'  === $away_class ? $winner_score : $loser_score );
							echo '</div>';

							// Home team second
							echo '<div class="pm-outcome__col home">';
								if ( 'winner'  === $home_class ) {
									printf( '<p class="pm-outcome__status">%s</p>', __( 'Winner', 'mai-asknews' ) );
								}
								printf( '<p class="pm-outcome__team %s">%s</p>', $home_class, $home_name );
								printf( '<p class="pm-outcome__score %s">%s</p>', $home_class, 'winner' === $home_class ? $winner_score : $loser_score );
							echo '</div>';
						echo '</div>';
					}
				}
				// No outcome.
				else {
					// TODO: Handle no outcome.
				}
			}
			// If not started and they have access to vote.
			elseif ( $has_access ) {
				// Display the avatar.
				printf( '<div class="pm-vote__avatar">%s</div>', $avatar );

				// Display the title.
				echo $heading;

				// Display the vote form.
				echo '<form class="pm-vote__form" method="post" action="">';
					$selected     = sprintf( '<span class="pm-vote__selected">%s</span>', __( 'Your pick', 'promatchups' ) );
					$prediction   = sprintf( '<span class="pm-vote__prediction">%s</span>', __( 'Bot pick', 'promatchups' ) );
					$home_name    = $home_full === $choice ? $home_name . $prediction : $home_name;
					$away_name    = $away_full === $choice ? $away_name . $prediction : $away_name;
					$home_name    = $home_full === $this->vote_name ? $home_name . $selected : $home_name;
					$away_name    = $away_full === $this->vote_name ? $away_name . $selected : $away_name;

					// printf( '<button class="button%s" type="submit" name="team" value="%s">%s</button>', $home_full === $this->vote_name ? ' selected' : '', $home_full, $home_name );
					// printf( '<button class="button%s" type="submit" name="team" value="%s">%s</button>', $away_full === $this->vote_name ? ' selected' : '', $away_full, $away_name );
					printf( '<button class="button" type="submit" name="team" value="%s">%s</button>', $home_full, $home_name );
					printf( '<button class="button" type="submit" name="team" value="%s">%s</button>', $away_full, $away_name );

				echo '</form>';
			}
			// No access.
			else {
				// Display the avatar.
				printf( '<div class="pm-vote__avatar">%s</div>', $avatar );

				// Display the title.
				echo $heading;

				// Display the faux vote form.
				echo '<div class="pm-vote__form">';
					// Build url.
					$url = add_query_arg(
						[
							'rcp_redirect' => get_permalink(),
						],
						get_permalink( 7049 )
					);

					printf( '<a class="button" href="%s">%s</a>', esc_url( $url ), $home_name );
					printf( '<a class="button" href="%s">%s</a>', esc_url( $url ), $away_name );
				echo '</div>';
			}

			// Display the description.
			echo $desc;

		echo '</div>';

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
				'reconciled_information' => [
					'label'  => __( 'Reconciled Info', 'mai-asknews' ),
					'hidden' => $reasoning,
				],
				'unique_information'     => [
					'label'  => __( 'Unique Info', 'mai-asknews' ),
					'hidden' => $reasoning,
				],
			];

			// Display the inner content.
			printf( '<div class="pm-prediction__inner%s">', $hidden ? ' pm-prediction__inner--obfuscated' : '' );
				foreach ( $keys as $key => $value ) {
					$content = $hidden ? $value['hidden'] : maiasknews_get_key( $key, $data );

					if ( ! $content ) {
						continue;
					}

					$heading = $value['label'] ? sprintf( '<strong>%s:</strong> ', $value['label'] ) : '';

					printf( '<p>%s%s</p>', $heading, $content );
					// printf( '<p>%s</p>', $content );
				}

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
				// Show odds.
				else {
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
		printf( '<p>%s</p>', __( 'Timeline of relavant events and news articles.', 'mai-asknews' ) );

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
