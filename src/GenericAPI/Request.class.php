<?php
namespace GenericAPI;

class Request {
    /* These constants define the type of validation that this instance's
    $_responseValidator property will enforce (if any). It can work as a simple
    list of keys for which to test existence in the response or as a data
    structure that the response must mirror. */
    const VALIDATE_KEYS = 1;
    const VALIDATE_PROTOTYPE = 2;
    protected static $_baseURL;
    protected static $_persistentArgs = array();
    protected $_EXCEPTION_TYPE = 'GenericAPI\ResponseValidationException';
    protected $_autoRedirect = true;
    protected $_forcePersistentArgsToGet = false;
    protected $_url;
    /* This is specifically for query string parameters, as opposed to POST
    fields, etc. */
    protected $_args;
    protected $_payload;
    protected $_verb;
    protected $_extraHeaders = array();
    protected $_httpUser;
    protected $_httpPass;
    /* If this property is true, a zero-byte response will be treated as a
    validation failure. */
    protected $_expectResponseLength = true;
    /* Depending on the value of this instance's $_responseValidationMethod
    property, this property should contain either a list of keys that must be
    present in the API response, or a more complex data structure. As an
    example of the latter case, consider the following prototype:
    
    array(
        'meta' => null,
        'data' => array(
            'foo' => null,
            'bar' => null
        )
    )
    
    If this instance's $_responseValidator property were set to that, it would
    cause an exception to be thrown if the response did not contain the keys
    'meta' and 'data', or if the 'data' array inside the response did not
    contain the keys 'foo' and 'bar'. */
    protected $_responseValidator;
    protected $_responseValidationMethod = self::VALIDATE_KEYS;
    
    /**
     * Constructs a new request.
     *
     * @param string, URL $url
     * @param array $args = null
     */
    public function __construct($url, array $args = null) {
        $this->setURL($url, $args);
    }
    
    /**
     * Validates that an API response (or a sub-element thereof) matches a
     * given prototype.
     *
     * @param array $response
     * @param array $prototype
     * @return boolean
     */
    private function _compareResponseToPrototype(
        $response,
        array $prototype
    ) {
        /* Note that we didn't use the type hint in the method signature
        because this is where we need to handle the case of a node in the
        response that's expected to be an array in fact being something else.
        */
        if (!is_array($response)) {
            return false;
        }
        foreach ($prototype as $key => $value) {
            if (!array_key_exists($key, $response) || (
                $value !== null && !$this->_compareResponseToPrototype(
                    $response[$key], $value
                )))
            {
                $this->_handleValidationFailure(array($key));
            }
        }
        return true;
    }
    
    /**
     * Throws an exception to indicate the validation failure.
     *
     * @param array $keys = null
     */
    protected function _handleValidationFailure(array $key = null) {
        if ($key === null) {
            /* By convention, this means the response was empty when it
            shouldn't have been. */
            throw new $this->_EXCEPTION_TYPE(
                'Got unexpected empty response.'
            );
        }
        throw new $this->_EXCEPTION_TYPE(
            'Validation of the response failed for the following key(s): ' .
            implode(', ', $key)
        );
    }
    
    /**
     * Changes the request's URL. If the first argument is a URL instance, it
     * will override any base URL that may be set in the class; otherwise it
     * will be appended to the base URL when constructing the full request URL.
     * Any parameters provided to the constructor in the second argument are
     * automatically treated as GET parameters, even if the instance's verb is
     * set otherwise.
     *
     * @param string, URL $url
     * @param array $args = null
     */
    public function setURL($url, array $args = null) {
        if (is_object($url)) {
            if (!($url instanceof \URL)) {
                throw new \InvalidArgumentException(
                    'Request URLs, if provided as objects, must be URL instances.'
                );
            }
            $this->_url = $url;
        }
        else {
            $this->_url = new \URL(static::$_baseURL . $url);
        }
        // Separate any query string parameters from the URL
        $urlArgs = $this->_url->getQueryStringData();
        if ($urlArgs) {
            $this->_url->setQueryString(null);
            /* Merge these with any arguments provided in the second argument,
            preferring the latter. */
            if ($args) {
                $args = array_merge($urlArgs, $args);
            }
            else {
                $args = $urlArgs;
            }
        }
        if ($args) {
            $this->setGetParameters($args);
        }
    }
    
    /**
     * Sets the HTTP verb.
     *
     * @param string $verb
     */
    public function setVerb($verb) {
        $this->_verb = $verb;
    }
    
    /**
     * Sets the request's GET parameters. Note that if any such parameters were
     * passed in the constructor, either as part of the URL argument or
     * discretely, they are eligible to be overwritten by this method.
     *
     * @param array $args
     */
    public function setGetParameters(array $args) {
        $this->_args = $args;
    }
    
