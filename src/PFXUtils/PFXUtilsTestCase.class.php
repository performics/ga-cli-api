<?php
class PFXUtilsTestCase extends TestHelpers\TempFileTestCase {
    /* Note that because of the differing inheritance needs, this test case
    only tests the PFXUtils methods that do not interact with the database. */
    
    private function _compress($file, $content, $gzip = true) {
        if ($gzip) {
            $fh = gzopen($file, 'wb');
            if (!$fh) {
                throw new RuntimeException(
                    'Unable to open ' . $file . ' for writing.'
                );
            }
            gzwrite($fh, $content);
            gzclose($fh);
            $newFile = $file . '.gz';
            if (rename($file, $newFile)) {
                self::$_tempFiles[] = $newFile;
            }
            else {
                throw new RuntimeException(
                    'Unable to rename ' . $file . ' to ' . $newFile . '.'
                );
            }
        }
        else {
            $newFile = $file . '.zip';
            $zip = new ZipArchive();
            $res = $zip->open($newFile, ZipArchive::CREATE);
            if ($res === true) {
                self::$_tempFiles[] = $newFile;
                $zip->addFromString(basename($file), $content);
                // In this case the original file isn't necessary
                unlink($file);
                $zip->close();
            }
            else {
                throw new RuntimeException(
                    'Got error code ' . $res . ' when attempting to open ' .
                    'zip archive ' . $newFile . '.'
                );
            }
        }
        // Just to make sure our subsequent assertions aren't false positives
        $this->assertFalse(file_exists($file));
        return $newFile;
    }
    
    /**
     * Not a test, but a helper method for $this->testCollapseArgs().
     *
     * @param string $argStr
     * @param array $shortArgs
     * @param array $longArgs = null
     * @param string $detectHelp = null
     * @param string $usageMessage = null
     * @param string $shortUsageMessage = null
     * @param boolean $rethrow = true
     * @return array, string
     */
    public function runCollapseArgsCommand(
        $argStr,
        array $shortArgs,
        array $longArgs = null,
        $detectHelp = null,
        $usageMessage = null,
        $shortUsageMessage = null,
        $rethrow = true
    ) {
        /* Serialize the arguments that will be passed to
        PFXUtils::collapseArgs() and append them to the argument string. */
        $serializableArgs = array($shortArgs);
        if ($longArgs !== null) {
            $serializableArgs[] = $longArgs;
        }
        if ($detectHelp !== null) {
            $serializableArgs[] = $detectHelp;
        }
        $argStr .= ' -- ' . escapeshellarg(serialize($serializableArgs));
        if ($usageMessage) {
            $argStr .= ' ' . escapeshellarg($usageMessage);
        }
        if ($shortUsageMessage) {
            if (!$usageMessage) {
                throw new InvalidArgumentException(
                    'Cannot specify a short usage message without specifying ' .
                    'a full usage message.'
                );
            }
            $argStr .= ' ' . escapeshellarg($shortUsageMessage);
        }
        exec(sprintf(
            '%s %s %s',
            PHP_BINARY,
            __DIR__ . DIRECTORY_SEPARATOR . 'test_collapse_args.php',
            $argStr
        ), $output, $returnVar);
        if ($returnVar == 0) {
            /* This may be the first time I've EVER used the @ operator, but I
            need to suppress the notice that unserialize() will issue if the
            data I'm passing to it isn't a valid serialized representation of a
            PHP value. We are checking the return value to determine that, and
            if I don't, PHPUnit interprets the test as having failed. */
            $outputStr = implode(PHP_EOL, $output);
            $unserialized = @unserialize($outputStr);
            if ($unserialized) {
                return $unserialized;
            }
            return $outputStr;
        }
        $matches = array();
        preg_match('/^(.*): (.*)$/', array_shift($output), $matches);
        $exceptionType = $matches[1];
        if ($rethrow) {
            throw new $exceptionType($matches[2]);
        }
        /* If we're not rethrowing, we're returning the remainder of what was
        printed to stdout so we can test that the appropriate usage message was
        present. */
        return trim(implode(PHP_EOL, $output));
    }
    
