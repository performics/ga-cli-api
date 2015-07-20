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
    protected function _executeCurlHandle() {
        return $this->executeCurlHandle();
    }
    
    protected function _getLastHTTPResponse() {
        return $this->getLastHTTPResponse();
    }
    
    protected function _getOAuthService() {
        return new FakeOAuthService();
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
     * @param string, URL $baseURL
	 * @param array $params = null
     */
    public function prepareRequest($baseURL, array $params = null) {
        $this->_prepareRequest($baseURL, $params);
    }
    
    /**
     * @param string $baseURL = null
	 * @param array $params = null
	 * @param array $postData = null
	 * @param array $extraHeaders = null
     * @return array
     */
    public function makeRequest(
		$baseURL = null,
        array $params = null,
		array $postData = null,
		array $extraHeaders = null
	) {
        return $this->_makeRequest(
            $baseURL, $params, $postData, $extraHeaders
        );
    }
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
        $instance->makeRequest('http://foo.bar/api');
        $curlOpts = $instance->getCurlOptions();
        $authHeader = 'Authorization: Bearer ' . TEST_OAUTH_TOKEN;
        $this->assertContains($authHeader, $curlOpts[CURLOPT_HTTPHEADER]);
        $extraHeaders = array(
            'Dingus: Drungle',
            'Captain: Beefheart'
        );
        $instance->makeRequest(
            'http://foo.bar/api', null, null, $extraHeaders
        );
        $curlOpts = $instance->getCurlOptions();
        $this->assertContains($authHeader, $curlOpts[CURLOPT_HTTPHEADER]);
        foreach ($extraHeaders as $header) {
            $this->assertContains($header, $curlOpts[CURLOPT_HTTPHEADER]);
        }
    }
    
    /**
     * Tests that query string parameters make it to the URL that is used in
     * the request.
     */
    public function testQueryStringParams() {
        $response = <<<EOF
{
    "foo": "bar"
}
EOF;
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->returnValue($response)
        ));
        $params = array(
            'foo' => 'bar',
            'bar' => 'baz'
        );
        $instance->makeRequest('http://foo.bar/api', $params);
        $this->assertEquals(
            $params, $instance->getRequestURL()->getQueryStringData()
        );
        // This should still happen if we pass data to be POSTed
        $params = array(
            'a' => 1,
            'b' => 2,
            'c' => 3
        );
        $postData = array(
            'favorite_actor' => 'dennehy',
            'favorite_drink' => "o'douls",
            'bears' => 'hawks',
            'sox' => 'bulls'
        );
        $instance->makeRequest('http://foo.bar/api', $params, $postData);
        $this->assertEquals(
            $params, $instance->getRequestURL()->getQueryStringData()
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
            array('iteration' => 4, 'uniq' => $this->_generateRandomText(128))
        );
        $instance = $this->_getStub(array(
            'executeCurlHandle' => $this->onConsecutiveCalls(
                json_encode($data[0]),
                json_encode($data[1]),
                json_encode($data[2]),
                json_encode($data[3])
            )
        ));
        $iteration = 0;
        $expectedIterations = 4;
        $instance->prepareRequest('http://foo.bar/api');
        while ($response = $instance->makeRequest()) {
            if ($iteration > 0) {
                $this->assertTrue($instance->getRequestURL()->compare(
                    $data[$iteration - 1]['nextLink']
                ));
            }
            else {
                $this->assertEquals(
                    'http://foo.bar/api', (string)$instance->getRequestURL()
                );
            }
            $this->assertEquals($data[$iteration++], $response);
        }
        $this->assertEquals($expectedIterations, $iteration);
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
		$instance->makeRequest('http://foo.bar/api');
	}
}
?>