<?php
namespace Google\SearchConsole;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class API extends \Google\ServiceAccountAPI {
    private static $_SETTINGS = array(
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_SCOPE' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_TARGET' => 'https://www.googleapis.com/oauth2/v3/token',
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_EMAIL' => null,
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_KEYFILE' => null,
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_KEYFILE_PASSWORD' => 'notasecret',
        'OAUTH_DB_DSN' => null,
        'OAUTH_DB_USER' => null,
        'OAUTH_DB_PASSWORD' => null
    );
    private static $_SETTING_TESTS = array(
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_SCOPE' => 'url',
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_TARGET' => 'url',
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_EMAIL' => 'email',
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_KEYFILE' => 'file',
        'GOOGLE_SEARCH_CONSOLE_API_AUTH_KEYFILE_PASSWORD' => 'string',
        'OAUTH_DB_DSN' => 'string',
        'OAUTH_DB_USER' => 'string',
        'OAUTH_DB_PASSWORD' => 'string'
    );
    private static $_staticPropsReady = false;
    
    public function __construct() {
        try {
            if (!self::$_staticPropsReady) {
                self::_initStaticProperties();
            }
            parent::__construct();
            $this->_EXCEPTION_TYPE = __NAMESPACE__ . '\RuntimeException';
        } catch (\Exception $e) {
            if ($e instanceof Exception) {
                throw $e;
            }
            throw new RuntimeException(
                'Encountered error during initialization.', null, $e
            );
        }
    }
}
?>