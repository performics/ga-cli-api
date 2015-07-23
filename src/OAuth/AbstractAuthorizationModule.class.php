<?php
namespace OAuth;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

abstract class AbstractAuthorizationModule {
	protected $_parser;
	protected $_cookie;
	protected $_authorizationURL;
	protected $_authorizationParams = array();
	protected $_postToAuthorize = false;
	/* This is only used when obtaining the authorization code from the
	redirect URL, but having it be a property improves unit test coverage. */
	protected $_responseHeaders;
	
	/**
	 * Casts the submitted argument as a URL, ensures that it uses the proper
	 * scheme, and returns it.
	 *
	 * @param mixed $url
	 * @return URL
	 */
	protected static function _castURL($url) {
		try {
			$url = \URL::cast($url);
			if ($url->getScheme() != 'https') {
				throw new InvalidArgumentException(
					'All URLs used in OAuth contexts must be secure.'
				);
			}
			return $url;
		} catch (\URLException $e) {
			throw new InvalidArgumentException(
				'Caught URL validation error.', null, $e
			);
		}
	}
	
	/**
	 * Initializes and returns a cURL session with some basic options set.
	 *
	 * @param URL $url
	 * @param string, array $params = null
	 * @param boolean $usePost = false
	 * @return resource
	 */
	protected function _getCurlSession(
		\URL $url,
		$params = null,
		$usePost = false
	) {
		$ch = curl_init();
		// Make sure we leave the original untouched
		$urlCopy = clone $url;
		if ($params && !$usePost) {
			$urlCopy->setQueryString($params);
		}
		curl_setopt_array($ch, array(
			CURLOPT_URL => $urlCopy->getURL(),
			CURLOPT_RETURNTRANSFER => true
		));
		if (defined('PFX_CA_BUNDLE') && PFX_CA_BUNDLE) {
			curl_setopt_array($ch, array(
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_CAINFO => PFX_CA_BUNDLE
			));
		}
		if ($this->_cookie && $this->_cookie instanceof \CookieNS) {
			$path = $this->_cookie->getPath();
			curl_setopt_array($ch, array(
				CURLOPT_COOKIEJAR => $path,
				CURLOPT_COOKIEFILE => $path
			));
		}
		if ($usePost) {
			curl_setopt($ch, CURLOPT_POST, true);
			if ($params) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
			}
		}
		return $ch;
	}
	
	/**
	 * Throws an OAuth\RuntimeException if the HTTP status indicates that an
	 * error has taken place.
	 *
	 * @param int $httpStatus
	 */
	protected function _handleAuthorizationError($httpStatus) {
		if ($httpStatus != 200) {
			$message = 'Authorization request failed with status '
			         . $httpStatus;
			$errorCode = $this->_parser->getErrorCode();
			$errorDescription = $this->_parser->getErrorDescription();
			if ($errorCode !== null) {
				$message .= ' (error code ' . $errorCode . ')';
			}
			if ($errorDescription !== null) {
				$message .= ': ' . $errorDescription;
			}
			throw new AuthorizationException($message);
		}
	}
	
	/**
	 * Executes the given cURL handle and returns the response content.
	 *
	 * @param resource $ch
	 * @return string
	 */
	protected function _getResponseFromCurlHandle($ch) {
		// Ensure we can separate the header and the body
		$headers = array();
		curl_setopt(
			$ch,
			CURLOPT_HEADERFUNCTION,
			function($ch, $headerString) use (&$headers) {
				$headers[] = $headerString;
				return strlen($headerString);
			}
		);
		$response = curl_exec($ch);
		$this->_responseHeaders = $headers;
		return $response;
	}
	
	/**
	 * Returns the HTTP response code from the given cURL handle.
	 *
	 * @param resource $ch
	 * @return int
	 */
	protected function _getResponseCodeFromCurlHandle($ch) {
		return curl_getinfo($ch, CURLINFO_HTTP_CODE);
	}
	
	/**
	 * Registers an object containing any cookies that may be a prerequisite to
	 * obtaining the OAuth token.
	 *
	 * @param \CookieBase $cookie
	 */
	public function registerCookie(\CookieBase $cookie) {
		$this->_cookie = $cookie;
	}
	
	/**
	 * @param boolean $includeParams = true
	 * @return URL
	 */
	public function getAuthorizationURL($includeParams = true) {
		$url = clone $this->_authorizationURL;
		if ($includeParams) {
			$url->setQueryStringParam($this->_authorizationParams);
		}
		return $url;
	}
	
	/**
	 * @return OAuth\AbstractTokenResponseParser
	 */
	public function getParser() {
		return $this->_parser;
	}
	
	/**
	 * Provides a hook allowing subclasses to return the OAuth token's scope
	 * directly, rather than getting it through the response parser.
	 *
	 * @return string
	 */
	public function getTokenScope() {}
	
	/**
	 * Sets this instance to use (or not use) POST rather than GET when making
	 * requests against the authorization URL.
	 *
	 * @param boolean
	 */
	public function usePostToAuthorize($usePost = true) {
		$this->_postToAuthorize = (bool)$usePost;
	}
	
	/**
	 * Returns a hash that uniquely identifies this instance.
	 *
	 * @return string
	 */
	abstract public function getHash();
	
	/**
	 * Performs the full authorization process.
	 *
	 * @return string
	 */
	abstract public function authorize();
}