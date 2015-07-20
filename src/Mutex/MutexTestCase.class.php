<?php
class MutexTestCase extends TestHelpers\TestCase {
    private static $_mutexID = 12345;
    
    /**
     * This isn't really a test but rather a trick to facilitate launching a
     * separate process that blocks for two seconds.
     */
    public function testBlockInSeparateProcess() {
        $mutex = new Mutex(self::$_mutexID);
        $mutex->acquire();
        sleep(2);
        $mutex->release();
    }
    
    /**
     * Verifies that another process can successfully block this one.
     */
    public function testBlock() {
        $mutex = new Mutex(self::$_mutexID);
        $bootstrapFile = realpath(__DIR__ . '/../classLoader.inc.php');
        $this->assertNotFalse($bootstrapFile);
        $cmd = 'env phpunit --bootstrap=' . $bootstrapFile
             . ' --filter=testBlockInSeparateProcess ' . __FILE__
             . ' > /dev/null';
        $startTime = time();
        pclose(popen($cmd, 'r'));
        $mutex->acquire();
        $mutex->release();
        $this->assertGreaterThanOrEqual(2, time() - $startTime);
    }
    
    /**
     * Verifies that we get an exception if there appears to be a key
     * collision.
     */
    public function testKeyCollision() {
        $mutex = new Mutex($this);
        $lockID = $mutex->getLockID();
        /* Passing the key manually should cause an error, because the code
        will know that the first time this key was used, it was automatically
        derived. */
        $this->assertThrows(
            'MutexException',
            function() use ($lockID) { new Mutex($lockID); },
            null,
            'Instantiating a new Mutex with a value that causes a key collision failed to raise the expected exception.'
        );
    }
}
?>