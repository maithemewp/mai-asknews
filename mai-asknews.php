<?php

/**
 * Plugin Name:     Mai AskNews
 * Plugin URI:      https://promatchups.com
 * Description:     A custom endpoint to receive data from AskNews.
 * Version:         0.1.0
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Must be at the top of the file.
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Main Mai_AskNews_Plugin Class.
 *
 * @since 0.1.0
 */
final class Mai_AskNews_Plugin {

	/**
	 * @var   Mai_AskNews_Plugin The one true Mai_AskNews_Plugin
	 * @since 0.1.0
	 */
	private static $instance;

	/**
	 * Main Mai_AskNews_Plugin Instance.
	 *
	 * Insures that only one instance of Mai_AskNews_Plugin exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since   0.1.0
	 * @static  var array $instance
	 * @uses    Mai_AskNews_Plugin::setup_constants() Setup the constants needed.
	 * @uses    Mai_AskNews_Plugin::includes() Include the required files.
	 * @uses    Mai_AskNews_Plugin::hooks() Activate, deactivate, etc.
	 * @see     Mai_AskNews_Plugin()
	 * @return  object | Mai_AskNews_Plugin The one true Mai_AskNews_Plugin
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			// Setup the setup.
			self::$instance = new Mai_AskNews_Plugin;
			// Methods.
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
			self::$instance->classes();
		}
		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @access private
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-asknews' ), '1.0' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @access private
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-asknews' ), '1.0' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access private
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function setup_constants() {
		// Plugin version.
		if ( ! defined( 'MAI_ASKNEWS_VERSION' ) ) {
			define( 'MAI_ASKNEWS_VERSION', '0.1.0' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'MAI_ASKNEWS_DIR' ) ) {
			define( 'MAI_ASKNEWS_DIR', plugin_dir_path( __FILE__ ) );
		}

		// // Plugin Includes Path.
		// if ( ! defined( 'MAI_ASKNEWS_INCLUDES_DIR' ) ) {
		// 	define( 'MAI_ASKNEWS_INCLUDES_DIR', MAI_ASKNEWS_DIR . 'includes/' );
		// }

		// Plugin Folder URL.
		if ( ! defined( 'MAI_ASKNEWS_URL' ) ) {
			define( 'MAI_ASKNEWS_URL', plugin_dir_url( __FILE__ ) );
		}
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function includes() {
		// Include vendor libraries.
		require_once __DIR__ . '/vendor/autoload.php';

		// Includes.
		foreach ( glob( MAI_ASKNEWS_DIR . 'classes/*.php' ) as $file ) { include $file; }
		foreach ( glob( MAI_ASKNEWS_DIR . 'includes/*.php' ) as $file ) { include $file; }
	}

	/**
	 * Run the hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'plugins_loaded', [ $this, 'updater' ] );
		add_action( 'init',           [ $this, 'register_content_types' ] );

		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
	}

	/**
	 * Setup the updater.
	 *
	 * composer require yahnis-elsts/plugin-update-checker
	 *
	 * @since 0.1.0
	 *
	 * @uses https://github.com/YahnisElsts/plugin-update-checker/
	 *
	 * @return void
	 */
	public function updater() {
		// Bail if plugin updater is not loaded.
		if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		// // Setup the updater.
		// $updater = PucFactory::buildUpdateChecker( 'https://github.com/maithemewp/plugin-slug/', __FILE__, 'mai-user-post' );

		// // Set the branch that contains the stable release.
		// $updater->setBranch( 'main' );

		// // Maybe set github api token.
		// if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
		// 	$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
		// }

		// // Add icons for Dashboard > Updates screen.
		// if ( function_exists( 'mai_get_updater_icons' ) && $icons = mai_get_updater_icons() ) {
		// 	$updater->addResultFilter(
		// 		function ( $info ) use ( $icons ) {
		// 			$info->icons = $icons;
		// 			return $info;
		// 		}
		// 	);
		// }
	}

	/**
	 * Instantiate the classes.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function classes() {
		$endpoints = new Mai_AskNews_Endpoints;
		$display   = new Mai_AskNews_Display;
		$rewrites  = new Mai_AskNews_Rewrites;
	}

	/**
	 * Register content types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_content_types() {
		/***********************
		 *  Post Types         *
		 ***********************/

