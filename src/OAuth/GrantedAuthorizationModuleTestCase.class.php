<?php
namespace OAuth;

class NoOpTokenResponseParser extends AbstractTokenResponseParser {
    protected function _parseAndSetResponse($response) {}
    
    public function getToken() {
        return 'Here is your token, sir or madam.';
    }
}

class TestGrantedAuthorizationModule extends GrantedAuthorizationModule {
    /* This class provides public hooks into some protected methods for testing
    purposes. */
    
    /**
     * @param resource $ch
     * @return string
     */
    protected function _getResponseFromCurlHandle($ch) {
        return $this->getResponseFromCurlHandle();
    }
    
    /**
     * @param resource $ch
     * @return int
     */
    protected function _getResponseCodeFromCurlHandle($ch) {
        return $this->getResponseCodeFromCurlHandle();
    }
    
    /**
     * @return string
     */
    public function getResponseFromCurlHandle() {
        // To be mocked
    }
    
    /**
     * @return int
     */
    public function getResponseCodeFromCurlHandle() {
        // To be mocked
        return 200;
    }
    
    /**
     * Sets an array of HTTP headers that will be parsed by
     * $this->getCodeFromHeaders().
     *
     * @param array $headers
     */
    public function setResponseHeaders(array $headers) {
        $this->_responseHeaders = $headers;
    }
}

class GrantedAuthorizationModuleTestCase extends \TestHelpers\TestCase {
    /**
     * Returns a stub of the TestGrantedAuthorizationModule class. An array of
     * constructor arguments must be provided, while an associative array of
     * mock method names to configurations may optionally be provided.
     *
     * @param array $constructorArgs
     * @param array $mockMethods = null
     * @return TestGrantedAuthorizationModule
     */
    private function _getStub(
        array $constructorArgs,
        array $mockMethods = null
    ) {
        $stub = $this->getMockBuilder(
            __NAMESPACE__ . '\TestGrantedAuthorizationModule'
        )->setConstructorArgs(
            $constructorArgs
        )->setMethods(
            $mockMethods ? array_keys($mockMethods) : null
        )->getMock();
        if ($mockMethods) {
            foreach ($mockMethods as $methodName => $configuration) {
                $stub->method($methodName)->will($configuration);
            }
        }
        return $stub;
    }
    
    /**
     * Tests whether instances having different users, client IDs, client
     * secrets, and/or redirect URLs return different hashes.
     */
    public function testHashing() {
        $parser = new JSONTokenResponseParser();
        $authURL = 'https://www.foo.bar';
        $authzURL = 'https://www.bar.baz';
        $constructorArgSets = array(
            array($parser, 'abc123', 'def456', 'foo', 'https://www.website.com/some-redirect-url', $authURL, $authzURL),
            array($parser, 'abc1234', 'def456', 'foo', 'https://www.website.com/some-redirect-url', $authURL, $authzURL),
            array($parser, 'abc123', 'def4567', 'foo', 'https://www.website.com/some-redirect-url', $authURL, $authzURL),
            array($parser, 'abc123', 'def456', 'bar', 'https://www.website.com/some-redirect-url', $authURL, $authzURL),
            array($parser, 'abc123', 'def456', 'foo', 'https://www.website.com/some-redirect-url/', $authURL, $authzURL),
            array($parser, 'abc1234', 'def4567', 'bar', 'https://www.website.com/some-redirect-url/', $authURL, $authzURL)
        );
        $hashes = array();
        foreach ($constructorArgSets as $argSet) {
            $instance = $this->_getStub($argSet);
            $hashes[] = $instance->getHash();
        }
        $this->assertEquals(array_unique($hashes), $hashes);
        /* But changing the parser instance or type shouldn't cause the hash to
        differ, nor should changing either the authentication or authorization
        URLs. */
        $argSet = $constructorArgSets[0];
        $argSet[0] = new NoOpTokenResponseParser();
        $instance = $this->_getStub($argSet);
        $this->assertEquals($hashes[0], $instance->getHash());
        $argSet[5] = 'https://www.foobar.com/oauth';
        $instance = $this->_getStub($argSet);
        $this->assertEquals($hashes[0], $instance->getHash());
        $argSet[6] = 'https://www.foobar.com/oauth';
        $instance = $this->_getStub($argSet);
        $this->assertEquals($hashes[0], $instance->getHash());
    }
    
