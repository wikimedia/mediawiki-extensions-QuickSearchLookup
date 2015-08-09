<?php

class QuickSearchLookup {
	private static $instance = null;

	/** @var Title|null $title the Title object for this QuickSearchLookup object */
	private $title;

	/** @var RequestContext $context Main RequestContext object */
	private $context;

	/** @var array $metadata Page metadata */
	private $metadata;

	/**
	 * Constructor
	 *
	 * @param RequestContext $context
	 */
	public function __construct( RequestContext $context ) {
		$this->context = $context;
	}

	/**
	 * Singleton function
	 *
	 * @return QuickSearchLookup
	 */
	public static function getMain() {
		if ( !isset( self::$instance ) ) {
			self::$instance = new self( RequestContext::getMain() );
		}
		return self::$instance;
	}

	/**
	 * Helper function, returns RequestContext::getRequest
	 *
	 * @return WebRequest
	 */
	private function getRequest() {
		return $this->context->getRequest();
	}

	/**
	 * Helper function, same as RequestContext::msg()
	 *
	 * @return Message
	 */
	private function msg( $key ) {
		return $this->context->msg( $key );
	}

	/**
	 * The given title will be used as the Title in this QuickSearchLookup object.
	 *
	 * @param String|Title $titleTerm The Title object of the frist search result,
	 * or a search term user as a first Title
	 */
	public function setFirstResult( $titleTerm ) {
		if ( $titleTerm instanceof Title && $titleTerm->exists() ) {
			$this->setTitle( $titleTerm );
		} else {
			// check, if the term is the exact name of a title in this wiki
			$title = Title::newFromText( $titleTerm );
			if ( $title && $title->exists() ) {
				$this->setTitle( $title );
			}
		}
	}

	/**
	 * Does various checks and set's the given title.
	 *
	 * @param Title $title
	 */
	private function setTitle( Title $title ) {
		// check for redirects
		if ( $title->isRedirect() ) {
			// get the new target (the redirect target)
			$page = WikiPage::factory( $title );
			if ( !$page->exists() ) {
				return;
			}
			$target = $page->getRedirectTarget();
		} else {
			$target = $title;
		}
		$this->title = $target;
	}

	/**
	 * Checks, if the first title is already set
	 *
	 * @return bool
	 */
	public function needsFirstResult() {
		return !isset( $this->title );
	}

	/**
	 * Adds the QuickSearchLookup panel element to the given OutputPage object
	 *
	 * @param OutputPage $out
	 */
	public function outputLookup( OutputPage $out ) {
		global $wgContLang, $wgLang;

		// only add the panel, if the given title exist to avoid
		// an empty panel
		if ( $this->title && $this->title->exists() ) {
			// the panel is build with OOUI, enable it
			$out->enableOOUI();
			$title = $this->title->getText();
			$elements = array();
			$out->addModuleStyles( array( 'ext.QuickSearchLookup' ) );

			// get the info about the Page images added to the firs title
			$imageInfo = $this->getPageImage( $title );

			// If there is a page image, add it in the correct orientation
			if ( $imageInfo ) {
				// get the orientation for this image
				$orientation = ( $imageInfo['thumb']['width'] < $imageInfo['thumb']['height'] ? 'upright' : 'cross' );
				$imageTag = new OOUI\Tag( 'img' );
				$imageTag->setAttributes( array(
					'src' => $imageInfo['thumb']['source'],
					'class' => 'mw-search-quicklookup-image mw-search-quicklookup-image-' . $orientation,
				) );
				$imageLink = Title::newFromText( $imageInfo['pageimage'], NS_FILE );
				$linkTag = new OOUI\Tag( 'a' );
				$linkTag
					->setAttributes( array(
						'href' => $imageLink->getLocalURL(),
						'class' => 'image',
					) )
					->appendContent( $imageTag );
				$elements[] = $linkTag;
			}

			// try to get some text from the page
			$text = $this->getTextExtract( $title );
			if ( $text ) {
				// the layout for the text, with an additional css class to add a margin for
				// the ButtonWidget
				$layout = new OOUI\Layout();
				$layout
					->appendContent( new OOUI\HtmlSnippet( $text ) )
					->addClasses( array(
						'mw-search-quicklookup-text',
						// this class adds space between the text and the read more button (which is positioned
						// aboslute) and will be removed if the expand map button is present
						'mw-search-quicklookup-textmargin'
					) );

				// if there are page coordinates, add an OSM map
				$coord = $this->getPageCoord( $title );
				if ( $coord ) {
					// add the JavaScript module to expand the map
					$out->addModules( array( 'ext.QuickSearchLookup.script' ) );

					// build the params for the URL
					$params = $coord['lat'] . '_N_' . $coord['lon'] . '_W';
					// lat and long has to be nulled to avoid duble adding them in self::buildOSMParams
					$coord['lat'] = null;
					$coord['lon'] = null;
					$addParams = $this->buildOSMParams( $coord );
					// add additional params, if there are any
					if ( $addParams ) {
						$params .= $addParams;
					}
					// add the params to the url params list
					$urlParamsArray = array(
						'params' => $params,
						'title' => $title,
						'lang' => $wgContLang->getCode(),
						'uselang' => $wgLang->getCode(),
					);
					// convert array to url encoded list
					$urlParams = wfArrayToCgi( $urlParamsArray );
					// built the complete URL
					$iframeLink = wfAppendQuery( "//tools.wmflabs.org/wiwosm/osm-on-ol/kml-on-ol.php", $urlParams );

					// create a new iframe tag to add OSM map under the text snippet
					$iframe = new OOUI\Tag( 'iframe' );
					$iframe->setAttributes( array(
						'id' => 'openstreetmap',
						'class' => 'mw-search-quicklookup-osm',
						'src' => $iframeLink,
						'width' => '100%',
						'height' => '100%',
					) );
					// the expand button allows a user to make the map bigger without clicking on permalink
					$expandButton = new OOUI\ButtonWidget( array(
						'label' => $this->msg( 'quicksearchlookup-expand' )->text(),
					) );
					$expandButton->addClasses( array(
						'mw-search-quicklookup-expand',
						// the button is hidden by default and will be visible if JS is enabled
						'hidden'
					) );
					// add OSM map to the layout
					$layout
						->appendContent( $iframe );
				}

				$elements[] = $layout;
			}

			// if there are elements, add them to the output in a PanelLayout
			if ( $elements ) {
				// build a ButtonWidget, with a custom class to position is absolute
				$button = new OOUI\ButtonWidget( array(
					'label' => $this->msg( 'quicksearchlookup-readmore' )->text(),
					'href' => $this->title->getLocalUrl(),
				) );
				$button->addClasses( array(
					'mw-search-quicklookup-readmore'
				) );

				// if there is an OSM map, show an "Expand" button at the right sode
				if ( isset( $expandButton ) ) {
					$elements[] = $expandButton;
				}

				// then add the read more button
				$elements[] = new OOUI\FieldLayout( $button );

				$panel = new OOUI\PanelLayout( array(
					'expanded' => false,
					'padded' => true,
					'framed' => true,
				) );

				$panel->appendContent(
					new OOUI\FieldsetLayout( array(
						'label' => $title,
						'items' => $elements,
					) )
				);
				$out->addHtml( Html::rawElement(
						'div',
						array(
							'class' => 'mw-search-quicklookup',
						),
						$panel
					)
				);
			}
		}
	}