		// Schedule/Matchups.
		register_post_type( 'matchup', [
			'exclude_from_search' => false,
			'has_archive'         => true,
			'hierarchical'        => false,
			'labels'              => [
				'name'               => _x( 'Matchups', 'Matchup general name', 'promatchups' ),
				'singular_name'      => _x( 'Matchup', 'Matchup singular name', 'promatchups' ),
				'menu_name'          => _x( 'Schedule', 'Matchup admin menu', 'promatchups' ),
				'name_admin_bar'     => _x( 'Matchup', 'Matchup add new on admin bar', 'promatchups' ),
				'add_new'            => _x( 'Add New Matchup', 'Matchup', 'promatchups' ),
				'add_new_item'       => __( 'Add New Matchup',  'promatchups' ),
				'new_item'           => __( 'New Matchup', 'promatchups' ),
				'edit_item'          => __( 'Edit Matchup', 'promatchups' ),
				'view_item'          => __( 'View Matchup', 'promatchups' ),
				'all_items'          => __( 'All Matchups', 'promatchups' ),
				'search_items'       => __( 'Search Matchups', 'promatchups' ),
				'parent_item_colon'  => __( 'Parent Matchups:', 'promatchups' ),
				'not_found'          => __( 'No Matchups found.', 'promatchups' ),
				'not_found_in_trash' => __( 'No Matchups found in Trash.', 'promatchups' )
			],
			'menu_icon'          => 'dashicons-calendar',
			'menu_position'      => 5,
			'public'             => true,
			'publicly_queryable' => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'show_ui'            => true,
			'supports'           => [ 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'genesis-cpt-archives-settings', 'genesis-layouts', 'mai-archive-settings', 'mai-single-settings' ],
			'taxonomies'         => [ 'team', 'season' ],
			'rewrite'            => [ 'slug' => 'schedule', 'with_front' => false ],
		] );

		// Insights.
		register_post_type( 'insight', [
			'exclude_from_search' => false,
			'has_archive'         => true,
			'hierarchical'        => false,
			'labels'              => [
				'name'               => _x( 'Insights', 'Insight general name', 'promatchups' ),
				'singular_name'      => _x( 'Insight', 'Insight singular name', 'promatchups' ),
				'menu_name'          => _x( 'Insights', 'Insight admin menu', 'promatchups' ),
				'name_admin_bar'     => _x( 'Insight', 'Insight add new on admin bar', 'promatchups' ),
				'add_new'            => _x( 'Add New Insight', 'Insight', 'promatchups' ),
				'add_new_item'       => __( 'Add New Insight',  'promatchups' ),
				'new_item'           => __( 'New Insight', 'promatchups' ),
				'edit_item'          => __( 'Edit Insight', 'promatchups' ),
				'view_item'          => __( 'View Insight', 'promatchups' ),
				'all_items'          => __( 'All Insights', 'promatchups' ),
				'search_items'       => __( 'Search Insights', 'promatchups' ),
				'parent_item_colon'  => __( 'Parent Insights:', 'promatchups' ),
				'not_found'          => __( 'No Insights found.', 'promatchups' ),
				'not_found_in_trash' => __( 'No Insights found in Trash.', 'promatchups' )
			],
			'menu_icon'          => 'dashicons-analytics',
			'menu_position'      => 6,
			'public'             => true,
			'publicly_queryable' => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'show_ui'            => true,
			'supports'           => [ 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'genesis-cpt-archives-settings', 'genesis-layouts', 'mai-archive-settings', 'mai-single-settings' ],
			'taxonomies'         => [ 'team', 'season' ],
			'rewrite'            => false, // Handled in Mai_AskNews_Rewrites.
		] );

		/***********************
		 *  Custom Taxonomies  *
		 ***********************/

		// Teams.
		register_taxonomy( 'team', [ 'matchup', 'insight' ], [
			'hierarchical' => true,
			'labels'       => [
				'name'                       => _x( 'Teams', 'Team General Name', 'promatchups' ),
				'singular_name'              => _x( 'Team', 'Team Singular Name', 'promatchups' ),
				'menu_name'                  => __( 'Teams', 'promatchups' ),
				'all_items'                  => __( 'All Teams', 'promatchups' ),
				'parent_item'                => __( 'Parent Team', 'promatchups' ),
				'parent_item_colon'          => __( 'Parent Team:', 'promatchups' ),
				'new_item_name'              => __( 'New Team Name', 'promatchups' ),
				'add_new_item'               => __( 'Add New Team', 'promatchups' ),
				'edit_item'                  => __( 'Edit Team', 'promatchups' ),
				'update_item'                => __( 'Update Team', 'promatchups' ),
				'view_item'                  => __( 'View Team', 'promatchups' ),
				'separate_items_with_commas' => __( 'Separate items with commas', 'promatchups' ),
				'add_or_remove_items'        => __( 'Add or remove items', 'promatchups' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'promatchups' ),
				'popular_items'              => __( 'Popular Teams', 'promatchups' ),
				'search_items'               => __( 'Search Teams', 'promatchups' ),
				'not_found'                  => __( 'Not Found', 'promatchups' ),
			],
			'meta_box_cb'       => false,   // Hides metabox.
			'public'            => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'show_tagcloud'     => false,
			'show_ui'           => true,
			'rewrite'           => false, // Handled in Mai_AskNews_Rewrites.
		] );

