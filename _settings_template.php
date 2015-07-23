<?php
/* Settings file TEMPLATE for Google Analytics API applications */

/* REQUIRED SETTINGS */

/**
 * General-purpose data directory.
 *
 * @type directory
 */
define('GOOGLE_ANALYTICS_API_DATA_DIR', );

/**
 * Full path to log file.
 *
 * @type file
 */
define('GOOGLE_ANALYTICS_API_LOG_FILE', );

/**
 * The email address associated with the Google API service account to be used
 * for authorization.
 *
 * @type email
 */
define('GOOGLE_ANALYTICS_API_AUTH_EMAIL', );

/**
 * Full path to the private key file associated with the Google API service
 * account to be used for authorization.
 *
 * @type file
 */
define('GOOGLE_ANALYTICS_API_AUTH_KEYFILE', );

/* OPTIONAL SETTINGS */

/**
 * The required scope for Analytics API read access (defaults to
 * "https://www.googleapis.com/auth/analytics.readonly").
 *
 * @type string
 */
define('GOOGLE_ANALYTICS_API_AUTH_SCOPE', );

/**
 * The OAuth endpoint from which to request authorization (defaults to
 * "https://www.googleapis.com/oauth2/v3/token").
 *
 * @type URL
 */
define('GOOGLE_ANALYTICS_API_AUTH_TARGET', );

/**
 * Defines an email address to which log messages will be sent automatically.
 *
 * @type email
 */
define('GOOGLE_ANALYTICS_API_LOG_EMAIL', );

/**
 * Defines the number of seconds that dimension and metric metadata will be
 * cached in the local database (defaults to 86400).
 *
 * @type int
 */
define('GOOGLE_ANALYTICS_API_METADATA_CACHE_DURATION', );

/**
 * Defines the number of seconds that account summaries will be cached in the
 * local database (defaults to 86400).
 *
 * @type int
 */
define('GOOGLE_ANALYTICS_API_ACCOUNTS_CACHE_DURATION', );

/**
 * Defines the maximum size of a file can be (in bytes) before it is
 * automatically compressed when being sent via email (defaults to 1048576).
 *
 * @type int
 */
define('GOOGLE_ANALYTICS_API_AUTOZIP_THRESHOLD', );

/**
 * Defines the password for the Google API private key file (defaults to
 * "notasecret").
 *
 * @type string
 */
define('GOOGLE_ANALYTICS_API_AUTH_KEYFILE_PASSWORD', );

/**
 * Defines the number of results (up to 1000) for which the application will
 * ask the Google Analytics API per query (defaults to 500).
 *
 * @type int
 */
define('GOOGLE_ANALYTICS_API_PAGE_SIZE', );

/**
 * The database DSN. If left empty, the application will not attempt to connect
 * to a database. See http://php.net/manual/en/ref.pdo-mysql.connection.php for
 * information on how to construct a DSN for MySQL.
 *
 * @type string
 */
define('OAUTH_DB_DSN', );
 
/**
 * The database username (required if OAUTH_DB_DSN has a value).
 *
 * @type string
 */
define('OAUTH_DB_USER', );
 
/**
 * The database password (required if OAUTH_DB_DSN has a value).
 *
 * @type string
 */
define('OAUTH_DB_PASSWORD', );

/**
 * Path to SSL certificate bundle. Normally this is not required, but may be
 * necessary if the environment's cURL installation does not automatically load
 * the certificate bundle necessary to connect to Google's API endpoints.
 *
 * @type file
 */
define('PFX_CA_BUNDLE', );
?>
