<?php
namespace DataStructures;

class SerializableQueueTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the polymorphic behavior is as expected.
     */
    public function testType() {
        $instance = SerializableQueue::factory();
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $this->assertInstanceOf('\SplQueue', $instance);
        }
        else {
            $this->assertInstanceOf(
                'DataStructures\SerializableQueue', $instance
            );
        }
    }
    
    /**
     * Tests stability during serialization and deserialization.
     */
    public function testSerialization() {
        $values = array(
            'foo', 'bar', null, array('a', 'b', 3), 5, new \StdClass()
        );
        $instance = SerializableQueue::factory();
        foreach ($values as $value) {
            $instance->enqueue($value);
        }
        $deserializedInstance = unserialize(serialize($instance));
        for ($i = 0; $i < count($instance); $i++) {
            $this->assertEquals($instance[$i], $deserializedInstance[$i]);
        }
        $this->assertEquals($i, count($values));
    }
}
?>