		// Seasons.
		register_taxonomy( 'season', [ 'matchup', 'insight' ], [
			'hierarchical' => false,
			'labels'       => [
				'name'                       => _x( 'Seasons', 'Season General Name', 'promatchups' ),
				'singular_name'              => _x( 'Season', 'Season Singular Name', 'promatchups' ),
				'menu_name'                  => __( 'Seasons', 'promatchups' ),
				'all_items'                  => __( 'All Items', 'promatchups' ),
				'parent_item'                => __( 'Parent Item', 'promatchups' ),
				'parent_item_colon'          => __( 'Parent Item:', 'promatchups' ),
				'new_item_name'              => __( 'New Item Name', 'promatchups' ),
				'add_new_item'               => __( 'Add New Item', 'promatchups' ),
				'edit_item'                  => __( 'Edit Item', 'promatchups' ),
				'update_item'                => __( 'Update Item', 'promatchups' ),
				'view_item'                  => __( 'View Item', 'promatchups' ),
				'separate_items_with_commas' => __( 'Separate items with commas', 'promatchups' ),
				'add_or_remove_items'        => __( 'Add or remove items', 'promatchups' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'promatchups' ),
				'popular_items'              => __( 'Popular Items', 'promatchups' ),
				'search_items'               => __( 'Search Items', 'promatchups' ),
				'not_found'                  => __( 'Not Found', 'promatchups' ),
			],
			'meta_box_cb'       => false,   // Hides metabox.
			'public'            => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => true,
			'show_tagcloud'     => false,
			'show_ui'           => true,
			'rewrite'           => false,   // Handled in Mai_AskNews_Rewrites.
		] );
	}

	/**
	 * Plugin activation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function activate() {
		$this->register_content_types();
		flush_rewrite_rules();
	}
}

/**
 * The main function for that returns Mai_AskNews_Plugin
 *
 * @since 0.1.0
 *
 * @return object|Mai_AskNews_Plugin The one true Mai_AskNews_Plugin Instance.
 */
function mai_asknews_plugin() {
	return Mai_AskNews_Plugin::instance();
}

// Get Mai_AskNews_Plugin Running.
mai_asknews_plugin();


// function add_custom_rewrite_rules() {
//     // Add rewrite rules for the custom structure
//     add_rewrite_rule(
//         '^([^/]+)/([^/]+)/([^/]+)/?$',
//         // 'index.php?post_type=insight&team=$matches[1]&season=$matches[2]&name=$matches[3]',
//         'index.php?post_type=insight&team=$matches[1]&name=$matches[2]',
//         'bottom'
//     );
// }
// add_action('init', 'add_custom_rewrite_rules');

// // Customize the permalink structure for 'matchup' posts
// add_filter('post_type_link', 'matchup_post_type_link', 10, 2);
// function matchup_post_type_link($post_link, $post) {
// 	if ($post->post_type === 'matchup') {
// 		// Get the terms of the 'team' taxonomy
// 		$teams = wp_get_post_terms($post->ID, 'team');
// 		if ($teams && !is_wp_error($teams)) {
// 			$league = $teams[0]->slug;
// 			$team = isset($teams[1]) ? $teams[1]->slug : '';
// 			$post_link = str_replace('%team%', "$league/$team", $post_link);
// 		}

// 		// Get the terms of the 'season' taxonomy
// 		$seasons = wp_get_post_terms($post->ID, 'season');
// 		if ($seasons && !is_wp_error($seasons)) {
// 			$season = $seasons[0]->slug;
// 			$post_link = str_replace('%season%', $season, $post_link);
// 		}

// 		$post_link = home_url(user_trailingslashit("$league/$team/$season/$post->post_name"));
// 	}
// 	return $post_link;
// }

// // Handle URL routing for 'matchup' posts
// function matchup_parse_request($query) {
// 	if (!empty($query->request)) {
// 		$matched = preg_match('#^([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$#', $query->request, $matches);
// 		if ($matched && count($matches) === 5) {
// 			$league = $matches[1];
// 			$team = $matches[2];
// 			$season = $matches[3];
// 			$post_slug = $matches[4];

// 			// Fetch the post based on the slug
// 			$query->query_vars['post_type'] = 'matchup';
// 			$query->query_vars['name'] = $post_slug;
// 			add_filter('posts_where', function($where) use ($league, $team, $season) {
// 				global $wpdb;
// 				$where .= $wpdb->prepare(" AND $wpdb->terms.slug = %s", $league);
// 				if ($team) {
// 					$where .= $wpdb->prepare(" AND $wpdb->terms.slug = %s", $team);
// 				}
// 				$where .= $wpdb->prepare(" AND $wpdb->terms.slug = %s", $season);
// 				return $where;
// 			});
// 		}
// 	}
// }
// add_action('parse_request', 'matchup_parse_request');

