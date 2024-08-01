<?php

/**
 * Plugin Name:     Mai AskNews
 * Plugin URI:      https://bizbudding.com
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
		 *  Custom Taxonomies  *
		 ***********************/

		 // Seasons.
		register_taxonomy( 'season', [ 'post' ], [
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
			'meta_box_cb'       => false, // Hides metabox.
			'public'            => false,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => false,
			'show_tagcloud'     => false,
			'show_ui'           => true,
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
