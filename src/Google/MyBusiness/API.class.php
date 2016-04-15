<?php
namespace Google\MyBusiness;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class API extends \Google\ServiceAccountAPI {
    private static $_SETTINGS = array(
        'GOOGLE_MYBUSINESS_API_AUTH_SCOPE' => 'https://www.googleapis.com/auth/plus.business.manage',
        'GOOGLE_MYBUSINESS_API_AUTH_TARGET' => 'https://www.googleapis.com/oauth2/v3/token',
        'GOOGLE_MYBUSINESS_API_AUTH_OWNER' => null,
        'GOOGLE_MYBUSINESS_API_AUTH_EMAIL' => null,
        'GOOGLE_MYBUSINESS_API_AUTH_KEYFILE' => null,
        'GOOGLE_MYBUSINESS_API_AUTH_KEYFILE_PASSWORD' => 'notasecret',
        'GOOGLE_MYBUSINESS_API_LOG_FILE' => null,
        'GOOGLE_MYBUSINESS_API_LOG_VERBOSE' => false,
        'GOOGLE_MYBUSINESS_API_LOG_EMAIL' => null,
        // Future: may need to support multiple category data sources
        'GOOGLE_MYBUSINESS_API_CATEGORIES_URL' => 'https://developers.google.com/my-business/categories/categories_en_US.csv',
        'GOOGLE_MYBUSINESS_API_CATEGORIES_CACHE_DURATION' => 604800,
        'GOOGLE_MYBUSINESS_API_LOCATION_PAGE_SIZE' => 100,
        /* This defaults to false as a safeguard to make sure applications that
        create new locations know what they're doing. */        
        'GOOGLE_MYBUSINESS_API_ALLOW_NEW_LOCATIONS' => false,
        'GOOGLE_MYBUSINESS_API_DRY_RUN' => false,
        'PFX_CA_BUNDLE' => null
    );
    private static $_SETTING_TESTS = array(
        'GOOGLE_MYBUSINESS_API_AUTH_SCOPE' => 'url',
        'GOOGLE_MYBUSINESS_API_AUTH_TARGET' => 'url',
        'GOOGLE_MYBUSINESS_API_AUTH_OWNER' => 'email',
        'GOOGLE_MYBUSINESS_API_AUTH_EMAIL' => 'email',
        'GOOGLE_MYBUSINESS_API_AUTH_KEYFILE' => 'file',
        'GOOGLE_MYBUSINESS_API_AUTH_KEYFILE_PASSWORD' => 'string',
        'GOOGLE_MYBUSINESS_API_LOG_FILE' => 'writable',
        'GOOGLE_MYBUSINESS_API_LOG_VERBOSE' => 'boolean',
        'GOOGLE_MYBUSINESS_API_LOG_EMAIL' => '?email',
        'GOOGLE_MYBUSINESS_API_CATEGORIES_URL' => 'url',
        'GOOGLE_MYBUSINESS_API_CATEGORIES_CACHE_DURATION' => 'integer',
        'GOOGLE_MYBUSINESS_API_LOCATION_PAGE_SIZE' => 'integer',
        'GOOGLE_MYBUSINESS_API_ALLOW_NEW_LOCATIONS' => 'boolean',
        'GOOGLE_MYBUSINESS_API_DRY_RUN' => 'boolean',
        'PFX_CA_BUNDLE' => '?file'
    );
    private static $_staticPropsReady = false;
    private static $_DB_STATEMENTS = array();
    private static $_validator;
    protected static $_apiMutex;
    private $_logger;
    private $_parseCallback;
    private $_fetchedCount;
    private $_returnedCount;
    private $_limit;
    private $_offset;
    private $_pageSize;
    private $_account;
    
