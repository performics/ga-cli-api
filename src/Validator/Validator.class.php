<?php
class Validator {
    /* This class houses a series of utility methods that can be used not only
    to validate data against certain constraints, but also to filter it. */
    
    // Requires that an argument pass the FILTER_VALIDATE_INT rules
    const ASSERT_INT = 1;
    // Requires that a numeric argument be positive
    const ASSERT_POSITIVE = 2;
    // Requires a strict type match (i.e. is_string() is true for an argument
    // passed to Validator::string())
    const ASSERT_TYPE_MATCH = 4;
    // Prevents null values from passing validation in methods that test
    // strings (for methods that test numbers, null will only be permitted if
    // ASSERT_ALLOW_NULL is present)
    const ASSERT_NOT_NULL = 8;
    // Allows null values to be passed to numeric validation methods without
    // causing a failure
    const ASSERT_ALLOW_NULL = 16;
    // Prevents comma-delimited lists of email addresses from passing
    // validation
    const ASSERT_SINGLE_EMAIL = 32;
    // Requires that the value be interpretable as true; e.g. strings have a
    // length, numbers are not zero (note that for numbers, ASSERT_ALLOW_NULL
    // has precedence over this assertion)
    const ASSERT_TRUTH = 64;
    // Asks the validation method to turn empty strings into nulls
    const FILTER_TO_NULL = 128;
    // Asks the validation method to trim whitespace from strings
    const FILTER_TRIM = 256;
    // Asks the validation method to prepend 'http://' to URLs missing a scheme
    const FILTER_ADD_SCHEME = 512;
    // Filters to lower case
    const FILTER_LOWERCASE = 1024;
    // Filters to upper case
    const FILTER_UPPERCASE = 2048;
    // FILTER_TO_NULL | FILTER_TRIM
    const FILTER_DEFAULT = 384;
    // FILTER_ADD_SCHEME | FILTER_TO_NULL | FILTER_TRIM
    const FILTER_DEFAULT_URL = 896;
    // ASSERT_INT | ASSERT_POSITIVE | ASSERT_TRUTH
    const ASSERT_INT_DEFAULT = 67;
    private $_throwExceptions = true;
    private $_exceptionType = 'InvalidArgumentException';
    private $_enumValues;
    
    /**
     * Instantiates an object, and if a namespace is passed, automatically sets
     * the exception type to that namespace's InvalidArgumentException, if it
     * exists.
     *
     * @param string $namespace = null
     */
    public function __construct($namespace = null) {
        if ($namespace) {
            $this->setExceptionType($namespace . '\InvalidArgumentException');
        }
    }
    
    /**
     * Builds and returns a generic error message.
     *
     * @param mixed $input
     * @param string $traceMethod
     * @param string $failedAssertion
     */
    private static function _buildErrorMessage(
        $input,
        $traceMethod,
        $failedAssertion
    ) {
        $message = 'Data validation failed in ' . $traceMethod . '()';
        if (is_scalar($input)) {
            $message .= ' for input "' . $input . '"';
        }
        if ($failedAssertion) {
            $message .= ' (failed assertion ' . $failedAssertion . ')';
        }
        $message .= '.';
        return $message;
    }
     
    /**
     * Throws an exception if applicable, otherwise returns false.
     *
     * @param string $message
     * @param string $traceMethod
     * @param string $failedAssertion = null
     * @param mixed $input = null
     * @param Exception $e = null
     * @return boolean
     */
    private function _throwError(
        $message,
        $traceMethod,
        $failedAssertion = null,
        $input = null,
        \Exception $e = null
    ) {
        if ($this->_throwExceptions) {
            if ($message === null) {
                $message = self::_buildErrorMessage(
                    $input, $traceMethod, $failedAssertion
                );
            }
            throw new $this->_exceptionType($message, null, $e);
        }
        return false;
    }
    
    /**
     * Directs all of this instance's methods to throw exceptions upon
     * validation failure.
     */
    public function enableExceptions() {
        $this->_throwExceptions = true;
    }
    
    /**
     * Directs all of this instance's methods to return false upon validation
     * failure.
     */
    public function disableExceptions() {
        $this->_throwExceptions = false;
    }
    
    /**
     * Registers the type of exceptions that this instance's methods should
     * throw.
     *
     * @param string $exceptionType
     */
    public function setExceptionType($exceptionType) {
        if (!class_exists($exceptionType)) {
            throw new InvalidArgumentException(
                '"' . $exceptionType . '" is not a recognized PHP class.'
            );
        }
        if (!is_subclass_of($exceptionType, 'Exception')) {
            throw new InvalidArgumentException(
                $exceptionType . ' is not an Exception subclass.'
            );
        }
        $this->_exceptionType = $exceptionType;
    }
    
