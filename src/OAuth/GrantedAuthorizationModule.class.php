<?php
namespace OAuth;

class GrantedAuthorizationModule extends AbstractAuthorizationModule {
	private $_authRequestBody;
	private $_authenticationURL;
	private $_authenticationParams = array();
	protected $_postToAuthenticate = false;
	private $_user;
	private $_clientID;
	private $_clientSecret;
	private $_redirectURL;
	private $_hash;
	// Default keys for OAuth params
	protected $_clientIDKey = 'client_id';
	protected $_clientSecretKey = 'client_secret';
	protected $_responseTypeKey = 'response_type';
	protected $_redirURLKey = 'redirect_uri';
	protected $_grantTypeKey = 'grant_type';
	protected $_authCodeKey = 'code';
	
	/**
	 * Instantiates an authorizer that uses the typical OAuth request flow,
	 * requiring a user to grant permission to an application. The token is
	 * specific to a certain client ID/secret pair, user ID string, and
	 * redirect URL. The user ID string may be anything, but it is intended to
	 * distinguish between different users on behalf of whom the authorization
	 * is taking place, and is used when generating this instance's internal
	 * identifier in order to enable the maintenance of distinct tokens for the
	 * same client ID/secret pair and redirect URL. In addition to those pieces
	 * of data, the URLs to be used for requesting authentication and
	 * authorization (which are separate in the OAuth flow that is this class'
	 * use case) must be specified here.
	 *
	 * @param OAuth\AbstractTokenResponseParser $parser
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $user
	 * @param string, URL $redirectURL
	 * @param string, URL $authenticationURL
	 * @param string, URL $authorizationURL
	 */
	public function __construct(
		AbstractTokenResponseParser $parser,
		$clientID,
		$clientSecret,
		$user,
		$redirectURL,
		$authenticationURL,
		$authorizationURL
	) {
		$this->_clientID = $clientID;
		if (!is_scalar($user)) {
			throw new InvalidArgumentException(
				'The user ID string must be a scalar value.'
			);
		}
		$this->_parser = $parser;
		$this->_user = $user;
		$this->_clientID = $clientID;
		$this->_clientSecret = $clientSecret;
		$this->_redirectURL = self::_castURL($redirectURL);
		$this->_authenticationURL = self::_castURL($authenticationURL);
		$this->_authorizationURL = self::_castURL($authorizationURL);
		/* Set the parameters for the authentication URL, and the ones we
		know at this stage for the authorization URL (of course, we won't yet
		know the code). */
		$params = array(
			$this->_clientIDKey => $clientID,
			$this->_responseTypeKey => 'code',
			$this->_redirURLKey => $this->_redirectURL->getURL()
		);
		$this->_authenticationParams = $params;
		unset($params[$this->_responseTypeKey]);
		$params[$this->_clientSecretKey] = $this->_clientSecret;
		$params[$this->_grantTypeKey] = 'authorization_code';
		$this->_authorizationParams = $params;
	}
	
	/**
	 * Sets this instance to use (or not use) POST rather than GET when making
	 * requests against the authentication URL.
	 *
	 * @param boolean
	 */
	public function usePostToAuthenticate($usePost = true) {
		$this->_postToAuthenticate = (bool)$usePost;
	}
	
	/**
	 * @return string
	 */
	public function getHash() {
		if ($this->_hash === null) {
			$this->_hash = md5(implode('|', array(
				$this->_user,
				$this->_clientID,
				$this->_clientSecret,
				(string)$this->_redirectURL
			)));
		}
		return $this->_hash;
	}
	
	/**
	 * Makes a request to the authentication URL and, if successful, extracts
	 * and returns the authorization code from the redirect URL. The response
	 * body (if any) will be placed in the $this->_authRequestBody property so
	 * that callers can get access to it if necessary.
	 *
	 * @return string
	 */
	public function requestAuthentication() {
		$ch = $this->_getCurlSession(
			$this->_authenticationURL,
			$this->_authenticationParams,
			$this->_postToAuthenticate
		);
		$this->_authRequestBody = $this->_getResponseFromCurlHandle($ch);
		curl_close($ch);
		return $this->getCodeFromHeaders();
	}
	
	/**
	 * Parses an array of HTTP headers to extract the authentication code
	 * returned from the OAuth server.
	 *
	 * @param array $headers = null
	 * @return string
	 */
	public function getCodeFromHeaders(array $headers = null) {
		if ($headers === null) {
			$headers = $this->_responseHeaders;
		}
		foreach ($headers as $line) {
			try {
				$matches = array();
				if (preg_match('/\blocation: *(.*)\s?/i', $line, $matches)) {
					$redirURL = new \URL($matches[1]);
					$qsData = $redirURL->getQueryStringData();
					if (array_key_exists($this->_authCodeKey, $qsData)) {
						return $qsData[$this->_authCodeKey];
					}
				}
			} catch (\URLException $e) {
				throw new UnexpectedValueException(
					'Encountered invalid redirect URL.'
				);
			}
		}
		throw new AuthenticationException(
			'Failed to obtain authentication code.'
		);
	}
	
	/**
	 * Requests the OAuth token from the provider using the code provided.
	 *
	 * @param string $code
	 */
	public function requestAuthorization($code) {
		$this->_authorizationParams[$this->_authCodeKey] = $code;
		$ch = $this->_getCurlSession(
			$this->_authorizationURL,
			$this->_authorizationParams,
			$this->_postToAuthorize
		);
		$this->_parser->setResponse($this->_getResponseFromCurlHandle($ch));
		$this->_handleAuthorizationError(
			$this->_getResponseCodeFromCurlHandle($ch)
		);
		curl_close($ch);
		if (!$this->_parser->getToken()) {
			throw new AuthorizationException('Failed to obtain OAuth token.');
		}
	}
	
	public function authorize() {
		$this->requestAuthorization($this->requestAuthentication());
	}
	
	/**
	 * @return string
	 */
	public function getAuthenticationRequestBody() {
		return $this->_authRequestBody;
	}
}
?>