    /**
     * Tests PFXUtils::validateSettings().
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testValidateSettings() {
        // Test the lazy initialization of constants
        $settings = array(
            'PFXUTILS_TEST_SETTING_LAZY_1' => 'foo',
            'PFXUTILS_TEST_SETTING_LAZY_2' => 3
        );
        $settingTests = array(
            'PFXUTILS_TEST_SETTING_LAZY_1' => 'string',
            'PFXUTILS_TEST_SETTING_LAZY_2' => 'int'
        );
        PFXUtils::validateSettings($settings, $settingTests);
        $this->assertSame('foo', PFXUTILS_TEST_SETTING_LAZY_1);
        $this->assertSame(3, PFXUTILS_TEST_SETTING_LAZY_2);
        /* Test the mixing of predefined and lazily defined constants, with an
        optional one mixed in. */
        define('PFXUTILS_TEST_SETTING_1', true);
        define('PFXUTILS_TEST_SETTING_2', 'http://www.foo.bar');
        $settings = array(
            'PFXUTILS_TEST_SETTING_1' => null,
            'PFXUTILS_TEST_SETTING_2' => 'https://www.baz.foo',
            'PFXUTILS_TEST_SETTING_LAZY_1' => 'bar',
            'PFXUTILS_TEST_SETTING_LAZY_3' => self::$_tempDir
        );
        $settingTests = array(
            'PFXUTILS_TEST_SETTING_1' => 'bool',
            'PFXUTILS_TEST_SETTING_2' => 'url',
            'PFXUTILS_TEST_SETTING_3' => '?executable',
            'PFXUTILS_TEST_SETTING_LAZY_1' => 'string',
            'PFXUTILS_TEST_SETTING_LAZY_3' => 'directory'
        );
        PFXUtils::validateSettings($settings, $settingTests);
        $this->assertSame(true, PFXUTILS_TEST_SETTING_1);
        $this->assertSame('http://www.foo.bar', PFXUTILS_TEST_SETTING_2);
        $this->assertFalse(defined('PFXUTILS_TEST_SETTING_3'));
        $this->assertSame('foo', PFXUTILS_TEST_SETTING_LAZY_1);
        $this->assertSame(self::$_tempDir, PFXUTILS_TEST_SETTING_LAZY_3);
        // Make sure exceptions bubble up on failures
        $settings = array(
            'PFXUTILS_TEST_SETTING_3' => 'me@website.com',
            'PFXUTILS_TEST_SETTING_4' => null,
            'PFXUTILS_TEST_SETTING_5' => 'asdf'
        );
        $settingTests = array(
            'PFXUTILS_TEST_SETTING_3' => 'email',
            'PFXUTILS_TEST_SETTING_4' => 'str',
            'PFXUTILS_TEST_SETTING_5' => 'int',
            'PFXUTILS_TEST_NONEXISTENT_SETTING' => 'bool'
        );
        $this->assertThrows(
            'SettingException',
            array('PFXUtils', 'validateSettings'),
            array($settings, $settingTests)
        );
        // The constants will be defined, of course
        $this->assertSame('me@website.com', PFXUTILS_TEST_SETTING_3);
        $this->assertNull(PFXUTILS_TEST_SETTING_4);
        $this->assertSame('asdf', PFXUTILS_TEST_SETTING_5);
        /* Make sure that the validation doesn't pass until all the tests are
        changed appropriately. */
        unset($settingTests['PFXUTILS_TEST_NONEXISTENT_SETTING']);
        $this->assertThrows(
            'SettingException',
            array('PFXUtils', 'validateSettings'),
            array($settings, $settingTests)
        );
        $settingTests['PFXUTILS_TEST_SETTING_4'] = '?str';
        $this->assertThrows(
            'SettingException',
            array('PFXUtils', 'validateSettings'),
            array($settings, $settingTests)
        );
        $settingTests['PFXUTILS_TEST_SETTING_5'] = 'str';
        PFXUtils::validateSettings($settings, $settingTests);
    }
    
    /**
     * Tests PFXUtils::testSettingTypes().
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTestSettingTypes() {
        define('PFXUTILS_TEST_SETTING_REGEX_MATCH_PASS_1', 'foo');
        define('PFXUTILS_TEST_SETTING_REGEX_MATCH_PASS_2', '  foo ');
        define('PFXUTILS_TEST_SETTING_REGEX_MATCH_PASS_3', 'Foobar');
        define('PFXUTILS_TEST_SETTING_REGEX_MATCH_FAIL_1', 'bar ');
        define('PFXUTILS_TEST_SETTING_OPTIONAL_REGEX_MATCH_PASS_1', ' foo');
        define('PFXUTILS_TEST_SETTING_OPTIONAL_REGEX_MATCH_PASS_2', null);
        define('PFXUTILS_TEST_SETTING_BOOLEAN_PASS_1', true);
        define('PFXUTILS_TEST_SETTING_BOOLEAN_PASS_2', false);
        define('PFXUTILS_TEST_SETTING_BOOLEAN_FAIL_1', 0);
        define('PFXUTILS_TEST_SETTING_BOOLEAN_FAIL_2', '');
        define('PFXUTILS_TEST_SETTING_BOOLEAN_FAIL_3', 1);
        define('PFXUTILS_TEST_SETTING_BOOLEAN_FAIL_4', 'true');
        define('PFXUTILS_TEST_SETTING_BOOLEAN_FAIL_5', null);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_BOOLEAN_PASS_1', false);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_BOOLEAN_PASS_2', null);
        define('PFXUTILS_TEST_SETTING_STRING_PASS_1', '');
        define('PFXUTILS_TEST_SETTING_STRING_PASS_2', 'asdf');
        define('PFXUTILS_TEST_SETTING_STRING_FAIL_1', null);
        define('PFXUTILS_TEST_SETTING_STRING_FAIL_2', 72);
        define('PFXUTILS_TEST_SETTING_STRING_FAIL_3', true);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_STRING_PASS_1', 'ao dsoigra 0a428h7 ');
        define('PFXUTILS_TEST_SETTING_OPTIONAL_STRING_PASS_2', null);
        define('PFXUTILS_TEST_SETTING_INTEGER_PASS_1', 0);
        define('PFXUTILS_TEST_SETTING_INTEGER_PASS_2', 293834);
        define('PFXUTILS_TEST_SETTING_INTEGER_PASS_3', '-2398034');
        define('PFXUTILS_TEST_SETTING_INTEGER_FAIL_1', 0.1);
        define('PFXUTILS_TEST_SETTING_INTEGER_FAIL_2', '0.1');
        define('PFXUTILS_TEST_SETTING_INTEGER_FAIL_3', 'a8924h898hrwga');
        define('PFXUTILS_TEST_SETTING_INTEGER_FAIL_4', null);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_INTEGER_PASS_1', 1);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_INTEGER_PASS_2', null);
        /* Make sure all the tests that are meant to pass the integer tests
        pass the numeric tests as well. */
        define('PFXUTILS_TEST_SETTING_NUM_PASS_1', 0.1);
        define('PFXUTILS_TEST_SETTING_NUM_PASS_2', '0.1');
        define('PFXUTILS_TEST_SETTING_NUM_PASS_3', 0x01ff);
        define('PFXUTILS_TEST_SETTING_NUM_PASS_4', '1.2345e5');
        define('PFXUTILS_TEST_SETTING_NUM_FAIL_1', 'zero');
        define('PFXUTILS_TEST_SETTING_NUM_FAIL_2', true);
        define('PFXUTILS_TEST_SETTING_NUM_FAIL_3', null);
        define('PFXUTILS_TEST_SETTING_NUM_FAIL_4', 'dsiapin op98ae9 8ba9d');
        define('PFXUTILS_TEST_SETTING_OPTIONAL_NUM_PASS_1', '23');
        define('PFXUTILS_TEST_SETTING_OPTIONAL_NUM_PASS_2', null);
        define('PFXUTILS_TEST_SETTING_RATIO_PASS_1', 0);
        define('PFXUTILS_TEST_SETTING_RATIO_PASS_2', 1);
        define('PFXUTILS_TEST_SETTING_RATIO_PASS_3', 2 / 3);
        define('PFXUTILS_TEST_SETTING_RATIO_PASS_4', '0.7');
        define('PFXUTILS_TEST_SETTING_RATIO_FAIL_1', -1);
        define('PFXUTILS_TEST_SETTING_RATIO_FAIL_2', '1.1');
        define('PFXUTILS_TEST_SETTING_RATIO_FAIL_3', '1/2');
        define('PFXUTILS_TEST_SETTING_OPTIONAL_RATIO_PASS_1', 0.234);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_RATIO_PASS_2', null);
        $tempFile = self::_createTempFile();
        self::$_tempFiles[] = $tempLink = self::$_tempDir . DIRECTORY_SEPARATOR . uniqid();
        symlink($tempFile, $tempLink);
        define('PFXUTILS_TEST_SETTING_FILE_PASS_1', $tempFile);
        define('PFXUTILS_TEST_SETTING_FILE_PASS_2', $tempLink);
        define('PFXUTILS_TEST_SETTING_FILE_FAIL_1', dirname($tempFile));
        define('PFXUTILS_TEST_SETTING_FILE_FAIL_2', 'asdofijasdf');
        define('PFXUTILS_TEST_SETTING_FILE_FAIL_3', null);
        define('PFXUTILS_TEST_SETTING_FILE_FAIL_4', true);
        define('PFXUTILS_TEST_SETTING_FILE_FAIL_5', 3);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_FILE_PASS_1', $tempFile);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_FILE_PASS_2', null);
        define('PFXUTILS_TEST_SETTING_DIR_PASS_1', dirname($tempFile));
        define('PFXUTILS_TEST_SETTING_DIR_FAIL_1', dirname($tempFile) . DIRECTORY_SEPARATOR);
        define('PFXUTILS_TEST_SETTING_DIR_FAIL_2', null);
        define('PFXUTILS_TEST_SETTING_DIR_FAIL_3', $tempFile);
        define('PFXUTILS_TEST_SETTING_DIR_FAIL_4', 'asdf');
        define('PFXUTILS_TEST_SETTING_DIR_FAIL_5', true);
        define('PFXUTILS_TEST_SETTING_DIR_FAIL_6', 98027);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_DIR_PASS_1', __DIR__);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_DIR_PASS_2', null);
        define('PFXUTILS_TEST_SETTING_WRITABLE_PASS_1', $tempFile);
        define('PFXUTILS_TEST_SETTING_WRITABLE_PASS_2', self::_createTempFile());
        define('PFXUTILS_TEST_SETTING_WRITABLE_FAIL_1', '/some/path/that/does/not/exist');
        define('PFXUTILS_TEST_SETTING_WRITABLE_FAIL_2', null);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_WRITABLE_PASS_1', $tempFile);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_WRITABLE_PASS_2', null);
        define('PFXUTILS_TEST_SETTING_EMAIL_PASS_1', 'me@website.com');
        define('PFXUTILS_TEST_SETTING_EMAIL_PASS_2', 'me@website.com,you@other-website.com');
        define('PFXUTILS_TEST_SETTING_EMAIL_FAIL_1', 'me@');
        define('PFXUTILS_TEST_SETTING_EMAIL_FAIL_2', false);
        define('PFXUTILS_TEST_SETTING_EMAIL_FAIL_3', 3);
        define('PFXUTILS_TEST_SETTING_EMAIL_FAIL_4', 'sadfoij');
        define('PFXUTILS_TEST_SETTING_EMAIL_FAIL_5', null);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_EMAIL_PASS_1', 'me@website.com');
        define('PFXUTILS_TEST_SETTING_OPTIONAL_EMAIL_PASS_2', null);
        // I hope this isn't too brittle of an assumption
        define('PFXUTILS_TEST_SETTING_EXECUTABLE_PASS_1', '/usr/bin/php');
        define('PFXUTILS_TEST_SETTING_EXECUTABLE_FAIL_1', 'oisajdf');
        define('PFXUTILS_TEST_SETTING_EXECUTABLE_FAIL_2', true);
        define('PFXUTILS_TEST_SETTING_EXECUTABLE_FAIL_3', null);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_EXECUTABLE_PASS_1', '/usr/bin/php');
        define('PFXUTILS_TEST_SETTING_OPTIONAL_EXECUTABLE_PASS_2', null);
        /* Since this validation is actually done by the URL class under the
        hood and that is pretty thoroughly covered with unit tests, I'm not
        going to go nuts testing multiple URL variants here. */
        define('PFXUTILS_TEST_SETTING_URL_PASS_1', 'http://www.foo.bar/?a=1');
        define('PFXUTILS_TEST_SETTING_URL_PASS_2', 'http://asdf');
        define('PFXUTILS_TEST_SETTING_URL_FAIL_1', 'www.foo.bar');
        define('PFXUTILS_TEST_SETTING_URL_FAIL_2', 'asdf');
        define('PFXUTILS_TEST_SETTING_URL_FAIL_3', 29376);
        define('PFXUTILS_TEST_SETTING_URL_FAIL_4', false);
        define('PFXUTILS_TEST_SETTING_URL_FAIL_5', null);
        define('PFXUTILS_TEST_SETTING_OPTIONAL_URL_PASS_1', 'http://www.foo.bar/?a=1');
        define('PFXUTILS_TEST_SETTING_OPTIONAL_URL_PASS_2', null);
        $tests = array(
            'boolean',
            'string',
            'integer',
            'num',
            'ratio',
            'file',
            'dir',
            'writable',
            'email',
            'executable',
            'url'
        );
        // Build a settings array containing one setting of each type
        $settings = array();
        foreach ($tests as $test) {
            $settings['PFXUTILS_TEST_SETTING_' . strtoupper($test) . '_PASS_1'] = $test;
        }
        // Add some regex tests
        $settings['PFXUTILS_TEST_SETTING_REGEX_MATCH_PASS_1'] = '/foo/';
        $settings['PFXUTILS_TEST_SETTING_REGEX_MATCH_PASS_2'] = '/foo/';
        $settings['PFXUTILS_TEST_SETTING_REGEX_MATCH_PASS_3'] = '/^foo/i';
        PFXUtils::testSettingTypes($settings);
        // Try with a mixture of passing and failing settings
        $settings = array(
            'PFXUTILS_TEST_SETTING_REGEX_MATCH_FAIL_1' => '/foo/',
            'PFXUTILS_TEST_SETTING_STRING_PASS_2' => 'str',
            'PFXUTILS_TEST_SETTING_BOOLEAN_FAIL_1' => 'bool',
            'PFXUTILS_TEST_SETTING_INTEGER_FAIL_1' => 'int',
            'PFXUTILS_TEST_SETTING_NUM_PASS_2' => 'number'
        );
        $this->assertThrows(
            'SettingException',
            array('PFXUtils', 'testSettingTypes'),
            array($settings)
        );
        // Remove the failing ones and try again
        unset($settings['PFXUTILS_TEST_SETTING_REGEX_MATCH_FAIL_1']);
        unset($settings['PFXUTILS_TEST_SETTING_BOOLEAN_FAIL_1']);
        unset($settings['PFXUTILS_TEST_SETTING_INTEGER_FAIL_1']);
        PFXUtils::testSettingTypes($settings);
        /* Try a mix of normal settings, ones that are optional and set
        correctly, and ones that are optional and not set. */
        $settings = array(
            'PFXUTILS_TEST_SETTING_BOOLEAN_PASS_2' => 'boolean',
            'PFXUTILS_TEST_SETTING_INTEGER_PASS_3' => 'integer',
            'PFXUTILS_TEST_SETTING_OPTIONAL_REGEX_MATCH_PASS_1' => '?/foo$/',
            'PFXUTILS_TEST_SETTING_OPTIONAL_BOOLEAN_PASS_2' => '?bool'
        );
        PFXUtils::testSettingTypes($settings);
        /* Now try the same thing, including one that is optional but set to
        something inappropriate. */
        $settings = array(
            'PFXUTILS_TEST_SETTING_NUM_PASS_3' => 'numeric',
            'PFXUTILS_TEST_SETTING_OPTIONAL_NUM_PASS_1' => '?num',
            'PFXUTILS_TEST_SETTING_NUM_FAIL_4' => '?number',
            'PFXUTILS_TEST_SETTING_OPTIONAL_REGEX_MATCH_PASS_2' => '?/foo/',
            'PFXUTILS_TEST_SETTING_OPTIONAL_DIR_PASS_1' => '?directory'
        );
        $this->assertThrows(
            'SettingException',
            array('PFXUtils', 'testSettingTypes'),
            array($settings)
        );
        unset($settings['PFXUTILS_TEST_SETTING_NUM_FAIL_4']);
        PFXUtils::testSettingTypes($settings);
        // Now make sure all the test constants get tested
        foreach ($tests as $test) {
            $i = 0;
            while (true) {
                $constName = 'PFXUTILS_TEST_SETTING_' . strtoupper($test)
                           . '_PASS_' . ++$i;
                if (!defined($constName)) {
                    break;
                }
                PFXUtils::testSettingTypes(array($constName => $test));
            }
        }
        foreach ($tests as $test) {
            $i = 0;
            while (true) {
                $constName = 'PFXUTILS_TEST_SETTING_' . strtoupper($test)
                           . '_FAIL_' . ++$i;
                if (!defined($constName)) {
                    break;
                }
                $this->assertThrows(
                    'SettingException',
                    array('PFXUtils', 'testSettingTypes'),
                    array(array($constName => $test)),
                    'Failed to throw expected exception when testing ' . $constName . '.'
                );
            }
        }
    }
    
    /**
     * Tests PFXUtils::arrayToCSV().
     */
    public function testArrayToCSV() {
        $expected = <<<EOF
Foo,Bar,Baz
hello,my,friend
how,are,you
today,,
EOF;
        $input = array(
            array('Foo', 'Bar', 'Baz'),
            array('hello', 'my', 'friend'),
            array('how', 'are', 'you'),
            array('today', null, null)
        );
        $this->assertEquals($expected, PFXUtils::arrayToCSV($input));
        // Change up the delimiter
        $expected = str_replace(',', ';', $expected);
        $this->assertEquals($expected, PFXUtils::arrayToCSV($input, ';'));
        /* A formatted line should contain one fewer delimiters than elements
        in the corresponding array. */
        $expected = <<<EOF
Foo,Bar,Baz
hello,my,friend
how,are,you
today?
I,am,fine.
EOF;
        $input = array(
            array('Foo', 'Bar', 'Baz'),
            array('hello', 'my', 'friend'),
            array('how', 'are', 'you'),
            array('today?'),
            array('I', 'am', 'fine.')
        );
        $this->assertEquals($expected, PFXUtils::arrayToCSV($input));
        // Again, with a different delimiter
        $expected = str_replace(',', '&amp;', $expected);
        $this->assertEquals($expected, PFXUtils::arrayToCSV($input, '&amp;'));
        // Delimiters or EOL characters embedded in data should trigger quoting
        $expected = <<<EOF
1,"2,3",4,5
a,b,"c, d", e,f 

EOF
. "\"two\nlines\"," . <<<EOF
2.356
foo,bar,baz,
EOF
. "\"flarf\r\n\"";
        $input = array(
            array(1, '2,3', 4, 5),
            array('a', 'b', 'c, d', ' e', 'f '), // The extra space should be reproduced
            array("two\nlines", 2.356),
            array('foo', 'bar', 'baz', "flarf\r\n")
        );
        $this->assertEquals($expected, PFXUtils::arrayToCSV($input));
        /* If data is quoted due to the presence of delimiters, embedded quotes
        should be doubled. */
        $expected = <<<EOF
Foo ;Bar ;Baz 
12";5',10";"7';9"""
a;b;c
EOF;
        $input = array(
            array('Foo ', 'Bar ', 'Baz '),
            array('12"', '5\',10"', '7\';9"'),
            array('a', 'b', 'c')
        );
        $this->assertEquals($expected, PFXUtils::arrayToCSV($input, ';'));
        // How about tabs?
        $expected = "Foo\tBar\n1\t2\t3\na,b\t\"c\t\"";
        $input = array(
            array('Foo', 'Bar'),
            array(1, 2, 3),
            array('a,b', "c\t")
        );
        $this->assertEquals($expected, PFXUtils::arrayToCSV($input, "\t"));
    }
    
    public function testWriteINIFile() {
        $file = self::_createTempFile();
        $data = array(
            'foo' => 'bar',
            'bar' => 3.2,
            'baz' => '"Robert"',
            'quux' => array('foo', 'bar', 'baz')
        );
        $expected = <<<EOF
foo = "bar"
bar = 3.2
baz = "\"Robert\""
quux[] = "foo"
quux[] = "bar"
quux[] = "baz"

EOF;
        PFXUtils::writeINIFile($data, $file);
        $this->assertEquals($expected, file_get_contents($file));
        // Append something
        PFXUtils::writeINIFile(array('horse' => 'secretariat'), $file, true);
        $expected .= 'horse = "secretariat"' . "\n";
        $this->assertEquals($expected, file_get_contents($file));
        $this->assertThrows(
            'RuntimeException',
            array('PFXUtils', 'writeINIFile'),
            array($data, '/some/file/that/does/not/exist')
        );
    }
    
    /**
     * Tests PFXUtils::emptyBadScriptTags().
     */
    public function testEmptyBadScriptTags() {
        $html = <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en">
<head>
<base href="http://www.mylespaul.com/forums/" /><!--[if IE]></base><![endif]-->
	<link rel="canonical" href="http://www.mylespaul.com/forums/other-single-cuts/340107-commissioning-replica-build-2015-whos-there.html" />
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
<meta name="generator" content="vBulletin 3.8.7" />
<script type="text/javascript" src="http://www.mylespaul.com/forums/clientscript/yui/yahoo-dom-event/yahoo-dom-event.js?v=387"></script>
<script type="text/javascript" src="http://www.mylespaul.com/forums/clientscript/yui/connection/connection-min.js?v=387"></script>
<script type="text/javascript">
<!--
var SESSIONURL = "";
var SECURITYTOKEN = "1433955396-8bc9b759ee4e63ae3496e274566a20ffe571fadd";
var IMGDIR_MISC = "images/misc";
var vb_disable_ajax = parseInt("0", 10);
// -->
</script>
<script type="text/javascript" src="http://www.mylespaul.com/forums/clientscript/vbulletin_global.js?v=387"></script>
<script type="text/javascript" src="http://www.mylespaul.com/forums/clientscript/vbulletin_menu.js?v=387"></script>
<script type="text/javascript" src="http://partner.googleadservices.com/gampad/google_service.js">
</script>
<script type="text/javascript">
  GS_googleAddAdSenseService("ca-pub-3910297843321261");
  GS_googleEnableAllServices();
</script>
<script type="text/javascript">
  GA_googleAddSlot("ca-pub-3910297843321261", "MyLesPaul_468x60");
</script>
<script type="text/javascript">
  GA_googleFetchAds();
</script>
</head>
<body>
<script>
document.write('<div>foo</div>');
</script>
<div align="right">
<a href="http://www.mylespaul.com">Homepage</a> - 
<a href="http://www.mylespaul.com/forums/sponsor-classifieds/">Sponsors</a> - 
<a href="http://www.mylespaul.com/forums/payments.php">Subscription</a> - 
<a href="http://www.mylespaul.com/auction">Auctions</a> - 
<a href="http://www.mylespaul.com/advertise">Advertise</a> - 
<a href="http://www.mylespaul.com/forums/spy.php">Spy</a> &nbsp;
</div>
</body>
</html>
EOF;
        $expected = <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en">
<head>
<base href="http://www.mylespaul.com/forums/" /><!--[if IE]></base><![endif]-->
	<link rel="canonical" href="http://www.mylespaul.com/forums/other-single-cuts/340107-commissioning-replica-build-2015-whos-there.html" />
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
<meta name="generator" content="vBulletin 3.8.7" />
<script type="text/javascript" src="http://www.mylespaul.com/forums/clientscript/yui/yahoo-dom-event/yahoo-dom-event.js?v=387"></script>
<script type="text/javascript" src="http://www.mylespaul.com/forums/clientscript/yui/connection/connection-min.js?v=387"></script>
<script type="text/javascript">"removed";</script>
<script type="text/javascript" src="http://www.mylespaul.com/forums/clientscript/vbulletin_global.js?v=387"></script>
<script type="text/javascript" src="http://www.mylespaul.com/forums/clientscript/vbulletin_menu.js?v=387"></script>
<script type="text/javascript" src="http://partner.googleadservices.com/gampad/google_service.js">
</script>
<script type="text/javascript">
  GS_googleAddAdSenseService("ca-pub-3910297843321261");
  GS_googleEnableAllServices();
</script>
<script type="text/javascript">
  GA_googleAddSlot("ca-pub-3910297843321261", "MyLesPaul_468x60");
</script>
<script type="text/javascript">
  GA_googleFetchAds();
</script>
</head>
<body>
<script>"removed";</script>
<div align="right">
<a href="http://www.mylespaul.com">Homepage</a> - 
<a href="http://www.mylespaul.com/forums/sponsor-classifieds/">Sponsors</a> - 
<a href="http://www.mylespaul.com/forums/payments.php">Subscription</a> - 
<a href="http://www.mylespaul.com/auction">Auctions</a> - 
<a href="http://www.mylespaul.com/advertise">Advertise</a> - 
<a href="http://www.mylespaul.com/forums/spy.php">Spy</a> &nbsp;
</div>
</body>
</html>
EOF;
        $this->assertEquals($expected, PFXUtils::emptyBadScriptTags($html));
        $html = <<<EOF
<!DOCTYPE html>
<html class="no-js">
<head>
    <title>22" Sonor Bass Drum Resonant</title>
    <meta name="robots" content="NOARCHIVE,NOFOLLOW">
	<link rel="canonical" href="http://chicago.craigslist.org/wcl/msg/5060382546.html">
	<meta name="description" content="22 Sonor drum head by remo in good condition, black ebony with white writing.">
	<meta name="twitter:card" content="preview">
	<meta property="og:description" content="22 Sonor drum head by remo in good condition, black ebony with white writing.">
	<meta property="og:image" content="http://images.craigslist.org/01313_cQu3kNofYfN_600x450.jpg">
	<meta property="og:site_name" content="craigslist">
	<meta property="og:title" content="22 Sonor Bass Drum Resonant">
	<meta property="og:type" content="article">
	<meta property="og:url" content="http://chicago.craigslist.org/wcl/msg/5060382546.html">
    <meta name="viewport" content="initial-scale=1.0, user-scalable=1">
    <link type="text/css" rel="stylesheet" media="all" href="//www.craigslist.org/styles/cl.css?v=7f3bc5bed8cde572b9862753ab355fe4">
    
    <!--[if lt IE 9]>
<script src="//www.craigslist.org/js/html5shiv.min.js?v=096822b653643ed1af3136947e4ea79a" type="text/javascript" ></script>
<![endif]-->
<!--[if lte IE 7]>
<script src="//www.craigslist.org/js/json2.min.js?v=178d4ad319e0e0b4a451b15e49b71bec" type="text/javascript" ></script>
<![endif]-->
</head>

<body class="posting">


    <article id="pagecontainer">
        <div class="bglogo"></div>
        <header class="bchead">
    <form id="breadcrumbform" method="get" action="" data-action="">
        
        <nav class="contents closed">
            <div class="breadbox">
                <ul class="breadcrumbs">
                    <li class="crumb cl"><a href="/">CL</a></li><li class="crumb area"><a href="/">chicago</a> &gt;</li><li class="crumb subarea"><a href="/wcl/">west chicagoland</a> &gt;</li><li class="crumb section"><a href="/wcl/sss">for sale</a> &gt;</li><li class="crumb category"><a href="/wcl/msg">musical instruments - by owner</a> <span class="no-js"> <input type="submit" value="go"></span></li>
                </ul>
                <ul class="userlinks">
    <li class="user post"><a href="https://post.craigslist.org/c/chi?lang=en">post</a></li>
    <li class="user account"><em>[ </em><a href="https://accounts.craigslist.org/login/home">account</a><em> ]</em></li>
    <li class="user fav"><div class="favorites">
    <a href="#" class="favlink"><span class="n">0</span><span class="no-mobile"> favorites</span></a>
</div></li>
    <li><div class="menu-button">&mdash; &mdash; &mdash;</div></li>
</ul>
            </div>
            <div class="clearfix"></div>
        </nav>
    </form>
</header>
        <section class="body">
            <section class="dateReplyBar">
    
        <script type="text/javascript">
            var isPreview = "";
var bestOf = "";
var buttonPostingID = "5060382546";

        </script>
    

<button class="reply_button js-only">reply <span class="envelope">&#9993;</span> <span class="phone">&#9742;</span></button>
    <span class="replylink"><a id="replylink" href="/reply/chi/msg/5060382546">reply</a></span>

<div class="returnemail js-only"></div>

    <aside class="flags">
    <a class="flaglink" data-flag="28" href="https://post.craigslist.org/flag?flagCode=28&amp;postingID=5060382546&amp;subareaid=3&amp;areaid=11&amp;cat=msg&amp;area=chi" title="flag as prohibited / spam / miscategorized"><span class="flag">x</span> <span class="flagtext">prohibited</span></a><sup>[<a href="http://www.craigslist.org/about/prohibited">?</a>]</sup>
</aside>
    <p id="display-date" class="postinginfo reveal">Posted: <time datetime="2015-06-05T18:17:13-0500">2015-06-05  6:17pm</time></p>
    <div class="prevnext js-only">
    <a class="prevnext prev">&#9664;  prev </a>
    <a class="backup" title="back to search">&#9650;</a>
    <a class="prevnext next"> next &#9654; </a>
</div>
    
    <a href="#" id="printme">print</a>
</section>

<h2 class="postingtitle">
  <span class="star"></span>
  <span class="postingtitletext">22" Sonor Bass Drum Resonant - <span class="price">&#x0024;30</span><small> (Bellwood)</small></span>
</h2>
<section class="userbody">
    <figure class="iw">
    

    <div class="slidernav">
        <span class="sliderback">&lt;</span>
        <span class="sliderinfo"></span>
        <span class="sliderforward">&gt;</span>
    </div>

    <div class="carousel oneimage">
        <div class="tray"><div id="1_image_cQu3kNofYfN" data-imgid="cQu3kNofYfN" class="slide first visible"><img src="http://images.craigslist.org/01313_cQu3kNofYfN_600x450.jpg" title="image 1" alt="image 1"></div></div>
    </div>

    
    
        <script type="text/javascript">
            var imgList = [{"shortid":"cQu3kNofYfN","url":"http://images.craigslist.org/01313_cQu3kNofYfN_600x450.jpg","thumb":"http://images.craigslist.org/01313_cQu3kNofYfN_50x50c.jpg","imgid":"0:01313_cQu3kNofYfN"}];
var imageText = "image";

        </script>
    
</figure>

    <div class="mapAndAttrs">
        
        
        
        <div class="no-mobile">
            <aside class="tsb">
    <ul>
        <li><a href="//www.craigslist.org/about/safety">safety tips</a>
        <li><a href="//www.craigslist.org/about/prohibited">prohibited items</a>
        <li><a href="//www.craigslist.org/about/recalled_items">product recalls</a>
        <li><a href="//www.craigslist.org/about/scams">avoiding scams</a>
    </ul>
</aside>
            <div id="printcontact"></div><p>
            <div id="qrcode"></div>
        </div>
    </div>

    <section id="postingbody">
        22" Sonor drum head by remo in good condition, black ebony with white writing.
    </section>


    <ul class="notices"><li>do NOT contact me with unsolicited services or offers</li></ul>

    <div class="postinginfos">
        <p class="postinginfo">post id: 5060382546</p>
        <p class="postinginfo reveal">posted: <time datetime="2015-06-05T18:17:13-0500">2015-06-05  6:17pm</time></p>
        <p class="postinginfo reveal">updated: <time datetime="2015-06-10T11:47:42-0500">2015-06-10 11:47am</time></p>
               <p class="postinginfo"><a href="https://accounts.craigslist.org/eaf?postingID=5060382546&amp;token=U2FsdGVkX18zODM2MzgzNntdoZkgC2ew4_vLxjKNzADf92XPkdM_MsL3EMa9gOHV99ZUEo9PyQqxHTDueaB9B6QGoQNbEnDu" class="tsb">email to friend</a></p>
               <p class="postinginfo"><a class="bestoflink" data-flag="9" href="https://post.craigslist.org/flag?flagCode=9&amp;postingID=5060382546&amp;" title="nominate for best-of-CL"><span class="bestof">&hearts; </span><span class="bestoftext">best of</span></a> <sup>[<a href="http://www.craigslist.org/about/best-of-craigslist">?</a>]</sup>
</p>
    </div>
    <div id="printpics"></div>

</section>

<div class="no-mobile">
    <aside class="tsb">
    <p><a href="//www.craigslist.org/about/scams">Avoid scams, deal locally</a>

    <em>Beware wiring (e.g. Western Union), cashier checks, money orders, shipping.</em>
    <br>
</aside>
</div>

<div class="mobile-only">
    <aside class="tsb">
    <ul>
        <li><a href="//www.craigslist.org/about/safety">safety tips</a>
        <li><a href="//www.craigslist.org/about/prohibited">prohibited items</a>
        <li><a href="//www.craigslist.org/about/recalled_items">product recalls</a>
        <li><a href="//www.craigslist.org/about/scams">avoiding scams</a>
    </ul>
</aside>
</div>


        </section>
        <footer>
    
    <ul class="clfooter">
        <li>&copy; 2015 <span class="desktop">craigslist</span><span class="mobile">CL</span></li>
        <li><a href="//www.craigslist.org/about/help/">help</a></li>
        <li><a href="//www.craigslist.org/about/scams">safety</a></li>
        <li class="desktop"><a href="//www.craigslist.org/about/privacy.policy">privacy</a></li>
        <li class="desktop"><a href="https://forums.craigslist.org/?forumID=8">feedback</a></li>
        <li class="desktop"><a href="//www.craigslist.org/about/craigslist_is_hiring">cl jobs</a></li>
        <li><a href="//www.craigslist.org/about/terms.of.use">terms</a></li>
        <li><a href="//www.craigslist.org/about/">about</a></li>
        <li class="fsel desktop linklike" data-mode="mobile">mobile</li>
        <li class="fsel mobile linklike" data-mode="regular">desktop</li>
    </ul>
</footer>
    </article>

    
        <script type="text/javascript">
            var countOfTotalText = "{count} of {total}";
var pID = "5060382546";

        </script>
    
    <script src="//www.craigslist.org/js/general-concat.min.js?v=dae4b3254666fd8bf88c511841a8ce94" type="text/javascript" ></script>
    <script type="text/javascript">
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = '//www.' + CL.url.baseDomain + '/static/localstorage.html?v=51a29e41f8e978141e4085ed4a77d170';
        document.body.insertBefore(iframe, null);
    </script>
    
    <script src="//www.craigslist.org/js/postings-concat.min.js?v=048e9182f83dd72bae32bcfa351511d9" type="text/javascript" ></script>
</body>
</html>
EOF;
        $this->assertEquals($html, PFXUtils::emptyBadScriptTags($html));
    }
    
    /**
     * Tests PFXUtils::collapseArgs().
     */
    public function testCollapseArgs() {
        /* This is a pretty unorthodox test due to the fact that we can't
        maniuplate the argument vector from within this process. To solve that
        problem we have a helper script that will be responsible for making
        the actual calls to PFXUtils::collapseArgs for us. */
        PFXUtils::validateSettings(
            array('PHP_BINARY' => null), array('PHP_BINARY' => 'executable')
        );
        $this->assertTrue(file_exists(
            __DIR__ . DIRECTORY_SEPARATOR . 'test_collapse_args.php'
        ));
        $expected = array('foo' => true);
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '--foo', array('f'), array('foo')
        ));
        // Should get the same thing with the short form
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-f', array('f'), array('foo')
        ));
        // With an argument
        $expected = array('foo' => 'bar');
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '--foo=bar', array('f:'), array('foo:')
        ));
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-f=bar', array('f:'), array('foo:')
        ));
        // Omitting a mandatory argument should spell death
        $this->assertThrows(
            'InvalidArgumentException',
            array($this, 'runCollapseArgsCommand'),
            array(
                '-f=bar',
                array('f:', '!b:'),
                // Note that we only have to indicate the mandatory flag once
                array('foo:', 'bar:')
            )
        );
        // Put the mandatory flag on the long form only
        $this->assertThrows(
            'InvalidArgumentException',
            array($this, 'runCollapseArgsCommand'),
            array(
                '-f=bar',
                array('f:', 'b:'),
                array('foo:', '!bar:')
            )
        );
        // How about on both
        $this->assertThrows(
            'InvalidArgumentException',
            array($this, 'runCollapseArgsCommand'),
            array(
                '-f=bar',
                array('f:', '!b:'),
                array('foo:', '!bar:')
            )
        );
        // But omitting a non-mandatory argument should be OK
        $expected = array('bar' => 'foo');
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '--bar=foo --baz', array('f:', '!b:'), array('foo:', 'bar:')
        ));
        /* We should be able to mix arguments that come in both forms,
        arguments that only have a short form, and arguments that only have a
        long form, provided they line up in the arrays we pass. */
        $expected = array('a' => true, 'bee' => '2', 'cee' => 'three');
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-a -b=2 --cee=three',
            array('a', 'b:'),
            array(null, 'bee:', 'cee:')
        ));
        /* If an available boolean argument is not supplied, it should
        automatically be resolved as false. */
        $expected = array('foo' => true, 'bar' => false);
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-f', array('f', 'b'), array('foo', 'bar')
        ));
        $expected = array('a' => false, 'b' => true, 'c' => false);
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-b', array('a', 'b', 'c')
        ));
        // Multiple invocations should end up in an array
        $expected = array('f' => array('bar', 'baz'), 'a' => 'b');
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-f=bar -f=baz -a=b', array('f:', 'a:')
        ));
        $expected = array('foo' => array('bar', 'baz'), 'a' => 'b');
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-f=bar -f=baz -a=b', array('f:', 'a:'), array('foo:')
        ));
        /* Even if a short form is used for one invocation, and the long form
        for another. */
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-f=bar -a=b --foo=baz', array('f:', 'a:'), array('foo:')
        ));
        /* If PFX_USAGE_MESSAGE is defined, and the command includes --help (or
        something that maps to it), PFXUtils::collapseArgs() will normally
        print it and exit. */
        $usageMessage = <<<EOF
