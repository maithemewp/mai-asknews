<?php

/**
 * Plugin Name:     Mai AskNews
 * Plugin URI:      https://promatchups.com
 * Description:     Custom functionality for promatchups.com.
 * Version:         0.8.1
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
			define( 'MAI_ASKNEWS_VERSION', '0.8.1' );
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

		// Listeners.
		include_once MAI_ASKNEWS_DIR . 'classes/listeners/class-listener.php';
		include_once MAI_ASKNEWS_DIR . 'classes/listeners/class-listener-matchup.php';
		include_once MAI_ASKNEWS_DIR . 'classes/listeners/class-listener-outcome.php';
		include_once MAI_ASKNEWS_DIR . 'classes/listeners/class-listener-matchup-outcome.php';
		include_once MAI_ASKNEWS_DIR . 'classes/listeners/class-listener-user-vote.php';
		include_once MAI_ASKNEWS_DIR . 'classes/listeners/class-listener-user-points.php';
		include_once MAI_ASKNEWS_DIR . 'classes/listeners/class-ajax-post-commentary.php';
		include_once MAI_ASKNEWS_DIR . 'classes/listeners/class-ajax-post-vote.php';

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
		$endpoints  = new Mai_AskNews_Endpoints;
		$rewrites   = new Mai_AskNews_Rewrites;
		$display    = new Mai_AskNews_Display;
		$archives   = new Mai_AskNews_Archives;
		$singular   = new Mai_AskNews_Singular;
		$users      = new Mai_AskNews_Users;
		$dashboard  = new Mai_AskNews_Dashboard;
		$shortcodes = new Mai_AskNews_Shortcodes;
		$publisher  = new Mai_AskNews_Mai_Publisher;
		$rank_math  = new Mai_AskNews_Rank_Math;
		$pro_squad  = new Mai_AskNews_Pro_Squad;
		$comments   = new Mai_AskNews_Comments;
		$commentary = new Mai_AskNews_Ajax_Post_Commentary;
		$votes      = new Mai_AskNews_Ajax_Post_Vote;
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
			'has_archive'         => true, // So customizer works.
			'hierarchical'        => false,
			'labels'              => [
				'name'               => _x( 'Matchups', 'Matchup general name', 'promatchups' ),
				'singular_name'      => _x( 'Matchup', 'Matchup singular name', 'promatchups' ),
				'menu_name'          => _x( 'Matchups', 'Matchup admin menu', 'promatchups' ),
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
			'supports'           => [ 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'page-attributes', 'genesis-cpt-archives-settings', 'genesis-layouts', 'mai-archive-settings', 'mai-single-settings' ],
			'taxonomies'         => [ 'team', 'season' ],
			'rewrite'            => [
				'slug'       => 'matchups',
				'with_front' => false,
			],
		] );

		// Insights.
		register_post_type( 'insight', [
			'exclude_from_search' => false,
			'has_archive'         => false,
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
			'public'             => false,
			'publicly_queryable' => false,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => true,
			'show_ui'            => true,
			'supports'           => [ 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'genesis-cpt-archives-settings', 'genesis-layouts', 'mai-archive-settings', 'mai-single-settings' ],
			'taxonomies'         => [ 'team', 'league', 'season' ],
			'rewrite'            => false, // Handled in Mai_AskNews_Rewrites.
		] );

		/***********************
		 *  Custom Taxonomies  *
		 ***********************/

		// Leagues/Matchups.
		register_taxonomy( 'league', [ 'matchup', 'insight' ], [
			'hierarchical' => true,
			'labels'       => [
				'name'                       => _x( 'Leagues', 'League General Name', 'promatchups' ),
				'singular_name'              => _x( 'League', 'League Singular Name', 'promatchups' ),
				'menu_name'                  => __( 'Leagues', 'promatchups' ),
				'all_items'                  => __( 'All Leagues', 'promatchups' ),
				'parent_item'                => __( 'Parent League', 'promatchups' ),
				'parent_item_colon'          => __( 'Parent League:', 'promatchups' ),
				'new_item_name'              => __( 'New League Name', 'promatchups' ),
				'add_new_item'               => __( 'Add New League', 'promatchups' ),
				'edit_item'                  => __( 'Edit League', 'promatchups' ),
				'update_item'                => __( 'Update League', 'promatchups' ),
				'view_item'                  => __( 'View League', 'promatchups' ),
				'separate_items_with_commas' => __( 'Separate items with commas', 'promatchups' ),
				'add_or_remove_items'        => __( 'Add or remove items', 'promatchups' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'promatchups' ),
				'popular_items'              => __( 'Popular Leagues', 'promatchups' ),
				'search_items'               => __( 'Search Leagues', 'promatchups' ),
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

		// Matchup Tags.
		register_taxonomy( 'matchup_tag', 'matchup', [
			'hierarchical' => false,
			'labels'       => [
				'name'                       => _x( 'Matchup Tags', 'Matchup Tag General Name', 'promatchups' ),
				'singular_name'              => _x( 'Matchup Tag', 'Matchup Tag Singular Name', 'promatchups' ),
				'menu_name'                  => __( 'Tags', 'promatchups' ),
				'all_items'                  => __( 'All Matchup Tags', 'promatchups' ),
				'parent_item'                => __( 'Parent Matchup Tag', 'promatchups' ),
				'parent_item_colon'          => __( 'Parent Matchup Tag:', 'promatchups' ),
				'new_item_name'              => __( 'New Matchup Tag Name', 'promatchups' ),
				'add_new_item'               => __( 'Add New Matchup Tag', 'promatchups' ),
				'edit_item'                  => __( 'Edit Matchup Tag', 'promatchups' ),
				'update_item'                => __( 'Update Matchup Tag', 'promatchups' ),
				'view_item'                  => __( 'View Matchup Tag', 'promatchups' ),
				'separate_items_with_commas' => __( 'Separate items with commas', 'promatchups' ),
				'add_or_remove_items'        => __( 'Add or remove items', 'promatchups' ),
				'choose_from_most_used'      => __( 'Choose from the most used', 'promatchups' ),
				'popular_items'              => __( 'Popular Matchup Tags', 'promatchups' ),
				'search_items'               => __( 'Search Matchup Tags', 'promatchups' ),
				'not_found'                  => __( 'Not Found', 'promatchups' ),
			],
			'meta_box_cb'       => false,   // Hides metabox.
			'public'            => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => true,
			'show_tagcloud'     => true,
			'show_ui'           => true,
			'rewrite'           => [
				'slug'       => 'tags',
				'with_front' => false,
			],
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