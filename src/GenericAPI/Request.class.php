<?php
namespace GenericAPI;

class Request {
    protected static $_baseURL;
    protected static $_persistentArgs = array();
    protected $_autoRedirect = true;
    protected $_forcePersistentArgsToGet = false;
    protected $_url;
    protected $_args;
    /* It is possible for there to be a distinction between arguments that
    belong in the query string of a URL to which to make a POST request and the
    POST payload of that request. */
    protected $_postArgs;
    protected $_verb = 'GET';
    protected $_extraHeaders = array();
    protected $_httpUser;
    protected $_httpPass;
    
    /**
     * Constructs a new request. If the first argument is a URL instance, it
     * will override any base URL that may be set in the class; otherwise it
     * will be appended to the base URL when constructing the full request URL.
     * Any parameters provided to the constructor in the second argument are
     * automatically treated as GET parameters, even if the instance's verb is
     * set otherwise.
     *
     * @param string, URL $url
     * @param array $args = null
     */
    public function __construct($url, array $args = null) {
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
        if ($this->_postArgs && $verb != 'POST') {
            throw new \LogicException(
                'Cannot set the verb on a request containing POST data to ' .
                'anything other than "POST".'
            );
        }
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
     * Sets the request's POST parameters. Calling this method implicitly sets
     * the HTTP verb to POST.
     *
     * @param array, string $args
     */
    public function setPostParameters($args) {
        $this->setVerb('POST');
        if (!is_array($args) && !is_string($args)) {
            throw new \InvalidArgumentException(
                'POST data must be passed either as an array or as a string.'
            );
        }
        $this->_postArgs = $args;
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
     * represented by the arguments. If a matching header does not yet exist in
     * this instance, it will be added. Unlike in this class' setHeader()
     * method, headers are passed as a key/value pair to this method. For
     * example, passing the values "Foo" and "Bar" as the first and second
     * arguments to this method yields the header "Foo: Bar". Note that this
     * method assumes there will be only one matching instance of the given
     * header and assumes it is done after finding the first one.
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
                $this->_extraHeaders[$i] = $key . ': ' . $value;
                return;
            }
        }
        $this->setHeader($key . ': ' . $value);
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
     * If an argument is passed, sets this instance to automatically follow
     * redirects or not according to the argument's boolean evaluation. If no
     * argument is passed, returns a boolean value indicating whether or not
     * this instance will follow redirects automatically.
     *
     * @param boolean $autoRedirect = null
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
     */
    public function forcePersistentParametersToGet($force = null) {
        if ($force === null) {
            return $this->_forcePersistentArgsToGet;
        }
        $this->_forcePersistentArgsToGet = (bool)$force;
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
            $this->_verb != 'POST' || $this->_forcePersistentArgsToGet
        )) {
            $url->setQueryStringParam(static::$_persistentArgs);
        }
        if ($this->_args) {
            $url->setQueryStringParam($this->_args);
        }
        return $url;
    }
    
    /**
     * @return string
     */
    public function getVerb() {
        return $this->_verb;
    }
    
    /**
     * @return array, string
     */
    public function getPostParameters() {
        $args = $this->_postArgs;
        if ($args === null) {
            $args = array();
        }
        if ($this->_verb == 'POST' && static::$_persistentArgs &&
            !$this->_forcePersistentArgsToGet)
        {
            if (!is_array($args)) {
                throw new \LogicException(
                    'When POSTing string data, persistent parameters must ' .
                    'be sent in the query string.'
                );
            }
            $args = array_merge(static::$_persistentArgs, $args);
        }
        return $args;
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
        if ($this->_verb == 'POST') {
            $opts[CURLOPT_POST] = true;
            $postArgs = $this->getPostParameters();
            if ($postArgs) {
                $opts[CURLOPT_POSTFIELDS] = $postArgs;
            }
        }
        elseif ($this->_verb != 'GET') {
            $opts[CURLOPT_CUSTOMREQUEST] = $this->_verb;
        }
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
}
?>