    public function __construct() {
        try {
            if (!self::$_staticPropsReady) {
                self::_initStaticProperties();
            }
            $this->_logger = new \Logger(
                GOOGLE_MYBUSINESS_API_LOG_FILE,
                GOOGLE_MYBUSINESS_API_LOG_EMAIL,
                self::$_apiMutex
            );
            if (!\LoggingExceptions\Exception::hasLogger()) {
                \LoggingExceptions\Exception::registerLogger($this->_logger);
            }
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
    
    protected static function _initStaticProperties() {
        parent::_initStaticProperties();
        \PFXUtils::validateSettings(self::$_SETTINGS, self::$_SETTING_TESTS);
        if (PFX_CA_BUNDLE) {
            self::_registerSSLCertificate(PFX_CA_BUNDLE);
        }
        self::_prepareDBStatements();
        self::$_apiMutex = new \Mutex(__CLASS__);
        self::$_validator = new \Validator(__NAMESPACE__);
        self::$_staticPropsReady = true;
    }
    
    private static function _prepareDBStatements() {
        self::$_DB_STATEMENTS['google_business_categories'] = array();
        $q = <<<EOF
SELECT * FROM google_business_categories
WHERE id = :id
EOF;
        self::$_DB_STATEMENTS['google_business_categories'][
            'select_by_id'
        ] = self::$_dbConn->prepare($q);
        self::$_DB_STATEMENTS['google_business_categories'][
            'select_by_name'
        ] = self::$_dbConn->prepare(str_replace(
            'id = :id', 'name = :name', $q
        ));
    }
    
    protected static function _configureOAuthService() {
        if (APIRequest::hasOAuthService()) {
            return;
        }
        $authModule = new \OAuth\UserlessAuthorizationModule(
            new \OAuth\JSONTokenResponseParser(),
            new \OAuth\GoogleJWTRequestBuilder(
                GOOGLE_MYBUSINESS_API_AUTH_EMAIL,
                GOOGLE_MYBUSINESS_API_AUTH_TARGET,
                GOOGLE_MYBUSINESS_API_AUTH_SCOPE,
                GOOGLE_MYBUSINESS_API_AUTH_KEYFILE,
                GOOGLE_MYBUSINESS_API_AUTH_KEYFILE_PASSWORD,
                GOOGLE_MYBUSINESS_API_AUTH_OWNER
            ),
            GOOGLE_MYBUSINESS_API_AUTH_TARGET
        );
        $authModule->usePostToAuthorize();
        APIRequest::registerOAuthService(new \OAuth\Service($authModule));
    }
    
    /**
     * Updates the database cache of Google My Business categories and returns
     * the number of categories modified by the update.
     *
     * @return int
     */
    private static function _refreshCategories() {
        /* Google doesn't offer an API for this at this time; instead it just
        hosts a bunch of CSV files in simple key-value format. */
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_URL => GOOGLE_MYBUSINESS_API_CATEGORIES_URL
        ));
        $response = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($responseCode != 200) {
            throw new RuntimeException(
                'The request to pull updated category information from ' .
                GOOGLE_MYBUSINESS_API_CATEGORIES_URL . ' failed with a ' .
                $responseCode . ' response code.'
            );
        }
        $memStream = fopen('php://memory', 'r+');
        fwrite($memStream, $response);
        rewind($memStream);
        $idCol = null;
        $nameCol = null;
        /* In order to make it easier to know when categories have fallen out,
        we'll load the contents to a temporary table and then update the main
        table. */
        try {
            self::$_dbConn->exec(
                'CREATE TEMPORARY TABLE temp_google_business_categories LIKE google_business_categories'
            );
            $stmt = self::$_dbConn->prepare(
                'INSERT INTO temp_google_business_categories (id, name) VALUES (:id, :name)'
            );
            $stmt->bindParam(':id', $catID, \PDO::PARAM_STR);
            $stmt->bindParam(':name', $catName, \PDO::PARAM_STR);
            while ($row = fgetcsv($memStream)) {
                if ($idCol === null) {
                    $rowCount = count($row);
                    if ($rowCount != 2) {
                        throw new UnexpectedValueException(
                            'Found ' . $rowCount . ' columns in the Google ' .
                            'category data; expected 2.'
                        );
                    }
                    for ($i = 0; $i < $rowCount; $i++) {
                        if (substr($row[$i], 0, 5) == 'gcid:') {
                            $idCol = $i;
                        }
                        else {
                            $nameCol = $i;
                        }
                    }
                    if ($idCol === null) {
                        throw new UnexpectedValueException(
                            'Could not determine which column in the Google ' .
                            'category data contains the ID.'
                        );
                    }
                }
                $catID = $row[$idCol];
                $catName = $row[$nameCol];
                $stmt->execute();
            }
            $changeCount = 0;
            // Removals
            $q = <<<EOF
UPDATE google_business_categories gbc
SET active = 0
WHERE active = 1
AND NOT EXISTS (
 SELECT * FROM temp_google_business_categories tgbc
 WHERE gbc.id = tgbc.id
)
EOF;
            $changeCount += self::$_dbConn->exec($q);
            // Updates
            $q = <<<EOF
UPDATE google_business_categories gbc
JOIN temp_google_business_categories tgbc
ON gbc.id = tgbc.id
SET gbc.active = 1, gbc.name = tgbc.name
WHERE gbc.active = 0 OR gbc.name != tgbc.name
EOF;
            $changeCount += self::$_dbConn->exec($q);
            // Additions
            $q = <<<EOF
INSERT INTO google_business_categories
(id, name, cdate)
SELECT t.id, t.name, UNIX_TIMESTAMP() AS cdate
FROM temp_google_business_categories t
WHERE NOT EXISTS (
 SELECT * FROM google_business_categories gbc
 WHERE t.id = gbc.id
)
EOF;
            $changeCount += self::$_dbConn->exec($q);
            return $changeCount;
        } catch (\PDOException $e) {
            throw new RuntimeException(
                'Caught database error while refreshing categories.', null, $e
            );
        }
    }
    