    /**
     * Declares an enumeration of values to be used by $this->enum().
     *
     * @param array $values
     */
    public function setEnumValues(array $values) {
        $this->_enumValues = array();
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                throw new $this->_exceptionType(
                    'Enumerations may only include scalar values.'
                );
            }
            /* The idea is to maintain a hash table for easy lookup, but also
            to maintain the raw value so that we can easily do further type
            assertions if necessary. Since array keys are typecast as strings,
            certain distinct enumerable values (e.g. false, 0, "0") will
            overwrite each other if we just do it the simple way, so we have to
            watch for collisions and initialize arrays if necessary. */
            if (isset($this->_enumValues[$value])) {
                if (is_array($this->_enumValues[$value])) {
                    if (!in_array($value, $this->_enumValues, true)) {
                        $this->_enumValues[$value][] = $value;
                    }
                }
                elseif ($value !== $this->_enumValues[$value]) {
                    $this->_enumValues[$value] = array(
                        $this->_enumValues[$value],
                        $value
                    );
                }
            }
            else {
                $this->_enumValues[$value] = $value;
            }
        }
    }
    
    /**
     * @return array
     */
    public function getEnumValues() {
        return $this->_enumValues;
    }
    
    /**
     * Validate and possibly clean generic string data.
     *
     * @param string $str
     * @param string $failureMessage = null
     * @param int $options = self::FILTER_DEFAULT
     * @param int $maxLength = null
     * @return string, boolean
     */
    public function string(
        $str,
        $failureMessage = null,
        $options = self::FILTER_DEFAULT,
        $maxLength = null
    ) {
        if ($options & self::ASSERT_TYPE_MATCH && !is_string($str)) {
            return $this->_throwError(
                $failureMessage, __METHOD__, 'ASSERT_TYPE_MATCH', $str
            );
        }
        if ($str === null) {
            if ($options & self::ASSERT_NOT_NULL) {
                return $this->_throwError(
                    $failureMessage, __METHOD__, 'ASSERT_NOT_NULL', $str
                );
            }
        }
        elseif (!is_scalar($str)) {
            /* Not passing the input to $this->_throwError() because it can't
            necessarily be represented as a string. */
            return $this->_throwError($failureMessage, __METHOD__, null);
        }
        $str = (string)$str;
        if ($options & self::FILTER_TRIM) {
            $str = trim($str);
        }
        $length = strlen($str);
        if (!$length) {
            if ($options & self::ASSERT_TRUTH) {
                return $this->_throwError(
                    $failureMessage, __METHOD__, 'ASSERT_TRUTH', $str
                );
            }
            if ($options & self::FILTER_TO_NULL) {
                $str = null;
            }
        }
        elseif ($maxLength !== null) {
            $maxLength = $this->number(
                $maxLength,
                'String lengths must be positive integers.',
                self::ASSERT_INT | self::ASSERT_POSITIVE
            );
            if ($length > $maxLength) {
                return $this->_throwError(
                    $failureMessage, __METHOD__, null, $str
                );
            }
        }
        if ($str !== null) {
            if ($options & self::FILTER_LOWERCASE) {
                $str = strtolower($str);
            }
            elseif ($options & self::FILTER_UPPERCASE) {
                $str = strtoupper($str);
            }
        }
        return $str;
    }
    
    /**
     * Validate and possibly typecast generic numeric data.
     *
     * @param int, float, string $num
     * @param string $failureMessage = null
     * @param int $options = 0
     * @param int $rangeMin = null
     * @param int $rangeMax = null
     * @return number, boolean
     */
    public function number(
        $num,
        $failureMessage = null,
        $options = 0,
        $rangeMin = null,
        $rangeMax = null
    ) {
        // This is the only circumstance under which the empty string is OK
        if ($options & self::FILTER_TO_NULL && $num === '') {
            $num = null;
        }
        if ($options & self::ASSERT_ALLOW_NULL && $num === null) {
            return null;
        }
        if ($options & self::ASSERT_TRUTH && !$num) {
            return $this->_throwError(
                $failureMessage, __METHOD__, 'ASSERT_TRUTH', $num
            );
        }
        if ($options & self::ASSERT_TYPE_MATCH) {
            $isInt = is_int($num);
            $isFloat = is_float($num);
            if (!$isInt && !$isFloat) {
                return $this->_throwError(
                    $failureMessage, __METHOD__, 'ASSERT_TYPE_MATCH', $num
                );
            }
            elseif ($options & self::ASSERT_INT && $isFloat) {
                return $this->_throwError(
                    $failureMessage, __METHOD__, 'ASSERT_INT', $num
                );
            }
        }
        else {
            if (!is_numeric($num)) {
                return $this->_throwError(
                    $failureMessage, __METHOD__, null, $num
                );
            }
            if (filter_var($num, FILTER_VALIDATE_INT) === false) {
                if ($options & self::ASSERT_INT) {
                    return $this->_throwError(
                        $failureMessage, __METHOD__, 'ASSERT_INT', $num
                    );
                }
                $num = (float)$num;
            }
            else {
                $num = (int)$num;
            }
        }
        if ($options & self::ASSERT_POSITIVE && $num < 0) {
            return $this->_throwError(
                $failureMessage, __METHOD__, 'ASSERT_POSITIVE', $num
            );
        }
        if ($rangeMin !== null) {
            $rangeMin = $this->number(
                $rangeMin, 'Range minimums must be numbers.'
            );
            if ($num < $rangeMin) {
                return $this->_throwError(
                    $failureMessage, __METHOD__, null, $num
                );
            }
        }
        if ($rangeMax !== null) {
            $rangeMax = $this->number(
                $rangeMax, 'Range maximums must be numbers.'
            );
            if ($num > $rangeMax) {
                return $this->_throwError(
                    $failureMessage, __METHOD__, null, $num
                );
            }
        }
        return $num;
    }
    
    /**
     * Validate and possibly clean a phone number. At the moment, this only
     * works for North American-style numbers, with the country code excluded.
     * The fourth argument should be a valid sprintf format string that will be
     * used to format the number that is returned (note that this allows the
     * user to completely mangle the phone number if he/she so chooses).
     *
     * @param string $num
     * @param string $failureMessage = null
     * @param int $options = self::FILTER_DEFAULT
     * @param string $style = '%s %s %s'
     * @return string, boolean
     */
    public function phone(
        $num,
        $failureMessage = null,
        $options = self::FILTER_DEFAULT,
        $style = '%s %s %s'
    ) {
        $num = $this->string($num, $failureMessage, $options);
        if ($num === false || $num === null) {
            return $num;
        }
        // Remove any non-numeric characters
        $num = preg_replace('/[^0-9]/', '', $num);
        // Verify proper length
        if (strlen($num) != 10) {
            return $this->_throwError($failureMessage, __METHOD__, null, $num);
        }
        return sprintf(
            $style, substr($num, 0, 3), substr($num, 3, 3), substr($num, 6)
        );
    }
    
    /**
     * Validate and possibly clean a URL.
     *
     * @param string $url
     * @param string $failureMessage = null
     * @param int $options = self::FILTER_DEFAULT_URL
     * @param int $maxLength = null
     * @return string, boolean
     */
    public function URL(
        $url,
        $failureMessage = null,
        $options = self::FILTER_DEFAULT_URL,
        $maxLength = null
    ) {
        $url = $this->string($url, $failureMessage, $options, $maxLength);
        if ($url === false || $url === null) {
            return $url;
        }
        try {
            $cleanURL = (string)new URL($url);
            if ($options & self::FILTER_ADD_SCHEME) {
                /* If we want to add a scheme but NOT trim whitespace, we have
                to go through some acrobatics. */
                if ($options & self::FILTER_TRIM || $url == $cleanURL) {
                    return $cleanURL;
                }
                $matches = array();
                preg_match('/^(\s*)\S+(\s*)$/', $url, $matches);
                return $matches[1] . $cleanURL . $matches[2];
            }
            return $url;
        } catch (URLException $e) {
            return $this->_throwError(
                $failureMessage, __METHOD__, null, $url, $e
            );
        }
    }
    
    /**
     * Validate and possibly clean an email address.
     *
     * @param string $email
     * @param string $failureMessage = null
     * @param int $options = self::FILTER_DEFAULT
     * @param int $maxLength = null
     * @return string, boolean
     */
    public function email(
        $email,
        $failureMessage = null,
        $options = self::FILTER_DEFAULT,
        $maxLength = null
    ) {
        $email = $this->string($email, $failureMessage, $options, $maxLength);
        if ($email === false || $email === null) {
            return $email;
        }
        if ($options & self::ASSERT_SINGLE_EMAIL) {
            $emails = array($email);
        }
        else {
            $emails = explode(',', $email);
        }
        foreach ($emails as &$email) {
            /* Test a separate copy of the string from what gets returned so
            that we can preserve leading/trailing whitespace if necessary. */
            $testableEmail = trim($email);
            if ($options & self::FILTER_TRIM) {
                $email = $testableEmail;
            }
            if (!filter_var($testableEmail, FILTER_VALIDATE_EMAIL)) {
                return $this->_throwError(
                    $failureMessage, __METHOD__, null, $email
                );
            }
        }
        return implode(',', $emails);
    }
    
    /**
     * Validate a value against an enumerated list set up in a previous call.
     *
     * @param mixed $value
     * @param string $failureMessage = null
     * @param int $options = 0
     * @return mixed
     */
    public function enum(
        $value,
        $failureMessage = null,
        $options = 0
    ) {
        if ($this->_enumValues === null) {
            throw new LogicException(
                'No enumerated list has been set up yet.'
            );
        }
        if ($options & self::ASSERT_ALLOW_NULL && $value === null) {
            return null;
        }
        /* Most of the assertions and filters don't make sense for this test,
        but string trimming could. */
        if ($options & self::FILTER_TRIM && is_string($value)) {
            $value = trim($value);
        }
        if (is_scalar($value) && isset($this->_enumValues[$value])) {
            // If we don't care about type matching, we should be good already
            if (!($options & self::ASSERT_TYPE_MATCH)) {
                return $value;
            }
            if (is_array($this->_enumValues[$value])) {
                if (in_array($value, $this->_enumValues[$value], true)) {
                    return $value;
                }
            }
            elseif ($value === $this->_enumValues[$value]) {
                return $value;
            }
        }
        return $this->_throwError(
            $failureMessage, __METHOD__, null, $value
        );
    }
}
?>