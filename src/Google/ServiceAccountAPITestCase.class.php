<?php
namespace Google;

define('TEST_OAUTH_TOKEN', 'an OAuth token');

class FakeOAuthService implements \OAuth\IService {
    public function getToken() {
        return TEST_OAUTH_TOKEN;
    }
}

class TestAPI extends ServiceAccountAPI {
	/* This subclass of the real class we're testing provides public hooks for
    the mocking of certain methods. */
	
    protected static function _configureOAuthService() {
		if (!TestAPIRequest::hasOAuthService()) {
			TestAPIRequest::registerOAuthService(new FakeOAuthService());
		}
	}
	
    protected function _executeCurlHandle() {
        return $this->executeCurlHandle();
    }
    
    protected function _getLastHTTPResponse() {
        return $this->getLastHTTPResponse();
    }
    
    /* Prevent things from blowing up because my test objects don't have class
    definitions. */
    protected function _castResponse() {
        return $this->_responseParsed;
    }
    
    public function executeCurlHandle() {
        // To be mocked
    }
    
    public function getLastHTTPResponse() {
        return 200;
    }
    
    /**
     * @param Google\ServiceAccountAPIRequest $request = null
     * @return mixed
     */
    public function makeRequest(ServiceAccountAPIRequest $request = null) {
		return $this->_makeRequest($request);
    }
}

class TestAPIRequest extends ServiceAccountAPIRequest {
	protected static $_oauthService;
}

class ServiceAccountAPITestCase extends \TestHelpers\TestCase {
    /**
     * Returns a stub of the Google\TestAPI class, optionally with some of its
     * methods mocked.
     *
     * @param array $mockMethods = null
     * @return Google\TestAPI
     */
    private function _getStub(array $mockMethods = null) {
        $stub = $this->getMockBuilder(__NAMESPACE__ . '\TestAPI')
                     ->setMethods($mockMethods ? array_keys($mockMethods) : null)
                     ->getMock();
        if ($mockMethods) {
            foreach ($mockMethods as $methodName => $configuration) {
                $stub->method($methodName)->will($configuration);
            }
        }
        return $stub;
    }
    
    /**
     * Tests that the authorization header is included in API calls, as well as
     * any ad-hoc custom headers.
     */
    public function testHeaders() {
        $response = <<<EOF
{
    "foo": "bar"
}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response)
        ));
        $instance->makeRequest(new TestAPIRequest('http://foo.bar/api'));
        $curlOpts = $instance->getCurlOptions();
        $authHeader = 'Authorization: Bearer ' . TEST_OAUTH_TOKEN;
        $this->assertContains($authHeader, $curlOpts[CURLOPT_HTTPHEADER]);
        $extraHeaders = array(
            'Dingus: Drungle',
            'Captain: Beefheart'
        );
		$request = new TestAPIRequest('http://foo.bar/api');
		$request->setHeader($extraHeaders);
        $instance->makeRequest($request);
        $curlOpts = $instance->getCurlOptions();
        $this->assertContains($authHeader, $curlOpts[CURLOPT_HTTPHEADER]);
        foreach ($extraHeaders as $header) {
            $this->assertContains($header, $curlOpts[CURLOPT_HTTPHEADER]);
        }
    }
	
	/**
	 * Confirms that error handling works as expected.
	 *
	 * @expectedException Google\RuntimeException
	 * @expectedExceptionMessage Encountered an error while querying API (response code was 400).
	 */
	public function testErrorHandling() {
		$instance = $this->_getStub(array(
			'getLastHTTPResponse' => $this->returnValue(400)
		));
		$instance->makeRequest(new TestAPIRequest('http://foo.bar/api'));
	}
	
	/**
	 * Confirms that OAuth headers are included once and only once, even if
	 * they are explicitly passed.
	 */
	public function testAuthHeader() {
		$testCallback = function(array $headers) {
			$authHeaderCount = 0;
			foreach ($headers as $header) {
				if (strpos(strtolower($header), 'authorization:') !== false) {
					$authHeaderCount++;
				}
			}
			return $authHeaderCount;
		};
		$request = new TestAPIRequest('http://foo.bar');
		$this->assertEquals(1, $testCallback($request->getHeaders()));
		$request->setHeader('Foo: bar');
		$this->assertEquals(1, $testCallback($request->getHeaders()));
		$request->setHeader('Authorization: foo');
		$this->assertEquals(1, $testCallback($request->getHeaders()));
		$request->setHeader(' authorization: bar');
		$this->assertEquals(1, $testCallback($request->getHeaders()));
		$request->replaceHeader('authorization', 'baz');
		$this->assertEquals(1, $testCallback($request->getHeaders()));
	}
}
?>