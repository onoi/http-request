<?php

namespace Onoi\HttpRequest\Tests;

use Onoi\HttpRequest\AsyncRequest;

/**
 * @covers \Onoi\HttpRequest\AsyncRequest
 * @group onoi-http-request
 *
 * @license GNU GPL v2+
 * @since 1.1
 *
 * @author mwjames
 */
class AsyncRequestTest extends \PHPUnit_Framework_TestCase {

	public function testCanConstruct() {

		$instance = new AsyncRequest();

		$this->assertInstanceOf(
			'\Onoi\HttpRequest\AsyncRequest',
			$instance
		);

		$this->assertInstanceOf(
			'\Onoi\HttpRequest\HttpRequest',
			$instance
		);
	}

	public function testPing() {

		$instance = new AsyncRequest();

		$this->assertFalse(
			$instance->ping()
		);

		$instance->setOption( ONOI_HTTP_REQUEST_URL, 'http://example.org' );

		$this->assertInternalType(
			'boolean',
			$instance->ping()
		);
	}

	public function testExecute() {

		$instance = new AsyncRequest();
		$instance->setOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT, 1 );
		$instance->setOption( ONOI_HTTP_REQUEST_URL, 'http://localhost:8888' );

		$this->assertInternalType(
			'boolean',
			$instance->execute()
		);
	}

	public function testGetLastError() {

		$instance = new AsyncRequest();
		$instance->setOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT, 1 );

		$instance->execute();

		$this->assertInternalType(
			'string',
			$instance->getLastError()
		);
	}

	public function testGetLastErrorCode() {

		$instance = new AsyncRequest();

		$this->assertInternalType(
			'integer',
			$instance->getLastErrorCode()
		);
	}

	public function testGetLastTransferInfo() {

		$instance = new AsyncRequest();

		$this->assertInternalType(
			'string',
			$instance->getLastTransferInfo()
		);
	}

	public function testCallbackOnRequestCompleted() {

		$instance = new AsyncRequest();
		$instance->setOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT, 0.1 );

		$requestResponse = null;

		$instance->setOption( ONOI_HTTP_REQUEST_ON_COMPLETED_CALLBACK, function( $requestResponseCompleted ) use ( &$requestResponse ) {
			$requestResponse = $requestResponseCompleted;
		} );

		$instance->execute();

		$this->assertRequestResponse( $requestResponse );
	}

	public function testTryInvalidCallbackOnRequestCompleted() {

		$instance = new AsyncRequest();

		$instance->setOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT, 0.1 );
		$instance->setOption( ONOI_HTTP_REQUEST_ON_COMPLETED_CALLBACK, 'foo' );

		$this->assertFalse(
			$instance->execute()
		);
	}

	public function testCallbackOnRequestFailed() {

		$instance = new AsyncRequest();
		$instance->setOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT, 0.1 );

		$requestResponse = null;

		$instance->setOption( ONOI_HTTP_REQUEST_ON_FAILED_CALLBACK, function( $requestResponseFailed ) use ( &$requestResponse ) {
			$requestResponse = $requestResponseFailed;
		} );

		$instance->execute();

		$this->assertRequestResponse( $requestResponse );
	}

	private function assertRequestResponse( $requestResponse ) {

		$this->assertInstanceOf(
			'\Onoi\HttpRequest\RequestResponse',
			$requestResponse
		);

		$expectedRequestResponseFields = array(
			'wasCompleted',
			'responseMessage',
			'host',
			'port',
			'path',
			'connectionFailure',
			'requestProcTime'
		);

		foreach ( $expectedRequestResponseFields as $field ) {
			$this->assertTrue( $requestResponse->has( $field ) );
		}
	}

	public function testDefinedConstants() {

		$constants = array(
			'ONOI_HTTP_REQUEST_ON_FAILED_CALLBACK',
			'ONOI_HTTP_REQUEST_ON_COMPLETED_CALLBACK',
			'ONOI_HTTP_REQUEST_STREAM_CLIENT_FLAGS',
			'ONOI_HTTP_REQUEST_URL',
			'ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT',
			'ONOI_HTTP_REQUEST_CONNECTION_FAILURE_REPEAT',
			'ONOI_HTTP_REQUEST_CONTENT',
			'ONOI_HTTP_REQUEST_CONTENT_TYPE',
			'ONOI_HTTP_REQUEST_METHOD',
			'ONOI_HTTP_REQUEST_PROTOCOL_VERSION',
			'ONOI_HTTP_REQUEST_SSL_VERIFYPEER'
		);

		$instance = new AsyncRequest();

		foreach ( $constants as $constant ) {
			$this->assertTrue( defined( $constant ) );
		}
	}

}
