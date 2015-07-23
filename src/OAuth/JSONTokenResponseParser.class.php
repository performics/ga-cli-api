<?php
namespace OAuth;
class JSONTokenResponseParser extends AbstractTokenResponseParser {
	/**
	 * Parses the JSON response and stores it in $this->_responseAsArray.
	 *
	 * @param string $response
	 */
	protected function _parseAndSetResponse($response) {
		$this->_responseAsArray = json_decode($response, true);
	}
}
?>