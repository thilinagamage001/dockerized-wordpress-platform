/*!
 * Lexiata XSlider — Frontend Engine
 * Vanilla JS, no deps. Animations: horizontal, vertical, fade, zoom.
 */
( function () {
	'use strict';

	function ready ( fn ) {
		if ( document.readyState !== 'loading' ) fn();
		else document.addEventListener( 'DOMContentLoaded', fn );
	}
	function $$ ( sel, root ) { return ( root || document ).querySelectorAll( sel ); }

	function LexSlider ( el ) {
		this.el = el;
		try { this.cfg = JSON.parse( el.getAttribute( 'data-lex-xs' ) || '{}' ); } catch ( e ) { this.cfg = {}; }

		this.track   = el.querySelector( '.lex-xs-track' );
		this.slides  = Array.prototype.slice.call( el.querySelectorAll( '.lex-xs-slide' ) );
		this.arrowP  = el.querySelector( '.lex-xs-arrow-prev' );
		this.arrowN  = el.querySelector( '.lex-xs-arrow-next' );
		this.bullets = Array.prototype.slice.call( el.querySelectorAll( '.lex-xs-bullet' ) );

		this.isHorizontal = el.classList.contains( 'lex-xs-anim-horizontal' );
		this.isVertical   = el.classList.contains( 'lex-xs-anim-vertical' );
		this.isFade       = el.classList.contains( 'lex-xs-anim-fade' );
		this.isZoom       = el.classList.contains( 'lex-xs-anim-zoom' );
		this.isStack      = this.isFade || this.isZoom;
		this.isBlur       = el.classList.contains( 'lex-xs-fill-blur_fit' );

		this.count   = this.slides.length;
		this.index   = 0;
		this.timer   = null;
		// Drag (swipe) enabled for ALL animation types when count > 1.
		// In stack modes (fade/zoom) we don't follow the finger with a transform — we just
		// trigger next/prev when the swipe crosses a threshold.
		this.dragOK  = this.cfg.drag === 'horizontal' && this.count > 1;
		this.paused  = false;

		if ( ! this.count ) return;

		// CSS transition duration
		this.el.style.setProperty( '--lex-xs-dur', ( this.cfg.duration || 800 ) + 'ms' );

		// blur_fit — set background-image on each slide for the blur effect
		if ( this.isBlur ) {
			this.slides.forEach( function ( s ) {
				var img = s.querySelector( '.lex-xs-img' );
				if ( img && img.src ) s.style.backgroundImage = "url('" + img.src + "')";
			} );
		}

		this.lazy = this.cfg.lazy && 'IntersectionObserver' in window;
		if ( this.lazy ) {
			var self = this;
			var threshold = ( this.cfg.lazyThreshold || 50 ) / 100;
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) { self.boot(); io.disconnect(); }
				} );
			}, { threshold: Math.max( 0.01, Math.min( 1, threshold ) ) } );
			io.observe( this.el );
		} else {
			this.boot();
		}
	}

	LexSlider.prototype.boot = function () {
		this.bind();
		this.go( 0, true );
		this.el.classList.add( 'is-ready' );

		// Cache reduced-motion preference (respected on initial start AND on resume from pause).
		this.reducedMotion = !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
		if ( this.cfg.autoplay && this.count > 1 && ! this.reducedMotion ) this.start();
	};

	LexSlider.prototype.bind = function () {
		var self = this;

		if ( this.arrowP ) this.arrowP.addEventListener( 'click', function () { self.prev(); self.userAction(); } );
		if ( this.arrowN ) this.arrowN.addEventListener( 'click', function () { self.next(); self.userAction(); } );

		this.bullets.forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				self.go( parseInt( b.getAttribute( 'data-go' ), 10 ) || 0 );
				self.userAction();
			} );
		} );

		if ( this.cfg.keyboard ) {
			this.el.setAttribute( 'tabindex', '0' );
			this._isHovered = false;
			this._isInView  = false;

			// Track hover state on the slider
			this.el.addEventListener( 'mouseenter', function () { self._isHovered = true; } );
			this.el.addEventListener( 'mouseleave', function () { self._isHovered = false; } );

			// Track viewport visibility (so keyboard works when slider is the visible content)
			if ( 'IntersectionObserver' in window ) {
				var keyIO = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( entry ) {
						self._isInView = entry.isIntersecting && entry.intersectionRatio > 0.5;
					} );
				}, { threshold: [ 0, 0.5, 1 ] } );
				keyIO.observe( this.el );
				this._keyIO = keyIO;
			} else {
				this._isInView = true; // fallback for old browsers
			}

			this._keyHandler = function ( e ) {
				// Activate keys if: slider has focus inside, OR mouse is over it, OR it's the dominant viewport element
				var hasFocus = self.el.contains( document.activeElement );
				if ( ! hasFocus && ! self._isHovered && ! self._isInView ) return;

				// Don't hijack keys when user is typing in an input
				var t = e.target;
				if ( t && ( t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable ) ) return;

				if ( e.key === 'ArrowLeft'  || e.key === 'ArrowUp' )   { self.prev(); self.userAction(); e.preventDefault(); }
				if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) { self.next(); self.userAction(); e.preventDefault(); }
			};
			document.addEventListener( 'keydown', this._keyHandler );
		}

		if ( this.cfg.stopOnHover ) {
			this.el.addEventListener( 'mouseenter', function () { self.pause(); } );
			if ( this.cfg.resumeOnLeave ) this.el.addEventListener( 'mouseleave', function () { self.resume(); } );
		}

		if ( this.dragOK ) this.bindDrag();

		this._visibilityHandler = function () {
			if ( document.hidden ) self.pause();
			else self.resume();
		};
		document.addEventListener( 'visibilitychange', this._visibilityHandler );
	};

	LexSlider.prototype.bindDrag = function () {
		var self = this;
		var startX = 0, startY = 0, deltaX = 0, deltaY = 0, dragging = false, locked = false;
		var threshold = 8;
		var axis = self.isVertical ? 'y' : 'x';

		// Tell the browser our touch handling expectations:
		// horizontal slider: pan-y allowed (vertical scrolling), we handle horizontal
		// vertical slider: pan-x allowed (horizontal scrolling), we handle vertical
		this.track.style.touchAction = ( axis === 'y' ) ? 'pan-x' : 'pan-y';

		// Prevent native browser image drag (would otherwise capture mouse and break swipe)
		this.track.addEventListener( 'dragstart', function ( e ) { e.preventDefault(); } );

		var down = function ( e ) {
			var t = e.touches ? e.touches[0] : e;
			startX = t.clientX; startY = t.clientY;
			deltaX = 0; deltaY = 0;
			dragging = true; locked = false;
			self.track.style.transition = 'none';
			self.el.classList.add( 'lex-xs-dragging' );
			self.pause();
		};
		var move = function ( e ) {
			if ( ! dragging ) return;
			var t = e.touches ? e.touches[0] : e;
			deltaX = t.clientX - startX;
			deltaY = t.clientY - startY;
			if ( ! locked ) {
				if ( Math.abs( deltaX ) < threshold && Math.abs( deltaY ) < threshold ) return;
				var primary   = axis === 'y' ? Math.abs( deltaY ) : Math.abs( deltaX );
				var secondary = axis === 'y' ? Math.abs( deltaX ) : Math.abs( deltaY );
				if ( secondary > primary ) { dragging = false; self.endDrag(); return; }
				locked = true;
			}
			if ( e.cancelable ) e.preventDefault();

			// In stack modes (fade/zoom), don't follow the finger with a transform —
			// we'll trigger next/prev on release if threshold is crossed.
			if ( self.isStack ) return;

			if ( axis === 'y' ) {
				var h = self.el.offsetHeight || 1;
				var pctY = ( deltaY / h ) * 100;
				self.track.style.transform = 'translate3d(0, calc(' + ( -self.index * 100 ) + '% + ' + pctY + '%), 0)';
			} else {
				var w = self.el.offsetWidth || 1;
				var pctX = ( deltaX / w ) * 100;
				self.track.style.transform = 'translate3d(calc(' + ( -self.index * 100 ) + '% + ' + pctX + '%), 0, 0)';
			}
		};
		var up = function () {
			if ( ! dragging ) return;
			dragging = false;
			var w = self.el.offsetWidth || 1;
			var h = self.el.offsetHeight || 1;
			var moved = axis === 'y' ? ( deltaY / h ) : ( deltaX / w );
			self.track.style.transition = '';
			self.el.classList.remove( 'lex-xs-dragging' );
			if ( self.isStack ) {
				// Stack modes: simple threshold-based next/prev (no transform to snap back)
				if ( moved < -0.15 )      self.next();
				else if ( moved > 0.15 )  self.prev();
			} else {
				if ( moved < -0.15 )      self.next();
				else if ( moved > 0.15 )  self.prev();
				else                      self.go( self.index, true );
			}
			if ( self.cfg.autoplay && ! self.pausedForever ) self.start();
		};

		this.track.addEventListener( 'mousedown', down );
		window.addEventListener( 'mousemove', move );
		window.addEventListener( 'mouseup',   up );

		this.track.addEventListener( 'touchstart', down, { passive: true } );
		window.addEventListener( 'touchmove', move, { passive: false } );
		window.addEventListener( 'touchend',  up );

		// Suppress click after drag
		this.track.addEventListener( 'click', function ( e ) {
			if ( Math.abs( deltaX ) > threshold || Math.abs( deltaY ) > threshold ) {
				e.preventDefault();
				e.stopPropagation();
			}
		}, true );

		if ( self.cfg.stopOnClick ) {
			this.el.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.lex-xs-arrow, .lex-xs-bullet' ) ) return;
				self.pauseForever();
			} );
		}
	};

	LexSlider.prototype.endDrag = function () {
		this.track.style.transition = '';
		this.el.classList.remove( 'lex-xs-dragging' );
	};

	LexSlider.prototype.go = function ( i, instant ) {
		var n = this.count;
		var self = this;
		this.index = ( ( i % n ) + n ) % n;

		if ( this.isStack ) {
			// Fade / Zoom — stacked layout via CSS Grid
			this.track.style.transform = '';
			this.slides.forEach( function ( s, idx ) {
				s.classList.toggle( 'is-active', idx === self.index );
			} );
		} else if ( this.isVertical ) {
			if ( instant ) this.track.style.transition = 'none';
			this.track.style.transform = 'translate3d(0, ' + ( -this.index * 100 ) + '%, 0)';
			if ( instant ) { void this.track.offsetWidth; this.track.style.transition = ''; }
		} else {
			// Horizontal (default)
			if ( instant ) this.track.style.transition = 'none';
			this.track.style.transform = 'translate3d(' + ( -this.index * 100 ) + '%, 0, 0)';
			if ( instant ) { void this.track.offsetWidth; this.track.style.transition = ''; }
		}

		this.bullets.forEach( function ( b, idx ) {
			b.classList.toggle( 'is-active', idx === self.index );
			b.setAttribute( 'aria-selected', idx === self.index ? 'true' : 'false' );
		} );
	};

	LexSlider.prototype.next = function () { this.go( this.index + 1 ); };
	LexSlider.prototype.prev = function () { this.go( this.index - 1 ); };

	LexSlider.prototype.start = function () {
		if ( this.timer || this.paused || this.pausedForever || this.reducedMotion ) return;
		var self = this;
		this.timer = setInterval( function () { self.next(); }, this.cfg.slideDuration || 8000 );
	};
	LexSlider.prototype.stop = function () {
		if ( this.timer ) { clearInterval( this.timer ); this.timer = null; }
	};
	LexSlider.prototype.pause  = function () { this.stop(); };
	LexSlider.prototype.resume = function () {
		if ( this.cfg.autoplay && ! this.pausedForever ) this.start();
	};
	LexSlider.prototype.pauseForever = function () {
		this.pausedForever = true;
		this.stop();
	};
	LexSlider.prototype.userAction = function () {
		if ( this.cfg.stopOnClick ) this.pauseForever();
	};
	LexSlider.prototype.destroy = function () {
		this.pauseForever();
		if ( this._visibilityHandler ) {
			document.removeEventListener( 'visibilitychange', this._visibilityHandler );
		}
		if ( this._keyHandler ) {
			document.removeEventListener( 'keydown', this._keyHandler );
		}
		if ( this._keyIO && this._keyIO.disconnect ) {
			this._keyIO.disconnect();
		}
		this.el.__lexInit = false;
	};

	function init () {
		$$( '.lex-xs[data-lex-xs]' ).forEach( function ( el ) {
			if ( el.__lexInit ) return;
			el.__lexInit = true;
			el.__lexInstance = new LexSlider( el );
		} );
	}

	// Public destroy helper — usable by SPA frameworks that remove sliders mid-page-life.
	window.lexiataXSliderDestroy = function ( el ) {
		if ( el && el.__lexInstance && typeof el.__lexInstance.destroy === 'function' ) {
			el.__lexInstance.destroy();
			el.__lexInstance = null;
		}
	};

	ready( init );
	window.lexiataXSliderRefresh = init;
} )();
