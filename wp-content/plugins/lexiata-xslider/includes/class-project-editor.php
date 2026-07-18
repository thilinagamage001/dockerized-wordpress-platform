<?php
/**
 * Lexiata XSlider — Project Editor
 *
 * Renders the 8-tab editor for a single slider project.
 *
 * @package Lexiata_XSlider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lex_XSlider_Project_Editor {

	/**
	 * Tab definitions.
	 *
	 * Implemented as a method (not a const) so labels can be passed through __()
	 * with string literals — PHP class constants don't allow function calls.
	 *
	 * @return array key => translated label.
	 */
	public static function get_tabs() {
		return array(
			'general'    => __( 'General',    'lexiata-xslider' ),
			'size'       => __( 'Size',       'lexiata-xslider' ),
			'controls'   => __( 'Controls',   'lexiata-xslider' ),
			'animations' => __( 'Animations', 'lexiata-xslider' ),
			'autoplay'   => __( 'Autoplay',   'lexiata-xslider' ),
			'optimize'   => __( 'Optimize',   'lexiata-xslider' ),
			'slides'     => __( 'Slides',     'lexiata-xslider' ),
			'developer'  => __( 'Developer',  'lexiata-xslider' ),
		);
	}

	public static function init() {
		add_action( 'admin_post_lex_xslider_save_settings', array( __CLASS__, 'handle_save_settings' ) );
	}

	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing
		$project_id = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
		$project    = $project_id ? get_post( $project_id ) : null;

		if ( ! $project || LEX_XSLIDER_CPT !== $project->post_type ) {
			echo '<div class="lex-empty"><p>' . esc_html__( 'Project not found.', 'lexiata-xslider' ) . '</p></div>';
			return;
		}

		$settings   = Lex_XSlider_Helpers::get_settings( $project_id );
		$slides     = Lex_XSlider_Helpers::get_slides( $project_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs       = self::get_tabs();
		if ( ! array_key_exists( $active_tab, $tabs ) ) $active_tab = 'general';

		$dashboard_url = admin_url( 'admin.php?page=' . Lex_XSlider_Admin::MENU_SLUG );
		$action_url    = admin_url( 'admin-post.php' );

		// Flash notice
		$notice = get_transient( 'lex_xslider_notice' );
		if ( $notice && ! empty( $notice['msg'] ) ) {
			delete_transient( 'lex_xslider_notice' );
			$type = ( isset( $notice['type'] ) && 'error' === $notice['type'] ) ? 'error' : 'success';
			echo '<div class="lex-notice lex-notice-' . esc_attr( $type ) . '">' . esc_html( $notice['msg'] ) . '</div>';
		}
		?>

		<!-- Breadcrumb -->
		<div class="lex-crumb">
			<a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Dashboard', 'lexiata-xslider' ); ?></a>
			<span class="lex-crumb-sep">/</span>
			<span class="lex-crumb-current"><?php echo esc_html( $project->post_title ); ?></span>
		</div>

		<!-- Slide strip -->
		<div class="lex-strip-wrap" data-project-id="<?php echo (int) $project_id; ?>">
			<div class="lex-strip" id="lex-strip">
				<button type="button" class="lex-add-slide" data-action="open-type-picker" aria-haspopup="dialog">
					<span class="lex-add-slide-icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					</span>
					<span><?php esc_html_e( 'ADD SLIDE', 'lexiata-xslider' ); ?></span>
				</button>

				<?php if ( empty( $slides ) ) : ?>
					<div class="lex-strip-empty" data-bind="empty"><?php esc_html_e( 'Click ADD SLIDE to create your first slide.', 'lexiata-xslider' ); ?></div>
				<?php else : ?>
					<?php foreach ( $slides as $slide ) Lex_XSlider_Slide_Manager::render_slide_thumb( $slide, $project_id ); ?>
				<?php endif; ?>
			</div>

			<div class="lex-strip-hint" data-bind="strip-hint" <?php echo count( $slides ) > 1 ? '' : 'style="display:none"'; ?>>
				<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>
				<?php esc_html_e( 'Drag the grip handle on each slide to reorder. Click any slide to edit it.', 'lexiata-xslider' ); ?>
			</div>

			<!-- Type picker overlay -->
			<div class="lex-type-picker" id="lex-type-picker" role="dialog" aria-label="<?php esc_attr_e( 'Choose slide type', 'lexiata-xslider' ); ?>">
				<button type="button" class="lex-type-card lex-type-card-image" data-action="add-slide" data-type="image">
					<span class="lex-type-card-icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
					</span>
					<span><?php esc_html_e( 'Image', 'lexiata-xslider' ); ?></span>
				</button>
				<button type="button" class="lex-type-card lex-type-card-blank" data-action="add-slide" data-type="blank">
					<span class="lex-type-card-icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					</span>
					<span><?php esc_html_e( 'Blank', 'lexiata-xslider' ); ?></span>
				</button>
				<button type="button" class="lex-type-card lex-type-card-close" data-action="close-type-picker">
					<span class="lex-type-card-icon" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</span>
					<span><?php esc_html_e( 'CLOSE', 'lexiata-xslider' ); ?></span>
				</button>
			</div>
		</div>

		<?php Lex_XSlider_Slide_Manager::render_slide_editor_modal(); ?>

		<!-- Toolbar -->
		<div class="lex-toolbar">
			<div class="lex-toolbar-title">
				<h1><?php echo esc_html( $project->post_title ); ?></h1>
				<span class="lex-id-pill">ID: <?php echo (int) $project_id; ?></span>
			</div>
			<div class="lex-toolbar-actions">
				<a class="lex-btn" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Back', 'lexiata-xslider' ); ?></a>
				<button type="submit" form="lex-settings-form" class="lex-btn lex-btn-primary">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
					<?php esc_html_e( 'Save', 'lexiata-xslider' ); ?>
				</button>
			</div>
		</div>

		<!-- Tabs -->
		<nav class="lex-tabs" role="tablist">
			<?php foreach ( $tabs as $key => $label ) :
				$tab_url = add_query_arg( array(
					'page'       => Lex_XSlider_Admin::MENU_SLUG,
					'view'       => 'editor',
					'project_id' => $project_id,
					'tab'        => $key,
				), admin_url( 'admin.php' ) );
				$is_on = ( $active_tab === $key );
			?>
				<a class="lex-tab<?php echo $is_on ? ' is-on' : ''; ?>"
				   href="<?php echo esc_url( $tab_url ); ?>"
				   role="tab"
				   aria-selected="<?php echo $is_on ? 'true' : 'false'; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<!-- Settings form -->
		<form id="lex-settings-form" method="post" action="<?php echo esc_url( $action_url ); ?>" class="lex-form">
			<?php wp_nonce_field( 'lex_xslider_save_settings_' . $project_id, '_lex_nonce' ); ?>
			<input type="hidden" name="action" value="lex_xslider_save_settings" />
			<input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>" />
			<input type="hidden" name="active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />

			<?php
			$method = 'tab_' . $active_tab;
			if ( method_exists( __CLASS__, $method ) ) {
				self::$method( $settings, $project_id );
			}
			?>
		</form>
		<?php
	}

	/* ================================================================== *
	 *  TAB: General
	 * ================================================================== */
	private static function tab_general( $settings, $project_id ) {
		$g = $settings['general'];
		?>
		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
				<?php esc_html_e( 'Publish', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-publish">
				<div class="lex-publish-cell">
					<h3><?php esc_html_e( 'Shortcode', 'lexiata-xslider' ); ?></h3>
					<p><?php esc_html_e( 'Click to copy. Paste into any post or page.', 'lexiata-xslider' ); ?></p>
					<code class="lex-code">[lex_xslider id="<?php echo (int) $project_id; ?>"]</code>
				</div>
				<div class="lex-publish-cell">
					<h3><?php esc_html_e( 'Page builders', 'lexiata-xslider' ); ?></h3>
					<p><?php esc_html_e( 'Works in Gutenberg, Classic Editor, Elementor, Divi, and any builder that accepts shortcodes.', 'lexiata-xslider' ); ?></p>
				</div>
				<div class="lex-publish-cell">
					<h3><?php esc_html_e( 'PHP code', 'lexiata-xslider' ); ?></h3>
					<p><?php esc_html_e( 'Paste into your theme template file.', 'lexiata-xslider' ); ?></p>
					<code class="lex-code">&lt;?php echo do_shortcode('[lex_xslider id="<?php echo (int) $project_id; ?>"]'); ?&gt;</code>
				</div>
			</div>
		</div>

		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
				<?php esc_html_e( 'General', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field">
					<label class="lex-field-label"><?php esc_html_e( 'Name', 'lexiata-xslider' ); ?></label>
					<input type="text" name="settings[general][name]" value="<?php echo esc_attr( $g['name'] ); ?>" />
				</div>
				<div class="lex-field">
					<label class="lex-field-label"><?php esc_html_e( 'Alias', 'lexiata-xslider' ); ?></label>
					<input type="text" name="settings[general][alias]" value="<?php echo esc_attr( $g['alias'] ); ?>" placeholder="hero-slider" />
				</div>
			</div>
		</div>

		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 9 6 9 12 3 12 3 6"/><polyline points="21 6 15 6 15 12 21 12 21 6"/></svg>
				<?php esc_html_e( 'Slider design', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Align', 'lexiata-xslider' ); ?></label>
					<select name="settings[general][align]">
						<?php foreach ( array(
							'normal' => __( 'Normal', 'lexiata-xslider' ),
							'left'   => __( 'Left', 'lexiata-xslider' ),
							'center' => __( 'Center', 'lexiata-xslider' ),
							'right'  => __( 'Right', 'lexiata-xslider' ),
						) as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $g['align'], $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Margin', 'lexiata-xslider' ); ?></label>
					<div class="lex-suffix">
						<input type="number" name="settings[general][margin]" value="<?php echo (int) $g['margin']; ?>" min="0" max="200" />
						<span class="lex-suffix-unit">PX</span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ================================================================== *
	 *  TAB: Size
	 * ================================================================== */
	private static function tab_size( $settings, $project_id ) {
		$s = $settings['size'];
		?>
		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
				<?php esc_html_e( 'Size', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Width', 'lexiata-xslider' ); ?></label>
					<div class="lex-suffix">
						<input type="number" name="settings[size][width]" value="<?php echo (int) $s['width']; ?>" min="100" max="5000" />
						<span class="lex-suffix-unit">PX</span>
					</div>
				</div>
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Height', 'lexiata-xslider' ); ?></label>
					<div class="lex-suffix">
						<input type="number" name="settings[size][height]" value="<?php echo (int) $s['height']; ?>" min="50" max="5000" />
						<span class="lex-suffix-unit">PX</span>
					</div>
				</div>
				<?php self::toggle( 'settings[size][limit_slide_width]', $s['limit_slide_width'], __( 'Limit slide width', 'lexiata-xslider' ) ); ?>
			</div>
		</div>

		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
				<?php esc_html_e( 'Layout', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-tiles">
				<label class="lex-tile <?php echo 'boxed' === $s['layout'] ? 'is-on' : ''; ?>">
					<input type="radio" name="settings[size][layout]" value="boxed" <?php checked( $s['layout'], 'boxed' ); ?> />
					<svg width="40" height="28" viewBox="0 0 40 28" fill="none"><rect x="6" y="4" width="28" height="20" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
					<span class="lex-tile-label"><?php esc_html_e( 'Boxed', 'lexiata-xslider' ); ?></span>
				</label>
				<label class="lex-tile <?php echo 'full_width' === $s['layout'] ? 'is-on' : ''; ?>">
					<input type="radio" name="settings[size][layout]" value="full_width" <?php checked( $s['layout'], 'full_width' ); ?> />
					<svg width="40" height="28" viewBox="0 0 40 28" fill="none"><rect x="2" y="4" width="36" height="20" rx="2" stroke="currentColor" stroke-width="1.6" fill="none"/></svg>
					<span class="lex-tile-label"><?php esc_html_e( 'Full width', 'lexiata-xslider' ); ?></span>
				</label>
			</div>

			<div class="lex-row">
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Min height', 'lexiata-xslider' ); ?></label>
					<div class="lex-suffix">
						<input type="number" name="settings[size][min_height]" value="<?php echo (int) $s['min_height']; ?>" min="0" max="2000" />
						<span class="lex-suffix-unit">PX</span>
					</div>
				</div>
				<?php self::toggle( 'settings[size][force_full_width]', $s['force_full_width'], __( 'Force full width', 'lexiata-xslider' ) ); ?>
			</div>

			<div class="lex-row">
				<div class="lex-field lex-field-md">
					<label class="lex-field-label"><?php esc_html_e( 'Border radius', 'lexiata-xslider' ); ?></label>
					<div class="lex-radius-row">
						<input type="range" class="lex-radius-slider" name="settings[size][border_radius]" id="lex-radius-slider" value="<?php echo (int) $s['border_radius']; ?>" min="0" max="100" step="1" />
						<div class="lex-suffix lex-radius-input">
							<input type="number" class="lex-radius-number" id="lex-radius-number" value="<?php echo (int) $s['border_radius']; ?>" min="0" max="100" step="1" />
							<span class="lex-suffix-unit">PX</span>
						</div>
					</div>
					<div class="lex-radius-presets" aria-label="<?php esc_attr_e( 'Border radius presets', 'lexiata-xslider' ); ?>">
						<button type="button" class="lex-radius-chip" data-radius="0"><?php esc_html_e( 'Sharp', 'lexiata-xslider' ); ?></button>
						<button type="button" class="lex-radius-chip" data-radius="6"><?php esc_html_e( 'Subtle', 'lexiata-xslider' ); ?></button>
						<button type="button" class="lex-radius-chip" data-radius="14"><?php esc_html_e( 'Soft', 'lexiata-xslider' ); ?></button>
						<button type="button" class="lex-radius-chip" data-radius="24"><?php esc_html_e( 'Rounded', 'lexiata-xslider' ); ?></button>
						<button type="button" class="lex-radius-chip" data-radius="48"><?php esc_html_e( 'Pill', 'lexiata-xslider' ); ?></button>
					</div>
					<span class="lex-help"><?php esc_html_e( 'Rounds the slider corners on the front-end. Set 0 for sharp corners.', 'lexiata-xslider' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/* ================================================================== *
	 *  TAB: Controls
	 * ================================================================== */
	private static function tab_controls( $settings, $project_id ) {
		$c = $settings['controls'];
		?>
		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9 9h6v6H9z"/></svg>
				<?php esc_html_e( 'General', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-md">
					<label class="lex-field-label"><?php esc_html_e( 'Touch & mouse swipe', 'lexiata-xslider' ); ?></label>
					<select name="settings[controls][drag]">
						<option value="horizontal" <?php selected( $c['drag'], 'horizontal' ); ?>><?php esc_html_e( 'Enabled (swipe to navigate)', 'lexiata-xslider' ); ?></option>
						<option value="disabled"   <?php selected( $c['drag'], 'disabled'   ); ?>><?php esc_html_e( 'Disabled', 'lexiata-xslider' ); ?></option>
					</select>
					<span class="lex-help"><?php esc_html_e( 'Lets visitors slide between slides by swiping (touch) or click-dragging (mouse).', 'lexiata-xslider' ); ?></span>
				</div>
				<?php self::toggle( 'settings[controls][keyboard]', $c['keyboard'], __( 'Keyboard arrows', 'lexiata-xslider' ) ); ?>
			</div>
		</div>

		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
				<?php esc_html_e( 'Arrows', 'lexiata-xslider' ); ?>
				<?php self::toggle_inline( 'settings[controls][arrow_enabled]', $c['arrow_enabled'] ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Style', 'lexiata-xslider' ); ?></label>
					<select name="settings[controls][arrow_style]">
						<option value="default" <?php selected( $c['arrow_style'], 'default' ); ?>><?php esc_html_e( 'Default', 'lexiata-xslider' ); ?></option>
						<option value="circle"  <?php selected( $c['arrow_style'], 'circle' );  ?>><?php esc_html_e( 'Circle', 'lexiata-xslider' ); ?></option>
						<option value="square"  <?php selected( $c['arrow_style'], 'square' );  ?>><?php esc_html_e( 'Square', 'lexiata-xslider' ); ?></option>
						<option value="minimal" <?php selected( $c['arrow_style'], 'minimal' ); ?>><?php esc_html_e( 'Minimal', 'lexiata-xslider' ); ?></option>
					</select>
				</div>
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Colour', 'lexiata-xslider' ); ?></label>
					<input type="text" name="settings[controls][arrow_color]" value="<?php echo esc_attr( $c['arrow_color'] ); ?>" placeholder="#ffffff" />
				</div>
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Position', 'lexiata-xslider' ); ?></label>
					<select name="settings[controls][arrow_position]">
						<option value="middle" <?php selected( $c['arrow_position'], 'middle' ); ?>><?php esc_html_e( 'Middle', 'lexiata-xslider' ); ?></option>
						<option value="bottom" <?php selected( $c['arrow_position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'lexiata-xslider' ); ?></option>
					</select>
				</div>
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Size', 'lexiata-xslider' ); ?></label>
					<div class="lex-suffix">
						<input type="number" name="settings[controls][arrow_size]" value="<?php echo (int) $c['arrow_size']; ?>" min="12" max="96" />
						<span class="lex-suffix-unit">PX</span>
					</div>
				</div>
			</div>
			<div class="lex-row">
				<fieldset class="lex-checks">
					<legend><?php esc_html_e( 'Hide on', 'lexiata-xslider' ); ?></legend>
					<?php foreach ( array(
						'mobile'  => __( 'Mobile', 'lexiata-xslider' ),
						'tablet'  => __( 'Tablet', 'lexiata-xslider' ),
						'desktop' => __( 'Desktop', 'lexiata-xslider' ),
					) as $val => $label ) : ?>
						<label class="lex-check">
							<input type="checkbox" name="settings[controls][arrow_hide_on][]" value="<?php echo esc_attr( $val ); ?>" <?php checked( in_array( $val, $c['arrow_hide_on'], true ) ); ?> />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</div>
		</div>

		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="6" cy="12" r="2.5" fill="currentColor"/><circle cx="12" cy="12" r="2.5" fill="currentColor"/><circle cx="18" cy="12" r="2.5" fill="currentColor"/></svg>
				<?php esc_html_e( 'Bullets', 'lexiata-xslider' ); ?>
				<?php self::toggle_inline( 'settings[controls][bullet_enabled]', $c['bullet_enabled'] ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Style', 'lexiata-xslider' ); ?></label>
					<select name="settings[controls][bullet_style]">
						<option value="dot"    <?php selected( $c['bullet_style'], 'dot' );    ?>><?php esc_html_e( 'Dot', 'lexiata-xslider' ); ?></option>
						<option value="bar"    <?php selected( $c['bullet_style'], 'bar' );    ?>><?php esc_html_e( 'Bar', 'lexiata-xslider' ); ?></option>
						<option value="pill"   <?php selected( $c['bullet_style'], 'pill' );   ?>><?php esc_html_e( 'Pill', 'lexiata-xslider' ); ?></option>
						<option value="square" <?php selected( $c['bullet_style'], 'square' ); ?>><?php esc_html_e( 'Square', 'lexiata-xslider' ); ?></option>
					</select>
				</div>
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Colour', 'lexiata-xslider' ); ?></label>
					<input type="text" name="settings[controls][bullet_color]" value="<?php echo esc_attr( $c['bullet_color'] ); ?>" placeholder="#ffffff" />
				</div>
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Position', 'lexiata-xslider' ); ?></label>
					<select name="settings[controls][bullet_position]">
						<option value="bottom" <?php selected( $c['bullet_position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'lexiata-xslider' ); ?></option>
						<option value="top"    <?php selected( $c['bullet_position'], 'top' );    ?>><?php esc_html_e( 'Top', 'lexiata-xslider' ); ?></option>
					</select>
				</div>
			</div>
		</div>
		<?php
	}

	private static function tab_animations( $settings, $project_id ) {
		$a = $settings['animations'];
		?>
		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
				<?php esc_html_e( 'Main animation', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Type', 'lexiata-xslider' ); ?></label>
					<select name="settings[animations][main_animation]">
						<option value="horizontal" <?php selected( $a['main_animation'], 'horizontal' ); ?>><?php esc_html_e( 'Horizontal', 'lexiata-xslider' ); ?></option>
						<option value="vertical"   <?php selected( $a['main_animation'], 'vertical' );   ?>><?php esc_html_e( 'Vertical', 'lexiata-xslider' ); ?></option>
						<option value="fade"       <?php selected( $a['main_animation'], 'fade' );       ?>><?php esc_html_e( 'Fade', 'lexiata-xslider' ); ?></option>
						<option value="zoom"       <?php selected( $a['main_animation'], 'zoom' );       ?>><?php esc_html_e( 'Zoom fade', 'lexiata-xslider' ); ?></option>
					</select>
				</div>
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Duration', 'lexiata-xslider' ); ?></label>
					<div class="lex-suffix">
						<input type="number" name="settings[animations][duration]" value="<?php echo (int) $a['duration']; ?>" min="100" max="5000" step="50" />
						<span class="lex-suffix-unit">MS</span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private static function tab_autoplay( $settings, $project_id ) {
		$ap = $settings['autoplay'];
		?>
		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
				<?php esc_html_e( 'Autoplay', 'lexiata-xslider' ); ?>
				<?php self::toggle_inline( 'settings[autoplay][enabled]', $ap['enabled'] ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Slide duration', 'lexiata-xslider' ); ?></label>
					<div class="lex-suffix">
						<input type="number" name="settings[autoplay][slide_duration]" value="<?php echo (int) $ap['slide_duration']; ?>" min="500" max="60000" step="100" />
						<span class="lex-suffix-unit">MS</span>
					</div>
				</div>
			</div>
			<div class="lex-row">
				<?php self::toggle( 'settings[autoplay][stop_on_click]',   $ap['stop_on_click'],   __( 'Stop on click', 'lexiata-xslider' ) ); ?>
				<?php self::toggle( 'settings[autoplay][stop_on_hover]',   $ap['stop_on_hover'],   __( 'Stop on hover', 'lexiata-xslider' ) ); ?>
				<?php self::toggle( 'settings[autoplay][resume_on_leave]', $ap['resume_on_leave'], __( 'Resume on leave', 'lexiata-xslider' ) ); ?>
			</div>
		</div>
		<?php
	}

	private static function tab_optimize( $settings, $project_id ) {
		$o = $settings['optimize'];
		?>
		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
				<?php esc_html_e( 'Loading', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Loading type', 'lexiata-xslider' ); ?></label>
					<select name="settings[optimize][loading_type]">
						<option value="instant" <?php selected( $o['loading_type'], 'instant' ); ?>><?php esc_html_e( 'Instant', 'lexiata-xslider' ); ?></option>
						<option value="lazy"    <?php selected( $o['loading_type'], 'lazy' );    ?>><?php esc_html_e( 'Lazy', 'lexiata-xslider' ); ?></option>
					</select>
				</div>
				<div class="lex-field lex-field-sm">
					<label class="lex-field-label"><?php esc_html_e( 'Lazy threshold', 'lexiata-xslider' ); ?></label>
					<div class="lex-suffix">
						<input type="number" name="settings[optimize][lazy_threshold]" value="<?php echo (int) $o['lazy_threshold']; ?>" min="0" max="100" />
						<span class="lex-suffix-unit">%</span>
					</div>
				</div>
			</div>
			<span class="lex-help"><?php esc_html_e( 'Lazy mode initialises the slider only when it scrolls into view — saves bandwidth on long pages.', 'lexiata-xslider' ); ?></span>
		</div>
		<?php
	}

	private static function tab_slides( $settings, $project_id ) {
		$sl = $settings['slides'];
		?>
		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
				<?php esc_html_e( 'Slides design', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-md">
					<label class="lex-field-label"><?php esc_html_e( 'Slide background image fill', 'lexiata-xslider' ); ?></label>
					<select name="settings[slides][bg_fill]">
						<option value="fill"     <?php selected( $sl['bg_fill'], 'fill' );     ?>><?php esc_html_e( 'Fill', 'lexiata-xslider' ); ?></option>
						<option value="blur_fit" <?php selected( $sl['bg_fill'], 'blur_fit' ); ?>><?php esc_html_e( 'Blur fit', 'lexiata-xslider' ); ?></option>
						<option value="fit"      <?php selected( $sl['bg_fill'], 'fit' );      ?>><?php esc_html_e( 'Fit', 'lexiata-xslider' ); ?></option>
						<option value="stretch"  <?php selected( $sl['bg_fill'], 'stretch' );  ?>><?php esc_html_e( 'Stretch', 'lexiata-xslider' ); ?></option>
						<option value="center"   <?php selected( $sl['bg_fill'], 'center' );   ?>><?php esc_html_e( 'Center', 'lexiata-xslider' ); ?></option>
					</select>
				</div>
			</div>
		</div>
		<?php
	}

	private static function tab_developer( $settings, $project_id ) {
		$d = $settings['developer'];
		?>
		<div class="lex-panel">
			<h2 class="lex-panel-title">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
				<?php esc_html_e( 'Developer', 'lexiata-xslider' ); ?>
			</h2>
			<div class="lex-row">
				<div class="lex-field lex-field-full">
					<label class="lex-field-label"><?php esc_html_e( 'Slider CSS classes', 'lexiata-xslider' ); ?></label>
					<input type="text" name="settings[developer][css_classes]" value="<?php echo esc_attr( $d['css_classes'] ); ?>" placeholder="my-custom-class another-class" />
					<span class="lex-help"><?php esc_html_e( 'Space-separated classes added to the slider wrapper. For custom styling, use WordPress&rsquo; built-in Customizer &rarr; Additional CSS, targeting these classes.', 'lexiata-xslider' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 *  Reusable form bits
	 * ------------------------------------------------------------------ */

	private static function toggle( $name, $checked, $label ) {
		?>
		<div class="lex-toggle-field">
			<label class="lex-field-label"><?php echo esc_html( $label ); ?></label>
			<label class="lex-toggle">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked, 1 ); ?> />
				<span class="lex-track"></span>
				<span class="lex-toggle-state"></span>
			</label>
		</div>
		<?php
	}

	private static function toggle_inline( $name, $checked ) {
		?>
		<label class="lex-toggle" style="margin-left: 12px;">
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked, 1 ); ?> />
			<span class="lex-track"></span>
		</label>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 *  Save handler
	 * ------------------------------------------------------------------ */

	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lexiata-xslider' ) );
		}

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		check_admin_referer( 'lex_xslider_save_settings_' . $project_id, '_lex_nonce' );

		if ( ! $project_id || get_post_type( $project_id ) !== LEX_XSLIDER_CPT ) {
			wp_die( esc_html__( 'Invalid project.', 'lexiata-xslider' ) );
		}

		$active_tab = isset( $_POST['active_tab'] ) ? sanitize_key( wp_unslash( $_POST['active_tab'] ) ) : 'general';
		if ( ! array_key_exists( $active_tab, self::get_tabs() ) ) $active_tab = 'general';

		$current   = Lex_XSlider_Helpers::get_settings( $project_id );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$submitted = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		if ( ! is_array( $submitted ) ) $submitted = array();

		/*
		 * HTML checkboxes/toggles do NOT send anything when unchecked.
		 * For the active tab only, normalise every boolean field to 0 if not in $submitted,
		 * so toggling OFF actually persists.
		 */
		$boolean_fields = array(
			'general'    => array(),
			'size'       => array( 'force_full_width', 'limit_slide_width' ),
			'controls'   => array( 'keyboard', 'arrow_enabled', 'bullet_enabled' ),
			'animations' => array(),
			'autoplay'   => array( 'enabled', 'stop_on_click', 'stop_on_hover', 'resume_on_leave' ),
			'optimize'   => array(),
			'slides'     => array(),
			'developer'  => array(),
		);

		if ( isset( $boolean_fields[ $active_tab ] ) ) {
			if ( ! isset( $submitted[ $active_tab ] ) || ! is_array( $submitted[ $active_tab ] ) ) {
				$submitted[ $active_tab ] = array();
			}
			foreach ( $boolean_fields[ $active_tab ] as $field ) {
				if ( ! isset( $submitted[ $active_tab ][ $field ] ) ) {
					$submitted[ $active_tab ][ $field ] = 0;
				}
			}
		}

		// Checkbox group on Controls tab — empty when none ticked.
		if ( 'controls' === $active_tab && ! isset( $submitted['controls']['arrow_hide_on'] ) ) {
			$submitted['controls']['arrow_hide_on'] = array();
		}

		$merged = Lex_XSlider_Helpers::deep_merge( $current, $submitted );
		Lex_XSlider_Helpers::save_settings( $project_id, $merged );

		// Sync project post_title with general.name if changed.
		if ( isset( $merged['general']['name'] ) && $merged['general']['name'] !== '' ) {
			wp_update_post( array(
				'ID'         => $project_id,
				'post_title' => sanitize_text_field( $merged['general']['name'] ),
			) );
		}

		set_transient( 'lex_xslider_notice', array(
			'type' => 'success',
			'msg'  => __( 'Settings saved.', 'lexiata-xslider' ),
		), 30 );

		wp_safe_redirect( add_query_arg( array(
			'page'       => Lex_XSlider_Admin::MENU_SLUG,
			'view'       => 'editor',
			'project_id' => $project_id,
			'tab'        => $active_tab,
		), admin_url( 'admin.php' ) ) );
		exit;
	}
}
