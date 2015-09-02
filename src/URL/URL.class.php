<?php
use DataStructures\SerializableFixedArray as FixedArray;
use DataStructures\SerializableQueue as Queue;

interface URLException {}
class URLRuntimeException extends LoggingExceptions\RuntimeException implements URLException {}
class URLInvalidArgumentException extends InvalidArgumentException implements URLException {}
class TLDException extends URLInvalidArgumentException {}
class URLLogicException extends LogicException implements URLException {}

class URL {
    const SUFFIX_LIST_CACHE_FILE = 'suffixlist_cache.txt';
    private static $_SETTINGS = array(
        // This is always stored in the system temporary directory
        'SUFFIX_LIST_CACHE_FILE' => 'suffixlist_cache.txt',
        'SUFFIX_LIST_URL' => 'https://publicsuffix.org/list/effective_tld_names.dat',
        'SUFFIX_LIST_REFRESH_INTERVAL' => 604800,
        /* If this is set true, this class will not attempt to retrieve the
        Public Suffix List data, and consequently the use of any setters or
        getters that deal with a component of the host will throw a
        TLDException. */
        'SUFFIX_LIST_DISABLE' => false,
        'PFX_CA_BUNDLE' => null
    );
    /* I'm omitting PFX_CA_BUNDLE from the setting tests for now, as cURL on
    Linux seems to automatically include a working certificate bundle. */
    private static $_SETTING_TESTS = array(
        'SUFFIX_LIST_CACHE_FILE' => 'string',
        'SUFFIX_LIST_URL' => 'string',
        'SUFFIX_LIST_REFRESH_INTERVAL' => 'integer',
        'SUFFIX_LIST_DISABLE' => 'boolean'
    );
    private static $_staticPropsReady = false;
    private static $_suffixList = array();
    private static $_hasIntl;
    /* This is used within $this->compare() if it's not possible to instantiate
    a string as a URL object. */
    private static $_stringComparisonCallback;
    /* This is used within $this->compareRootDomain() if it's not possible to
    instantiate the comparison URL as a URL object, in which case it extracts
    the host portion and attempts to use that as the basis for comparison. */
    private static $_hostComparisonCallback;
    private $_rawURL;
    private $_URL;
    private $_scheme = 'http';
    private $_host;
    private $_path;
    private $_subdomain;
    private $_domain;
    private $_TLD;
    private $_port;
    private $_queryString;
    private $_hashFragment;
    private $_qsArgSeparator = '&';
    private $_qsData = array();
    private $_hostIsIP = false;
    private $_parsedTLD = false;
    
    /**
     * If a URL is passed, attempts to set it. Otherwise, sets scheme to
     * default value (http) and waits for user to set the other components by
     * calling the appropriate methods.
     *
     * @param string $rawURL
     */
    public function __construct($rawURL = null) {
        if (!self::$_staticPropsReady) {
            self::_initStaticProperties();
        }
        if ($rawURL !== null) {
            $this->setURL($rawURL);
        }
    }
    
    public function __toString() {
        if (!$this->_host) {
            return '';
        }
        return $this->_URL;
    }
    
    private static function _initStaticProperties() {
        self::$_hasIntl = extension_loaded('intl');
        PFXUtils::validateSettings(
            self::$_SETTINGS, self::$_SETTING_TESTS
        );
        self::$_stringComparisonCallback = function(URL $url, $compURL) {
            return (string)$url === (string)$compURL;
        };
        self::$_hostComparisonCallback = function(
            URL $url,
            $compURL,
            $requireSubdomainMatch = false
        ) {
            $schemeDelimiterPos = strpos($compURL, '://');
            if ($schemeDelimiterPos === false) {
                return false;
            }
            $schemeDelimiterPos += 3;
            $slashPos = strpos($compURL, '/', $schemeDelimiterPos);
            if ($slashPos === false) {
                $compHost = substr($compURL, $schemeDelimiterPos);
            }
            else {
                $compHost = substr(
                    $compURL,
                    $schemeDelimiterPos,
                    $slashPos - $schemeDelimiterPos
                );
            }
            if ($requireSubdomainMatch) {
                return $url->getHost() === $compHost;
            }
            try {
                return $url->compareRootDomain(new URL($compHost));
            } catch (URLException $e) {
                return false;
            }
        };
        self::$_staticPropsReady = true;
    }