    /**
     * Tests a successful authentication process, in which we make a request to
     * the authorization endpoint and receive a redirection URL containing the
     * authorization code as a query string parameter.
     */
    public function testSuccessfulAuthentication() {
        $authResponseContent = <<<EOF
<!DOCTYPE html>
<html>
<head></head>
<body>Whatever.</body>
</html>
EOF;
        $instance = $this->_getStub(
            array(
                new JSONTokenResponseParser(),
                'abc123',
                'def456',
                'foo',
                'https://www.website.com/some-redirect-url',
                'https://www.foobar.com/oauth',
                'https://www.foobar.com/oauth'
            ),
            array(
                'getResponseFromCurlHandle' => $this->returnValue($authResponseContent),
                'getResponseCodeFromCurlHandle' => $this->returnValue(302)
            )
        );
        $expectedCode = '0q3t8h0q3r8nh90aer';
        // Mock up the kind of headers we might expect to get
        $instance->setResponseHeaders(array(
            'HTTP/1.1 302 Found',
            'Server: nginx',
            'Location: https://www.website.com/some-redirect-url?code=' . $expectedCode . '#_=_',
            'Set-Cookie: oauth_token=asdfasdfasdf; Expires=Sat, 19 Dec 2015 18:49:44 GMT; Path=/; Secure',
            'X-Frame-Options: SAMEORIGIN',
            'X-XSS-Protection: 1; mode=block',
            'X-Content-Type-Options: nosniff',
            'Pragma: no-cache',
            'Strict-Transport-Security: max-age=31536000',
            'X-ex: fastly_cdn',
            'Transfer-Encoding: chunked',
            'Accept-Ranges: bytes',
            'Date: Mon, 22 Jun 2015 18:49:44 GMT',
            'via: 1.1 varnish',
            'Connection: keep-alive',
            'X-Served-By: cache-ord1722-ORD',
            'X-Cache: MISS',
            'X-Cache-Hits: 0',
            'Vary: Accept-Encoding,User-Agent,Accept-Language'
        ));
        $this->assertEquals($expectedCode, $instance->requestAuthentication());
        $this->assertEquals(
            $authResponseContent, $instance->getAuthenticationRequestBody()
        );
    }
    
    /**
     * Ensures the proper exception is thrown when the authentication code is
     * not returned.
     */
    public function testFailedAuthentication() {
        $instance = $this->_getStub(
            array(
                new JSONTokenResponseParser(),
                'abc123',
                'def456',
                'foo',
                'https://www.website.com/some-redirect-url',
                'https://www.foobar.com/oauth',
                'https://www.foobar.com/oauth'
            ),
            array(
                'getResponseFromCurlHandle' => $this->returnValue(''),
                'getResponseCodeFromCurlHandle' => $this->returnValue(302)
            )
        );
        $code = 'asdfasdfasdf';
        // Try just changing the query string key
        $headers = array(
            'HTTP/1.1 302 Found',
            'Server: nginx',
            'Location: https://www.website.com/some-redirect-url?auth_code=' . $code . '#_=_',
            'Set-Cookie: oauth_token=asdfasdfasdf; Expires=Sat, 19 Dec 2015 18:49:44 GMT; Path=/; Secure',
            'X-Frame-Options: SAMEORIGIN',
            'X-XSS-Protection: 1; mode=block',
            'X-Content-Type-Options: nosniff',
            'Pragma: no-cache',
            'Strict-Transport-Security: max-age=31536000',
            'X-ex: fastly_cdn',
            'Transfer-Encoding: chunked',
            'Accept-Ranges: bytes',
            'Date: Mon, 22 Jun 2015 18:49:44 GMT',
            'via: 1.1 varnish',
            'Connection: keep-alive',
            'X-Served-By: cache-ord1722-ORD',
            'X-Cache: MISS',
            'X-Cache-Hits: 0',
            'Vary: Accept-Encoding,User-Agent,Accept-Language'
        );
        $instance->setResponseHeaders($headers);
        $this->assertThrows(
            __NAMESPACE__ . '\AuthenticationException',
            array($instance, 'requestAuthentication')
        );
        // This should fail in the same way
        $this->assertThrows(
            __NAMESPACE__ . '\AuthenticationException',
            array($instance, 'authorize')
        );
        // This should fix it
        $headers[2] = str_replace('auth_code', 'code', $headers[2]);
        $instance->setResponseHeaders($headers);
        $this->assertEquals($code, $instance->requestAuthentication());
        // Remove the Location header entirely
        $instance->setResponseHeaders(array(
            'HTTP/1.1 302 Found',
            'Server: nginx',
            'Set-Cookie: oauth_token=asdfasdfasdf; Expires=Sat, 19 Dec 2015 18:49:44 GMT; Path=/; Secure',
            'X-Frame-Options: SAMEORIGIN',
            'X-XSS-Protection: 1; mode=block',
            'X-Content-Type-Options: nosniff',
            'Pragma: no-cache',
            'Strict-Transport-Security: max-age=31536000',
            'X-ex: fastly_cdn',
            'Transfer-Encoding: chunked',
            'Accept-Ranges: bytes',
            'Date: Mon, 22 Jun 2015 18:49:44 GMT',
            'via: 1.1 varnish',
            'Connection: keep-alive',
            'X-Served-By: cache-ord1722-ORD',
            'X-Cache: MISS',
            'X-Cache-Hits: 0',
            'Vary: Accept-Encoding,User-Agent,Accept-Language'
        ));
        $this->assertThrows(
            __NAMESPACE__ . '\AuthenticationException',
            array($instance, 'requestAuthentication')
        );
        $this->assertThrows(
            __NAMESPACE__ . '\AuthenticationException',
            array($instance, 'authorize')
        );
    }
    
