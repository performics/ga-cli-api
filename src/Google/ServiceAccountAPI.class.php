<?php
namespace Google;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

abstract class ServiceAccountAPI extends \GenericAPI\Base {
    /* Concrete subclasses may or may not (but probably should) have a mutex.
    This class does not itself create one, because I want to be able to talk to
    separate Google APIs concurrently.
    
    Also note: I had a nasty race condition when I used to have this as an
    instance property. It ended up being caused by the fact that the mutex
    instance that acquired a lock during the API call could end up being
    distinct from the mutex instance that attempts to acquire a lock on the log
    file in methods that might get called at that time (e.g. handleError()).
    The logger's mutex couldn't tell that the outer mutex was acquired, so it
    gamely tried to get a lock and then hung forever. */
    protected static $_apiMutex;
    protected static $_dbConn;
    private static $_DB_STATEMENTS = array();
    private static $_SETTINGS = array(
        'OAUTH_DB_DSN' => null,
        'OAUTH_DB_USER' => null,
        'OAUTH_DB_PASSWORD' => null,
    );
    private static $_SETTING_TESTS = array(
        'OAUTH_DB_DSN' => '?string',
        'OAUTH_DB_USER' => '?string',
        'OAUTH_DB_PASSWORD' => '?string'
    );
    protected $_oauthService;
    protected $_responseFormat = self::RESPONSE_FORMAT_JSON;
    protected $_httpResponseActionMap = array(
        304 => self::ACTION_SUCCESS
    );
    protected $_responseTries = 2;
    protected $_repeatPauseInterval = 5;
    protected $_nextRequest;
    
    protected static function _initStaticProperties() {
        if (self::$_dbConn) {
            return;
        }
        \PFXUtils::validateSettings(self::$_SETTINGS, self::$_SETTING_TESTS);
        /* Since we use the OAuth back end for authentication, and it
        (optionally) uses the database, we can piggyback off its credentials if
        provided. This isn't enforced because some subclasses may be built to
        work correctly with no database connection. */
        if (OAUTH_DB_DSN) {
            self::$_dbConn = \PFXUtils::getDBConn(
                OAUTH_DB_DSN, OAUTH_DB_USER, OAUTH_DB_PASSWORD
            );
            /* This table used to pertain only to the Google Analytics back
            end, but then it became obvious that it was more generally useful.
            I retained the old name simply because it created more hassle than
            necessary to change it. */
            self::$_DB_STATEMENTS['google_analytics_api_fetch_log'] = array();
            $q = <<<EOF
INSERT INTO google_analytics_api_fetch_log
(entity, etag, result_count, fetch_date)
VALUES
(:entity, :etag, :result_count, UNIX_TIMESTAMP())
EOF;
            self::$_DB_STATEMENTS[
                'google_analytics_api_fetch_log'
            ]['insert'] = self::$_dbConn->prepare($q);
            $q = <<<EOF
SELECT * FROM google_analytics_api_fetch_log
WHERE entity = :entity
ORDER BY id DESC
LIMIT 1
EOF;
            self::$_DB_STATEMENTS[
                'google_analytics_api_fetch_log'
            ]['select'] = self::$_dbConn->prepare($q);
        }
    }
    
    /**
     * Inserts data into the fetch log.
     *
     * @param string $entity
     * @param int $resultCount
     * @param string $eTag = null
     */
    protected static function _storeFetchData(
        $entity,
        $resultCount,
        $eTag = null
    ) {
        try {
            $stmt = self::$_DB_STATEMENTS[
                'google_analytics_api_fetch_log'
            ]['insert'];
            $stmt->bindValue(':entity', $entity, \PDO::PARAM_STR);
            $stmt->bindValue(':etag', $eTag, \PDO::PARAM_STR);
            $stmt->bindValue(':result_count', $resultCount, \PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new RuntimeException(
                'Caught database error while storing fetch data.', null, $e
            );
        }
    }
    
    /**
     * Gets the most recent entry for a given entity from the fetch log as an
     * associative array (or false if there is no such entry).
     *
     * @param string $entity
     * @return array, boolean
     */
    protected static function _getLastFetchData($entity) {
        try {
            $stmt = self::$_DB_STATEMENTS[
                'google_analytics_api_fetch_log'
            ]['select'];
            $stmt->bindValue(':entity', $entity, \PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new RuntimeException(
                'Caught database error while getting last fetch data.', null, $e
            );
        }
    }
    
    /**
     * This method must register the appropriate OAuth service with the
     * appropriate Google\ServiceAccountAPIRequest subclass. The child of this
     * class is responsible for making sure this method is called at the
     * appropriate time.
     */
    abstract protected static function _configureOAuthService();
    
    /**
     * This method will be called whenever the API response was not empty, and
     * should return a useful representation of that response.
     *
     * @return mixed
     */
    abstract protected function _castResponse();
    
    /**
     * Raises a generic RuntimeException if the response code is greater than
     * or equal to 400. This method is mainly here to be overridden by
     * subclasses.
     */
    protected function _handleError() {
        if ($this->_responseCode >= 400) {
            throw new RuntimeException(
                'Encountered an error while querying API (response code was ' .
                $this->_responseCode . ').'
            );
        }
    }
    
    /**
     * Prepares a request in advance (this facilitates iterating through a
     * paged response in a while loop).
     *
     * @param Google\ServiceAccountAPIRequest $request
     */
    protected function _prepareRequest(ServiceAccountAPIRequest $request) {
        $this->_nextRequest = $request;
    }
    
    /**
     * Makes an authorized request to a Google API. If the argument is omitted,
     * this method will use a request previously set up via a call to
     * $this->_prepareRequest(), if any. If the response includes the key
     * 'items', this method returns an array of the corresponding items
     * instantiated as the appropriate object type (if possible). If it
     * includes the key 'kind', it returns an instance of the corresponding
     * object type (if possible). If the request is made with no arguments and
     * there is no next URL cached, it returns false.
     *
     * @param Google\ServiceAccountAPIRequest $request = null
     * @return mixed
     */
    protected function _makeRequest(ServiceAccountAPIRequest $request = null) {
        static::_configureOAuthService();
        if (!$request) {
            if ($this->_nextRequest === null) {
                return false;
            }
            $request = $this->_nextRequest;
        }
        if (static::$_apiMutex) {
            static::$_apiMutex->acquire();
        }
        try {
            $this->_getResponse($request);
            if (static::$_apiMutex) {
                static::$_apiMutex->release();
            }
        } catch (\Exception $e) {
            if (static::$_apiMutex) {
                static::$_apiMutex->release();
            }
            throw $e;
        }
        if ($this->_responseParsed) {
            return $this->_castResponse();
        }
    }
}
