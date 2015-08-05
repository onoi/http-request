<?php

namespace Onoi\HttpRequest\Tests;

use Onoi\HttpRequest\AsyncCurlRequest;
use Onoi\HttpRequest\CurlRequest;

/**
 * @covers \Onoi\HttpRequest\AsyncCurlRequest
 * @group onoi-http-request
 *
 * @license GNU GPL v2+
 * @since 1.0
 *
 * @author mwjames
 */
class AsyncCurlRequestTest extends \PHPUnit_Framework_TestCase {

	public function testCanConstruct() {

		$instance = new AsyncCurlRequest( curl_multi_init() );

		$this->assertInstanceOf(
			'\Onoi\HttpRequest\HttpRequest',
			$instance
		);

		$this->assertInstanceOf(
			'\Onoi\HttpRequest\AsyncCurlRequest',
			$instance
		);
	}

	public function testWrongResourceTypeThrowsException() {
		$this->setExpectedException( 'InvalidArgumentException' );
		new AsyncCurlRequest( curl_init() );
	}

	public function testPingForEmptyHttpRequest() {

		$instance = new AsyncCurlRequest( curl_multi_init() );

		$this->assertFalse(
			$instance->ping()
		);
	}

	/**
	 * @dataProvider pingConnectionStateProvider
	 */
	public function testPingConnectionState( $connectionState ) {

		$instance = new AsyncCurlRequest( curl_multi_init() );

		$httpRequest = $this->getMockBuilder( '\Onoi\HttpRequest\CurlRequest' )
			->disableOriginalConstructor()
			->getMock();

		$httpRequest->expects( $this->once() )
			->method( 'ping' )
			->will( $this->returnValue( $connectionState ) );

		$instance->addHttpRequest(
			$httpRequest
		);

		$this->assertEquals(
			$connectionState,
			$instance->ping()
		);
	}

	public function testSetGetOption() {

		// https://github.com/facebook/hhvm/issues/5761
		if ( !defined( 'CURLMOPT_MAXCONNECTS' ) ) {
			$this->markTestSkipped( "Option is not supported for current PHP version" );
		}

		$instance = new AsyncCurlRequest( curl_multi_init() );

		$instance->setOption(
			CURLMOPT_MAXCONNECTS,
			5
		);

		$this->assertSame(
			5,
			$instance->getOption( CURLMOPT_MAXCONNECTS )
		);
	}

	public function testExecuteForResponse() {

		$instance = new AsyncCurlRequest( curl_multi_init() );

		$instance->addHttpRequest(
			new CurlRequest( curl_init() )
		);

		$this->assertInternalType(
			'array',
			$instance->execute()
		);

		$this->assertInternalType(
			'string',
			$instance->getLastError()
		);

		// http://php.net/manual/en/function.curl-multi-info-read.php
		$this->assertFalse(
			$instance->getLastTransferInfo()
		);
	}

	public function testExecuteForCallbackResponse() {

		$asyncCallbackResponseMock = $this->getMockBuilder( '\stdClass' )
			->setMethods( array( 'run' ) )
			->getMock();

		$asyncCallbackResponseMock->expects( $this->once() )
			->method( 'run' );

		$instance = new AsyncCurlRequest( curl_multi_init() );

		$instance->addHttpRequest(
			new CurlRequest( curl_init() )
		);

		$instance->setCallback( function( $data, $info ) use ( $asyncCallbackResponseMock ) {
			$asyncCallbackResponseMock->run( $data, $info );
		} );

		$this->assertNull(
			$instance->execute()
		);

		$this->assertSame(
			0,
			$instance->getLastErrorCode()
		);
	}

	public function pingConnectionStateProvider() {

		$provider[] = array(
			true
		);

		$provider[] = array(
			false
		);

		return $provider;
	}

}
