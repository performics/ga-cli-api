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
        $request2->setPostParameters(array('baz' => 'boo'));
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
     * Confirms that it is possible to set arbitrary HTTP verbs, unless the
     * instance contains POST parameters already.
     */
    public function testVerbs() {
        $request = new Request('http://127.0.0.1');
        $this->assertEquals('GET', $request->getVerb());
        $request->setVerb('DELETE');
        $this->assertEquals('DELETE', $request->getVerb());
        $request->setPostParameters(array('foo' => 'bar'));
        $this->assertEquals('POST', $request->getVerb());
        $this->assertThrows(
            'LogicException', array($request, 'setVerb'), array('GET')
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
        // If the verb is POST, persistent parameters go in the POST payload
        $request->setVerb('POST');
        $this->assertEquals(
            'http://127.0.0.1/?bar=baz', (string)$request->getURL()
        );
        $this->assertEquals(
            array('name' => 'dingus', 'occupation' => 'prostman'),
            $request->getPostParameters()
        );
        /* Attempting to mix string data and persistent POST parameters throws
        an exception. */
        $postString = 'asdfasdfasdf';
        $request->setPostParameters($postString);
        $this->assertThrows(
            'LogicException', array($request, 'getPostParameters')
        );
        // For this reason, it is possible to force those parameters to the GET
        $request->forcePersistentParametersToGet(true);
        $this->assertTrue($request->getURL()->compare(
            'http://127.0.0.1/?bar=baz&name=dingus&occupation=prostman'
        ));
        $this->assertEquals($postString, $request->getPostParameters());
        /* Persistent parameters may be selectively overridden, whether or not
        they are in the GET or the POST. */
        $request = new PersistentParameterRequest('127.0.0.1/?bar=baz');
        $request->setGetParameters(array('occupation' => 'prilot'));
        $this->assertTrue($request->getURL()->compare(
            'http://127.0.0.1/?bar=baz&name=dingus&occupation=prilot'
        ));
        $request = new PersistentParameterRequest('http://foo.bar/api');
        $request->setPostParameters(
            array('occupation' => 'ump', 'is_hunk' => true)
        );
        $this->assertEquals(
            array('name' => 'dingus', 'occupation' => 'ump', 'is_hunk' => true),
            $request->getPostParameters()
        );
    }
    
    /**
     * Confirms that cURL options are returned as expected.
     */
    public function testCurlOptions() {
        $request = new Request('foo.bar/api', array('composer' => 'brahms'));
        $expected = array(
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
        $request->setPostParameters($postContent);
        unset($expected[CURLOPT_CUSTOMREQUEST]);
        $expected[CURLOPT_POST] = true;
        $expected[CURLOPT_POSTFIELDS] = $postContent;
        $this->assertEquals($expected, $request->getCurlOptions());
        $request->setBasicAuthentication('max', 'mypassword123');
        $expected[CURLOPT_USERPWD] = 'max:mypassword123';
        $this->assertEquals($expected, $request->getCurlOptions());
        $request->setHeader('Foo: bar');
        $expected[CURLOPT_HTTPHEADER] = array('Foo: bar');
        $this->assertEquals($expected, $request->getCurlOptions());
    }
}
?>