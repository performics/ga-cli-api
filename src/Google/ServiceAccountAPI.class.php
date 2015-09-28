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
	protected $_nextRequest;
	
	public function __construct() {
		$this->_mutex = new \Mutex(__CLASS__);
	}
	
	/**
	 * This method must register the appropriate OAuth service with the
	 * appropriate Google\ServiceAccountAPIRequest subclass. The child of this
	 * class is responsible for making sure this method is called at the
	 * appropriate time.
	 */
	abstract protected static function _configureOAuthService();
	
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
	 * Prepares a request in advance (this facilitates iterating through a
	 * paged response in a while loop).
	 *
	 * @param Google\ServiceAccountAPIRequest $request
	 */
	protected function _prepareRequest(ServiceAccountAPIRequest $request) {
		$this->_nextRequest = $request;
	}
	
	/**
	 * Makes an authorized request to a Google API. If the argument is omitted,
	 * this method will use a request previously set up via a call to
	 * $this->_prepareRequest(), if any. If the response includes the key
	 * 'items', this method returns an array of the corresponding items
	 * instantiated as the appropriate object type (if possible). If it
	 * includes the key 'kind', it returns an instance of the corresponding
	 * object type (if possible). If the request is made with no arguments and
	 * there is no next URL cached, it returns false.
	 *
	 * @param Google\ServiceAccountAPIRequest $request = null
	 * @return mixed
	 */
	protected function _makeRequest(ServiceAccountAPIRequest $request = null) {
		static::_configureOAuthService();
		if (!$request) {
			if ($this->_nextRequest === null) {
				return false;
			}
			$request = $this->_nextRequest;
		}
		$this->_mutex->acquire();
		try {
			$result = $this->_getResponse($request);
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
					/* Instantiate the same type of request we made previously,
					with the same verb and headers. */
					$class = get_class($request);
					$this->_nextRequest = new $class(new \URL(
						$this->_responseParsed['nextLink']
					));
					$this->_nextRequest->setHeader($request->getHeaders());
					$this->_nextRequest->setVerb($request->getVerb());
				} catch (\URLException $e) {
					throw new UnexpectedValueException(
						'Caught error while parsing paginated URL.', null, $e
					);
				}
			}
			else {
				$this->_nextRequest = null;
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
}