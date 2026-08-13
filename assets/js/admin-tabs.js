( function () {
	'use strict';

	function setActiveTab( tabId ) {
		document.querySelectorAll( '.forwp-seo-tab' ).forEach( function ( btn ) {
			var active = btn.getAttribute( 'data-tab' ) === tabId;
			btn.classList.toggle( 'is-active', active );
			btn.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			btn.tabIndex = active ? 0 : -1;
		} );

		document.querySelectorAll( '.forwp-seo-tab-panel [role="tabpanel"]' ).forEach( function ( panel ) {
			var show = panel.id === 'forwp-seo-panel-' + tabId;
			panel.hidden = ! show;
		} );

		if ( window.history && window.history.replaceState ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'tab', tabId );
			window.history.replaceState( {}, '', url.toString() );
		}
	}

	function initTabs() {
		var shell = document.querySelector( '.forwp-seo-tab-panel' );
		if ( ! shell ) {
			return;
		}

		shell.querySelectorAll( '.forwp-seo-tab' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var tab = btn.getAttribute( 'data-tab' );
				if ( tab ) {
					setActiveTab( tab );
				}
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', initTabs );
} )();
