/**
 * Flib'Up — logique publique (JavaScript natif, sans dépendance).
 *
 * Une pop-up ouverte automatiquement est considérée comme « vue » dès son
 * OUVERTURE : c'est le comportement le plus robuste pour un plafonnement de
 * fréquence, car un visiteur qui l'ignore sans la fermer ne la reverra pas à
 * chaque page.
 *
 * Les pop-ups déclenchées au clic répondent toujours à l'action du visiteur :
 * elles ignorent par défaut le plafond de fréquence.
 */
( function () {
	'use strict';

	var settings = window.flibupPublic || { allowMultiple: false, preview: false };

	/* Instances construites, indexées par identifiant de pop-up. */
	var instances = {};

	/* Pile des pop-ups ouvertes (la dernière reçoit la touche Échap). */
	var openStack = [];

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
	/* Éligibilité.                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * La fenêtre de diffusion (dates de début/fin) est-elle ouverte ?
	 */
	function inSchedule( config ) {
		var now = nowSeconds();
		if ( config.startTs && now < config.startTs ) {
			return false;
		}
		if ( config.endTs && now > config.endTs ) {
			return false;
		}
		return true;
	}

	/**
	 * Éligibilité d'une ouverture automatique (chargement ou délai).
	 */
	function isAutoEligible( config ) {
		if ( config.preview ) {
			return true;
		}
		if ( ! inSchedule( config ) ) {
			return false;
		}
		return ! alreadySeen( config );
	}

	/**
	 * Éligibilité d'une ouverture provoquée par le visiteur.
	 */
	function isClickEligible( config ) {
		if ( config.preview ) {
			return true;
		}
		if ( ! inSchedule( config ) ) {
			return false;
		}
		if ( config.ignoreFrequency ) {
			return true;
		}
		return ! alreadySeen( config );
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
		this.isOpen = false;
		this.closeTimer = null;

		this.onKeydown = this.handleKeydown.bind( this );
		this.onOverlayClick = this.handleOverlayClick.bind( this );

		this.bindStaticListeners();
	}

	/**
	 * Écouteurs posés une seule fois : une pop-up déclenchée au clic peut être
	 * ouverte et fermée un nombre indéfini de fois.
	 */
	Popup.prototype.bindStaticListeners = function () {
		var self = this;

		if ( this.closeBtn ) {
			this.closeBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				self.close();
			} );
		}

		// Empêche la fermeture au clic à l'intérieur de la pop-up.
		if ( this.dialog ) {
			this.dialog.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
			} );
		}

		// Un élément portant data-flibup-close ferme la pop-up qui le contient.
		this.overlay.addEventListener( 'click', function ( e ) {
			var closer = e.target.closest ? e.target.closest( '[data-flibup-close]' ) : null;
			if ( closer && self.overlay.contains( closer ) ) {
				e.preventDefault();
				self.close();
			}
		} );
	};

	/**
	 * Ouvre la pop-up.
	 *
	 * @param {Object} opts Options : { focus: bool } pour déplacer le focus.
	 */
	Popup.prototype.open = function ( opts ) {
		var self = this;
		opts = opts || {};

		if ( this.isOpen ) {
			return;
		}
		this.isOpen = true;

		if ( this.closeTimer ) {
			window.clearTimeout( this.closeTimer );
			this.closeTimer = null;
		}

		// La pop-up est comptée comme vue dès l'ouverture.
		markSeen( this.config );

		this.previouslyFocused = document.activeElement;
		this.overlay.hidden = false;

		if ( this.config.blockScroll ) {
			document.documentElement.classList.add( 'flibup-no-scroll' );
			document.body.classList.add( 'flibup-no-scroll' );
		}

		if ( this.config.closeOnOverlay ) {
			this.overlay.addEventListener( 'click', this.onOverlayClick );
		}
		document.addEventListener( 'keydown', this.onKeydown );

		openStack.push( this );

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

		// Focus initial : systématique en mode modal, réservé aux ouvertures
		// provoquées par le visiteur en mode non bloquant (une notification qui
		// vole le focus est très gênante).
		var shouldFocus = ( this.config.modal !== false ) || opts.focus === true;
		if ( shouldFocus ) {
			var focusables = getFocusable( this.dialog );
			if ( focusables.length ) {
				focusables[ 0 ].focus();
			} else if ( this.dialog ) {
				this.dialog.focus();
			}
		}
	};

	Popup.prototype.handleOverlayClick = function ( e ) {
		if ( e.target === this.overlay ) {
			this.close();
		}
	};

	Popup.prototype.handleKeydown = function ( e ) {
		// Seule la pop-up au sommet de la pile réagit.
		if ( openStack[ openStack.length - 1 ] !== this ) {
			return;
		}

		if ( e.key === 'Escape' || e.key === 'Esc' ) {
			if ( ! this.config.closeOnEsc ) {
				return;
			}
			// En mode non bloquant, on ne capte Échap que si le focus est dans
			// la pop-up : le reste de la page doit rester libre.
			if ( this.config.modal === false && this.dialog && ! this.dialog.contains( document.activeElement ) ) {
				return;
			}
			e.preventDefault();
			this.close();
			return;
		}

		// Piège de focus : uniquement en mode modal.
		if ( e.key === 'Tab' && this.config.modal !== false ) {
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

		if ( ! this.isOpen ) {
			return;
		}
		this.isOpen = false;

		document.removeEventListener( 'keydown', this.onKeydown );
		this.overlay.removeEventListener( 'click', this.onOverlayClick );
		this.overlay.classList.remove( 'flibup-is-open' );

		var stackIndex = openStack.indexOf( this );
		if ( stackIndex !== -1 ) {
			openStack.splice( stackIndex, 1 );
		}

		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var delay = ( this.config.animDisabled || reduce ) ? 0 : ( this.config.animSpeed || 0 );

		this.closeTimer = window.setTimeout( function () {
			self.closeTimer = null;
			self.overlay.hidden = true;

			// Le défilement n'est rétabli que si plus aucune pop-up ne le bloque.
			var stillBlocking = openStack.some( function ( p ) {
				return p.config.blockScroll;
			} );
			if ( ! stillBlocking ) {
				document.documentElement.classList.remove( 'flibup-no-scroll' );
				document.body.classList.remove( 'flibup-no-scroll' );
			}

			// Restaure le focus s'il se trouve encore dans la pop-up.
			if ( self.previouslyFocused && typeof self.previouslyFocused.focus === 'function' ) {
				if ( ! self.dialog || self.dialog.contains( document.activeElement ) || document.activeElement === document.body ) {
					self.previouslyFocused.focus();
				}
			}

			// Passe à la pop-up suivante de la file.
			if ( typeof self.onClosed === 'function' ) {
				self.onClosed();
			}
		}, delay );
	};

	/* ------------------------------------------------------------------ */
	/* Déclencheurs au clic.                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Renvoie l'identifiant de pop-up visé par un élément cliqué, ou null.
	 */
	function resolveTargetId( element ) {
		if ( ! element || ! element.closest ) {
			return null;
		}

		// 1. Attribut explicite data-flibup-open="12".
		var explicit = element.closest( '[data-flibup-open]' );
		if ( explicit ) {
			var attrId = parseInt( explicit.getAttribute( 'data-flibup-open' ), 10 );
			if ( ! isNaN( attrId ) ) {
				return attrId;
			}
		}

		// 2. Lien dont l'ancre est #flibup-12.
		var anchor = element.closest( 'a[href*="#flibup-"]' );
		if ( anchor ) {
			var match = /#flibup-(\d+)$/.exec( anchor.getAttribute( 'href' ) || '' );
			if ( match ) {
				return parseInt( match[ 1 ], 10 );
			}
		}

		// 3. Sélecteur CSS configuré dans l'administration.
		for ( var id in instances ) {
			if ( ! Object.prototype.hasOwnProperty.call( instances, id ) ) {
				continue;
			}
			var selector = instances[ id ].config.triggerSelector;
			if ( ! selector ) {
				continue;
			}
			try {
				if ( element.closest( selector ) ) {
					return parseInt( id, 10 );
				}
			} catch ( e ) {
				// Sélecteur invalide : on l'ignore silencieusement.
			}
		}

		return null;
	}

	function handleDocumentClick( e ) {
		if ( e.button !== undefined && e.button !== 0 ) {
			return;
		}
		if ( e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return;
		}

		var id = resolveTargetId( e.target );
		if ( null === id ) {
			return;
		}

		var entry = instances[ id ];
		if ( ! entry ) {
			return;
		}

		if ( ! isClickEligible( entry.config ) ) {
			return;
		}

		e.preventDefault();
		entry.popup.open( { focus: true } );
	}

	/* ------------------------------------------------------------------ */
	/* Interface publique.                                                 */
	/* ------------------------------------------------------------------ */

	var api = {
		/**
		 * Ouvre une pop-up par son identifiant.
		 *
		 * @param {number}  id    Identifiant de la pop-up.
		 * @param {boolean} force Ignorer le plafond de fréquence et les dates.
		 * @return {boolean} Vrai si la pop-up a été ouverte.
		 */
		open: function ( id, force ) {
			var entry = instances[ parseInt( id, 10 ) ];
			if ( ! entry ) {
				return false;
			}
			if ( ! force && ! isClickEligible( entry.config ) ) {
				return false;
			}
			entry.popup.open( { focus: true } );
			return true;
		},

		/**
		 * Ferme une pop-up par son identifiant, ou toutes si aucun n'est fourni.
		 *
		 * @param {number} [id] Identifiant de la pop-up.
		 * @return {void}
		 */
		close: function ( id ) {
			if ( id === undefined || id === null ) {
				openStack.slice().forEach( function ( p ) {
					p.close();
				} );
				return;
			}
			var entry = instances[ parseInt( id, 10 ) ];
			if ( entry ) {
				entry.popup.close();
			}
		},

		/**
		 * Réinitialise la mémorisation « déjà vue » d'une pop-up.
		 *
		 * @param {number} id Identifiant de la pop-up.
		 * @return {void}
		 */
		reset: function ( id ) {
			var entry = instances[ parseInt( id, 10 ) ];
			if ( ! entry ) {
				return;
			}
			var key = keyFor( entry.config );
			if ( hasLocal ) {
				window.localStorage.removeItem( key );
			}
			if ( hasSession ) {
				window.sessionStorage.removeItem( key );
			}
			setCookie( key, '', -1 );
		}
	};

	/* ------------------------------------------------------------------ */
	/* File d'attente et initialisation.                                   */
	/* ------------------------------------------------------------------ */

	function init() {
		var overlays = Array.prototype.slice.call( document.querySelectorAll( '.flibup-overlay' ) );
		if ( ! overlays.length ) {
			return;
		}

		var autoQueue = [];

		overlays.forEach( function ( overlay ) {
			var config;
			try {
				config = JSON.parse( overlay.getAttribute( 'data-flibup' ) );
			} catch ( e ) {
				return;
			}
			if ( ! config || ! config.id ) {
				return;
			}

			var popup = new Popup( overlay, config );
			instances[ config.id ] = { overlay: overlay, config: config, popup: popup };

			if ( config.triggerMode === 'click' ) {
				// En prévisualisation, on ouvre quand même pour voir le rendu.
				if ( config.preview ) {
					autoQueue.push( instances[ config.id ] );
				}
				return;
			}

			if ( isAutoEligible( config ) ) {
				autoQueue.push( instances[ config.id ] );
			}
		} );

		// L'écoute des clics est globale et déléguée : elle couvre aussi les
		// boutons injectés après le chargement (constructeurs de page, AJAX…).
		// Toute pop-up présente dans la page peut ainsi être ouverte au clic,
		// quel que soit son mode de déclenchement.
		document.addEventListener( 'click', handleDocumentClick );

		if ( ! autoQueue.length ) {
			return;
		}

		// Tri par priorité décroissante (au cas où l'ordre du DOM diffère).
		autoQueue.sort( function ( a, b ) {
			return ( b.config.priority || 0 ) - ( a.config.priority || 0 );
		} );

		// Sans mode multiple : une seule pop-up automatique.
		if ( ! settings.allowMultiple ) {
			autoQueue = autoQueue.slice( 0, 1 );
		}

		var index = 0;

		function showNext() {
			if ( index >= autoQueue.length ) {
				return;
			}
			var item = autoQueue[ index ];
			index++;

			item.popup.onClosed = function () {
				if ( settings.allowMultiple ) {
					showNext();
				}
			};

			var delayMs = item.config.preview
				? 0
				: ( item.config.triggerMode === 'delay' ? ( item.config.triggerDelayMs || 0 ) : 0 );

			if ( delayMs > 0 ) {
				window.setTimeout( function () {
					item.popup.open();
				}, delayMs );
			} else {
				item.popup.open();
			}
		}

		showNext();
	}

	window.FlibUp = api;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
