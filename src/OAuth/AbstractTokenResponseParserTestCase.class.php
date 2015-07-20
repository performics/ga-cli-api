<?php
namespace OAuth;

class TestTokenResponseParser extends AbstractTokenResponseParser {
    /**
     * This method provides the ability to make external modifications to
     * protected properties. The property name should be passed without the
     * leading underscore.
     *
     * @param string $propName
     * @param string $propValue
     */
    public function setProperty($propName, $propValue) {
        $propName = '_' . $propName;
        $r = new \ReflectionClass($this);
        $prop = $r->getProperty($propName);
        if (!$prop->isPublic()) {
            $prop->setAccessible(true);
        }
        $prop->setValue($this, $propValue);
    }
    
    /**
     * @param string $response
     */
    protected function _parseAndSetResponse($response) {
        $this->_responseAsArray = unserialize($response);
    }
}

class AbstractTokenResponseParserTestCase extends \TestHelpers\TestCase {
    /* This test case also covers OAuth\JSONTokenResponseParser, since it is a
    very thin wrapper around the base class. */
    
    private function _runBasicAssertionSet(
        array $assertions,
        AbstractTokenResponseParser $parser
    ) {
        foreach ($assertions as $method => $expected) {
            $this->assertSame($expected, $parser->$method());
        }
    }
    
    public function testGettersWithStandardKeys() {
        $parserTypes = array('TestTokenResponseParser', 'JSONTokenResponseParser');
        $encodingCallbacks = array('serialize', 'json_encode');
        for ($i = 0; $i < count($parserTypes); $i++) {
            $expirationTime = null;
            $token = null;
            $refreshToken = null;
            $tokenType = null;
            $tokenScope = null;
            $errorCode = null;
            $errorDescription = null;
            $response = array('expires_in' => 60);
            $token = $response['access_token'] = 'abcdefg';
            $tokenType = $response['token_type'] = 'foo';
            $expected = array(
                'getExpirationTime' => &$expirationTime,
                'getToken' => &$token,
                'getRefreshToken' => &$refreshToken,
                'getTokenType' => &$tokenType,
                'getTokenScope' => &$tokenScope,
                'getErrorCode' => &$errorCode,
                'getErrorDescription' => &$errorDescription,
                'getResponseAsArray' => &$response
            );
            $parserType = __NAMESPACE__ . '\\' . $parserTypes[$i];
            $parser = new $parserType();
            $parser->setResponse($encodingCallbacks[$i]($response));
            $expirationTime = $parser->getResponseSetTime() + $response['expires_in'];
            $this->_runBasicAssertionSet($expected, $parser);
            $response = array();
            $errorCode = $response['error'] = 'bad_thing_happened';
            $errorDescription = $response['error_description'] = 'A bad thing happened.';
            $expirationTime = null;
            $token = null;
            $refreshToken = null;
            $tokenType = null;
            $tokenScope = null;
            $parser->setResponse($encodingCallbacks[$i]($response));
            $this->_runBasicAssertionSet($expected, $parser);
            $parser->expirationTimeIsRelative = false;
            $response = array();
            $expirationTime = $response['expires_in'] = time() + 20;
            $token = $response['access_token'] = '298badsg';
            $tokenScope = $response['scope'] = 'some_scope';
            $refreshToken = null;
            $tokenType = null;
            $errorCode = null;
            $errorDescription = null;
            $parser->setResponse($encodingCallbacks[$i]($response));
            $this->_runBasicAssertionSet($expected, $parser);
        }
    }
    
    public function testGettersWithCustomKeys() {
        /* Normally a subclass would declare its custom keys in the appropriate
        properties, but we are doing this dynamically. */
        $parser = new TestTokenResponseParser();
        $parser->setProperty('tokenKey', 'my_cool_token');
        $parser->setProperty('expirationKey', 'expire_seconds');
        $parser->setProperty('errorCodeKey', 'error_code');
        // If the response contains the default keys, we won't get anything
        $response = array(
            'access_token' => 'abc123',
            'expires_in' => 120,
            'error' => 'none'
        );
        $parser->setResponse(serialize($response));
        $this->assertNull($parser->getToken());
        $this->assertNull($parser->getExpirationTime());
        $this->assertNull($parser->getErrorCode());
        $response = array(
            'my_cool_token' => 'abc123',
            'expire_seconds' => 120,
            'refresh_token' => 'def456',
            'error_code' => 'none'
        );
        $parser->setResponse(serialize($response));
        $this->assertEquals('abc123', $parser->getToken());
        $this->assertEquals(
            $parser->getResponseSetTime() + 120, $parser->getExpirationTime()
        );
        $this->assertEquals('def456', $parser->getRefreshToken());
        $this->assertEquals('none', $parser->getErrorCode());
    }
}
?>