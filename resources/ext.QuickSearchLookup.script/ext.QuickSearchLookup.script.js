( function ( mw, $ ) {
	var $expandButton = $( '.mw-search-quicklookup-expand' ),
		$text = $( '.mw-search-quicklookup-text' ),
		$map = $( '.mw-search-quicklookup-osm' );

	// check, if there is an expand button
	if ( $expandButton.length > 0 ) {
		// remove the hidden class (to show it)
		$expandButton.removeClass( 'hidden' );
		// remove the text-margin class, the expand button is a blocking element,
		// which makes it useless and more confusing (a lot more free, unused space)
		$text.removeClass( 'mw-search-quicklookup-textmargin' );
		// add click handler
		$expandButton.on( 'click', function ( ev ) {
			// hide the button (no way back, once expanded)
			$expandButton.hide();
			// expand the map animated to 500px height (width still 100% of the panel)
			$map.animate( {
				height: '500px'
			}, 300 );
			// the text margin class has to be re-added, now the expand button is hidden
			$text.addClass( 'mw-search-quicklookup-textmargin' );
			ev.preventDefault();
		} );
	}
}( mediaWiki, jQuery ) );