    /**
     * Validates that the given instance has an ID.
     *
     * @param Google\MyBusiness\AbstractAPIResponseObject $obj
     */
    private static function _validateID(AbstractAPIResponseObject $obj) {
        if (!strlen($obj->getID())) {
            throw new InvalidArgumentException(
                get_class($obj) . ' instances must have IDs to be ' .
                'used in API queries.'
            );
        }
    }
    
    /**
     * Validates that the given Google\MyBusiness\Location instance may be used
     * in API queries on its own, which is to say that it contains the owner
     * account ID.
     *
     * @param Google\MyBusiness\Location $location
     */
    private static function _validateLocation(Location $location) {
        self::_validateID($location);
        if (!strlen($location->getOwnerAccountID())) {
            throw new InvalidArgumentException(
                'This ' . __NAMESPACE__ . '\Location instance does not ' .
                'have the ID of its owning account set.'
            );
        }
    }
    
    /**
     * @return Google\MyBusiness\Account
     */
    private function _getCachedAccount() {
        if (!$this->_account) {
            throw new LogicException(
                'An account must be set before calling this method.'
            );
        }
        return $this->_account;
    }
    
    /**
     * Returns a representation of one or more accounts.
     *
     * @return Google\MyBusiness\Account, array
     */
    private function _parseAccountResponse() {
        if (isset($this->_responseParsed['accounts'])) {
            $accounts = array();
            foreach ($this->_responseParsed['accounts'] as $accountData) {
                $accounts[] = new Account($accountData);
            }
            return $accounts;
        }
        return new Account($this->_responseParsed);
    }
    
    /**
     * Returns a representation of one or more locations.
     *
     * @return Google\MyBusiness\Location, array
     */
    private function _parseLocationResponse() {
        if (isset($this->_responseParsed['locations'])) {
            $locations = $this->_responseParsed['locations'];
            $locationCount = count($locations);
            $this->_fetchedCount += $locationCount;
            if ($this->_fetchedCount < $this->_offset) {
                // No need to bother initializing these objects
                return array();
            }
            $diff = $this->_fetchedCount - $this->_offset;
            if ($diff < $locationCount) {
                $locations = array_slice(
                    $locations, $locationCount - $diff
                );
            }
            if ($this->_limit && $this->_fetchedCount > $this->_limit) {
                $locations = array_slice(
                    $locations,
                    0,
                    $this->_limit - $this->_offset - $this->_returnedCount
                );
            }
            $this->_returnedCount += count($locations);
            return array_map(
                function(array $locationData) { return new Location($locationData); },
                $locations
            );
        }
        return new Location($this->_responseParsed);
    }
    
