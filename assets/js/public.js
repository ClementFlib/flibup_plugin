/**
 * Flib'Up — logique publique (JavaScript natif, sans dépendance).
 *
 * Une pop-up est considérée comme « vue » dès son OUVERTURE : c'est le
 * comportement le plus robuste pour un plafonnement de fréquence, car un
 * visiteur qui l'ignore sans la fermer ne la reverra pas à chaque page.
 */
( function () {
	'use strict';

	var settings = window.flibupPublic || { allowMultiple: false, preview: false };

	/* ------------------------------------------------------------------ */
	/* Stockage navigateur (avec repli cookie).                            */
	/* ------------------------------------------------------------------ */

	function storageAvailable( type ) {
		try {
			var s = window[ type ];
			var x = '__flibup_test__';
			s.setItem( x, x );
			s.removeItem( x );
			return true;
		} catch ( e ) {
			return false;
		}
	}

	var hasLocal = storageAvailable( 'localStorage' );
	var hasSession = storageAvailable( 'sessionStorage' );

	function setCookie( name, value, days ) {
		var expires = '';
		if ( days ) {
			var d = new Date();
			d.setTime( d.getTime() + days * 24 * 60 * 60 * 1000 );
			expires = '; expires=' + d.toUTCString();
		}
		document.cookie = name + '=' + encodeURIComponent( value ) + expires + '; path=/; SameSite=Lax';
	}

	function getCookie( name ) {
		var parts = document.cookie ? document.cookie.split( ';' ) : [];
		for ( var i = 0; i < parts.length; i++ ) {
			var c = parts[ i ].trim();
			if ( c.indexOf( name + '=' ) === 0 ) {
				return decodeURIComponent( c.substring( name.length + 1 ) );
			}
		}
		return null;
	}

	function keyFor( config ) {
		return 'flibup:' + config.id + ':' + config.campaign;
	}

	function nowSeconds() {
		return Math.floor( Date.now() / 1000 );
	}

	/**
	 * La pop-up a-t-elle déjà été vue selon son mode de fréquence ?
	 */
	function alreadySeen( config ) {
		var key = keyFor( config );

		if ( config.frequency === 'session' ) {
			if ( hasSession ) {
				return window.sessionStorage.getItem( key ) === '1';
			}
			return getCookie( key ) === '1';
		}

		if ( config.frequency === 'visitor' || config.frequency === 'days' ) {
			var raw = hasLocal ? window.localStorage.getItem( key ) : getCookie( key );
			if ( ! raw ) {
				return false;
			}
			var expiry = parseInt( raw, 10 );
			if ( isNaN( expiry ) ) {
				return true; // Valeur « 1 » sans expiration (visitor via cookie court).
			}
			return nowSeconds() < expiry;
		}

		return false; // always
	}

	/**
	 * Marque la pop-up comme vue.
	 */
	function markSeen( config ) {
		var key = keyFor( config );

		if ( config.frequency === 'session' ) {
			if ( hasSession ) {
				window.sessionStorage.setItem( key, '1' );
			} else {
				setCookie( key, '1', 0 );
			}
			return;
		}

		if ( config.frequency === 'visitor' ) {
			var expV = nowSeconds() + ( config.cookieDays || 365 ) * 86400;
			if ( hasLocal ) {
				window.localStorage.setItem( key, String( expV ) );
			} else {
				setCookie( key, '1', config.cookieDays || 365 );
			}
			return;
		}

		if ( config.frequency === 'days' ) {
			var expD = nowSeconds() + ( config.frequencyDays || 7 ) * 86400;
			if ( hasLocal ) {
				window.localStorage.setItem( key, String( expD ) );
			} else {
				setCookie( key, '1', config.frequencyDays || 7 );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Éligibilité (programmation + fréquence), vérifiée côté navigateur.  */
	/* ------------------------------------------------------------------ */

	function isEligible( config ) {
		if ( config.preview ) {
			return true;
		}

		var now = nowSeconds();
		if ( config.startTs && now < config.startTs ) {
			return false;
		}
		if ( config.endTs && now > config.endTs ) {
			return false;
		}
		if ( alreadySeen( config ) ) {
			return false;
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Gestion du focus.                                                   */
	/* ------------------------------------------------------------------ */

	var FOCUSABLE = 'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

	function getFocusable( container ) {
		return Array.prototype.slice.call( container.querySelectorAll( FOCUSABLE ) ).filter( function ( el ) {
			return el.offsetParent !== null || el === document.activeElement;
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Contrôleur d'une pop-up.                                            */
	/* ------------------------------------------------------------------ */

	function Popup( overlay, config ) {
		this.overlay = overlay;
		this.config = config;
		this.dialog = overlay.querySelector( '.flibup-dialog' );
		this.closeBtn = overlay.querySelector( '.flibup-close' );
		this.previouslyFocused = null;
		this.onKeydown = this.handleKeydown.bind( this );
		this.onOverlayClick = this.handleOverlayClick.bind( this );
	}

	Popup.prototype.open = function () {
		var self = this;

		// La pop-up est comptée comme vue dès l'ouverture.
		markSeen( this.config );

		this.previouslyFocused = document.activeElement;

		this.overlay.hidden = false;

		if ( this.config.blockScroll ) {
			document.documentElement.classList.add( 'flibup-no-scroll' );
			document.body.classList.add( 'flibup-no-scroll' );
		}

		// Écouteurs.
		this.closeBtn.addEventListener( 'click', function () {
			self.close();
		} );
		if ( this.config.closeOnOverlay ) {
			this.overlay.addEventListener( 'click', this.onOverlayClick );
		}
		document.addEventListener( 'keydown', this.onKeydown );

		// Empêche la fermeture au clic dans la pop-up.
		this.dialog.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
		} );

		// Animation d'apparition.
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( this.config.animDisabled || reduce ) {
			this.overlay.classList.add( 'flibup-is-open' );
		} else {
			// Force un reflow pour déclencher la transition.
			void this.overlay.offsetWidth;
			requestAnimationFrame( function () {
				self.overlay.classList.add( 'flibup-is-open' );
			} );
		}

		// Focus initial.
		var focusables = getFocusable( this.dialog );
		if ( focusables.length ) {
			focusables[ 0 ].focus();
		} else {
			this.dialog.focus();
		}
	};

	Popup.prototype.handleOverlayClick = function ( e ) {
		if ( e.target === this.overlay ) {
			this.close();
		}
	};

	Popup.prototype.handleKeydown = function ( e ) {
		if ( ( e.key === 'Escape' || e.key === 'Esc' ) && this.config.closeOnEsc ) {
			e.preventDefault();
			this.close();
			return;
		}

		if ( e.key === 'Tab' ) {
			var focusables = getFocusable( this.dialog );
			if ( ! focusables.length ) {
				e.preventDefault();
				this.dialog.focus();
				return;
			}
			var first = focusables[ 0 ];
			var last = focusables[ focusables.length - 1 ];

			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}
	};

	Popup.prototype.close = function () {
		var self = this;

		document.removeEventListener( 'keydown', this.onKeydown );
		this.overlay.removeEventListener( 'click', this.onOverlayClick );
		this.overlay.classList.remove( 'flibup-is-open' );

		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var delay = ( this.config.animDisabled || reduce ) ? 0 : ( this.config.animSpeed || 0 );

		window.setTimeout( function () {
			self.overlay.hidden = true;

			document.documentElement.classList.remove( 'flibup-no-scroll' );
			document.body.classList.remove( 'flibup-no-scroll' );

			// Restaure le focus.
			if ( self.previouslyFocused && typeof self.previouslyFocused.focus === 'function' ) {
				self.previouslyFocused.focus();
			}

			// Passe à la pop-up suivante de la file.
			if ( typeof self.onClosed === 'function' ) {
				self.onClosed();
			}
		}, delay );
	};

	/* ------------------------------------------------------------------ */
	/* File d'attente et initialisation.                                   */
	/* ------------------------------------------------------------------ */

	function init() {
		var overlays = Array.prototype.slice.call( document.querySelectorAll( '.flibup-overlay' ) );
		if ( ! overlays.length ) {
			return;
		}

		var queue = [];

		overlays.forEach( function ( overlay ) {
			var config;
			try {
				config = JSON.parse( overlay.getAttribute( 'data-flibup' ) );
			} catch ( e ) {
				return;
			}
			if ( ! isEligible( config ) ) {
				return;
			}
			queue.push( { overlay: overlay, config: config } );
		} );

		if ( ! queue.length ) {
			return;
		}

		// Tri par priorité décroissante (au cas où l'ordre du DOM diffère).
		queue.sort( function ( a, b ) {
			return ( b.config.priority || 0 ) - ( a.config.priority || 0 );
		} );

		// Sans mode multiple : une seule pop-up.
		if ( ! settings.allowMultiple ) {
			queue = queue.slice( 0, 1 );
		}

		var index = 0;

		function showNext() {
			if ( index >= queue.length ) {
				return;
			}
			var item = queue[ index ];
			index++;

			var popup = new Popup( item.overlay, item.config );
			popup.onClosed = function () {
				if ( settings.allowMultiple ) {
					showNext();
				}
			};

			var delayMs = item.config.preview ? 0 : ( item.config.triggerMode === 'delay' ? ( item.config.triggerDelayMs || 0 ) : 0 );

			if ( delayMs > 0 ) {
				window.setTimeout( function () {
					popup.open();
				}, delayMs );
			} else {
				popup.open();
			}
		}

		showNext();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