    /**
     * Tests a successful authorization (assuming we have the authentication
     * code already).
     */
    public function testSuccessfulAuthorization() {
        $token = 'aosidfjoaisdgj -aw';
        $response = sprintf('{"access_token": "%s"}', $token);
        $instance = $this->_getStub(
            array(
                new JSONTokenResponseParser(),
                'abc123',
                'def456',
                'foo',
                'https://www.website.com/some-redirect-url',
                'https://www.foobar.com/oauth',
                'https://www.foobar.com/oauth'
            ),
            array(
                'getResponseFromCurlHandle' => $this->returnValue($response)
            )
        );
        $instance->requestAuthorization('abc123');
        $this->assertEquals($token, $instance->getParser()->getToken());
    }
    
    /**
     * Ensures the proper exception is thrown when an error takes place
     * during the authorization phase.
     */
    public function testFailedAuthorization() {
        /* An error may occur because the endpoint returns something other than
        200 or because the response content omits the token. */
        $response = '{"access_token": "98j3098aehrb98aeh"}';
        $instance = $this->_getStub(
            array(
                new JSONTokenResponseParser(),
                'abc123',
                'def456',
                'foo',
                'https://www.website.com/some-redirect-url',
                'https://www.foobar.com/oauth',
                'https://www.foobar.com/oauth'
            ),
            array(
                'getResponseFromCurlHandle' => $this->returnValue($response),
                'getResponseCodeFromCurlHandle' => $this->returnValue(403)
            )
        );
        $this->assertThrows(
            __NAMESPACE__ . '\AuthorizationException',
            array($instance, 'requestAuthorization'),
            array('oaisjd')
        );
        $response = str_replace('access_token', '_access_token', $response);
        $instance = $this->_getStub(
            array(
                new JSONTokenResponseParser(),
                'abc123',
                'def456',
                'foo',
                'https://www.website.com/some-redirect-url',
                'https://www.foobar.com/oauth',
                'https://www.foobar.com/oauth'
            ),
            array(
                'getResponseFromCurlHandle' => $this->returnValue($response),
            )
        );
        $this->assertThrows(
            __NAMESPACE__ . '\AuthorizationException',
            array($instance, 'requestAuthorization'),
            array('oaisjd')
        );
    }
    
    /**
     * Tests the full authorization process. This more or less combines the
     * tests from $this->testSuccessfulAuthentication() and
     * $this->testSuccessfulAuthorization().
     */
    public function testFullAuthorizationProcess() {
        $authResponseContent = <<<EOF
<!DOCTYPE html>
<html>
<head></head>
<body>Whatever.</body>
</html>
EOF;
        $token = '98j3098aehrb98aeh';
        $authzResponseContent = sprintf('{"access_token": "%s"}', $token);
        $instance = $this->_getStub(
            array(
                new JSONTokenResponseParser(),
                'abc123',
                'def456',
                'foo',
                'https://www.website.com/some-redirect-url',
                'https://www.foobar.com/oauth',
                'https://www.foobar.com/oauth'
            ),
            /* We don't mock up the returning of the HTTP codes because that
            value is not tested during the authentication. */
            array(
                'getResponseFromCurlHandle' => $this->onConsecutiveCalls(
                    $authResponseContent, $authzResponseContent
                )
            )
        );
        $instance->setResponseHeaders(array(
            'HTTP/1.1 302 Found',
            'Server: nginx',
            'Location: https://www.website.com/some-redirect-url?code=aera89athi#_=_',
            'Set-Cookie: oauth_token=asdfasdfasdf; Expires=Sat, 19 Dec 2015 18:49:44 GMT; Path=/; Secure',
            'X-Frame-Options: SAMEORIGIN',
            'X-XSS-Protection: 1; mode=block',
            'X-Content-Type-Options: nosniff',
            'Pragma: no-cache',
            'Strict-Transport-Security: max-age=31536000',
            'X-ex: fastly_cdn',
            'Transfer-Encoding: chunked',
            'Accept-Ranges: bytes',
            'Date: Mon, 22 Jun 2015 18:49:44 GMT',
            'via: 1.1 varnish',
            'Connection: keep-alive',
            'X-Served-By: cache-ord1722-ORD',
            'X-Cache: MISS',
            'X-Cache-Hits: 0',
            'Vary: Accept-Encoding,User-Agent,Accept-Language'
        ));
        $instance->authorize();
        $this->assertEquals($token, $instance->getParser()->getToken());
    }
}
?>