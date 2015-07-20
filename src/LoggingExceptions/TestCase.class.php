<?php
namespace LoggingExceptions;

class TestCase extends \TestHelpers\TempFileTestCase {
    private static $_logFile;
    private static $_logger;
    private static $_splExceptionHierarchy = array(
        array(
            'LogicException',
            array('BadFunctionCallException', 'BadMethodCallException'),
            'DomainException',
            'InvalidArgumentException',
            'LengthException',
            'OutOfRangeException'
        ),
        array(
            'RuntimeException',
            'OutOfBoundsException',
            'OverflowException',
            'RangeException',
            'UnderflowException',
            'UnexpectedValueException'
        )
    );
    
    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();
        self::$_logFile = self::_createTempFile();
        self::$_logger = new \Logger(self::$_logFile, 'admin@website.com');
    }
    
    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        /* Make sure that no other test suites that might throw
        LoggingExceptions try to use this logger instance. */
        Exception::unregisterLogger();
    }
    
    /**
     * Helper method to reformat the exception hierarchy as a flat list.
     *
     * @param array $hierarchy
     * @return array
     */
    private static function _flatten(array $hierarchy) {
        $flattened = array();
        foreach ($hierarchy as $member) {
            if (is_array($member)) {
                $flattened = array_merge($flattened, self::_flatten($member));
            }
            else {
                $flattened[] = $member;
            }
        }
        return $flattened;
    }
    
    /**
     * Helper method for iterating through the exception hierarchy recursively.
     *
     * @param string $parent
     * @param array $children
     */
    private function _testHierarchy($parent, array $children) {
        foreach ($children as $child) {
            if (is_array($child)) {
                $subParent = array_shift($child);
                /* Run the same assertions on the SPL exception and the
                LoggingException. */
                $this->assertEquals($parent, get_parent_class($subParent));
                $this->assertEquals(
                    __NAMESPACE__ . '\\' . $parent,
                    get_parent_class(__NAMESPACE__ . '\\' . $subParent)
                );
                $this->_testHierarchy($subParent, $child);
            }
            else {
                $this->assertEquals($parent, get_parent_class($child));
                $this->assertEquals(
                    __NAMESPACE__ . '\\' . $parent,
                    get_parent_class(__NAMESPACE__ . '\\' . $child)
                );
            }
        }
    }
    
    /**
     * Helper function used in the inspection of logged data to first assert
     * that a string begins with a pattern that looks like a timestamp, then
     * strip it and return the cleaned string.
     *
     * @param string $str
     * @param string $timestampRegex = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}: /'
     */
    private function _stripTimestamp(
        $str,
        $timestampRegex = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}: /'
    ) {
        $this->assertRegExp($timestampRegex, $str);
        return preg_replace($timestampRegex, '', $str);
    }
    
    /**
     * Tests that LoggingExceptions behave the same way as normal Exceptions
     * when no Logger instance has been registered.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testVanillaUsage() {
        $this->assertFalse(Exception::hasLogger());
        try {
            throw new Exception(
                'This should behave as a normal exception'
            );
        } catch (Exception $e) {
            /* There's no need to make any assertions about the exception's
            type, because we did so by catching it. */
            $this->assertEquals(
                'This should behave as a normal exception', $e->getMessage()
            );
        }
        try {
            throw new Exception(
                'This should behave as a normal exception',
                12345,
                new \Exception(
                    'And it should have a previous exception in the chain'
                )
            );
        } catch (Exception $e) {
            $this->assertSame(12345, $e->getCode());
            $this->assertEquals(
                'This should behave as a normal exception', $e->getMessage()
            );
            $this->assertEquals('Exception', get_class($e->getPrevious()));
            $this->assertEquals(
                'And it should have a previous exception in the chain',
                $e->getPrevious()->getMessage()
            );
        }
    }
    
    /**
     * Tests whether there is a LoggingException subclass that shadows each
     * member of the SPL exception subclass hierarchy.
     */
    public function testHierarchy() {
        $this->_testHierarchy('Exception', self::$_splExceptionHierarchy);
    }
    
    /**
     * Tests the logging of exceptions.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLogging() {
        // We're not allowing the Logger to send emails in this test
        Exception::registerLogger(self::$_logger, false);
        $hierarchy = self::_flatten(self::$_splExceptionHierarchy);
        // Do a vanilla LoggingException too
        array_unshift($hierarchy, 'Exception');
        $typeCount = count($hierarchy);
        for ($i = 0; $i < $typeCount; $i++) {
            $exceptionType = __NAMESPACE__ . '\\' . $hierarchy[$i];
            try {
                $message = 'Testing the logging behavior of ' . $exceptionType;
                throw new $exceptionType($message);
            } catch (Exception $e) {
                self::$_logger->flush();
                $content = self::_getFileContentsAsArray(self::$_logFile);
                $this->assertEquals($i + 1, count($content));
                $lastLine = $this->_stripTimestamp(array_pop($content));
                $this->assertStringStartsWith($exceptionType . ':', $lastLine);
                $this->assertContains($message, $lastLine);
            }
        }
        /* When a LoggingException is chained, the message builder looks
        backward in the chain until it finds another LoggingException, at which
        point it stops and finishes up (so that we don't get redundant messages
        in the log). */
        try {
            throw new InvalidArgumentException(
                'This is the outermost exception in a chain.',
                null,
                new DomainException('This is a chained LoggingException.')
            );
        } catch (Exception $e) {
            self::$_logger->flush();
            $content = self::_getFileContentsAsArray(self::$_logFile);
            // The last exception to be logged should be the outermost one
            $line = $this->_stripTimestamp(array_pop($content));
            $this->assertStringStartsWith(
                'LoggingExceptions\InvalidArgumentException:', $line
            );
            $this->assertContains(
                'This is the outermost exception in a chain.', $line
            );
            $line = $this->_stripTimestamp(array_pop($content));
            $this->assertStringStartsWith(
                'LoggingExceptions\DomainException:', $line
            );
            $this->assertContains(
                'This is a chained LoggingException.', $line
            );
        }
        try {
            throw new InvalidArgumentException(
                'This is the outermost exception in a chain.',
                null,
                new \DomainException(
                    'This is a chained SPL exception.',
                    null,
                    new \InvalidArgumentException(
                        'This is another chained SPL exception.',
                        null,
                        new OverflowException(
                            'This is a LoggingException.'
                        )
                    )
                )
            );
        } catch (Exception $e) {
            self::$_logger->flush();
            $content = self::_getFileContentsAsArray(self::$_logFile);
            /* We are expecting three lines from the outermost logging call.
            Only the first of the three will begin with a timestamp. */
            $lastCall = array_splice($content, -3);
            $lastCall[0] = $this->_stripTimestamp($lastCall[0]);
            $this->assertStringStartsWith(
                'LoggingExceptions\InvalidArgumentException (1 of 3)',
                $lastCall[0]
            );
            $this->assertContains(
                'This is the outermost exception in a chain.', $lastCall[0]
            );
            $this->assertStringStartsWith(
                'DomainException (2 of 3)',
                $lastCall[1]
            );
            $this->assertContains(
                'This is a chained SPL exception.', $lastCall[1]
            );
            $this->assertStringStartsWith(
                'InvalidArgumentException (3 of 3)',
                $lastCall[2]
            );
            $this->assertContains(
                'This is another chained SPL exception.', $lastCall[2]
            );
            /* The previous call should have been from the innermost
            LoggingException. */
            $lastCall = $this->_stripTimestamp(array_pop($content));
            $this->assertStringStartsWith(
                'LoggingExceptions\OverflowException:', $lastCall
            );
            $this->assertContains('This is a LoggingException.', $lastCall);
        }
        // Confirm that no email was sent
        if (class_exists('Email')) {
            $this->assertFalse(isset($GLOBALS['__lastEmailSent']));
        }
        else {
            $this->assertFalse(isset($GLOBALS['__lastEmailSent']['sent_time']));
        }
    }
    
    /**
     * Tests the logging and emailing of exceptions.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLoggingAndEmailing() {
        Exception::registerLogger(self::$_logger);
        try {
            throw new UnderflowException(
                'foo', null, new BadMethodCallException(
                    'bar', null, new \OverflowException('baz')
                )
            );
        } catch (\Exception $e) {}
        try {
            throw new \Exception(
                'aipd9-2j89 a3-9n ', null, new Exception(
                    'iojj -8 89-32 -a3', null, new \Exception('oaij 8j ag')
                )
            );
        } catch (\Exception $e) {}
        try {
            throw new BadFunctionCallException('bad function call');
        } catch (\Exception $e) {}
        self::$_logger->flush();
        /* Note that the log file's contents will have a trailing newline that
        the email message will not. */
        $this->assertEquals(
            rtrim(file_get_contents(self::$_logFile), PHP_EOL),
            $GLOBALS['__lastEmailSent']['message']
        );
        $this->assertEquals(
            self::$_logger->getEmailRecipient(),
            $GLOBALS['__lastEmailSent']['to']
        );
    }
    
    /**
     * Tests whether an OverflowException (not a
     * LoggingExceptions\OverflowException) is raised when there is an attempt
     * to register a Logger instance that conflicts with the existing one.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLoggerConflict() {
        Exception::registerLogger(self::$_logger);
        /* It's OK to make this call a second time if the file, email
        recipient, and automatic email settings match. */
        $logger = new \Logger(
            self::$_logger->getFile(), self::$_logger->getEmailRecipient()
        );
        Exception::registerLogger($logger);
        $this->assertThrows(
            'OverflowException',
            array('LoggingExceptions\Exception', 'registerLogger'),
            array($logger, false)
        );
        $this->assertThrows(
            'OverflowException',
            array('LoggingExceptions\Exception', 'registerLogger'),
            array(new \Logger(self::_createTempFile()))
        );
        $this->assertThrows(
            'OverflowException',
            array('LoggingExceptions\Exception', 'registerLogger'),
            array(new \Logger(self::$_logFile, 'foo@bar.baz'))
        );
        $this->assertThrows(
            'OverflowException',
            array('LoggingExceptions\Exception', 'registerLogger'),
            array(new \Logger(self::_createTempFile(), 'foo@bar.baz'))
        );
    }
}
?>