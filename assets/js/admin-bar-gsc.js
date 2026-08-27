( function () {
	'use strict';

	var cfg = window.forwpSeoAdminBarGsc;
	if ( ! cfg || ! cfg.postId ) {
		return;
	}

	var link = document.querySelector( '#wp-admin-bar-forwp-seo-admin-bar-gsc > a.forwp-seo-ab-gsc-link' );
	if ( ! link ) {
		return;
	}

	function openUrl( url ) {
		if ( ! url ) {
			return false;
		}
		window.open( url, '_blank', 'noopener,noreferrer' );
		return true;
	}

	function pickInspectUrl( response ) {
		if ( ! response || typeof response !== 'object' ) {
			return '';
		}
		return response.gscInspectUrl || ( response.inspect && response.inspect.inspectLink ) || '';
	}

	function apiInspect() {
		var url = ( cfg.restRoot || '/wp-json/forwp-seo/v1/' ).replace( /\/?$/, '/' ) +
			'gsc/post-index?post_id=' + encodeURIComponent( String( cfg.postId ) ) + '&inspect=1';

		return fetch( url, {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce || '',
			},
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			return response.json();
		} );
	}

	link.addEventListener( 'click', function ( event ) {
		if ( cfg.storedUrl ) {
			return;
		}

		event.preventDefault();

		if ( link.classList.contains( 'is-forwp-gsc-busy' ) ) {
			return;
		}

		link.classList.add( 'is-forwp-gsc-busy' );
		var label = link.querySelector( '.forwp-seo-ab-label' );
		var previous = label ? label.textContent : '';
		if ( label && cfg.i18n && cfg.i18n.opening ) {
			label.textContent = cfg.i18n.opening;
		}

		apiInspect()
			.then( function ( response ) {
				var inspectUrl = pickInspectUrl( response );
				if ( openUrl( inspectUrl ) ) {
					cfg.storedUrl = inspectUrl;
					link.setAttribute( 'href', inspectUrl );
					return;
				}
				if ( openUrl( cfg.propertyUrl ) ) {
					return;
				}
				window.alert( ( cfg.i18n && cfg.i18n.error ) || 'Search Console inspect failed.' );
			} )
			.catch( function () {
				if ( ! openUrl( cfg.propertyUrl ) ) {
					window.alert( ( cfg.i18n && cfg.i18n.error ) || 'Search Console inspect failed.' );
				}
			} )
			.finally( function () {
				link.classList.remove( 'is-forwp-gsc-busy' );
				if ( label && previous ) {
					label.textContent = previous;
				}
			} );
	} );
}() );