	/**
	 * If not already done, performs an internal Api request to get
	 * page data like page images and a short text snippet.
	 *
	 * @param String $title The title to lookup
	 * @return array The page meta data
	 */
	private function getPageMeta( $title ) {
		if ( !$this->metadata ) {
			$params = new DerivativeRequest(
				$this->getRequest(),
				array(
					'action' => 'query',
					'prop' => 'extracts|pageimages|coordinates',
					'pithumbsize' => 800,
					'exchars' => 450,
					'explaintext' => true,
					'exintro' => true,
					'coprop' => 'type|name|dim|country|region',
					'titles' => $title,
				),
				true
			);
			$api = new ApiMain( $params );
			$api->execute();
			$data = $api->getResultData();
			foreach ( $data['query']['pages'] as $id => $d ) {
				$this->metadata = $d;
			}
		}
		return $this->metadata;
	}

	/**
	 * Get the TextExtract specific data from page meta data,
	 * if any, otherwise an empty string.
	 *
	 * @param String Title The title to lookup
	 * @return string
	 */
	private function getTextExtract( $title ) {
		// try to get text from TextExtracts
		$page = $this->getPageMeta( $title );
		if ( $page && isset( $page['extract'] ) ) {
			return $page['extract']['*'];
		}

		return '';
	}

	/**
	 * Get the PageImages specific data from page meta data,
	 * if any, otherwise false.
	 *
	 * @param String Title The title to lookup
	 * @return array|bool
	 */
	private function getPageImage( $title ) {
		// try to get a page image
		$page = $this->getPageMeta( $title );
		if ( $page && isset( $page['thumbnail'] ) ) {
			$data = array( 'thumb' => $page['thumbnail'] );
			$data['pageimage'] = $page['pageimage'];
			return $data;
		}

		return false;
	}

	/**
	 * Extracts any GeoData related information from the API respond.
	 *
	 * @param String Title The title to lookup
	 * @return array|bool
	 */
	private function getPageCoord( $title ) {
		$page = $this->getPageMeta( $title );
		// check, if there are coordinates for this title and if they are on earth
		if (
			$page &&
			isset( $page['coordinates'] )
		) {
			if ( isset( $page['coordinates'][0]['globe'] ) && $page['coordinates'][0]['globe'] !== "earth" ) {
				return false;
			}
			$info = $page['coordinates'][0];
			return array(
				'lat' => $info['lat'],
				'lon' => $info['lon'],
				'region' => isset( $info['region'] ) ? $info['region'] : null,
				'type' => isset( $info['type'] ) ? $info['type'] : null,
				'dim' => isset( $info['dim'] ) ? $info['dim'] : null,
			);
		}

		return false;
	}

	/**
	 * Helper to generate a list of parameters for the "params" url parameter.
	 *
	 * @param array $data Data to check and add in key/value format
	 * @return String
	 */
	private function buildOSMParams( array $data ) {
		$res = '';
		foreach ( $data as $type => $info ) {
			if ( $info ) {
				$res .= '_' . $type . ':' . $info;
			}
		}
		return $res;
	}
}