    /**
     * Loads the cached suffix list data or refreshes it if necessary. The
     * target data structure is an array of arrays indexed by the rightmost TLD
     * label, in which is another array of arrays indexed by the number of
     * labels in the rule, with the special index 0 indicating an exception.
     */
    private static function _loadSuffixList() {
        $refreshFile = true;
        $suffixListFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                        . SUFFIX_LIST_CACHE_FILE;
        $suffixListFileExists = file_exists($suffixListFile);
        if ($suffixListFileExists) {
            $stat = stat($suffixListFile);
            if (!$stat['size']) {
                // Don't consider the file as existing if it's there but empty
                $suffixListFileExists = false;
            }
            elseif ($stat['mtime'] > time() - SUFFIX_LIST_REFRESH_INTERVAL) {
                $refreshFile = false;
            }
        }
        try {
            if ($refreshFile) {
                $ch = curl_init();
                curl_setopt_array($ch, array(
                    CURLOPT_URL => SUFFIX_LIST_URL,
                    CURLOPT_FAILONERROR => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 60,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 10
                ));
                if (PFX_CA_BUNDLE) {
                    curl_setopt_array($ch, array(
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                        CURLOPT_CAINFO => PFX_CA_BUNDLE
                    ));
                }
                $listContents = curl_exec($ch);
                if (!$listContents) {
                    throw new URLRuntimeException(
                        'Failed to download latest Public Suffix List.'
                    );
                }
                $memStream = fopen('php://memory', 'wb');
                if (!$memStream) {
                    throw new URLRuntimeException(
                        'Unable to open memory stream.'
                    );
                }
                fwrite($memStream, $listContents);
                rewind($memStream);
                while ($line = fgets($memStream)) {
                    $line = trim($line);
                    /* The contents of the Public Suffix List represent
                    something a little more nuanced than just a list of what
                    does and doesn't constitute a valid TLD. As its name
                    implies, it's a list of suffixes on which anybody could
                    register a domain name. This includes actual TLDs, as well
                    as some private domains like blogspot.com, appspot.com,
                    etc. I THINK that for the purposes of identifying just the
                    TLDs, I need to stop reading the file once the ICANN
                    domains are done. */
                    if (strpos($line, 'END ICANN DOMAINS') !== false) {
                        break;
                    }
                    if (substr($line, 0, 2) == '//' || strlen($line) == 0) {
                        continue;
                    }
                    $ruleArray = explode('.', $line);
                    $ruleLength = count($ruleArray);
                    $topLabel = $ruleArray[$ruleLength - 1];
                    if (substr($line, 0, 1) == '!') {
                        $line = substr($line, 1);
                        $ruleIndex = 0;
                    }
                    else {
                        $ruleIndex = $ruleLength;
                    }
                    if (!isset(self::$_suffixList[$topLabel])) {
                        /* Since this collection will probably never need to
                        contain more than a handful of entries, I am using
                        a FixedArray for this. */
                        self::$_suffixList[$topLabel] = FixedArray::factory(1);
                    }
                    if ($ruleIndex > count(self::$_suffixList[$topLabel]) - 1)
                    {
                        self::$_suffixList[$topLabel]->setSize($ruleIndex + 1);
                    }
                    if (!self::$_suffixList[$topLabel][$ruleIndex]) {
                        /* I'm using a queue here because with a plain array,
                        I can't append to it directly due to the fact that
                        accessing a member of a fixed array returns the member
                        itself, not a reference to it, so I would need to pull
                        out the array, append, and reassign. Using an object
                        makes it return a reference no matter what. */
                        self::$_suffixList[$topLabel][$ruleIndex] = Queue::factory();
                    }
                    self::$_suffixList[$topLabel][$ruleIndex]->enqueue(
                        FixedArray::fromArray(explode('.', $line))
                    );
                }
                $bytesWritten = file_put_contents(
                    $suffixListFile, serialize(self::$_suffixList)
                );
                if ($bytesWritten === false) {
                    throw new URLRuntimeException(
                        'Failed to cache suffix list.'
                    );
                }
            }
        } catch (URLRuntimeException $e) {
            /* We couldn't retrieve the Public Suffix List. If we still have
            a cached file from last time, we can try to use it now; because
            the exception that we just caught is a LoggingException, somebody
            should get a notification about this condition (provided that the
            application registered a Logger instance to do this). If we don't
            have any cached data, we have to rethrow the exception. */
            if (!self::$_suffixList && !$suffixListFileExists) {
                throw $e;
            }
        }
        if (!self::$_suffixList) {
            $storedList = file_get_contents($suffixListFile);
            if (!$storedList) {
                throw new URLRuntimeException(
                    'Failed to read cached suffix list data.'
                );
            }
            self::$_suffixList = unserialize($storedList);
            if (!self::$_suffixList) {
                throw new URLRuntimeException(
                    'Failed to load suffix list from cache.'
                );
            }
        }
    }
    
    /**
     * Returns the length of the most appropriate TLD match for the given host
     * (in terms of label count, not string length).
     *
     * @param string $host
     * @return int
     */
    private static function _getMatchingTLDLength($host) {
        if (!self::$_suffixList) {
            self::_loadSuffixList();
        }
        $hostComponents = explode('.', $host);
        /* IDN support: the labels will be indexed as UTF-8, so in case we get
        an ASCII representation of the TLD, we will want to convert it to UTF-8
        (assuming the proper extension is available in this environment). */
        $labelCount = count($hostComponents);
        if (self::$_hasIntl) {
            for ($i = 0; $i < $labelCount; $i++) {
                if (substr($hostComponents[$i], 0, 4) == 'xn--') {
                    $hostComponents[$i] = idn_to_utf8($hostComponents[$i]);
                }
            }
        }
        $topLabel = $hostComponents[$labelCount - 1];
        if (!$topLabel || !isset(self::$_suffixList[$topLabel])) {
            throw new TLDException(
                'Invalid final host label "' . $topLabel . '" in host "' .
                $host . '".'
            );
        }
        /* Try exceptions first (if any). Logic is a little different here than
        for the non-exception rules, as we don't yet know the length of these
        rules, so we'll need to keep track of any matching ones and return the
        longest one. */
        if (isset(self::$_suffixList[$topLabel][0]) &&
            self::$_suffixList[$topLabel][0])
        {
            $matchingRule = null;
            $matchingRuleLength = 0;
            foreach (self::$_suffixList[$topLabel][0] as $rule) {
                $matched = true;
                $ruleLength = count($rule);
                /* Get the corresponding portion of the host, unless the host's
                length is lesser than that of the rule, in which case move on
                to the next rule. */
                if ($labelCount < $ruleLength) {
                    continue;
                }
                $offset = $labelCount - $ruleLength;
                $matchSlice = array_slice($hostComponents, $offset);
                /* We know the top level matches already, so subtract 2 instead
                of 1. */
                for ($i = $ruleLength - 2; $i >= 0; $i--) {
                    if ($rule[$i] != '*' &&
                        $rule[$i] != $matchSlice[$i])
                    {
                        $matched = false;
                        break;
                    }
                }
                if ($matched && $ruleLength > $matchingRuleLength) {
                    $matchingRule = $rule;
                    $matchingRuleLength = $ruleLength;
                }
            }
            if ($matchingRule) {
                /* The first element of the matching rule is actually the
                domain name in the case of a match on an exception, so subtract
                one when returning the length. */
                return $matchingRuleLength - 1;
            }
        }
        /* Now just go through from the longest rule (or the longest one with a
        length at most one lesser than the length of the host) to the shortest
        to try to find a match. */
        $startIndex = min(
            count(self::$_suffixList[$topLabel]), $labelCount - 1
        );
        for ($i = $startIndex; $i >= 1; $i--) {
            /* There may not actually be valid TLDs with this specific number
            of labels. */
            if (!isset(self::$_suffixList[$topLabel][$i])) {
                continue;
            }
            $offset = $labelCount - $i;
            $matchSlice = array_slice($hostComponents, $offset);
            foreach (self::$_suffixList[$topLabel][$i] as $rule) {
                $matched = true;
                /* $i is equal to the length of the rule we're trying. We don't
                just subtract 1 to take the zero-indexing into account, but
                rather 2, because we already know the last label in the slice
                we're testing matches the last label in the rule we're trying
                to match. */
                for ($j = $i - 2; $j >= 0; $j--) {
                    if ($rule[$j] != '*' && $rule[$j] != $matchSlice[$j]) {
                        $matched = false;
                        break;
                    }
                }
                if ($matched) {
                    return $i;
                }
            }
        }
        // If we make it here, the URL did not match anything
        throw new TLDException(
            'Host "' . $host . '" does not appear to contain a valid TLD.'
        );
    }
    
