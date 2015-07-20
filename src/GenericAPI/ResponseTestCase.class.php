<?php
namespace GenericAPI;

class TestResponse extends Response {
    protected static $_SETTER_DISPATCH_MODEL = array(
        'foo' => 'setFoo',
        'bar' => 'setBar',
        'nested_container' => array(
            'nested_1' => 'setNestedProperty',
            'nested_2' => 'setSecondNestedProperty',
            'nested_3' => array(
                'nested_4' => 'setDeepNestedProperty'
            ),
            'nested_array' => 'setNestedArray'
        ),
        /* This one points to a nonexistent method so that we can test the
        behavior that is supposed to throw an exception if the constructor
        tries to call it. */
        'baz' => 'setBaz'
    );
    protected $_foo;
    protected $_bar;
    protected $_nested;
    protected $_nested2;
    protected $_nestedDeep;
    protected $_nestedArray;
    
    public function setFoo($foo) {
        $this->_foo = $foo;
    }
    
    public function setBar($bar) {
        $this->_bar = $bar;
    }
    
    public function setNestedProperty($nested) {
        $this->_nested = $nested;
    }
    
    public function setSecondNestedProperty($nested) {
        $this->_nested2 = $nested;
    }
    
    public function setDeepNestedProperty($nested) {
        $this->_nestedDeep = $nested;
    }
    
    public function setNestedArray(array $nested) {
        $this->_nestedArray = $nested;
    }
    
    public function get($property) {
        $property = '_' . $property;
        return $this->$property;
    }
}

class ResponseTestCase extends \TestHelpers\TestCase {
    private function _runAssertions(array $expected, TestResponse $response) {
        foreach ($expected as $property => $expectedValue) {
            $this->assertSame($expectedValue, $response->get($property));
        }
    }
    
    /**
     * Tests various typical cases of instantiation.
     */
    public function testInstantiation() {
        $apiData = array(
            'foo' => 'value for foo',
            'bar' => 'value for bar',
            'nested_container' => array(
                'nested_1' => 'value for nested property 1',
                'nested_2' => 27359,
                'nested_3' => array(
                    'nested_4' => 'value for deep nested property'
                ),
                'nested_array' => array('foo', 'bar', 'baz')
            )
        );
        $expected = array(
            'foo' => $apiData['foo'],
            'bar' => $apiData['bar'],
            'nested' => $apiData['nested_container']['nested_1'],
            'nested2' => $apiData['nested_container']['nested_2'],
            'nestedDeep' => $apiData['nested_container']['nested_3']['nested_4'],
            'nestedArray' => $apiData['nested_container']['nested_array']
        );
        $this->_runAssertions($expected, new TestResponse($apiData));
        // Not all properties need to be present
        unset($apiData['bar']);
        unset($apiData['nested_container']['nested_3']);
        $expected['bar'] = null;
        $expected['nestedDeep'] = null;
        $this->_runAssertions($expected, new TestResponse($apiData));
        // Extra ones can be present without affecting anything
        $apiData['unused_property'] = 'asoidfj';
        $apiData['nested_container']['unused_nested_proeprty'] = 23985;
        $this->_runAssertions($expected, new TestResponse($apiData));
    }
    
    /**
     * Tests instantiation with data that does not cause any of the setters to
     * run.
     *
     * @expectedException RuntimeException
     * @expectedExceptionMessage No data was set
     */
    public function testInstantiationWithBadData() {
        new TestResponse(array(
            'bad_key' => 'bad_value',
            'bad_key_2' => array(
                'bad_nested_key' => 'asdof',
                'bad_nested_key_2' => 'ao24j98'
            )
        ));
    }
    
    /**
     * Tests instantiation with data that causes the constructor to try to run
     * an undefined setter.
     *
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage The method "setBaz" does not exist in this object
     */
    public function testInstantiationWithBadSetter() {
        new TestResponse(array(
            'foo' => 'value for foo',
            'baz' => 'value for baz',
            'nested_container' => array(
                'nested_1' => 'value for nested property 1',
                'nested_2' => 27359,
                'nested_3' => array(
                    'nested_4' => 'value for deep nested property'
                ),
                'nested_array' => array('foo', 'bar', 'baz')
            )
        ));
    }
}
?>