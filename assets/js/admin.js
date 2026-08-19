/**
 * Flib'Up — scripts d'administration.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.flibupAdmin || {};

	$( function () {
		initTabs();
		initColorPickers();
		initConditionalFields();
		initIdSelectors();
		initPositionGrid();
		initImagePicker();
	} );

	/* --- Onglets --- */
	function initTabs() {
		var $tabs = $( '.flibup-tabs .nav-tab' );
		$tabs.on( 'click', function ( e ) {
			e.preventDefault();
			var target = $( this ).attr( 'href' );

			$tabs.removeClass( 'nav-tab-active' );
			$( this ).addClass( 'nav-tab-active' );

			$( '.flibup-tab' ).removeClass( 'flibup-tab-active' );
			$( target ).addClass( 'flibup-tab-active' );
		} );
	}

	/* --- Sélecteurs de couleur natifs --- */
	function initColorPickers() {
		if ( $.fn.wpColorPicker ) {
			$( '.flibup-color' ).wpColorPicker();
		}
	}

	/* --- Champs conditionnels --- */
	function initConditionalFields() {
		var $mode = $( '#flibup_targeting_mode' );
		var $selected = $( '.flibup-selected-wrap' );

		function toggleTargeting() {
			if ( $mode.val() === 'selected' ) {
				$selected.removeClass( 'flibup-hidden' );
			} else {
				$selected.addClass( 'flibup-hidden' );
			}
		}
		if ( $mode.length ) {
			toggleTargeting();
			$mode.on( 'change', toggleTargeting );
		}

		var $freq = $( '#flibup_frequency_mode' );
		var $days = $( '.flibup-freq-days' );
		var $cookie = $( '.flibup-freq-cookie' );

		function toggleFreq() {
			var val = $freq.val();
			$days.toggleClass( 'flibup-hidden', val !== 'days' );
			$cookie.toggleClass( 'flibup-hidden', val !== 'visitor' );
		}
		if ( $freq.length ) {
			toggleFreq();
			$freq.on( 'change', toggleFreq );
		}

		var $trigger = $( '.flibup-trigger-mode' );

		function toggleTrigger() {
			var val = $trigger.filter( ':checked' ).val();
			$( '.flibup-trigger-click' ).toggleClass( 'flibup-hidden', val !== 'click' );
			$( '.flibup-trigger-delay' ).toggleClass( 'flibup-hidden', val !== 'delay' );
		}
		if ( $trigger.length ) {
			toggleTrigger();
			$trigger.on( 'change', toggleTrigger );
		}
	}

	/* --- Grille de position --- */
	function initPositionGrid() {
		var $grid = $( '.flibup-position-grid' );
		if ( ! $grid.length ) {
			return;
		}

		$grid.on( 'change', 'input[type="radio"]', function () {
			var $cell = $( this ).closest( '.flibup-position-cell' );
			$grid.find( '.flibup-position-cell' ).removeClass( 'is-selected' );
			$cell.addClass( 'is-selected' );
			$( '.flibup-position-label' ).text( $cell.attr( 'title' ) || '' );
		} );
	}

	/* --- Sélecteur d'image (médiathèque) --- */
	function initImagePicker() {
		var $picker = $( '.flibup-image-picker' );
		if ( ! $picker.length ) {
			return;
		}

		var $id = $picker.find( '.flibup-image-id' );
		var $preview = $picker.find( '.flibup-image-preview' );
		var $remove = $picker.find( '.flibup-image-remove' );
		var $url = $picker.find( '.flibup-image-url' );
		var frame = null;

		function refreshVisibility() {
			var has = ( parseInt( $id.val(), 10 ) > 0 ) || $.trim( $url.val() ) !== '';
			$remove.prop( 'hidden', ! has );
			$( '.flibup-image-options' ).toggleClass( 'flibup-hidden', ! has );
		}

		$picker.find( '.flibup-image-select' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( ! window.wp || ! window.wp.media ) {
				return;
			}

			if ( ! frame ) {
				frame = window.wp.media( {
					title: cfg.i18n ? cfg.i18n.mediaTitle : 'Select image',
					button: { text: cfg.i18n ? cfg.i18n.mediaButton : 'Use this image' },
					library: { type: 'image' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var thumb = attachment.url;

					if ( attachment.sizes && attachment.sizes.medium ) {
						thumb = attachment.sizes.medium.url;
					}

					$id.val( attachment.id );
					$preview.find( 'img' ).attr( 'src', thumb );
					$preview.prop( 'hidden', false );
					refreshVisibility();
				} );
			}

			frame.open();
		} );

		$remove.on( 'click', function ( e ) {
			e.preventDefault();
			$id.val( 0 );
			$url.val( '' );
			$preview.prop( 'hidden', true ).find( 'img' ).attr( 'src', '' );
			refreshVisibility();
		} );

		$url.on( 'input', refreshVisibility );

		refreshVisibility();
	}

	/* --- Sélecteurs d'ID avec recherche AJAX --- */
	function initIdSelectors() {
		$( '.flibup-id-selector' ).each( function () {
			setupSelector( $( this ) );
		} );
	}

	function setupSelector( $selector ) {
		var name = $selector.data( 'name' );
		var ptype = $selector.data( 'ptype' );
		var $input = $selector.find( '.flibup-id-search' );
		var $results = $selector.find( '.flibup-id-results' );
		var $chips = $selector.find( '.flibup-chips' );
		var timer = null;

		function currentIds() {
			var ids = [];
			$chips.find( 'input[type="hidden"]' ).each( function () {
				ids.push( String( $( this ).val() ) );
			} );
			return ids;
		}

		function addChip( id, text ) {
			if ( currentIds().indexOf( String( id ) ) !== -1 ) {
				return;
			}
			var $chip = $( '<span class="flibup-chip"></span>' );
			$chip.append( $( '<span class="flibup-chip-label"></span>' ).text( text ) );
			var $remove = $( '<button type="button" class="flibup-chip-remove"></button>' )
				.attr( 'aria-label', cfg.i18n ? cfg.i18n.remove : 'Remove' )
				.html( '&times;' );
			$chip.append( $remove );
			$chip.append(
				$( '<input type="hidden" />' ).attr( 'name', name + '[]' ).val( id )
			);
			$chips.append( $chip );
		}

		$chips.on( 'click', '.flibup-chip-remove', function () {
			$( this ).closest( '.flibup-chip' ).remove();
		} );

		function renderResults( items ) {
			$results.empty();
			if ( ! items.length ) {
				$results.append(
					$( '<li class="flibup-empty"></li>' ).text( cfg.i18n ? cfg.i18n.noResult : 'No result' )
				);
			} else {
				items.forEach( function ( item ) {
					if ( currentIds().indexOf( String( item.id ) ) !== -1 ) {
						return;
					}
					var $li = $( '<li></li>' ).text( item.text ).attr( 'data-id', item.id );
					$results.append( $li );
				} );
			}
			$results.prop( 'hidden', false );
		}

		$results.on( 'click', 'li[data-id]', function () {
			addChip( $( this ).attr( 'data-id' ), $( this ).text() );
			$results.prop( 'hidden', true );
			$input.val( '' );
		} );

		function doSearch( term ) {
			$.ajax( {
				url: cfg.ajaxUrl,
				method: 'GET',
				dataType: 'json',
				data: {
					action: 'flibup_search_content',
					nonce: cfg.searchNonce,
					term: term,
					ptype: ptype
				}
			} ).done( function ( resp ) {
				if ( resp && resp.success && resp.data && resp.data.results ) {
					renderResults( resp.data.results );
				} else {
					renderResults( [] );
				}
			} ).fail( function () {
				renderResults( [] );
			} );
		}

		$input.on( 'input', function () {
			var term = $.trim( $input.val() );
			window.clearTimeout( timer );
			if ( term.length < 2 ) {
				$results.prop( 'hidden', true );
				return;
			}
			timer = window.setTimeout( function () {
				doSearch( term );
			}, 250 );
		} );

		$input.on( 'focus', function () {
			if ( $results.children().length ) {
				$results.prop( 'hidden', false );
			}
		} );

		// Ferme la liste au clic extérieur.
		$( document ).on( 'click', function ( e ) {
			if ( ! $.contains( $selector[ 0 ], e.target ) ) {
				$results.prop( 'hidden', true );
			}
		} );
	}
} )( jQuery );