    /**
     * Builds a query string based on an associative array of data and a
     * separator string. This is like PHP's native http_build_query(), except
     * that function doesn't correctly handle parameters with no value.
     *
     * @param array $qsData
     * @param string $argSeparator
     * @return string
     */
    private static function _buildQueryString(array $qsData, $argSeparator) {
        $keys = array_keys($qsData);
        $keyCount = count($keys);
        $qs = '';
        for ($i = 0; $i < $keyCount; $i++) {
            $qs .= urlencode($keys[$i]);
            if (strlen($qsData[$keys[$i]])) {
                $qs .= '=' . urlencode($qsData[$keys[$i]]);
            }
            if ($i + 1 < $keyCount) {
                $qs .= $argSeparator;
            }
        }
        return $qs;
    }
    
    /**
     * Handles the boilerplate work of normalizing something that may be a
     * string to a URL object for the purposes of doing further comparison.
     * If a callback is passed as the third argument, it will be run if the
     * second argument is a string and a URLException is thrown when this
     * method attempts to initialize it as a URL object, and its return
     * value will be passed through (this callback should accept a URL object
     * as its first argument and a string as its second). If this exception is
     * thrown without a callback having been passed, this method returns false.
     * The only other circumstance under which this method will return a
     * non-null value is if one URL is empty and the other is not, in which
     * case it will return false. Any additional arguments passed to this
     * method after the callback will be passed to the callback in addition
     * to the URL and the string.
     *
     * @param URL $urlA
     * @param string, URL $urlB
     * @param callable $failureCallback = null
     * [@param mixed $failureCallbackArg...]
     * @return boolean
     */
    private static function _normalizeForComparison(
        URL $urlA,
        &$urlB,
        $failureCallback = null
    ) {
        if (!is_object($urlB)) {
            try {
                $urlB = new self($urlB);
            } catch (URLException $e) {
                if ($failureCallback) {
                    if (!is_callable($failureCallback)) {
                        throw new URLLogicException(
                            'A non-callable value was passed in a context ' .
                            'that expects a callable.'
                        );
                    }
                    $callbackArgs = array($urlA, $urlB);
                    $argCount = func_num_args();
                    for ($i = 3; $i < $argCount; $i++) {
                        $callbackArgs[] = func_get_arg($i);
                    }
                    return call_user_func_array(
                        $failureCallback, $callbackArgs
                    );
                }
                return false;
            }
        }
        if (!$urlA->_URL xor !$urlB->_URL) {
            return false;
        }
    }
    
    /**
     * Helper method for self::parseQueryString(). If the argument is an array,
     * iterates through it recursively decoding keys and values; otherwise
     * hex-decodes the value and returns it.
     *
     * @param mixed $val
     * @return mixed
     */
    private static function _decodeQueryStringValue($val) {
        if (is_array($val)) {
            $processed = array();
            foreach ($val as $key => $nestedVal) {
                $processed[$key] = self::_decodeQueryStringValue($nestedVal);
            }
            return $processed;
        }
        else {
            return pack('H*', $val);
        }
    }
    
    /**
     * Validates a host name, or a component thereof, to confirm that it
     * uses only the allowed characters.
     *
     * @param string $component
     * @param boolean $asDomain
     */
    private static function _validateHostComponent(
        $component,
        $asDomain = false
    ) {
        if (!strlen($component)) {
            return;
        }
        /* Host components should not contain any characters other than
        hyphens, letters, numbers, and periods. In addition, the first and last
        characters may not be hyphens or periods, and it should not contain two
        consecutive periods. In order to allow this validation to succeed for
        international domain names, we have to send them to ASCII first, if
        possible. */
        $testComponent = self::$_hasIntl ? idn_to_ascii($component) : $component;
        $len = strlen($testComponent);
        $chars = '-a-zA-Z0-9';
        if (!$asDomain) {
            $chars .= '.';
        }
        // $testComponent will be boolean false if idn_to_ascii() failed
        if ($testComponent === false ||
            preg_match('/[^' . $chars . ']/', $testComponent) ||
            $testComponent[0] == '-' || $testComponent[0] == '.' ||
            $testComponent[$len - 1] == '-' ||
            $testComponent[$len - 1] == '.' ||
            strpos($testComponent, '..') !== false)
        {
            throw new URLInvalidArgumentException(
                '"' . $component . '" is not valid in a host name.'
            );
        }
    }
    
    /**
     * Resets all internal properties.
     */
    private function _resetState() {
        $this->_rawURL = null;
        $this->_URL = null;
        $this->_scheme = null;
        $this->_host = null;
        $this->_path = null;
        $this->_subdomain = null;
        $this->_domain = null;
        $this->_TLD = null;
        $this->_port = null;
        $this->_queryString = null;
        $this->_hashFragment = null;
        $this->_qsArgSeparator = '&';
        $this->_qsData = array();
        $this->_hostIsIP = false;
        $this->_parsedTLD = false;
    }
    
    /**
     * Validates and sets the path.
     *
     * @param string $path
     */
    private function _setPath($path) {
        if (strpos($path, '#') !== false || strpos($path, '?') !== false) {
            throw new URLInvalidArgumentException(
                '"' . $path . '" is not a valid URL path.'
            );
        }
        $this->_path = $path;
    }
    
    /**
     * Validates and sets the scheme.
     *
     * @param string $scheme
     */
    private function _setScheme($scheme) {
        // Check that it is syntactically sound
        if (!preg_match('/^[a-zA-Z][-a-zA-Z0-9.]+$/', $scheme)) {
            throw new URLInvalidArgumentException(
                '"' . $scheme . '" is not a valid URL scheme.'
            );
        }
        $this->_scheme = $scheme;
    }
    
    /**
     * Validates and sets the host. The port, if present, is automatically
     * isolated and set in its own property.
     *
     * @param string $host
     */
    private function _setHost($host) {
        $port = null;
        if (strlen($host)) {
            // First remove the port, if present
            $cPos = strpos($host, ':');
            if ($cPos !== false) {
                $port = substr($host, $cPos + 1);
                $host = substr($host, 0, $cPos);
            }
            self::_validateHostComponent($host);
        }
        else {
            $host = null;
        }
        $this->_host = $host;
        $this->_setPort($port);
        if (filter_var($this->_host, FILTER_VALIDATE_IP)) {
            $this->_hostIsIP = true;
        }
        $this->_parsedTLD = false;
        $this->_subdomain = null;
        $this->_domain = null;
        $this->_TLD = null;
    }
    
    /**
     * Validates and sets the URL's port.
     *
     * @param int $port
     */
    private function _setPort($port) {
        if ($port !== null && 
            filter_var($port, FILTER_VALIDATE_INT) === false || $port < 0)
        {
            throw new URLInvalidArgumentException(
                '"' . $port . '" is not a valid port.'
            );
        }
        $this->_port = $port;
    }
    
