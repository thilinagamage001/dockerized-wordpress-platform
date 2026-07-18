<?php
/**
 * Lexiata XSlider — Frontend
 *
 * Registers the [lex_xslider id="X"] shortcode and renders sliders.
 *
 * @package Lexiata_XSlider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lex_XSlider_Frontend {

	/** @var int[] IDs of sliders rendered in the current request (used to enqueue assets only when needed). */
	private static $rendered_ids = array();

	/** @var int Counter to make wrapper IDs unique even if the same shortcode appears twice on a page. */
	private static $instance_counter = 0;

	public static function init() {
		add_shortcode( 'lex_xslider', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Shortcode entry: [lex_xslider id="X"]
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'lex_xslider' );

		$id = absint( $atts['id'] );
		if ( ! $id ) return '';

		if ( get_post_type( $id ) !== LEX_XSLIDER_CPT ) return '';

		$post = get_post( $id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			// Allow non-published in preview for admins (so editing works pre-publish).
			if ( ! current_user_can( 'manage_options' ) ) return '';
		}

		// Enqueue frontend assets on demand.
		self::enqueue_assets();

		return self::render_slider( $id );
	}

	private static function enqueue_assets() {
		if ( ! wp_style_is( 'lex-xslider-frontend', 'enqueued' ) ) {
			wp_enqueue_style(
				'lex-xslider-frontend',
				LEX_XSLIDER_URL . 'assets/frontend/lex-xslider.css',
				array(),
				LEX_XSLIDER_VERSION
			);
		}
		if ( ! wp_script_is( 'lex-xslider-frontend', 'enqueued' ) ) {
			wp_enqueue_script(
				'lex-xslider-frontend',
				LEX_XSLIDER_URL . 'assets/frontend/lex-xslider.js',
				array(),
				LEX_XSLIDER_VERSION,
				true
			);
		}
	}

	/**
	 * Render a slider's full HTML.
	 */
	public static function render_slider( $id ) {
		$settings = Lex_XSlider_Helpers::get_settings( $id );
		$slides   = Lex_XSlider_Helpers::get_slides( $id );

		// Filter out unpublished
		$published = array();
		foreach ( $slides as $s ) {
			if ( ! empty( $s['published'] ) ) $published[] = $s;
		}
		if ( empty( $published ) ) return '';

		self::$rendered_ids[ $id ] = true;
		self::$instance_counter++;
		$instance_id = $id . '-' . self::$instance_counter;

		// Build classes
		$classes = array(
			'lex-xs',
			'lex-xs-' . $id,
			'lex-xs-anim-' . sanitize_html_class( $settings['animations']['main_animation'] ),
			'lex-xs-fill-' . sanitize_html_class( $settings['slides']['bg_fill'] ),
			'lex-xs-layout-' . sanitize_html_class( $settings['size']['layout'] ),
			'lex-xs-align-' . sanitize_html_class( $settings['general']['align'] ),
			'lex-xs-arrow-' . sanitize_html_class( $settings['controls']['arrow_style'] ),
			'lex-xs-bullet-' . sanitize_html_class( $settings['controls']['bullet_style'] ),
		);
		if ( ! empty( $settings['developer']['css_classes'] ) ) {
			foreach ( explode( ' ', $settings['developer']['css_classes'] ) as $c ) {
				$c = sanitize_html_class( trim( $c ) );
				if ( $c ) $classes[] = $c;
			}
		}

		// Hide-on-device classes for arrows
		foreach ( (array) $settings['controls']['arrow_hide_on'] as $dev ) {
			$classes[] = 'lex-xs-hide-arrow-' . sanitize_html_class( $dev );
		}

		// Inline wrapper sizing
		$wrap_style = '';
		if ( 'boxed' === $settings['size']['layout'] ) {
			$wrap_style .= 'max-width:' . (int) $settings['size']['width'] . 'px;';
		}
		$wrap_style .= 'aspect-ratio:' . (int) $settings['size']['width'] . '/' . max( 1, (int) $settings['size']['height'] ) . ';';
		if ( ! empty( $settings['size']['min_height'] ) ) {
			$wrap_style .= 'min-height:' . (int) $settings['size']['min_height'] . 'px;';
		}
		if ( 'normal' !== $settings['general']['align'] ) {
			if ( 'center' === $settings['general']['align'] ) $wrap_style .= 'margin-left:auto;margin-right:auto;';
			if ( 'left'   === $settings['general']['align'] ) $wrap_style .= 'margin-right:auto;';
			if ( 'right'  === $settings['general']['align'] ) $wrap_style .= 'margin-left:auto;';
		}
		if ( ! empty( $settings['general']['margin'] ) ) {
			$wrap_style .= 'margin-top:' . (int) $settings['general']['margin'] . 'px;margin-bottom:' . (int) $settings['general']['margin'] . 'px;';
		}
		if ( ! empty( $settings['size']['border_radius'] ) ) {
			$wrap_style .= 'border-radius:' . (int) $settings['size']['border_radius'] . 'px;';
		}

		// Config blob for JS
		$config = array(
			'duration'      => (int) $settings['animations']['duration'],
			'animation'     => $settings['animations']['main_animation'],
			'autoplay'      => ! empty( $settings['autoplay']['enabled'] ),
			'slideDuration' => (int) $settings['autoplay']['slide_duration'],
			'stopOnClick'   => ! empty( $settings['autoplay']['stop_on_click'] ),
			'stopOnHover'   => ! empty( $settings['autoplay']['stop_on_hover'] ),
			'resumeOnLeave' => ! empty( $settings['autoplay']['resume_on_leave'] ),
			'drag'          => $settings['controls']['drag'],
			'keyboard'      => ! empty( $settings['controls']['keyboard'] ),
			'arrowEnabled'  => ! empty( $settings['controls']['arrow_enabled'] ),
			'bulletEnabled' => ! empty( $settings['controls']['bullet_enabled'] ),
			'lazy'          => 'lazy' === $settings['optimize']['loading_type'],
			'lazyThreshold' => (int) $settings['optimize']['lazy_threshold'],
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		     id="lex-xs-<?php echo esc_attr( $instance_id ); ?>"
		     style="<?php echo esc_attr( $wrap_style ); ?>"
		     data-lex-xs="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
		     data-drag="<?php echo esc_attr( $settings['controls']['drag'] ); ?>"
		     aria-roledescription="<?php esc_attr_e( 'carousel', 'lexiata-xslider' ); ?>"
		     aria-label="<?php echo esc_attr( $settings['general']['name'] ?: __( 'Slider', 'lexiata-xslider' ) ); ?>">

			<div class="lex-xs-track" role="presentation">
				<?php foreach ( $published as $index => $slide ) self::render_slide( $slide, $index, count( $published ), $settings ); ?>
			</div>

			<?php if ( ! empty( $settings['controls']['arrow_enabled'] ) && count( $published ) > 1 ) : ?>
				<button type="button" class="lex-xs-arrow lex-xs-arrow-prev lex-xs-arrow-<?php echo esc_attr( $settings['controls']['arrow_position'] ); ?>"
				        style="<?php echo esc_attr( self::arrow_style( $settings ) ); ?>"
				        aria-label="<?php esc_attr_e( 'Previous slide', 'lexiata-xslider' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
				</button>
				<button type="button" class="lex-xs-arrow lex-xs-arrow-next lex-xs-arrow-<?php echo esc_attr( $settings['controls']['arrow_position'] ); ?>"
				        style="<?php echo esc_attr( self::arrow_style( $settings ) ); ?>"
				        aria-label="<?php esc_attr_e( 'Next slide', 'lexiata-xslider' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
				</button>
			<?php endif; ?>

			<?php if ( ! empty( $settings['controls']['bullet_enabled'] ) && count( $published ) > 1 ) : ?>
				<div class="lex-xs-bullets lex-xs-bullets-<?php echo esc_attr( $settings['controls']['bullet_position'] ); ?>"
				     role="tablist"
				     aria-label="<?php esc_attr_e( 'Slides', 'lexiata-xslider' ); ?>"
				     style="<?php echo esc_attr( '--lex-xs-bullet-color:' . ( $settings['controls']['bullet_color'] ?: '#fff' ) ); ?>">
					<?php for ( $i = 0; $i < count( $published ); $i++ ) : ?>
						<button type="button" class="lex-xs-bullet" role="tab" aria-label="<?php
							/* translators: %d: slide number */
							echo esc_attr( sprintf( __( 'Go to slide %d', 'lexiata-xslider' ), $i + 1 ) );
						?>" data-go="<?php echo (int) $i; ?>"></button>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function arrow_style( $settings ) {
		return '--lex-xs-arrow-color:' . ( $settings['controls']['arrow_color'] ?: '#fff' ) . ';'
		     . '--lex-xs-arrow-size:' . (int) $settings['controls']['arrow_size'] . 'px;';
	}

	private static function render_slide( $slide, $index, $total, $settings ) {
		$has_image = ! empty( $slide['image_url'] );
		$has_link  = ! empty( $slide['link_url'] );
		$bg        = ! empty( $slide['background_color'] ) ? $slide['background_color'] : '';

		$slide_style = '';
		if ( $bg ) {
			$slide_style .= 'background-color:' . esc_attr( $bg ) . ';';
		}

		$inner = '';
		if ( $has_image ) {
			$is_first = ( 0 === $index );
			$loading  = ( $is_first && 'lazy' !== $settings['optimize']['loading_type'] ) ? 'eager' : 'lazy';
			$priority = ( $is_first && 'lazy' !== $settings['optimize']['loading_type'] ) ? ' fetchpriority="high"' : '';
			$inner = sprintf(
				'<img class="lex-xs-img" src="%s" alt="%s" loading="%s" decoding="async" draggable="false"%s />',
				esc_url( $slide['image_url'] ),
				esc_attr( $slide['image_alt'] ),
				esc_attr( $loading ),
				$priority
			);
		}

		// Build link wrapper if needed
		$link_attrs = '';
		if ( $has_link ) {
			$rel  = 'noopener';
			if ( ! empty( $slide['link_nofollow'] ) ) $rel .= ' nofollow';
			if ( '_blank' === $slide['link_target'] ) $rel .= ' noreferrer';

			$link_attrs = sprintf(
				' href="%s" target="%s" rel="%s" draggable="false"',
				esc_url( $slide['link_url'] ),
				esc_attr( $slide['link_target'] ),
				esc_attr( $rel )
			);
		}

		?>
		<div class="lex-xs-slide" role="group" aria-roledescription="slide" aria-label="<?php
			/* translators: 1: current slide number, 2: total slides */
			echo esc_attr( sprintf( __( '%1$d of %2$d', 'lexiata-xslider' ), $index + 1, $total ) );
		?>" data-index="<?php echo (int) $index; ?>" style="<?php echo esc_attr( $slide_style ); ?>">
			<?php if ( $has_link ) : ?>
				<a class="lex-xs-link"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above ?>>
					<?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above ?>
				</a>
			<?php else : ?>
				<?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