    protected function _handleError() {
        if (!$this->_responseCode < 400 && !isset($this->_responseParsed['error']))
        {
            return;
        }
        $responseID = null;
        if (\PFXUtils::nestedArrayKeyExists(
            array('error', 'message'), $this->_responseParsed
        )) {
            $message = $this->_responseParsed['error']['message'];
            // Why is this buried so deep?
            if (\PFXUtils::nestedArrayKeyExists(
                array('error', 'details', 0, 'errorDetails', 0, 'message'),
                $this->_responseParsed
            )) {
                $message .= ' (' . $this->_responseParsed['error'][
                    'details'
                ][0]['errorDetails'][0]['message'] . ')';
            }
        }
        else {
            $responseID = uniqid();
            $message = 'Could not find error message in API response. '
                     . 'Response code was ' . $this->_responseCode
                     . '; response ID is ' . $responseID . '.';
            $this->_logger->log(
                'Raw content of response ID ' . $responseID . ': ' .
                $this->_responseRaw,
                false
            );
        }
        switch ($this->_responseCode) {
            case 400:
                throw new BadRequestException($message);
            case 404:
                throw new NotFoundException($message);
            default:
                throw new RemoteException($message);
        }
    }
    
    /**
     * Handles logging and intercepts dry run calls where applicable.
     *
     * @param Google\MyBusiness\APIRequest $request
     * return mixed
     */
    protected function _makeRequest(APIRequest $request = null) {
        $verb = $request ? $request->getVerb() : null;
        if ($verb && $verb != 'GET') {
            if (GOOGLE_MYBUSINESS_API_LOG_VERBOSE) {
                $payload = $request->getPayload();
                $message = sprintf(
                    'Preparing %s%s request to %s%s',
                    GOOGLE_MYBUSINESS_API_DRY_RUN ? 'dry run ' : '',
                    $request->getVerb(),
                    $request->getURL(),
                    strlen($payload) ? ' with payload "' . $payload . '"' : ''
                );
                $this->_logger->log($message, false);
            }
            if (GOOGLE_MYBUSINESS_API_DRY_RUN) {
                return;
            }
        }
        return parent::_makeRequest($request);
    }
    
    /**
     * Returns a parsed representation of the response, either as an object or
     * as a list of objects.
     *
     * @return Google\MyBusiness\AbstractAPIResponseObject, array
     */
    protected function _castResponse() {
        if (!$this->_parseCallback) {
            throw new LogicException('No parse callback was declared.');
        }
        if (isset($this->_responseParsed['nextPageToken'])) {
            $this->_nextRequest = clone $this->_request;
            $this->_nextRequest->setURL($this->_request->getURL(), array(
                'pageToken' => $this->_responseParsed['nextPageToken']
            ));
        }
        else {
            $this->_nextRequest = null;
        }
        return call_user_func(array($this, $this->_parseCallback));
    }
    
