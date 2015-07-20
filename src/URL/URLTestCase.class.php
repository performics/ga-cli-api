<?php
// Make sure we don't interfere with any real suffix list caches
define('SUFFIX_LIST_CACHE_FILE', 'suffixlist_cache_test.txt');

class URLTestCase extends TestHelpers\TestCase {
    private function _runEqualityAssertions(
        array $assertions,
        URL $url,
        array $argAssertions = null
    ) {
        foreach ($assertions as $method => $expected) {
            $failureMessage = 'Assertion failed for method URL::' . $method . '().';
            if ($expected === null) {
                $this->assertNull($url->$method(), $failureMessage);
            }
            else {
                $this->assertEquals($expected, $url->$method(), $failureMessage);
            }
        }
        if (!$argAssertions) {
            return;
        }
        foreach ($argAssertions as $method => $assertionList) {
            $failureMessage = 'Assertion failed for method URL::' . $method . '().';
            foreach ($assertionList as $assertionData) {
                if ($assertionData[0] === null) {
                    $this->assertNull(
                        call_user_func_array(array($url, $method), $assertionData[1]),
                        $failureMessage
                    );
                }
                else {
                    $this->assertEquals(
                        $assertionData[0],
                        call_user_func_array(array($url, $method), $assertionData[1]),
                        $failureMessage
                    );
                }
            }
        }
    }
    
