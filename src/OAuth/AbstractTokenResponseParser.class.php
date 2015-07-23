<?php
namespace OAuth;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

abstract class AbstractTokenResponseParser {
	protected $_responseSetTime;
	protected $_responseAsArray;
	// Default array keys
	protected $_tokenKey = 'access_token';
	protected $_expirationKey = 'expires_in';
	protected $_refreshKey = 'refresh_token';
	protected $_typeKey = 'token_type';
	protected $_scopeKey = 'scope';
	protected $_errorCodeKey = 'error';
	protected $_errorDescriptionKey = 'error_description';
	public $expirationTimeIsRelative = true;
	
	/**
	 * Takes the raw response, parses it, and sets it in the
	 * $this->_responseAsArray property.
	 *
	 * @param string $response
	 */
	abstract protected function _parseAndSetResponse($response);
	
	/**
	 * @param string $key
	 * @return string, int
	 */
	protected function _getResponseElement($key) {
		if (array_key_exists($key, $this->_responseAsArray)) {
			return $this->_responseAsArray[$key];
		}
	}
	
	/**
	 * Parses the response.
	 *
	 * @param string $response
	 */
	public function setResponse($response) {
		$this->_responseSetTime = time();
		$this->_parseAndSetResponse($response);
	}
	
	/**
	 * Returns the timestamp when this token expires.
	 *
	 * @return int
	 */
	public function getExpirationTime() {
		$responseElement = $this->_getResponseElement($this->_expirationKey);
		if ($responseElement !== null) {
			if (!is_numeric($responseElement)) {
				throw new UnexpectedValueException(
					'Found non-numeric value in response element under key "' .
					$this->_expirationKey . '".'
				);
			}
			return $responseElement + (
				$this->expirationTimeIsRelative ? $this->_responseSetTime : 0
			);
		}
	}
	
	/**
	 * @return string
	 */
	public function getToken() {
		return $this->_getResponseElement($this->_tokenKey);
	}
	
	/**
	 * @return string
	 */
	public function getRefreshToken() {
		return $this->_getResponseElement($this->_refreshKey);
	}
	
	/**
	 * @return string
	 */
	public function getTokenType() {
		return $this->_getResponseElement($this->_typeKey);
	}
	
	/**
	 * @return string
	 */
	public function getTokenScope() {
		return $this->_getResponseElement($this->_scopeKey);
	}
	
	/**
	 * @return string
	 */
	public function getErrorCode() {
		return $this->_getResponseElement($this->_errorCodeKey);
	}
	
	/**
	 * @return string
	 */
	public function getErrorDescription() {
		return $this->_getResponseElement($this->_errorDescriptionKey);
	}
	
	/**
	 * Returns an associative array representing the response obtained when
	 * requesting the token.
	 * 
	 * @return array
	 */
	public function getResponseAsArray() {
		return $this->_responseAsArray;
	}
	
	/**
	 * Returns the timestamp when the response was set.
	 *
	 * @return int
	 */
	public function getResponseSetTime() {
		return $this->_responseSetTime;
	}
}
?>