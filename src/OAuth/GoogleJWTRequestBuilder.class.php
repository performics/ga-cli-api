<?php
namespace OAuth;

class GoogleJWTRequestBuilder extends AbstractTokenRequestBuilder {
	private static $_staticPropsReady = false;
	private $_hash;
	private $_user;
	private $_authTarget;
	private $_scope;
	private $_keyFile;
	private $_keyFilePassword;
	private $_onBehalfOfUser;
	
	/**
	 * @param string $user
	 * @param string $authTarget
	 * @param string, array $scopes
	 * @param string $keyFile,
	 * @param string $keyFilePassword,
	 * @param string $onBehalfOfUser = null
	 */
	public function __construct(
		$user,
		$authTarget,
		$scopes,
		$keyFile,
		$keyFilePassword,
		$onBehalfOfUser = null
	) {
		if (!self::$_staticPropsReady) {
			self::_initStaticProperties();
		}
		if (!is_file($keyFile)) {
			throw new InvalidArgumentException(
				'The private key file for signing the JWT must be a real file.'
			);
		}
		$this->_user = $user;
		$this->_authTarget = $authTarget;
		$this->_scope = is_array($scopes) ? implode(' ', $scopes) : $scopes;
		$this->_keyFile = $keyFile;
		$this->_keyFilePassword = $keyFilePassword;
		$this->_onBehalfOfUser = $onBehalfOfUser;
	}
	
	private static function _initStaticProperties() {
		if (!extension_loaded('openssl')) {
			throw new RuntimeException(
				'This class requires the OpenSSL extension.'
			);
		}
	}
	
	/**
	 * Builds and returns a JWT (JSON Web Signature) to submit to Google's
	 * OAuth2 endpoint. See https://developers.google.com/accounts/docs/OAuth2ServiceAccount
	 * for documentation on the contents of this signature.
	 *
	 * @return string
	 */
	private function _getJWT() {
		$header = array('alg' => 'RS256', 'typ' => 'JWT');
		$requestTime = time();
		$claimSet = array(
			'iss' => $this->_user,
			'scope' => $this->_scope,
			'aud' => $this->_authTarget,
			'exp' => $requestTime + 3600,
			'iat' => $requestTime
		);
		if ($this->_onBehalfOfUser !== null) {
			$claimSet['sub'] = $this->_onBehalfOfUser;
		}
		$payload = base64_encode(json_encode($header)) . '.'
		         . base64_encode(json_encode($claimSet));
		$cert = array();
		if (!openssl_pkcs12_read(
			file_get_contents($this->_keyFile), $cert, 'notasecret'
		)) {
			throw new RuntimeException(
				'Unable to load private key from file.'
			);
		}
		$signature = '';
		if (!openssl_sign($payload, $signature, $cert['pkey'], 'sha256')) {
			throw new RuntimeException('Unable to generate signature.');
		}
		return $payload . '.' . base64_encode($signature);
	}
	
	/**
	 * @return string
	 */
	public function getHash() {
		if ($this->_hash === null) {
			$this->_hash = md5(implode('|', array(
				$this->_user,
				$this->_scope,
				$this->_onBehalfOfUser,
				md5_file($this->_keyFile)
			)));
		}
		return $this->_hash;
	}
	
	/**
	 * @return array
	 */
	public function getRequestPayload() {
		return array(
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion' => $this->_getJWT()
		);
	}
	
	/**
	 * @return string
	 */
	public function getTokenScope() {
		return $this->_scope;
	}
}
?>