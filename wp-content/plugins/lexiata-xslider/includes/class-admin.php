<?php
/**
 * Lexiata XSlider — Admin
 *
 * Top-level menu, dashboard listing, create-project handler.
 *
 * @package Lexiata_XSlider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Lex_XSlider_Admin {

	const MENU_SLUG = 'lex-xslider';

	public static function init() {
		add_action( 'admin_menu',            array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init',            array( __CLASS__, 'maybe_handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Lexiata XSlider', 'lexiata-xslider' ),
			__( 'XSlider', 'lexiata-xslider' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-images-alt2',
			58
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( ! self::is_plugin_screen() ) {
			return;
		}

		// Required for WP media frame (image picker).
		wp_enqueue_media();

		wp_enqueue_style(
			'lex-xslider-admin',
			LEX_XSLIDER_URL . 'assets/admin/admin.css',
			array(),
			LEX_XSLIDER_VERSION
		);

		wp_enqueue_script(
			'lex-xslider-admin',
			LEX_XSLIDER_URL . 'assets/admin/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			LEX_XSLIDER_VERSION,
			true
		);

		wp_localize_script( 'lex-xslider-admin', 'lexiataXSlider', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'menuSlug'   => self::MENU_SLUG,
			'slideNonce' => wp_create_nonce( Lex_XSlider_Slide_Manager::NONCE_ACTION ),
			'i18n'       => array(
				'confirmDelete'      => __( 'Delete this project? This cannot be undone.', 'lexiata-xslider' ),
				'confirmDeleteSlide' => __( 'Delete this slide? This cannot be undone.', 'lexiata-xslider' ),
				'selectImage'        => __( 'Select Slide Image', 'lexiata-xslider' ),
				'useImage'           => __( 'Use this image', 'lexiata-xslider' ),
				'changeImage'        => __( 'Change image', 'lexiata-xslider' ),
				'addNewSlide'        => __( 'Add new slide', 'lexiata-xslider' ),
				'editSlide'          => __( 'Edit slide', 'lexiata-xslider' ),
				'saving'             => __( 'Saving…', 'lexiata-xslider' ),
				'copied'             => __( 'Copied!', 'lexiata-xslider' ),
				'unsavedTab'         => __( 'You have unsaved changes on this tab. Switch tab anyway?', 'lexiata-xslider' ),
				'emptyStripHint'     => __( 'Click ADD SLIDE to create your first slide.', 'lexiata-xslider' ),
				'error'              => __( 'Something went wrong. Please try again.', 'lexiata-xslider' ),
			),
		) );
	}

	public static function is_plugin_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection
		return isset( $_GET['page'] ) && self::MENU_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/* ------------------------------------------------------------------ *
	 *  Routing
	 * ------------------------------------------------------------------ */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'lexiata-xslider' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';

		echo '<div class="wrap lex-wrap">';
		self::render_header();
		echo '<div class="lex-body">';

		if ( 'editor' === $view ) {
			Lex_XSlider_Project_Editor::render();
		} else {
			self::render_dashboard();
		}

		echo '</div></div>';
	}

	private static function render_header() {
		?>
		<div class="lex-header">
			<a class="lex-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>">
				<span class="lex-brand-logo" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 10l-2 2 2 2M17 10l2 2-2 2"/></svg>
				</span>
				<span class="lex-brand-text">
					<span class="lex-brand-name">Lexiata</span>
					<span class="lex-brand-sub">XSlider</span>
					<span class="lex-version-pill">v<?php echo esc_html( LEX_XSLIDER_VERSION ); ?></span>
				</span>
			</a>
			<div class="lex-header-actions">
				<a class="lex-iconlink" href="https://lexiata.lk" target="_blank" rel="noopener noreferrer">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
					lexiata.lk
				</a>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 *  Dashboard
	 * ------------------------------------------------------------------ */

	private static function render_dashboard() {
		$projects = Lex_XSlider_CPT::get_all_projects();
		$create_url = wp_nonce_url(
			admin_url( 'admin.php?page=' . self::MENU_SLUG . '&action=open_create' ),
			'lex_xslider_open_create'
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag
		$show_modal = isset( $_GET['create'] ) && '1' === ( is_string( $_GET['create'] ) ? sanitize_key( wp_unslash( $_GET['create'] ) ) : '' );
		?>
		<div class="lex-dashboard">

			<?php self::render_admin_notices(); ?>

			<div class="lex-section-head">
				<div>
					<h1><?php esc_html_e( 'Slider projects', 'lexiata-xslider' ); ?></h1>
					<p><?php
						/* translators: %d: number of projects */
						printf( esc_html( _n( '%d project', '%d projects', count( $projects ), 'lexiata-xslider' ) ), (int) count( $projects ) );
					?></p>
				</div>
			</div>

			<div class="lex-grid">
				<a class="lex-newcard" href="<?php echo esc_url( $create_url ); ?>">
					<span class="lex-newcard-plus" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					</span>
					<span class="lex-newcard-text"><?php esc_html_e( 'New project', 'lexiata-xslider' ); ?></span>
					<span class="lex-newcard-sub"><?php esc_html_e( 'Start a new slider', 'lexiata-xslider' ); ?></span>
				</a>

				<?php if ( empty( $projects ) ) : ?>
					<div class="lex-empty">
						<p><?php esc_html_e( 'No slider projects yet. Click "New project" to create your first slider.', 'lexiata-xslider' ); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ( $projects as $project ) self::render_project_card( $project ); ?>
				<?php endif; ?>
			</div>

			<?php if ( $show_modal ) self::render_create_modal(); ?>
		</div>
		<?php
	}

	private static function render_project_card( $project ) {
		$slides    = Lex_XSlider_Helpers::get_slides( $project->ID );
		$slide_cnt = count( $slides );

		$thumb_url = '';
		foreach ( $slides as $slide ) {
			if ( ! empty( $slide['image_url'] ) ) { $thumb_url = $slide['image_url']; break; }
		}

		$edit_url   = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&view=editor&project_id=' . $project->ID );
		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=' . self::MENU_SLUG . '&action=delete&project_id=' . $project->ID ),
			'lex_xslider_delete_' . $project->ID
		);
		?>
		<div class="lex-card">
			<a class="lex-card-thumb" href="<?php echo esc_url( $edit_url ); ?>">
				<?php if ( $thumb_url ) : ?>
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
				<?php else : ?>
					<span class="lex-card-thumb-empty" aria-hidden="true">
						<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
							<rect x="3" y="5" width="18" height="14" rx="2"/>
							<circle cx="9" cy="11" r="1.4" fill="currentColor"/>
							<path d="M4 17l5-4 4 3 3-2 4 3" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
				<?php endif; ?>
			</a>
			<div class="lex-card-meta">
				<a class="lex-card-title" href="<?php echo esc_url( $edit_url ); ?>">
					<?php echo esc_html( $project->post_title ); ?>
				</a>
				<span class="lex-pill"><?php
					/* translators: %d: slide count */
					printf( esc_html( _n( '%d slide', '%d slides', $slide_cnt, 'lexiata-xslider' ) ), (int) $slide_cnt );
				?></span>
			</div>
			<div class="lex-card-actions">
				<a class="lex-btn lex-btn-sm" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'lexiata-xslider' ); ?></a>
				<a class="lex-btn lex-btn-sm lex-btn-danger" href="<?php echo esc_url( $delete_url ); ?>" data-confirm-delete>
					<?php esc_html_e( 'Delete', 'lexiata-xslider' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	private static function render_create_modal() {
		$action_url = admin_url( 'admin-post.php' );
		$close_url  = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		?>
		<div class="lex-modal is-open" role="dialog" aria-modal="true" aria-labelledby="lex-create-title">
			<div class="lex-modal-box">
				<div class="lex-modal-head">
					<div>
						<h2 id="lex-create-title"><?php esc_html_e( 'Create new project', 'lexiata-xslider' ); ?></h2>
						<p><?php esc_html_e( 'Configure the basic project settings — you can change these later.', 'lexiata-xslider' ); ?></p>
					</div>
					<a href="<?php echo esc_url( $close_url ); ?>" class="lex-modal-close" aria-label="<?php esc_attr_e( 'Close', 'lexiata-xslider' ); ?>">×</a>
				</div>

				<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="lex-create-form">
					<?php wp_nonce_field( 'lex_xslider_create_project', '_lex_nonce' ); ?>
					<input type="hidden" name="action" value="lex_xslider_create_project" />

					<div class="lex-modal-body">
						<div class="lex-section">
							<div class="lex-section-label"><?php esc_html_e( 'Project type', 'lexiata-xslider' ); ?></div>
							<div class="lex-tiles">
								<label class="lex-tile is-on">
									<input type="radio" name="project_type" value="slider" checked />
									<svg width="36" height="26" viewBox="0 0 36 26" fill="none"><rect x="2" y="2" width="32" height="22" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M10 13h16M9 13l3-3M27 13l-3-3M9 13l3 3M27 13l-3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" fill="none"/></svg>
									<span class="lex-tile-label"><?php esc_html_e( 'Slider', 'lexiata-xslider' ); ?></span>
								</label>
							</div>
						</div>

						<div class="lex-section">
							<div class="lex-section-label"><?php esc_html_e( 'Slider type', 'lexiata-xslider' ); ?></div>
							<div class="lex-tiles">
								<label class="lex-tile is-on">
									<input type="radio" name="slider_type" value="simple" checked />
									<svg width="36" height="26" viewBox="0 0 36 26" fill="none"><rect x="2" y="2" width="32" height="22" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M10 13h16M9 13l3-3M27 13l-3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" fill="none"/></svg>
									<span class="lex-tile-label"><?php esc_html_e( 'Simple', 'lexiata-xslider' ); ?></span>
								</label>
							</div>
						</div>

						<div class="lex-section">
							<div class="lex-section-label"><?php esc_html_e( 'Settings', 'lexiata-xslider' ); ?></div>
							<div class="lex-row">
								<div class="lex-field">
									<label class="lex-field-label"><?php esc_html_e( 'Name', 'lexiata-xslider' ); ?></label>
									<input type="text" name="name" value="<?php esc_attr_e( 'My project', 'lexiata-xslider' ); ?>" required />
								</div>
								<div class="lex-field lex-field-sm">
									<label class="lex-field-label"><?php esc_html_e( 'Width', 'lexiata-xslider' ); ?></label>
									<div class="lex-suffix">
										<input type="number" name="width" value="1200" min="100" max="5000" />
										<span class="lex-suffix-unit">PX</span>
									</div>
								</div>
								<div class="lex-field lex-field-sm">
									<label class="lex-field-label"><?php esc_html_e( 'Height', 'lexiata-xslider' ); ?></label>
									<div class="lex-suffix">
										<input type="number" name="height" value="600" min="50" max="5000" />
										<span class="lex-suffix-unit">PX</span>
									</div>
								</div>
								<div class="lex-field lex-field-sm">
									<label class="lex-field-label"><?php esc_html_e( 'Layout', 'lexiata-xslider' ); ?></label>
									<select name="layout">
										<option value="full_width"><?php esc_html_e( 'Full width', 'lexiata-xslider' ); ?></option>
										<option value="boxed"><?php esc_html_e( 'Boxed', 'lexiata-xslider' ); ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>

					<div class="lex-modal-foot">
						<a href="<?php echo esc_url( $close_url ); ?>" class="lex-btn"><?php esc_html_e( 'Cancel', 'lexiata-xslider' ); ?></a>
						<button type="submit" class="lex-btn lex-btn-brand"><?php esc_html_e( 'Create project', 'lexiata-xslider' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 *  Action handlers
	 * ------------------------------------------------------------------ */

	public static function maybe_handle_actions() {
		// Bail early if we're not on our page (avoids unnecessary processing on every admin page load).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing check
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::MENU_SLUG !== $page ) {
			// Still register the admin-post handler — it has its own capability + nonce checks.
			add_action( 'admin_post_lex_xslider_create_project', array( __CLASS__, 'handle_create_project' ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		// All actions on our page require manage_options.
		if ( $action && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'lexiata-xslider' ) );
		}

		if ( 'open_create' === $action ) {
			check_admin_referer( 'lex_xslider_open_create' );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&create=1' ) );
			exit;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below
		if ( 'delete' === $action && isset( $_GET['project_id'] ) ) {
			$project_id = absint( $_GET['project_id'] );
			check_admin_referer( 'lex_xslider_delete_' . $project_id );
			if ( get_post_type( $project_id ) === LEX_XSLIDER_CPT ) {
				wp_delete_post( $project_id, true );
				set_transient( 'lex_xslider_notice', array(
					'type' => 'success',
					'msg'  => __( 'Project deleted.', 'lexiata-xslider' ),
				), 30 );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
			exit;
		}
		add_action( 'admin_post_lex_xslider_create_project', array( __CLASS__, 'handle_create_project' ) );
	}

	public static function handle_create_project() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lexiata-xslider' ) );
		}
		check_admin_referer( 'lex_xslider_create_project', '_lex_nonce' );

		$args = array(
			'name'   => isset( $_POST['name'] )   ? sanitize_text_field( wp_unslash( $_POST['name'] ) )   : __( 'My project', 'lexiata-xslider' ),
			'width'  => isset( $_POST['width'] )  ? absint( $_POST['width'] )  : 1200,
			'height' => isset( $_POST['height'] ) ? absint( $_POST['height'] ) : 600,
			'layout' => isset( $_POST['layout'] ) ? sanitize_key( wp_unslash( $_POST['layout'] ) ) : 'full_width',
		);

		$post_id = Lex_XSlider_CPT::create_project( $args );

		if ( is_wp_error( $post_id ) ) {
			set_transient( 'lex_xslider_notice', array( 'type' => 'error', 'msg' => $post_id->get_error_message() ), 30 );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
			exit;
		}

		set_transient( 'lex_xslider_notice', array(
			'type' => 'success',
			'msg'  => __( 'Project created — add your first slide.', 'lexiata-xslider' ),
		), 30 );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&view=editor&project_id=' . $post_id ) );
		exit;
	}

	private static function render_admin_notices() {
		$notice = get_transient( 'lex_xslider_notice' );
		if ( ! $notice || empty( $notice['msg'] ) ) return;
		delete_transient( 'lex_xslider_notice' );
		$type = ( isset( $notice['type'] ) && 'error' === $notice['type'] ) ? 'error' : 'success';
		?>
		<div class="lex-notice lex-notice-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $notice['msg'] ); ?></div>
		<?php
	}
}
