<?php
class LoggerTestCase extends TestHelpers\TempFileTestCase {
    private function _runBasicAssertionSet(
        Logger $logger,
        $logFile,
        $assertGZipped = false
    ) {
        $logger->log('hello');
        /* We need to flush the file buffer before examining the file contents
        or our assertions might fail. */
        $logger->flushWriteBuffer();
        // Now's the time to assert that this is a gzipped file if necessary
        if ($assertGZipped) {
            $fh = fopen($logFile, 'r');
            $this->assertEquals(pack('H*', '1f8b'), fread($fh, 2));
            fclose($fh);
        }
        $contents = self::_getFileContentsAsArray($logFile);
        $this->assertEquals(1, count($contents));
        $this->assertEquals('hello', substr($contents[0], -5));
        $logger->log('Hello again.');
        $logger->flushWriteBuffer();
        $contents = self::_getFileContentsAsArray($logFile);
        $this->assertEquals(2, count($contents));
        $this->assertEquals('Hello again.', substr($contents[1], -12));
        /* Make sure it works properly to write a series of lines and then
        flush the buffer. */
        $offset = count($contents);
        $lines = array();
        for ($i = 0; $i < 100; $i++) {
            $lines[] = $line = self::_generateRandomText(mt_rand(64, 2048));
            $logger->log($line);
        }
        $logger->flushWriteBuffer();
        $contents = self::_getFileContentsAsArray($logFile);
        $this->assertEquals($offset + 100, count($contents));
        for ($i = 0; $i < 100; $i++) {
            $this->assertEquals(
                $lines[$i],
                substr($contents[$i + $offset], strlen($lines[$i]) * -1)
            );
        }
    }
    
    public function testSimpleInstantiation() {
        $logFile = self::_createTempFile();
        $logger = new Logger($logFile);
        $this->_runBasicAssertionSet($logger, $logFile);
        /* The file does not need to exist at the time the logger is
        instantiated. */
        $logFile = self::_createTempFile();
        unlink($logFile);
        $logger = new Logger($logFile);
        $this->_runBasicAssertionSet($logger, $logFile);
        // GZip is used automatically if the file extension is .gz
        $logFile = self::_createTempFile('temp_zipped_log.txt.gz');
        $logger = new Logger($logFile);
        $this->_runBasicAssertionSet($logger, $logFile, true);
        $logFile = self::_createTempFile('temp_zipped_log_2.txt.gz');
        unlink($logFile);
        $logger = new Logger($logFile);
        $this->_runBasicAssertionSet($logger, $logFile, true);
        /* But we can force gzipping for any file, or plain text for a file
        with the .gz extension. */
        $logFile = self::_createTempFile();
        $logger = new Logger($logFile, null, null, true);
        $this->_runBasicAssertionSet($logger, $logFile, true);
        $logFile = self::_createTempFile('temp_unzipped_log.txt.gz');
        $logger = new Logger($logFile, null, null, false);
        $this->_runBasicAssertionSet($logger, $logFile);
    }
    
    public function testEmailing() {
        $tempFile = self::_createTempFile();
        $logger = new Logger($tempFile, 'joe.blow@website.com');
        /* By default, every logged message is included in the automatically-
        generated email; an individual logged message may be omitted from the
        email message by passing a false value as the second argument to the
        log() call. */
        $logger->log('This message should be included in the email.');
        $logger->log('This one, however, should not.', false);
        $logger->log('And this one should again.');
        $logger->flush();
        $logContents = self::_getFileContentsAsArray($tempFile);
        $this->assertEquals(3, count($logContents));
        $this->assertStringEndsWith(
            'This one, however, should not.', $logContents[1]
        );
        $this->assertNotContains(
            'This one, however, should not.', $GLOBALS['__lastEmailSent']['message']
        );
        $this->assertContains(
            'This message should be included in the email.',
            $GLOBALS['__lastEmailSent']['message']
        );
        $this->assertContains(
            'And this one should again.',
            $GLOBALS['__lastEmailSent']['message']
        );
        $this->assertEquals(
            'joe.blow@website.com', $GLOBALS['__lastEmailSent']['to']
        );
        /* Emails should automatically be dispatched when instances are
        destroyed. */
        $logger = new Logger(self::_createTempFile(), 'joe.blow@website.com');
        // Set a custom subject line this time
        $logger->setEmailSubject('My hovercraft is full of eels');
        $logger->log('foo');
        unset($logger);
        $this->assertEquals(
            'My hovercraft is full of eels', $GLOBALS['__lastEmailSent']['subject']
        );
        $this->assertStringEndsWith(
            'foo', $GLOBALS['__lastEmailSent']['message']
        );
    }
    
