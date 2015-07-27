<?php

class QuickSearchLookupHooks {
	/**
	 * If there isn't a first title already, set it here.
	 *
	 * @param Title $title Title object of the list item
	 * @param String $text Text to use for the link
	 */
	public static function onShowSearchHitTitle( &$title, &$text, $result, $terms, $pag ) {
		$qsl = QuickSearchLookup::getMain();
		if ( $qsl->needsFirstResult() ) {
			$qsl->setFirstResult( $title );
		}
	}

	/**
	 * If there isn't a first title already, set it here.
	 *
	 * @param SpecialSearch $special
	 * @param String $profile current search profile
	 * @param SearchEngine the search engine
	 */
	public static function onSpecialSearchSetupEngine( SpecialSearch $page, $profile, $searchEngine ) {
		$qsl = QuickSearchLookup::getMain();
		if ( $qsl->needsFirstResult() ) {
			$term = str_replace( "\n", " ", $page->getRequest()->getText( 'search' ) );
			$qsl->setFirstResult( $term );
		}
	}

	/**
	 * Output the panel afterthe search results.
	 *
	 * @param SpecialSearch $specialSearch
	 * @param OutputPage $output
	 */
	public static function onSpecialSearchResultsAppend( SpecialSearch $specialSearch, OutputPage $output ) {
		QuickSearchLookup::getMain()->outputLookup( $output );
	}
}