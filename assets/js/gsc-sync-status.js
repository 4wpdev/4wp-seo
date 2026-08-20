( function () {
	var panel = document.getElementById( 'forwp-seo-gsc-sync-status' );
	if ( ! panel || panel.dataset.running !== '1' ) {
		return;
	}

	window.setTimeout( function () {
		window.location.reload();
	}, 15000 );
}() );