    /**
     * Sets the query string.
     *
     * @param string $queryString
     */
    private function _setQueryString($queryString) {
        $this->_queryString = $queryString;
        $this->_resetQSData();
    }
    
    /**
     * Sets the hash fragment.
     *
     * @param string $hashFragment
     */
    private function _setHashFragment($hashFragment) {
        $this->_hashFragment = $hashFragment;
    }
    
    /**
     * Validates and sets the subdomain.
     *
     * @param string $subdomain
     */
    private function _setSubdomain($subdomain) {
        if (!strlen($subdomain)) {
            $subdomain = null;
        }
        self::_validateHostComponent($subdomain);
        /* I'm not allowing construction of a host from scratch component-by-
        component, as that opens up some complicated situations. */
        if (!$this->_host) {
            throw new URLLogicException(
                'Cannot set a subdomain before setting a host.'
            );
        }
        /* If no TLD has been set, but we have attempted to parse the TLD
        already, it means that the host we're working with doesn't have a valid
        TLD, and we have no basis to determine which part of it represents the
        subdomain. */
        if (!$this->_TLD) {
            $this->_parseTLD();
        }
        $this->_subdomain = $subdomain;
    }
    
    /**
     * Validates and sets the domain.
     *
     * @param string $domain
     */
    private function _setDomain($domain) {
        if (!strlen($domain)) {
            throw new URLInvalidArgumentException(
                'Cannot set the domain to an empty value.'
            );
        }
        self::_validateHostComponent($domain, true);
        if (!$this->_host) {
            throw new URLLogicException(
                'Cannot set a domain before setting a host.'
            );
        }
        if (!$this->_TLD) {
            $this->_parseTLD();
        }
        $this->_domain = $domain;
    }
    
    /**
     * Extracts and sets the scheme portion of the URL (i.e. whatever comes
     * before the '://').
     */
    private function _parseScheme() {
        /* We don't have to worry about the strpos() call returning false, as
        this method doesn't get called until $this->_URL has been tested
        against a regular expression that confirms the presence of this
        substring. */
        $this->_setScheme(substr($this->_URL, 0, strpos($this->_URL, '://')));
    }
    
    /**
     * Extracts and sets the host portion of the URL (i.e. what appears after
     * the '://' and before the next forward slash, if any), the path (i.e.
     * what appears beginning at the third forward slash, if any), and the port
     * (i.e. any values following a colon in what gets parsed as the host, if
     * any). Always sets path as '/' for root-level URLs.
     */
    private function _parseHostAndPath() {
        /* Identify the first character based on the position of the second
        slash. */
        $firstChar = strpos($this->_URL, '://') + 3;
        // Find the next slash character, if one exists
        $slashPos = strpos($this->_URL, '/', $firstChar);
        // Find the positions of the # and ? characters, if any
        $aPos = strpos($this->_URL, '#');
        $qPos = strpos($this->_URL, '?');
        if ($slashPos === false || ($aPos !== false && $aPos < $slashPos) ||
            ($qPos !== false && $qPos < $slashPos))
        {
            /* Path is root, but there could still be a query string or hash
            fragment. */
            $this->_setPath('/');
            if ($aPos === false && $qPos === false) {
                $this->_setHost(substr($this->_URL, $firstChar));
            }
            elseif ($aPos !== false && $qPos !== false) {
                $this->_setHost(substr(
                    $this->_URL, $firstChar, min($aPos, $qPos) - $firstChar
                ));
            }
            else {
                if ($aPos !== false) {
                    $len = $aPos;
                }
                else {
                    $len = $qPos;
                }
                $this->_setHost(substr(
                    $this->_URL, $firstChar, $len - $firstChar
                ));
            }
        }
        else {
            $this->_setHost(substr(
                $this->_URL, $firstChar, $slashPos - $firstChar
            ));
            if ($aPos === false && $qPos === false) {
                $this->_setPath(substr($this->_URL, $slashPos));
            }
            elseif ($aPos !== false && $qPos !== false) {
                $this->_setPath(substr(
                    $this->_URL, $slashPos, min($aPos, $qPos) - $slashPos
                ));
            }
            else {
                if ($aPos !== false) {
                    $len = $aPos;
                }
                else {
                    $len = $qPos;
                }
                $this->_setPath(substr(
                    $this->_URL, $slashPos, $len - $slashPos
                ));
            }
        }
    }
    
    /**
     * Extracts and sets the query string of the URL (if any). The value that
     * gets set does not include the question mark.
     */
    private function _parseQueryString() {
        $qPos = strpos($this->_URL, '?');
        if ($qPos === false) {
            return;
        }
        $queryString = substr($this->_URL, $qPos + 1);
        // Remove the hash fragment, if present
        $aPos = strpos($queryString, '#');
        if ($aPos !== false)  {
            $queryString = substr($queryString, 0, $aPos);
        }
        $this->_setQueryString($queryString);
    }
    
    /**
     * Extracts and sets the hash fragment portion of the URL (if any). Note
     * that the value that gets set DOES include the pound character.
     */
    private function _parseHashFragment() {
        $aPos = strpos($this->_URL, '#');
        if ($aPos !== false) {
            $this->_setHashFragment(substr($this->_URL, $aPos));
        }
    }
    
    /**
     * Determines the TLD, domain, and subdomain of this instance's $_host
     * property by finding the most specific match in the Public Suffix List.
     */
    private function _parseTLD() {
        if (SUFFIX_LIST_DISABLE || !$this->_URL) {
            return;
        }
        elseif (!$this->_host) {
            throw new URLLogicException(
                'Cannot parse host components until host is set.'
            );
        }
        if ($this->_parsedTLD) {
            if (!$this->_TLD) {
                /* Putting this here preserves an expectation on the part of
                the rest of this code that this method will raise an exception
                in the case of a bad TLD, even if we're not actively checking
                it this time around. */
                throw new TLDException(
                    'Host "' . $this->_host . '" does not contain a valid TLD.'
                );
            }
            // Otherwise, we don't have to reparse
            return;
        }
        /* Even though we're only beginning the parse process now, go ahead and
        set $this->_parsedTLD to true, because even if we don't manage to
        successfully parse it, there will be no point in a second attempt. */
        $this->_parsedTLD = true;
        $this->_setDomainFields(self::_getMatchingTLDLength($this->_host));
    }
    
    /**
     * Sets the $this->_TLD, $this->_domain, and $this->_subdomain properties
     * based on the length of the matched TLD rule.
     *
     * @param int $ruleLength
     */
    private function _setDomainFields($ruleLength) {
        $hostComponents = explode('.', $this->_host);
        $this->_TLD = implode(
            '.', array_splice($hostComponents, $ruleLength * -1)
        );
        $this->_setDomain(array_pop($hostComponents));
        if ($hostComponents) {
            $this->_setSubdomain(implode('.', $hostComponents));
        }
    }
    
