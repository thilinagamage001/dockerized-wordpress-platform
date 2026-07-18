<?php
/**
 * Lexiata XSlider — Helpers
 *
 * Default settings, sanitization, data access.
 *
 * @package Lexiata_XSlider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lex_XSlider_Helpers {

	const META_SETTINGS = '_lex_xslider_settings';
	const META_SLIDES   = '_lex_xslider_slides';

	/**
	 * Default settings for a new slider project (8 tabs).
	 */
	public static function default_settings() {
		return array(
			// General
			'general' => array(
				'name'   => '',
				'alias'  => '',
				'align'  => 'normal',  // normal | left | center | right
				'margin' => 0,
			),
			// Size
			'size' => array(
				'width'              => 1200,
				'height'             => 600,
				'layout'             => 'full_width',  // full_width | boxed
				'min_height'         => 0,
				'force_full_width'   => 1,
				'limit_slide_width'  => 1,
				'border_radius'      => 0,             // 0–100 px
			),
			// Controls
			'controls' => array(
				'drag'           => 'horizontal',  // horizontal | disabled
				'keyboard'       => 1,
				'arrow_enabled'  => 1,
				'arrow_style'    => 'default',     // default | circle | minimal | square
				'arrow_color'    => '#ffffff',
				'arrow_position' => 'middle',      // middle | bottom
				'arrow_size'     => 32,
				'arrow_hide_on'  => array(),
				'bullet_enabled' => 0,
				'bullet_style'   => 'dot',         // dot | bar | pill | square
				'bullet_color'   => '#ffffff',
				'bullet_position'=> 'bottom',
			),
			// Animations
			'animations' => array(
				'main_animation' => 'horizontal',  // horizontal | vertical | fade | zoom
				'duration'       => 800,
			),
			// Autoplay
			'autoplay' => array(
				'enabled'         => 1,
				'slide_duration'  => 8000,
				'stop_on_click'   => 1,
				'stop_on_hover'   => 0,
				'resume_on_leave' => 0,
			),
			// Optimize
			'optimize' => array(
				'loading_type'   => 'instant',  // instant | lazy
				'lazy_threshold' => 50,
			),
			// Slides
			'slides' => array(
				'bg_fill' => 'fill',  // fill | fit | stretch | center | blur_fit
			),
			// Developer
			'developer' => array(
				'css_classes' => '',
			),
		);
	}

	/**
	 * Default for a single slide.
	 */
	public static function default_slide( $type = 'image' ) {
		return array(
			'id'              => 'slide_' . wp_generate_uuid4(),
			'type'            => in_array( $type, array( 'image', 'blank' ), true ) ? $type : 'image',
			'name'            => '',
			'image_id'        => 0,
			'image_url'       => '',
			'image_alt'       => '',
			'background_color'=> '',
			'content_html'    => '',
			'link_url'        => '',
			'link_target'     => '_self',  // _self | _blank
			'link_nofollow'   => 0,
			'published'       => 1,
		);
	}

	/**
	 * Get settings for a project (post ID).
	 * Always merged with defaults so new fields are filled in safely.
	 */
	public static function get_settings( $post_id ) {
		$saved = get_post_meta( $post_id, self::META_SETTINGS, true );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return self::deep_merge( self::default_settings(), $saved );
	}

	/**
	 * Save settings (after sanitization).
	 */
	public static function save_settings( $post_id, $raw ) {
		$clean = self::sanitize_settings( $raw );
		update_post_meta( $post_id, self::META_SETTINGS, $clean );
		return $clean;
	}

	/**
	 * Get slides for a project.
	 */
	public static function get_slides( $post_id ) {
		$saved = get_post_meta( $post_id, self::META_SLIDES, true );
		return is_array( $saved ) ? array_values( $saved ) : array();
	}

	/**
	 * Save slides (sanitized).
	 */
	public static function save_slides( $post_id, $raw ) {
		$clean = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $slide ) {
				$clean[] = self::sanitize_slide( $slide );
			}
		}
		update_post_meta( $post_id, self::META_SLIDES, $clean );
		return $clean;
	}

	/**
	 * Sanitize a full settings array.
	 */
	public static function sanitize_settings( $raw ) {
		$defaults = self::default_settings();
		$clean    = $defaults;

		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		// General
		if ( isset( $raw['general'] ) && is_array( $raw['general'] ) ) {
			$g = $raw['general'];
			$clean['general']['name']   = sanitize_text_field( $g['name']   ?? '' );
			$clean['general']['alias']  = sanitize_title(     $g['alias']  ?? '' );
			$clean['general']['align']  = self::pick( $g['align']  ?? '', array( 'normal', 'left', 'center', 'right' ), 'normal' );
			$clean['general']['margin'] = self::int_in_range( $g['margin'] ?? 0, 0, 200 );
		}

		// Size
		if ( isset( $raw['size'] ) && is_array( $raw['size'] ) ) {
			$s = $raw['size'];
			$clean['size']['width']             = self::int_in_range( $s['width']  ?? 1200, 100, 5000 );
			$clean['size']['height']            = self::int_in_range( $s['height'] ?? 600, 50, 5000 );
			$clean['size']['layout']            = self::pick( $s['layout'] ?? '', array( 'full_width', 'boxed' ), 'full_width' );
			$clean['size']['min_height']        = self::int_in_range( $s['min_height'] ?? 0, 0, 2000 );
			$clean['size']['force_full_width']  = ! empty( $s['force_full_width'] ) ? 1 : 0;
			$clean['size']['limit_slide_width'] = ! empty( $s['limit_slide_width'] ) ? 1 : 0;
			$clean['size']['border_radius']     = self::int_in_range( $s['border_radius'] ?? 0, 0, 100 );
		}

		// Controls
		if ( isset( $raw['controls'] ) && is_array( $raw['controls'] ) ) {
			$c = $raw['controls'];
			$clean['controls']['drag']           = self::pick( $c['drag'] ?? '', array( 'horizontal', 'disabled' ), 'horizontal' );
			$clean['controls']['keyboard']       = ! empty( $c['keyboard'] ) ? 1 : 0;
			$clean['controls']['arrow_enabled']  = ! empty( $c['arrow_enabled'] ) ? 1 : 0;
			$clean['controls']['arrow_style']    = self::pick( $c['arrow_style'] ?? '', array( 'default', 'circle', 'minimal', 'square' ), 'default' );
			$clean['controls']['arrow_color']    = self::sanitize_hex( $c['arrow_color'] ?? '#ffffff' );
			$clean['controls']['arrow_position'] = self::pick( $c['arrow_position'] ?? '', array( 'middle', 'bottom' ), 'middle' );
			$clean['controls']['arrow_size']     = self::int_in_range( $c['arrow_size'] ?? 32, 12, 96 );
			$clean['controls']['arrow_hide_on']  = self::sanitize_devices( $c['arrow_hide_on'] ?? array() );
			$clean['controls']['bullet_enabled'] = ! empty( $c['bullet_enabled'] ) ? 1 : 0;
			$clean['controls']['bullet_style']   = self::pick( $c['bullet_style'] ?? '', array( 'dot', 'bar', 'pill', 'square' ), 'dot' );
			$clean['controls']['bullet_color']   = self::sanitize_hex( $c['bullet_color'] ?? '#ffffff' );
			$clean['controls']['bullet_position']= self::pick( $c['bullet_position'] ?? '', array( 'bottom', 'top' ), 'bottom' );
		}

		// Animations
		if ( isset( $raw['animations'] ) && is_array( $raw['animations'] ) ) {
			$a = $raw['animations'];
			$clean['animations']['main_animation'] = self::pick( $a['main_animation'] ?? '', array( 'horizontal', 'vertical', 'fade', 'zoom' ), 'horizontal' );
			$clean['animations']['duration']       = self::int_in_range( $a['duration'] ?? 800, 100, 5000 );
		}

		// Autoplay
		if ( isset( $raw['autoplay'] ) && is_array( $raw['autoplay'] ) ) {
			$ap = $raw['autoplay'];
			$clean['autoplay']['enabled']         = ! empty( $ap['enabled'] ) ? 1 : 0;
			$clean['autoplay']['slide_duration']  = self::int_in_range( $ap['slide_duration'] ?? 8000, 500, 60000 );
			$clean['autoplay']['stop_on_click']   = ! empty( $ap['stop_on_click'] ) ? 1 : 0;
			$clean['autoplay']['stop_on_hover']   = ! empty( $ap['stop_on_hover'] ) ? 1 : 0;
			$clean['autoplay']['resume_on_leave'] = ! empty( $ap['resume_on_leave'] ) ? 1 : 0;
		}

		// Optimize
		if ( isset( $raw['optimize'] ) && is_array( $raw['optimize'] ) ) {
			$o = $raw['optimize'];
			$clean['optimize']['loading_type']   = self::pick( $o['loading_type'] ?? '', array( 'instant', 'lazy' ), 'instant' );
			$clean['optimize']['lazy_threshold'] = self::int_in_range( $o['lazy_threshold'] ?? 50, 0, 100 );
		}

		// Slides design
		if ( isset( $raw['slides'] ) && is_array( $raw['slides'] ) ) {
			$sl = $raw['slides'];
			$clean['slides']['bg_fill'] = self::pick( $sl['bg_fill'] ?? '', array( 'fill', 'fit', 'stretch', 'center', 'blur_fit' ), 'fill' );
		}

		// Developer
		if ( isset( $raw['developer'] ) && is_array( $raw['developer'] ) ) {
			$d = $raw['developer'];
			$clean['developer']['css_classes'] = sanitize_text_field( $d['css_classes'] ?? '' );
		}

		return $clean;
	}

	/**
	 * Sanitize a single slide.
	 */
	public static function sanitize_slide( $raw ) {
		$default = self::default_slide();
		if ( ! is_array( $raw ) ) {
			return $default;
		}

		$type = self::pick( $raw['type'] ?? 'image', array( 'image', 'blank' ), 'image' );

		$slide = array(
			'id'              => ! empty( $raw['id'] ) ? sanitize_key( $raw['id'] ) : 'slide_' . wp_generate_uuid4(),
			'type'            => $type,
			'name'            => sanitize_text_field( $raw['name'] ?? '' ),
			'image_id'        => absint( $raw['image_id'] ?? 0 ),
			'image_url'       => esc_url_raw( $raw['image_url'] ?? '' ),
			'image_alt'       => sanitize_text_field( $raw['image_alt'] ?? '' ),
			'background_color'=> self::sanitize_hex( $raw['background_color'] ?? '' ),
			'content_html'    => wp_kses_post( $raw['content_html'] ?? '' ),
			'link_url'        => esc_url_raw( $raw['link_url'] ?? '' ),
			'link_target'     => self::pick( $raw['link_target'] ?? '_self', array( '_self', '_blank' ), '_self' ),
			'link_nofollow'   => ! empty( $raw['link_nofollow'] ) ? 1 : 0,
			'published'       => isset( $raw['published'] ) ? ( $raw['published'] ? 1 : 0 ) : 1,
		);

		return $slide;
	}

	/* ------------------------------------------------------------------ *
	 *  Small utilities
	 * ------------------------------------------------------------------ */

	public static function int_in_range( $value, $min, $max ) {
		$v = (int) $value;
		return max( $min, min( $max, $v ) );
	}

	public static function pick( $value, $allowed, $fallback ) {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	public static function sanitize_hex( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}
		if ( preg_match( '/^#?([a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/', $value, $m ) ) {
			return '#' . strtolower( $m[1] );
		}
		return '';
	}

	public static function sanitize_devices( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}
		$allowed = array( 'mobile', 'tablet', 'desktop' );
		$out     = array();
		foreach ( $values as $v ) {
			$v = sanitize_key( $v );
			if ( in_array( $v, $allowed, true ) && ! in_array( $v, $out, true ) ) {
				$out[] = $v;
			}
		}
		return $out;
	}

	/**
	 * Recursively merge defaults with saved values.
	 */
	public static function deep_merge( $defaults, $saved ) {
		$out = $defaults;
		foreach ( $saved as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && is_array( $value ) ) {
				$out[ $key ] = self::deep_merge( $defaults[ $key ], $value );
			} else {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}
}
