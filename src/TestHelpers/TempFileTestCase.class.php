<?php
namespace TestHelpers;

abstract class TempFileTestCase extends TestCase {
    protected static $_tempDir;
    protected static $_tempFiles = array();
    
    public static function setUpBeforeClass() {
        self::$_tempDir = sys_get_temp_dir();
    }
    
    public static function tearDownAfterClass() {
        foreach (self::$_tempFiles as $file) {
            if (is_file($file) || is_link($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Creates a temporary file in the directory defined in the environment
     * variable TEMP, optionally with a specific name (if possible) and
     * content. Returns the full path to the file.
     *
     * @param string $name = null
     * @param string $content = null
     * @return string
     */
    protected static function _createTempFile($name = null, $content = null) {
        if ($name === null) {
            $fullPath = tempnam(self::$_tempDir, 't');
            if ($fullPath === false) {
                throw new \Exception(
                    'Unable to create temporary file in ' . self::$_tempDir . '.'
                );
            }
        }
        else {
            $fullPath = self::$_tempDir . DIRECTORY_SEPARATOR . $name;
            if (file_exists($fullPath)) {
                throw new \Exception(
                    'Cannot create ' . $fullPath . ' as a temporary file ' .
                    'because it already exists.'
                );
            }
            $fh = fopen($fullPath, 'w');
            if (!$fh) {
                throw new \Exception(
                    'Unable to create named temporary file ' . $fullPath . '.'
                );
            }
            fclose($fh);
        }
        self::$_tempFiles[] = $fullPath;
        if ($content !== null) {
            $fh = fopen($fullPath, 'wb');
            if (!$fh) {
                throw new \Exception(
                    'Unable to open ' . $fullPath . ' for writing.'
                );
            }
            fwrite($fh, $content);
            fclose($fh);
        }
        return $fullPath;
    }
    
    /**
     * Returns the contents of the file as an array, split on PHP_EOL.
     *
     * @param string $file
     * @return array
     */
    protected static function _getFileContentsAsArray($file) {
        // Fortunately, gzopen() will handle reading uncompressed files too
        $fileContents = array();
        $fh = gzopen($file, 'r');
        $eolLen = strlen(PHP_EOL);
        while ($line = gzgets($fh)) {
            /* Here I'm not just doing an easy trimming of whitespace because I
            want to be sure that any trailing whitespace that was meant to be
            on this line of the file stays there. */
            if (substr($line, $eolLen * -1) == PHP_EOL) {
                $line = substr($line, 0, strlen($line) - $eolLen);
            }
            $fileContents[] = $line;
        }
        gzclose($fh);
        return $fileContents;
    }
    
    /**
     * Returns the last line from the file (based on whatever PHP_EOL is).
     *
     * @param string $file
     * @return string
     */
    protected static function _getLastLineFromFile($file) {
        $lines = self::_getFileContentsAsArray($file);
        return array_pop($lines);
    }
}
?>
