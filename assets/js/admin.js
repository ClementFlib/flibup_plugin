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
