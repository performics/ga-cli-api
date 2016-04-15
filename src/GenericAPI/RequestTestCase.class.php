<?php
namespace GenericAPI;

class MutableRequest extends Request {
    /* This is a concrete implementation of GenericAPI\Request that provides
    certain functionality for testing purposes. */
    
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
}

class PersistentParameterRequest extends Request {
    static $_persistentArgs = array(
        'name' => 'dingus',
        'occupation' => 'prostman'
    );
}

class PersistentHeaderRequest extends Request {
    /* This subclass implements header peristance via overriding of the
    getHeaders() method. */
    
    /**
     * @return array
     */
    public function getHeaders() {
        $headers = parent::getHeaders();
        $headers[] = 'Persistent: header';
        return $headers;
    }
}

class BaseURLRequest extends Request {
    /* It doesn't work to modify the value of the $_baseURL property at
    runtime, because it won't happen until after the constructor has been
    executed, and the constructor is where the $_baseURL property is used if
    set. */
    protected static $_baseURL = 'http://127.0.0.1/foo';
}

class RequestTestCase extends \TestHelpers\TestCase {    
    /**
     * Tests whether passing GET parameters directly in the URL passed to the
     * constructor has the same effect as passing them discretely.
     */
    public function testGetParameterSpecification() {
        $request1 = new Request(
            'http://www.foo.bar', array('foo' => 'bar', 'bar' => 'baz')
        );
        $request2 = new Request('http://www.foo.bar/?foo=bar&bar=baz');
        $this->assertTrue($request1->getURL()->compare($request2->getURL()));
        // This is true even if the verb isn't GET
        $request1->setVerb('POST');
        // Calling this method implicitly sets the verb to POST
        $request2->setPayload(array('baz' => 'boo'));
        $this->assertTrue($request1->getURL()->compare($request2->getURL()));
        /* It's also possible to modify GET parameters after the fact, which
        should have the same effect no matter how the instance was constructed.
        */
        $params = array('a' => 'b', '1' => '2');
        $request1->setGetParameters($params);
        $this->assertEquals(
            'http://www.foo.bar/?a=b&1=2', (string)$request1->getURL()
        );
        $request2->setGetParameters($params);
        $this->assertTrue($request1->getURL()->compare($request2->getURL()));
        /* When GET parameters are specified in both the URL and in the second
        argument, they should be merged, with the latter preferred. */
        $request = new Request(
            '127.0.0.1/?foo=bar&bar=baz', array('bar' => 'foo', 'a' => 'b')
        );
        $this->assertTrue($request->getURL()->compare(
            'http://127.0.0.1/?foo=bar&bar=foo&a=b'
        ));
    }
    
    /**
     * Tests whether it is possible to specify a URL base as a class property,
     * and whether it's then possible to override it.
     */
    public function testBaseURL() {
        $request = new BaseURLRequest('bar', array('a' => 'b'));
        $this->assertEquals(
            'http://127.0.0.1/foobar?a=b', (string)$request->getURL()
        );
        $request = new BaseURLRequest('bar?a=b');
        $this->assertEquals(
            'http://127.0.0.1/foobar?a=b', (string)$request->getURL()
        );
        $request = new BaseURLRequest(
            new \URL('https://127.0.0.2/bar/'), array('foo' => 'bar')
        );
        $this->assertEquals(
            'https://127.0.0.2/bar/?foo=bar', (string)$request->getURL()
        );
    }
    
    /**
     * Confirms that it is possible to set arbitrary HTTP verbs.
     */
    public function testVerbs() {
        $request = new Request('http://127.0.0.1');
        $this->assertEquals('GET', $request->getVerb());
        $request->setVerb('DELETE');
        $this->assertEquals('DELETE', $request->getVerb());
        /* Setting a payload implicitly sets the HTTP verb to 'POST' unless
        another one has been set. */
        $request = new Request('http://127.0.0.1');
        $args = array('foo' => 'bar');
        $request->setPayload($args);
        $this->assertEquals('POST', $request->getVerb());
        $request->setVerb('PATCH');
        $this->assertEquals('PATCH', $request->getVerb());
        $this->assertEquals($args, $request->getPayload());
        // We can set the verb and the payload in the opposite order too
        $request = new Request('http://127.0.0.1');
        $request->setVerb('BUTT');
        $payload = json_encode($args);
        $request->setPayload($payload);
        $this->assertEquals('BUTT', $request->getVerb());
        $this->assertEquals($payload, $request->getPayload());
        /* But if we try to force the verb to GET, we'll have a problem when we
        try to get the cURL options, which is the point when conflicts are
        resolved. */
        $request->setVerb('GET');
        $this->assertThrows(
            'LogicException', array($request, 'getCurlOptions')
        );
    }
    