// // Filter to replace %project_category% with the actual term
// // add_filter('post_type_link', 'custom_post_type_link', 1, 2);
// function custom_post_type_link($post_link, $post) {
// 	if ( ! $post || 'insight' !== $post->post_type ) {
// 		return $post_link;
// 	}

// 	// Get the terms from the team taxonomy.
// 	$terms = get_the_terms( $post->ID, 'team' );

// 	// If no terms, return the link.
// 	if ( ! $terms ) {
// 		return $post_link;
// 	}

// 	// Get the first term.
// 	$term = reset( $terms );

// 	// Check if the term has a parent and keep updating until top-level term is found
// 	while ( 0 !== $term->parent ) {
// 		$term = get_term( $term->parent, 'team' );
// 	}

// 	// Replace the placeholder with the actual term slug.
// 	$post_link = str_replace( '%team%', $term->slug, $post_link );

// 	return $post_link;
// }

// add_action( 'init', 'rewrite_tag_permalink_init' );
// /**
//  * Add our rewrite rule and rewrite tag
//  */
// function rewrite_tag_permalink_init(){
// 	// rewrite rule looks for league
// 	add_rewrite_rule( '^([^/]+)/(.*)/?', 'index.php?league=$matches[1]&season=$matches[2]', 'bottom' );

// 	// rewrite tag puts league value into the query vars
// 	add_rewrite_tag( '%league%', '(.*)' );
// 	add_rewrite_tag( '%team%', '(.*)' );
// 	add_rewrite_tag( '%season%', '(.*)' );
// }


// Register custom permalink structure and rewrite tags
// add_action('init', 'custom_matchup_permalinks');
function custom_matchup_permalinks() {
	// Add permastruct for 'insight' posts
	add_permastruct( 'insight', '%league%/%season%/%postname%', array(
		'ep_mask'    => EP_PERMALINK,
		'with_front' => false,
	));

	// Add rewrite tags for custom placeholders
	add_rewrite_tag( '%league%', '([^/]+)', 'league=' );
	add_rewrite_tag( '%season%', '([^/]+)', 'season=' );
	add_rewrite_tag( '%postname%', '([^/]+)', 'name=' );
}


// Customize the permalink structure for 'insight' posts
// add_filter( 'post_type_link', 'custom_insight_post_link', 10, 2 );
function custom_insight_post_link( $post_link, $post ) {
	if ( 'insight' !== $post->post_type ) {
		return $post_link;
	}

	$league = get_league( $post->ID );
	$season = get_season( $post->ID );

	if ( $league ) {
		$post_link = str_replace( '%league%', $league->slug, $post_link );
	} else {
		$post_link = str_replace( '%league%/', '', $post_link );
	}

	if ( $season ) {
		$post_link = str_replace( '%season%', $season->slug, $post_link );
	} else {
		$post_link = str_replace( '%season%/', '', $post_link );
	}

	// Post name.
	$post_link = str_replace( '%postname%', $post->post_name, $post_link );

	return $post_link;
}

function get_league( $post_id ) {
	$league = null;
	$terms  = get_the_terms( $post_id, 'team' );

	// Bail if no terms.
	if ( ! $terms || is_wp_error( $terms ) ) {
		return $league;
	}

	// Find the first term that has no parent.
	foreach ( $terms as $term ) {
		if ( 0 === $term->parent ) {
			$league = $term;
			break;
		}
	}

	// If no league.
	if ( ! $league ) {
		foreach ( $terms as $term ) {
			$current_term = $term;

			while ( $current_term ) {
				if ( 0 === $current_term->parent ) {
					$league = $current_term;
					break;
				}

				$current_term = get_term( $current_term->parent, 'team' );
			}

			if ( $league ) {
				break;
			}
		}
	}

	return $league;
}

function get_team( $post_id ) {
	$team  = null;
	$terms = get_the_terms( $post_id, 'team' );

	// Bail if no terms.
	if ( ! $terms || is_wp_error( $terms ) ) {
		return $team;
	}

	// Find the first term that has a parent.
	foreach ( $terms as $term ) {
		if ( 0 !== $term->parent ) {
			$team = $term;
		}
	}

	return $team;
}

function get_season( $post_id ) {
	$season = null;
	$terms  = get_the_terms( $post_id, 'season' );

	// Bail if no terms.
	if ( ! $terms || is_wp_error( $terms ) ) {
		return $season;
	}

	$season = reset( $terms );

	return $season;
}