/**
 * STC Product Showcase Pro – front-end behaviour.
 * Vanilla JS, no jQuery dependency.
 */
( function () {
	'use strict';

	var vars = window.stcPspVars || { ajaxUrl: '', nonce: '', i18n: {} };

	/* ----------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------- */
	function ajax( params, onDone, onFail ) {
		var body = new URLSearchParams();
		Object.keys( params ).forEach( function ( key ) {
			body.append( key, params[ key ] );
		} );

		fetch( vars.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( json ) { ( onDone || function () {} )( json ); } )
			.catch( function ( err ) { ( onFail || function () {} )( err ); } );
	}

	/* ----------------------------------------------------------------
	 * Read More / Read Less
	 * ------------------------------------------------------------- */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.stc-psp-readmore' );
		if ( ! btn ) {
			return;
		}
		var desc = btn.closest( '.stc-psp-desc' );
		if ( ! desc ) {
			return;
		}
		var shortEl = desc.querySelector( '.stc-psp-desc-short' );
		var fullEl = desc.querySelector( '.stc-psp-desc-full' );
		if ( ! shortEl || ! fullEl ) {
			return;
		}
		var expanded = ! fullEl.hasAttribute( 'hidden' );
		if ( expanded ) {
			fullEl.setAttribute( 'hidden', '' );
			shortEl.removeAttribute( 'hidden' );
			btn.textContent = btn.getAttribute( 'data-more' );
		} else {
			fullEl.removeAttribute( 'hidden' );
			shortEl.setAttribute( 'hidden', '' );
			btn.textContent = btn.getAttribute( 'data-less' );
		}
	} );

	/* ----------------------------------------------------------------
	 * Load More / Infinite Scroll
	 * ------------------------------------------------------------- */
	function initWrapper( wrapper ) {
		var config;
		try {
			config = JSON.parse( wrapper.getAttribute( 'data-stc-psp' ) || '{}' );
		} catch ( err ) {
			return;
		}

		var grid = wrapper.querySelector( '.stc-psp-grid' );
		var btn = wrapper.querySelector( '.stc-psp-loadmore' );
		var spinner = wrapper.querySelector( '.stc-psp-spinner' );
		var currentPage = 1;
		var loading = false;
		var maxPages = parseInt( config.max_pages, 10 ) || 1;

		function loadNext() {
			if ( loading || currentPage >= maxPages ) {
				return;
			}
			loading = true;
			if ( spinner ) { spinner.removeAttribute( 'hidden' ); }
			if ( btn ) { btn.disabled = true; }

			ajax(
				{
					action: 'stc_psp_load_products',
					nonce: vars.nonce,
					page: currentPage + 1,
					settings: JSON.stringify( config.settings || {} ),
				},
				function ( json ) {
					loading = false;
					if ( spinner ) { spinner.setAttribute( 'hidden', '' ); }
					if ( btn ) { btn.disabled = false; }

					if ( json && json.success && json.data && json.data.html ) {
						grid.insertAdjacentHTML( 'beforeend', json.data.html );
						currentPage = json.data.page;
						if ( ! json.data.has_more && btn ) {
							btn.parentNode.style.display = 'none';
						}
					}
				},
				function () {
					loading = false;
					if ( spinner ) { spinner.setAttribute( 'hidden', '' ); }
					if ( btn ) { btn.disabled = false; }
				}
			);
		}

		if ( btn ) {
			btn.addEventListener( 'click', loadNext );
		}

		if ( config.pagination === 'infinite' ) {
			var sentinel = document.createElement( 'div' );
			sentinel.className = 'stc-psp-sentinel';
			wrapper.appendChild( sentinel );

			if ( 'IntersectionObserver' in window ) {
				var observer = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting ) {
							loadNext();
						}
					} );
				}, { rootMargin: '300px' } );
				observer.observe( sentinel );
			}
		}
	}

	/* ----------------------------------------------------------------
	 * Popup (enquiry)
	 * ------------------------------------------------------------- */
	function getPopup() {
		return document.getElementById( 'stc-psp-popup' );
	}

	function openPopup( data ) {
		var popup = getPopup();
		if ( ! popup ) {
			return;
		}

		var map = {
			'product_id': '.stc-psp-meta-product-id',
			'product_name': '.stc-psp-meta-product-name',
			'product_sku': '.stc-psp-meta-product-sku',
			'product_category': '.stc-psp-meta-product-category',
			'product_url': '.stc-psp-meta-product-url',
		};
		Object.keys( map ).forEach( function ( key ) {
			var field = popup.querySelector( map[ key ] );
			if ( field ) {
				field.value = data[ key ] || '';
			}
		} );

		var nameEl = popup.querySelector( '.stc-psp-popup-product' );
		if ( nameEl ) {
			nameEl.textContent = data.product_name || '';
		}

		// Reset previous messages.
		var msg = popup.querySelector( '.stc-psp-form-message' );
		if ( msg ) {
			msg.textContent = '';
			msg.className = 'stc-psp-form-message';
		}

		popup.classList.add( 'is-open' );
		popup.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';
	}

	function closePopup() {
		var popup = getPopup();
		if ( ! popup ) {
			return;
		}
		popup.classList.remove( 'is-open' );
		popup.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';
	}

	document.addEventListener( 'click', function ( e ) {
		var enquire = e.target.closest( '.stc-psp-enquire-btn' );
		if ( enquire ) {
			e.preventDefault();
			openPopup( {
				product_id: enquire.getAttribute( 'data-product-id' ),
				product_name: enquire.getAttribute( 'data-product-name' ),
				product_sku: enquire.getAttribute( 'data-product-sku' ),
				product_category: enquire.getAttribute( 'data-product-category' ),
				product_url: enquire.getAttribute( 'data-product-url' ),
			} );
			return;
		}

		if ( e.target.closest( '.stc-psp-popup-close' ) ) {
			closePopup();
			return;
		}

		if ( e.target.classList && e.target.classList.contains( 'stc-psp-popup-overlay' ) ) {
			closePopup();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			closePopup();
		}
	} );

	/* ----------------------------------------------------------------
	 * Enquiry form submit
	 * ------------------------------------------------------------- */
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest( '.stc-psp-enquiry-form' );
		if ( ! form ) {
			return;
		}
		e.preventDefault();

		var msg = form.querySelector( '.stc-psp-form-message' );
		var submit = form.querySelector( '.stc-psp-submit-btn' );
		var original = submit ? submit.textContent : '';

		if ( submit ) {
			submit.disabled = true;
			submit.textContent = ( vars.i18n && vars.i18n.sending ) || 'Sending...';
		}
		if ( msg ) {
			msg.textContent = '';
			msg.className = 'stc-psp-form-message';
		}

		var formData = new FormData( form );

		fetch( vars.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( json ) {
				if ( submit ) {
					submit.disabled = false;
					submit.textContent = original;
				}
				if ( json && json.success ) {
					if ( msg ) {
						msg.textContent = json.data.message || 'Thank you!';
						msg.className = 'stc-psp-form-message is-success';
					}
					form.reset();
				} else if ( msg ) {
					msg.textContent = ( json && json.data && json.data.message ) || ( vars.i18n && vars.i18n.error ) || 'Error';
					msg.className = 'stc-psp-form-message is-error';
				}
			} )
			.catch( function () {
				if ( submit ) {
					submit.disabled = false;
					submit.textContent = original;
				}
				if ( msg ) {
					msg.textContent = ( vars.i18n && vars.i18n.error ) || 'Error';
					msg.className = 'stc-psp-form-message is-error';
				}
			} );
	} );

	/* ----------------------------------------------------------------
	 * Download tracking
	 * ------------------------------------------------------------- */
	document.addEventListener( 'click', function ( e ) {
		var dl = e.target.closest( '.stc-psp-btn-download' );
		if ( ! dl || dl.getAttribute( 'data-track' ) !== '1' ) {
			return;
		}
		// Fire and forget — do not block the navigation.
		ajax(
			{
				action: 'stc_psp_track_download',
				nonce: vars.nonce,
				product_id: dl.getAttribute( 'data-product-id' ) || 0,
			},
			function ( json ) {
				if ( json && json.success && json.data && typeof json.data.count !== 'undefined' ) {
					var counter = dl.querySelector( '.stc-psp-dl-count' );
					if ( counter ) {
						counter.textContent = json.data.count;
					}
				}
			}
		);
	} );

	/* ----------------------------------------------------------------
	 * Init
	 * ------------------------------------------------------------- */
	function init() {
		document.querySelectorAll( '.stc-psp-wrapper' ).forEach( initWrapper );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Re-init for Elementor editor previews.
	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', init );
	}
} )();