Some kind of helpful message.
It will probably be spread across several lines.
EOF;
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-f=bar -a=b --foo=baz', array('f:', 'a:'), array('foo:')
        ));
        $this->assertEquals($usageMessage, $this->runCollapseArgsCommand(
            '-f=bar -a=b --foo=baz --help',
            array('f:', 'a:', 'h'),
            array('foo:', null, 'help'),
            null,
            $usageMessage
        ));
        // This happens even if required arguments are missing
        $this->assertEquals($usageMessage, $this->runCollapseArgsCommand(
            '-h',
            array('!f:', 'a:', 'h'),
            array('foo:', null, 'help'),
            null,
            $usageMessage
        ));
        // But if we turn this off, we get the normal behavior
        $expected['help'] = true;
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '-f=bar -a=b --foo=baz --help',
            array('f:', 'a:', 'h'),
            array('foo:', null, 'help'),
            false,
            $usageMessage
        ));
        $this->assertThrows(
            'InvalidArgumentException',
            array($this, 'runCollapseArgsCommand'),
            array(
                '-h',
                array('!f:', 'a:', 'h'),
                array('foo:', null, 'help'),
                false,
                $usageMessage
            )
        );
        $shortUsageMessage = <<<EOF
A shorter message.
Also probably multiple lines.
EOF;
        /* If both a usage message and a short usage message are passed, the
        short one is used if there is an error in the command line arguments,
        while the long one is used if we ask for help. */
        $this->assertEquals($usageMessage, $this->runCollapseArgsCommand(
            '--help',
            array('!f:', 'h'),
            array('!foo:', 'help'),
            true,
            $usageMessage,
            $shortUsageMessage,
            false
        ));
        $this->assertEquals($shortUsageMessage, $this->runCollapseArgsCommand(
            '--baz',
            array('!f:', 'h'),
            array('!foo:', 'help'),
            true,
            $usageMessage,
            $shortUsageMessage,
            false
        ));
    }
    
    /**
     * Tests PFXUtils::initNestedArrays().
     */
    public function testInitNestedArrays() {
        $expected = array(
            'foo' => array(
                'bar' => null
            )
        );
        $nestedArrays = array();
        PFXUtils::initNestedArrays(array('foo', 'bar'), $nestedArrays);
        $this->assertEquals($expected, $nestedArrays);
        // We can add a key to an existing array
        $expected['bar'] = array('baz' => null);
        PFXUtils::initNestedArrays(array('bar', 'baz'), $nestedArrays);
        $this->assertEquals($expected, $nestedArrays);
        /* The addition can be done deeper, and with an arbitrary deepest
        element. */
        $expected['foo']['baz'] = array('zab' => new stdClass());
        PFXUtils::initNestedArrays(
            array('baz', 'zab'), $expected['foo'], new stdClass()
        );
        /* It can also be used to initialize a single element although that's
        silly. */
        $nestedArrays = array();
        $expected = array(
            0 => array('foo' => 'bar', 'baz' => array(1, 2, 3))
        );
        PFXUtils::initNestedArrays(
            array(0),
            $nestedArrays,
            array('foo' => 'bar', 'baz' => array(1, 2, 3))
        );
        $this->assertEquals($expected, $nestedArrays);
    }
    
    /**
     * Tests PFXUtils::nestedArrayKeyExists().
     */
    public function testNestedArrayKeyExists() {
        $structure = array(
            'foo' => array(
                'foo' => 'bar',
                'bar' => array(0, 1, 3),
                'baz' => array(
                    'abc' => 'def',
                    'ghi' => 'jkl'
                )
            ),
            'bar' => array(
                'foo' => null,
                'baz' => false,
                'borg' => array('foo' => array('foo' => 'bar'))
            )
        );
        $this->assertTrue(PFXUtils::nestedArrayKeyExists(
            array('foo', 'foo'), $structure
        ));
        $this->assertTrue(PFXUtils::nestedArrayKeyExists(
            array('foo', 'bar', 0), $structure
        ));
        $this->assertTrue(PFXUtils::nestedArrayKeyExists(
            array('foo', 'baz', 'ghi'), $structure
        ));
        $this->assertTrue(PFXUtils::nestedArrayKeyExists(
            array('bar', 'foo'), $structure
        ));
        $this->assertTrue(PFXUtils::nestedArrayKeyExists(
            array('bar', 'baz'), $structure
        ));
        $this->assertTrue(PFXUtils::nestedArrayKeyExists(
            array('bar', 'borg'), $structure
        ));
        $this->assertTrue(PFXUtils::nestedArrayKeyExists(
            array('bar', 'borg', 'foo'), $structure
        ));
        $this->assertTrue(PFXUtils::nestedArrayKeyExists(
            array('bar', 'borg', 'foo', 'foo'), $structure
        ));
        $this->assertFalse(PFXUtils::nestedArrayKeyExists(
            array('baz'), $structure
        ));
        $this->assertFalse(PFXUtils::nestedArrayKeyExists(
            array('foo', 'borg'), $structure
        ));
        $this->assertFalse(PFXUtils::nestedArrayKeyExists(
            array('foo', 'foo', 'bar'), $structure
        ));
        $this->assertFalse(PFXUtils::nestedArrayKeyExists(
            array('foo', 'bar', 3), $structure
        ));
        $this->assertFalse(PFXUtils::nestedArrayKeyExists(
            array('foo', 'baz', 'jkl'), $structure
        ));
        $this->assertFalse(PFXUtils::nestedArrayKeyExists(
            array('bar', 'bar'), $structure
        ));
        $this->assertFalse(PFXUtils::nestedArrayKeyExists(
            array('bar', 'baz', 'borf'), $structure
        ));
        $this->assertFalse(PFXUtils::nestedArrayKeyExists(
            array('bar', 'borg', 'foo', 'baz'), $structure
        ));
    }
    
    /**
     * Tests PFXUtils::inflate().
     */
    public function testInflate() {
        $content = <<<EOF
This is some test content for a file that will be compressed in several
different ways.

8936(*#%&)#%(*

S98JW98W
EOF;
        $tempFile = self::_createTempFile();
        $compressed = $this->_compress($tempFile, $content);
        $this->assertEquals('.gz', substr($compressed, -3));
        self::$_tempFiles[] = $inflated = PFXUtils::inflate($compressed);
        $this->assertFalse(file_exists($compressed));
        $this->assertEquals($content, file_get_contents($inflated));
        // Should get the same result with a zip
        $tempFile = self::_createTempFile();
        $compressed = $this->_compress($tempFile, $content, false);
        $this->assertEquals('.zip', substr($compressed, -4));
        self::$_tempFiles[] = $inflated = PFXUtils::inflate($compressed);
        $this->assertFalse(file_exists($compressed));
        $this->assertEquals($content, file_get_contents($inflated));
        // Make sure extensions are preserved
        $tempFile = self::_createTempFile('foo.csv');
        $compressed = $this->_compress($tempFile, $content);
        self::$_tempFiles[] = $inflated = PFXUtils::inflate($compressed);
        $this->assertEquals('.csv', substr($inflated, -4));
        $this->assertFalse(file_exists($compressed));
        $this->assertEquals($content, file_get_contents($inflated));
        $tempFile = self::_createTempFile('bar.csv');
        $compressed = $this->_compress($tempFile, $content, false);
        self::$_tempFiles[] = $inflated = PFXUtils::inflate($compressed);
        $this->assertEquals('.csv', substr($inflated, -4));
        $this->assertFalse(file_exists($compressed));
        $this->assertEquals($content, file_get_contents($inflated));
    }
    
    /**
     * Tests PFXUtils::searchArray().
     */
    public function testSearchArray() {
        $searchArray = array(
            '1',
            'aa',
            'bar',
            'baz',
            'zee'
        );
        $this->assertEquals(3, PFXUtils::searchArray('baz', $searchArray));
        $searchArray[] = 'zeee';
        $this->assertEquals(3, PFXUtils::searchArray('baz', $searchArray));
        array_unshift($searchArray, '0');
        $this->assertEquals(4, PFXUtils::searchArray('baz', $searchArray));
        /* Loose matching is problematic due to the fact that, for example, "1"
        compares as less than "bar", while 1 compares as greater than "bar".
        For this reason, it's not supported. */
        $this->assertFalse(PFXUtils::searchArray(1, $searchArray));
        $this->assertEquals(1, PFXUtils::searchArray("1", $searchArray));
        $this->assertFalse(PFXUtils::searchArray('foo', $searchArray));
        // We can find the next lowest or highest value
        $this->assertEquals(2, PFXUtils::searchArray(
            'az', $searchArray, 0, null, PFXUtils::ARRAY_SEARCH_NEXTLOWEST
        ));
        $this->assertEquals(4, PFXUtils::searchArray(
            'bax', $searchArray, 0, null, PFXUtils::ARRAY_SEARCH_NEXTHIGHEST
        ));
        // Of course, exact matches are still preferred
        $this->assertEquals(3, PFXUtils::searchArray(
            'bar', $searchArray, 0, null, PFXUtils::ARRAY_SEARCH_NEXTLOWEST
        ));
        $this->assertEquals(4, PFXUtils::searchArray(
            'baz', $searchArray, 0, null, PFXUtils::ARRAY_SEARCH_NEXTHIGHEST
        ));
        /* If there is no next lowest or next highest match, the corresponding
        search types do not indicate a match. */
        $this->assertFalse(PFXUtils::searchArray(
            '-1', $searchArray, 0, null, PFXUtils::ARRAY_SEARCH_NEXTLOWEST
        ));
        $this->assertFalse(PFXUtils::searchArray(
            'zz', $searchArray, 0, null, PFXUtils::ARRAY_SEARCH_NEXTHIGHEST
        ));
        $this->assertSame(0, PFXUtils::searchArray(
            '-1', $searchArray, 0, null, PFXUtils::ARRAY_SEARCH_NEXTHIGHEST
        ));
        $this->assertSame(count($searchArray) - 1, PFXUtils::searchArray(
            'zz', $searchArray, 0, null, PFXUtils::ARRAY_SEARCH_NEXTLOWEST
        ));
        // Try a bigger array
        $searchArray = array();
        for ($i = 0; $i < 1000; $i++) {
            $searchArray[] = $i * 3;
        }
        /* I'm only going from 1 to 999 because I have some assertions around
        finding the next lowest and next highest items. */
        for ($i = 1; $i < 999; $i++) {
            $searchVal = $i * 3;
            $this->assertEquals($searchVal / 3, PFXUtils::searchArray(
                $searchVal, $searchArray
            ));
            $this->assertFalse(PFXUtils::searchArray(
                (string)$searchVal,
                $searchArray,
                0,
                null,
                PFXUtils::ARRAY_SEARCH_STRICTMATCH
            ));
            $this->assertEquals(($searchVal / 3) + 1, PFXUtils::searchArray(
                $searchVal + 1,
                $searchArray,
                0,
                null,
                PFXUtils::ARRAY_SEARCH_NEXTHIGHEST
            ));
            $this->assertEquals($searchVal / 3, PFXUtils::searchArray(
                $searchVal + 1,
                $searchArray,
                0,
                null,
                PFXUtils::ARRAY_SEARCH_NEXTLOWEST
            ));
            $this->assertEquals(($searchVal / 3) - 1, PFXUtils::searchArray(
                $searchVal - 1,
                $searchArray,
                0,
                null,
                PFXUtils::ARRAY_SEARCH_NEXTLOWEST
            ));
            $this->assertEquals($searchVal / 3, PFXUtils::searchArray(
                $searchVal - 1,
                $searchArray,
                0,
                null,
                PFXUtils::ARRAY_SEARCH_NEXTHIGHEST
            ));
        }
        // We can also use a custom getter to examine the value
        $searchArray = array();
        for ($i = 0; $i < 20; $i++) {
            $obj = new stdClass();
            $obj->prop = $i * 3;
            $searchArray[] = $obj;
        }
        $this->assertFalse(PFXUtils::searchArray(
            '12',
            $searchArray,
            0,
            null,
            PFXUtils::ARRAY_SEARCH_STRICTMATCH,
            function($obj) { return $obj->prop; }
        ));
        $this->assertEquals(4, PFXUtils::searchArray(
            12,
            $searchArray,
            0,
            null,
            PFXUtils::ARRAY_SEARCH_STRICTMATCH,
            function($obj) { return $obj->prop; }
        ));
        $this->assertFalse(PFXUtils::searchArray(
            13,
            $searchArray,
            0,
            null,
            PFXUtils::ARRAY_SEARCH_STRICTMATCH,
            function($obj) { return $obj->prop; }
        ));
        $this->assertEquals(4, PFXUtils::searchArray(
            13,
            $searchArray,
            0,
            null,
            PFXUtils::ARRAY_SEARCH_NEXTLOWEST,
            function($obj) { return $obj->prop; }
        ));
        $this->assertEquals(5, PFXUtils::searchArray(
            13,
            $searchArray,
            0,
            null,
            PFXUtils::ARRAY_SEARCH_NEXTHIGHEST,
            function($obj) { return $obj->prop; }
        ));
        // Try a test with floats
        $searchArray = array();
        for ($i = 0; $i < 1000; $i++) {
            $obj = new stdClass();
            $obj->prop = $i + (mt_rand(0, 999999) / 1000000);
            $searchArray[] = $obj;
        }
        for ($i = 1; $i < 1000; $i++) {
            /* I'm not going to test strict match searching here, because as
            these are floats, it may or may not succeed. Searching for the next
            highest or lowest value is the way to go here. */
            $this->assertEquals($i, PFXUtils::searchArray(
                $i,
                $searchArray,
                0,
                null,
                PFXUtils::ARRAY_SEARCH_NEXTHIGHEST,
                function($obj) { return $obj->prop; }
            ));
            $this->assertEquals($i - 1, PFXUtils::searchArray(
                $i,
                $searchArray,
                0,
                null,
                PFXUtils::ARRAY_SEARCH_NEXTLOWEST,
                function($obj) { return $obj->prop; }
            ));
        }
        // Various bad arguments will cause an exception to be thrown
        $this->assertThrows(
            'InvalidArgumentException',
            array('PFXUtils', 'searchArray'),
            array(null, $searchArray) // Not a scalar
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array('PFXUtils', 'searchArray'),
            array(array('foo', 'bar'), $searchArray) // Also not a scalar
        );
        /* This array does not appear to be numerically indexed, although it
        would be trivial to fool the method into using an associative array by
        just adding the key 0. */
        $this->assertThrows(
            'InvalidArgumentException',
            array('PFXUtils', 'searchArray'),
            array(1, array('foo' => 'bar'))
        );
        // Not a valid callable
        $this->assertThrows(
            'InvalidArgumentException',
            array('PFXUtils', 'searchArray'),
            array(1, $searchArray, 0, null, PFXUtils::ARRAY_SEARCH_STRICTMATCH, 'asdf')
        );
        // Bad search type
        $this->assertThrows(
            'InvalidArgumentException',
            array('PFXUtils', 'searchArray'),
            array(1, $searchArray, 0, null, 'asdf')
        );
    }
    
    /**
     * Tests PFXUtils::guessEncoding().
     */
    public function testGuessEncoding() {
        $content = 'The ɋuick brőwn fox jumped ōver the lazy dog.';
        $file = self::_createTempFile(null, $content);
        $fh = fopen($file, 'r');
        $this->assertEquals('UTF-8', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        fclose($fh);
        // Now try an explicit UTF-8 BOM
        $file = self::_createTempFile(null, "\xef\xbb\xbf" . $content);
        $fh = fopen($file, 'r');
        $this->assertEquals('UTF-8', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        fclose($fh);
        // UTF-16BE
        $file = self::_createTempFile(null, "\xfe\xff" . $content);
        $fh = fopen($file, 'r');
        $this->assertEquals('UTF-16BE', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        /* Even though we just advanced the pointer, we should still get the
        same result if we do the same tests again, because the method will
        rewind it for us. */
        $this->assertEquals('UTF-16BE', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        fclose($fh);
        // UTF-16LE
        $file = self::_createTempFile(null, "\xff\xfe" . $content);
        $fh = fopen($file, 'r');
        $this->assertEquals('UTF-16LE', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        $this->assertEquals('UTF-16LE', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        fclose($fh);
        // UTF-32BE
        $file = self::_createTempFile(null, "\x00\x00\xfe\xff" . $content);
        $fh = fopen($file, 'r');
        $this->assertEquals('UTF-32BE', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        $this->assertEquals('UTF-32BE', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        fclose($fh);
        // UTF-32LE
        $file = self::_createTempFile(null, "\xfe\xff\x00\x00" . $content);
        $fh = fopen($file, 'r');
        $this->assertEquals('UTF-32LE', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        $this->assertEquals('UTF-32LE', PFXUtils::guessEncoding($fh));
        $this->assertEquals($content, fread($fh, strlen($content)));
        fclose($fh);
    }
    
    /**
     * Tests PFXUtils::guessEOL() and PFXUtils::guessEOLInString().
     */
    public function testGuessEOL() {
        $content = <<<EOF
Hello.
Is it me you're looking for?

EOF;
        $file = self::_createTempFile(null, $content);
        $fh = fopen($file, 'r');
        $this->assertEquals("\n", PFXUtils::guessEOL($fh));
        $this->assertEquals("\n", PFXUtils::guessEOLInString($content));
        fclose($fh);
        $content = str_replace("\n", "\r\n", $content);
        $file = self::_createTempFile(null, $content);
        $fh = fopen($file, 'r');
        $this->assertEquals("\r\n", PFXUtils::guessEOL($fh));
        $this->assertEquals("\r\n", PFXUtils::guessEOLInString($content));
        fclose($fh);
        $content = str_replace("\r\n", "\r", $content);
        $file = self::_createTempFile(null, $content);
        $fh = fopen($file, 'r');
        $this->assertEquals("\r", PFXUtils::guessEOL($fh));
        $this->assertEquals("\r", PFXUtils::guessEOLInString($content));
        fclose($fh);
    }
    
    /**
     * Tests PFXUtils::fgetsMB().
     */
    public function testfgetsMB() {
        /* Although this sort of violates the spirit of unit testing, I am
        engineering a test that is designed with knowledge of how
        PFXUtils::fgetsMB() works internally. It reads from the file handle in
        chunks of 4096 bytes, and one of the edge cases to worry about is using
        a two-byte EOL character and having it split between two fread() calls.
        For that reason I am using a string whose length in bytes (45) is a
        factor of 4095 so that I can just repeat it a bunch of times. */
        $content = 'The quick brown fox jumped over the lazy dog.';
        $contentLength = strlen($content);
        $this->assertEquals(45, $contentLength);
        $contentEncoding = 'UTF-8';
        $tempFile = self::_createTempFile();
        $eolChars = array("\n", "\r", "\r\n");
        $sourceEncodings = mb_list_encodings();
        $skipEncodings = array(
            'UUENCODE',
            'Quoted-Printable',
            /* This is problematic due to the way mb_convert_encoding breaks up
            lines into 78-byte chunks on the way out. */
            'BASE64',
            /* It seems like this character set uses some meta characters that
            interfere with the back-and-forth conversion. */
            'SJIS-2004'
        );
        $targetEncodings = array('UTF-8', 'ASCII');
        foreach ($sourceEncodings as $sourceEncoding) {
            if (in_array($sourceEncoding, $skipEncodings)) {
                continue;
            }
            foreach ($eolChars as $eol) {
                foreach ($targetEncodings as $targetEncoding) {
                    // Set up the content
                    $expectedContent = '';
                    $fh = fopen($tempFile, 'wb');
                    /* Start with the big long line that's designed to test our
                    edge case handling for UTF-8 and Windows-style line
                    endings. */
                    $line = str_repeat($content, 4095 / $contentLength) . $eol;
                    $expectedContent .= $line;
                    if ($sourceEncoding != $contentEncoding) {
                        $line = mb_convert_encoding(
                            $line, $sourceEncoding, $contentEncoding
                        );
                    }
                    fwrite($fh, $line);
                    // Write a few more lines of varying lengths
                    $lineCount = mt_rand(6, 50);
                    for ($i = 0; $i < $lineCount; $i++) {
                        $line = str_repeat(' ', mt_rand(1, 100)) . $content;
                        $charCount = mt_rand(1, 100);
                        for ($j = 0; $j < $charCount; $j++) {
                            $line .= chr(mt_rand(32, 126));
                        }
                        $line .= $eol;
                        $expectedContent .= $line;
                        if ($sourceEncoding != $contentEncoding) {
                            $line = mb_convert_encoding(
                                $line, $sourceEncoding, $contentEncoding
                            );
                        }
                        fwrite($fh, $line);
                    }
                    fclose($fh);
                    $buffer = '';
                    $first = true;
                    $fh = fopen($tempFile, 'rb');
                    $fileContent = '';
                    while ($line = PFXUtils::fgetsMB(
                        $fh, $buffer, $sourceEncoding, $targetEncoding, $eol
                    )) {
                        if ($first) {
                            /* The first line should be equal to our content
                            multiplied by our multiplier followed by the EOL
                            character. */
                            $this->assertEquals(
                                str_repeat($content, 4095 / $contentLength) . $eol,
                                $line,
                                'The first line did not contain the expected ' .
                                'content when reading content encoded as ' .
                                $sourceEncoding . ' and converting to ' .
                                $targetEncoding . ' and using the EOL style ' .
                                str_replace(array("\r", "\n"), array('CR', 'LF'), $eol) .
                                '.'
                            );
                            $first = false;
                        }
                        $fileContent .= $line;
                    }
                    $this->assertEquals(
                        $expectedContent,
                        $fileContent,
                        'Transparent character set conversion failed when ' .
                        'reading content encoded as ' .
                        $sourceEncoding . ' and converting to ' .
                        $targetEncoding . ' and using the EOL style ' .
                        str_replace(array("\r", "\n"), array('CR', 'LF'), $eol) .
                        '.'
                    );
                }
            }
        }
    }
    
    /**
     * Tests PFXUtils::printUsage().
     */
    public function testPrintUsage() {
        $PFX_USAGE_MESSAGE = <<<EOF
{FILE} is the name of this file.
{PAD} This should be aligned with the "is" in the last line.
{PAD} So should this.
EOF;
        $expected = <<<EOF
test_collapse_args.php is the name of this file.
                       This should be aligned with the "is" in the last line.
                       So should this.
EOF;
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '--help',
            array(),
            array('help'),
            true,
            $PFX_USAGE_MESSAGE
        ));
        // Try it with something before the initial {FILE} placeholder
        $PFX_USAGE_MESSAGE = <<<EOF
Usage: {FILE} (no command line args, sorry)
{PAD} What did you expect from a little test script?
EOF;
        $expected = <<<EOF
Usage: test_collapse_args.php (no command line args, sorry)
                              What did you expect from a little test script?
EOF;
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '--help',
            array(),
            array('help'),
            true,
            $PFX_USAGE_MESSAGE
        ));
        // Try it with {FILE} placeholders on multiple lines
        $PFX_USAGE_MESSAGE = <<<EOF
Usage: {FILE} (no command line args, sorry)
{PAD} What did you expect from a little test script?
Or: {FILE} --help
{PAD} I guess you can at least ask for help.
EOF;
        $expected = <<<EOF
Usage: test_collapse_args.php (no command line args, sorry)
                              What did you expect from a little test script?
Or: test_collapse_args.php --help
                           I guess you can at least ask for help.
EOF;
        $this->assertEquals($expected, $this->runCollapseArgsCommand(
            '--help',
            array(),
            array('help'),
            true,
            $PFX_USAGE_MESSAGE
        ));
    }
    
    /**
     * Tests PFXUtils::escape().
     */
    public function testEscape() {
        $str = <<<EOF
"Robert's" house
EOF;
        $expected = <<<EOF
\"Robert\'s\" house
EOF;
        $escapeChars = <<<EOF
"'
EOF;
        $this->assertEquals($expected, PFXUtils::escape($str, $escapeChars));
        // Maybe we just want to escape the o
        $expected = <<<EOF
"R\obert's" h\ouse
EOF;
        $this->assertEquals($expected, PFXUtils::escape($str, 'o'));
        /* The escape character itself is implicitly escaped, as long as it's
        not already escaping an escapable character. */
        $str = <<<EOF
"R\obert\'s" house
EOF;
        /* Note that this isn't literally what we expect...we are escaping the
        escape character for PHP's benefit. */
        $expected = <<<EOF
"R\obert\\\'s" h\ouse
EOF;
        $this->assertEquals($expected, PFXUtils::escape($str, 'o'));
        // We can use an alternate escape character if we want
        $str = <<<EOF
"Robert's" house
is a real dump.
EOF;
        $expected = <<<EOF
'"Robert''s'" house
is a real dump'.
EOF;
        $this->assertEquals($expected, PFXUtils::escape($str, '".', "'"));
        // Multiple-character escape sequences are not allowed
        $this->assertThrows(
            'InvalidArgumentException',
            array('PFXUtils', 'escape'),
            array($str, '".', "''")
        );
    }
    
    /**
     * Tests PFXUtils::explodeUnescaped().
     */
    public function testExplodeUnescaped() {
        $str = 'foo;bar\;baz;boo';
        $expected = array('foo', 'bar\;baz', 'boo');
        $this->assertEquals($expected, PFXUtils::explodeUnescaped(';', $str));
        // We can use a different escape character
        $str = <<<EOF
   some -guy
wants toghit me. What the heck is that about?g.
EOF;
        $expected = array(
            "   some -guy\nwants to",
            'hit me. What the heck is that about?',
            '.'
        );
        $this->assertEquals(
            $expected, PFXUtils::explodeUnescaped('g', $str, '-')
        );
    }
    
    /**
     * Tests PFXUtils::quantify().
     */
    public function testQuantify() {
        $this->assertEquals(
            '1 monkey',
            PFXUtils::quantify(1, 'monkey')
        );
        $this->assertEquals(
            '19 monkeys',
            PFXUtils::quantify(19, 'monkey')
        );
        $this->assertEquals(
            '0 cats',
            PFXUtils::quantify(0, 'cat')
        );
        $this->assertEquals(
            '-1 cats',
            PFXUtils::quantify(-1, 'cat')
        );
        $this->assertEquals(
            '1 country',
            PFXUtils::quantify(1, 'country', 'countries')
        );
        $this->assertEquals(
            '11 countries',
            PFXUtils::quantify(11, 'country', 'countries')
        );
    }
    
    /**
     * Tests PFXUtils::implodeSemantically().
     */
    public function testImplodeSemantically() {
        $this->assertEquals('foo and bar', PFXUtils::implodeSemantically(
            ', ', array('foo', 'bar')
        ));
        $this->assertEquals('foo, bar, and baz', PFXUtils::implodeSemantically(
            ', ', array('foo', 'bar', 'baz')
        ));
        $this->assertEquals('foo; bar; or baz', PFXUtils::implodeSemantically(
            '; ', array('foo', 'bar', 'baz'), 'or'
        ));
        $this->assertEquals(
            '1, 2, 3, 4, 5, 6, 7, 8, aaaaaaaaand 9',
            PFXUtils::implodeSemantically(
                ', ',
                array(1, 2, 3, 4, 5, 6, 7, 8, 9),
                'aaaaaaaaand'
            )
        );
        $this->assertEquals('1 aaaaaaaaand 9', PFXUtils::implodeSemantically(
            '+', array(1, 9), 'aaaaaaaaand'
        ));
    }
}
?> 