    /**
     * Validates that the given Google\MyBusiness\Category instance contains
     * either a name or ID that exists in the available category data.
     *
     * @param Google\MyBusiness\Category $category
     */
    public static function validateCategory(Category $category) {
        if (!self::$_staticPropsReady) {
            self::_initStaticProperties();
        }
        // Refresh category data if necessary
        $lastFetchData = self::_getLastFetchData('google_business_categories');
        if (!$lastFetchData ||
            (int)$lastFetchData['fetch_date'] < time() - GOOGLE_MYBUSINESS_API_CATEGORIES_CACHE_DURATION)
        {
            self::_storeFetchData(
                'google_business_categories',
                self::_refreshCategories()
            );
        }
        $catID = $category->getID();
        $catName = $category->getName();
        if (!$catID && !$catName) {
            throw new InvalidArgumentException(
                'Cannot validate a category unless it has an ID and/or a name.'
            );
        }
        try {
            // Always prefer to validate on the basis of the ID, if present
            if ($catID) {
                $stmt = self::$_DB_STATEMENTS['google_business_categories'][
                    'select_by_id'
                ];
                $stmt->bindValue(':id', $catID, \PDO::PARAM_STR);
            }
            else {
                $stmt = self::$_DB_STATEMENTS['google_business_categories'][
                    'select_by_name'
                ];
                $stmt->bindValue(':name', $catName, \PDO::PARAM_STR);
            }
            $stmt->execute();
            $stmt->bindColumn('id', $dbID, \PDO::PARAM_STR);
            $stmt->bindColumn('name', $dbName, \PDO::PARAM_STR);
            if (!$stmt->fetch(\PDO::FETCH_BOUND)) {
                if ($catID) {
                    $message = 'The category ID "' . $catID . '" is invalid.';
                }
                else {
                    $message = 'The category name "' . $catName . '" is invalid.';
                }
                throw new UnexpectedValueException($message);
            }
            if ($catID) {
                $category->setName($dbName);
            }
            else {
                $category->setID($dbID);
            }
        } catch (\PDOException $e) {
            throw new RuntimeException(
                'Caught database error while validating category.', null, $e
            );
        }
    }
    
    /**
     * Sets the Google\MyBusiness\Account instance whose locations are to be
     * queried/edited.
     *
     * @param Google\MyBusiness\Account $account
     */
    public function setAccount(Account $account) {
        self::_validateID($account);
        $this->_account = $account;
    }
    
    /**
     * Returns a list of objects representing the available accounts.
     *
     * @return array
     */
    public function getAccounts() {
        $this->_parseCallback = '_parseAccountResponse';
        $this->_prepareRequest(
            new APIRequest('accounts', array('pageSize' => 50))
        );
        $accounts = array();
        while ($page = $this->_makeRequest()) {
            $accounts = array_merge($accounts, $page);
        }
        return $accounts;
    }
    
    /**
     * Given an account ID, returns an object representing a specific account.
     *
     * @param string $id
     * @return Google\MyBusiness\Account
     */
    public function getAccount($id) {
        $id = self::$_validator->string(
            $id,
            'Account IDs may not be empty.',
            \Validator::FILTER_TRIM | \Validator::ASSERT_TRUTH
        );
        $this->_parseCallback = '_parseAccountResponse';
        return $this->_makeRequest(new APIRequest('accounts/' . $id));
    }
    
    /**
     * Returns a list of locations for the previously set account, or
     * optionally another account specified as an argument..
     *
     * @param Google\MyBusiness\LocationFilterCollection $filter = null
     * @param Google\MyBusiness\Account $account = null
     * @return array
     */
    public function getLocations(
        LocationFilterCollection $filter = null,
        Account $account = null
    ) {
        if ($account) {
            self::_validateID($account);
        }
        else {
            $account = $this->_getCachedAccount();
        }
        $this->_parseCallback = '_parseLocationResponse';
        $this->_fetchedCount = 0;
        $this->_returnedCount = 0;
        $params = array(
            'pageSize' => GOOGLE_MYBUSINESS_API_LOCATION_PAGE_SIZE
        );
        if ($filter) {
            $this->_offset = $filter->getOffset();
            $this->_limit = $filter->getLimit();
            if ($this->_limit) {
                $this->_limit += $this->_offset;
                if ($this->_limit < GOOGLE_MYBUSINESS_API_LOCATION_PAGE_SIZE) {
                    $params['pageSize'] = $this->_limit;
                }
            }
            $filter = (string)$filter;
            if ($filter) {
                $params['filter'] = (string)$filter;
            }
        }
        else {
            $this->_limit = null;
            $this->_offset = 0;
        }
        $this->_pageSize = $params['pageSize'];
        $response = array();
        $this->_prepareRequest(new APIRequest(
            $account->getName() . '/locations', $params
        ));
        while ($page = $this->_makeRequest()) {
            $response = array_merge($response, $page);
            if ($this->_limit !== null && $this->_returnedCount >= $this->_limit)
            {
                break;
            }
        }
        return $response;
    }
    
