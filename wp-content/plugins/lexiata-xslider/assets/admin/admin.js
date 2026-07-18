/**
 * Lexiata XSlider — Admin JS
 *
 * - Opens the always-present #lex-slide-editor modal and binds field updates
 * - WP Media frame integration
 * - AJAX CRUD for slides
 * - Drag-to-reorder
 * - Click-to-copy shortcode
 */
( function ( $, wp ) {
	'use strict';

	var App = {
		ajax:  ( window.lexiataXSlider && lexiataXSlider.ajaxUrl ) || window.ajaxurl,
		nonce: ( window.lexiataXSlider && lexiataXSlider.slideNonce ) || '',
		i18n:  ( window.lexiataXSlider && lexiataXSlider.i18n ) || {},
		mediaFrame: null
	};

	$( function () {
		bindBasic();
		bindCopyCode();
		bindTypePicker();
		bindStripClicks();
		bindModal();
		bindRadiusControl();
		enableTouchSort();
		bindSortable();
		bindUnsavedWarning();
	} );

	/* ============================================================ *
	 *  Border radius control (Size tab) — sync slider ↔ number ↔ presets
	 * ============================================================ */
	function bindRadiusControl () {
		var $slider = $( '#lex-radius-slider' );
		var $number = $( '#lex-radius-number' );
		var $chips  = $( '.lex-radius-chip' );
		if ( ! $slider.length ) return;

		var setVal = function ( v, src ) {
			v = parseInt( v, 10 );
			if ( isNaN( v ) ) v = 0;
			v = Math.max( 0, Math.min( 100, v ) );

			if ( src !== 'slider' ) $slider.val( v );
			if ( src !== 'number' ) $number.val( v );

			// Update the slider fill gradient
			$slider[0].style.setProperty( '--lex-radius-fill', v + '%' );

			// Mark active preset chip (exact match)
			$chips.removeClass( 'is-active' );
			$chips.filter( '[data-radius="' + v + '"]' ).addClass( 'is-active' );
		};

		// Initial state
		setVal( $slider.val(), 'init' );

		$slider.on( 'input change', function () { setVal( this.value, 'slider' ); } );
		$number.on( 'input change', function () { setVal( this.value, 'number' ); } );
		$chips.on( 'click', function () { setVal( $( this ).data( 'radius' ), 'chip' ); } );
	}

	/* ============================================================ *
	 *  Touch-to-mouse polyfill for jQuery UI Sortable
	 *  (jQuery UI doesn't support touch events natively)
	 * ============================================================ */
	function enableTouchSort () {
		// Only need to do this once per page load
		if ( window.__lexTouchSort ) return;
		window.__lexTouchSort = true;

		var dragSource = null;

		document.addEventListener( 'touchstart', function ( e ) {
			var grip = e.target.closest && e.target.closest( '.lex-slide-grip' );
			if ( ! grip ) return;
			dragSource = grip;
			fireMouse( grip, 'mousedown', e.touches[0] );
		}, { passive: false } );

		document.addEventListener( 'touchmove', function ( e ) {
			if ( ! dragSource ) return;
			e.preventDefault();
			var t = e.touches[0];
			fireMouse( document, 'mousemove', t );
		}, { passive: false } );

		document.addEventListener( 'touchend', function ( e ) {
			if ( ! dragSource ) return;
			var last = e.changedTouches[0];
			fireMouse( document, 'mouseup', last );
			dragSource = null;
		} );

		document.addEventListener( 'touchcancel', function () {
			if ( ! dragSource ) return;
			fireMouse( document, 'mouseup', { clientX: 0, clientY: 0 } );
			dragSource = null;
		} );

		function fireMouse ( target, type, src ) {
			var ev = document.createEvent( 'MouseEvent' );
			ev.initMouseEvent(
				type, true, true, window, 1,
				src.clientX || 0, src.clientY || 0,
				src.clientX || 0, src.clientY || 0,
				false, false, false, false, 0, null
			);
			target.dispatchEvent( ev );
		}
	}

	/* ============================================================ *
	 *  Unsaved-changes warning on settings form
	 * ============================================================ */
	function bindUnsavedWarning () {
		var $form = $( '#lex-settings-form' );
		if ( ! $form.length ) return;

		var dirty = false;
		$form.on( 'change input', 'input, select, textarea', function () { dirty = true; } );
		$form.on( 'submit', function () { dirty = false; } );

		// Don't warn when navigating to another tab in our editor (those are server saves)
		$( document ).on( 'click', '.lex-tab', function () {
			if ( ! dirty ) return;
			if ( ! window.confirm( ( window.lexiataXSlider && lexiataXSlider.i18n && lexiataXSlider.i18n.unsavedTab ) || 'You have unsaved changes. Switch tab anyway?' ) ) {
				return false;
			}
			dirty = false;
		} );

		window.addEventListener( 'beforeunload', function ( e ) {
			if ( ! dirty ) return;
			e.preventDefault();
			e.returnValue = '';
		} );
	}

	/* ============================================================ *
	 *  Basic interactions
	 * ============================================================ */
	function bindBasic () {
		// Project delete confirm
		$( document ).on( 'click', '[data-confirm-delete]', function ( e ) {
			if ( ! window.confirm( App.i18n.confirmDelete || 'Delete this project?' ) ) e.preventDefault();
		} );

		// Tile (radio) active state
		$( document ).on( 'change', '.lex-tile input[type="radio"]', function () {
			var $tile = $( this ).closest( '.lex-tile' );
			$tile.closest( '.lex-tiles, .lex-section' )
				.find( '.lex-tile input[name="' + this.name + '"]' )
				.each( function () { $( this ).closest( '.lex-tile' ).removeClass( 'is-on' ); } );
			$tile.addClass( 'is-on' );
		} );

		// Esc closes modal / type-picker
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' ) return;
			var $picker = $( '#lex-type-picker.is-open' );
			if ( $picker.length ) { $picker.removeClass( 'is-open' ); return; }
			// Slide editor modal
			if ( $( '#lex-slide-editor.is-open' ).length ) { closeModal(); return; }
			// Other modals (create-project): navigate via close link
			var $other = $( '.lex-modal.is-open .lex-modal-close' ).first();
			if ( $other.length && $other.attr( 'href' ) ) window.location.href = $other.attr( 'href' );
		} );

		// Click outside modal box closes it
		$( document ).on( 'click', '.lex-modal', function ( e ) {
			if ( ! $( e.target ).is( this ) ) return;
			// Slide editor modal: close in-place
			if ( this.id === 'lex-slide-editor' ) { closeModal(); return; }
			// Other modals (create-project): use the close link
			var $close = $( this ).find( '.lex-modal-close' ).first();
			if ( $close.length && $close.attr( 'href' ) ) window.location.href = $close.attr( 'href' );
		} );
	}

	/* ============================================================ *
	 *  Copy shortcode
	 * ============================================================ */
	function bindCopyCode () {
		$( document ).on( 'click', '.lex-code', function () {
			var $code = $( this );
			var text = ( $code.text() || '' ).trim();
			copy( text ).then( function ( ok ) {
				if ( ! ok ) return;
				var orig = $code.text();
				$code.addClass( 'is-copied' ).text( App.i18n.copied || 'Copied!' );
				setTimeout( function () { $code.removeClass( 'is-copied' ).text( orig ); }, 1100 );
			} );
		} );
	}
	function copy ( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).then( function () { return true; }, function () { return false; } );
		}
		return new Promise( function ( resolve ) {
			var $ta = $( '<textarea>' ).val( text ).css( { position: 'fixed', opacity: 0 } ).appendTo( document.body );
			$ta[0].select();
			var ok = false;
			try { ok = document.execCommand( 'copy' ); } catch ( e ) {}
			$ta.remove(); resolve( ok );
		} );
	}

	/* ============================================================ *
	 *  Type picker (Add Slide)
	 * ============================================================ */
	function bindTypePicker () {
		$( document ).on( 'click', '[data-action="open-type-picker"]', function () {
			$( '#lex-type-picker' ).addClass( 'is-open' );
		} );
		$( document ).on( 'click', '[data-action="close-type-picker"]', function () {
			$( '#lex-type-picker' ).removeClass( 'is-open' );
		} );
		$( document ).on( 'click', '[data-action="add-slide"]', function () {
			var type = $( this ).data( 'type' ) || 'image';
			$( '#lex-type-picker' ).removeClass( 'is-open' );
			openModal( {
				id: '', type: type, name: '',
				image_id: 0, image_url: '', image_alt: '',
				background_color: type === 'blank' ? '#1f2937' : '',
				link_url: '', link_target: '_self', link_nofollow: 0,
				published: 1
			}, true );
		} );
	}

	/* ============================================================ *
	 *  Slide strip clicks
	 * ============================================================ */
	function bindStripClicks () {
		$( document ).on( 'click', '.lex-slide', function ( e ) {
			if ( $( e.target ).closest( '[data-no-edit]' ).length ) return;
			var slideId = $( this ).data( 'slide-id' );
			fetchSlide( slideId );
		} );
	}

	function fetchSlide ( slideId ) {
		$.post( App.ajax, {
			action: 'lex_xslider_get_slide',
			_nonce: App.nonce,
			project_id: getProjectId(),
			slide_id: slideId
		} ).done( function ( res ) {
			if ( res && res.success && res.data && res.data.slide ) {
				openModal( res.data.slide, false );
			} else {
				alert( ( res && res.data && res.data.message ) || App.i18n.error || 'Error' );
			}
		} ).fail( function () {
			alert( App.i18n.error || 'Network error' );
		} );
	}

	/* ============================================================ *
	 *  Slide editor modal — open / close / fill / save
	 * ============================================================ */
	function bindModal () {
		var sel = '#lex-slide-editor ';

		// Close
		$( document ).on( 'click', sel + '[data-action="close"], ' + sel + '[data-action="cancel"]', function () {
			closeModal();
		} );

		// Image picker
		$( document ).on( 'click', sel + '[data-action="select-image"]', openMediaFrame );

		// Remove image
		$( document ).on( 'click', sel + '[data-action="remove-image"]', function () {
			setField( 'image_id', 0 );
			setField( 'image_url', '' );
			updatePreview();
		} );

		// Background color: sync swatch <-> text
		$( document ).on( 'input change', sel + '[data-field="bg_picker"]', function () {
			$( sel + '[data-field="background_color"]' ).val( this.value );
			updatePreview();
		} );
		$( document ).on( 'input change', sel + '[data-field="background_color"]', function () {
			var v = ( this.value || '' ).trim();
			if ( /^#[0-9a-fA-F]{6}$/.test( v ) ) {
				$( sel + '[data-field="bg_picker"]' ).val( v );
			}
			updatePreview();
		} );
		$( document ).on( 'click', sel + '[data-action="bg-clear"]', function () {
			setField( 'background_color', '' );
			$( sel + '[data-field="bg_picker"]' ).val( '#000000' );
			updatePreview();
		} );

		// Save
		$( document ).on( 'click', sel + '[data-action="save"]', function ( e ) {
			e.preventDefault();
			saveSlide();
		} );

		// Delete
		$( document ).on( 'click', sel + '[data-action="delete"]', function () {
			if ( ! window.confirm( App.i18n.confirmDeleteSlide || 'Delete this slide?' ) ) return;
			deleteSlide();
		} );

		// Duplicate
		$( document ).on( 'click', sel + '[data-action="duplicate"]', duplicateSlide );
	}

	function $modal () { return $( '#lex-slide-editor' ); }

	function setField ( name, value ) {
		var $el = $modal().find( '[data-field="' + name + '"]' );
		if ( $el.attr( 'type' ) === 'checkbox' ) {
			$el.prop( 'checked', !! value );
		} else {
			$el.val( value );
		}
	}

	function getField ( name ) {
		var $el = $modal().find( '[data-field="' + name + '"]' );
		if ( $el.attr( 'type' ) === 'checkbox' ) return $el.is( ':checked' ) ? 1 : 0;
		return $el.val() || '';
	}

	function openModal ( slide, isNew ) {
		var $m = $modal();
		if ( ! $m.length ) return;

		// Title
		$m.find( '#lex-slide-editor-title' ).text(
			isNew ? ( App.i18n.addNewSlide || 'Add new slide' )
			      : ( App.i18n.editSlide   || 'Edit slide' )
		);

		// Reset every field explicitly (so previous values never leak in)
		setField( 'id',               slide.id || '' );
		setField( 'type',             slide.type || 'image' );
		setField( 'name',             slide.name || '' );
		setField( 'image_id',         slide.image_id || 0 );
		setField( 'image_url',        slide.image_url || '' );
		setField( 'image_alt',        slide.image_alt || '' );
		setField( 'background_color', slide.background_color || '' );
		setField( 'link_url',         slide.link_url || '' );
		setField( 'link_target',      slide.link_target || '_self' );
		setField( 'link_nofollow',    !! slide.link_nofollow );
		setField( 'published',        slide.published === undefined ? 1 : slide.published );

		// Color picker swatch sync
		var bg = slide.background_color || '#1f2937';
		if ( /^#[0-9a-fA-F]{6}$/.test( bg ) ) {
			$m.find( '[data-field="bg_picker"]' ).val( bg );
		}

		// Preview & action buttons
		updatePreview();
		$m.find( '[data-action="delete"], [data-action="duplicate"]' ).toggle( ! isNew );

		// Open
		$m.addClass( 'is-open' );

		// Focus first field
		setTimeout( function () {
			$m.find( '[data-field="name"]' ).trigger( 'focus' );
		}, 80 );
	}

	function closeModal () {
		$modal().removeClass( 'is-open' );
	}

	function updatePreview () {
		var $m = $modal();
		var url = getField( 'image_url' );
		var bg  = getField( 'background_color' );
		var $frame = $m.find( '[data-bind="frame"]' );
		var $img   = $m.find( '[data-bind="img"]' );
		var $empty = $m.find( '[data-bind="empty"]' );

		// Clear inline background first
		$frame.css( 'background-color', '' );
		$frame.removeClass( 'has-image has-color' );

		if ( url ) {
			$img.attr( 'src', url ).show();
			$empty.hide();
			$frame.addClass( 'has-image' );
			$m.find( '[data-bind="select-label"]' ).text( App.i18n.changeImage || 'Change image' );
			$m.find( '[data-action="remove-image"]' ).show();
		} else {
			$img.attr( 'src', '' ).hide();
			$m.find( '[data-action="remove-image"]' ).hide();
			$m.find( '[data-bind="select-label"]' ).text( App.i18n.selectImage || 'Select image' );

			if ( bg && /^#[0-9a-fA-F]{6}$/.test( bg ) ) {
				$frame.addClass( 'has-color' ).css( 'background-color', bg );
				$empty.hide();
			} else {
				$empty.show();
			}
		}
	}

	/* ============================================================ *
	 *  WP Media frame — robust init each time
	 * ============================================================ */
	function openMediaFrame () {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			alert( 'WordPress media library is not loaded. Please refresh the page.' );
			return;
		}
		// Re-create on every call to avoid stale state.
		App.mediaFrame = wp.media( {
			title: App.i18n.selectImage || 'Select Slide Image',
			button: { text: App.i18n.useImage || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		} );
		App.mediaFrame.on( 'select', function () {
			var att = App.mediaFrame.state().get( 'selection' ).first().toJSON();
			setField( 'image_id', att.id || 0 );
			setField( 'image_url', att.url || '' );
			setField( 'type', 'image' );
			// Auto-fill alt only if empty
			var $alt = $modal().find( '[data-field="image_alt"]' );
			if ( ! $alt.val() && att.alt ) $alt.val( att.alt );
			updatePreview();
		} );
		App.mediaFrame.open();
	}

	/* ============================================================ *
	 *  AJAX: Save / Delete / Duplicate
	 * ============================================================ */
	function getProjectId () {
		var pid = $( '.lex-strip-wrap' ).data( 'project-id' );
		return pid ? parseInt( pid, 10 ) : 0;
	}

	function readSlide () {
		return {
			id:               getField( 'id' ),
			type:             getField( 'type' ),
			name:             getField( 'name' ),
			image_id:         parseInt( getField( 'image_id' ), 10 ) || 0,
			image_url:        getField( 'image_url' ),
			image_alt:        getField( 'image_alt' ),
			background_color: getField( 'background_color' ),
			link_url:         getField( 'link_url' ),
			link_target:      getField( 'link_target' ),
			link_nofollow:    getField( 'link_nofollow' ),
			published:        getField( 'published' )
		};
	}

	function saveSlide () {
		var slide = readSlide();
		var $btn = $modal().find( '[data-action="save"]' );
		btnLoading( $btn, true );

		$.post( App.ajax, {
			action: 'lex_xslider_save_slide',
			_nonce: App.nonce,
			project_id: getProjectId(),
			slide: slide
		} ).done( function ( res ) {
			btnLoading( $btn, false );
			if ( res && res.success && res.data ) {
				upsertThumb( res.data.slide, res.data.thumbnail, !! res.data.created );
				closeModal();
			} else {
				alert( ( res && res.data && res.data.message ) || App.i18n.error || 'Error' );
			}
		} ).fail( function ( xhr ) {
			btnLoading( $btn, false );
			alert( ( App.i18n.error || 'Network error' ) + ' (' + ( xhr && xhr.status ) + ')' );
		} );
	}

	function deleteSlide () {
		var id = getField( 'id' );
		if ( ! id ) { closeModal(); return; }
		$.post( App.ajax, {
			action: 'lex_xslider_delete_slide',
			_nonce: App.nonce,
			project_id: getProjectId(),
			slide_id: id
		} ).done( function ( res ) {
			if ( res && res.success ) {
				$( '.lex-slide[data-slide-id="' + id + '"]' ).remove();
				ensureEmptyHint();
				closeModal();
			}
		} );
	}

	function duplicateSlide () {
		var id = getField( 'id' );
		if ( ! id ) return;
		$.post( App.ajax, {
			action: 'lex_xslider_duplicate_slide',
			_nonce: App.nonce,
			project_id: getProjectId(),
			slide_id: id
		} ).done( function ( res ) {
			if ( res && res.success && res.data && res.data.slide ) {
				upsertThumb( res.data.slide, res.data.thumbnail, true );
				closeModal();
			}
		} );
	}

	function upsertThumb ( slide, html, created ) {
		if ( ! html ) return;
		var $existing = $( '.lex-slide[data-slide-id="' + slide.id + '"]' );
		if ( $existing.length && ! created ) {
			$existing.replaceWith( html );
		} else {
			$( '#lex-strip [data-bind="empty"]' ).remove();
			$( '#lex-strip' ).append( html );
		}
		ensureEmptyHint();
	}

	function ensureEmptyHint () {
		var $strip = $( '#lex-strip' );
		var slideCount = $strip.find( '.lex-slide' ).length;
		if ( slideCount === 0 && $strip.find( '[data-bind="empty"]' ).length === 0 ) {
			var msg = ( App.i18n.emptyStripHint ) || 'Click ADD SLIDE to create your first slide.';
			$strip.append( '<div class="lex-strip-empty" data-bind="empty"></div>' );
			$strip.find( '[data-bind="empty"]' ).text( msg );
		}
		// Toggle reorder hint visibility (only relevant when 2+ slides)
		var $hint = $( '[data-bind="strip-hint"]' );
		if ( $hint.length ) {
			$hint.toggle( slideCount > 1 );
		}
	}

	function btnLoading ( $btn, on ) {
		if ( on ) {
			$btn.addClass( 'lex-btn-loading' ).data( 'orig', $btn.html() ).html( '<span class="lex-spin"></span> ' + ( App.i18n.saving || 'Saving…' ) );
		} else {
			$btn.removeClass( 'lex-btn-loading' ).html( $btn.data( 'orig' ) || $btn.html() );
		}
	}

	/* ============================================================ *
	 *  Sortable
	 * ============================================================ */
	function bindSortable () {
		var $strip = $( '#lex-strip' );
		if ( ! $strip.length || ! $.fn.sortable ) return;

		$strip.sortable( {
			items: '.lex-slide',
			handle: '.lex-slide-grip',
			placeholder: 'lex-strip-placeholder',
			tolerance: 'pointer',
			forcePlaceholderSize: true,
			start: function ( e, ui ) { ui.item.addClass( 'is-dragging' ); },
			stop:  function ( e, ui ) { ui.item.removeClass( 'is-dragging' ); },
			update: function () {
				var ids = $strip.find( '.lex-slide' ).map( function () { return $( this ).data( 'slide-id' ); } ).get();
				$.post( App.ajax, {
					action: 'lex_xslider_reorder_slides',
					_nonce: App.nonce,
					project_id: getProjectId(),
					order: ids
				} );
			}
		} );
	}

} )( jQuery, window.wp );
