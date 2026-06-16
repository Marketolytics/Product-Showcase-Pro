/**
 * STC Product Showcase Pro – admin behaviour.
 */
( function ( $ ) {
	'use strict';

	var admin = window.stcPspAdmin || {};

	$( function () {

		/* ------------------------------------------------------------
		 * Settings tabs
		 * --------------------------------------------------------- */
		var $tabs = $( '.stc-psp-tabs .nav-tab' );
		if ( $tabs.length ) {
			$tabs.on( 'click', function ( e ) {
				e.preventDefault();
				var target = $( this ).attr( 'href' );
				$tabs.removeClass( 'nav-tab-active' );
				$( this ).addClass( 'nav-tab-active' );
				$( '.stc-psp-tab-panel' ).hide();
				$( target ).show();
			} );
		}

		/* ------------------------------------------------------------
		 * Form builder: sortable + add/remove rows
		 * --------------------------------------------------------- */
		var $list = $( '.stc-psp-fb-list' );
		if ( $list.length && $.fn.sortable ) {
			$list.sortable( {
				handle: '.stc-psp-fb-handle',
				placeholder: 'stc-psp-fb-row',
				axis: 'y',
				update: reindexRows,
			} );
		}

		function reindexRows() {
			$( '.stc-psp-fb-list .stc-psp-fb-row' ).each( function ( i ) {
				$( this ).attr( 'data-index', i );
				$( this ).find( 'input, select, textarea' ).each( function () {
					var name = $( this ).attr( 'name' );
					if ( name ) {
						$( this ).attr( 'name', name.replace( /\[form_fields\]\[[^\]]*\]/, '[form_fields][' + i + ']' ) );
					}
				} );
			} );
		}

		$( document ).on( 'click', '.stc-psp-fb-add', function ( e ) {
			e.preventDefault();
			var tpl = $( '#stc-psp-fb-template' ).html();
			if ( ! tpl ) {
				return;
			}
			var index = $( '.stc-psp-fb-list .stc-psp-fb-row' ).length;
			var html = tpl.replace( /__INDEX__/g, index );
			$list.append( html );
			reindexRows();
		} );

		$( document ).on( 'click', '.stc-psp-fb-remove', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.stc-psp-fb-row' ).remove();
			reindexRows();
		} );

		// Ensure disabled checkboxes still post a value of "0" is not needed;
		// unchecked simply omits, and PHP treats missing as false.

		/* ------------------------------------------------------------
		 * Product editor: PDF media uploader
		 * --------------------------------------------------------- */
		var frame;
		$( document ).on( 'click', '.stc-psp-select-pdf', function ( e ) {
			e.preventDefault();
			if ( frame ) {
				frame.open();
				return;
			}
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			frame = window.wp.media( {
				title: admin.selectPdfTitle || 'Select PDF',
				button: { text: admin.selectPdfBtn || 'Use this file' },
				library: { type: [ 'application/pdf' ] },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$( '#stc_psp_pdf_url' ).val( attachment.url );
				$( '#stc_psp_pdf_id' ).val( attachment.id );
				$( '#stc_psp_pdf_source' ).val( 'media' );
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.stc-psp-remove-pdf', function ( e ) {
			e.preventDefault();
			$( '#stc_psp_pdf_url' ).val( '' );
			$( '#stc_psp_pdf_id' ).val( '' );
		} );

		/* ------------------------------------------------------------
		 * Product editor: multiple downloads repeater
		 * --------------------------------------------------------- */
		$( document ).on( 'click', '.stc-psp-dl-add', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.stc-psp-downloads-repeater' );
			var tpl = $( '#stc-psp-dl-template' ).html();
			if ( ! tpl ) {
				return;
			}
			// Unique index avoids name collisions regardless of removals.
			var index = 'n' + Date.now();
			$wrap.find( '.stc-psp-dl-rows' ).append( tpl.replace( /__INDEX__/g, index ) );
		} );

		$( document ).on( 'click', '.stc-psp-dl-remove', function ( e ) {
			e.preventDefault();
			$( this ).closest( '.stc-psp-dl-row' ).remove();
		} );

		var dlFrame;
		var $dlRow;
		$( document ).on( 'click', '.stc-psp-dl-select', function ( e ) {
			e.preventDefault();
			$dlRow = $( this ).closest( '.stc-psp-dl-row' );
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( dlFrame ) {
				dlFrame.open();
				return;
			}
			dlFrame = window.wp.media( {
				title: admin.selectPdfTitle || 'Select file',
				button: { text: admin.selectPdfBtn || 'Use this file' },
				multiple: false,
			} );
			dlFrame.on( 'select', function () {
				var attachment = dlFrame.state().get( 'selection' ).first().toJSON();
				if ( $dlRow ) {
					$dlRow.find( '.stc-psp-dl-url' ).val( attachment.url );
					$dlRow.find( '.stc-psp-dl-id' ).val( attachment.id );
				}
			} );
			dlFrame.open();
		} );

	} );
} )( jQuery );
