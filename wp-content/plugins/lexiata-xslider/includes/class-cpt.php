<?php
/**
 * Lexiata XSlider — Custom Post Type
 *
 * Registers the "lex_xslider" CPT for slider projects.
 *
 * @package Lexiata_XSlider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lex_XSlider_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$labels = array(
			'name'          => __( 'XSlider Projects', 'lexiata-xslider' ),
			'singular_name' => __( 'XSlider Project',  'lexiata-xslider' ),
			'add_new'       => __( 'New Project',      'lexiata-xslider' ),
			'add_new_item'  => __( 'Add New Project',  'lexiata-xslider' ),
			'edit_item'     => __( 'Edit Project',     'lexiata-xslider' ),
			'all_items'     => __( 'All Projects',     'lexiata-xslider' ),
			'search_items'  => __( 'Search Projects',  'lexiata-xslider' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => false,           // We use our own admin UI
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title' ),
		);

		register_post_type( LEX_XSLIDER_CPT, $args );
	}

	/**
	 * Create a new slider project.
	 *
	 * @param array $args { name, width, height, layout, slider_type }
	 * @return int|WP_Error  Post ID or WP_Error.
	 */
	public static function create_project( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'name'        => __( 'My project', 'lexiata-xslider' ),
				'width'       => 1200,
				'height'      => 600,
				'layout'      => 'full_width',
				'slider_type' => 'simple',
			)
		);

		$post_id = wp_insert_post( array(
			'post_type'   => LEX_XSLIDER_CPT,
			'post_status' => 'publish',
			'post_title'  => sanitize_text_field( $args['name'] ),
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save initial settings (merge user input over defaults).
		$settings                       = Lex_XSlider_Helpers::default_settings();
		$settings['general']['name']    = sanitize_text_field( $args['name'] );
		$settings['size']['width']      = Lex_XSlider_Helpers::int_in_range( $args['width'],  100, 5000 );
		$settings['size']['height']     = Lex_XSlider_Helpers::int_in_range( $args['height'],  50, 5000 );
		$settings['size']['layout']     = Lex_XSlider_Helpers::pick( $args['layout'], array( 'full_width', 'boxed' ), 'full_width' );

		Lex_XSlider_Helpers::save_settings( $post_id, $settings );
		Lex_XSlider_Helpers::save_slides( $post_id, array() );

		return $post_id;
	}

	/**
	 * Get all slider projects (for the dashboard listing).
	 *
	 * @return WP_Post[]
	 */
	public static function get_all_projects() {
		$query = new WP_Query( array(
			'post_type'      => LEX_XSLIDER_CPT,
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		return $query->posts;
	}
}
