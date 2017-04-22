<?php

/**
 * @group Extensions
 * @group Database
 */
class QuickSearchLooupTest extends MediaWikiTestCase {
	private $apiResult = [
		'pageid' => 4,
		'ns' => 0,
		'title' => 'Test',
		'extract' => 'Test',
		'thumbnail' => [
			'source' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/aa/test.png',
			'width' => 800,
			'height' => 649
		],
		'pageimage' => 'test.png'
	];

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
		$mock = $this->getMockBuilder( QuickSearchLookup::class )
			->setConstructorArgs( [ $context ] )
			->setMethods( [ 'getPageMeta' ] )
			->getMock();
		$mock->expects( $this->any() )
			->method( 'getPageMeta' )
			->withAnyParameters()
			->will( $this->returnValue( $this->apiResult ) );

		return $mock;
	}

	/**
	 * @dataProvider getTitleResults
	 */
	public function testSetFirstResult( $title, $result ) {
		$qsl = $this->makeQSL();
		$this->assertEquals( $result, $qsl->setFirstResult( $title ) );
	}

	/**
	 * @dataProvider getTitleResults
	 */
	public function testNeedsFirstResult( $title, $result ) {
		$qsl = $this->makeQSL();
		$qsl->setFirstResult( $title );
		$this->assertEquals( !$result, $qsl->needsFirstResult() );
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
			array( 'UTPage', true ),
			array( Title::newFromText( 'BogusTest' ), false ),
			array( Title::newFromText( 'UTPage' ), true ),
		);
	}
}

class MockQuickLookupTest {
	public function __call( $name, $args ) {
		throw new Exception( 'Functions shouldn\'t call the singleton itself.' );
	}
}
