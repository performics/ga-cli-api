<?php
namespace TestHelpers;
if (!defined('PFX_UNIT_TEST')) {
    define('PFX_UNIT_TEST', true);
}

abstract class DatabaseTestCase extends \PHPUnit_Extensions_Database_TestCase {
    /* This property may contain a list of table names on which TRUNCATE TABLE
    statements will be issued prior to the execution of each test. */
    protected static $_tablesUnderTest = array();
    protected static $_dbConn;
    /* Whereas self::$_dbConn is a PDO instance, $this->_phpunitDBConn is an
    instance of PHPUnit_Extensions_Database_DB_IDatabaseConnection. */
    protected $_phpunitDBConn;
    
    public static function setUpBeforeClass() {
        \PFXUtils::validateSettings(
            array(
                'TEST_DB_DSN' => null,
                'TEST_DB_USER' => null,
                'TEST_DB_PASSWORD' => null,
                'TEST_DB_DBNAME' => null,
                /* If defined, this should be the full path to a file
                containing the paths of the database script files (relative to
                the containing file) that must be executed prior to entering
                the test case, one on each line. */
                'TEST_DB_SCHEMA_HISTORY' => null,
                /* If defined, this should be a directory whose contents will
                be treated as database script files and executed in
                alphabetical order according to their names (or more
                specifically, in the order in which they are stored in the
                filesystem, which should amount to the same thing). If the
                value of this constant is not an absolute path (i.e. it does
                not begin with a forward slash), it will be treated as relative
                to the schemata/ directory that should be at the root level of
                the version control repository that contains this file. */
                'TEST_DB_SCHEMA_DIRECTORY' => null,
                /* This class currently expects that the database system in use
                will be MySQL. */
                'TEST_DB_BINARY' => null,
                /* Directs this test to behave as if the database did not
                exist. */
                'TEST_IGNORE_DB' => false
            ),
            array(
                'TEST_DB_DSN' => 'str',
                'TEST_DB_USER' => 'str',
                'TEST_DB_PASSWORD' => 'str',
                'TEST_DB_DBNAME' => 'str',
                'TEST_DB_SCHEMA_HISTORY' => '?file',
                /* Can't use directory validation on this because it may be
                relative. */
                'TEST_DB_SCHEMA_DIRECTORY' => '?string',
                'TEST_DB_BINARY' => 'executable'
            )
        );
        self::$_dbConn = \PFXUtils::getDBConn(
            TEST_DB_DSN, TEST_DB_USER, TEST_DB_PASSWORD
        );
        /* If we're ignoring the database, we can bail out now that we have
        satisfied PHPUnit's requirements about what this class has to set up.
        */
        if (TEST_IGNORE_DB) {
            return;
        }
        // Make sure the database is empty first
        $stmt = self::$_dbConn->query('SHOW TABLES');
        if ($stmt->fetch()) {
            throw new \RuntimeException(
                'Cannot execute a test case against a non-empty database.'
            );
        }
        printf(
            'Running scripts against test database %s...%s',
            TEST_DB_DBNAME,
            PHP_EOL
        );
        /* This opens a security hole if the test database user's password is
        sensitive, which it really shouldn't be. */
        $cmd = sprintf(
            '%s %s --user=%s --password=%s',
            TEST_DB_BINARY,
            TEST_DB_DBNAME,
            TEST_DB_USER,
            TEST_DB_PASSWORD
        );
        $scripts = array();
        if (TEST_DB_SCHEMA_HISTORY) {
            $fh = fopen(TEST_DB_SCHEMA_HISTORY, 'r');
            $baseDir = dirname(TEST_DB_SCHEMA_HISTORY);
            while ($line = fgets($fh)) {
                $scripts[] = $baseDir . DIRECTORY_SEPARATOR . trim($line);
            }
        }
        if (TEST_DB_SCHEMA_DIRECTORY) {
            // Treat this as a comma-delimited list of directories
            $directories = explode(',', TEST_DB_SCHEMA_DIRECTORY);
            foreach ($directories as $dir) {
                $dir = trim($dir);
                if ($dir[0] != '/') {
                    $dir = realpath(__DIR__ . '/../../schemata')
                         . '/' . $dir;
                }
                $dirH = opendir($dir);
                if ($dirH === false) {
                    throw new RuntimeException(
                        'Failed to open the directory ' . $dir . ' for reading.'
                    );
                }
                while (false !== ($entry = readdir($dirH))) {
                    $fullPath = $dir . '/' . $entry;
                    if (!is_dir($fullPath)) {
                        $scripts[] = $fullPath;
                    }
                }
                closedir($dirH);
            }
        }
        foreach ($scripts as $script) {
            system($cmd . ' < ' . $script, $returnVal);
            if ($returnVal) {
                throw new \RuntimeException(
                    'Execution of database script ' . $script . ' failed ' .
                    'with exit code ' . $returnVal . '.'
                );
            }
        }
        printf('Done.%s', PHP_EOL);
    }
    
    public static function tearDownAfterClass() {
        if (TEST_IGNORE_DB) {
            return;
        }
        printf(
            'Destroying tables in test database %s...%s',
            TEST_DB_DBNAME,
            PHP_EOL
        );
        /* We don't care about violating foreign key contraints when we are
        destroying the contents of the database. */
        self::$_dbConn->exec('SET foreign_key_checks = 0');
        $stmt = self::$_dbConn->query('SHOW TABLES');
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
        foreach ($tables as $table) {
            self::$_dbConn->exec('DROP TABLE ' . $table);
            printf('Destroyed table %s%s', $table, PHP_EOL);
        }
        printf('Done.%s', PHP_EOL);
    }
    
    final public function getConnection() {
        if (!$this->_phpunitDBConn) {
            $this->_phpunitDBConn = $this->createDefaultDBConnection(
                self::$_dbConn, TEST_DB_DBNAME
            );
        }
        return $this->_phpunitDBConn;
    }
    
    /**
     * Generates a string containing $length bytes of text drawn from
     * characters between ASCII character codes 32 and 126. Copied from
     * TestHelpers\TestCase because I'm writing this on PHP 5.3 and I can't do
     * mixins yet.
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
     * This returns an empty data set and should be overridden as necessary.
     *
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet() {
        return new \PHPUnit_Extensions_Database_DataSet_DefaultDataSet();
    }
    
    /**
     * Asserts that calling a given function/method throws an exception of the
     * specified type. Copied from TestHelpers\TestCase because this is written
     * to be compatible with PHP 5.3 and I can't do mixins yet.
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
    
    /**
     * Truncates each table under test.
     *
     * @before
     */
    public function resetDatabaseState() {
        if (TEST_IGNORE_DB) {
            return;
        }
        foreach (static::$_tablesUnderTest as $table) {
            self::$_dbConn->exec('DELETE FROM ' . $table);
            // This should be a no-op for tables without an auto increment
            self::$_dbConn->exec('ALTER TABLE ' . $table . ' AUTO_INCREMENT = 1');
        }
    }
}
?>
