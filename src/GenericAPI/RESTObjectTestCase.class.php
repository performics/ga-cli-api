<?php
namespace GenericAPI;

class TestRESTObject extends RESTObject {
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
        )
    );
    protected static $_GETTER_DISPATCH_MODEL = array();
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
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
    
    public function getFoo() {
        return $this->_foo;
    }
    
    public function getBar() {
        return $this->_bar;
    }
    
    public function getNestedProperty() {
        return $this->_nested;
    }
    
    public function getSecondNestedProperty() {
        return $this->_nested2;
    }
    
    public function getDeepNestedProperty() {
        return $this->_nestedDeep;
    }
    
    public function getNestedArray() {
        return $this->_nestedArray;
    }
}

class BadRESTObject extends TestRESTObject {
    protected static $_SETTER_DISPATCH_MODEL = array(
        /* This one points to a nonexistent method so that we can test the
        behavior that is supposed to throw an exception if the model mentions
        it. */
        'baz' => 'setBaz'
    );
    protected static $_GETTER_DISPATCH_MODEL = array();
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
}

class TestRESTObjectSubclass extends TestRESTObject {
    protected static $_SETTER_DISPATCH_MODEL = array(
        'subclass_property' => 'setSubclassProperty',
        'nested_container' => array(
            'subclass_nested' => 'setSubclassNestedProperty'
        ),
        'baz' => 'setFoo'
    );
    /* For this one, we'll specify a couple of custom getters, while still
    allowing others to be merged (with one exception). */
    protected static $_GETTER_DISPATCH_MODEL = array(
        'bar' => null,
        'baz' => 'getCustomValue',
        'custom' => 'getAnotherCustomValue'
    );
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
    
    protected $_subclassProperty;
    protected $_subclassNestedProperty;
    
    public function setSubclassProperty($val) {
        $this->_subclassProperty = $val;
    }
    
    public function setSubclassNestedProperty($val) {
        $this->_subclassNestedProperty = $val;
    }
    
    public function getSubclassProperty() {
        return $this->_subclassProperty;
    }
    
    public function getSubclassNestedProperty() {
        return $this->_subclassNestedProperty;
    }
    
    public function getCustomValue() {
        return 'custom value 1';
    }
    
    public function getAnotherCustomValue() {
        return 'custom value 2';
    }
}

class TestRESTObjectSubSubclass extends TestRESTObjectSubclass {
    protected static $_SETTER_DISPATCH_MODEL = array(
        'deep_subclass_property' => 'setDeepSubclassProperty'
    );
    /* For this one, we'll have a custom getter model that does not attempt to
    merge in anything from the setter model, but it will still be resolved
    against its parents. */
    protected static $_GETTER_DISPATCH_MODEL = array(
        'custom' => 'getCustomValue',
        'metal_man' => 'getAnotherCustomValue'
    );
    protected static $_MERGE_DISPATCH_MODELS = false;
    protected static $_dispatchModelReady = false;
    protected $_deepSubclassProperty;
    
    public function setDeepSubclassProperty($val) {
        $this->_deepSubclassProperty = $val;
    }
    
    public function getDeepSubclassProperty() {
        return $this->_deepSubclassProperty;
    }
    
    public function getAnotherCustomValue() {
        return 'has won his wings';
    }
}

class RESTObjectTestCase extends \TestHelpers\TestCase {
    private function _runAssertions(
        array $expected,
        TestRESTObject $response,
        array $expectedREST = null
    ) {
        foreach ($expected as $property => $expectedValue) {
            $getter = 'get' . ucfirst($property);
            $this->assertSame($expectedValue, $response->$getter());
        }
        if ($expectedREST !== null) {
            $this->assertEquals($expectedREST, $response->toREST());
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
            'nestedProperty' => $apiData['nested_container']['nested_1'],
            'secondNestedProperty' => $apiData['nested_container']['nested_2'],
            'deepNestedProperty' => $apiData['nested_container']['nested_3']['nested_4'],
            'nestedArray' => $apiData['nested_container']['nested_array']
        );
        // Converting back the other way should get us back where we started
        $this->_runAssertions($expected, new TestRESTObject($apiData), $apiData);
        // Not all properties need to be present
        unset($apiData['bar']);
        unset($apiData['nested_container']['nested_3']);
        $expected['bar'] = null;
        $expected['deepNestedProperty'] = null;
        $expectedRest = $apiData;
        // In the REST-encoded object, all keys will be present
        $expectedRest['bar'] = null;
        $expectedRest['nested_container']['nested_3'] = array('nested_4' => null);
        $this->_runAssertions($expected, new TestRESTObject($apiData), $expectedRest);
        // Extra ones can be present without affecting anything
        $apiData['unused_property'] = 'asoidfj';
        $apiData['nested_container']['unused_nested_proeprty'] = 23985;
        $this->_runAssertions($expected, new TestRESTObject($apiData), $expectedRest);
    }
    
    /**
     * Tests instantiation with data that does not cause any of the setters to
     * run.
     *
     * @expectedException RuntimeException
     * @expectedExceptionMessage No data was set
     */
    public function testInstantiationWithBadData() {
        new TestRESTObject(array(
            'bad_key' => 'bad_value',
            'bad_key_2' => array(
                'bad_nested_key' => 'asdof',
                'bad_nested_key_2' => 'ao24j98'
            )
        ));
    }
    
    /**
     * Tests instantiation of a subclass that mentions an undefined setter.
     *
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage The method "setBaz" is not present
     */
    public function testInstantiationWithBadSetter() {
        new BadRESTObject(array(
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
    
    /**
     * Tests the behavior that merges the setter dispatch models of a subclass
     * with those of its parents.
     */
    public function testSubclassInstantiation() {
        $apiData = array(
            'bar' => 'value for bar',
            /* Note that in the subclass, we pointed 'baz' to setFoo() in order
            to prove that it overwrites the parent. */
            'baz' => 'value for foo',
            'subclass_property' => 'subclass property value',
            'nested_container' => array(
                'subclass_nested' => 'subclass-specific nested property value'
            )
        );
        $expected = array(
            'bar' => $apiData['bar'],
            'foo' => $apiData['baz'],
            'subclassProperty' => $apiData['subclass_property'],
            'subclassNestedProperty' => $apiData['nested_container']['subclass_nested']
        );
        $expectedRest = $apiData;
        /* We explicitly declared our intention that this property not be
        included in the REST representation. */
        unset($expectedRest['bar']);
        $expectedRest['foo'] = $expectedRest['baz'];
        $expectedRest['baz'] = 'custom value 1';
        $expectedRest['custom'] = 'custom value 2';
        $this->_runAssertions($expected, new TestRESTObjectSubclass($apiData), $expectedRest);
        $expected['deepSubclassProperty'] = $apiData['deep_subclass_property'] = 'deep subclass property value';
        $expectedRest['custom'] = 'custom value 1';
        $expectedRest['metal_man'] = 'has won his wings';
        $this->_runAssertions(
            $expected,
            new TestRESTObjectSubSubclass($apiData),
            $expectedRest
        );
    }
}
?>