    public function testLazyFileOpening() {
        // Files shouldn't be opened until they are needed
        $file = self::$_tempDir . DIRECTORY_SEPARATOR . 'unused_log_file.txt';
        $logger = new Logger($file);
        $this->assertFalse(file_exists($file));
        unset($logger);
        $this->assertFalse(file_exists($file));
        // Whether it's gzipped shouldn't matter either
        $logger = new Logger($file, null, null, true);
        $this->assertFalse(file_exists($file));
        unset($logger);
        $this->assertFalse(file_exists($file));
        $file = self::_createTempFile();
        unlink($file);
        $logger = new Logger($file);
        $this->assertFalse(file_exists($file));
        $logger->log('asdf');
        $this->assertTrue(file_exists($file));
    }
    
    /**
     * Not a test, but facilitates mutex testing.
     *
     * @group meta
     */
    public function testMutexInSeparateProcess() {
        $mutex = new Mutex('Logger');
        $mutex->acquire();
        sleep(2);
        $mutex->release();
    }
    
    public function testMutex() {
        /* Most code that uses Logger instances probably passes it a specific
        mutex, but it will use a generic one if necessary. */
        $logFile = self::_createTempFile();
        $logger = new Logger($logFile);
        $bootstrapFile = realpath(__DIR__ . '/../classLoader.inc.php');
        $this->assertNotFalse($bootstrapFile);
        $cmd = 'env phpunit --no-configuration --bootstrap=' . $bootstrapFile
		     . ' --filter=testMutexInSeparateProcess ' . __FILE__
			 . ' > /dev/null';
        // This should block writes to the file for two seconds
        $time = time();
		pclose(popen($cmd, 'r'));
        $logger->log(time());
        $logger->flush();
        $content = self::_getFileContentsAsArray($logFile);
        $this->assertGreaterThanOrEqual(
            2, (int)substr($content[0], -10) - $time
        );
        /* This instance uses a differently-keyed mutex, so it shouldn't be
        blocked. */
        $logger = new Logger($logFile, null, new Mutex(__CLASS__));
        $time = time();
		pclose(popen($cmd, 'r'));
        $logger->log(time());
        $logger->flush();
        $content = self::_getFileContentsAsArray($logFile);
        $this->assertLessThan(
            2, (int)substr($content[0], -10) - $time
        );
    }
    
    public function testTimestamp() {
        $logFile = self::_createTempFile();
        $logger = new Logger($logFile);
        // By default, timestamps should be YYYY-MM-DD HH:MM:SS
        $logger->log('hello');
        $logger->flush();
        $this->assertRegExp(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
            self::_getLastLineFromFile($logFile)
        );
        // They may be any valid strftime()-compatible string
        $logger->setTimestampFormat(
            '  %A, %B %d, %Y at %I:%M %p was when this thing happened'
        );
        $logger->log('hello again');
        $logger->flush();
        $this->assertRegExp(
            '/^  [A-Za-z]+, [A-Za-z]+ \d{2}, \d{4} at \d{2}:\d{2} [AP]M was when this thing happened/',
            self::_getLastLineFromFile($logFile)
        );
        // We can turn them off too
        $logger->setTimestampFormat(null);
        $logger->log('hello a third time');
        $logger->flush();
        $this->assertEquals(
            'hello a third time', self::_getLastLineFromFile($logFile)
        );
    }
    
    public function testAppend() {
        /* If a logger instance puts content in a file and another one opens it
        up and puts more content in, we should be appending to what was there
        before. */
        $logFile = self::_createTempFile();
        // Use gzip for this because why not
        $logger = new Logger($logFile, null, null, true);
        $logger->log('Here is an entry from logger 1');
        $logger2 = new Logger($logFile, null, null, true);
        $logger2->log('Here is an entry from logger 2');
        $logger->flush();
        $logger2->flush();
        $content = self::_getFileContentsAsArray($logFile);
        $this->assertEquals(2, count($content));
        $this->assertStringEndsWith(
            'Here is an entry from logger 1', $content[0]
        );
        $this->assertStringEndsWith(
            'Here is an entry from logger 2', $content[1]
        );
    }
    
    public function testMirroringInExternalBuffer() {
        $logFile = self::_createTempFile();
        $logger = new Logger($logFile);
        $buffer = array();
        $logger->setExternalBuffer($buffer);
        for ($i = 0; $i < 100; $i++) {
            $logger->log(self::_generateRandomText(mt_rand(64, 2048)));
        }
        $logger->flush();
        $this->assertEquals($buffer, self::_getFileContentsAsArray($logFile));
    }
}
?>