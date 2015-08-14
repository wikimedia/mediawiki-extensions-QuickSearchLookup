<?php

/**
 * @group Extensions
 */
class QuickSearchLooupTest extends MediaWikiTestCase {
	protected function setUp() {
		parent::setUp();
		QuickSearchLookup::setInstance( new MockQuickLookupTest() );
	}

	protected function tearDown() {
		QuickSearchLookup::setInstance( null );
		parent::tearDown();
	}

	private function makeQSL( $url = '/' ) {
		$params = wfParseUrl( wfExpandUrl( $url ) );
		$q = array();
		if ( isset( $params['query'] ) ) {
			$q = wfCgiToArray( $params['query'] );
		}
		$request = new FauxRequest( $q );
		$request->setRequestURL( $url );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );
		$context->setOutput( new OutputPage( $context ) );
		return new QuickSearchLookup( $context );
	}

	/**
	 * @dataProvider getTitleResults
	 */
	public function testSetFirstResult( $title, $result ) {
		$qsl = $this->makeQSL();
		$this->assertEquals( $qsl->setFirstResult( $title ), $result );
	}

	/**
	 * @dataProvider getTitleResults
	 */
	public function testNeedsFirstResult( $title, $result ) {
		$qsl = $this->makeQSL();
		$qsl->setFirstResult( $title );
		$this->assertEquals( $qsl->needsFirstResult(), !$result );
	}

	/**
	 * @dataProvider getTitleResults
	 */
	public function testOutputLookup( $title, $result ) {
		$qsl = $this->makeQSL();
		$request = new RequestContext();
		$out = $request->getOutput();
		$qsl->setFirstResult( $title );
		$qsl->outputLookup( $out );
		$this->assertEquals( $result, (bool)$out->getHTML() );
	}

	public function getTitleResults() {
		return array(
			array( 'BogusTest', false ),
			array( 'Main_Page', true ),
			array( Title::newFromText( 'BogusTest' ), false ),
			array( Title::newMainPage(), true ),
		);
	}
}

class MockQuickLookupTest {
	public function __call( $name, $args ) {
		throw new Exception( 'Functions shouldn\'t call the singleton itself.' );
	}
}