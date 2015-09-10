<?php

namespace Onoi\HttpRequest;

use InvalidArgumentException;
use Closure;

/**
 * This class creates a remote socked connection to initiate an asynchronous http
 * request.
 *
 * Once a connection is established and content has been posted, the request
 * is expected to close the connection. The receiving client is expected to open
 * a separate process and initiated a independent transaction from that of the
 * originating request.
 *
 * A callback can verify the acknowledge but not the transactional response
 * (as it would be for a cURL request).
 *
 * @license GNU GPL v2+
 * @since 1.1
 *
 * @author mwjames
 */
class AsyncRequest implements HttpRequest {

	/**
	 * @var array
	 */
	private $options = array();

	/**
	 * @var integer
	 */
	private $errno = 0;

	/**
	 * @var string
	 */
	private $errstr = '';

	/**
	 * @var string
	 */
	private $lastTransferInfo = '';

	/**
	 * @since 1.1
	 *
	 * @param string|null $url
	 */
	public function __construct( $url = null ) {
		$this->setOption( ONOI_HTTP_REQUEST_URL, $url );
		$this->setOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT, 15 );
		$this->setOption( ONOI_HTTP_REQUEST_CONNECTION_FAILURE_REPEAT, 2 );
		$this->setOption( ONOI_HTTP_REQUEST_STREAM_CLIENT_FLAGS, STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT );
		$this->setOption( ONOI_HTTP_REQUEST_METHOD, 'POST' );
		$this->setOption( ONOI_HTTP_REQUEST_CONTENT_TYPE, "application/x-www-form-urlencoded" );
		$this->setOption( ONOI_HTTP_REQUEST_SSL_VERIFYPEER, false );
	}

	/**
	 * @since 1.1
	 *
	 * {@inheritDoc}
	 */
	public function ping() {

		if ( $this->getOption( ONOI_HTTP_REQUEST_URL ) === null ) {
			return false;
		}

		$urlComponents = $this->getUrlComponents( $this->getOption( ONOI_HTTP_REQUEST_URL ) );

		$resource = $this->getResourceFromSocketClient(
			$urlComponents,
			STREAM_CLIENT_CONNECT
		);

		if ( !$resource ) {
			return false;
		}

		stream_set_timeout( $resource, $this->getOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT ) );

		$httpMessage = (
			"HEAD "  . $urlComponents['path'] . " HTTP/1.1\r\n" .
			"Host: " . $urlComponents['host'] . "\r\n" .
			"Connection: Close\r\n\r\n"
		);

		$res = @fwrite( $resource, $httpMessage );
		@fclose( $resource );

		return $res !== false;
	}

	/**
	 * @since 1.1
	 *
	 * {@inheritDoc}
	 */
	public function setOption( $name, $value ) {
		$this->options[$name] = $value;
	}

	/**
	 * @since 1.1
	 *
	 * {@inheritDoc}
	 */
	public function getOption( $name ) {
		return isset( $this->options[$name] ) ? $this->options[$name] : null;
	}

	/**
	 * @since 1.1
	 *
	 * {@inheritDoc}
	 */
	public function getLastTransferInfo( $name = null ) {
		return $this->lastTransferInfo;
	}

	/**
	 * @since 1.1
	 *
	 * {@inheritDoc}
	 */
	public function getLastError() {
		return $this->errstr;
	}

	/**
	 * @since 1.1
	 *
	 * {@inheritDoc}
	 */
	public function getLastErrorCode() {
		return $this->errno;
	}

	/**
	 * @since 1.1
	 *
	 * {@inheritDoc}
	 */
	public function execute() {

		$urlComponents = $this->getUrlComponents( $this->getOption( ONOI_HTTP_REQUEST_URL ) );

		$resource = $this->getResourceFromSocketClient(
			$urlComponents,
			$this->getOption( ONOI_HTTP_REQUEST_STREAM_CLIENT_FLAGS )
		);

		// Defaults
		$response = array(
			'responseMessage' => "$this->errstr ($this->errno)",
			'connectionFailure' => -1,
			'wasCompleted' => false,
			'time'    => microtime( true )
		);

		$this->doMakeSocketRequest(
			$urlComponents,
			$resource,
			$response
		);

		$this->postResponseToCallback(
			$urlComponents,
			$response
		);

		return $response['wasCompleted'];
	}

	private function getResourceFromSocketClient( $urlComponents, $flags ) {

		$context = stream_context_create();
		stream_context_set_option( $context, 'ssl', 'verify_peer', $this->getOption( ONOI_HTTP_REQUEST_SSL_VERIFYPEER ) );

		$resource = @stream_socket_client(
			$urlComponents['host'] . ':'. $urlComponents['port'],
			$this->errno,
			$this->errstr,
			$this->getOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT ),
			$flags,
			$context
		);

		return $resource;
	}

	private function doMakeSocketRequest( $urlComponents, $resource, &$response ) {

		if ( !$resource ) {
			return;
		}

		$requestResponse = false;
		stream_set_timeout( $resource, $this->getOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT ) );

		$httpMessage = (
			strtoupper( $this->getOption( ONOI_HTTP_REQUEST_METHOD ) ) . " " . $urlComponents['path'] . " HTTP/1.1\r\n" .
			"Host: " . $urlComponents['host'] . "\r\n" .
			"Content-Type: " . $this->getOption( ONOI_HTTP_REQUEST_CONTENT_TYPE ) . "\r\n" .
			"Content-Length: " . strlen( $this->getOption( ONOI_HTTP_REQUEST_CONTENT ) ) . "\r\n" .
			"Connection: Close\r\n\r\n" .
			$this->getOption( ONOI_HTTP_REQUEST_CONTENT )
		);

		// Sometimes a response can fail (busy server, timeout etc.), try as for
		// as many times the FAILURE_REPEAT option dictates
		for ( $repeats = 0; $repeats < $this->getOption( ONOI_HTTP_REQUEST_CONNECTION_FAILURE_REPEAT ); $repeats++ ) {
			if ( $requestResponse = @fwrite( $resource, $httpMessage ) ) {
				break;
			}
		}

		// Fetch the acknowledge response
		$this->lastTransferInfo = @fgets( $resource );
		@fclose( $resource );

		$response['responseMessage'] = $this->lastTransferInfo;
		$response['wasCompleted'] = (bool)$requestResponse;
		$response['connectionFailure'] = $repeats;
	}

	private function getUrlComponents( $url ) {

		$urlComponents = parse_url( $url );

		$urlComponents['host'] = isset( $urlComponents['host'] ) ? $urlComponents['host'] : '';
		$urlComponents['port'] = isset( $urlComponents['port'] ) ? $urlComponents['port'] : 80;
		$urlComponents['path'] = isset( $urlComponents['path'] ) ? $urlComponents['path'] : '';

		return array(
			'host' => $urlComponents['host'],
			'port' => $urlComponents['port'],
			'path' => $urlComponents['path']
		);
	}

	private function postResponseToCallback( $urlComponents, $response ) {

		$requestResponse = new RequestResponse( array(
			'host' => $urlComponents['host'],
			'port' => $urlComponents['port'],
			'path' => $urlComponents['path'],
			'responseMessage'   => $response['responseMessage'],
			'wasCompleted'      => $response['wasCompleted'],
			'connectionFailure' => $response['connectionFailure'],
			'requestProcTime'   => microtime( true ) - $response['time']
		) );

		if ( is_callable( $this->getOption( ONOI_HTTP_REQUEST_ON_COMPLETED_CALLBACK ) ) ) {
			call_user_func_array( $this->getOption( ONOI_HTTP_REQUEST_ON_COMPLETED_CALLBACK ), array( $requestResponse ) );
		}

		if ( is_callable( $this->getOption( ONOI_HTTP_REQUEST_ON_FAILED_CALLBACK ) ) && !$response['wasCompleted'] ) {
			call_user_func_array( $this->getOption( ONOI_HTTP_REQUEST_ON_FAILED_CALLBACK ), array( $requestResponse ) );
		}
	}

}
