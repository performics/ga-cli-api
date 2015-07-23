<?php
namespace TestHelpers;
if (!defined('PFX_UNIT_TEST')) {
    define('PFX_UNIT_TEST', true);
}

abstract class TestCase extends \PHPUnit_Framework_TestCase {
    protected $_lastException;
    
    /**
     * Generates a string containing $length bytes of text drawn from
     * characters between ASCII character codes 32 and 126.
     *
     * @param int $length
     * @return string
     */
    protected static function _generateRandomText($length) {
        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $text .= chr(mt_rand(32, 126));
        }
        return $text;
    }
    
    /**
     * Asserts that calling a given function/method throws an exception of the
     * specified type.
     *
     * @param string $exceptionType
     * @param callable $callable
     * @param array $args = null
     * @param string $message = ''
     */
    public function assertThrows(
        $exceptionType,
        $callable,
        array $args = null,
        $message = ''
    ) {
        $e = null;
        try {
            call_user_func_array($callable, $args ? $args : array());
        } catch (\Exception $e) {
            $this->_lastException = $e;
            if ($message) {
                $message .= PHP_EOL;
            }
            $message .= 'Caught an instance of ' . get_class($e)
                      . ' with message "' . $e->getMessage() . '".';
            $this->assertInstanceOf($exceptionType, $e, $message);
            return;
        }
        $this->fail('An instance of ' . $exceptionType . ' was not thrown.');
    }
}
?>