<?php
require_once(__DIR__ . '/src/classLoader.inc.php');

/* Bootstrap file TEMPLATE for testing Google Analytics API applications */

/**
 * The test database DSN. This should resolve to an existing empty database.
 *
 * @type string
 */
define('TEST_DB_DSN', );

/**
 * The test database name. This should reflect the database name specified in
 * the DSN.
 *
 * @type string
 */
define('TEST_DB_DBNAME', );

/**
 * The test database user.
 *
 * @type string
 */
define('TEST_DB_USER', );

/**
 * The test database password.
 *
 * @type string
 */
define('TEST_DB_PASSWORD', );

/**
 * The full path to the database client binary. This is used in a system call
 * when creating the database's schema during test setup.
 *
 * @type executable
 */
define('TEST_DB_BINARY', );

/**
 * The full path to the system's PHP binary. This is used to execute a helper
 * script as part of PFXUtilsTestCase.
 *
 * @type executable
 */
define('PHP_BINARY', );
?>
