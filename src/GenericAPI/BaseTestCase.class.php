<?php
namespace GenericAPI;

class TestAPIException extends \RuntimeException {}
class MutableAPIInterface extends Base {
    /* This is a concrete implementation of GenericAPI\Base that provides
    certain functionality for testing purposes. */
    protected function _executeCurlHandle() {
        return $this->executeCurlHandle();
    }
    
    protected function _getLastHTTPResponse() {
        $code = $this->getLastHTTPResponse();
        /* If I don't cache the result of this call in a property, it screws up
        the tests where I rely on setting up consecutive return values. */
        $this->_responseCode = $code;
        return $code;
    }
    
    /**
     * Provides public access to self::_registerSSLCertificate() for testing
     * purposes.
     *
     * @param string $certFile
     * @param string $host = '*'
     */
    public static function registerSSLCertificate($certFile, $host = '*') {
        self::_registerSSLCertificate($certFile, $host);
    }
    
    protected function _handleError() {
        if ($this->_responseCode != 200) {
            throw new TestAPIException(
                'Got a response code of ' . $this->_responseCode . '.'
            );
        }
    }
    
    /**
     * Provides a hook to mock this method in tests.
     *
     * @return string
     */
    public function executeCurlHandle() {
        return 'OK';
    }
    
    /**
     * Provides a hook to mock this method in tests.
     *
     * @return int
     */
    public function getLastHTTPResponse() {
        return 200;
    }
    
    /**
     * Sets the value of a protected property for the purpose of testing
     * various modes of operation. The property name should be passed without
     * the leading underscore.
     *
     * @param string $propName
     * @param mixed $propValue
     */
    public function setProperty($propName, $propValue) {
        $propName = '_' . $propName;
        $r = new \ReflectionClass($this);
        $prop = $r->getProperty($propName);
        if (!$prop->isPublic()) {
            $prop->setAccessible(true);
        }
        if ($prop->isStatic()) {
            $prop->setValue($propValue);
        }
        else {
            $prop->setValue($this, $propValue);
        }
    }
    
    /**
     * Provides public access to $this->_registerParseCallback() for testing
     * purposes.
     *
     * @param callable $callback
     * @param array $extraParams
     */
    public function registerParseCallback($callback, array $extraParams = null)
    {
        $this->_registerParseCallback($callback, $extraParams);
    }
    
    /**
     * Provides public access to $this->_getResponse() for testing purposes.
     *
     * @param GenericAPI\Request $request
     * @param boolean $parse = true
     */
    public function callGetResponse(Request $request, $parse = true) {
        $this->_getResponse($request, $parse);
    }
    
    /**
     * Provides a getter for the self::$_archiveCount property.
     *
     * @return int
     */
    public function getArchiveCount() {
        return $this->_archiveCount;
    }
}

class BlockingAPIInterface extends MutableAPIInterface {
    // This class assists with the testing of blocking actions
    protected $_requestDelayInterval = 2000;
}

class BaseTestCase extends \TestHelpers\TempFileTestCase {    
    /**
     * Returns a stub of the MutableAPIInterface class. The first argument, if
     * provided, should be an associative array of property names (without the
     * leading underscore) to values. The second argument, if provided, should
     * be an associative array of method names to stubbed method behavior
     * instructions (e.g. $this->returnValue()).
     *
     * @param array $propertyOverrides = null
     * @param array $mockMethods = null
     * @return GenericAPI\MutableAPIInterface
     */
    private function _getStub(
        array $propertyOverrides = null,
        array $mockMethods = null
    ) {
        $stub = $this->getMockBuilder(__NAMESPACE__ . '\MutableAPIInterface')
                     ->setMethods($mockMethods ? array_keys($mockMethods) : null)
                     ->getMock();
        if ($mockMethods) {
            foreach ($mockMethods as $methodName => $configuration) {
                $stub->method($methodName)->will($configuration);
            }
        }
        if ($propertyOverrides) {
            foreach ($propertyOverrides as $propName => $propVal) {
                $stub->setProperty($propName, $propVal);
            }
        }
        return $stub;
    }
    
