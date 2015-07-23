<?php
namespace OAuth;

class UserlessAuthorizationModule extends AbstractAuthorizationModule {
	private $_builder;
	
	/**
	 * @param OAuth\AbstractTokenResponseParser $parser
	 * @param OAuth\AbstractTokenRequestBuilder $builder
	 * @param string, URL $authorizationURL
	 */
	public function __construct(
		AbstractTokenResponseParser $parser,
		AbstractTokenRequestBuilder $builder,
		$authorizationURL
	) {
		$this->_parser = $parser;
		$this->_builder = $builder;
		$this->_authorizationURL = self::_castURL($authorizationURL);
	}
	
	/**
	 * @return string
	 */
	public function getHash() {
		return $this->_builder->getHash();
	}
	
	/**
	 * @return string
	 */
	public function getTokenScope() {
		return $this->_builder->getTokenScope();
	}
	
	public function authorize() {
		$this->_authorizationParams = http_build_query(
			$this->_builder->getRequestPayload()
		);
		$ch = $this->_getCurlSession(
			$this->_authorizationURL,
			$this->_authorizationParams,
			$this->_postToAuthorize
		);
		$this->_parser->setResponse($this->_getResponseFromCurlHandle($ch));
		$this->_handleAuthorizationError(
			$this->_getResponseCodeFromCurlHandle($ch)
		);
	}
}
?>