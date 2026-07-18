<?php
/**
 * Plugin Name: Lexiata XSlider
 * Description: A lightweight, fast, and easy-to-use slider plugin for WordPress. Build responsive image sliders with clickable links — no bloat, no third-party dependencies.
 * Version: 1.0.0
 * Author: Suresh L. Bamunusinghe
 * Author URI: https://lexiata.lk
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lexiata-xslider
 * Domain Path: /languages
 * Requires at least: 5.5
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constants
define( 'LEX_XSLIDER_VERSION',  '1.0.0' );
define( 'LEX_XSLIDER_FILE',     __FILE__ );
define( 'LEX_XSLIDER_DIR',      plugin_dir_path( __FILE__ ) );
define( 'LEX_XSLIDER_URL',      plugin_dir_url( __FILE__ ) );
define( 'LEX_XSLIDER_BASENAME', plugin_basename( __FILE__ ) );
define( 'LEX_XSLIDER_CPT',      'lex_xslider' );

// Core includes
require_once LEX_XSLIDER_DIR . 'includes/class-helpers.php';
require_once LEX_XSLIDER_DIR . 'includes/class-cpt.php';
require_once LEX_XSLIDER_DIR . 'includes/class-admin.php';
require_once LEX_XSLIDER_DIR . 'includes/class-project-editor.php';
require_once LEX_XSLIDER_DIR . 'includes/class-slide-manager.php';
require_once LEX_XSLIDER_DIR . 'includes/class-frontend.php';

/**
 * Bootstrap the plugin once WordPress is ready.
 *
 * Note: We don't call load_plugin_textdomain() here — since WordPress 4.6,
 * translations hosted on WordPress.org are loaded automatically based on the
 * plugin slug declared in the header (Text Domain: lexiata-xslider).
 */
add_action( 'plugins_loaded', function () {
	Lex_XSlider_CPT::init();
	Lex_XSlider_Admin::init();
	Lex_XSlider_Project_Editor::init();
	Lex_XSlider_Slide_Manager::init();
	Lex_XSlider_Frontend::init();
} );

/**
 * Activation: register CPT and flush rewrite rules so admin URLs work.
 */
register_activation_hook( __FILE__, function () {
	Lex_XSlider_CPT::register();
	flush_rewrite_rules();
} );

/**
 * Deactivation: clean up rewrite rules.
 */
register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );

/**
 * Add a Settings link on the Plugins page.
 */
add_filter( 'plugin_action_links_' . LEX_XSLIDER_BASENAME, function ( $links ) {
	$dashboard_url = admin_url( 'admin.php?page=lex-xslider' );
	$action_link   = '<a href="' . esc_url( $dashboard_url ) . '">' . esc_html__( 'Dashboard', 'lexiata-xslider' ) . '</a>';
	array_unshift( $links, $action_link );
	return $links;
} );
