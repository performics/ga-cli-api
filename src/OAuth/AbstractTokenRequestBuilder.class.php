<?php
namespace OAuth;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

abstract class AbstractTokenRequestBuilder {
	/**
	 * Request builders may be responsible for identifying the scope of the
	 * access token.
	 *
	 * @return string
	 */
	public function getTokenScope() {}
	
	/**
	 * Returns a hash that uniquely identifies this instance.
	 *
	 * @return string
	 */
	abstract public function getHash();
	
	/**
	 * Returns the data payload to be sent to the OAuth authorization endpoint.
	 *
	 * @return string, array
	 */
	abstract public function getRequestPayload();
}
?>