    /**
     * Confirms that it is possible to build a list of additional HTTP headers
     * to be used in the request.
     */
    public function testHeaders() {
        $request = new Request('http://127.0.0.1');
        $this->assertEmpty($request->getHeaders());
        $request->setHeader('Foo: bar');
        $this->assertEquals(array('Foo: bar'), $request->getHeaders());
        $request->setHeader('Foo: bar', 'Bar: baz');
        // These headers will have been appended to the existing one
        $this->assertEquals(
            array('Foo: bar', 'Foo: bar', 'Bar: baz'), $request->getHeaders()
        );
        // Headers may be passed as an array
        $request->setHeader(array('Cookie: tasty'));
        $this->assertEquals(
            array('Foo: bar', 'Foo: bar', 'Bar: baz', 'Cookie: tasty'),
            $request->getHeaders()
        );
        // But not if there are additional arguments
        $this->assertThrows(
            'InvalidArgumentException',
            array($request, 'setHeader'),
            array(array('Foo: bar', 'Bar: baz'), 'Brule: dingus')
        );
        // This subclass always adds a persistent header to whatever we set
        $request = new PersistentHeaderRequest('http://127.0.0.1');
        $this->assertEquals(
            array('Persistent: header'), $request->getHeaders()
        );
        $request->setHeader('Foo: bar', 'Bar: baz');
        $this->assertEquals(
            array('Foo: bar', 'Bar: baz', 'Persistent: header'),
            $request->getHeaders()
        );
        // Headers can be passed as an array instead of variadically
        $request2 = new PersistentHeaderRequest('http://127.0.0.1');
        $request2->setHeader(array('Foo: bar', 'Bar: baz'));
        $this->assertEquals($request->getHeaders(), $request2->getHeaders());
    }
    
    /**
     * Tests that the overriding of existing headers works as expected.
     */
    public function testReplaceHeader() {
        $request = new Request('127.0.0.1');
        $request->setHeader('Foo: bar', 'Bar: baz');
        $request->replaceHeader('Foo', 'baz');
        $this->assertEquals(
            array('Foo: baz', 'Bar: baz'), $request->getHeaders()
        );
        $request->replaceHeader('Bar', 'foo');
        $this->assertEquals(
            array('Foo: baz', 'Bar: foo'), $request->getHeaders()
        );
        // Only the first matching instance of a header will be replaced
        $request->setHeader('Baz: asdf', 'Baz: 1234');
        $request->replaceHeader('Baz', 'qwerty');
        $this->assertEquals(
            array('Foo: baz', 'Bar: foo', 'Baz: qwerty', 'Baz: 1234'),
            $request->getHeaders()
        );
        // Case sensitivity is not required
        $request->replaceHeader('foo', 'Bar');
        $this->assertEquals(
            array('foo: Bar', 'Bar: foo', 'Baz: qwerty', 'Baz: 1234'),
            $request->getHeaders()
        );
        // If the header isn't there yet, it is added
        $request->replaceHeader('Brule', 'Dingus');
        $this->assertEquals(
            array('foo: Bar', 'Bar: foo', 'Baz: qwerty', 'Baz: 1234', 'Brule: Dingus'),
            $request->getHeaders()
        );
    }
    
    /**
     * Tests parameter persistence.
     */
    public function testParameterPersistence() {
        $request = new PersistentParameterRequest('127.0.0.1/?bar=baz');
        $this->assertTrue($request->getURL()->compare(
            'http://127.0.0.1/?bar=baz&name=dingus&occupation=prostman'
        ));
        /* If the verb is POST, persistent parameters go in the POST payload,
        but this is only resolved within the cURL options. */
        $request->setVerb('POST');
        $this->assertEquals(
            'http://127.0.0.1/?bar=baz', (string)$request->getURL()
        );
        $curlOpts = $request->getCurlOptions();
        $this->assertEquals(
            array('name' => 'dingus', 'occupation' => 'prostman'),
            $curlOpts[CURLOPT_POSTFIELDS]
        );
        /* Attempting to mix string data and persistent POST parameters throws
        an exception. */
        $postString = 'asdfasdfasdf';
        $request->setPayload($postString);
        $this->assertThrows(
            'LogicException', array($request, 'getCurlOptions')
        );
        // For this reason, it is possible to force those parameters to the GET
        $request->forcePersistentParametersToGet(true);
        $this->assertTrue($request->getURL()->compare(
            'http://127.0.0.1/?bar=baz&name=dingus&occupation=prostman'
        ));
        $curlOpts = $request->getCurlOptions();
        $this->assertEquals($postString, $curlOpts[CURLOPT_POSTFIELDS]);
        /* Persistent parameters may be selectively overridden, whether or not
        they are in the GET or the POST. */
        $request = new PersistentParameterRequest('127.0.0.1/?bar=baz');
        $request->setGetParameters(array('occupation' => 'prilot'));
        $this->assertTrue($request->getURL()->compare(
            'http://127.0.0.1/?bar=baz&name=dingus&occupation=prilot'
        ));
        $request = new PersistentParameterRequest('http://foo.bar/api');
        $request->setPayload(
            array('occupation' => 'ump', 'is_hunk' => true)
        );
        $curlOpts = $request->getCurlOptions();
        $this->assertEquals(
            array('name' => 'dingus', 'occupation' => 'ump', 'is_hunk' => true),
            $curlOpts[CURLOPT_POSTFIELDS]
        );
    }
    
