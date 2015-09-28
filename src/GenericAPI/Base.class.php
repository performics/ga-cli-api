<?php
namespace GenericAPI;
/* Base class for generic Web service API behavior (i.e. building request URLs,
getting REST data, etc.) */
abstract class Base {
	const ACTION_SUCCESS = 0;
	const ACTION_REPEAT_REQUEST = 1;
	const ACTION_BREAK_REQUEST = 2;
	const ACTION_URL_MOVED = 3;
	const RESPONSE_FORMAT_JSON = 1;
	/* I'm removing support for this. It was only ever in here in the first
	place because Yahoo's old geocoding API supported returning its responses
	as serialized PHP, and back then I wasn't thinking about the security
	considerations. */
	//const RESPONSE_FORMAT_PHP = 2;
	const RESPONSE_FORMAT_XML = 3;
	protected static $_sslCerts = array(); // keyed on host portion of the URL
	private static $_SETTINGS = array(
		'API_VERBOSE_REQUESTS' => false,
		'API_DEBUG' => false
	);
	private static $_SETTING_TESTS = array(
		'API_VERBOSE_REQUESTS' => 'boolean',
		'API_DEBUG' => 'boolean'
	);
	private static $_testedGenericSettings = false;
	private static $_HTTP_RESPONSE_DEFAULT_MAP = array();
	/* This associates standard response formats with the callbacks that parse
	them and any extra arguments that should be passed to it in addition to the
	raw response itself. */
	private static $_STANDARD_PARSE_CALLBACKS = array(
		self::RESPONSE_FORMAT_JSON => array('json_decode', true),
		self::RESPONSE_FORMAT_XML => array(array('PFXUtils', 'xmlToArray'))
	);
	/* I am letting subclasses define their own validators so they can declare
	namespaces for their exceptions. */
	private static $_validator;
	protected $_transferFile;
	protected $_transferFileName;
	private $_transferFileType;
	protected $_archiveCount;
	private $_parseCallback;
	private $_parseCallbackArgs = array();
	private $_lastRequestTimeVarName;
	private $_instanceSetupComplete = false;
	private $_requestBound;
	private $_curlHandle;
	private $_curlOptions;
	private $_attemptCount;
	private $_shmSegment;
	/* A mutex is necessary if a minimum time in between requests is to be
	enforced; it may also be necessary for more specific behavior in the
	subclass. If the subclass doesn't declare one, but invokes options that
	require one, a generic one will be used. */
	protected $_mutex;
	protected $_EXCEPTION_TYPE;
	protected $_responseTries = 1;
	// Seconds between repeated attempts to make the same API call
	protected $_repeatPauseInterval = 2;
	// Milliseconds to wait between making two different calls to the same API
	protected $_requestDelayInterval;
	protected $_httpResponseActionMap = array();
	protected $_responseFormat;
	protected $_request;
	protected $_responseRaw;
	protected $_responseParsed;
	protected $_responseCode;
	protected $_responseHeader;
	/* This controls what gets written to the transfer file (if the child class
	defines one) to separate entries. */
	protected $_transferEOL = "\n";
	/* If this property is true, $this->_responseParsed will always contain an
	array after the parsing of the API response, even if the response is empty
	or cannot be decoded. */
	protected $_guaranteeResponseArray = true;
	/* If this property is true, an instance of the registered exception type
	will be thrown automatically if the API returns a zero-byte response. */
	protected $_expectResponseLength = true;
	/* If this property is set, it will be treated as a prototype against which
	to compare the parsed API response in order to confirm that it looks like
	it should. For example take the following prototype:
	
	array(
		'meta' => null,
		'data' => array(
			'foo' => null,
			'bar' => null
		)
	)
	
	This would cause an exception to be thrown if the response did not contain
	the keys 'meta' and 'data', or if the 'data' array inside the response did
	not contain the keys 'foo' and 'bar'. */
	protected $_responsePrototype;
	
