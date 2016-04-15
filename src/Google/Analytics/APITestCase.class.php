<?php
namespace Google\Analytics;

define('GOOGLE_ANALYTICS_API_AUTH_EMAIL', 'foo@bar.baz');
define('GOOGLE_ANALYTICS_API_AUTH_KEYFILE', __FILE__);
define('GOOGLE_ANALYTICS_API_DATA_DIR', sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid());
define('GOOGLE_ANALYTICS_API_LOG_FILE', GOOGLE_ANALYTICS_API_DATA_DIR . DIRECTORY_SEPARATOR . 'log.txt');
define('GOOGLE_ANALYTICS_API_LOG_EMAIL', 'foo@bar.baz');
if ((!defined('TEST_IGNORE_DB') || !TEST_IGNORE_DB) && !defined('OAUTH_DB_DSN')) {
    define('OAUTH_DB_DSN', TEST_DB_DSN);
    define('OAUTH_DB_USER', TEST_DB_USER);
    define('OAUTH_DB_PASSWORD', TEST_DB_PASSWORD);
}

// We will reuse FakeOAuthService from this test case
require_once(__DIR__ . '/../ServiceAccountAPITestCase.class.php');
use \Google\TestAPIRequest as TestAPIRequest;

class TestAPI extends API {
    /* This subclass of the real class we're testing provides public hooks for
    the mocking of certain methods. */
    
    protected static function _configureOAuthService() {
        if (!APIRequest::hasOAuthService()) {
            APIRequest::registerOAuthService(new \Google\FakeOAuthService());
        }
    }
    
    protected function _executeCurlHandle() {
        return $this->executeCurlHandle();
    }
    
    protected function _getLastHTTPResponse() {
        return $this->getLastHTTPResponse();
    }
    
    public function executeCurlHandle() {
        // To be mocked
    }
    
    public function getLastHTTPResponse() {
        return 200;
    }
}

class PaginationTestAPI extends TestAPI {
    /* This subclass is necessary because I did some refactoring, which
    required moving a test from Google\ServiceAccountAPITestCase to here, and
    that test wasn't written to be compatible with the stricter object typing
    that Google\Analytics\API enforces. */
    protected static function _configureOAuthService() {
        if (!TestAPIRequest::hasOAuthService()) {
            TestAPIRequest::registerOAuthService(new \Google\FakeOAuthService());
        }
    }
    
    protected function _castResponse() {
        /* Swallow the exception that Google\Analytics\APIResponseObjectFactory
        will throw. */
        try {
            parent::_castResponse();
        } catch (InvalidArgumentException $e) {}
        return $this->_responseParsed;
    }
    
    /**
     * @param Google\ServiceAccountAPIRequest $request
     */
    public function prepareRequest(\Google\ServiceAccountAPIRequest $request) {
        $this->_prepareRequest($request);
    }
    
    /**
     * @param Google\ServiceAccountAPIRequest $request = null
     * @return mixed
     */
    public function makeRequest(\Google\ServiceAccountAPIRequest $request = null)
    {
		return $this->_makeRequest($request);
    }
}

