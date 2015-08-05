<?php

namespace Onoi\HttpRequest;

/**
 * @license GNU GPL v2+
 * @since 1.0
 *
 * @author mwjames
 */
interface HttpRequest {

	/**
	 * @since 1.0
	 *
	 * @return boolean
	 */
	public function ping();

	/**
	 * @since 1.0
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function setOption( $name, $value );

	/**
	 * @since 1.0
	 *
	 * @param string $name
	 */
	public function getOption( $name );

	/**
	 * @since 1.0
	 *
	 * @param string|null $name
	 */
	public function getLastTransferInfo( $name = null );

	/**
	 * @since 1.0
	 *
	 * @return string
	 */
	public function getLastError();

	/**
	 * @since 1.0
	 *
	 * @return integer
	 */
	public function getLastErrorCode();

	/**
	 * @since 1.0
	 *
	 * @return mixed
	 */
	public function execute();

	/**
	 * @since 1.0
	 */
	public function __invoke();

}