    /**
     * Sets the request's payload. This may be passed as an array, in which
     * case the default behavior will be to treat this request as a POST with
     * a content type of "multipart/form-data"; or as a string, in which case
     * the content type will be set as the value passed in the second argument.
     *
     * @param array, string $args
     * @param string $contentType = 'application/x-www-form-urlencoded'
     */
    public function setPayload(
        $args,
        $contentType = 'application/x-www-form-urlencoded'
    ) {
        if (is_array($args)) {
            $this->replaceHeader('Content-Type', 'multipart/form-data');
        }
        elseif (is_string($args)) {
            $this->replaceHeader('Content-Type', $contentType);
        }
        else {
            throw new \InvalidArgumentException(
                'The payload must be passed either as an array or as a string.'
            );
        }
        $this->_payload = $args;
    }
    
    /**
     * Sets additional HTTP headers that will be sent verbatim with this
     * request. This method may be called as a variadic function accepting an
     * arbitrary number of headers as strings, or with a single array
     * containing all the headers to be set.
     *
     * @param string, array $header
     * [@param string $header...]
     */
    public function setHeader() {
        $args = func_get_args();
        if (!$args) {
            throw new \InvalidArgumentException(
                'This method requires at least one argument.'
            );
        }
        if (is_array($args[0])) {
            if (count($args) > 1) {
                throw new \InvalidArgumentException(
                    'When an array of headers is passed to this method, no ' .
                    'additional arguments may be passed.' 
                );
            }
            $args = $args[0];
        }
        foreach ($args as $arg) {
            if (!is_string($arg)) {
                throw new \InvalidArgumentException(
                    'HTTP headers must be passed as strings.'
                );
            }
        }
        $this->_extraHeaders = array_merge($this->_extraHeaders, $args);
    }
    
    /**
     * Replaces the given header, as identified by its key, with the header
     * represented by the arguments. If the value is null, the given header
     * will be removed rather than replaced. If a matching header does not yet
     * exist in this instance, it will be added. Unlike in this class'
     * setHeader() method, headers are passed as a key/value pair to this
     * method. For example, passing the values "Foo" and "Bar" as the first and
     * second arguments to this method yields the header "Foo: Bar". Note that
     * this method assumes there will be only one matching instance of the
     * given header and assumes it is done after finding the first one.
     *
     * @param string $key
     * @param string $value
     */
    public function replaceHeader($key, $value) {
        $headerCount = count($this->_extraHeaders);
        // We will not use case sensitivity to find any existing header
        $lcKey = strtolower($key);
        for ($i = 0; $i < $headerCount; $i++) {
            $lcHeader = strtolower($this->_extraHeaders[$i]);
            if (strpos($lcHeader, $lcKey . ':') === 0) {
                if ($value === null) {
                    array_splice($this->_extraHeaders, $i);
                }
                else {
                    $this->_extraHeaders[$i] = $key . ': ' . $value;
                }
                return;
            }
        }
        if ($value !== null) {
            $this->setHeader($key . ': ' . $value);
        }
    }
    
    /**
     * Sets a username and password for basic HTTP authentication.
     *
     * @param string $user
     * @param string $pass
     */
    public function setBasicAuthentication($user, $pass) {
        $this->_httpUser = $user;
        $this->_httpPass = $pass;
    }
    
    /**
     * Sets a validator against which to compare the response that results from
     * this request.
     *
     * @param array $validator
     */
    public function setResponseValidator(array $validator) {
        $this->_responseValidator = $validator;
    }
    
    /**
     * Sets the method of validation that the validator (if set) will enforce.
     * Setting the method explicitly to null disables any validation that would
     * otherwise take place.
     *
     * @param int $validationMethod
     */
    public function setResponseValidationMethod($validationMethod) {
        if ($validationMethod !== null &&
            $validationMethod !== self::VALIDATE_KEYS &&
            $validationMethod !== self::VALIDATE_PROTOTYPE)
        {
            throw new \InvalidArgumentException(
                "The validation method must be passed as one of this class' " .
                'VALIDATE_* constants.'
            );
        }
        $this->_responseValidationMethod = $validationMethod;
    }
    
    /**
     * If an argument is passed, sets this instance to automatically follow
     * redirects or not according to the argument's boolean evaluation. If no
     * argument is passed, returns a boolean value indicating whether or not
     * this instance will follow redirects automatically.
     *
     * @param boolean $autoRedirect = null
     * @return boolean
     */
    public function autoRedirect($autoRedirect = null) {
        if ($autoRedirect === null) {
            return $this->_autoRedirect;
        }
        $this->_autoRedirect = (bool)$autoRedirect;
    }
    
    /**
     * If an argument is passed, sets this instance to always place the
     * persistent parameters in the query string even when doing a POST, or
     * not, according to the argument's boolean evaluation. If no argument is
     * passed, returns a boolean value indicating whether or not this instance
     * has that characteristic.
     *
     * @param boolean $force = null
     * @return boolean
     */
    public function forcePersistentParametersToGet($force = null) {
        if ($force === null) {
            return $this->_forcePersistentArgsToGet;
        }
        $this->_forcePersistentArgsToGet = (bool)$force;
    }
    
