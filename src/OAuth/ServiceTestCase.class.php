<?php
namespace OAuth;

// Reuse the test classes from this case
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'GrantedAuthorizationModuleTestCase.class.php');
if ((!defined('TEST_IGNORE_DB') || !TEST_IGNORE_DB) && !defined('OAUTH_DB_DSN')) {
    define('OAUTH_DB_DSN', TEST_DB_DSN);
    define('OAUTH_DB_USER', TEST_DB_USER);
    define('OAUTH_DB_PASSWORD', TEST_DB_PASSWORD);
}

class ServiceTestCase extends \TestHelpers\DatabaseTestCase {
    protected static $_tablesUnderTest = array(
        'oauth_effective_tokens'
    );
    
    /**
     * Returns a stub of the TestGrantedAuthorizationModule class. An array of
     * constructor arguments must be provided, while an associative array of
     * mock method names to configurations may optionally be provided (copied
     * from GrantedAuthorizationModuleTestCase).
     *
     * @param array $constructorArgs
     * @param array $mockMethods = null
     * @return TestGrantedAuthorizationModule
     */
    private function _getAuthModuleStub(
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
        $stub->setResponseHeaders(array(
            'Location: http://www.bar.baz?code=asodifj'
        ));
        return $stub;
    }
    
    /**
     * Executes all the tests in this çase (with the exception of this one)
     * with settings that cause it to ignore the database.
     *
     * @group meta
     */
    public function testWithoutDatabase() {
        passthru(sprintf(
            'env phpunit --configuration=%s',
            __DIR__ . '/phpunit_nodb.xml'
        ), $returnVal);
        if ($returnVal != 0) {
            $this->fail('Got failure when running without database.');
        }
    }
    
    /**
     * Tests that a cached token is or isn't available for use, as appropriate.
     */
    public function testCaching() {
        // Set up a stub that behaves realistically
        $constructorArgs = array(
            new JSONTokenResponseParser(),
            'foo',
            'bar',
            'user x',
            'https://www.foo.bar',
            'https://www.foo.bar',
            'https://www.foo.bar'
        );
        $token = 'a9ds8fbjq308hj3p wna8b6';
        $jsonResponse = sprintf(
            '{"access_token": "%s", "expires_in": 60}', $token
        );
        $authModule = $this->_getAuthModuleStub($constructorArgs, array(
            // This instance may be called to authorize a couple of times
            'getResponseFromCurlHandle' => $this->onConsecutiveCalls(
                'nothing to see here',
                $jsonResponse,
                'nothing to see here',
                $jsonResponse,
                'nothing to see here',
                $jsonResponse
            )
        ));
        $service = new Service($authModule);
        /* Ask for the token without permitting the auth process to take
        place automatically; it should be null whether or not a database is in
        use. */
        $this->assertNull($service->getToken(false));
        // Now allow authorization to happen
        $this->assertEquals($token, $service->getToken());
        /* If we are testing with a database, a new instance with the same
        arguments should return the expected token even if we ask for it
        without allowing authorization to happen automatically. */
        $service = new Service($authModule);
        if (TEST_IGNORE_DB) {
            $this->assertNull($service->getToken(false));
        }
        else {
            $this->assertEquals($token, $service->getToken(false));
        }
        $this->assertEquals($token, $service->getToken());
        /* Changing the user argument should prevent the previous cached value
        from being used. */
        $constructorArgs[3] = 'user y';
        $authModule = $this->_getAuthModuleStub($constructorArgs, array(
            'getResponseFromCurlHandle' => $this->onConsecutiveCalls(
                'nothing to see here',
                $jsonResponse
            )
        ));
        $service = new Service($authModule);
        $this->assertNull($service->getToken(false));
        $this->assertEquals($token, $service->getToken());
        $constructorArgs[3] = 'user z';
        $jsonResponse = sprintf(
            '{"access_token": "%s", "expires_in": 0}', $token
        );
        $authModule = $this->_getAuthModuleStub($constructorArgs, array(
            'getResponseFromCurlHandle' => $this->onConsecutiveCalls(
                'nothing to see here',
                $jsonResponse
            )
        ));
        $service = new Service($authModule);
        /* With an immediate expiration time, we should get the token the first
        time we ask for it, and not again after that (provided we don't allow
        automatic authorization the second time). */
        $this->assertEquals($token, $service->getToken());
        $this->assertNull($service->getToken(false));
        if (!TEST_IGNORE_DB) {
            $jsonResponse = sprintf(
                '{"access_token": "%s", "expires_in": 60}', $token
            );
            $authModule = $this->_getAuthModuleStub($constructorArgs, array(
                'getResponseFromCurlHandle' => $this->onConsecutiveCalls(
                    'nothing to see here',
                    $jsonResponse
                )
            ));
            $service = new Service($authModule);
            $this->assertEquals($token, $service->getToken());
            /* Even though the expiration time is in the future, we can destroy
            the token manually and thus prevent other instances from getting
            it. */
            $service->destroyToken();
            $service = new Service($authModule);
            $this->assertNull($service->getToken(false));
        }
    }
}
?>