    /**
     * Reset the value of $this->_host based on the current values of each
     * component property (subdomain, domain, and TLD). It should only ever be
     * necessary for $this->_updateURL() to call this method.
     */
    private function _updateHost() {
        /* If none of the three properties that make up the host have ever been
        set, then there is no need to do anything. The host remains whatever it
        already is. */
        if ($this->_subdomain === null &&
            $this->_domain === null &&
            $this->_TLD === null)
        {
            return;
        }
        $this->_host = '';
        if ($this->_subdomain) {
            $this->_host .= $this->_subdomain . '.';
        }
        $this->_host .= $this->_domain . '.' . $this->_TLD;
    }
    
    /**
     * Reset the value of $this->_URL based on the current values of each
     * component property. Every method that allows a user to set any of these
     * properties must call this method before returning.
     *
     * @param boolean $updateHost = true
     */
    private function _updateURL($updateHost = true) {
        if ($updateHost) {
            $this->_updateHost();
        }
        $this->_URL = $this->_scheme . '://' . $this->_host;
        if ($this->_port) {
            $this->_URL .= ':' . $this->_port;
        }
        if ($this->_path) {
            $this->_URL .= $this->_path;
        }
        if ($this->_queryString) {
            $this->_URL .= '?' . $this->_queryString;
        }
        if ($this->_hashFragment) {
            $this->_URL .= $this->_hashFragment;
        }
    }
    
    /**
     * Resets the contents of the array containing query string data.
     */
    private function _resetQSData() {
        if ($this->_queryString) {
            self::parseQueryString(
                $this->_queryString, $this->_qsArgSeparator, $this->_qsData
            );
        }
    }
    
    /**
     * If the argument passed is already a URL instance, it is returned
     * unchanged; otherwise, this method attempts to instantiate it as a URL
     * and return it.
     *
     * @param mixed $arg
     * @return URL
     */
    public static function cast($arg) {
        if (is_object($arg) && $arg instanceof self) {
            return $arg;
        }
        // This is a no-op on empty arguments
        if (strlen($arg)) {
            return new self($arg);
        }
    }
    
    /**
     * Removes the file that contains cached Public Suffix List data, if it
     * exists.
     */
    public static function clearSuffixListCache() {
        if (!self::$_staticPropsReady) {
            self::_initStaticProperties();
        }
        $suffixListFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                        . SUFFIX_LIST_CACHE_FILE;
        if (file_exists($suffixListFile) && !unlink($suffixListFile)) {
            throw new URLRuntimeException(
                'Failed to remove cached Public Suffix List data.'
            );
        }
    }
    
    /**
     * This addresses various deficiencies in PHP's native parse_str(), such as
     * not automatically storing multiple values for the same query string
     * parameter in an array and silently changing characters that are not
     * permitted in variable names to underscores. To run this on POST, GET, or
     * cookie data, pass file_get_contents('php://input'),
     * $_SERVER['QUERY_STRING'], or $_SERVER['HTTP_COOKIE'] respectively. If an
     * array is passed as the third argument, it will be treated as a
     * reference and populated with the decoded data. This array reference is
     * normally emptied prior to doing the decoding; this can be bypassed by
     * passing true as the fourth argument.
     *
     * @param string $queryString
     * @param string $argSeparator = '&'
     * @param array &$target = null
     * @param boolean $keepRaw = false
     * @return array
     */
    public static function parseQueryString(
        $queryString,
        $argSeparator = '&',
        array &$target = null,
        $keepRaw = false
    ) {
        if (!strlen($argSeparator)) {
            throw new URLInvalidArgumentException(
                'Argument separators must be at least one character in length.'
            );
        }
        if ($target === null) {
            $parsedQueryString = array();
        }
        else {
            if (!$keepRaw) {
                $target = array();
            }
            $parsedQueryString = &$target;
        }
        $components = explode($argSeparator, $queryString);
        $keys = array();
        $values = array();
        /* This contains the first index of every key we find, for reasons that
        will become clear shortly. */
        $keyIndexes = array();
        /* Despite the name this is a table containing each key, normalized
        without brackets, associated with a boolean value indicating whether we
        have observed more than one. */
        $multipleKeys = array();
        $componentCount = count($components);
        for ($i = 0; $i < $componentCount; $i++) {
            $component = $components[$i];
            $pos = strpos($component, '=');
            if ($pos === false) {
                $key = urldecode($component);
                $val = null;
            }
            else {
                $key = urldecode(substr($component, 0, $pos));
                $val = urldecode(substr($component, $pos + 1));
            }
            /* In order for parse_str() to do its thing the way I want it to,
            there are a couple of tweaks we need to make. First of all, as a
            workaround for the fact that PHP's native behavior will not place
            the values associated with multiple instances of the same key into
            an array unless the bracket syntax is present, we're going to add
            it where it's missing.
            
            We also need to worry about the fact that parse_str() will always
            turn certain characters (e.g. periods) into underscores in order to
            deal with the fact that those characters aren't allowed in variable
            names, which shouldn't be relevant in this case because we're
            dumping the result into an array instead of the current scope, but
            unfortunately we're stuck with this crappy design decision. I am
            using an approach based on Rok Kralj's solution (see
            http://stackoverflow.com/questions/68651/get-php-to-stop-replacing-characters-in-get-or-post-arrays)
            where the keys are converted to hex on the way in and back to
            binary on the way out. Only the portion of the key preceding the
            first pair of brackets needs to be touched in this way; PHP's
            native handling of the indexes is correct. */
            $pos = strpos($key, '[');
            // If there's no closing bracket, it's just a literal bracket
            $cPos = $pos === false ? false : strpos($key, ']', $pos);
            $val = bin2hex($val);
            if ($cPos === false) {
                $lookupKey = $key = bin2hex($key);
            }
            else {
                $lookupKey = bin2hex(substr($key, 0, $pos));
                $key = $lookupKey . substr($key, $pos);
            }
            if (isset($keyIndexes[$lookupKey])) {
                /* This means we have encountered this key before. If there is
                no explicit indexing on this key, we need to add it, or we'll
                erase any previous data for this key. */
                if (!$cPos) {
                    $key .= '[]';
                }
                /* If this is only the second time we are encountering this
                key, we need to know whether the first instance used explicit
                indexing. If not, we need to add it. A simple string comparison
                of the lookup key and the literal key will tell us this without
                the need to find a pair of brackets in the string. */
                if (!$multipleKeys[$lookupKey]) {
                    $multipleKeys[$lookupKey] = true;
                    if ($keys[$keyIndexes[$lookupKey]] == $lookupKey) {
                        $keys[$keyIndexes[$lookupKey]] .= '[]';
                    }
                }
            }
            else {
                $multipleKeys[$lookupKey] = false;
                $keyIndexes[$lookupKey] = $i;
            }
            $keys[] = $key;
            $values[] = $val;
        }
        $stageStr = '';
        /* No matter what the original separator was, we're going to use an
        ampersand here so that parse_str() understands it. Since any literal
        ampersands will have been hexed by this time, this is OK. */
        for ($i = 0; $i < $componentCount; $i++) {
            $stageStr .= $keys[$i] . '=' . $values[$i];
            if ($i + 1 < $componentCount) {
                $stageStr .= '&';
            }
        }
        parse_str($stageStr, $hexQueryString);
        foreach ($hexQueryString as $key => $val) {
            $parsedQueryString[pack('H*', $key)] = self::_decodeQueryStringValue($val);
        }
        return $parsedQueryString;
    }
    