    /**
     * If an argument is passed, sets this instance to expect a response with a
     * non-zero length, or not, depending on the argument's boolean
     * representation. If no argument is passed, returns a boolean value
     * indicating whether or not this instance has this expectation.
     *
     * @param boolean $expectLength = null
     * @return boolean
     */
    public function expectResponseLength($expectLength = null) {
        if ($expectLength === null) {
            return $this->_expectResponseLength;
        }
        $this->_expectResponseLength = (bool)$expectLength;
    }
    
    /**
     * Returns the request URL including all applicable parameters.
     *
     * @return URL
     */
    public function getURL() {
        $url = clone $this->_url;
        /* Merge in persistent parameters first so they may be selectively
        overridden by instance parameters. */
        if (static::$_persistentArgs && (
            $this->getVerb() == 'GET' || $this->_forcePersistentArgsToGet
        )) {
            $url->setQueryStringParam(static::$_persistentArgs);
        }
        if ($this->_args) {
            $url->setQueryStringParam($this->_args);
        }
        return $url;
    }
    
    /**
     * Returns the HTTP verb that will be used to make this request. If no verb
     * has been set explicitly, it will choose a sensible default based on the
     * other request attributes that have been set (POST if a payload has been
     * set, GET otherwise).
     *
     * @return string
     */
    public function getVerb() {
        if ($this->_verb) {
            return $this->_verb;
        }
        return $this->_payload === null ? 'GET' : 'POST';
    }
    
    /**
     * @return array, string
     */
    public function getPayload() {
        return $this->_payload;
    }
    
    /**
     * @return array
     */
    public function getHeaders() {
        return $this->_extraHeaders;
    }
    
    /**
     * @return string
     */
    public function getUser() {
        return $this->_httpUser;
    }
    
    /**
     * @return string
     */
    public function getPassword() {
        return $this->_httpPass;
    }
    
    /**
     * Return an array of this request's characteristics, suitable for passing
     * to curl_setopt_array().
     *
     * @return array
     */
    public function getCurlOptions() {
        $opts = array(
            CURLOPT_URL => (string)$this->getURL()
        );
        if ($this->_autoRedirect) {
            $opts[CURLOPT_FOLLOWLOCATION] = true;
            $opts[CURLOPT_MAXREDIRS] = 10;
        }
        $verb = $this->getVerb();
        $payload = $this->_payload;
        if (static::$_persistentArgs && $verb != 'GET' &&
            !$this->_forcePersistentArgsToGet)
        {
            if ($payload !== null && !is_array($payload)) {
                throw new \LogicException(
                    'When using string data as a payload, persistent ' .
                    'parameters must be sent in the query string.'
                );
            }
            $payload = $payload === null ? static::$_persistentArgs : array_merge(
                static::$_persistentArgs, $payload
            );
        }
        if ($payload !== null) {
            if ($verb == 'GET') {
                throw new \LogicException(
                    'A GET request may not contain a payload.'
                );
            }
            $opts[CURLOPT_POSTFIELDS] = $payload;
        }
        $opts[CURLOPT_CUSTOMREQUEST] = $verb;
        if ($this->_httpUser !== null) {
            $opts[CURLOPT_USERPWD] = $this->_httpUser . ':' . $this->_httpPass;
        }
        /* Instead of just referring to $this->_extraHeaders here, call the
        getter so that subclasses may override that method in order to produce
        persistent headers. */
        $extraHeaders = $this->getHeaders();
        if ($extraHeaders) {
            $opts[CURLOPT_HTTPHEADER] = $extraHeaders;
        }
        return $opts;
    }
    
    /**
     * @return array
     */
    public function getResponseValidator() {
        return $this->_responseValidator;
    }
    
    /**
     * @return int
     */
    public function getResponseValidationMethod() {
        return $this->_responseValidationMethod;
    }
    
    /**
     * Validates the API response against any requirements that have been set
     * in this instance.
     *
     @ @param string $rawResponse
     * @param array $parsedResponse = null
     */
    public function validateResponse(
        $rawResponse,
        array $parsedResponse = null
    ) {
        if ($parsedResponse === null) {
            $parsedResponse = array();
        }
        if ($this->_expectResponseLength && !strlen($rawResponse)) {
            $this->_handleValidationFailure();
        }
        if ($this->_responseValidator) {
            if ($this->_responseValidationMethod == self::VALIDATE_KEYS) {
                $diff = array_diff(
                    $this->_responseValidator, array_keys($parsedResponse)
                );
                if ($diff) {
                    $this->_handleValidationFailure($diff);
                }
            }
            elseif ($this->_responseValidationMethod == self::VALIDATE_PROTOTYPE)
            {
                $this->_compareResponseToPrototype(
                    $parsedResponse, $this->_responseValidator
                );
            }
        }
    }
}
?>