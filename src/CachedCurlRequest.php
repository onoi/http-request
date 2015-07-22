<?php

namespace Onoi\HttpRequest;

use Onoi\Cache\Cache;

/**
 * Simple cache layer from the client-side to avoid repeated requests to
 * the same target.
 *
 * @license GNU GPL v2+
 * @since 1.0
 *
 * @author mwjames
 */
class CachedCurlRequest extends CurlRequest {

	/**
	 * Fixed constant
	 */
	const CACHE_PREFIX = 'onoi:http:';

	/**
	 * @var Cache
	 */
	private $cache;

	/**
	 * Custom prefix
	 *
	 * @var string
	 */
	private $cachePrefix = '';

	/**
	 * @var boolean
	 */
	private $isCached = false;

	/**
	 * @var integer
	 */
	private $expiry = 60; // 60 sec by default

	/**
	 * @since  1.0
	 *
	 * @param resource $handle
	 * @param Cache $cache
	 */
	public function __construct( $handle, Cache $cache ) {
		parent::__construct( $handle );

		$this->cache = $cache;
	}

	/**
	 * @since  1.0
	 *
	 * @param integer $expiry
	 */
	public function setExpiryInSeconds( $expiry ) {
		$this->expiry = (int)$expiry;
	}

	/**
	 * @since  1.0
	 *
	 * @param string $cachePrefix
	 */
	public function setCachePrefix( $cachePrefix ) {
		$this->cachePrefix = (string)$cachePrefix;
	}

	/**
	 * @since  1.0
	 *
	 * @return boolean
	 */
	public function isCached() {
		return $this->isCached;
	}

	/**
	 * @since  1.0
	 *
	 * @return mixed
	 */
	public function execute() {

		$key = $this->getKeyFromOptions();
		$this->isCached = false;

		if ( $this->cache->contains( $key ) ) {
			$this->isCached = true;
			return $this->cache->fetch( $key );
		}

		$response = parent::execute();

		// Do not cache for a failed response
		if ( $this->getLastErrorCode() !== 0 ) {
			return $response;
		}

		$this->cache->save(
			$key,
			$response,
			$this->expiry
		);

		return $response;
	}

	private function getKeyFromOptions() {

		// curl_init can provide the URL which will set the value to the
		// CURLOPT_URL option, ensure to have the URL as part of the options
		// independent from where/when it was set
		$this->setOption(
			CURLOPT_URL,
			$this->getLastTransferInfo( CURLINFO_EFFECTIVE_URL )
		);

		// Avoid an unsorted order that would create unstable keys
		ksort( $this->options );

		$key = $this->cachePrefix . self::CACHE_PREFIX . md5(
			json_encode( $this->options )
		);

		// Reuse the handle but clear the options
		$this->options = array();

		return $key;
	}

}
