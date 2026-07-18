<?php
/**
 * Lexiata XSlider — Slide Manager
 *
 * Slide CRUD via AJAX. Modal is rendered once into the page (hidden via
 * .lex-modal class — display:none until .is-open is added). This avoids
 * any <template> cloning quirks that broke field updates.
 *
 * @package Lexiata_XSlider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lex_XSlider_Slide_Manager {

	const NONCE_ACTION = 'lex_xslider_slide_manager';

	public static function init() {
		add_action( 'wp_ajax_lex_xslider_save_slide',     array( __CLASS__, 'ajax_save_slide' ) );
		add_action( 'wp_ajax_lex_xslider_delete_slide',   array( __CLASS__, 'ajax_delete_slide' ) );
		add_action( 'wp_ajax_lex_xslider_duplicate_slide',array( __CLASS__, 'ajax_duplicate_slide' ) );
		add_action( 'wp_ajax_lex_xslider_reorder_slides', array( __CLASS__, 'ajax_reorder_slides' ) );
		add_action( 'wp_ajax_lex_xslider_get_slide',      array( __CLASS__, 'ajax_get_slide' ) );
	}

	private static function check_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'lexiata-xslider' ) ), 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, '_nonce' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_ajax_referer() above.
		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		if ( ! $project_id || get_post_type( $project_id ) !== LEX_XSLIDER_CPT ) {
			wp_send_json_error( array( 'message' => __( 'Invalid project.', 'lexiata-xslider' ) ), 400 );
		}
		return $project_id;
	}

	public static function ajax_save_slide() {
		$project_id = self::check_request(); // nonce + capability + project verified.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST['slide'] ) ? wp_unslash( $_POST['slide'] ) : array();
		// phpcs:enable
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid slide data.', 'lexiata-xslider' ) ), 400 );
		}

		$slide  = Lex_XSlider_Helpers::sanitize_slide( $raw );
		$slides = Lex_XSlider_Helpers::get_slides( $project_id );

		$updated = false;
		foreach ( $slides as $i => $existing ) {
			if ( $existing['id'] === $slide['id'] ) {
				$slides[ $i ] = $slide;
				$updated      = true;
				break;
			}
		}
		if ( ! $updated ) {
			$slides[] = $slide;
		}
		Lex_XSlider_Helpers::save_slides( $project_id, $slides );

		wp_send_json_success( array(
			'slide'      => $slide,
			'created'    => ! $updated,
			'thumbnail'  => self::render_slide_thumb_html( $slide, $project_id ),
			'count'      => count( $slides ),
		) );
	}

	public static function ajax_delete_slide() {
		$project_id = self::check_request(); // nonce + capability verified.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slide_id = isset( $_POST['slide_id'] ) ? sanitize_key( wp_unslash( $_POST['slide_id'] ) ) : '';
		if ( ! $slide_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing slide ID.', 'lexiata-xslider' ) ), 400 );
		}
		$kept = array();
		foreach ( Lex_XSlider_Helpers::get_slides( $project_id ) as $s ) {
			if ( $s['id'] !== $slide_id ) $kept[] = $s;
		}
		Lex_XSlider_Helpers::save_slides( $project_id, $kept );
		wp_send_json_success( array( 'count' => count( $kept ) ) );
	}

	public static function ajax_duplicate_slide() {
		$project_id = self::check_request(); // nonce + capability verified.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slide_id = isset( $_POST['slide_id'] ) ? sanitize_key( wp_unslash( $_POST['slide_id'] ) ) : '';
		if ( ! $slide_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing slide ID.', 'lexiata-xslider' ) ), 400 );
		}
		$out  = array();
		$copy = null;
		foreach ( Lex_XSlider_Helpers::get_slides( $project_id ) as $s ) {
			$out[] = $s;
			if ( $s['id'] === $slide_id ) {
				$copy           = $s;
				$copy['id']     = 'slide_' . wp_generate_uuid4();
				$copy['name']   = trim( $s['name'] . ' ' . __( '(copy)', 'lexiata-xslider' ) );
				$out[]          = $copy;
			}
		}
		Lex_XSlider_Helpers::save_slides( $project_id, $out );
		wp_send_json_success( array(
			'slide'     => $copy,
			'thumbnail' => $copy ? self::render_slide_thumb_html( $copy, $project_id ) : '',
		) );
	}

	public static function ajax_reorder_slides() {
		$project_id = self::check_request(); // nonce + capability verified.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array();
		// phpcs:enable
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'lexiata-xslider' ) ), 400 );
		}
		$ordered = array();
		foreach ( $raw as $v ) {
			if ( is_string( $v ) ) {
				$key = sanitize_key( $v );
				if ( $key ) $ordered[] = $key;
			}
		}

		$current = Lex_XSlider_Helpers::get_slides( $project_id );
		$by_id   = array();
		foreach ( $current as $s ) $by_id[ $s['id'] ] = $s;

		$reordered = array();
		foreach ( $ordered as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$reordered[] = $by_id[ $id ];
				unset( $by_id[ $id ] );
			}
		}
		foreach ( $by_id as $leftover ) $reordered[] = $leftover;

		Lex_XSlider_Helpers::save_slides( $project_id, $reordered );
		wp_send_json_success( array( 'count' => count( $reordered ) ) );
	}

	public static function ajax_get_slide() {
		$project_id = self::check_request(); // nonce + capability verified.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slide_id = isset( $_POST['slide_id'] ) ? sanitize_key( wp_unslash( $_POST['slide_id'] ) ) : '';
		foreach ( Lex_XSlider_Helpers::get_slides( $project_id ) as $s ) {
			if ( $s['id'] === $slide_id ) {
				wp_send_json_success( array( 'slide' => $s ) );
			}
		}
		wp_send_json_error( array( 'message' => __( 'Slide not found.', 'lexiata-xslider' ) ), 404 );
	}

	/* ------------------------------------------------------------------ *
	 *  Renderers
	 * ------------------------------------------------------------------ */

	public static function render_slide_thumb_html( $slide, $project_id ) {
		ob_start();
		self::render_slide_thumb( $slide, $project_id );
		return ob_get_clean();
	}

	public static function render_slide_thumb( $slide, $project_id ) {
		$has_image = ! empty( $slide['image_url'] );
		$has_link  = ! empty( $slide['link_url'] );
		$is_blank  = ( 'blank' === $slide['type'] );
		$is_off    = empty( $slide['published'] );
		$bg        = ! empty( $slide['background_color'] ) ? $slide['background_color'] : '';
		$name      = $slide['name'] !== '' ? $slide['name']
			: ( $is_blank ? __( 'Blank slide', 'lexiata-xslider' ) : __( 'Image slide', 'lexiata-xslider' ) );
		?>
		<div class="lex-slide"
		     data-slide-id="<?php echo esc_attr( $slide['id'] ); ?>"
		     <?php if ( $is_blank && $bg ) echo 'style="background-color:' . esc_attr( $bg ) . ';"'; ?>>

			<?php if ( $has_image ) : ?>
				<img src="<?php echo esc_url( $slide['image_url'] ); ?>" alt="" loading="lazy" />
			<?php elseif ( $is_blank ) : ?>
				<?php /* Background colour applied via inline style above */ ?>
				<div class="lex-slide-blank" <?php if ( $bg ) echo 'style="color:rgba(255,255,255,.6);"'; ?>>
					<?php echo $bg ? esc_html( strtoupper( $bg ) ) : esc_html__( 'Blank', 'lexiata-xslider' ); ?>
				</div>
			<?php else : ?>
				<div class="lex-slide-blank"><?php esc_html_e( 'No image', 'lexiata-xslider' ); ?></div>
			<?php endif; ?>

			<div class="lex-slide-badges">
				<?php if ( $is_blank ) : ?>
					<span class="lex-badge lex-badge-blank"><?php esc_html_e( 'BLANK', 'lexiata-xslider' ); ?></span>
				<?php endif; ?>
				<?php if ( $has_link ) : ?>
					<span class="lex-badge lex-badge-link" title="<?php echo esc_attr( $slide['link_url'] ); ?>">
						<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
						<?php esc_html_e( 'LINK', 'lexiata-xslider' ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $is_off ) : ?>
					<span class="lex-badge lex-badge-off"><?php esc_html_e( 'OFF', 'lexiata-xslider' ); ?></span>
				<?php endif; ?>
			</div>

			<button type="button" class="lex-slide-grip" aria-label="<?php esc_attr_e( 'Drag to reorder', 'lexiata-xslider' ); ?>" data-no-edit="1" tabindex="-1">
				<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
			</button>

			<div class="lex-slide-overlay">
				<span class="lex-slide-name"><?php echo esc_html( $name ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the slide-editor modal once into the editor page (hidden by default).
	 */
	public static function render_slide_editor_modal() {
		?>
		<div class="lex-modal lex-modal-lg lex-slide-editor" id="lex-slide-editor" role="dialog" aria-modal="true" aria-labelledby="lex-slide-editor-title" data-action="modal">
			<div class="lex-modal-box">

				<div class="lex-modal-head">
					<div>
						<h2 id="lex-slide-editor-title"><?php esc_html_e( 'Edit slide', 'lexiata-xslider' ); ?></h2>
						<p><?php esc_html_e( 'Configure the slide content, link, and visibility.', 'lexiata-xslider' ); ?></p>
					</div>
					<button type="button" class="lex-modal-close" data-action="close" aria-label="<?php esc_attr_e( 'Close', 'lexiata-xslider' ); ?>">×</button>
				</div>

				<div class="lex-modal-body">
					<div class="lex-slide-editor-grid">

						<!-- LEFT: Live preview + image picker -->
						<div class="lex-slide-preview">
							<div class="lex-preview-frame" data-bind="frame">
								<img data-bind="img" alt="" style="display:none" />
								<div class="lex-preview-empty" data-bind="empty">
									<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
										<rect x="3" y="5" width="18" height="14" rx="2"/>
										<circle cx="9" cy="11" r="1.4" fill="currentColor"/>
										<path d="M4 17l5-4 4 3 3-2 4 3" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
									<span><?php esc_html_e( 'No image', 'lexiata-xslider' ); ?></span>
								</div>
							</div>
							<div class="lex-preview-actions">
								<button type="button" class="lex-btn lex-btn-brand" data-action="select-image">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
									<span data-bind="select-label"><?php esc_html_e( 'Select image', 'lexiata-xslider' ); ?></span>
								</button>
								<button type="button" class="lex-btn lex-btn-ghost" data-action="remove-image" style="display:none">
									<?php esc_html_e( 'Remove image', 'lexiata-xslider' ); ?>
								</button>
							</div>
						</div>

						<!-- RIGHT: Fields -->
						<div class="lex-slide-fields">

							<input type="hidden" data-field="id"        value="" />
							<input type="hidden" data-field="type"      value="image" />
							<input type="hidden" data-field="image_id"  value="0" />
							<input type="hidden" data-field="image_url" value="" />

							<!-- Basic -->
							<div class="lex-section">
								<div class="lex-section-label">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
									<?php esc_html_e( 'Basic', 'lexiata-xslider' ); ?>
								</div>
								<div class="lex-row">
									<div class="lex-field">
										<label class="lex-field-label"><?php esc_html_e( 'Slide name', 'lexiata-xslider' ); ?></label>
										<input type="text" data-field="name" placeholder="<?php esc_attr_e( 'Internal name (optional)', 'lexiata-xslider' ); ?>" />
									</div>
									<div class="lex-field">
										<label class="lex-field-label"><?php esc_html_e( 'Image alt text', 'lexiata-xslider' ); ?></label>
										<input type="text" data-field="image_alt" placeholder="<?php esc_attr_e( 'Describe the image (SEO)', 'lexiata-xslider' ); ?>" />
									</div>
								</div>
							</div>

							<!-- Background colour -->
							<div class="lex-section">
								<div class="lex-section-label">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2a10 10 0 0 0 0 20 4 4 0 0 0 0-8 2 2 0 0 1 0-4 6 6 0 0 0 0-8z"/></svg>
									<?php esc_html_e( 'Background colour', 'lexiata-xslider' ); ?>
								</div>
								<div class="lex-row">
									<div class="lex-field lex-field-md">
										<label class="lex-field-label"><?php esc_html_e( 'Colour', 'lexiata-xslider' ); ?></label>
										<div class="lex-color">
											<input type="color" class="lex-color-swatch" data-field="bg_picker" value="#000000" />
											<input type="text"  class="lex-color-text"   data-field="background_color" value="" placeholder="#000000" />
										</div>
									</div>
									<div class="lex-field" style="flex: 0 0 auto;">
										<label class="lex-field-label">&nbsp;</label>
										<button type="button" class="lex-btn lex-btn-sm" data-action="bg-clear"><?php esc_html_e( 'Clear', 'lexiata-xslider' ); ?></button>
									</div>
								</div>
								<span class="lex-help"><?php esc_html_e( 'Used for blank slides or as a fallback while the image loads.', 'lexiata-xslider' ); ?></span>
							</div>

							<!-- Click action -->
							<div class="lex-section">
								<div class="lex-section-label">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
									<?php esc_html_e( 'Click action (URL link)', 'lexiata-xslider' ); ?>
								</div>
								<div class="lex-row">
									<div class="lex-field">
										<label class="lex-field-label"><?php esc_html_e( 'URL', 'lexiata-xslider' ); ?></label>
										<input type="url" data-field="link_url" placeholder="https://example.com/landing-page" />
									</div>
								</div>
								<div class="lex-row">
									<div class="lex-field lex-field-sm">
										<label class="lex-field-label"><?php esc_html_e( 'Open in', 'lexiata-xslider' ); ?></label>
										<select data-field="link_target">
											<option value="_self"><?php esc_html_e( 'Same tab', 'lexiata-xslider' ); ?></option>
											<option value="_blank"><?php esc_html_e( 'New tab', 'lexiata-xslider' ); ?></option>
										</select>
									</div>
									<div class="lex-toggle-field">
										<label class="lex-field-label"><?php esc_html_e( 'No-follow', 'lexiata-xslider' ); ?></label>
										<label class="lex-toggle">
											<input type="checkbox" data-field="link_nofollow" value="1" />
											<span class="lex-track"></span>
											<span class="lex-toggle-state"></span>
										</label>
									</div>
								</div>
								<span class="lex-help"><?php esc_html_e( 'When set, clicking the slide opens this URL. Leave empty for non-clickable slides.', 'lexiata-xslider' ); ?></span>
							</div>

							<!-- Visibility -->
							<div class="lex-section">
								<div class="lex-section-label">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
									<?php esc_html_e( 'Visibility', 'lexiata-xslider' ); ?>
								</div>
								<div class="lex-row">
									<div class="lex-toggle-field">
										<label class="lex-field-label"><?php esc_html_e( 'Published', 'lexiata-xslider' ); ?></label>
										<label class="lex-toggle">
											<input type="checkbox" data-field="published" value="1" checked />
											<span class="lex-track"></span>
											<span class="lex-toggle-state"></span>
										</label>
									</div>
								</div>
								<span class="lex-help"><?php esc_html_e( 'Unpublished slides are hidden on the front-end but kept in the editor.', 'lexiata-xslider' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<div class="lex-modal-foot">
					<button type="button" class="lex-btn lex-btn-danger lex-modal-foot-pull" data-action="delete" style="display:none">
						<?php esc_html_e( 'Delete', 'lexiata-xslider' ); ?>
					</button>
					<button type="button" class="lex-btn" data-action="duplicate" style="display:none">
						<?php esc_html_e( 'Duplicate', 'lexiata-xslider' ); ?>
					</button>
					<button type="button" class="lex-btn" data-action="cancel">
						<?php esc_html_e( 'Cancel', 'lexiata-xslider' ); ?>
					</button>
					<button type="button" class="lex-btn lex-btn-brand" data-action="save">
						<?php esc_html_e( 'Save slide', 'lexiata-xslider' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}
}
