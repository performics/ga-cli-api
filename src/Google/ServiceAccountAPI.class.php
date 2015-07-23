<?php
namespace Google;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

abstract class ServiceAccountAPI extends \GenericAPI\Base {
	protected $_mutex;
	protected $_oauthService;
	protected $_responseFormat = self::RESPONSE_FORMAT_JSON;
	protected $_httpResponseActionMap = array(
		304 => self::ACTION_SUCCESS
	);
	// We won't get a response value if our request triggers a 304
	protected $_expectResponseLength = false;
	protected $_responseTries = 2;
	protected $_repeatPauseInterval = 5;
	protected $_nextURL;
	
	public function __construct() {
		$this->_mutex = new \Mutex(__CLASS__);
		$this->_oauthService = $this->_getOAuthService();
	}
	
	/**
	 * @return OAuth\Service
	 */
	abstract protected function _getOAuthService();
	
	/**
	 * Raises a generic RuntimeException. This method is mainly here to be
	 * overridden by subclasses.
	 */
	protected function _handleError() {
		throw new RuntimeException(
			'Encountered an error while querying API (response code was ' .
			$this->_responseCode . ').'
		);
	}
	
	/**
	 * Prepares a request so that it's not necessary to include the URL in the
	 * call to $this->_makeRequest(), which makes it easier to iterate through
	 * a paginated response.
	 *
	 * @param string, URL $baseURL
	 * @param array $params = null
	 */
	protected function _prepareRequest($baseURL, array $params = null) {
		$this->_nextURL = \URL::cast($baseURL);
		if ($params) {
			$this->_nextURL->setQueryStringParam($params);
		}
	}
	
	/**
	 * Makes an authorized request to a Google API. IF the first argument is
	 * omitted, the request will automatically be made to the URL that was set
	 * up in a previous call to $this->_prepareRequest() or returned as the
	 * next paginated URL during a previous request (the second argument is
	 * ignored in this case). If the response includes the key 'items', this
	 * method returns an array of the corresponding items instantiated as the
	 * appropriate object type (if possible). If it includes the key 'kind', it
	 * returns an instance of the corresponding object type (if possible). If
	 * the request is made with no arguments and there is no next URL cached,
	 * it returns false.
	 *
	 * @param string $baseURL = null
	 * @param array $params = null
	 * @param array $postData = null
	 * @param array $extraHeaders = null
	 * @return mixed
	 */
	protected function _makeRequest(
		$baseURL = null,
		array $params = null,
		array $postData = null,
		array $extraHeaders = null
	) {
		if ($baseURL === null) {
			if ($this->_nextURL === null) {
				return false;
			}
			/* The parent class still requires us to call
			$this->_buildRequestURL() in order to bind everything properly. */
			$qsParams = $this->_nextURL->getQueryStringData();
			$this->_nextURL->setQueryString(null);
			$this->_buildRequestURL((string)$this->_nextURL, $qsParams);
		}
		else {
			$this->_buildRequestURL($baseURL, $params);
		}
		$headers = array(
			'Authorization: Bearer ' . $this->_oauthService->getToken()
		);
		if ($extraHeaders) {
			$headers = array_merge($headers, $extraHeaders);
		}
		$this->_mutex->acquire();
		try {
			$result = $this->_getResponse(true, $postData, $headers);
			$this->_mutex->release();
		} catch (\Exception $e) {
			$this->_mutex->release();
			throw $e;
		}
		if (!$result) {
			$this->_handleError();
		}
		if ($this->_responseParsed) {
			if (array_key_exists('nextLink', $this->_responseParsed)) {
				try {
					$this->_nextURL = new \URL(
						$this->_responseParsed['nextLink']
					);
				} catch (\URLException $e) {
					throw new UnexpectedValueException(
						'Caught error while parsing paginated URL.', null, $e
					);
				}
			}
			else {
				$this->_nextURL = null;
			}
			return $this->_castResponse();
		}
	}
	
	/**
	 * Returns an object or an array of objects from the parsed response.
	 *
	 * @return array, Google\AbstractAPIResponseObject
	 */
	protected function _castResponse() {
		if (array_key_exists('items', $this->_responseParsed)) {
			$items = array();
			foreach ($this->_responseParsed['items'] as $item) {
				$items[] = APIResponseObjectFactory::create($item);
			}
			return $items;
		}
		return APIResponseObjectFactory::create($this->_responseParsed);
	}
	
	/**
	 * If the previous API call caused a paginated response and there is a next
	 * URL available, performs the request and returns its response value;
	 * otherwise returns false.
	 *
	 * @return array, boolean
	 */
	public function getNextPage() {
		if ($this->_nextURL === null) {
			return false;
		}
		return $this->_makeRequest();
	}
}