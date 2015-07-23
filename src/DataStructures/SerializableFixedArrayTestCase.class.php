<?php
namespace DataStructures;

class SerializableFixedArrayTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the polymorphic behavior is as expected.
     */
    public function testType() {
        $instance = SerializableFixedArray::factory();
        if (version_compare(PHP_VERSION, '5.4.19') >= 0) {
            $this->assertInstanceOf('\SplFixedArray', $instance);
        }
        else {
            $this->assertInstanceOf(
                'DataStructures\SerializableFixedArray', $instance
            );
        }
    }
    
    /**
     * Tests foreach iteration.
     */
    public function testIteration() {
        $instance = SerializableFixedArray::factory(5);
        $instance[1] = 'foo';
        $instance[2] = 2;
        $instance[4] = array('bar', null, 1359);
        $expected = array(
            null, 'foo', 2, null, array('bar', null, 1359)
        );
        $i = 0;
        foreach ($instance as $value) {
            $this->assertSame($expected[$i++], $value);
        }
        $this->assertEquals($i, count($instance));
        // Do it again for good measure
        $i = 0;
        foreach ($instance as $value) {
            $this->assertSame($expected[$i++], $value);
        }
        $this->assertEquals($i, count($instance));
        // Add a couple of items
        $instance->setSize($instance->getSize() + 2);
        $expected[] = $instance[5] = new \StdClass();
        $expected[] = $instance[6] = 'asdf';
        $i = 0;
        foreach ($instance as $value) {
            $this->assertSame($expected[$i++], $value);
        }
        $this->assertEquals($i, count($instance));
        // Now take some away
        $instance->setSize(3);
        $i = 0;
        foreach ($instance as $value) {
            $this->assertSame($expected[$i++], $value);
        }
        $this->assertEquals($i, count($instance));
    }
    
    /**
     * Tests array access.
     */
    public function testArrayAccess() {
        $instance = SerializableFixedArray::factory(5);
        $instance[1] = 'foo';
        $instance[2] = 2;
        $instance[4] = array('bar', null, 1359);
        $this->assertTrue(isset($instance[1]));
        $this->assertFalse(isset($instance[0]));
        $this->assertFalse(isset($instance[5]));
        $this->assertNull($instance[0]);
        $this->assertSame('foo', $instance[1]);
        $this->assertSame(2, $instance[2]);
        $this->assertSame(array('bar', null, 1359), $instance[4]);
        /* Why PHP doesn't automatically call this method when unset() is used,
        I have no idea. */
        $instance->offsetUnset(4);
        $this->assertNull($instance[4]);
        $this->assertThrows(
            'RuntimeException',
            function() use ($instance) { $a = $instance[5]; },
            null,
            'The expected RuntimeException was not raised during access of ' .
            'an out-of-range array index.'
        );
    }
    
    /**
     * Tests countability.
     */
    public function testCountability() {
        $size = 5;
        $instance = SerializableFixedArray::factory($size);
        $this->assertEquals($size, count($instance));
    }
    
    /**
     * Tests initialization directly from an array.
     */
    public function testArrayInitialization() {
        $comparison = array(
            'foo', 'baz', 1, null, 7, null, new \StdClass()
        );
        $instance = SerializableFixedArray::fromArray($comparison);
        $i = 0;
        foreach ($comparison as $value) {
            $this->assertSame($value, $instance[$i++]);
        }
        $this->assertEquals($i, count($instance));
    }
    
    /**
     * Tests stability during serialization and deserialization.
     */
    public function testSerialization() {
        $instance = SerializableFixedArray::factory(5);
        $instance[0] = new \StdClass();
        $instance[1] = 'foo';
        $instance[2] = 2;
        $instance[4] = array('bar', null, 1359);
        $expected = array(
            null, 'foo', 2, null, array('bar', null, 1359)
        );
        $deserializedInstance = unserialize(serialize($instance));
        for ($i = 0; $i < count($instance); $i++) {
            $this->assertEquals($instance[$i], $deserializedInstance[$i]);
        }
    }
}
?>