<?php

namespace Onoi\HttpRequest;

use InvalidArgumentException;
use Closure;

/**
 * This class creates a remote socked connection to initiate an asynchronous http
 * request.
 *
 * Once a connection is established and content has been posted, the request
 * will close the connection. The receiving client is responsible for open
 * a separate process and initiated a independent transaction.
 *
 * @license GNU GPL v2+
 * @since 1.1
 *
 * @author mwjames
 */
class SocketRequest implements HttpRequest {

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
	 * @var boolean
	 */
	private $followedLocation = false;

	/**
	 * @since 1.1
	 *
	 * @param string|null $url
	 */
	public function __construct( $url = null ) {
		$this->setOption( ONOI_HTTP_REQUEST_URL, $url );
		$this->setOption( ONOI_HTTP_REQUEST_CONNECTION_TIMEOUT, 15 );
		$this->setOption( ONOI_HTTP_REQUEST_CONNECTION_FAILURE_REPEAT, 2 );
		$this->setOption( ONOI_HTTP_REQUEST_SOCKET_CLIENT_FLAGS, STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_CONNECT );
		$this->setOption( ONOI_HTTP_REQUEST_METHOD, 'POST' );
		$this->setOption( ONOI_HTTP_REQUEST_CONTENT_TYPE, "application/x-www-form-urlencoded" );
		$this->setOption( ONOI_HTTP_REQUEST_SSL_VERIFYPEER, false );
		$this->setOption( ONOI_HTTP_REQUEST_FOLLOWLOCATION, true );
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

		if ( $this->getOption( ONOI_HTTP_REQUEST_FOLLOWLOCATION ) && $res !== false ) {
			$this->setOption( ONOI_HTTP_REQUEST_URL, $this->tryToFindFollowLocation( $resource, $urlComponents ) );
		}

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
			$this->getOption( ONOI_HTTP_REQUEST_SOCKET_CLIENT_FLAGS )
		);

		// Defaults
		$response = array(
			'responseMessage' => "$this->errstr ($this->errno)",
			'connectionFailure' => -1,
			'wasCompleted' => false,
			'wasAccepted'  => false,
			'followedLocation' => false,
			'time' => microtime( true )
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

	protected function getResourceFromSocketClient( $urlComponents, $flags ) {

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
		$response['followedLocation'] = $this->followedLocation;
		$response['wasCompleted'] = (bool)$requestResponse;
		$response['wasAccepted'] = (bool)preg_match( '#^HTTP/\d\.\d 202 #', $response['responseMessage'] );
		$response['connectionFailure'] = $repeats;
	}

	private function getUrlComponents( $url ) {

		$urlComponents = parse_url( $url );

		$urlComponents['scheme'] = isset( $urlComponents['scheme'] ) ? $urlComponents['scheme'] : '';
		$urlComponents['host'] = isset( $urlComponents['host'] ) ? $urlComponents['host'] : '';
		$urlComponents['port'] = isset( $urlComponents['port'] ) ? $urlComponents['port'] : 80;
		$urlComponents['path'] = isset( $urlComponents['path'] ) ? $urlComponents['path'] : '';

		return array(
			'scheme' => $urlComponents['scheme'],
			'host'   => $urlComponents['host'],
			'port'   => $urlComponents['port'],
			'path'   => $urlComponents['path']
		);
	}

	private function postResponseToCallback( $urlComponents, $response ) {

		$requestResponse = new RequestResponse( array(
			'host' => $urlComponents['host'],
			'port' => $urlComponents['port'],
			'path' => $urlComponents['path'],
			'responseMessage'   => $response['responseMessage'],
			'followedLocation'  => $response['followedLocation'],
			'wasCompleted'      => $response['wasCompleted'],
			'wasAccepted'       => $response['wasAccepted'],
			'connectionFailure' => $response['connectionFailure'],
			'requestProcTime'   => microtime( true ) - $response['time']
		) );

		if ( is_callable( $this->getOption( ONOI_HTTP_REQUEST_ON_COMPLETED_CALLBACK ) ) && $response['wasCompleted'] ) {
			call_user_func_array( $this->getOption( ONOI_HTTP_REQUEST_ON_COMPLETED_CALLBACK ), array( $requestResponse ) );
		}

		if ( is_callable( $this->getOption( ONOI_HTTP_REQUEST_ON_FAILED_CALLBACK ) ) && ( !$response['wasCompleted'] || !$response['wasAccepted'] ) ) {
			call_user_func_array( $this->getOption( ONOI_HTTP_REQUEST_ON_FAILED_CALLBACK ), array( $requestResponse ) );
		}
	}

	private function tryToFindFollowLocation( $resource, $urlComponents ) {

		// http://stackoverflow.com/questions/3799134/how-to-get-final-url-after-following-http-redirections-in-pure-php

		$response = '';
		while( !feof( $resource ) ) $response .= fread( $resource, 8192 );

		// Only try to match a 301 message (Moved Permanently)
		if ( preg_match( '#^HTTP/\d\.\d 301 #', $response ) && preg_match('/^Location: (.+?)$/m', $response, $matches ) ) {
			$this->followedLocation = true;

			if ( substr( $matches[1], 0, 1 ) == "/" ) {
				return $urlComponents['scheme'] . "://" . $urlComponents['host'] . trim( $matches[1] );
			}

			return trim( $matches[1] );
		}

		// Return the URL we know
		return $this->getOption( ONOI_HTTP_REQUEST_URL );
	}

}
