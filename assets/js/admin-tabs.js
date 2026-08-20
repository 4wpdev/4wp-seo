( function () {
	'use strict';

	function setActiveTab( shell, tabId ) {
		shell.querySelectorAll( '.forwp-seo-tab' ).forEach( function ( btn ) {
			var active = btn.getAttribute( 'data-tab' ) === tabId;
			btn.classList.toggle( 'is-active', active );
			btn.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			btn.tabIndex = active ? 0 : -1;
		} );

		shell.querySelectorAll( '[role="tabpanel"]' ).forEach( function ( panel ) {
			var show = panel.id === 'forwp-seo-panel-' + tabId;
			panel.hidden = ! show;
		} );

		var rangeBar = shell.querySelector( '#forwp-seo-gsc-range-bar' );
		if ( rangeBar ) {
			rangeBar.hidden = tabId === 'sync' || tabId === 'inspection';
			var tabInput = rangeBar.querySelector( 'input[name="tab"]' );
			if ( tabInput ) {
				tabInput.value = tabId;
			}
		}

		if ( window.history && window.history.replaceState ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'tab', tabId );
			if ( tabId === 'sync' || tabId === 'inspection' ) {
				url.searchParams.delete( 'range' );
			}
			window.history.replaceState( {}, '', url.toString() );
		}
	}

	function initTabPanel( shell ) {
		shell.querySelectorAll( '.forwp-seo-tab' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var tab = btn.getAttribute( 'data-tab' );
				if ( tab ) {
					setActiveTab( shell, tab );
				}
			} );
		} );
	}

	function initGscRangeBar() {
		var select = document.getElementById( 'forwp-seo-gsc-range' );
		if ( ! select ) {
			return;
		}

		var form = select.closest( 'form' );
		if ( form ) {
			var url = new URL( window.location.href );
			var tabInput = form.querySelector( 'input[name="tab"]' );
			if ( tabInput ) {
				tabInput.value = url.searchParams.get( 'tab' ) || tabInput.value || 'overview';
			}
		}

		select.addEventListener( 'change', function () {
			var url = new URL( window.location.href );
			url.searchParams.set( 'range', select.value );
			if ( ! url.searchParams.get( 'tab' ) ) {
				url.searchParams.set( 'tab', 'overview' );
			}
			url.searchParams.delete( 'orderby' );
			url.searchParams.delete( 'order' );
			window.location.assign( url.toString() );
		} );
	}

	function initTabs() {
		document.querySelectorAll( '.forwp-seo-tab-panel' ).forEach( initTabPanel );
		initGscRangeBar();
	}

	document.addEventListener( 'DOMContentLoaded', initTabs );
} )();
