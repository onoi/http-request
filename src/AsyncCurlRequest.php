<?php

namespace Onoi\HttpRequest;

use InvalidArgumentException;
use Closure;

/**
 * @license GNU GPL v2+
 * @since 1.0
 *
 * @author mwjames
 */
class AsyncCurlRequest implements HttpRequest {

	/**
	 * @var resource
	 */
	private $handle;

	/**
	 * @var array
	 */
	protected $options = array();

	/**
	 * @var HttpRequest[]
	 */
	private $httpRequests = array();

	/**
	 * @var integer
	 */
	private $lastErrorCode = 0;

	/**
	 * @var Closure|null
	 */
	private $callback = null;

	/**
	 * @since 1.0
	 *
	 * @param resource $handle
	 */
	public function __construct( $handle ) {

		if ( get_resource_type( $handle ) !== 'curl_multi' ) {
			throw new InvalidArgumentException( "Expected a cURL multi resource type" );
		}

		$this->handle = $handle;
	}

	/**
	 * @since 1.0
	 *
	 * @return boolean
	 */
	public function ping() {

		$canConnect = false;

		foreach ( $this->httpRequests as $httpRequest ) {
			if ( !$httpRequest->ping() ) {
				return false;
			}

			$canConnect = true;
		}

		return $canConnect;
	}

	/**
	 * @since 1.0
	 *
	 * @return boolean
	 */
	public function addHttpRequest( HttpRequest $httpRequest ) {
		$this->httpRequests[] = $httpRequest;
	}

	/**
	 * @since 1.0
	 *
	 * @param Closure $callback
	 */
	public function setCallback( Closure $callback ) {
		$this->callback = $callback;
	}

	/**
	 * @since 1.0
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function setOption( $name, $value ) {

		$this->options[$name] = $value;

		curl_multi_setopt(
			$this->handle,
			$name,
			$value
		);
	}

	/**
	 * @since 1.0
	 *
	 * @param string $name
	 *
	 * @return mixed|null
	 */
	public function getOption( $name ) {
		return isset( $this->options[$name] ) ? $this->options[$name] : null;
	}

	/**
	 * @since 1.0
	 *
	 * @param string|null $name
	 *
	 * @return mixed
	 */
	public function getLastTransferInfo( $name = null ) {
		return curl_multi_info_read( $this->handle );
	}

	/**
	 * @note PHP 5.5.0
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function getLastError() {
		return function_exists( 'curl_multi_strerror' ) ?  curl_multi_strerror( $this->lastErrorCode ) : '';
	}

	/**
	 * @since 1.0
	 *
	 * @return integer
	 */
	public function getLastErrorCode() {
		return $this->lastErrorCode;
	}

	/**
	 * @since 1.0
	 *
	 * @return array|null
	 */
	public function execute() {

		// Register all handles and monitor that
		// all requests are executed
		$handleExecutionCounter = array();

		foreach( $this->httpRequests as $httpRequest ) {
			$httpRequest->setOption( CURLOPT_RETURNTRANSFER, true );
			$handleExecutionCounter[(int)  $httpRequest() ] = true;
			curl_multi_add_handle( $this->handle, $httpRequest() );
		}

		$response = $this->doExecute( $handleExecutionCounter );

		if ( $this->callback !== null ) {
			return null;
		}

		return $response;
	}

	private function doExecute( $handleExecutionCounter ) {

		$active = null;
		$responses = array();

		// http://php.net/manual/en/function.curl-multi-init.php
		// https://gist.github.com/Xeoncross/2362936
		do {
			$this->lastErrorCode = curl_multi_exec( $this->handle, $active );

			// Wait for activity on any curl-connection
			if ( curl_multi_select( $this->handle ) == -1 ) {
				usleep( 100 );
			}

		} while ( $this->lastErrorCode == CURLM_CALL_MULTI_PERFORM );

		while ( ( $active && $this->lastErrorCode == CURLM_OK ) || $handleExecutionCounter !== array() ) {

			$response = null;

			if ( ( $state = curl_multi_info_read( $this->handle ) ) && $state["msg"] == CURLMSG_DONE ) {

				$response = array(
					'contents' => curl_multi_getcontent( $state['handle'] ),
					'info'     => curl_getinfo( $state['handle'] )
				);

				unset( $handleExecutionCounter[(int) $state['handle']] );
				curl_multi_remove_handle( $this->handle, $state['handle'] );
			}

			if ( $this->callback !== null && $response !== null ) {
				call_user_func_array( $this->callback, array(
					$response['contents'],
					$response['info']
				) );
			} elseif ( $response !== null ) {
				$responses[] = $response;
			}

			// Continue to exec until curl is ready
			do {
				$this->lastErrorCode = curl_multi_exec( $this->handle, $active );
			} while ( $this->lastErrorCode == CURLM_CALL_MULTI_PERFORM );
		}

		return $responses;
	}

	/**
	 * @since 1.0
	 */
	public function __destruct() {
		curl_multi_close( $this->handle );
	}

	/**
	 * @since 1.0
	 */
	public function __invoke() {
		return $this->execute();
	}

}