	public function __destruct() {
		// Clean up our shared memory segment if possible
		if ($this->_shmSegment && !$this->_shmSegment->isDestroyed() &&
		    $this->_requestDelayInterval !== null)
		{
			$this->_mutex->acquire();
			if ($this->_shmSegment->hasVar($this->_lastRequestTimeVarName)) {
				$timeSinceLast = \PFXUtils::millitime() - $this->_shmSegment->getVar(
					$this->_lastRequestTimeVarName, 0
				);
				if ($timeSinceLast >= $this->_requestDelayInterval) {
					$this->_shmSegment->removeVar(
						$this->_lastRequestTimeVarName
					);
				}
			}
			$this->_mutex->release();
		}
	}
	
	/**
	 * Creates a default mapping of the actions that should happen as a result
	 * of HTTP response codes above the 200 range.
	 */
	private static function _buildDefaultHTTPActionMap() {
		for ($i = 200; $i < 300; $i++) {
			self::$_HTTP_RESPONSE_DEFAULT_MAP[$i] = self::ACTION_SUCCESS;
		}
		for ($i = 300; $i < 400; $i++) {
			self::$_HTTP_RESPONSE_DEFAULT_MAP[$i] = self::ACTION_URL_MOVED;
		}
		for ($i = 400; $i <= 600; $i++) {
			self::$_HTTP_RESPONSE_DEFAULT_MAP[$i] = self::ACTION_BREAK_REQUEST;
		}
		// Some 500-level codes imply the need to retry, but some don't
		self::$_HTTP_RESPONSE_DEFAULT_MAP[500] = self::ACTION_REPEAT_REQUEST;
		self::$_HTTP_RESPONSE_DEFAULT_MAP[502] = self::ACTION_REPEAT_REQUEST;
		self::$_HTTP_RESPONSE_DEFAULT_MAP[503] = self::ACTION_REPEAT_REQUEST;
		self::$_HTTP_RESPONSE_DEFAULT_MAP[504] = self::ACTION_REPEAT_REQUEST;
		// And make sure failure to respond is covered
		self::$_HTTP_RESPONSE_DEFAULT_MAP[0] = self::ACTION_REPEAT_REQUEST;
	}
	