    /**
     * Compares this instance to another URL and returns a boolean value
     * describing whether the two are fundamentally equal, which does not
     * necessarily mean that the strings are equivalent. The following list
     * summarizes the conditions under which this method will return true:
     * 1) The two URLs are string-equivalent
     * 2) The two URLs differ only in that one uses the subdomain 'www' and the
     *    other omits a subdomain entirely
     * 3) The two URLs differ only in their hash fragment components
     * 4) The two URLs differ only in their query strings, AND the comparison
     *    URL contains all the same query string keys and values as this
     *    instance (but it may contain other ones too, or have them in a
     *    different order)
     * 5) The two URLs differ only in that one is http and the other is https
     *
     * @param string, URL $compURL
     * @return boolean
     */
    public function compare($compURL) {
        if (self::_normalizeForComparison(
            $this, $compURL, self::$_stringComparisonCallback
        ) === false) {
            return false;
        }
        /* The only case in which we want to allow differing schemes is if one
        is http and the other is https. */
        if ($this->_scheme != $compURL->_scheme && !(
                ($this->_scheme == 'http' && $compURL->_scheme == 'https') ||
                ($this->_scheme == 'https' && $compURL->_scheme == 'http')
            ))
        {
            return false;
        }
        /* Start by stripping page fragments and converting to lower case,
        since those are two easy steps that could result in a match. These are
        destructive operations, though, so we need to clone the existing URLs
        first. */
        $thisClone = clone $this;
        $compClone = clone $compURL;
        $thisClone->stripHashFragment();
        $thisClone->setLowerCase();
        $compClone->stripHashFragment();
        $compClone->setLowerCase();
        if ($thisClone->_URL == $compClone->_URL) {
            return true;
        }
        /* The domain, TLD, and path must always match. However, we can't
        guarantee that we can get the TLD successfully, as the URL might not
        obey the Mozila public suffix list rules (for example, the host could
        be an IP address, or the TLD could just be an outlier that exists but
        isn't documented). We can compare the path directly; for the remainder,
        test whether the entire hosts match, or whether the only difference
        between them is that one begins with 'www.' and the other does not. */
        if ($thisClone->_path != $compClone->_path || (
            $thisClone->_host != $compClone->_host &&
            !(substr($thisClone->_host, 0, 4) == 'www.' &&
              substr($thisClone->_host, 4) == $compClone->_host) &&
            !(substr($compClone->_host, 0, 4) == 'www.' &&
              substr($compClone->_host, 4) == $thisClone->_host)
        ))
        {
            return false;
        }
        /* Now we know that the subdomains and paths match, so test the query
        strings. */
        $refQS = self::parseQueryString($thisClone->_queryString);
        $compQS = self::parseQueryString($compClone->_queryString);
        foreach ($refQS as $key => $val) {
            if (!array_key_exists($key, $compQS) || $val != $compQS[$key]) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * This is a convenience method that returns true if the comparison URL
     * shares the same root domain as the instance URL. The same result could
     * be achieved by comparing both the domain and the TLD, but this method
     * circumvents that potentially expensive behavior where possible by
     * dealing with the low-hanging fruit first (e.g. both hosts match exactly,
     * the host of one is a substring anchored at the end of the host of the
     * other, etc).
     *
     * @param string, URL $compURL
     * @param boolean $requireSubdomainMatch = false
     * @return boolean
     */
    public function compareRootDomain($compURL, $requireSubdomainMatch = false)
    {
        if (self::_normalizeForComparison(
            $this,
            $compURL,
            self::$_hostComparisonCallback,
            $requireSubdomainMatch
        ) === false) {
            return false;
        }
        // Low-hanging fruit first
        if ($this->_host === $compURL->_host) {
            return true;
        }
        /* If we need to match the subdomain, which implies the entire host, we
        have already failed by now. */
        if ($requireSubdomainMatch) {
            return false;
        }
        $thisHostLength = strlen($this->_host);
        $compHostLength = strlen($compURL->_host);
        /* If the two hosts have the same length, we will need to resort to TLD
        analysis to compare them. */
        if ($thisHostLength != $compHostLength) {
            list($shortHost, $longHost, $shortHostLength) =
                $thisHostLength < $compHostLength ?
                    array($this->_host, $compURL->_host, $thisHostLength) :
                    array($compURL->_host, $this->_host, $compHostLength);
            if ($shortHost == substr($longHost, $shortHostLength * -1)) {
                return true;
            }
        }
        /* Otherwise we have to resort to TLD analysis, but this could throw
        an exception. */
        try {
            return $this->getDomain() === $compURL->getDomain() &&
                   $this->getTLD() === $compURL->getTLD();
        } catch (URLException $e) {
            return false;
        }
    }
    
    /**
     * Takes a URL, does some basic cleaning and validation, and if successful,
     * parses it into its various pieces.
     */
    public function setURL($rawURL) {
        $this->_resetState();
        $url = trim($rawURL);
        if (!strlen($url)) {
            throw new URLInvalidArgumentException(
                'Cannot set an empty value as a URL.'
            );
        }
        $this->_rawURL = $rawURL;
        /* Matches a URI scheme followed by the delimiter that separates it
        from the rest of the URL. */
        $schemeRegex = '^[-a-zA-Z0-9.]+:(\/\/|\\\\)';
        /* Matches the characters that may be represented in URLs without being
        hex-encoded. */
        $allowedChars = '-\w\d:#%\/;$()~_?=\\\.&+';
        /* Verify that the raw URL starts with something that could be a URI
        scheme. */
        if (!preg_match('/' . $schemeRegex . '/', $url)) {
            $url = 'http://' . $url;
        }
        /* Automatically URL-encode, taking care not to URL-encode meaningful
        characters. */
        $url = preg_replace_callback(
            '/[^' . $allowedChars . ']/',
            function($matches) { return urlencode($matches[0]); },
            $url
        );
        /* This regular expression is fairly permissive, as it only validates
        that the URL doesn't contain any invalid characters, but not more
        specific rules (e.g. the host name may only contain a subset of these).
        The internal setters for the various properties will take care of
        enforcing those rules. */
        $regex = '/' . $schemeRegex . '[' . $allowedChars . ']+' . '$/';
        if (!preg_match($regex, $url)) {
            throw new URLInvalidArgumentException(
                '"' . $url . '" is not a valid URL.'
            );
        }
        $this->_URL = $url;
        $this->_parseScheme();
        $this->_parseHostAndPath();
        $this->_parseQueryString();
        $this->_parseHashFragment();
    }
    
    /**
     * Removes the hash fragment (if any) from a URL.
     */
    public function stripHashFragment() {
        if ($this->_hashFragment) {
            $aPos = strpos($this->_URL, '#');
            $this->_URL = substr($this->_URL, 0, $aPos);
            $this->_hashFragment = null;
        }
    }
    
    /**
     * Alias of $this->stripHashFragment().
     */
    public function stripAnchor() {
        $this->stripHashFragment();
    }
    
    /**
     * Changes the URL scheme.
     *
     * @param string $scheme
     */
    public function setScheme($scheme) {
        $this->_setScheme($scheme);
        $this->_updateURL();
    }
    
    /**
     * Changes the URL's host.
     *
     * @param string $host
     */
    public function setHost($host) {
        $this->_setHost($host);
        $this->_updateURL();
    }
    
    /**
     * Sets the URL's port.
     *
     * @param int $port
     */
    public function setPort($port) {
        $this->_setPort($port);
        $this->_updateURL();
    }
    
    /**
     * Changes the URL's subdomain.
     *
     * @param string $subdomain
     */
    public function setSubdomain($subdomain) {
        if (SUFFIX_LIST_DISABLE) {
            throw new TLDException(
                'Cannot manipulate individual host components while Public ' .
                'Suffix List usage is disabled.'
            );
        }
        $this->_setSubdomain($subdomain);
        $this->_updateURL();
    }
    
    /**
     * Changes the URL's domain.
     *
     * @param string $domain
     */
    public function setDomain($domain) {
        if (SUFFIX_LIST_DISABLE) {
            throw new TLDException(
                'Cannot manipulate individual host components while Public ' .
                'Suffix List usage is disabled.'
            );
        }
        $this->_setDomain($domain);
        $this->_updateURL();
    }
    
    /**
     * Changes the URL's TLD.
     *
     * @param string $tld
     */
    public function setTLD($tld) {
        if (SUFFIX_LIST_DISABLE) {
            throw new TLDException(
                'Cannot manipulate individual host components while Public ' .
                'Suffix List usage is disabled.'
            );
        }
        if (!$this->_host) {
            throw new URLLogicException(
                'Cannot set a TLD before setting a host.'
            );
        }
        $newHost = (
            $this->_subdomain === null ? '' : ($this->_subdomain . '.')
        ) . $this->_domain . '.' . $tld;
        $length = self::_getMatchingTLDLength($newHost);
        /* If no exception was thrown by now, all is well. Rather than just
        setting the TLD, we want to set the host to its new value, then call
        $this->_setDomainFields(), which will figure out any necessary changes
        to the domain and subdomain. */
        $this->_host = $newHost;
        $this->_setDomainFields($length);
        $this->_updateURL(false);
    }
    
    /**
     * Changes the URL's path.
     *
     * @param string $path
     */
    public function setPath($path) {
        /* We are using substr() instead of indexing to account for the
        possibility that $path is empty. */
        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }
        $this->_setPath($path);
        $this->_updateURL();
    }
    
    /**
     * Changes the base name of the URL's path without changing the remainder
     * of it. For example, with a URL instance containing the path
     * '/some-directory/some-file', passing the argument 'some-other-file'
     * would change the path to '/some-directory/some-other-file'. If the base
     * name of the existing path terminates in a slash, and the second argument
     * is false, the portion between the last two slashes in the URL will be
     * modified and the trailing slash preserved.
     *
     * @param string $baseName
     * @param boolean $appendIfTrailingSlash = false
     */
    public function setPathBaseName($baseName, $appendIfTrailingSlash = true)
    {
        // We don't want any leading slashes in the submitted value
        $baseName = ltrim($baseName, '/');
        $slashPos = strrpos($this->_path, '/');
        $trailingSlash = false;
        if ($slashPos == strlen($this->_path) - 1 && !$appendIfTrailingSlash) {
            $slashPos = strrpos($this->_path, '/', -2);
            $trailingSlash = true;
        }
        $path = substr($this->_path, 0, $slashPos + 1) . $baseName;
        if ($trailingSlash && substr($path, -1) != '/') {
            $path .= '/';
        }
        $this->_setPath($path);
        $this->_updateURL();
    }
    
    /**
     * Changes the URL's hash fragment.
     *
     * @param string $hashFragment
     */
    public function setHashFragment($hashFragment) {
        if (substr($hashFragment, 0, 1) != '#') {
            $hashFragment = '#' . $hashFragment;
        }
        $this->_setHashFragment($hashFragment);
        $this->_updateURL();
    }
    
    /**
     * Changes the URL's query string. Accepts either a pre-formatted query
     * string (with or without leading question mark) or an associative array
     * of keys to values which will be passed to self::_buildQueryString().
     *
     * @param string, array $queryData
     */
    public function setQueryString($queryData) {
        if (is_array($queryData)) {
            $queryString = self::_buildQueryString(
                $queryData, $this->_qsArgSeparator
            );
        }
        else {
            if (substr($queryData, 0, 1) == '?') {
                $queryString = substr($queryData, 1);
            }
            else {
                $queryString = $queryData;
            }
        }
        $this->_setQueryString($queryString);
        $this->_updateURL();
    }
    
    /**
     * Updates or sets one or more parameters in the query string, while
     * leaving any other parameters that may have existed in the string
     * unaffected. This method may be called either with a key in the first
     * argument position and a value in the second, or with an associative
     * array of keys to values in the first argument.
     *
     * @param string, array $arg
     * @param string $val = null
     */
    public function setQueryStringParam($arg, $val = null) {
        if ($val !== null && !is_scalar($val)) {
            throw new URLInvalidArgumentException(
                'The second argument to this method must be a scalar value.'
            );
        }
        if (is_scalar($arg)) {
            $qsParams = array($arg => $val);
        }
        elseif (is_array($arg)) {
            if ($val !== null) {
                throw new URLInvalidArgumentException(
                    'If the first argument to this method is an array, the ' .
                    'second argument should not be passed.'
                );
            }
            $qsParams = $arg;
        }
        else {
            throw new URLInvalidArgumentException(
                'This method expects either an array or a scalar value as ' .
                'its first argument.'
            );
        }
        foreach ($qsParams as $key => $val) {
            $this->_qsData[$key] = $val;
        }
        $this->_setQueryString(self::_buildQueryString(
            $this->_qsData, $this->_qsArgSeparator
        ));
        $this->_updateURL();
    }

    /**
     * The opposite of URL::setQueryStringParam(). If the string passed is a
     * key in the query string, it is removed, and the URL is reset to reflect
     * the change. If the unset key was the only key in the query string, the
     * query string is removed from the URL entirely.
     *
     * @param string $key
     */
    public function unsetQueryStringParam($key) {
        if (array_key_exists($key, $this->_qsData)) {
            unset($this->_qsData[$key]);
        }
        $this->_setQueryString(self::_buildQueryString(
            $this->_qsData, $this->_qsArgSeparator
        ));
        $this->_updateURL();
    }
    
    /**
     * Changes the default argument separator for query strings.
     *
     * @param string $sep
     */
    public function setQSArgSeparator($sep) {
        if (!strlen($sep)) {
            throw new URLInvalidArgumentException(
                'The argument separator for query strings must be at least ' .
                'one character in length.'
            );
        }
        $this->_qsArgSeparator = $sep;
        if ($this->_queryString) {
            $this->_resetQSData();
            $this->_updateURL();
        }
    }
    
    /**
     * Changes all initialized components of this URL to lower case.
     */
    public function setLowerCase() {
        // Need to parse the TLD if it hasn't been done
        if (!$this->_parsedTLD) {
            try {
                $this->_parseTLD();
            } catch (URLException $e) {
                /* This means that we couldn't parse the TLD, which is okay,
                but it does mean we have to do some stuff different later on.
                */
            }
        }
        $mutableComponents = array(
            &$this->_scheme,
            &$this->_path,
            &$this->_queryString,
            &$this->_hashFragment
        );
        if ($this->_TLD) {
            $mutableComponents[] = &$this->_subdomain;
            $mutableComponents[] = &$this->_domain;
            $mutableComponents[] = &$this->_TLD;
        }
        else {
            $mutableComponents[] = &$this->_host;
        }
        foreach ($mutableComponents as &$component) {
            if ($component !== null) {
                $component = strtolower($component);
            }
        }
        $this->_resetQSData();
        $this->_updateURL();
    }
    
    public function getScheme() {
        return $this->_scheme;
    }
    
    public function getHost() {
        return $this->_host;
    }
    
    public function getPort() {
        return $this->_port;
    }
    
    public function getPath() {
        return $this->_path;
    }

    /**
     * Convenience method to provide access to the entire URL path, including
     * query string and hash fragment, if present.
     *
     * @return string
     */
    public function getFullPath() {
        return $this->_path . (
            strlen($this->_queryString) ? ('?' . $this->_queryString) : ''
        ) . (strlen($this->_hashFragment) ? $this->_hashFragment : '');
    }
    
    /**
     * Returns the portion of the URL path following the final slash (not
     * counting trailing slashes).
     *
     * @return string
     */
    public function getPathBaseName() {
        $slashPos = strrpos($this->_path, '/');
        $pathLength = strlen($this->_path);
        if ($slashPos == $pathLength - 1 && $pathLength > 1) {
            // Account for there being a trailing slash on a non-root URL
            $slashPos = strrpos($this->_path, '/', -2);
        }
        return substr($this->_path, $slashPos + 1);
    }

    /**
     * Convenience method to provide access to the host plus the port, if
     * present.
     *
     * @return string
     */
    public function getFullHost() {
        return $this->_host . (strlen($this->_port) ? ':' . $this->_port : '');
    }
    
    /**
     * @return string
     */
    public function getRawURL() {
        return $this->_rawURL;
    }
    
    /**
     * @return string
     */
    public function getURL() {
        return (string)$this;
    }
    
    /**
     * Returns the query string as a string.
     *
     * @return string
     */ 
    public function getQueryString() {
        return $this->_queryString;
    }

    /**
     * Returns the query string as an associative array.
     *
     * @return array
     */
    public function getQueryStringData() {
        return $this->_qsData;
    }

    /**
     * Gets the value of a named query string parameter.
     *
     * @param string $key
     * @return string
     */
    public function getQueryStringParam($key) {
        if (array_key_exists($key, $this->_qsData)) {
            return $this->_qsData[$key];
        }
        throw new URLInvalidArgumentException(
            'Unrecognized query string parameter "' . $key . '".'
        );
    }
    
    /**
     * @return string
     */
    public function getHashFragment() {
        return $this->_hashFragment;
    }

    /**
     * @return string
     */
    public function getTLD() {
        if ($this->_hostIsIP) {
            return;
        }
        if (SUFFIX_LIST_DISABLE) {
            throw new TLDException(
                'Cannot manipulate individual host components while Public ' .
                'Suffix List usage is disabled.'
            );
        }
        // Need to parse the TLD if it hasn't been done
        if (!$this->_TLD) {
            $this->_parseTLD();
        }
        return $this->_TLD;
    }

    /**
     * @return string
     */
    public function getDomain() {
        if ($this->_hostIsIP) {
            return;
        }
        if (SUFFIX_LIST_DISABLE) {
            throw new TLDException(
                'Cannot manipulate individual host components while Public ' .
                'Suffix List usage is disabled.'
            );
        }
        // Need to parse the TLD if it hasn't been done
        if (!$this->_TLD) {
            $this->_parseTLD();
        }
        return $this->_domain;
    }
    
    /**
     * @return string
     */
    public function getSubdomain() {
        if ($this->_hostIsIP) {
            return;
        }
        if (SUFFIX_LIST_DISABLE) {
            throw new TLDException(
                'Cannot manipulate individual host components while Public ' .
                'Suffix List usage is disabled.'
            );
        }
        // Need to parse the TLD if it hasn't been done
        if (!$this->_TLD) {
            $this->_parseTLD();
        }
        return $this->_subdomain;
    }
    
    /**
     * @return boolean
     */
    public function hostIsIP() {
        return $this->_hostIsIP;
    }
    
    /**
     * @return string
     */
    public function getQSArgSeparator() {
        return $this->_qsArgSeparator;
    }
}
?>