class APITestCase extends \TestHelpers\DatabaseTestCase {
    protected static $_tablesUnderTest = array(
        'oauth_effective_tokens',
        'google_analytics_api_profile_summaries',
        'google_analytics_api_web_property_summaries',
        'google_analytics_api_account_summaries',
        'google_analytics_api_columns',
        'google_analytics_api_fetch_log'
    );
    
    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        if (!is_dir(GOOGLE_ANALYTICS_API_DATA_DIR)) {
            mkdir(GOOGLE_ANALYTICS_API_DATA_DIR);
        }
    }
    
    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        /* This is important or the next test that throws a LoggingException
        will try and fail to write to the temporary file we're about to delete.
        */
        RuntimeException::unregisterLogger();
        $dirH = opendir(GOOGLE_ANALYTICS_API_DATA_DIR);
        if ($dirH !== false) {
            while (false !== ($entry = readdir($dirH))) {
                $path = GOOGLE_ANALYTICS_API_DATA_DIR . DIRECTORY_SEPARATOR
                      . $entry;
                if (!is_dir($path) && !unlink($path)) {
                    throw new \RuntimeException(
                        'Unable to delete ' . $path . '.'
                    );
                }
            }
            closedir($dirH);
            if (!rmdir(GOOGLE_ANALYTICS_API_DATA_DIR)) {
                throw new \RuntimeException(
                    'Unable to remove directory ' .
                    GOOGLE_ANALYTICS_API_DATA_DIR . '.'
                );
            }
        }
    }
    
    /**
     * @param array $columnData
     * @return array
     */
    private static function _getColumnNamesFromRawData(array $columnData) {
        $names = array();
        foreach ($columnData as $column) {
            $names[] = substr($column['name'], 3);
        }
        return $names;
    }
    
    /**
     * Returns a stub of the Google\Analytics\TestAPI class, optionally with
     * some of its methods mocked. If the second argument is true, the
     * stub's clearAccountCache() and clearColumnCache() methods will be called
     * before it is returned.
     *
     * @param array $mockMethods = null
     * @param boolean $emptyCache = true
     * @return Google\Analytics\TestAPI
     */
    protected function _getStub(array $mockMethods = null, $emptyCache = true) {
        $stub = $this->getMockBuilder(__NAMESPACE__ . '\TestAPI')
                     ->setMethods($mockMethods ? array_keys($mockMethods) : null)
                     ->getMock();
        if ($mockMethods) {
            foreach ($mockMethods as $methodName => $configuration) {
                $stub->method($methodName)->will($configuration);
            }
        }
        if ($emptyCache) {
            $stub->clearAccountCache();
            $stub->clearColumnCache();
        }
        return $stub;
    }
    
    /**
     * Runs a set of assertions against an object returned from the Google
     * Analytics API.
     *
     * @param array $expected
     * @param Google\Analytics\AbstractAPIResponseObject $obj
     */
    protected function _runAssertions(
        array $expected,
        AbstractAPIResponseObject $obj
    ) {
        foreach ($expected as $method => $expectedForMethod) {
            if (!method_exists($obj, $method)) {
                throw new \BadMethodCallException(
                    'This object has no method named "' . $method . '".'
                );
            }
            $result = $obj->$method();
            if (is_array($result)) {
                // This is probably an array of objects to be tested
                if ($result[key($result)] instanceof AbstractAPIResponseObject)
                {
                    $expectedCount = count($expectedForMethod);
                    $this->assertEquals(
                        $expectedCount,
                        count($result),
                        sprintf(
                            'Got an unexpected number of items in the return ' .
                            'value when testing %s::%s() (%s).',
                            get_class($obj),
                            $method,
                            $obj->getID()
                        )
                    );
                    for ($i = 0; $i < $expectedCount; $i++) {
                        $this->_runAssertions(
                            $expectedForMethod[$i], $result[$i]
                        );
                    }
                }
                else {
                    $this->assertSame(
                        $expectedForMethod,
                        $result,
                        sprintf(
                            'The array returned by %s::%s() (%s) failed to ' .
                            'compare as equal to the expected value.',
                            get_class($obj),
                            $method,
                            $obj->getID()
                        )
                    );
                }
            }
            elseif ($result instanceof AbstractAPIResponseObject) {
                $this->_runAssertions($expectedForMethod, $result);
            }
            else {
                $message = sprintf(
                    'Comparison failed when testing the value returned by ' .
                    '%s::%s() (%s).',
                    get_class($obj),
                    $method,
                    $obj->getID()
                );
                if (is_object($result)) {
                    if ($result instanceof \URL) {
                        /* In this case we probably have strings in our
                        expected result set. */
                        $this->assertEquals(
                            $expectedForMethod, (string)$result, $message
                        );
                    }
                    else {
                        // It probably won't be the same object instance
                        $this->assertEquals(
                            $expectedForMethod, $result, $message
                        );
                    }
                }
                else {
                    $this->assertSame(
                        $expectedForMethod, $result, $message
                    );
                }
            }
        }
    }
    
    /**
     * Helper method that calls the getSegments() method on the given instance,
     * asserts that it throws the specified exception and that it contains the
     * given message, asserts that the exception message is present in the last
     * line of the log file, and asserts that an email regarding the exception
     * is sent.
     *
     * @param Google\Analytics\TestAPI $instance
     * @param string $expectedException
     * @param string $expectedMessage
     * @param boolean $assertMatchingClass = false
     */
    protected function _testException(
        TestAPI $instance,
        $expectedException,
        $expectedMessage,
        $assertMatchingClass = false
    ) {
        $this->assertThrows(
            $expectedException, array($instance, 'getSegments')
        );
        if ($assertMatchingClass) {
            $this->assertEquals(
                $expectedException, get_class($this->_lastException)
            );
        }
        $this->assertEquals(
            $expectedMessage, $this->_lastException->getMessage()
        );
        \LoggingExceptions\Exception::getLogger()->flush();
        // Fortunately this works because we're not zipping it
        $fileContents = explode(
            PHP_EOL, trim(file_get_contents(GOOGLE_ANALYTICS_API_LOG_FILE))
        );
        $this->assertContains($expectedMessage, array_pop($fileContents));
        $this->assertContains($expectedMessage, $GLOBALS['__lastEmailSent']['message']);
        $this->assertEquals(GOOGLE_ANALYTICS_API_LOG_EMAIL, $GLOBALS['__lastEmailSent']['to']);
    }
    
    /**
     * Executes all the tests in this case (with the exception of this one)
     * with settings that cause it to ignore the database.
     *
     * @group meta
     */
    public function testWithoutDatabase() {
        passthru(sprintf(
            'env phpunit --configuration=%s',
            __DIR__ . '/../phpunit_nodb.xml'
        ), $returnVal);
        if ($returnVal != 0) {
            $this->fail('Got failure when running without database.');
        }
    }
    
    /**
     * Helper test that confirms that when using a database and the settings
     * permit it, a new process pulls column information from there instead of
     * making a new API request.
     *
     * @group nested
     */
    public function testGettingCachedColumns() {
        if (!TEST_IGNORE_DB) {
            throw new \RuntimeException(
                'This test requires that the TEST_IGNORE_DB setting be true.'
            );
        }
        define('OAUTH_DB_DSN', TEST_DB_DSN);
        define('OAUTH_DB_USER', TEST_DB_USER);
        define('OAUTH_DB_PASSWORD', TEST_DB_PASSWORD);
        /* Set up an instance that will throw an exception if it tries to make
        any API request at all. */
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "dailyLimitExceeded"}
    ],
    "message": "Daily limit exceeded!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(403)
        ), false);
        $dimensions = $instance->getDimensions(true);
        $expectedCount = count(APITestData::$TEST_EXPECTED_DIMENSIONS);
        $this->assertEquals($expectedCount, count($dimensions));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_DIMENSIONS[$i],
                $instance->getColumn($dimensions[$i])
            );
        }
        $metrics = $instance->getMetrics(true);
        $expectedCount = count(APITestData::$TEST_EXPECTED_METRICS);
        $this->assertEquals($expectedCount, count($metrics));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_METRICS[$i],
                $instance->getColumn($metrics[$i])
            );
        }
    }
    
    /**
     * Helper test that confirms that when the lifetime of the database cache
     * has expired, the API's request to get them contains the etag from the
     * previous response, and that if the service responds appropriately, the
     * cached data is reused.
     *
     * @group nested
     */
    public function testNotModifiedResponse() {
        if (!TEST_IGNORE_DB) {
            throw new \RuntimeException(
                'This test requires that the TEST_IGNORE_DB setting be true.'
            );
        }
        define('OAUTH_DB_DSN', TEST_DB_DSN);
        define('OAUTH_DB_USER', TEST_DB_USER);
        define('OAUTH_DB_PASSWORD', TEST_DB_PASSWORD);
        /* If we don't define this, the instance will just try to pull from the
        database. Conveniently, this also proves that the setting is respected
        so I don't have to write a separate test for it. */
        define('GOOGLE_ANALYTICS_API_METADATA_CACHE_DURATION', 0);
        $instance = $this->_getStub(array(
            'getLastHTTPResponse' => $this->returnValue(304),
            'getResponseHeaderAsAssociativeArray' => $this->returnValue(array(
                'ETag' => APITestData::$TEST_ETAG
            ))
        ));
        $dimensions = $instance->getDimensions(true);
        /* There should have been a request, and the ETag should have been
        included. */
        $curlOpts = $instance->getCurlOptions();
        $this->assertContains(
            'If-None-Match: ' . APITestData::$TEST_ETAG,
            $curlOpts[CURLOPT_HTTPHEADER]
        );
        $expectedCount = count(APITestData::$TEST_EXPECTED_DIMENSIONS);
        $this->assertEquals($expectedCount, count($dimensions));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_DIMENSIONS[$i],
                $instance->getColumn($dimensions[$i])
            );
        }
        $metrics = $instance->getMetrics(true);
        $expectedCount = count(APITestData::$TEST_EXPECTED_METRICS);
        $this->assertEquals($expectedCount, count($metrics));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_METRICS[$i],
                $instance->getColumn($metrics[$i])
            );
        }
    }
    
    /**
     * Helper test that confirms that when using a database and the settings
     * permit it, a new process pulls account information from there instead of
     * making a new API request.
     *
     * @group nested
     */
    public function testGettingCachedAccountSummaries() {
        if (!TEST_IGNORE_DB) {
            throw new \RuntimeException(
                'This test requires that the TEST_IGNORE_DB setting be true.'
            );
        }
        define('OAUTH_DB_DSN', TEST_DB_DSN);
        define('OAUTH_DB_USER', TEST_DB_USER);
        define('OAUTH_DB_PASSWORD', TEST_DB_PASSWORD);
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "dailyLimitExceeded"}
    ],
    "message": "Daily limit exceeded!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(403)
        ), false); // Make sure this instance doesn't clear its cache
        $summaries = $instance->getAccountSummaries();
        $expectedCount = count(APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES);
        $this->assertEquals($expectedCount, count($summaries));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES[$i], $summaries[$i]
            );
        }
    }
    
    /**
     * Helper test that confirms that when using a database and the settings
     * permit it, a new process pulls account information from there instead of
     * making a new API request.
     *
     * @group nested
     */
    public function testGettingCachedUpdatedAccountSummaries() {
        if (!TEST_IGNORE_DB) {
            throw new \RuntimeException(
                'This test requires that the TEST_IGNORE_DB setting be true.'
            );
        }
        define('OAUTH_DB_DSN', TEST_DB_DSN);
        define('OAUTH_DB_USER', TEST_DB_USER);
        define('OAUTH_DB_PASSWORD', TEST_DB_PASSWORD);
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "dailyLimitExceeded"}
    ],
    "message": "Daily limit exceeded!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(403)
        ), false); // Make sure this instance doesn't clear its cache
        $summaries = $instance->getAccountSummaries();
        $expectedCount = count(APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES_2);
        $this->assertEquals($expectedCount, count($summaries));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES_2[$i], $summaries[$i]
            );
        }
    }
    
    /**
     * Helper test that confirms that the account cache setting works properly.
     *
     * @group nested
     */
    public function testGettingUpdatedAccountSummaries() {
        if (!TEST_IGNORE_DB) {
            throw new \RuntimeException(
                'This test requires that the TEST_IGNORE_DB setting be true.'
            );
        }
        define('OAUTH_DB_DSN', TEST_DB_DSN);
        define('OAUTH_DB_USER', TEST_DB_USER);
        define('OAUTH_DB_PASSWORD', TEST_DB_PASSWORD);
        define('GOOGLE_ANALYTICS_API_ACCOUNTS_CACHE_DURATION', 0);
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue(
                APITestData::$TEST_ACCOUNT_SUMMARIES_RESPONSE_2
            )
        ), false); // Don't do an explicit clear of the cache
        $summaries = $instance->getAccountSummaries();
        $expectedCount = count(APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES_2);
        $this->assertEquals($expectedCount, count($summaries));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES_2[$i], $summaries[$i]
            );
        }
    }
    
    /**
     * Helper test for testing the autozip behavior.
     *
     * @group nested
     */
    public function testAutozip() {
        if (!TEST_IGNORE_DB) {
            throw new \RuntimeException(
                'This test requires that the TEST_IGNORE_DB setting be true.'
            );
        }
        define('GOOGLE_ANALYTICS_API_AUTOZIP_THRESHOLD', 20);
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_QUERY_RESPONSE,
                APITestData::$TEST_COLUMNS_RESPONSE
            )
        ));
        $query = new GaDataQuery();
        $query->setProfile(new ProfileSummary(array(
            'id' => 'ga:12345',
            'name' => 'security Blog',
            'type' => 'WEB'
        )));
        $query->setStartDate("2015-06-01");
        $query->setEndDate("2015-06-02");
        $query->setMetrics(array("users", "organicSearches"));
        $query->setDimensions(array("medium"));
        $formatter = new ReportFormatter();
        $email = new \Email('somebody@somewhere.com');
        $instance->queryToEmail($query, $formatter, $email);
        $this->assertEquals('.zip', substr($email->getAttachmentName(0), -4));
    }
    
    /**
     * Tests whether the expected exception is thrown when the API response
     * content includes an object for which no class definition exists. Note
     * that because some of these error codes will cause the request to be
     * automatically retried with a five second delay, this test should be
     * expected to take some time.
     */
    public function testBadObjectType() {
        /* This will fail because there is no Google\Analytics\AccountSummaries
        class. */
        $response = <<<EOF
{
  "kind": "analytics#accountSummaries",
  "username": "some user",
  "totalResults": 3,
  "startIndex": 1,
  "itemsPerPage": 50
}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response)
        ));
        $this->assertThrows(
            __NAMESPACE__ . '\BadMethodCallException',
            array($instance, 'getAccountSummaries')
        );
        // This will fail because there is no Google\Analytics\FooBar class
        $response = <<<EOF
{
  "username": "some user",
  "totalResults": 3,
  "startIndex": 1,
  "itemsPerPage": 50,
  "items": [
    {
      "kind": "analytics#fooBar",
      "foo": "bar"
    }
  ]
}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response)
        ));
        /* Doesn't matter what method we call as long as it makes a request to
        the remote service. */
        $this->assertThrows(
            __NAMESPACE__ . '\BadMethodCallException',
            array($instance, 'getSegments')
        );
        // This will fail because neither the 'kind' nor 'items' key is present
        $response = <<<EOF
{
  "username": "some user",
  "totalResults": 3,
  "startIndex": 1,
  "itemsPerPage": 50
}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response)
        ));
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            array($instance, 'getSegments')
        );
        // This will fail because the 'kind' property is malformed
        $response = <<<EOF
{
  "username": "some user",
  "totalResults": 3,
  "startIndex": 1,
  "itemsPerPage": 50,
  "kind": "asdf"
}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response)
        ));
        $this->assertThrows(
            __NAMESPACE__ . '\UnexpectedValueException',
            array($instance, 'getSegments')
        );
    }
    
    /**
     * Tests that the proper exceptions are thrown in the proper situations.
     */
    public function testErrorHandling() {
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "invalidParameter"}
    ],
    "message": "Don't pass invalid parameters!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(400)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\InvalidParameterException',
            "Don't pass invalid parameters!"
        );
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "badRequest"}
    ],
    "message": "Your request was bad!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(400)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\BadRequestException',
            "Your request was bad!"
        );
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "asdfasdf"}
    ],
    "message": "Invalid credentials dummy!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(401)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\InvalidCredentialsException',
            "Invalid credentials dummy!"
        );
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "insufficientPermissions"}
    ],
    "message": "Insufficient permissions!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(403)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\InsufficientPermissionsException',
            "Insufficient permissions!"
        );
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "dailyLimitExceeded"}
    ],
    "message": "Daily limit exceeded!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(403)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\DailyLimitExceededException',
            "Daily limit exceeded!"
        );
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "userRateLimitExceeded"}
    ],
    "message": "Rate limit exceeded!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(403)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\UserRateLimitExceededException',
            "Rate limit exceeded!"
        );
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "quotaExceeded"}
    ],
    "message": "Quota exceeded!"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(403)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\QuotaExceededException',
            "Quota exceeded!"
        );
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "asdfasdf"}
    ],
    "message": "Unknown error"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(503)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\RemoteException',
            "Unknown error"
        );
        // Any other response code should trigger a more generic exception
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(409)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\RuntimeException',
            "Unknown error",
            /* Since we're testing for the parent type of the more specific
            exceptions, make the helper method do a more specific assertion
            than normal. */
            true
        );
        /* If the message isn't present, a generic one should be used, no
        matter what the exception type. */
        $response = <<<EOF
{"error": {
    "errors": [
        {"reason": "invalidParameter"}
    ]
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(400)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\InvalidParameterException',
            'Could not find error message in API response (response code was 400).'
        );
        /* If the reason string isn't present, in some cases the proper
        exception type may be thrown (if it is unambiguous based on the HTTP
        response code), while in others a more generic exception will be used
        as a fallback. */
        $uniq = uniqid();
        $response = <<<EOF
{"error": {
    "message": "Unknown error",
    "uniq": "{$uniq}"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(401)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\InvalidCredentialsException',
            'Unknown error'
        );
        $uniq = uniqid();
        $response = <<<EOF
{"error": {
    "message": "Unknown error",
    "uniq": "{$uniq}"
}}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(403)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\RuntimeException',
            'Unknown error',
            true
        );
        /* Since the handler had to fall back to a generic exception, the
        response should have been logged. */
        $this->assertContains(
            $response, file_get_contents(GOOGLE_ANALYTICS_API_LOG_FILE)
        );
        /* If the response doesn't have any of what we expect, we get the
        generic message, the generic exception, and the logging. */
        $uniq = uniqid();
        $response = '{"uniq": "' . $uniq . '"}';
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response),
            'getLastHTTPResponse' => $this->returnValue(404)
        ));
        $this->_testException(
            $instance,
            __NAMESPACE__ . '\RuntimeException',
            'Could not find error message in API response (response code was 404).',
            true
        );
        $this->assertContains(
            $response, file_get_contents(GOOGLE_ANALYTICS_API_LOG_FILE)
        );
    }
    
    /**
     * Tests that the automatic pagination feature works as expected.
     */
    public function testPagination() {
        $data = array(
            array(
                'iteration' => 1,
                'uniq' => $this->_generateRandomText(128),
                'nextLink' => 'http://foo.bar/api_paginated/?p=2&h=iogfnaeorghiua'
            ),
            array(
                'iteration' => 2,
                'uniq' => $this->_generateRandomText(128),
                'nextLink' => 'http://foo.bar/api_paginated/?p=3&h=adsfeansgytukt'
            ),
            array(
                'iteration' => 3,
                'uniq' => $this->_generateRandomText(128),
                'nextLink' => 'http://foo.bar/api_paginated/?p=4&h=dfsfgjsrtrtjrt'
            ),
            array(
                'iteration' => 4,
                'uniq' => $this->_generateRandomText(128)
            )
        );
        $instance = $this->getMockBuilder(__NAMESPACE__ . '\PaginationTestAPI')
                         ->setMethods(array('executeCurlHandle'))->getMock();
        $instance->method('executeCurlHandle')->will($this->onConsecutiveCalls(
                            json_encode($data[0]),
                            json_encode($data[1]),
                            json_encode($data[2]),
                            json_encode($data[3])
                         ));
        $iteration = 0;
        $expectedIterations = 4;
        $instance->prepareRequest(new TestAPIRequest('http://foo.bar/api'));
        while ($response = $instance->makeRequest()) {
            if ($iteration > 0) {
                $this->assertTrue($instance->getRequest()->getURL()->compare(
                    $data[$iteration - 1]['nextLink']
                ));
            }
            else {
                $this->assertEquals(
                    'http://foo.bar/api',
					(string)$instance->getRequest()->getURL()
                );
            }
            $this->assertEquals($data[$iteration++], $response);
        }
        $this->assertEquals($expectedIterations, $iteration);
    }
    
    /**
     * Tests that columns (i.e. dimensions and metrics) are cached, bypassing a
     * call to the API every time a caller wants access to one.
     */
    public function testColumnCaching() {
        $errorResponse = <<<EOF
{"error": {
    "errors": [
        {"reason": "dailyLimitExceeded"}
    ],
    "message": "Daily limit exceeded!"
}}
EOF;
        /* Set up an instance that will respond with a set of columns on the
        first call and an error on the second call. */
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_COLUMNS_RESPONSE, $errorResponse
            ),
            'getLastHTTPResponse' => $this->onConsecutiveCalls(200, 403)
        ));
        /* Go ahead and get the deprecated ones too, because it makes the
        assertion easier. */
        $dimensions = $instance->getDimensions(true);
        $expectedCount = count(APITestData::$TEST_EXPECTED_DIMENSIONS);
        $this->assertEquals($expectedCount, count($dimensions));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_DIMENSIONS[$i],
                /* Note that this would fail if the response weren't cached in
                the instance. */
                $instance->getColumn($dimensions[$i])
            );
        }
        $metrics = $instance->getMetrics(true);
        $expectedCount = count(APITestData::$TEST_EXPECTED_METRICS);
        $this->assertEquals($expectedCount, count($metrics));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_METRICS[$i],
                $instance->getColumn($metrics[$i])
            );
        }
        // Test the filtering out of the deprecated ones
        $expectedLiveDimensions = array();
        foreach (APITestData::$TEST_EXPECTED_DIMENSIONS as $dimensionData) {
            if (!$dimensionData['isDeprecated']) {
                $expectedLiveDimensions[] = $dimensionData;
            }
        }
        $expectedLiveMetrics = array();
        foreach (APITestData::$TEST_EXPECTED_METRICS as $metricData) {
            if (!$metricData['isDeprecated']) {
                $expectedLiveMetrics[] = $metricData;
            }
        }
        $dimensions = $instance->getDimensions();
        $expectedCount = count($expectedLiveDimensions);
        $this->assertEquals($expectedCount, count($dimensions));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                $expectedLiveDimensions[$i],
                $instance->getColumn($dimensions[$i])
            );
        }
        $metrics = $instance->getMetrics();
        $expectedCount = count($expectedLiveMetrics);
        $this->assertEquals($expectedCount, count($metrics));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                $expectedLiveMetrics[$i],
                $instance->getColumn($metrics[$i])
            );
        }
        if (!TEST_IGNORE_DB) {
            /* Run these tests in a separate process so we know they will start
            with a clean slate. */
            $tests = array(
                'testGettingCachedColumns', 'testNotModifiedResponse'
            );
            foreach ($tests as $test) {
                passthru(sprintf(
                    'env phpunit --configuration=%s --filter=%s',
                    __DIR__ . '/../phpunit_nested.xml',
                    $test
                ), $returnVal);
                if ($returnVal != 0) {
                    $this->fail(
                        'Got failure when testing retrieval of columns from ' .
                        'the cache.'
                    );
                }
            }
        }
    }
    
    /**
     * Tests that account summaries are cached after being retrieved initially.
     */
    public function testAccountSummaryCaching() {
        $errorResponse = <<<EOF
{"error": {
    "errors": [
        {"reason": "dailyLimitExceeded"}
    ],
    "message": "Daily limit exceeded!"
}}
EOF;
        /* Set up an instance that will respond with account summaries on the
        first call and an error on the second call. */
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_ACCOUNT_SUMMARIES_RESPONSE, $errorResponse
            ),
            'getLastHTTPResponse' => $this->onConsecutiveCalls(200, 403)
        ));
        $summaries = $instance->getAccountSummaries();
        $expectedCount = count(APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES);
        $this->assertEquals($expectedCount, count($summaries));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES[$i], $summaries[$i]
            );
        }
        /* Now go through getting them by name and asserting that they are what
        we expect; if they weren't cached this would result in additional API
        calls. */
        foreach (APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES as $summaryData) {
            $this->_runAssertions(
                $summaryData,
                $instance->getAccountSummaryByName($summaryData['getName'])
            );
            $this->_runAssertions(
                $summaryData,
                $instance->getAccountSummaryByID($summaryData['getID'])
            );
            foreach ($summaryData['getWebPropertySummaries'] as $webPropertyData)
            {
                $this->_runAssertions(
                    $webPropertyData,
                    $instance->getWebPropertySummaryByName($webPropertyData['getName'])
                );
                $this->_runAssertions(
                    $webPropertyData,
                    $instance->getWebPropertySummaryByID($webPropertyData['getID'])
                );
                foreach ($webPropertyData['getProfileSummaries'] as $profileData)
                {
                    $this->_runAssertions(
                        $profileData,
                        $instance->getProfileSummaryByName($profileData['getName'])
                    );
                    $this->_runAssertions(
                        $profileData,
                        $instance->getProfileSummaryByID($profileData['getID'])
                    );
                }
            }
        }
        if (!TEST_IGNORE_DB) {
            passthru(sprintf(
                'env phpunit --configuration=%s --filter=%s',
                __DIR__ . '/../phpunit_nested.xml',
                'testGettingCachedAccountSummaries'
            ), $returnVal);
            if ($returnVal != 0) {
                $this->fail(
                    'Got failure when testing retrieval of account summaries ' .
                    'from the cache.'
                );
            }
        }
        /* Now confirm that if we get a new response that contains a different
        set of properties, the database will be updated to reflect the ones
        that are no longer visible, as confirmed by spinning up a separate
        process. */
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue(
                APITestData::$TEST_ACCOUNT_SUMMARIES_RESPONSE_2
            )
        ));
        $summaries = $instance->getAccountSummaries();
        $expectedCount = count(APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES_2);
        $this->assertEquals($expectedCount, count($summaries));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES_2[$i], $summaries[$i]
            );
        }
        if (!TEST_IGNORE_DB) {
            passthru(sprintf(
                'env phpunit --configuration=%s --filter=%s',
                __DIR__ . '/../phpunit_nested.xml',
                'testGettingCachedUpdatedAccountSummaries'
            ), $returnVal);
            if ($returnVal != 0) {
                $this->fail(
                    'Got failure when testing retrieval of modified account ' .
                    'summaries from the cache.'
                );
            }
        }
    }
    
    /**
     * Tests whether we can get one account summary response, spin up a new
     * process, tweak the cache setting, and get a different response.
     */
    public function testWhetherAccountCacheSettingIsRespected() {
        if (TEST_IGNORE_DB) {
            // Test doesn't make sense in this context
            return;
        }
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue(
                APITestData::$TEST_ACCOUNT_SUMMARIES_RESPONSE
            )
        ));
        $summaries = $instance->getAccountSummaries();
        $expectedCount = count(APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES);
        $this->assertEquals($expectedCount, count($summaries));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES[$i], $summaries[$i]
            );
        }
        passthru(sprintf(
            'env phpunit --configuration=%s --filter=%s',
            __DIR__ . '/../phpunit_nested.xml',
            'testGettingUpdatedAccountSummaries'
        ), $returnVal);
        if ($returnVal != 0) {
            $this->fail(
                'Got failure when testing retrieval of modified account ' .
                'summaries in another process.'
            );
        }
    }
    
    /**
     * Tests that API responses are parsed into objects as expected.
     */
    public function testObjectParsing() {
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue(
                APITestData::$TEST_ACCOUNT_SUMMARIES_RESPONSE
            )
        ));
        $summaries = $instance->getAccountSummaries();
        $expectedCount = count(APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES);
        $this->assertEquals($expectedCount, count($summaries));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_ACCOUNT_SUMMARIES[$i], $summaries[$i]
            );
        }
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue(
                APITestData::$TEST_SEGMENTS_RESPONSE
            )
        ));
        /* The method we call really doesn't matter but I'm calling the correct
        one for the return value anyway lest another reader get confused. */
        $segments = $instance->getSegments();
        $expectedCount = count(APITestData::$TEST_EXPECTED_SEGMENTS);
        $this->assertEquals($expectedCount, count($segments));
        for ($i = 0; $i < $expectedCount; $i++) {
            $this->_runAssertions(
                APITestData::$TEST_EXPECTED_SEGMENTS[$i], $segments[$i]
            );
        }
    }
    
    
    /**
     * Tests the API behavior with an ordinary query (both a single-page and a
     * paged variety).
     */
    public function testBasicQuery() {
        /* Certain pieces of plumbing will ask the API instance for information
        about the columns before the actual query happens, so the first thing
        it has to return is a response containing column information. It
        doesn't matter which columns, because the plumbing in question is smart
        enough to construct new column objects on the fly. */
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_COLUMNS_RESPONSE,
                APITestData::$TEST_QUERY_RESPONSE
            )
        ));
        $query = new GaDataQuery();
        $query->setProfile(new ProfileSummary(array(
            'id' => 'ga:12345',
            'name' => 'security Blog',
            'type' => 'WEB'
        )));
        $query->setStartDate("2015-06-01");
        $query->setEndDate("2015-06-02");
        $query->setMetrics(array("users", "organicSearches"));
        $query->setDimensions(array("medium"));
        $expected = array(
            'containsSampledData' => false,
            'getItemsPerPage' => 500,
            'getTotalResults' => 3,
            'getPreviousLink' => null,
            'getNextLink' => null,
            'getProfileInfo' => array(
                'profileId' => '12345',
                'accountId' => '98765',
                'webPropertyId' => 'UA-98765-1',
                'internalWebPropertyId' => '46897987',
                'profileName' => 'security Blog',
                'tableId' => 'ga:12345'
            ),
            'getColumnHeaders' => new GaDataColumnHeaderCollection(
                APITestData::$TEST_QUERY_RESPONSE_COLUMNS, $instance
            ),
            'getRows' => new GaDataRowCollection(
                APITestData::$TEST_QUERY_RESPONSE_ROWS
            ),
            'getSampleSize' => null,
            'getSampleSpace' => null,
            'getTotals' => array(
                'ga:users' => '151',
                'ga:organicSearches' => '31'
            )
        );
        /* By the time the comparison is made, this will have happened on the
        response object. */
        $expected['getRows']->setColumnHeaders($expected['getColumnHeaders']);
        foreach ($expected['getTotals'] as $gaName => $total) {
            $expected['getColumnHeaders']->getColumn(
                substr($gaName, 3)
            )->setTotal($total);
        }
        $data = $instance->query($query);
        $this->_runAssertions($expected, $data);
        $this->assertEquals(
            count(APITestData::$TEST_QUERY_RESPONSE_ROWS),
            $instance->getLastFetchedRowCount()
        );
        /* The Google\Analytics\GaData object's "id" is its URL, which should
        have been what was requested. The boilerplate API code probably URL-
        encoded its parameters, though. */
        $requestURL = new \URL(
            urldecode((string)$instance->getRequest()->getURL())
        );
        $this->assertTrue($requestURL->compare($data->getID()));
        /* Now try a paged one. Avoid having this stub empty the caches so we
        don't have to mock the returning of the column data. */
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_PAGED_QUERY_RESPONSE_1,
                APITestData::$TEST_PAGED_QUERY_RESPONSE_2
            )
        ), false);
        $query = new GaDataQuery();
        $query->setProfile(new ProfileSummary(array(
            'id' => 'ga:12345',
            'name' => 'security Blog',
            'type' => 'WEB'
        )));
        $query->setStartDate("2015-06-01");
        $query->setEndDate("2015-06-02");
        $query->setMetrics(array("users", "organicSearches"));
        $query->setDimensions(array('source'));
        $query->setMaxResults(5);
		/* Note that this total result amount is hardcoded in
		Google/Analytics/APITestData::$TEST_PAGED_QUERY_RESPONSE_1. */
        $expected['getTotalResults'] = 27;
        $expected['getItemsPerPage'] = 5;
        $expected['getNextLink'] = 'https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users%2Cga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=6&max-results=5';
        $expected['getColumnHeaders'] = new GaDataColumnHeaderCollection(
            APITestData::$TEST_PAGED_QUERY_RESPONSE_COLUMNS, $instance
        );
        $expected['getRows'] = new GaDataRowCollection(
            APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_1
        );
        $expected['getRows']->setColumnHeaders($expected['getColumnHeaders']);
        foreach ($expected['getTotals'] as $gaName => $total) {
            $expected['getColumnHeaders']->getColumn(
                substr($gaName, 3)
            )->setTotal($total);
        }
        $data = $instance->query($query);
        $this->_runAssertions($expected, $data);
        $this->assertEquals(
            count(APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_1),
            $instance->getLastFetchedRowCount()
        );
        $requestURL = new \URL(
            urldecode((string)$instance->getRequest()->getURL())
        );
        $this->assertTrue($requestURL->compare($data->getID()));
        $expected['getRows'] = new GaDataRowCollection(
            APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_2
        );
        $expected['getRows']->setColumnHeaders($expected['getColumnHeaders']);
        $expected['getPreviousLink'] = 'https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users%2Cga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=1&max-results=5';
        $expected['getNextLink'] = 'https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users%2Cga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=11&max-results=5';
        $data = $instance->query($query);
        $this->_runAssertions($expected, $data);
        $this->assertEquals(
            count(APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_1) +
            count(APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_2),
            $instance->getLastFetchedRowCount()
        );
        $requestURL = new \URL(
            urldecode((string)$instance->getRequest()->getURL())
        );
        $this->assertTrue($requestURL->compare($data->getID()));
        // Might as well test what happens when we write this query to a file
        $formatter = new ReportFormatter();
        $formatter->openTempFile(GOOGLE_ANALYTICS_API_DATA_DIR);
        $instance->queryToFile($query, $formatter);
        $expectedRows = array(
            array("(direct)", "57", "0"),
            array("aol", "1", "1"),
            array("articles.latimes.com", "1", "0"),
            array("blacksintechnology.net", "1", "0"),
            array("blog.infosecanalytics.com", "4", "0"),
            array("business.time.com", "1", "0"),
            array("clarksite.wordpress.com", "1", "0"),
            array("csirtgadgets.org", "1", "0"),
            array("cygnus.vzbi.com", "1", "0"),
            array("databreaches.net", "1", "0")
        );
        // And it should start with a header of course
        array_unshift(
            $expectedRows, $expected['getColumnHeaders']->getColumnNames()
        );
        $fh = fopen($formatter->getFileName(), 'r');
        $i = 0;
        while ($row = fgetcsv($fh)) {
            $this->assertEquals($expectedRows[$i++], $row);
        }
        $this->assertEquals(
            count(APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_1) +
            count(APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_2),
            $instance->getLastFetchedRowCount()
        );
    }
	
	/**
	 * Tests the API behavior with a query where we ask for a total number of
	 * results less than the available number of results.
	 */
	public function testTruncatedQuery() {
		// This test broadly follows Google\Analytics\APITestCase::testBasicQuery()
		$instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_COLUMNS_RESPONSE,
                APITestData::$TEST_QUERY_RESPONSE
            )
        ));
		$query = new GaDataQuery();
        $query->setProfile(new ProfileSummary(array(
            'id' => 'ga:12345',
            'name' => 'security Blog',
            'type' => 'WEB'
        )));
        $query->setStartDate("2015-06-01");
        $query->setEndDate("2015-06-02");
        $query->setMetrics(array("users", "organicSearches"));
        $query->setDimensions(array("medium"));
		$query->setTotalResults(2);
        $expected = array(
            'containsSampledData' => false,
            'getItemsPerPage' => 500,
            'getTotalResults' => 3,
            'getPreviousLink' => null,
            'getNextLink' => null,
            'getProfileInfo' => array(
                'profileId' => '12345',
                'accountId' => '98765',
                'webPropertyId' => 'UA-98765-1',
                'internalWebPropertyId' => '46897987',
                'profileName' => 'security Blog',
                'tableId' => 'ga:12345'
            ),
            'getColumnHeaders' => new GaDataColumnHeaderCollection(
                APITestData::$TEST_QUERY_RESPONSE_COLUMNS, $instance
            ),
            'getRows' => new GaDataRowCollection(
                array_slice(APITestData::$TEST_QUERY_RESPONSE_ROWS, 0, 2)
            ),
            'getSampleSize' => null,
            'getSampleSpace' => null,
            'getTotals' => array(
                'ga:users' => '151',
                'ga:organicSearches' => '31'
            )
        );
		$expected['getRows']->setColumnHeaders($expected['getColumnHeaders']);
        foreach ($expected['getTotals'] as $gaName => $total) {
            $expected['getColumnHeaders']->getColumn(
                substr($gaName, 3)
            )->setTotal($total);
        }
		$data = $instance->query($query);
        $this->_runAssertions($expected, $data);
		/* Even though the returned object only had two rows in it (as we
		requested), the API instance still fetched three rows. */
		$this->assertEquals(
            count(APITestData::$TEST_QUERY_RESPONSE_ROWS),
            $instance->getLastFetchedRowCount()
        );
		// Try a paged query where we stop on a page boundary
		$instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_PAGED_QUERY_RESPONSE_1,
                APITestData::$TEST_PAGED_QUERY_RESPONSE_2
            )
        ), false);
		$query = new GaDataQuery();
        $query->setProfile(new ProfileSummary(array(
            'id' => 'ga:12345',
            'name' => 'security Blog',
            'type' => 'WEB'
        )));
        $query->setStartDate("2015-06-01");
        $query->setEndDate("2015-06-02");
        $query->setMetrics(array("users", "organicSearches"));
        $query->setDimensions(array('source'));
        $query->setMaxResults(5);
		$query->setTotalResults(5);
		$expected['getTotalResults'] = 27;
        $expected['getItemsPerPage'] = 5;
        $expected['getNextLink'] = 'https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users%2Cga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=6&max-results=5';
        $expected['getColumnHeaders'] = new GaDataColumnHeaderCollection(
            APITestData::$TEST_PAGED_QUERY_RESPONSE_COLUMNS, $instance
        );
        $expected['getRows'] = new GaDataRowCollection(
            APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_1
        );
        $expected['getRows']->setColumnHeaders($expected['getColumnHeaders']);
        foreach ($expected['getTotals'] as $gaName => $total) {
            $expected['getColumnHeaders']->getColumn(
                substr($gaName, 3)
            )->setTotal($total);
        }
		while ($data = $instance->query($query)) {
			$lastData = $data;
		}
		$this->_runAssertions($expected, $lastData);
		$this->assertEquals(
            count(APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_1),
            $instance->getLastFetchedRowCount()
        );
		// Now try one where we stop in the middle of a page
		$instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_PAGED_QUERY_RESPONSE_1,
                APITestData::$TEST_PAGED_QUERY_RESPONSE_2
            )
        ), false);
		$query->setTotalResults(7);
		$expected['getRows'] = new GaDataRowCollection(
            array_slice(APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_2, 0, 2)
        );
        $expected['getRows']->setColumnHeaders($expected['getColumnHeaders']);
		$expected['getPreviousLink'] = 'https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users%2Cga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=1&max-results=5';
		$expected['getNextLink'] = 'https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users%2Cga:organicSearches&start-date=2015-06-01&end-date=2015-06-02&start-index=11&max-results=5';
		$iterations = 0;
		while ($data = $instance->query($query)) {
			$iterations++;
			$lastData = $data;
		}
		$this->_runAssertions($expected, $lastData);
		$this->assertEquals(2, $iterations);
		$this->assertEquals(
            count(APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_1) +
            count(APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_2),
            $instance->getLastFetchedRowCount()
        );
	}
    
    /**
     * Tests the API behavior with an iterative query, which is a bit different
     * than with an ordinary query.
     */
    public function testIterativeQuery() {
        /* To test this type of behavior, we need to mock up a situation where
        the API returns a complete result set over one or more requests, then
        does the same thing based on different query parameters. */
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_COLUMNS_RESPONSE,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_1,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_2,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_3
            )
        ));
        $query = new DateRangeGaDataQuery();
        $query->setSummaryStartDate('2015-06-01');
        $query->setSummaryEndDate('2015-06-02');
        $query->setIterationInterval(new \DateInterval('P1D'));
        $query->setProfile(new ProfileSummary(array(
            'id' => 'ga:12345',
            'name' => 'security Blog',
            'type' => 'WEB'
        )));
        $query->setMetrics(array("users", "organicSearches"));
        $query->setDimensions(array('source'));
        $query->setMaxResults(10);
        $expected = array();
        $expectedInstance = array(
            'containsSampledData' => true,
            'getItemsPerPage' => 10,
            'getTotalResults' => 10,
            'getPreviousLink' => null,
            'getNextLink' => null,
            'getProfileInfo' => array(
                'profileId' => '12345',
                'accountId' => '98765',
                'webPropertyId' => 'UA-98765-1',
                'internalWebPropertyId' => '46897987',
                'profileName' => 'security Blog',
                'tableId' => 'ga:12345'
            ),
            'getColumnHeaders' => new GaDataColumnHeaderCollection(
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_COLUMNS, $instance
            ),
            'getRows' => new GaDataRowCollection(
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_1
            ),
            'getSampleSize' => '654978',
            'getSampleSpace' => '6579876',
            'getTotals' => array(
                'ga:users' => '69',
                'ga:organicSearches' => '1'
            )
        );
        $expectedInstance['getRows']->setColumnHeaders(
            $expectedInstance['getColumnHeaders']
        );
        foreach ($expectedInstance['getTotals'] as $gaName => $total) {
            $expectedInstance['getColumnHeaders']->getColumn(
                substr($gaName, 3)
            )->setTotal($total);
        }
        $expected[] = $expectedInstance;
        $expectedInstance['containsSampledData'] = false;
        $expectedInstance['getSampleSize'] = null;
        $expectedInstance['getSampleSpace'] = null;
        $expectedInstance['getTotalResults'] = 17;
        $expectedInstance['getNextLink'] = 'https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users%2Cga:organicSearches&start-date=2015-06-02&end-date=2015-06-02&start-index=11&max-results=10';
        /* Even though the second iteration will have the same columns, we need
        to associate them with different totals for the comparison to work
        right. */
        $expectedInstance['getColumnHeaders'] = new GaDataColumnHeaderCollection(
            APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_COLUMNS, $instance
        );
        $expectedInstance['getRows'] = new GaDataRowCollection(
            APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_2
        );
        $expectedInstance['getTotals'] = array(
            'ga:users' => '83',
            'ga:organicSearches' => '30'
        );
        $expectedInstance['getRows']->setColumnHeaders(
            $expectedInstance['getColumnHeaders']
        );
        foreach ($expectedInstance['getTotals'] as $gaName => $total) {
            $expectedInstance['getColumnHeaders']->getColumn(
                substr($gaName, 3)
            )->setTotal($total);
        }
        $expected[] = $expectedInstance;
        $expectedInstance['getNextLink'] = null;
        $expectedInstance['getPreviousLink'] = 'https://www.googleapis.com/analytics/v3/data/ga?ids=ga:12345&dimensions=ga:source&metrics=ga:users%2Cga:organicSearches&start-date=2015-06-02&end-date=2015-06-02&start-index=1&max-results=10';
        $expectedInstance['getRows'] = new GaDataRowCollection(
            APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_3
        );
        $expectedInstance['getRows']->setColumnHeaders(
            $expectedInstance['getColumnHeaders']
        );
        $expected[] = $expectedInstance;
        /* By the time we hit the third iteration, the response count will
        include the rows from both the second and third iterations, because
        those queries hash the same. */
        $expectedCounts = array(
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_1),
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_2),
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_2) +
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_3)
        );
        $i = 0;
        while ($data = $instance->query($query)) {
            $this->assertEquals(
                $expectedCounts[$i], $instance->getLastFetchedRowCount()
            );
            $this->_runAssertions($expected[$i++], $data);
            $requestURL = new \URL(
                urldecode((string)$instance->getRequest()->getURL())
            );
            $this->assertTrue($requestURL->compare($data->getID()));
        }
        /* Try it again and make sure we get the expected exception on the
        first iteration if we insist on not getting sampled data. */
        $query->reset();
        $query->setSamplingLevel(GaDataQuery::SAMPLING_LEVEL_NONE);
        /* We have to mock up a new instance, but we can leave the caches
        undisturbed. */
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_1,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_2,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_3
            )
        ), false);
        $this->assertThrows(
            __NAMESPACE__ . '\SamplingException',
            array($instance, 'query'),
            array($query)
        );
        $i = 1;
        while ($data = $instance->query($query)) {
            $this->assertEquals(
                $expectedCounts[$i], $instance->getLastFetchedRowCount()
            );
            $this->_runAssertions($expected[$i++], $data);
            $requestURL = new \URL(
                urldecode((string)$instance->getRequest()->getURL())
            );
            $this->assertTrue($requestURL->compare($data->getID()));
        }
        /* Since it's a pain in the ass to mock this up, let's reuse this
        scenario to test what happens when you write a query that involves an
        iteration failure to a file or email. */
        $query->reset();
        // Let's test setting an iterative name in the query while we're at it
        $query->setIterativeName("There's too many bloody irons in the fire");
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_1,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_2,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_3
            )
        ), false);
        $formatter = new ReportFormatter();
        $formatter->openTempFile(GOOGLE_ANALYTICS_API_DATA_DIR);
        $this->assertThrows(
            __NAMESPACE__ . '\FailedIterationsException',
            array($instance, 'queryToFile'),
            array($query, $formatter)
        );
        $this->assertEquals(
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_2) +
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_3),
            $instance->getLastFetchedRowCount()
        );
        $fh = fopen($formatter->getFileName(), 'r');
        $expectedHeaders = array_merge(
            array("There's too many bloody irons in the fire"),
            $expected[0]['getColumnHeaders']->getColumnNames()
        );
        $this->assertEquals($expectedHeaders, fgetcsv($fh));
        // The file should contain data for the second two responses
        $expectedRows = array();
        for ($i = 1; $i < 3; $i++) {
            while ($row = $expected[$i]['getRows']->fetch()) {
                $expectedRows[] = $row;
            }
        }
        $i = 0;
        while ($row = fgetcsv($fh)) {
            /* We should only expect a single date, which is fortunate for
            those of us who are tired of writing unit tests. */
            $this->assertEquals(
                array_merge(array('2015-06-02'), $expectedRows[$i++]), $row
            );
        }
        fclose($fh);
        /* Hang on to the contents of the file so we can use it in the NEXT
        variation on this test. */
        $fileContents = file_get_contents($formatter->getFileName());
        $query->reset();
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_1,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_2,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_3
            )
        ), false);
        $formatter = new ReportFormatter();
        $email = new \Email('your.mom@performics.com');
        $instance->queryToEmail($query, $formatter, $email);
        $this->assertEquals(
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_2) +
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_3),
            $instance->getLastFetchedRowCount()
        );
        $this->assertEquals(
            $query->getEmailSubject(), $email->getSubject()
        );
        $this->assertGreaterThan(
            0, strlen($instance->getFailedIterationsMessage())
        );
        $this->assertContains(
            $instance->getFailedIterationsMessage(),
            $email->getMessage()
        );
        $this->assertEquals(
            $fileContents, $email->getAttachmentContent(0)
        );
        // Run a nested test to confirm the autozip email behavior
        passthru(sprintf(
            'env phpunit --configuration=%s --filter=%s',
            __DIR__ . '/../phpunit_nested.xml',
            'testAutozip'
        ), $returnVal);
        if ($returnVal != 0) {
            $this->fail(
                'Got failure when testing autozip behavior in another process.'
            );
        }
    }
    
    /**
     * Tests the API behavior with a query collection.
     */
    public function testQueryCollection() {
        /* Let's make it a collection where the first query results in a single
        response, the second query results in a paged response, and the third
        is an iterative query...mostly because that lets me reuse my existing
        mock data. */
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                /* Note that in this test, the API will get the first query
                response before asking for the columns. */
                APITestData::$TEST_QUERY_RESPONSE,
                APITestData::$TEST_COLUMNS_RESPONSE,
                APITestData::$TEST_PAGED_QUERY_RESPONSE_1,
                APITestData::$TEST_PAGED_QUERY_RESPONSE_2_FINAL,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_1,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_2,
                APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_3
            )
        ));
        $query = new GaDataQuery();
        $query->setProfile(new ProfileSummary(array(
            'id' => 'ga:12345',
            'name' => 'security Blog',
            'type' => 'WEB'
        )));
        $query->setStartDate("2015-06-01");
        $query->setEndDate("2015-06-02");
        $query->setMetrics(array("users", "organicSearches"));
        $query->setDimensions(array("medium"));
        $query->setName('Basic query');
        $query2 = new GaDataQuery();
        $query2->setProfile(new ProfileSummary(array(
            'id' => 'ga:12345',
            'name' => 'security Blog 2',
            'type' => 'WEB'
        )));
        $query2->setStartDate("2015-06-01");
        $query2->setEndDate("2015-06-02");
        $query2->setMetrics(array("users", "organicSearches"));
        $query2->setDimensions(array('source'));
        $query2->setMaxResults(5);
        // I'm leaving the second query nameless to make sure that works
        $query3 = new DateRangeGaDataQuery();
        // This needs a total of two iterations
        $query3->setSummaryStartDate('2015-06-01');
        $query3->setSummaryEndDate('2015-06-14');
        $query3->setIterationInterval(new \DateInterval('P1W'));
        $query3->setProfile(new ProfileSummary(array(
            'id' => 'ga:12345',
            'name' => 'security Blog 3',
            'type' => 'WEB'
        )));
        $query3->setMetrics(array("users", "organicSearches"));
        $query3->setDimensions(array('source'));
        $query3->setMaxResults(10);
        $query3->setName('An iterative query');
        $collection = new GaDataQueryCollection($query, $query2, $query3);
        $collection->setName('Test collection');
        $formatter = new ReportFormatter();
        $email = new \Email('some.dude@someplace.com');
        // Send it to a file too, just for testing purposes
        $tempFile = tempnam(GOOGLE_ANALYTICS_API_DATA_DIR, 'G');
        $instance->queryToEmail($collection, $formatter, $email, $tempFile);
        /* The last query in the collection pulled two pages of data on its
        final iteration. */
        $this->assertEquals(
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_2) +
            count(APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_3),
            $instance->getLastFetchedRowCount()
        );
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, 'Report 1 of 3' . PHP_EOL);
        fputcsv($fh, array('Description:', 'Basic query'));
        fputcsv($fh, array('Profile:', 'security Blog'));
        fputcsv($fh, array('Start date:', '2015-06-01'));
        fputcsv($fh, array('End date:', '2015-06-02'));
        fwrite($fh, PHP_EOL);
        fputcsv($fh, self::_getColumnNamesFromRawData(
            APITestData::$TEST_QUERY_RESPONSE_COLUMNS
        ));
        foreach (APITestData::$TEST_QUERY_RESPONSE_ROWS as $row) {
            fputcsv($fh, $row);
        }
        fwrite($fh, $formatter->getSeparator() . PHP_EOL);
        fwrite($fh, 'Report 2 of 3' . PHP_EOL);
        fputcsv($fh, array('Description:', ''));
        fputcsv($fh, array('Profile:', 'security Blog 2'));
        fputcsv($fh, array('Start date:', '2015-06-01'));
        fputcsv($fh, array('End date:', '2015-06-02'));
        fwrite($fh, PHP_EOL);
        fputcsv($fh, self::_getColumnNamesFromRawData(
            APITestData::$TEST_PAGED_QUERY_RESPONSE_COLUMNS
        ));
        foreach (APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_1 as $row) {
            fputcsv($fh, $row);
        }
        foreach (APITestData::$TEST_PAGED_QUERY_RESPONSE_ROWS_2 as $row) {
            fputcsv($fh, $row);
        }
        fwrite($fh, $formatter->getSeparator() . PHP_EOL);
        fwrite($fh, 'Report 3 of 3' . PHP_EOL);
        fputcsv($fh, array('Description:', 'An iterative query'));
        fputcsv($fh, array('Profile:', 'security Blog 3'));
        fputcsv($fh, array('Start date:', '2015-06-01'));
        fputcsv($fh, array('End date:', '2015-06-14'));
        fwrite($fh, PHP_EOL);
        // Have to worry about the iterative name with this one
        $columns = self::_getColumnNamesFromRawData(
            APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_COLUMNS
        );
        array_unshift($columns, $query3->getIterativeName());
        fputcsv($fh, $columns);
        foreach (APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_1 as $row) {
            array_unshift($row, '2015-06-01');
            fputcsv($fh, $row);
        }
        foreach (APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_2 as $row) {
            array_unshift($row, '2015-06-08');
            fputcsv($fh, $row);
        }
        foreach (APITestData::$TEST_ITERATIVE_QUERY_RESPONSE_ROWS_3 as $row) {
            array_unshift($row, '2015-06-08');
            fputcsv($fh, $row);
        }
        $expectedContents = '';
        rewind($fh);
        while ($line = fgets($fh)) {
            $expectedContents .= $line;
        }
        fclose($fh);
        $this->assertEquals($expectedContents, file_get_contents($tempFile));
        $this->assertEquals($expectedContents, $email->getAttachmentContent(0));
        $this->assertEquals(
            'Google Analytics report collection "Test collection"',
            $email->getSubject()
        );
    }
}
?>