	/**
	 * Ensures that the prototype in the argument is an array, the values in
	 * which are either nulls or deeper arrays.
	 *
	 * @param array $prototype
	 */
	private static function _validateResponsePrototype($prototype) {
		if (!is_array($prototype)) {
			return false;
		}
		foreach ($prototype as $key => $val) {
			if (!(is_array($val) && self::_validateResponsePrototype($val)) &&
			    $val !== null)
			{
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Evaluates an API response (or a sub-element thereof) against the
	 * prototype and returns a boolean value to indicate a match or a failure.
	 *
	 * @param array $response
	 * @param array $prototype
	 * @return boolean
	 */
	private static function _compareResponseAgainstPrototype(
		array $response,
		array $prototype
	) {
		if (!is_array($response)) {
			return false;
		}
		foreach ($prototype as $key => $value) {
			if (!array_key_exists($key, $response) || (
				$value !== null && !self::_compareResponseAgainstPrototype(
					$response[$key], $value
				)))
			{
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Specify the full path to a file containing an SSL certificate to be used
	 * to make connections (optionally to a particular host only).
	 *
	 * @param string $certFile
	 * @param string $host = '*'
	 */
	protected static function _registerSSLCertificate($certFile, $host = '*') {
		if (!file_exists($certFile)) {
			throw new \RuntimeException(
				'Could not find file ' . $certfile . '.'
			);
		}
		self::$_sslCerts[$host] = $certFile;
	}
	
	/**
	 * Accepts an associative array of parameters (i.e. from a request URL or
	 * from the POST portion of a request) and reformats them as a string for
	 * logging purposes, after first removing any parameters with keys matching
	 * those in the second argument. If the third argument is true, this method
	 * will only perform the removal and return the modified array. When a
	 * string is returned, its format is "key1:value1;key2:value2;...".
	 *
	 * @param array $params
	 * @param array $removeParams
	 * @param boolean $asArray = false
	 * @return string, array
	 */
	protected static function _getLoggableParams(
		array $params,
		array $removeParams = null,
		$asArray = false
	) {
		if ($removeParams) {
			foreach ($removeParams as $key) {
				unset($params[$key]);
			}
		}
		if ($asArray) {
			return $params;
		}
		$paramKeys = array_keys($params);
		$paramCount = count($paramKeys);
		$args = '';
		for ($i = 0; $i < $paramCount; $i++) {
			$args .= $paramKeys[$i] . ':' . $params[$paramKeys[$i]];
			if ($i + 1 < $paramCount) {
				$args .= ';';
			}
		}
		return $args;
	}
	
	/**
	 * Handles certain setup tasks. This is the kind of thing that a
	 * constructor would handle, but since this is an abstract class and I want
	 * child classes to be free to accept any arguments they want, I'm not
	 * using a constructor here.
	 */
	private function _setupInstance() {
		if (!self::$_testedGenericSettings) {
			\PFXUtils::validateSettings(
				self::$_SETTINGS, self::$_SETTING_TESTS
			);
			self::_buildDefaultHTTPActionMap();
			self::$_validator = new \Validator();
		}
		/* If there is an exception type configured, validate that it really
		exists. Otherwise default to RuntimeException. */
		if ($this->_EXCEPTION_TYPE) {
			if (!class_exists($this->_EXCEPTION_TYPE) ||
			    !is_a($this->_EXCEPTION_TYPE, 'Exception', true))
			{
				throw new \Exception(
					'Exception types registered in descendants of this ' .
					'class must be valid Exception subclasses.'
				);
			}
		}
		else {
			$this->_EXCEPTION_TYPE = 'RuntimeException';
		}
		if ($this->_responseFormat && array_key_exists(
			$this->_responseFormat, self::$_STANDARD_PARSE_CALLBACKS
		)) {
			$callbackData = self::$_STANDARD_PARSE_CALLBACKS[
				$this->_responseFormat
			];
			$callback = array_shift($callbackData);
			$this->_registerParseCallback($callback, $callbackData);
		}
		self::$_validator->number(
			$this->_responseTries,
			'The number of attempts to use when connecting to API URLs ' .
			'must be a non-zero integer.',
			\Validator::ASSERT_INT_DEFAULT
		);
		self::$_validator->number(
			$this->_repeatPauseInterval,
			'The number of seconds to wait before repeating an API ' .
			'request must be a positive integer.',
			\Validator::ASSERT_INT | \Validator::ASSERT_POSITIVE
		);
		if ($this->_requestDelayInterval !== null) {
			self::$_validator->number(
				$this->_requestDelayInterval,
				'Delay intervals must be positive integers.',
				\Validator::ASSERT_INT | \Validator::ASSERT_POSITIVE
			);
			/* In order to observe this delay across processes, we need to have
			a mutex and use shared memory. */
			try {
				if (!$this->_mutex) {
					$this->_mutex = new \Mutex($this);
				}
				/* We will be keeping track of a millisecond timestamp, which
				means 13 digits. */
				$allocBytes = \SharedMemory::getRequiredBytes(
					array(1000000000000)
				);
				$this->_shmSegment = new \SharedMemory(
					$this->_mutex, $allocBytes
				);
			} catch (\Exception $e) {
				throw new $this->_EXCEPTION_TYPE(
					'Caught error while configuring shared memory segment.',
					null,
					$e
				);
			}
			/* Not only do we need to keep track of the delay interval, but we
			also need a variable name that reflects the specific subclass for
			which we are tracking this interval. */
			$this->_lastRequestTimeVarName = 'req' . get_class($this);
		}
		if ($this->_responsePrototype !== null &&
		    !self::_validateResponsePrototype($this->_responsePrototype))
		{
			throw new \UnexpectedValueException(
				'The response prototype, if declared, must be an array, and ' .
				'its values must be either arrays or null.'
			);
		}
		foreach (self::$_HTTP_RESPONSE_DEFAULT_MAP as $code => $action) {
			/* If the child has already registered certain actions in any of
			these positions, skip them. */
			if (!array_key_exists($code, $this->_httpResponseActionMap)) {
				$this->_httpResponseActionMap[$code] = $action;
			}
		}
		$this->_instanceSetupComplete = true;
	}
	
	/**
	 * Registers a callback for $this->_parseResponse() to use in order to
	 * decode information returned from the API. This callback must accept
	 * $this->_responseRaw as its first parameter. If any other arguments need
	 * to be passed to the callback, they may be passed in the $extraParams
	 * array. The callback's return value will be set in 
	 * $this->_responseParsed.
	 *
	 * @param callable $callback
	 * @param array $extraParams = null
	 */
	protected function _registerParseCallback(
		$callback,
		array $extraParams = null
	) {
		if (!is_callable($callback)) {
			throw new \InvalidArgumentException(
				'The argument passed to this method was not a callable object.'
			);
		}
		$this->_parseCallback = $callback;
		$this->_parseCallbackArgs = array(&$this->_responseRaw);
		if ($extraParams) {
			$this->_parseCallbackArgs = array_merge(
				$this->_parseCallbackArgs, $extraParams
			);
		}
	}
	
	/**
	 * Called by cURL to store the header data.
	 *
	 * @param resource $ch
	 * @param string $header
	 * @return int
	 */
	private function _setHeader($ch, $header) {
		$this->_responseHeader .= $header;
		return strlen($header);
	}
	
	/**
	 * Resets various properties in preparation for making a request to the
	 * Web service.
	 */
	private function _resetState() {
		$this->_responseRaw = null;
		$this->_responseParsed = null;
		$this->_responseCode = null;
		$this->_attemptCount = 0;
		$this->_responseHeader = '';
		$this->_curlOptions = array();
		$this->_curlHandle = curl_init();
		$this->_request = null;
		$this->_requestBound = false;
		$opts = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADERFUNCTION => array($this, '_setHeader')
		);
		$this->_setCurlOption($opts);
	}
	
	/**
	 * Sets one or more cURL options. These may be passed with the option
	 * constant as the first argument and its value as the second argument, or
	 * as a single associative array of options to values (the second argument
	 * is ignored in this case).
	 *
	 * @param int, array $opt
	 * @param mixed $optVal = null
	 */
	private function _setCurlOption($opt, $optVal = null) {
		if (is_array($opt)) {
			curl_setopt_array($this->_curlHandle, $opt);
			$this->_curlOptions = array_replace($this->_curlOptions, $opt);
		}
		else {
			curl_setopt($this->_curlHandle, $opt, $optVal);
			$this->_curlOptions[$opt] = $optVal;
		}
	}
	
	/**
	 * Binds the characteristics of the request to the cURL session.
	 */
	private function _bindRequestToCurlHandle() {
		if (API_VERBOSE_REQUESTS) {
			echo 'Request URL is ' . $this->_request->getURL() . PHP_EOL;
		}
		$this->_setCurlOption($this->_request->getCurlOptions());
		if (API_VERBOSE_REQUESTS && $this->_request->getVerb() == 'POST') {
			echo 'POST data is as follows: ';
			$postData = $this->_request->getPostParameters();
			if (is_array($postData)) {
				echo '(' . PHP_EOL;
				foreach ($postData as $key => $val) {
					$len = strlen($val);
					if ($len > 160) {
						$val = substr($val, 0, 133) . '...(' . ($len - 133)
							 . ' byte(s) truncated)';
					}
					echo "\t" . $key . ': ' . $val . PHP_EOL;
				}
				echo ')';
			}
			else {
				$len = strlen($postData);
				if ($len > 160) {
					echo substr($postData, 0, 133) . '...('
					   . ($len - 133) . ' byte(s) truncated)';
				}
				else {
					echo $postData;
				}
			}
			echo PHP_EOL;
		}
		$url = $this->_request->getURL();
		if ($url->getScheme() == 'https') {
			/* See whether a specific CA bundle has been registered for us to
			use, starting by checking for the specific host, then the wildcard.
			Otherwise just let cURL do what it would by default, which may or
			may not work depending on the environment. */
			// First try specific host, then try wildcard
			$host = $url->getHost();
			$certFile = null;
			if (array_key_exists($host, self::$_sslCerts)) {
				$certFile = self::$_sslCerts[$host];
			}
			elseif (array_key_exists('*', self::$_sslCerts)) {
				$certFile = self::$_sslCerts['*'];
			}
			if ($certFile) {
				$this->_setCurlOption(array(
					CURLOPT_SSL_VERIFYPEER => true,
					CURLOPT_SSL_VERIFYHOST => 2,
					CURLOPT_CAINFO => $certFile
				));
			}
		}
		$this->_requestBound = true;
	}
	
	/**
	 * Executes the active cURL handle and returns its response. This normally
	 * should never need to be overridden but is protected rather than private
	 * to facilitate unit testing.
	 *
	 * @return string
	 */
	protected function _executeCurlHandle() {
		if (API_DEBUG) {
			echo "Debug mode active; assuming successful response.\n";
			return true;
		}
		return curl_exec($this->_curlHandle);
	}
	
	/**
	 * Returns the last HTTP response recorded by the active cURL handle. This
	 * normally should never need to be overridden but is protected rather than
	 * private to facilitate unit testing.
	 *
	 * @return int
	 */
	protected function _getLastHTTPResponse() {
		if (API_DEBUG) {
			return 200;
		}
		return curl_getinfo($this->_curlHandle, CURLINFO_HTTP_CODE);
	}
	
	/**
	 * Performs a request, which may involve more than one call (e.g. if there
	 * is a redirect involved or the request fails on the first attempt and is
	 * subsequently retried). This method returns false if the HTTP status code
	 * of the final call was anything other than 200, unless it was handled by
	 * mapping an HTTP status to an action.
	 *
	 * @param GenericAPI\Request $request
	 * @param boolean $parse = true
	 * @return boolean
	 */
	protected function _getResponse(Request $request, $parse = true) {
		if (!$this->_instanceSetupComplete) {
			$this->_setupInstance();
		}
		if ($this->_requestDelayInterval) {
			$this->_mutex->acquire();
			$timeSinceLast = \PFXUtils::millitime() - $this->_shmSegment->getVar(
				$this->_lastRequestTimeVarName, 0
			);
			if ($timeSinceLast < $this->_requestDelayInterval) {
				usleep(($this->_requestDelayInterval - $timeSinceLast) * 1000);
			}
			$this->_mutex->release();
		}
		$this->_resetState();
		$this->_request = $request;
		for ($i = 0; $i < $this->_responseTries; $i++) {
			/* This needs to be reinitialized on each loop iteration, or else
			we will end up returning false when the first attempt fails but a
			subsequent attempt succeeds; this is because
			$this->_determineResponseAction() will only modify this variable's
			value if it is null. */
			$rtnVal = null;
			$this->_attemptCount++;
			if (API_VERBOSE_REQUESTS) {
				echo 'Executing attempt ' . $this->_attemptCount . ' of '
				   . $this->_responseTries . '...' . PHP_EOL;
			}
			if (!$this->_requestBound) {
				$this->_bindRequestToCurlHandle();
			}
			$response = $this->_executeCurlHandle();
			/* I didn't used to set the raw response property until this loop
			was finished, but we need it in order to make the call to
			$this->_determineResponseAction() work correctly. In order to
			preserve the legacy behavior, I'm going to leave the property null
			if we get a zero-byte response. */
			if (strlen($response)) {
				$this->_responseRaw = $response;
			}
			if ($this->_requestDelayInterval) {
				// Store last request timestamp down to the millisecond
				$this->_shmSegment->putVar(
					$this->_lastRequestTimeVarName, \PFXUtils::millitime()
				);
			}
			$this->_responseCode = $this->_getLastHTTPResponse();
			if (API_VERBOSE_REQUESTS) {
				echo 'Response code was ' . $this->_responseCode
				   . '; received ' . strlen($response) . ' bytes' . PHP_EOL;
			}
			$action = $this->_determineResponseAction($rtnVal);
			if ($action === null) {
				throw new $this->_EXCEPTION_TYPE(
					'Failed to determine response action (response code was ' .
					$this->_responseCode . ').'
				);
			}
			if ($action == self::ACTION_URL_MOVED) {
				/* This condition throws an exception so it's easier to know
				that URLs in library code need to be updated. Note that this
				only takes effect if the request is not set to redirect
				automatically or if the number of redirects emitted by the
				remote service exceeds 10. */
				$headers = $this->getResponseHeaderAsAssociativeArray();
				if (isset($headers['Location'])) {
					$message = 'The remote service reports that this resource '
					         . 'has moved to ' . $headers['Location']
							 . ' (response code was ' . $this->_responseCode
							 . ').';
				}
				else {
					$message = 'Got response code ' . $this->_responseCode
					         . ' from remote service.';
				}
				throw new $this->_EXCEPTION_TYPE($message);
			}
			if ($action != self::ACTION_REPEAT_REQUEST) {
				break;
			}
			sleep($this->_repeatPauseInterval);
		}
		/* In order for certain things to work properly (e.g. the storing of
		raw SERP source code from the SEMRush API), we need to parse the
		response before we store the raw response. However, if for some reason
		the parse code throws an exception, we don't want to die without at
		least attempting to store the raw data. Therefore, we'll catch any
		exceptions here, then re-throw them after storing the raw response. */
		$rethrowException = null;
		if ($parse) {
			try {
				$this->_parseResponse();
			} catch (\Exception $e) {
				$rethrowException = $e;
			}
		}
		if (strlen($this->_responseRaw)) {
			if ($this->_transferFile) {
				$this->_storeRawResponse();
			}
			if ($rethrowException) {
				throw $rethrowException;
			}
		}
		elseif ($this->_expectResponseLength) {
			throw new $this->_EXCEPTION_TYPE(
				'Got empty response from API (last HTTP response code was ' .
				$this->_responseCode . ').'
			);
		}
		return $rtnVal;
	}
	
	/** 
	 * This method will be called immediately after every call to the remote
	 * service. Subclasses may override it to determine whether the request
	 * needs to be repeated or terminated. This is necessary particularly when
	 * working with web services that do a bad job of reliably messaging their
	 * errors in the form of HTTP status codes. It will have property access to
	 * the raw response and the HTTP response code, but not the parsed
	 * response. It should return one of the GenericAPI\Base::ACTION_*
	 * constants, or null, in which case the HTTP response code will be
	 * examined as usual in order to determine the course of action. This
	 * method also has the opportunity to set the boolean value that
	 * $this->_getResponse() will return.
	 *
	 * @param boolean &$rtnVal
	 * @return int
	 */
	protected function _determineResponseAction(&$rtnVal) {
		if (array_key_exists(
			$this->_responseCode, $this->_httpResponseActionMap
		)) {
			$action = $this->_httpResponseActionMap[$this->_responseCode];
		}
		else {
			$action = null;
		}
		// Only set the return value if the subclass hasn't already
		if ($rtnVal === null) {
			if ($this->_responseCode < 400) {
				$rtnVal = true;
			}
			else {
				$rtnVal = false;
			}
		}
		return $action;
	}
	
	/**
	 * Parses response as govered by the registered callback and sets it in
	 * $_responseParsed.
	 */
	protected function _parseResponse() {
		if (!$this->_parseCallback) {
			throw new $this->_EXCEPTION_TYPE(
				'No parse callback has been registered.'
			);
		}
		$this->_responseParsed = call_user_func_array(
			$this->_parseCallback, $this->_parseCallbackArgs
		);
		if ($this->_responsePrototype !== null &&
		    !self::_compareResponseAgainstPrototype(
				$this->_responseParsed, $this->_responsePrototype
			))
		{
			throw new $this->_EXCEPTION_TYPE(
				'The response failed to match the prototype.'
			);
		}
		if ($this->_guaranteeResponseArray &&
		    !is_array($this->_responseParsed))
		{
			$this->_responseParsed = array();
		}
	}
	
	/**
	 * Store the raw response in the file handle contained in the calling
	 * instance's $_transferFile property. If the $extraData parameter is
	 * passed, it will be written to the transfer file immediately before the
	 * contents of $this->_responseRaw. Because this method is normally called
	 * without arguments by $this->_getResponse(), in order to pass this
	 * parameter, child classes should override this method with a replacement
	 * method that is capable of supplying the necessary string, then have it
	 * make an explicit call to the overridden method.
	 *
	 * @param string $extraData
	 */
	protected function _storeRawResponse($extraData = null) {
		if (!$this->_transferFileType) {
			if (is_resource($this->_transferFile) &&
			    get_resource_type($this->_transferFile) == 'stream')
			{
				$metadata = stream_get_meta_data($this->_transferFile);
				$this->_transferFileType = $metadata['stream_type'];
			}
			else {
				throw new \LogicException(
					'The transfer file must be opened before storing the ' .
					'response.'
				);
			}
		}
		$useLocalMutex = $this->_mutex && !$this->_mutex->isAcquired();
		if ($useLocalMutex) {
			$this->_mutex->acquire();
		}
		$content = '';
		if ($extraData !== null) {
			$content = $extraData . $this->_transferEOL;
		}
		$content .= $this->_responseRaw . $this->_transferEOL;
		if ($this->_transferFileType == 'ZLIB') {
			$bytes = gzwrite($this->_transferFile, $content);
		}
		else {
			$bytes = fwrite($this->_transferFile, $content);
		}
		if ($this->_archiveCount !== null) {
			$this->_archiveCount++;
		}
		$contentBytes = strlen($content);
		if ($bytes != $contentBytes) {
			throw new $this->_EXCEPTION_TYPE(
				'Wrote ' . $bytes . ' bytes to ' .
				$this->_transferFileName . '; expected to write ' .
				$contentBytes . '.'
			);
		}
		if ($useLocalMutex) {
			$this->_mutex->release();
		}
	}
	
	/**
	 * @return int
	 */
	public function getResponseCode() {
		return $this->_responseCode;
	}
	
	/**
	 * @return GenericAPI\Request
	 */
	public function getRequest() {
		return $this->_request;
	}
	
	/**
	 * @return string
	 */
	public function getRawResponse() {
		return $this->_responseRaw;
	}
	
	/**
	 * @return mixed
	 */
	public function getResponse() {
		return $this->_responseParsed;
	}
	
	/**
	 * @return string
	 */
	public function getResponseHeader() {
		return $this->_responseHeader;
	}
	
	/**
	 * Gets the response header from the last transfer (if there is one) as a
	 * numerically-indexed array.
	 *
	 * @return array
	 */
	public function getResponseHeaderAsArray() {
		if (!strlen($this->_responseHeader)) {
			return;
		}
		$headerLines = explode("\n", $this->_responseHeader);
		// Clean up any carriage returns that might be in there
		foreach ($headerLines as &$line) {
			$line = trim($line);
		}
		return $headerLines;
	}
	
	/**
	 * Gets the response header from the last transfer (if there is one) as an
	 * associative array. Note that if the same header occurs multiple times
	 * within the response header, only the last corresponding value will be
	 * preserved.
	 *
	 * @return array
	 */
	public function getResponseHeaderAsAssociativeArray() {
		if (!strlen($this->_responseHeader)) {
			return;
		}
		$headerLines = $this->getResponseHeaderAsArray();
		$headerDict = array();
		foreach ($headerLines as $line) {
			$cPos = strpos($line, ':');
			if ($cPos !== false) {
				$key = trim(substr($line, 0, $cPos));
				$val = trim(substr($line, $cPos + 1));
				/* If a header appears multiple times, make it available as a
				list. */
				if (array_key_exists($key, $headerDict)) {
					if (!is_array($headerDict[$key])) {
						$headerDict[$key] = array($headerDict[$key]);
					}
					$headerDict[$key][] = $val;
				}
				else {
					$headerDict[$key] = $val;
				}
			}
		}
		return $headerDict;
	}
	
	/**
	 * Returns the array of cURL options that was effective for the last
	 * request.
	 */
	public function getCurlOptions() {
		return $this->_curlOptions;
	}
	
	/**
	 * Returns the number of attempts made during the last call to
	 * $this->_getResponse() before the request succeeded or failed.
	 *
	 * @return int
	 */
	public function getAttemptCount() {
		return $this->_attemptCount;
	}
}
?>