    /**
     * Given a location ID, returns an object representing that location.
     *
     * @param string $id
     * @param Google\MyBusiness\Account $account = null
     * @return Google\MyBusiness\Location
     */
    public function getLocation($id, Account $account = null) {
        if ($account) {
            self::_validateID($account);
        }
        else {
            $account = $this->_getCachedAccount();
        }
        $id = self::$_validator->string(
            $id,
            'Location IDs may not be empty.',
            \Validator::FILTER_TRIM | \Validator::ASSERT_TRUTH
        );
        $this->_parseCallback = '_parseLocationResponse';
        return $this->_makeRequest(new APIRequest(
            $account->getName() . '/locations/' . $id
        ));
    }
    
    /**
     * Edits a location and returns the updated instance.
     *
     * @param Google\MyBusiness\Location $location
     * @param Google\MyBusiness\Account $account = null
     * @param boolean $dryRun = false
     * @return Google\MyBusiness\Location
     */
    public function editLocation(
        Location $location,
        Account $account = null,
        $dryRun = false
    ) {
        try {
            self::_validateLocation($location);
        } catch (AccountOwnerNotSetException $e) {
            /* Try to use the account in the arguments, or failing that, the
            cached account. */
            if ($account) {
                self::_validateID($account);
            }
            else {
                $account = $this->_getCachedAccount();
            }
            $location->setOwnerAccountID($account->getID());
        }
        $locationRest = $location->toREST();
        $requestBody = array(
            'location' => $locationRest,
            'languageCode' => $location->getLanguageCode(),
            'fieldMask' => implode(',', array_map(
                function($key) { return 'location.' . $key; },
                array_keys($locationRest)
            )),
            'validateOnly' => $dryRun
        );
        $request = new APIRequest($location->getName());
        $request->setPayload(json_encode($requestBody));
        $request->setVerb('PATCH');
        $this->_parseCallback = '_parseLocationResponse';
        return $this->_makeRequest($request);
    }
    
    /**
     * Creates a location and returns the new location as returned from the
     * API.
     *
     * @param Google\MyBusiness\Location $location
     * @param Google\MyBusiness\Account $account = null
     * @param boolean $dryRun = false
     */
    public function createLocation(
        Location $location,
        Account $account = null,
        $dryRun = false
    ) {
        $ownerAccountID = $location->getOwnerAccountID();
        if (strlen($ownerAccountID)) {
            $account = new Account();
            $account->setID($ownerAccountID);
        }
        elseif ($account) {
            self::_validateID($account);
        }
        else {
            $account = $this->_getCachedAccount();
        }
        if ($location->getID() !== null) {
            throw new InvalidArgumentException(
                'A new location may not already contain an ID.'
            );
        }
        /* Even if the creation of new locations is disabled, we'll still allow
        dry runs. */
        if (!GOOGLE_MYBUSINESS_API_ALLOW_NEW_LOCATIONS && !$dryRun) {
            throw new RuntimeException(
                'Cannot create new locations unless the ' .
                'GOOGLE_MYBUSINESS_API_ALLOW_NEW_LOCATIONS setting is ' .
                'defined as true.'
            );
        }
        $requestBody = array(
            'location' => $location->toREST(),
            'languageCode' => $location->getLanguageCode(),
            'validateOnly' => $dryRun,
            'requestId' => $location->getHash()
        );
        $request = new APIRequest($account->getName() . '/locations');
        $request->setPayload(json_encode($requestBody));
        $this->_parseCallback = '_parseLocationResponse';
        return $this->_makeRequest($request);
    }
}
?>