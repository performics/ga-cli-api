<?php
class SharedMemoryTestCase extends TestHelpers\TestCase {
    private static $_mutex;
    private static $_testValues = array(
        'integer' => 3576,
        'string' => <<<EOF
The quick brown fox jumped over the lazy dog!
%@##%'
EOF
,
        'float' => 384199.5316,
        'array' => array('foo', 'bar', 'baz', 1, 7203, 2383.3, '')
    );
    
    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        self::$_mutex = new Mutex(12345);
    }
    
    private function _putValues(SharedMemory $mem) {
        foreach (self::$_testValues as $type => $value) {
            $mem->putVar($type, $value);
        }
    }
    
    private function _removeValues(SharedMemory $mem) {
        foreach (self::$_testValues as $type => $value) {
            $mem->removeVar($type);
        }
    }
    
    private function _runTests(SharedMemory $mem) {
        foreach (self::$_testValues as $type => $value) {
            $this->assertEquals(
                $value,
                $mem->getVar($type),
                'Equality test failed for variable "' . $type . '".'
            );
        }
        // Test in-place addition
        $val = $mem->getVar('integer');
        $operand = 20;
        $mem->addToVar('integer', $operand);
        $this->assertEquals($val + $operand, $mem->getVar('integer'));
        $val = $mem->getVar('float');
        $operand = -283.5;
        $mem->addToVar('float', $operand);
        $this->assertEquals(
            round($val + $operand, 2), round($mem->getVar('float'), 2)
        );
        // Now clear them out for the next test and make sure they're gone
        $this->_removeValues($mem);
        foreach (self::$_testValues as $type => $value) {
            $this->assertFalse($mem->hasVar($type));
        }
    }
    
    /**
     * Ensures that the storage of nulls is disallowed.
     *
     * @expectedException SharedMemoryException
     */
    public function testNullDisallowed() {
        $mem = new SharedMemory(self::$_mutex, 2048);
        $mem->putVar('badVar', null);
    }
    
    /**
     * Ensures that we can put objects of various types in shared memory and
     * retrieve them unchanged (within a process).
     */
    public function testIntraProcessUsage() {
        $mem = new SharedMemory(
            self::$_mutex,
            SharedMemory::getRequiredBytes(array_values(self::$_testValues))
        );
        $this->_putValues($mem);
        $this->_runTests($mem);
    }
    
    /**
     * Not a real test but necessary for $this->testInterProcessUsage().
     *
     * @group meta
     */
    public function testPutValues() {
        $mem = new SharedMemory(
            self::$_mutex,
            SharedMemory::getRequiredBytes(array_values(self::$_testValues))
        );
        $this->_putValues($mem);
    }
    
    /**
     * Ensures that we can put objects of various types in shared memory and
     * retrieve them unchanged (between processes).
     */
    public function testInterProcessUsage() {
        $mem = new SharedMemory(
            self::$_mutex,
            SharedMemory::getRequiredBytes(array_values(self::$_testValues))
        );
        $bootstrapFile = realpath(__DIR__ . '/../classLoader.inc.php');
        $this->assertNotFalse($bootstrapFile);
        $cmd = 'env phpunit --no-configuration --bootstrap=' . $bootstrapFile
             . ' --filter=testPutValues ' . __FILE__
             . ' > /dev/null';
        pclose(popen($cmd, 'r'));
        $this->_runTests($mem);
    }
}
?>