    /**
     * Tests the basic URL parsing behavior.
     */
    public function testBasicParsing() {
        $urlStr = 'http://www.some-website.com/some-path?favorite_color=blue&favorite_food=pizza';
        $url = new URL($urlStr);
        /* I'm packaging up as many of these assertions as is convenient into
        an associative array so I can change little things as I go and just
        iterate through them. */
        $equalityAssertions = array(
            '__toString' => $urlStr,
            'getRawURL' => $urlStr,
            'getScheme' => 'http',
            'getHost' => 'www.some-website.com',
            'getFullHost' => 'www.some-website.com',
            'getSubdomain' => 'www',
            'getDomain' => 'some-website',
            'getTLD' => 'com',
            'getPort' => null,
            'getPath' => '/some-path',
            'getPathBaseName' => 'some-path',
            'getFullPath' => '/some-path?favorite_color=blue&favorite_food=pizza',
            'getHashFragment' => null,
            'getQueryString' => 'favorite_color=blue&favorite_food=pizza',
            'getQueryStringData' => array(
                'favorite_color' => 'blue', 'favorite_food' => 'pizza'
            )
        );
        $equalityAssertionArgs = array(
            'getQueryStringParam' => array(
                array('blue', array('favorite_color')),
                array('pizza', array('favorite_food'))
            )
        );
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $this->assertFalse($url->hostIsIP());
        // Add a port to the host
        $port = ':8080';
        $urlStr = str_replace(
            $equalityAssertions['getHost'],
            $equalityAssertions['getHost'] . $port,
            $urlStr
        );
        $equalityAssertions['__toString'] = $equalityAssertions['getRawURL'] = $urlStr;
        $equalityAssertions['getFullHost'] .= $port;
        $equalityAssertions['getPort'] = '8080';
        $url = new URL($urlStr);
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $this->assertFalse($url->hostIsIP());
        // Throw a hash fragment into the mix
        $equalityAssertions['getHashFragment'] = $hashFragment = '#some-hash-fragment';
        $equalityAssertions['getFullPath'] .= $hashFragment;
        $urlStr .= $hashFragment;
        $equalityAssertions['__toString'] = $equalityAssertions['getRawURL'] = $urlStr;
        $url = new URL($urlStr);
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $this->assertFalse($url->hostIsIP());
        // How about another scheme
        $scheme = 'my-special-scheme';
        $urlStr = str_replace('http', $scheme, $urlStr);
        $equalityAssertions['__toString'] = $equalityAssertions['getRawURL'] = $urlStr;
        $equalityAssertions['getScheme'] = $scheme;
        $url = new URL($urlStr);
        $this->_runEqualityAssertions($equalityAssertions, $url);
        // How about an IP address instead of a named host
        $host = '127.0.0.1';
        $urlStr = str_replace('www.some-website.com', $host, $urlStr);
        $equalityAssertions['__toString'] = $equalityAssertions['getRawURL'] = $urlStr;
        $equalityAssertions['getHost'] = $host;
        $equalityAssertions['getFullHost'] = str_replace(
            'www.some-website.com', $host, $equalityAssertions['getFullHost']
        );
        $equalityAssertions['getSubdomain'] = null;
        $equalityAssertions['getDomain'] = null;
        $equalityAssertions['getTLD'] = null;
        $url = new URL($urlStr);
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $this->assertTrue($url->hostIsIP());
        // Get rid of the port
        $urlStr = str_replace($port, '', $urlStr);
        $equalityAssertions['__toString'] = $equalityAssertions['getRawURL'] = $urlStr;
        $equalityAssertions['getPort'] = null;
        $equalityAssertions['getFullHost'] = str_replace(
            $port, '', $equalityAssertions['getFullHost']
        );
        // Add another component to the path, this time with a trailing slash
        $path = '/some-path/some-node/';
        $urlStr = str_replace('/some-path', $path, $urlStr);
        $equalityAssertions['__toString'] = $equalityAssertions['getRawURL'] = $urlStr;
        $equalityAssertions['getPath'] = str_replace(
            '/some-path', $path, $equalityAssertions['getPath']
        );
        $equalityAssertions['getFullPath'] = str_replace(
            '/some-path', $path, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getPathBaseName'] = 'some-node/';
        $url = new URL($urlStr);
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        // How about with no query string
        $queryString = '?' . $equalityAssertions['getQueryString'];
        $urlStr = str_replace($queryString, '', $urlStr);
        $equalityAssertions['__toString'] = $equalityAssertions['getRawURL'] = $urlStr;
        $equalityAssertions['getFullPath'] = str_replace(
            $queryString, '', $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getQueryString'] = null;
        $equalityAssertions['getQueryStringData'] = array();
        $url = new URL($urlStr);
        $this->_runEqualityAssertions($equalityAssertions, $url);
        $this->assertNull($url->getQueryString());
        // Eliminate the hash fragment too
        $hashFragment = $equalityAssertions['getHashFragment'];
        $urlStr = str_replace($hashFragment, '', $urlStr);
        $equalityAssertions['__toString'] = $equalityAssertions['getRawURL'] = $urlStr;
        $equalityAssertions['getFullPath'] = str_replace(
            $hashFragment, '', $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getHashFragment'] = null;
        $url = new URL($urlStr);
        $this->_runEqualityAssertions($equalityAssertions, $url);
        // Leading/trailing whitespace shouldn't affect anything
        $urlStr = '   ' . $urlStr . ' ';
        $equalityAssertions['getRawURL'] = $urlStr;
        $url = new URL($urlStr);
        $this->_runEqualityAssertions($equalityAssertions, $url);
        /* We can instantiate the class with no argument or an explicit null
        argument. */
        $url = new URL();
        $url = new URL(null);
        /* But not with an empty string or anything that gets trimmed down to
        an empty string. */
        $this->assertThrows(
            'InvalidArgumentException',
            function() { new URL(''); }
        );
        $this->assertThrows(
            'InvalidArgumentException',
            function() { new URL('      '); }
        );
    }
    
    /**
     * Tests the ability to use the various setter methods to alter specific
     * parts of a URL.
     */
    public function testMutability() {
        $url = new URL('http://www.foo.co.uk/baz');
        $urlStr = 'https://www.baz.com.au/?a=b';
        $url->setURL($urlStr);
        // The raw URL will stay the same as we modify these attributes
        $equalityAssertions = array(
            '__toString' => &$urlStr,
            'getRawURL' => $urlStr,
            'getScheme' => 'https',
            'getHost' => 'www.baz.com.au',
            'getFullHost' => 'www.baz.com.au',
            'getSubdomain' => 'www',
            'getDomain' => 'baz',
            'getTLD' => 'com.au',
            'getPort' => null,
            'getPath' => '/',
            'getPathBaseName' => '',
            'getFullPath' => '/?a=b',
            'getHashFragment' => null,
            'getQueryString' => 'a=b',
            'getQueryStringData' => array('a' => 'b')
        );
        $equalityAssertionArgs = array(
            'getQueryStringParam' => array(
                array('b', array('a'))
            )
        );
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $url->setScheme('http');
        $urlStr = str_replace('https', 'http', $urlStr);
        $equalityAssertions['getScheme'] = 'http';
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $host = 'baz.bar.foo.co';
        $oldHost = $equalityAssertions['getHost'];
        $url->setHost($host);
        $urlStr = str_replace($oldHost, $host, $urlStr);
        $equalityAssertions['getHost'] = $host;
        $equalityAssertions['getFullHost'] = str_replace(
            $oldHost, $host, $equalityAssertions['getFullHost']
        );
        $equalityAssertions['getSubdomain'] = 'baz.bar';
        $equalityAssertions['getDomain'] = 'foo';
        $equalityAssertions['getTLD'] = 'co';
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $port = '12345';
        $url->setPort($port);
        $urlStr = str_replace($host, $host . ':' . $port, $urlStr);
        $equalityAssertions['getPort'] = $port;
        $equalityAssertions['getFullHost'] .= ':' . $port;
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $subdomain = 'the-black-page';
        $url->setSubdomain($subdomain);
        $oldSubdomain = $equalityAssertions['getSubdomain'];
        $urlStr = str_replace($oldSubdomain, $subdomain, $urlStr);
        $equalityAssertions['getHost'] = str_replace(
            $oldSubdomain, $subdomain, $equalityAssertions['getHost']
        );
        $equalityAssertions['getFullHost'] = str_replace(
            $oldSubdomain, $subdomain, $equalityAssertions['getFullHost']
        );
        $equalityAssertions['getSubdomain'] = $subdomain;
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $domain = 'zappa';
        $url->setDomain($domain);
        $oldDomain = $equalityAssertions['getDomain'];
        $urlStr = str_replace($oldDomain, $domain, $urlStr);
        $equalityAssertions['getHost'] = str_replace(
            $oldDomain, $domain, $equalityAssertions['getHost']
        );
        $equalityAssertions['getFullHost'] = str_replace(
            $oldDomain, $domain, $equalityAssertions['getFullHost']
        );
        $equalityAssertions['getDomain'] = $domain;
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $tld = 'k12.ia.us';
        $url->setTLD($tld);
        $oldTLD = $equalityAssertions['getTLD'];
        $urlStr = str_replace($oldTLD, $tld, $urlStr);
        $equalityAssertions['getHost'] = str_replace(
            $oldTLD, $tld, $equalityAssertions['getHost']
        );
        $equalityAssertions['getFullHost'] = str_replace(
            $oldTLD, $tld, $equalityAssertions['getFullHost']
        );
        $equalityAssertions['getTLD'] = $tld;
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $path = '/a-cool-page';
        $url->setPath($path);
        $oldPath = $equalityAssertions['getPath'];
        $urlStr = substr($urlStr, 0, 8) . str_replace(
            $oldPath, $path, substr($urlStr, 8)
        );
        $equalityAssertions['getPath'] = $path;
        $equalityAssertions['getFullPath'] = str_replace(
            $oldPath, $path, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getPathBaseName'] = ltrim($path, '/');
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        // URL::setPath() should add a leading slash for us if one is missing
        $oldPath = $equalityAssertions['getPath'];
        $path = 'a-mildly-cool-page';
        $url->setPath($path);
        $urlStr = str_replace($oldPath, '/' . $path, $urlStr);
        $equalityAssertions['getPath'] = '/' . $path;
        $equalityAssertions['getFullPath'] = str_replace(
            $oldPath, '/' . $path, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getPathBaseName'] = $path;
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        /* URL::setPathBaseName() can do several different things depending on
        the URL's existing path and the arguments with which it is called. */
        $pathBaseName = 'a-super-cool-page/';
        $url->setPathBaseName($pathBaseName);
        $oldPath = $equalityAssertions['getPath'];
        $urlStr = str_replace($oldPath, '/' . $pathBaseName, $urlStr);
        $equalityAssertions['getPath'] = '/' . $pathBaseName;
        $equalityAssertions['getFullPath'] = str_replace(
            $oldPath, '/' . $pathBaseName, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getPathBaseName'] = $pathBaseName;
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        /* When the existing path has a trailing slash, we can choose to have
        URL::setPathBaseName() replace the portion between the last two
        trailing slashes or append the argument as a new node. */
        $oldPathContent = rtrim($pathBaseName, '/');
        $pathBaseName = 'a-super-duper-cool-page';
        $url->setPathBaseName($pathBaseName, false);
        $urlStr = str_replace($oldPathContent, $pathBaseName, $urlStr);
        $equalityAssertions['getPath'] = str_replace(
            $oldPathContent, $pathBaseName, $equalityAssertions['getPath']
        );
        $equalityAssertions['getFullPath'] = str_replace(
            $oldPathContent, $pathBaseName, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getPathBaseName'] = $pathBaseName . '/';
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $pathBaseName = 'node';
        $url->setPathBaseName($pathBaseName);
        $oldPath = $equalityAssertions['getPath'];
        $urlStr = str_replace($oldPath, $oldPath . $pathBaseName, $urlStr);
        $equalityAssertions['getPath'] = str_replace(
            $oldPath, $oldPath . $pathBaseName, $equalityAssertions['getPath']
        );
        $equalityAssertions['getFullPath'] = str_replace(
            $oldPath, $oldPath . $pathBaseName, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getPathBaseName'] = $pathBaseName;
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        // URL::setPathBaseName() removes leading slashes if present
        $pathBaseName = 'Another-Node';
        $oldPathBaseName = $equalityAssertions['getPathBaseName'];
        $url->setPathBaseName('/' . $pathBaseName);
        $urlStr = str_replace($oldPathBaseName, $pathBaseName, $urlStr);
        $equalityAssertions['getPath'] = str_replace(
            $oldPathBaseName, $pathBaseName, $equalityAssertions['getPath']
        );
        $equalityAssertions['getFullPath'] = str_replace(
            $oldPathBaseName, $pathBaseName, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getPathBaseName'] = $pathBaseName;
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $hashFragment = '#asdf';
        $url->setHashFragment($hashFragment);
        $urlStr .= $hashFragment;
        $equalityAssertions['getHashFragment'] = $hashFragment;
        $equalityAssertions['getFullPath'] .= $hashFragment;
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        // URL::setHashFragment() should add a leading pound sign where missing
        $hashFragment = 'JKLM';
        $url->setHashFragment($hashFragment);
        $oldHashFragment = $equalityAssertions['getHashFragment'];
        $urlStr = str_replace($oldHashFragment, '#' . $hashFragment, $urlStr);
        $equalityAssertions['getHashFragment'] = '#' . $hashFragment;
        $equalityAssertions['getFullPath'] = str_replace(
            $oldHashFragment, '#' . $hashFragment, $equalityAssertions['getFullPath']
        );
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $queryString = 'foo=bar';
        $oldQueryString = $equalityAssertions['getQueryString'];
        $url->setQueryString($queryString);
        $urlStr = str_replace($oldQueryString, $queryString, $urlStr);
        $equalityAssertions['getQueryString'] = $queryString;
        $equalityAssertions['getFullPath'] = str_replace(
            $oldQueryString, $queryString, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getQueryStringData'] = array('foo' => 'bar');
        $equalityAssertionArgs['getQueryStringParam'] = array(
            array('bar', array('foo'))
        );
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        // URL::setQueryString() should remove leading question marks
        $queryString = '?bar=baz&zoo&flab=flam';
        $oldQueryString = $equalityAssertions['getQueryString'];
        $url->setQueryString($queryString);
        $urlStr = str_replace($oldQueryString, ltrim($queryString, '?'), $urlStr);
        $equalityAssertions['getQueryString'] = ltrim($queryString, '?');
        $equalityAssertions['getFullPath'] = str_replace(
            '?' . $oldQueryString, $queryString, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getQueryStringData'] = array(
            'bar' => 'baz', 'zoo' => '', 'flab' => 'flam'
        );
        $equalityAssertionArgs['getQueryStringParam'] = array(
            array('baz', array('bar')),
            array('', array('zoo')),
            array('flam', array('flab'))
        );
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        /* We can call URL::setQueryStringParam() with a single key and value
        as two arguments, or an associative array as a single argument. */
        $oldQueryStringContent = 'bar=baz';
        $newQueryStringContent = 'bar=foo';
        $url->setQueryStringParam('bar', 'foo');
        $urlStr = str_replace($oldQueryStringContent, $newQueryStringContent, $urlStr);
        $equalityAssertions['getQueryString'] = str_replace(
            $oldQueryStringContent, $newQueryStringContent, $equalityAssertions['getQueryString']
        );
        $equalityAssertions['getFullPath'] = str_replace(
            $oldQueryStringContent, $newQueryStringContent, $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getQueryStringData']['bar'] = 'foo';
        $equalityAssertionArgs['getQueryStringParam'][0][0] = 'foo';
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $newQueryStringContent = '&today=tuesday';
        $url->setQueryStringParam('today', 'tuesday');
        $urlStr = str_replace(
            $equalityAssertions['getQueryString'],
            $equalityAssertions['getQueryString'] . $newQueryStringContent,
            $urlStr
        );
        $equalityAssertions['getFullPath'] = str_replace(
            $equalityAssertions['getQueryString'],
            $equalityAssertions['getQueryString'] . $newQueryStringContent,
            $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getQueryString'] .= $newQueryStringContent;
        $equalityAssertions['getQueryStringData']['today'] = 'tuesday';
        $equalityAssertionArgs['getQueryStringParam'][] = array('tuesday', array('today'));
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $oldQueryStringContent = 'bar=foo';
        $replacedQueryStringContent = 'bar=12345';
        $newQueryStringContent = '&tomorrow=Wednesday';
        $url->setQueryStringParam(array('bar' => 12345, 'tomorrow' => 'Wednesday'));
        $urlStr = str_replace(
            array($oldQueryStringContent, $equalityAssertions['getHashFragment']),
            array($replacedQueryStringContent, $newQueryStringContent . $equalityAssertions['getHashFragment']),
            $urlStr
        );
        $equalityAssertions['getFullPath'] = str_replace(
            array($oldQueryStringContent, $equalityAssertions['getHashFragment']),
            array($replacedQueryStringContent, $newQueryStringContent . $equalityAssertions['getHashFragment']),
            $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getQueryString'] = str_replace(
            $oldQueryStringContent,
            $replacedQueryStringContent,
            $equalityAssertions['getQueryString']
        ) . $newQueryStringContent;
        $equalityAssertions['getQueryStringData']['bar'] = 12345;
        $equalityAssertions['getQueryStringData']['tomorrow'] = 'Wednesday';
        $equalityAssertionArgs['getQueryStringParam'][0][0] = 12345;
        $equalityAssertionArgs['getQueryStringParam'][] = array('Wednesday', array('tomorrow'));
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $removedQueryStringContent = 'bar=12345&';
        $url->unsetQueryStringParam('bar');
        $urlStr = str_replace($removedQueryStringContent, '', $urlStr);
        $equalityAssertions['getFullPath'] = str_replace(
            $removedQueryStringContent, '', $equalityAssertions['getFullPath']
        );
        $equalityAssertions['getQueryString'] = str_replace(
            $removedQueryStringContent, '', $equalityAssertions['getQueryString']
        );
        unset($equalityAssertions['getQueryStringData']['bar']);
        array_shift($equalityAssertionArgs['getQueryStringParam']);
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $argSeparator = '$asdf;';
        $url->setQSArgSeparator($argSeparator);
        /* Setting the argument separator changes how the current query string
        is understood, but doesn't modify it to use that argument separator. */
        $pos = strpos($equalityAssertions['getQueryString'], '=');
        $key = substr($equalityAssertions['getQueryString'], 0, $pos);
        $savedQueryStringDataAssertions = $equalityAssertions['getQueryStringData'];
        $equalityAssertions['getQueryStringData'] = array(
            $key => substr($equalityAssertions['getQueryString'], $pos + 1)
        );
        $this->_runEqualityAssertions($equalityAssertions, $url, array(
            'getQueryStringParam' => array(
                array($equalityAssertions['getQueryStringData'][$key], array($key))
            )
        ));
        /* If we perform the replacement to introduce the new argument
        separator, it should behave just like the ampersand does. */
        $equalityAssertions['getQueryString'] = str_replace(
            '&', $argSeparator, $equalityAssertions['getQueryString']
        );
        $url->setQueryString($equalityAssertions['getQueryString']);
        $equalityAssertions['getQueryStringData'] = $savedQueryStringDataAssertions;
        $urlStr = str_replace('&', $argSeparator, $urlStr);
        $equalityAssertions['getFullPath'] = str_replace(
            '&', $argSeparator, $equalityAssertions['getFullPath']
        );
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        $url->setLowerCase();
        $urlStr = strtolower($urlStr);
        foreach ($equalityAssertions as &$val) {
            if (is_string($val)) {
                $val = strtolower($val);
            }
            elseif (is_array($val)) {
                $keysToRemove = array();
                foreach ($val as $key => &$nestedVal) {
                    if (is_string($nestedVal)) {
                        $nestedVal = strtolower($nestedVal);
                    }
                    if (preg_match('/[A-Z]/', $key)) {
                        $keysToRemove[] = $key;
                        $val[strtolower($key)] = $nestedVal;
                    }
                }
                foreach ($keysToRemove as $key) {
                    unset($val[$key]);
                }
            }
        }
        foreach ($equalityAssertionArgs as &$assertionList) {
            foreach ($assertionList as &$assertion) {
                if (is_string($assertion[0])) {
                    $assertion[0] = strtolower($assertion[0]);
                }
                foreach ($assertion[1] as &$arg) {
                    if (is_string($arg)) {
                        $arg = strtolower($arg);
                    }
                }
            }
        }
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        /* We can't set a URL to null, an empty string, or a string that gets
        trimmed down to empty. */
        $emptyValues = array(null, '', '     ');
        foreach ($emptyValues as $empty) {
            $this->assertThrows(
                'InvalidArgumentException',
                array($url, 'setURL'),
                array($empty)
            );
        }
    }
    
    /**
     * Verifies that the automatic URL encoding behavior in URL::setURL()
     * doesn't mangle anything it shouldn't.
     */
    public function testURLEncoding() {
        $urlStr = 'http://www.foo.com/~some_url-with%permissible.(characters):$;?foo=%20+bar&baz\\#asdf';
        $url = new URL($urlStr);
        $this->assertEquals($urlStr, (string)$url);
        $urlStr = 'http://www.foo.com/?a[]=foo&b[]=bar baz&sadf={}';
        $url = new URL($urlStr);
        $encodableCharacters = array('[', ']', ' ', '{', '}');
        $encodedCharacters = array();
        foreach ($encodableCharacters as $char) {
            $encodedCharacters[] = urlencode($char);
        }
        $urlStr = str_replace(
            $encodableCharacters, $encodedCharacters, $urlStr
        );
        $this->assertEquals($urlStr, (string)$url);
    }
    
    /**
     * Tests the decoding of query strings.
     */
    public function testQueryStringParsing() {
        /* I'm packaging these assertions in an array so that I can try the
        same ones with different argument separators. */
        $assertions = array(
            'foo=bar' => array('foo' => 'bar'),
            'foo=bar&bar=baz' => array('foo' => 'bar', 'bar' => 'baz'),
            // Keys with no values should be allowed
            'foo=bar&bar=baz&barf' => array('foo' => 'bar', 'bar' => 'baz', 'barf' => ''),
            'foo=bar&bar=baz&barf=' => array('foo' => 'bar', 'bar' => 'baz', 'barf' => ''),
            // Keys and values should be URL decoded
            'what%27s=up&oh%2c=Nothing%20much.' => array(
                "what's" => 'up', 'oh,' => 'Nothing much.'
            ),
            /* Multiple instances of the same key should be put into an array,
            whether they use PHP's bracket syntax or not. */
            'foo=bar&foo=baz&something=else' => array(
                'foo' => array('bar', 'baz'),
                'something' => 'else'
            ),
            // Mixing of the standard and the bracket syntax is allowed
            'foo=bar&foo[]=barf&foo=baz&something=else' => array(
                'foo' => array('bar', 'barf', 'baz'),
                'something' => 'else'
            ),
            // In any order
            'foo[]=bar&foo=barf&foo=baz&something=else' => array(
                'foo' => array('bar', 'barf', 'baz'),
                'something' => 'else'
            ),
            'foo=bar&foo=barf&foo[]=baz&something=else' => array(
                'foo' => array('bar', 'barf', 'baz'),
                'something' => 'else'
            ),
            // Even if it is URL-encoded
            'foo=bar&foo=baz&foo%5b%5d=barf&something=else' => array(
                'foo' => array('bar', 'baz', 'barf'),
                'something' => 'else'
            ),
            'foo%5b%5d=bar&foo=baz&foo=barf&something=else' => array(
                'foo' => array('bar', 'baz', 'barf'),
                'something' => 'else'
            ),
            'foo=bar&foo%5b%5d=baz&foo=barf&something=else' => array(
                'foo' => array('bar', 'baz', 'barf'),
                'something' => 'else'
            ),
            /* Characters that PHP would normally convert to underscores are
            preserved, even open square brackets not paired with a closing
            bracket. */
            'performics.com=website&foo.bar&barack obama=president&as]fdio=bkm8y92' => array(
                'performics.com' => 'website',
                'foo.bar' => '',
                'barack obama' => 'president',
                'as]fdio' => 'bkm8y92'
            ),
            // Explicit array indexing is supported
            'foo=bar&foo[]=baz&foo.bar[a b c][827]=asdf' => array(
                'foo' => array('bar', 'baz'),
                'foo.bar' => array('a b c' => array(827 => 'asdf'))
            )
        );
        foreach ($assertions as $queryString => $expected) {
            $this->assertEquals(
                $expected,
                URL::parseQueryString($queryString),
                'Failed successful parse of query string "' . $queryString . '".'
            );
        }
        // Try it with different separators (single and multiple characters)
        $separators = array(':', '$', ':asdf$');
        foreach ($separators as $separator) {
            foreach ($assertions as $queryString => $expected) {
                $queryString = str_replace('&', $separator, $queryString);
                $this->assertEquals(
                    $expected,
                    URL::parseQueryString($queryString, $separator)
                );
            }
        }
        /* Make sure that when using a non-standard separator, ampersands in
        the data don't cause problems. */
        $this->assertEquals(
            array(
                'foo' => array('bar', 1),
                'bar&baz' => 'barf&blarg'
            ),
            URL::parseQueryString(
                'foo=bar&amp;bar&baz=barf&blarg&amp;foo=1', '&amp;'
            )
        );
        $this->assertThrows(
            'URLInvalidArgumentException',
            array('URL', 'parseQueryString'),
            array('foo=bar&amp;bar&baz=barf&blarg&amp;foo=1', '')
        );
    }
    
    /**
     * Tests URL comparison.
     */
    public function testComparison() {
        $baseURL = new URL('http://www.website-a.com');
        $compURL = new URL('http://website-b.com');
        /* For each comparison, we should get the same result whether we pass
        a URL object or a string. */
        $this->assertFalse($baseURL->compare($compURL));
        $this->assertFalse($baseURL->compare((string)$compURL));
        $baseURL = new URL('http://www.foo.com');
        // This will default to http
        $compURL = new URL('www.foo.com');
        // These URLs are string-equivalent
        $this->assertTrue($baseURL->compare($compURL));
        $this->assertTrue($baseURL->compare((string)$compURL));
        $compURL->setPath('/asdf');
        // Oops, not anymore
        $this->assertFalse($baseURL->compare($compURL));
        $this->assertFalse($baseURL->compare((string)$compURL));
        $compURL = new URL('http://foo.com');
        // 'www' is assumed when the subdomain is absent
        $this->assertTrue($baseURL->compare($compURL));
        $this->assertTrue($baseURL->compare((string)$compURL));
        $baseURL->setSubdomain('foo');
        $this->assertFalse($baseURL->compare($compURL));
        $this->assertFalse($baseURL->compare((string)$compURL));
        $baseURL = new URL('www.foo.com/some_page/#foo');
        $compURL = new URL('www.foo.com/some_page/#bar');
        $this->assertTrue($baseURL->compare($compURL));
        $this->assertTrue($baseURL->compare((string)$compURL));
        $baseURL = new URL('www.foo.com/some_page?my=dog&has=fleas');
        // Order doesn't matter for query string comparison
        $compURL = new URL('www.foo.com/some_page?has=fleas&my=dog');
        $this->assertTrue($baseURL->compare($compURL));
        $this->assertTrue($baseURL->compare((string)$compURL));
        /* The comparison URL is allowed to have other query string parameters,
        and the comparison can still succeed (although you could make an
        argument for why this is wrong). */
        $compURL->setQueryStringParam('foo', 'bar');
        $this->assertTrue($baseURL->compare($compURL));
        $this->assertTrue($baseURL->compare((string)$compURL));
        // But extra parameters in the base URL cause the comparison to fail
        $baseURL->setQueryStringParam('too', 'bad');
        $this->assertFalse($baseURL->compare($compURL));
        $this->assertFalse($baseURL->compare((string)$compURL));
        $baseURL->unsetQueryStringParam('too');
        // Naturally, differing values for corresponding keys will cause this
        $compURL->setQueryStringParam('has', 'stock_options');
        $this->assertFalse($baseURL->compare($compURL));
        $this->assertFalse($baseURL->compare((string)$compURL));
        // Schemes are allowed to differ if one is http and the other is https
        $baseURL = new URL('http://some-website.com/foo/');
        $compURL = new URL('https://some-website.com/foo/');
        $this->assertTrue($baseURL->compare($compURL));
        $this->assertTrue($baseURL->compare((string)$compURL));
        $baseURL->setScheme('ftp');
        $this->assertFalse($baseURL->compare($compURL));
        $this->assertFalse($baseURL->compare((string)$compURL));
    }
    
    /**
     * Tests the comparison of domains between URL instances.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDomainComparison() {
        /* Part of the point of this test is to make sure that it's possible to
        make certain comparisons without resorting to TLD parsing, so we will
        start by clearing the cache and then making sure that it is not
        populated until it should be. */
        URL::clearSuffixListCache();
        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . SUFFIX_LIST_CACHE_FILE;
        $baseURL = new URL('www.foo.com');
        $compURL = new URL('www.foo.com');
        $this->assertTrue($baseURL->compareRootDomain($compURL));
        $this->assertTrue($baseURL->compareRootDomain((string)$compURL));
        $this->assertFalse(file_exists($cacheFile));
        // Differing paths don't matter
        $compURL->setPath('/asdf/asdf/asdf/');
        $this->assertTrue($baseURL->compareRootDomain($compURL));
        $this->assertTrue($baseURL->compareRootDomain((string)$compURL));
        $this->assertFalse(file_exists($cacheFile));
        /* Neither do differing subdomains (note that I am tailoring these
        values to not trigger TLD parsing). */
        $baseURL->setHost('foo.com');
        $compURL->setHost('some-new-subdomain.foo.com');
        $this->assertTrue($baseURL->compareRootDomain($compURL));
        $this->assertTrue($baseURL->compareRootDomain((string)$compURL));
        $this->assertFalse(file_exists($cacheFile));
        // Unless, of course, we ask for subdomain matching
        $this->assertFalse($baseURL->compareRootDomain($compURL, true));
        $this->assertFalse($baseURL->compareRootDomain((string)$compURL, true));
        $this->assertFalse(file_exists($cacheFile));
        /* Two hosts that are not string-equivalent and have the same string
        length must be compared via TLD parsing, unless we specifically ask
        for subdomain matching.. */
        $baseURL = new URL('foo.bar.com/asdf');
        $compURL = new URL('baz.bar.com/soadfjoaisdf');
        $this->assertFalse($baseURL->compareRootDomain($compURL, true));
        $this->assertFalse($baseURL->compareRootDomain((string)$compURL, true));
        $this->assertFalse(file_exists($cacheFile));
        $this->assertTrue($baseURL->compareRootDomain($compURL));
        $this->assertTrue($baseURL->compareRootDomain((string)$compURL));
        $this->assertTrue(file_exists($cacheFile));
        /* Differing domains will cause the comparison to fail, of course. Note
        that this test will always trigger TLD parsing, because the code needs
        to check whether the mismatch is one that is permissible. */
        $compURL->setHost('barr.com');
        $this->assertFalse($baseURL->compareRootDomain($compURL));
        $this->assertFalse($baseURL->compareRootDomain((string)$compURL));
        /* We shouldn't get an exception if the comparison object is invalid
        as a URL. */
        $this->assertFalse($baseURL->compareRootDomain(
            "Some crazy crap that doesn't work as a URL."
        ));
    }
    
    /**
     * Tests casting.
     */
    public function testCast() {
        $this->assertInstanceOf('URL', URL::cast('www.some-url.com/asoidfjj'));
        // URL objects should be passed through unchanged
        $url = new URL('www.some-url.com/asdf');
        $this->assertSame($url, URL::cast($url));
        // Exceptions shouldn't be trapped
        $this->assertThrows(
            'URLException',
            array('URL', 'cast'),
            array('H**B)H*B(n843ah89h89WG'),
            'Attempting to cast an invalid value to a URL did not raise the expected exception.'
        );
    }
    
    /**
     * Tests what happens when we can't download a new Public Suffix List file,
     * but we have one cached from last time.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFallBackToSuffixListCache() {
        // First, define the URL as something that won't work
        define('SUFFIX_LIST_URL', 'http://your_mom.co/osdifajaosidfj');
        // And define the refresh interval such that it is always triggered
        define('SUFFIX_LIST_REFRESH_INTERVAL', 0);
        // Ensure there is no cached file
        URL::clearSuffixListCache();
        /* Now we need to make sure there's something to use as a fallback.
        This requires building a mock data structure that is compatible with
        the test values we're intending to use and the inner workings of the
        URL class, serializing it, and saving it to the cached file location.
        */
        $suffixList = array(
            'foobar' => DataStructures\SerializableFixedArray::factory(3)
        );
        $suffixList['foobar'][1] = array(
            DataStructures\SerializableFixedArray::fromArray(array('foobar'))
        );
        $suffixList['foobar'][2] = array(
            DataStructures\SerializableFixedArray::fromArray(
                array('foo', 'foobar')
            ),
            DataStructures\SerializableFixedArray::fromArray(
                array('bar', 'foobar')
            )
        );
        file_put_contents(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . SUFFIX_LIST_CACHE_FILE,
            serialize($suffixList)
        );
        $url = new URL('www.something.foobar');
        $this->assertEquals('www', $url->getSubdomain());
        $this->assertEquals('something', $url->getDomain());
        $this->assertEquals('foobar', $url->getTLD());
        $url = new URL('www.foo.foobar');
        $this->assertNull($url->getSubdomain());
        $this->assertEquals('www', $url->getDomain());
        $this->assertEquals('foo.foobar', $url->getTLD());
        $url = new URL('www.bar.foobar');
        $this->assertNull($url->getSubdomain());
        $this->assertEquals('www', $url->getDomain());
        $this->assertEquals('bar.foobar', $url->getTLD());
        // Clear out our dummy data from the cache
        URL::clearSuffixListCache();
    }
    
    /**
     * Tests what happens when we can't download a new Public Suffix List file,
     * and we don't have one cached from last time.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @expectedException URLRuntimeException
     * @expectedExceptionMessage Failed to download latest Public Suffix List.
     */
    public function testSuffixListFailure() {
        // Define the URL as something that won't work
        define('SUFFIX_LIST_URL', 'http://your_mom.co/osdifajaosidfj');
        // Ensure there is no cached file
        URL::clearSuffixListCache();
        $url = new URL('www.google.com');
        $url->getTLD();
    }
    
    /**
     * Tests the ability to disable usage of the Public Suffix List and still
     * have access to sensible functionality.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSuffixListDisable() {
        define('SUFFIX_LIST_DISABLE', true);
        /* This is copied from $this->testBasicParsing(), just to make sure
        that everything except access to the host components works. */
        $urlStr = 'http://www.some-website.com/some-path?favorite_color=blue&favorite_food=pizza';
        $url = new URL($urlStr);
        /* I'm packaging up as many of these assertions as is convenient into
        an associative array so I can change little things as I go and just
        iterate through them. */
        $equalityAssertions = array(
            '__toString' => $urlStr,
            'getRawURL' => $urlStr,
            'getScheme' => 'http',
            'getHost' => 'www.some-website.com',
            'getFullHost' => 'www.some-website.com',
            'getPort' => null,
            'getPath' => '/some-path',
            'getPathBaseName' => 'some-path',
            'getFullPath' => '/some-path?favorite_color=blue&favorite_food=pizza',
            'getHashFragment' => null,
            'getQueryString' => 'favorite_color=blue&favorite_food=pizza',
            'getQueryStringData' => array(
                'favorite_color' => 'blue', 'favorite_food' => 'pizza'
            )
        );
        $equalityAssertionArgs = array(
            'getQueryStringParam' => array(
                array('blue', array('favorite_color')),
                array('pizza', array('favorite_food'))
            )
        );
        $this->_runEqualityAssertions($equalityAssertions, $url, $equalityAssertionArgs);
        /* But attempting to set or get the domain components should throw an
        exception. */
        $exceptionAssertions = array(
            'getSubdomain' => null,
            'getDomain' => null,
            'getTLD' => null,
            'setSubdomain' => array('foo'),
            'setDomain' => array('foo'),
            'setTLD' => array('com')
        );
        foreach ($exceptionAssertions as $method => $args) {
            $this->assertThrows(
                'URLException',
                array($url, $method),
                $args,
                'Failed to raise expected exception when calling URL::' . $method . '().'
            );
        }
        /* We should still be able to call the getters when the host is an IP
        address, though. */
        $url->setHost('127.0.0.1');
        $this->assertNull($url->getSubdomain());
        $this->assertNull($url->getDomain());
        $this->assertNull($url->getTLD());
    }
    
    /**
     * Tests that the expected exceptions are thrown when passing invalid
     * arguments to various methods.
     */
    public function testArgumentValidation() {
        $url = new URL('www.foo.com');
        // Hash fragments and query strings are not allowed in URL paths
        $badPaths = array(
            '/asdf?foo=bar',
            'asdf?foo=bar&bar=baz',
            '/aasdf#foo',
            'asdf#foo',
            '/sdaf?foo=bar#asdf',
            'asdf?bar=baz&1=2#saodfijsadoifj'
        );
        foreach ($badPaths as $path) {
            $this->assertThrows(
                'URLInvalidArgumentException',
                array($url, 'setPath'),
                array($path),
                'The expected exception was not thrown when attempting to ' .
                'set a URL\'s path to "' . $path . '".'
            );
        }
        // Schemes may contain only a limited set of characters
        $url->setScheme('foo');
        $url->setScheme('foo-bar');
        $url->setScheme('foo-bar.baz123');
        $badSchemes = array(
            '-foo',
            'foo$',
            'foo:',
            '9f2b03'
        );
        foreach ($badSchemes as $scheme) {
            $this->assertThrows(
                'URLInvalidArgumentException',
                array($url, 'setScheme'),
                array($scheme),
                'The expected exception was not thrown when attempting to ' .
                'set a URL\'s scheme to "' . $scheme . '".'
            );
        }
        $url->setHost(null);
        $url->setHost('');
        $url->setHost('Google.Com');
        $url->setHost('www-1.1.2.3.4.5');
        $url->setHost('www');
        $badHosts = array(
            '-foo',
            'foo..bar',
            'foo-',
            'google$.com',
            'some_website.com',
            '.google.com',
            'www.'
        );
        /* International domains names will work if the proper extension is
        available. */
        $idns = array(
            'ουτοπία.δπθ.gr',
            'türkiye.com'
        );
        if (extension_loaded('intl')) {
            foreach ($idns as $idn) {
                $url->setHost($idn);
            }
        }
        else {
            $badHosts = array_merge($badHosts, $idns);
        }
        foreach ($badHosts as $host) {
             $this->assertThrows(
                'URLInvalidArgumentException',
                array($url, 'setHost'),
                array($host),
                'The expected exception was not thrown when attempting to ' .
                'set a URL\'s host to "' . $host . '".'
            );
        }
        /* Strings or integers work for ports, as long as they validate as
        positive integers. */
        $url->setPort(0);
        $url->setPort('0');
        $url->setPort(1234);
        $url->setPort('1234');
        $url->setPort(PHP_INT_MAX);
        $url->setPort((string)PHP_INT_MAX);
        $badPorts = array(
            -1,
            '-1',
            'sadf',
            PHP_INT_MAX + 1,
            ':1234'
        );
        foreach ($badPorts as $port) {
            $this->assertThrows(
                'URLInvalidArgumentException',
                array($url, 'setPort'),
                array($port),
                'The expected exception was not thrown when attempting to ' .
                'set a URL\'s port to "' . $port . '".'
            );
        }
        /* Since we're going to work with domain components now, set the host
        to something with a valid TLD. */
        $url->setHost('www.performics.com');
        // Anything that works as a host should work as a subdomain
        $url->setSubdomain(null);
        $url->setSubdomain('');
        $url->setSubdomain('Google.Com');
        $url->setSubdomain('www-1.1.2.3.4.5');
        $url->setSubdomain('www');
        if (extension_loaded('intl')) {
            foreach ($idns as $idn) {
                $url->setSubdomain($idn);
            }
        }
        foreach ($badHosts as $subdomain) {
            $this->assertThrows(
                'URLInvalidArgumentException',
                array($url, 'setSubdomain'),
                array($subdomain),
                'The expected exception was not thrown when attempting ' .
                'to set a URL\'s subdomain to "' . $subdomain . '".'
            );
        }
        // Even valid subdomains may not be set if a host is not yet set
        $emptyURL = new URL();
        $this->assertThrows(
            'URLLogicException',
            array($emptyURL, 'setSubdomain'),
            array('foo'),
            'The expected exception was not thrown when attempting to set ' .
            'the subdomain on an empty URL.'
        );
        /* Domains are more restrictive in that they may not contain periods
        and they must have a length. */
        $url->setDomain('foo');
        $url->setDomain('Google');
        $url->setDomain('99-problems');
        $badDomains = array(
            'Google.Com',
            'www-1.1.2.3.4.5',
            null,
            '',
            '-foo',
            'foo..bar',
            'foo-',
            'google$.com',
            'some_website.com',
            '.google.com',
            'www.'
        );
        $idnDomains = array(
            'ουτοπία',
            'türkiye'
        );
        if (extension_loaded('intl')) {
            foreach ($idnDomains as $domain) {
                $url->setDomain($domain);
            }
        }
        else {
            $badDomains = array_merge($badDomains, $idnDomains);
        }
        foreach ($badDomains as $domain) {
            $this->assertThrows(
                'URLInvalidArgumentException',
                array($url, 'setDomain'),
                array($domain),
                'The expected exception was not thrown when attempting ' .
                'to set a URL\'s domain to "' . $domain . '".'
            );
        }
        $this->assertThrows(
            'URLLogicException',
            array($emptyURL, 'setDomain'),
            array('foo'),
            'The expected exception was not thrown when attempting to set ' .
            'the domain on an empty URL.'
        );
        // TLDs must be valid according to the Public Suffix List
        $url->setTLD('com');
        $url->setTLD('co.uk');
        $url->setTLD('info.na');
        $url->setTLD('museum');
        /* For some of these expected failures to succeed, I have to make sure
        there's no subdomain. */
        $url->setHost('foo.com');
        $badTLDs = array(
            'oaisdfj',
            'ao.ea989ag',
            'il',
            'mz'
        );
        foreach ($badTLDs as $tld) {
            $this->assertThrows(
                'TLDException',
                array($url, 'setTLD'),
                array($tld),
                'The expected exception was not thrown when attempting ' .
                'to set a URL\'s TLD to "' . $tld . '".'
            );
        }
        // mz isn't valid on its own UNLESS the domain is 'teledata'
        $url->setDomain('teledata');
        $url->setTLD('mz');
        // Some TLD rules involve wildcards
        $url->setTLD('foo.er');
        $url->setTLD('bar.er');
        $url->setTLD('baz.er');
        /* International TLDs should work properly regardless of whether the
        intl extension is loaded. */
        $url->setTLD('中国');
        $url->setTLD('الجزائر');
        $this->assertThrows(
            'URLLogicException',
            array($emptyURL, 'setTLD'),
            array('com'),
            'The expected exception was not thrown when attempting to set ' .
            'the TLD on an empty URL.'
        );
        // This method either wants two scalars or a single array
        $this->assertThrows(
            'URLInvalidArgumentException',
            array($url, 'setQueryStringParam'),
            array(array('foo' => 'bar'), 'baz'),
            'The expected exception was not thrown when attempting to pass ' .
            'both an array and a scalar argument to URL::setQueryStringParam().'
        );
        $this->assertThrows(
            'URLInvalidArgumentException',
            array($url, 'setQueryStringParam'),
            array('baz', array('foo' => 'bar')),
            'The expected exception was not thrown when attempting to pass ' .
            'both an array and a scalar argument to URL::setQueryStringParam().'
        );
        $this->assertThrows(
            'URLInvalidArgumentException',
            array($url, 'setQSArgSeparator'),
            array(''),
            'The expected exception was not thrown when attempting to set ' .
            'empty value as a query string argument separator.'
        );
        $this->assertThrows(
            'URLInvalidArgumentException',
            array($url, 'getQueryStringParam'),
            array('foo'),
            'The expected exception was not thrown when attempting to get a ' .
            'non-existent query string parameter value.'
        );
    }
}
?>