    /**
     * Verifies that the appropriate HTTP verb is used in the appropriate
     * situation.
     */
    public function testHTTPVerb() {
        $instance = $this->_getStub();
        $instance->callGetResponse(new Request('http://127.0.0.1/'), false);
        $curlOptions = $instance->getCurlOptions();
        $this->assertEquals('GET', $curlOptions[CURLOPT_CUSTOMREQUEST]);
        /* The use of CURLOPT_POST in this framework should be deprecated by
        now, so I'm going to keep the assertions that it not be present in the
        cURL options. */
        $this->assertArrayNotHasKey(CURLOPT_POST, $curlOptions);
        $this->assertEquals(
            (string)$instance->getRequest()->getURL(), 'http://127.0.0.1/'
        );
        // Setting a payload in the request implicitly triggers a POST
        $params = array('foo' => 'bar');
        $request = new Request('http://127.0.0.1/');
        $request->setPayload($params);
        $instance->callGetResponse($request, false);
        $curlOptions = $instance->getCurlOptions();
        $this->assertEquals('POST', $curlOptions[CURLOPT_CUSTOMREQUEST]);
        $this->assertArrayNotHasKey(CURLOPT_POST, $curlOptions);
        $this->assertEquals($params, $curlOptions[CURLOPT_POSTFIELDS]);
        $this->assertEquals(
            'http://127.0.0.1/', (string)$instance->getRequest()->getURL()
        );
        /* Parameters specified in the request constructor shouldn't be used as
        POST parameters even if the verb is set to POST. */
        $request = new Request('http://127.0.0.1/', $params);
        $request->setVerb('POST');
        $instance->callGetResponse($request, false);
        $curlOptions = $instance->getCurlOptions();
        $this->assertEquals('POST', $curlOptions[CURLOPT_CUSTOMREQUEST]);
        $this->assertArrayNotHasKey(CURLOPT_POST, $curlOptions);
        $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, $curlOptions);
        $this->assertEquals(
            'http://127.0.0.1/?foo=bar',
            (string)$instance->getRequest()->getURL()
        );
        // Now try a custom verb
        $request = new Request('http://127.0.0.1/', $params);
        $request->setVerb('FROBNICATE');
        $instance->callGetResponse($request, false);
        $curlOptions = $instance->getCurlOptions();
        $this->assertArrayNotHasKey(CURLOPT_POST, $curlOptions);
        $this->assertEquals('FROBNICATE', $curlOptions[CURLOPT_CUSTOMREQUEST]);
        $this->assertEquals(
            'http://127.0.0.1/?foo=bar',
            (string)$instance->getRequest()->getURL()
        );
    }
    
    /**
     * Verifies that the appropriate SSL certificate file is used to make
     * the appropriate connection.
     */
    public function testSSLCertUsage() {
        $fakeCertFiles = array(
            '127.0.0.1' => self::_createTempFile(),
            '127.0.0.2' => self::_createTempFile(),
            '*' => self::_createTempFile()
        );
        $instance = $this->_getStub();
        $r = new \ReflectionClass($instance);
        $method = $r->getMethod('registerSSLCertificate');
        foreach ($fakeCertFiles as $host => $file) {
            $method->invoke(null, $file, $host);
        }
        $instance->callGetResponse(
            new Request('http://127.0.0.1/foo/'), false
        );
        $curlOptions = $instance->getCurlOptions();
        $this->assertArrayNotHasKey(CURLOPT_CAINFO, $curlOptions);
        $instance->callGetResponse(
            new Request('https://127.0.0.1/foo/'), false
        );
        $curlOptions = $instance->getCurlOptions();
        $this->assertEquals(
            $fakeCertFiles['127.0.0.1'], $curlOptions[CURLOPT_CAINFO]
        );
        $instance->callGetResponse(
            new Request('https://127.0.0.2/foo/', array('foo' => 'bar')),
            false
        );
        $curlOptions = $instance->getCurlOptions();
        $this->assertEquals(
            $fakeCertFiles['127.0.0.2'], $curlOptions[CURLOPT_CAINFO]
        );
        $instance->callGetResponse(
            new Request('https://127.0.0.3/foo/', array('foo' => 'bar')),
            false
        );
        $curlOptions = $instance->getCurlOptions();
        $this->assertEquals(
            $fakeCertFiles['*'], $curlOptions[CURLOPT_CAINFO]
        );
    }
    
    /**
     * Tests the parsing of responses according to preset and custom callbacks.
     */
    public function testResponseParsing() {
        $expectedResponse = array(
            'response' => array(
                'foo' => array('', 1, 'b'),
                'bar' => array('knock-knock' => 'who\'s there'),
                'baz' => '927381.29'
            )
        );
        $jsonResponse = <<<EOF
{
    "response": {
        "foo": ["", 1, "b"],
        "bar": {
            "knock-knock": "who's there"
        },
        "baz": "927381.29"
    }
}
EOF;
        $xmlResponse = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<response>
    <foo></foo>
    <foo>1</foo>
    <foo>b</foo>
    <bar>
        <knock-knock>who's there</knock-knock>
    </bar>
    <baz>927381.29</baz>
</response>
EOF;
        $instance = $this->_getStub(
            array('responseFormat' => Base::RESPONSE_FORMAT_JSON),
            array('executeCurlHandle' => $this->returnValue($jsonResponse))
        );
        $instance->callGetResponse(new Request('http://127.0.0.1'));
        $this->assertEquals($expectedResponse, $instance->getResponse());
        $instance = $this->_getStub(
            array('responseFormat' => Base::RESPONSE_FORMAT_XML),
            array('executeCurlHandle' => $this->returnValue($xmlResponse))
        );
        $instance->callGetResponse(new Request('http://127.0.0.1'));
        $this->assertEquals($expectedResponse, $instance->getResponse());
        /* Create a special parsing callback that is based on decoding XML, but
        turns empty strings into nulls (or whatever argument is passed). This
        is basically just a tweaked version of what PFXUtils::xmlToArray()
        does. */
        $getNodeValue = function($xml, $emptyStringReplacement = null) use (&$getNodeValue) {
            if ($xml->count()) {
                $val = array();
                foreach ($xml->children() as $child) {
                    $childName = $child->getName();
                    $childVal = $getNodeValue($child, $emptyStringReplacement);
                    /* This got me--I'm initializing a key with a null value
                    (instead of the empty string you would normally get with
                    XML), and I was originally using isset() here. That was
                    preventing the null value from showing up in the array that
                    I ultimately expected to have, because the array key was
                    set, but its value was null. */
                    if (array_key_exists($childName, $val)) {
                        /* If there is already a value at this node name, turn it
                        into a numerically-indexed array. */
                        if (!is_array($val[$childName]) ||
                            !array_key_exists(0, $val[$childName]))
                        {
                            $val[$childName] = array($val[$childName]);
                        }
                        $val[$childName][] = $childVal;
                    }
                    else {
                        $val[$childName] = $childVal;
                    }
                }
                return $val;
            }
            else {
                // Here's the tweak
                $val = trim((string)$xml);
                if (strlen($val)) {
                    return $val;
                }
                else {
                    return $emptyStringReplacement;
                }
            }
        };
        $parseCallback = function($xml, $emptyStringReplacement = null) use ($getNodeValue) {
            if (is_string($xml)) {
                $xml = new \SimpleXMLElement($xml);
            }
            return array(
                $xml->getName() => $getNodeValue($xml, $emptyStringReplacement)
            );
        };
        $instance = $this->_getStub(
            null,
            array('executeCurlHandle' => $this->returnValue($xmlResponse))
        );
        $instance->registerParseCallback($parseCallback);
        $instance->callGetResponse(new Request('http://127.0.0.1'));
        $response = $instance->getResponse();
        $this->assertEquals($expectedResponse, $response);
        $this->assertNotSame(
            $response['response']['foo'][0],
            $expectedResponse['response']['foo'][0]
        );
        $expectedResponse['response']['foo'][0] = null;
        $this->assertSame(
            $response['response']['foo'][0],
            $expectedResponse['response']['foo'][0]
        );
        // Now try it with something other than null as the replacement value
        $instance->registerParseCallback($parseCallback, array('dingus'));
        $instance->callGetResponse(new Request('http://127.0.0.1'));
        $response = $instance->getResponse();
        $this->assertNotEquals($expectedResponse, $response);
        $expectedResponse['response']['foo'][0] = 'dingus';
        $this->assertEquals($expectedResponse, $response);
    }
    
    /**
     * Tests that the proper delay takes place in between successive attempts
     * to make the same API call.
     */
    public function testRepeatDelay() {
        // A call that is immediately successful should not result in a delay
        $instance = $this->_getStub();
        $startTime = microtime(true);
        $instance->callGetResponse(new Request('http://127.0.0.1'), false);
        $baselineDiff = microtime(true) - $startTime;
        $expectedInterval = 2;
        $instance = $this->_getStub(
            array('responseTries' => 2, 'repeatPauseInterval' => $expectedInterval),
            array('getLastHTTPResponse' => $this->onConsecutiveCalls(500, 200))
        );
        $startTime = microtime(true);
        $instance->callGetResponse(new Request('http://127.0.0.1'), false);
        $elapsed = microtime(true) - $startTime;
        $this->assertEquals($expectedInterval, round($elapsed - $baselineDiff));
        $instance = $this->_getStub(
            array('responseTries' => 3, 'repeatPauseInterval' => $expectedInterval),
            array('getLastHTTPResponse' => $this->onConsecutiveCalls(500, 500, 200))
        );
        $startTime = microtime(true);
        $instance->callGetResponse(new Request('http://127.0.0.1'), false);
        $elapsed = microtime(true) - $startTime;
        $this->assertEquals($expectedInterval * 2, round($elapsed - $baselineDiff));
        /* Now what about specifying a hard minimum delay after each successful
        call? */
        $delayMillisecs = 500;
        $instance = $this->_getStub(
            array('requestDelayInterval' => $delayMillisecs)
        );
        $request = new Request('http://127.0.0.1');
        $startTime = microtime(true);
        $instance->callGetResponse($request, false);
        $instance->callGetResponse($request, false);
        $elapsed = microtime(true) - $startTime;
        $this->assertGreaterThanOrEqual($delayMillisecs / 1000, $elapsed);
        // Turn off the delay and make sure it goes away
        $instance = $this->_getStub();
        $startTime = microtime(true);
        $instance->callGetResponse($request, false);
        $instance->callGetResponse($request, false);
        $elapsed = microtime(true) - $startTime;
        $this->assertLessThan($delayMillisecs / 1000, $elapsed);
    }
    
    /**
     * Tests that the property that controls whether a response array is
     * guaranteed works as expected.
     */
    public function testResponseArrayGuarantee() {
        $instance = $this->_getStub(
            array(
                'guaranteeResponseArray' => true,
                'responseFormat' => Base::RESPONSE_FORMAT_JSON
            ),
            array('executeCurlHandle' => $this->returnValue(null))
        );
        $request = new Request('http://127.0.0.1');
        $request->expectResponseLength(false);
        $instance->callGetResponse($request);
        $this->assertSame($instance->getResponse(), array());
        $instance->setProperty('guaranteeResponseArray', false);
        $instance->callGetResponse($request);
        $this->assertSame($instance->getResponse(), null);
    }
    
    /**
     * Confirms that by default, an exception is thrown if a zero-byte response
     * comes back.
     *
     * @expectedException GenericAPI\ResponseValidationException
     */
    public function testResponseLengthGuarantee() {
        $instance = $this->_getStub(
            array('responseFormat' => Base::RESPONSE_FORMAT_JSON),
            array('executeCurlHandle' => $this->returnValue(null))
        );
        $instance->callGetResponse(new Request('http://127.0.0.1'));
    }
    
    /**
     * Confirms that responses are passed along to the request object for
     * validation.
     */
    public function testResponsePrototypeGuarantee() {
        $proto = array(
            'response' => array(
                'foo' => null,
                'bar' => array('knock-knock' => null),
                'baz' => null
            )
        );
        $jsonResponse = <<<EOF
{
    "response": {
        "foo": ["", 1, "b"],
        "bar": {
            "knock-knock": "who's there"
        },
        "baz": "927381.29"
    }
}
EOF;
        $stubProperties = array(
            'responseFormat' => Base::RESPONSE_FORMAT_JSON
        );
        $instance = $this->_getStub(
            $stubProperties,
            array('executeCurlHandle' => $this->returnValue($jsonResponse))
        );
        $request = new Request('http://127.0.0.1');
        $request->setResponseValidator($proto);
        $request->setResponseValidationMethod(Request::VALIDATE_PROTOTYPE);
        $instance->callGetResponse($request);
        /* Adding an additional element to the response that is not found in
        the prototype doesn't throw an exception. */
        $jsonResponse = <<<EOF
{
    "response": {
        "foo": ["", 1, "b"],
        "bar": {
            "knock-knock": "who's there"
        },
        "baz": "927381.29",
        "bort": {
            "eenie": "meenie",
            "minie": "moe"
        }
    }
}
EOF;
        $instance = $this->_getStub(
            $stubProperties,
            array('executeCurlHandle' => $this->returnValue($jsonResponse))
        );
        $instance->callGetResponse($request);
        // But adding an extra requirement to the prototype does
        $proto['baz'] = null;
        $request->setResponseValidator($proto);
        $this->assertThrows(
            'RuntimeException',
            array($instance, 'callGetResponse'),
            array($request)
        );
    }
    
    /**
     * Tests whether it is possible to customize what happens based on a
     * specific HTTP response code.
     */
    public function testHTTPResponseActionMap() {
        /* Ordinarily, any responses in the 400 range cause the request to
        break, while most responses in the 500 range cause the request to be
        repeated. */
        $instance = $this->_getStub(
            array('responseTries' => 1, 'repeatPauseInterval' => 0),
            array('getLastHTTPResponse' => $this->onConsecutiveCalls(
                200, 404, 400, 500
            ))
        );
        $request = new Request('http://127.0.0.1/');
        $instance->callGetResponse($request, false);
        for ($i = 0; $i < 3; $i++) {
            $this->assertThrows(
                __NAMESPACE__ . '\TestAPIException',
                array($instance, 'callGetResponse'),
                array($request, false)
            );
        }
        $instance = $this->_getStub(
            array(
                'httpResponseActionMap' => array(
                    404 => Base::ACTION_REPEAT_REQUEST,
                    400 => Base::ACTION_SUCCESS,
                    500 => Base::ACTION_BREAK_REQUEST
                ),
                'responseTries' => 3,
                'repeatPauseInterval' => 0
            ),
            /* This is a little tricky because we need to tailor the chain of
            return values to the amount of repetitions. */
            array(
                'getLastHTTPResponse' => $this->onConsecutiveCalls(
                    // First call, returns after one attempt
                    200,
                    // Second call, will contain three attempts
                    404,
                    404,
                    200,
                    // Third call, successful after one attempt
                    400,
                    // Fourth call, dies after one attempt
                    500
                )
            )
        );
        $instance->callGetResponse($request, false);
        $this->assertEquals(1, $instance->getAttemptCount());
        $instance->callGetResponse($request, false);
        $this->assertEquals(3, $instance->getAttemptCount());
        $this->assertThrows(
            __NAMESPACE__ . '\TestAPIException',
            array($instance, 'callGetResponse'),
            array($request, false)
        );
        $this->assertEquals(1, $instance->getAttemptCount());
        $this->assertThrows(
            __NAMESPACE__ . '\TestAPIException',
            array($instance, 'callGetResponse'),
            array($request, false)
        );
        $this->assertEquals(1, $instance->getAttemptCount());
    }
    
    /**
     * Validates that the methods that parse the HTTP response headers to
     * return them in various useful formats work as expected.
     */
    public function testResponseHeaderParsing() {
        $responseHeaderLines = array(
            'HTTP/1.1 200 OK',
            'Expires: Thu, 28 May 2015 21:31:43 GMT',
            'Date: Thu, 28 May 2015 21:31:43 GMT',
            'Cache-Control: private, max-age=0, must-revalidate, no-transform',
            'ETag: "o-85COrcxoYkAw5itMLG4AKNpMY/2aqXzdfGLTlj_eybvPxwiDCkkIs"',
            'Vary: Origin',
            'Vary: X-Origin',
            'Content-Type: application/json; charset=UTF-8',
            'X-Content-Type-Options: nosniff',
            'X-Frame-Options: SAMEORIGIN',
            'X-XSS-Protection: 1; mode=block',
            'Content-Length: 139069',
            'Server: GSE',
            'Alternate-Protocol: 443:quic,p=1'
        );
        // The associative version won't include the first line
        $responseHeaderAssoc = array(
            'Expires' => 'Thu, 28 May 2015 21:31:43 GMT',
            'Date' => 'Thu, 28 May 2015 21:31:43 GMT',
            'Cache-Control' => 'private, max-age=0, must-revalidate, no-transform',
            'ETag' => '"o-85COrcxoYkAw5itMLG4AKNpMY/2aqXzdfGLTlj_eybvPxwiDCkkIs"',
            'Vary' => array('Origin', 'X-Origin'),
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Content-Length' => '139069',
            'Server' => 'GSE',
            'Alternate-Protocol' => '443:quic,p=1'
        );
        /* Raw HTTP response headers may have Windows-style newlines, so
        simulate that for the test. */
        $responseHeader = implode("\r\n", $responseHeaderLines);
        $instance = $this->_getStub(
            array('responseHeader' => $responseHeader)
        );
        $this->assertEquals(
            $responseHeaderLines, $instance->getResponseHeaderAsArray()
        );
        $this->assertEquals(
            $responseHeaderAssoc, $instance->getResponseHeaderAsAssociativeArray()
        );
    }
    
    /**
     * This isn't really a test; this is just here so that I can call it in a
     * separate process in testInterProcessBlocking().
     *
     * @group meta
     */
    public function testCallInSeparateProcess() {
        $instance = new BlockingAPIInterface();
        $instance->callGetResponse(new Request('http://127.0.0.1/'), false);
    }
    
    /**
     * Tests whether this process respects blocking put in place by a separate
     * process making a call to the same API.
     *
     * @group interProcess
     */
    public function testInterProcessBlocking() {
        $bootstrapFile = realpath(__DIR__ . '/../classLoader.inc.php');
        $this->assertNotFalse($bootstrapFile);
        $instance = new BlockingAPIInterface();
        // This subclass is configured to block for two seconds
        $request = new Request('http://127.0.0.1/');
        $startTime = time();
        $cmd = 'env phpunit --no-configuration --bootstrap=' . $bootstrapFile
             . ' --filter=testCallInSeparateProcess ' . __FILE__
             . ' > /dev/null';
        pclose(popen($cmd, 'r'));
        $instance->callGetResponse($request, false);
        $this->assertGreaterThanOrEqual(2, time() - $startTime);
        // We should be able to make calls to other APIs as we please
        $instance = $this->_getStub(array(
            'requestDelayInterval' => 500
        ));
        pclose(popen($cmd, 'r'));
        $startTime = time();
        $instance->callGetResponse($request, false);
        $this->assertLessThanOrEqual(1, time() - $startTime);
    }
    
    /**
     * Tests whether custom headers are used when included in the API call.
     */
    public function testCustomRequestHeaders() {
        $instance = $this->_getStub();
        $customHeaders = array(
            'Foo: Bar',
            'Dingus: Drungle'
        );
        $request = new Request('http://127.0.0.1/');
        foreach ($customHeaders as $header) {
            $request->setHeader($header);
        }
        $instance->callGetResponse($request, false);
        $curlOpts = $instance->getCurlOptions();
        $this->assertEquals($customHeaders, $curlOpts[CURLOPT_HTTPHEADER]);
    }
    
    /**
     * Tests the automatic storage of raw response data in an archive file.
     */
    public function testFileStorage() {
        $json1 = '{"foo": "bar"}';
        $json2 = <<<EOF
{
    "foo": "bar",
    "baz": ["a", "b", "c"]
    "bytes": 
EOF;
        $json2 .= '"' . addslashes(openssl_random_pseudo_bytes(2048)) . '"}';
        $eol = "\n";
        $tempFileName = self::_createTempFile();
        $tempFile = fopen($tempFileName, 'a');
        $instance = $this->_getStub(
            array(
                'transferFileName' => $tempFileName,
                'transferFile' => $tempFile,
                'transferEOL' => $eol,
                'responseFormat' => Base::RESPONSE_FORMAT_JSON
            ),
            array(
                'executeCurlHandle' => $this->onConsecutiveCalls($json1, $json2)
            )
        );
        $request = new Request('http://127.0.0.1/');
        $instance->callGetResponse($request);
        $instance->callGetResponse($request);
        $expectedContent = $json1 . $eol . $json2 . $eol;
        $this->assertEquals(
            $expectedContent, file_get_contents($tempFileName)
        );
        // Change up the EOL style and try from another instance
        $eol = "==;\n";
        $instance = $this->_getStub(
            array(
                'transferFileName' => $tempFileName,
                'transferFile' => $tempFile,
                'transferEOL' => $eol,
                'responseFormat' => Base::RESPONSE_FORMAT_JSON
            ),
            array(
                'executeCurlHandle' => $this->onConsecutiveCalls($json1, $json2)
            )
        );
        $instance->callGetResponse($request);
        $instance->callGetResponse($request);
        $expectedContent .= $json1 . $eol . $json2 . $eol;
        $this->assertEquals(
            $expectedContent, file_get_contents($tempFileName)
        );
        // Test the auto-incrementing of the stored response count property
        $instance = $this->_getStub(
            array(
                'transferFileName' => $tempFileName,
                'transferFile' => $tempFile,
                'archiveCount' => 0,
                'responseFormat' => Base::RESPONSE_FORMAT_JSON
            ),
            array(
                'executeCurlHandle' => $this->onConsecutiveCalls($json1, $json2)
            )
        );
        $instance->callGetResponse($request);
        $this->assertEquals(1, $instance->getArchiveCount());
        $instance->callGetResponse($request);
        $this->assertEquals(2, $instance->getArchiveCount());
    }
}
?>