    /**
     * Confirms that cURL options are returned as expected.
     */
    public function testCurlOptions() {
        $request = new Request('foo.bar/api', array('composer' => 'brahms'));
        $expected = array(
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_URL => 'http://foo.bar/api?composer=brahms',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10
        );
        $this->assertEquals($expected, $request->getCurlOptions());
        $request->autoRedirect(false);
        unset($expected[CURLOPT_FOLLOWLOCATION]);
        unset($expected[CURLOPT_MAXREDIRS]);
        $this->assertEquals($expected, $request->getCurlOptions());
        $request->setVerb('FROWN');
        $expected[CURLOPT_CUSTOMREQUEST] = 'FROWN';
        $this->assertEquals($expected, $request->getCurlOptions());
        $postContent = 'aosidfjoaisdfjoiasdfj';
        $request->setPayload($postContent);
        $request->setVerb('POST');
        $expected[CURLOPT_CUSTOMREQUEST] = 'POST';
        $expected[CURLOPT_POSTFIELDS] = $postContent;
        // When the payload is a string, the content type should be this
        $expected[CURLOPT_HTTPHEADER] = array(
            'Content-Type: application/x-www-form-urlencoded'
        );
        $this->assertEquals($expected, $request->getCurlOptions());
        // WHen it's an array, it's this
        $postContent = array('foo' => 'bar');
        $request->setPayload($postContent);
        $expected[CURLOPT_HTTPHEADER] = array(
            'Content-Type: multipart/form-data'
        );
        $expected[CURLOPT_POSTFIELDS] = $postContent;
        $request->setBasicAuthentication('max', 'mypassword123');
        $expected[CURLOPT_USERPWD] = 'max:mypassword123';
        $this->assertEquals($expected, $request->getCurlOptions());
        $request->setHeader('Foo: bar');
        $expected[CURLOPT_HTTPHEADER][] = 'Foo: bar';
        $this->assertEquals($expected, $request->getCurlOptions());
    }
    
    /**
     * Confirms that the enforcing of validation on the response works as
     * expected.
     */
    public function testResponseValidation() {
        $request = new Request('127.0.0.1');
        $response = array(
            'foo' => 'a',
            'bar' => 'b',
            'baz' => 'c'
        );
        /* Note that because a raw response with a non-zero length must be
        passed to GenericAPI\Request::validateResponse(), but it does not need
        to have anything to do with the parsed response, I am passing a
        placeholder argument in these calls. */
        $request->setResponseValidator(array('foo', 'bar'));
        // The response can have more keys than the validator checks for
        $request->validateResponse('a', $response);
        $request->setResponseValidator(array('foo', 'bar', 'baz', 'asdf'));
        $this->assertThrows(
            __NAMESPACE__ . '\ResponseValidationException',
            array($request, 'validateResponse'),
            array('a', $response)
        );
        $response['asdf'] = 'd';
        $request->validateResponse('a', $response);
        $proto = array('response' => array(
            'foo' => null,
            'bar' => array('knock-knock' => null),
            'baz' => null
        ));
        $request->setResponseValidator($proto);
        $request->setResponseValidationMethod(Request::VALIDATE_PROTOTYPE);
        $response = array('response' => array(
            'foo' => array('', 1, 'b'),
            'bar' => array('knock-knock' => "who's there"),
            'baz' => '927381.29'
        ));
        $request->validateResponse('a', $response);
        /* This doesn't just validate the presence of the array keys, so this
        should cause a failure. */
        $response['response']['bar'] = 'asdf';
        $this->assertThrows(
            __NAMESPACE__ . '\ResponseValidationException',
            array($request, 'validateResponse'),
            array('a', $response)
        );
        $response['response']['bar'] = array('knock-knock' => "who's there");
        // Adding a requirement to the prototype also causes a failure
        $proto['baz'] = null;
        $request->setResponseValidator($proto);
        $this->assertThrows(
            __NAMESPACE__ . '\ResponseValidationException',
            array($request, 'validateResponse'),
            array('a', $response)
        );
        // But we can selectively disble validation
        $request->setResponseValidationMethod(null);
        $request->validateResponse('a', $response);
    }
}
?>