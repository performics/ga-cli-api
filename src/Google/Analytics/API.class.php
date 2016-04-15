<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class API extends \Google\ServiceAccountAPI {
    const FAILURE_CODE_SAMPLING = 1;
    private static $_SETTINGS = array(
        'GOOGLE_ANALYTICS_API_AUTH_SCOPE' => 'https://www.googleapis.com/auth/analytics.readonly',
        'GOOGLE_ANALYTICS_API_AUTH_TARGET' => 'https://www.googleapis.com/oauth2/v3/token',
        'GOOGLE_ANALYTICS_API_AUTH_EMAIL' => null,
        'GOOGLE_ANALYTICS_API_AUTH_KEYFILE' => null,
        'GOOGLE_ANALYTICS_API_AUTH_KEYFILE_PASSWORD' => 'notasecret',
        'GOOGLE_ANALYTICS_API_DATA_DIR' => null,
        'GOOGLE_ANALYTICS_API_LOG_FILE' => null,
        'GOOGLE_ANALYTICS_API_LOG_EMAIL' => null,
        'GOOGLE_ANALYTICS_API_METADATA_CACHE_DURATION' => 86400,
        'GOOGLE_ANALYTICS_API_ACCOUNTS_CACHE_DURATION' => 86400,
        'GOOGLE_ANALYTICS_API_AUTOZIP_THRESHOLD' => 1048576,
        'PFX_CA_BUNDLE' => null
    );
    private static $_SETTING_TESTS = array(
        'GOOGLE_ANALYTICS_API_AUTH_SCOPE' => 'url',
        'GOOGLE_ANALYTICS_API_AUTH_TARGET' => 'url',
        'GOOGLE_ANALYTICS_API_AUTH_EMAIL' => 'email',
        'GOOGLE_ANALYTICS_API_AUTH_KEYFILE' => 'file',
        'GOOGLE_ANALYTICS_API_AUTH_KEYFILE_PASSWORD' => 'string',
        'GOOGLE_ANALYTICS_API_DATA_DIR' => 'dir',
        'GOOGLE_ANALYTICS_API_LOG_FILE' => 'writable',
        'GOOGLE_ANALYTICS_API_LOG_EMAIL' => '?email',
        'GOOGLE_ANALYTICS_API_METADATA_CACHE_DURATION' => 'integer',
        'GOOGLE_ANALYTICS_API_ACCOUNTS_CACHE_DURATION' => 'integer',
        'GOOGLE_ANALYTICS_API_AUTOZIP_THRESHOLD' => 'integer',
        'PFX_CA_BUNDLE' => '?file'
    );
    private static $_DB_STATEMENTS = array();
    private static $_staticPropsReady = false;
    /* self::$_dimensions and self::$_metrics are indexed by name. The
    corresponding name lists only contain the non-deprecated columns. */
    private static $_dimensions = array();
    private static $_dimensionNames = array();
    private static $_metrics = array();
    private static $_metricNames = array();
    // This isn't actually used at the moment
    private static $_predefinedSegments = array();
    /* These properties are provided for convenience so that it's not necessary
    to drill down all the way from the account level to the profile level to
    get a known named profile. It is possible to encounter name conflicts at
    lower levels, though, so if multiple entities are found to have the same
    name, the corresponding array entry is set to null so that getters can
    return the appropriate error message. */
    private static $_accountsByName = array();
    private static $_accountsByID = array();
    private static $_webPropertiesByName = array();
    private static $_webPropertiesByID = array();
    private static $_profilesByName = array();
    private static $_profilesByID = array();
    protected static $_apiMutex;
    private $_bypassAccountCache = false;
    private $_bypassColumnCache = false;
    private $_activeQuery;
    private $_failedIterations;
    private $_logger;
    // This isn't really useful. May re-implement someday.
    //private $_failureReason;
    private $_totalRows;
    
    public function __construct() {
        try {
            if (!self::$_staticPropsReady) {
                self::_initStaticProperties();
            }
            $this->_logger = new \Logger(
                GOOGLE_ANALYTICS_API_LOG_FILE,
                GOOGLE_ANALYTICS_API_LOG_EMAIL,
                self::$_apiMutex
            );
            // It's only necessary to register a logger once
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
        if (self::$_dbConn) {
            self::_prepareDBStatements();
        }
        self::$_apiMutex = new \Mutex(__CLASS__);
        self::$_staticPropsReady = true;
    }
    
    private static function _prepareDBStatements() {
        self::$_DB_STATEMENTS['google_analytics_api_account_summaries'] = array();
        $q = <<<EOF
SELECT * FROM google_analytics_api_account_summaries
WHERE gid = :gid
EOF;
        self::$_DB_STATEMENTS['google_analytics_api_account_summaries'][
            'select'
        ] = self::$_dbConn->prepare($q);
        $q = <<<EOF
INSERT INTO google_analytics_api_account_summaries
(gid, name, visible, cdate)
VALUES
(:gid, :name, 1, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
name = VALUES(name),
visible = VALUES(visible)
EOF;
        self::$_DB_STATEMENTS['google_analytics_api_account_summaries'][
            'insert'
        ] = self::$_dbConn->prepare($q);
        self::$_DB_STATEMENTS['google_analytics_api_web_property_summaries'] = array();
        $q = <<<EOF
SELECT * FROM google_analytics_api_web_property_summaries
WHERE gid = :gid
EOF;
        self::$_DB_STATEMENTS['google_analytics_api_web_property_summaries'][
            'select'
        ] = self::$_dbConn->prepare($q);
        $q = <<<EOF
INSERT INTO google_analytics_api_web_property_summaries
(gid, gaaas_id, name, url, level, visible, cdate)
VALUES
(:gid, :gaaas_id, :name, :url, :level, 1, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
gaaas_id = VALUES(gaaas_id),
name = VALUES(name),
url = VALUES(url),
level = VALUES(level),
visible = VALUES(visible)
EOF;
        self::$_DB_STATEMENTS['google_analytics_api_web_property_summaries'][
            'insert'
        ] = self::$_dbConn->prepare($q);
        self::$_DB_STATEMENTS['google_analytics_api_profile_summaries'] = array();
        $q = <<<EOF
SELECT * FROM google_analytics_api_profile_summaries
WHERE gid = :gid
EOF;
        self::$_DB_STATEMENTS['google_analytics_api_profile_summaries'][
            'select'
        ] = self::$_dbConn->prepare($q);
        $q = <<<EOF
INSERT INTO google_analytics_api_profile_summaries
(gid, gaawps_id, name, type, visible, cdate)
VALUES
(:gid, :gaawps_id, :name, :type, 1, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
gaawps_id = VALUES(gaawps_id),
name = VALUES(name),
type = VALUES(type),
visible = VALUES(visible)
EOF;
        self::$_DB_STATEMENTS['google_analytics_api_profile_summaries'][
            'insert'
        ] = self::$_dbConn->prepare($q);
        self::$_DB_STATEMENTS['google_analytics_api_columns'] = array();
        $q = <<<EOF
SELECT * FROM google_analytics_api_columns
WHERE id = :id
EOF;
        self::$_DB_STATEMENTS['google_analytics_api_columns'][
            'select_by_id'
        ] = self::$_dbConn->prepare($q);
        $q = <<<EOF
SELECT * FROM google_analytics_api_columns
WHERE name = :name
EOF;
        self::$_DB_STATEMENTS['google_analytics_api_columns'][
            'select_by_name'
        ] = self::$_dbConn->prepare($q);
        $q = <<<EOF
INSERT INTO google_analytics_api_columns
(name,
type,
data_type,
replaced_by,
`group`,
ui_name,
description,
calculation,
min_template_index,
max_template_index,
min_template_index_premium,
max_template_index_premium,
allowed_in_segments,
deprecated,
cdate)
VALUES
(:name,
:type,
:data_type,
:replaced_by,
:group,
:ui_name,
:description,
:calculation,
:min_template_index,
:max_template_index,
:min_template_index_premium,
:max_template_index_premium,
:allowed_in_segments,
:deprecated,
UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
type = VALUES(type),
data_type = VALUES(data_type),
replaced_by = VALUES(replaced_by),
`group` = VALUES(`group`),
ui_name = VALUES(ui_name),
description = VALUES(description),
calculation = VALUES(calculation),
min_template_index = VALUES(min_template_index),
max_template_index = VALUES(max_template_index),
min_template_index_premium = VALUES(min_template_index_premium),
max_template_index_premium = VALUES(max_template_index_premium),
allowed_in_segments = VALUES(allowed_in_segments),
deprecated = VALUES(deprecated)
EOF;
        self::$_DB_STATEMENTS['google_analytics_api_columns'][
            'insert'
        ] = self::$_dbConn->prepare($q);
    }
    
    /**
     * Places an object in the array reference passed under the given key if
     * the key does not exist in the array. Otherwise sets the corresponding
     * array value to null.
     *
     * @param string $key
     * @param mixed $obj
     * @param array &$prop
     */
    private static function _populateProperty($key, $obj, array &$prop) {
        if (array_key_exists($key, $prop)) {
            $prop[$key] = null;
        }
        else {
            $prop[$key] = $obj;
        }
    }
    
    /**
     * Returns a text description of a failure reason code.
     *
     * @param int $reasonCode
     * @return string
     */
    private static function _getFailureReason($reasonCode) {
        switch ($reasonCode) {
            case self::FAILURE_CODE_SAMPLING:
                return 'Sampled data was present in the response.';
            default:
                throw new InvalidArgumentException(
                    'Unrecognized reason code.'
                );
        }
    }
     
    protected static function _configureOAuthService() {
        if (APIRequest::hasOAuthService()) {
            return;
        }
        $authModule = new \OAuth\UserlessAuthorizationModule(
            new \OAuth\JSONTokenResponseParser(),
            new \OAuth\GoogleJWTRequestBuilder(
                GOOGLE_ANALYTICS_API_AUTH_EMAIL,
                GOOGLE_ANALYTICS_API_AUTH_TARGET,
                GOOGLE_ANALYTICS_API_AUTH_SCOPE,
                GOOGLE_ANALYTICS_API_AUTH_KEYFILE,
                GOOGLE_ANALYTICS_API_AUTH_KEYFILE_PASSWORD
            ),
            GOOGLE_ANALYTICS_API_AUTH_TARGET
        );
        $authModule->usePostToAuthorize();
        APIRequest::registerOAuthService(new \OAuth\Service($authModule));
    }
    
    /**
     * Returns an object from the given property reference using the given name,
     * if present.
     *
     * @param string $name
     * @param array &$from
     * @return Google\Analytics\AbstractAPIResponseObject
     */
    private function _getAccountPropertyByName($name, array &$from) {
        $this->_populateAccountProperties();
        if (array_key_exists($name, $from)) {
            if ($from[$name] !== null) {
                return $from[$name];
            }
            throw new NameConflictException(
                'The name "' . $name . '" resolves to multiple objects.'
            );
        }
        throw new InvalidArgumentException(
            'Unrecognized object name "' . $name . '".'
        );
    }
    
    /**
     * Returns an object from the given array using the given ID, if present.
     *
     * @param string $id
     * @param array &$from
     * @return Google\Analytics\AbstractAPIResponseObject
     */
    private function _getAccountPropertyByID($id, array &$from) {
        $this->_populateAccountProperties();
        if (array_key_exists($id, $from)) {
            return $from[$id];
        }
        throw new InvalidArgumentException(
            'Unrecognized object ID "' . $id . '".'
        );
    }
    
    /**
     * Populates the properties that store cached dimensions and metrics,
     * either from the database or from the API directly, depending on when
     * the data that we have was last fetched.
     */
    private function _refreshColumnMetadata() {
        /* If we're not using a database, the best we can do is to cache the
        column metadata for the duration of this process' existence. */
        if (self::$_dbConn) {
            try {
                $lastFetchData = self::_getLastFetchData(
                    'google_analytics_api_columns'
                );
                if ($lastFetchData) {
                    $fetchDate = (int)$lastFetchData['fetch_date'];
                    $etag = $lastFetchData['etag'];
                }
                else {
                    $fetchDate = null;
                    $etag = null;
                }
                $columns = null;
                $newETag = null;
                if ($this->_bypassColumnCache || !$fetchDate ||
                    $fetchDate <= time() - GOOGLE_ANALYTICS_API_METADATA_CACHE_DURATION)
                {
                    $this->_bypassColumnCache = false;
                    $request = new APIRequest('metadata/ga/columns');
                    if ($etag) {
                        $request->setHeader('If-None-Match: ' . $etag);
                    }
                    $columns = $this->_makeRequest($request);
                    if ($columns) {
                        $stmt = self::$_DB_STATEMENTS[
                            'google_analytics_api_columns'
                        ]['insert'];
                        foreach ($columns as $column) {
                            $stmt->bindValue(
                                ':name', $column->getName(), \PDO::PARAM_STR
                            );
                            $stmt->bindValue(
                                ':type', $column->getType(), \PDO::PARAM_STR
                            );
                            $stmt->bindValue(
                                ':data_type',
                                $column->getDataType(),
                                \PDO::PARAM_STR
                            );
                            $stmt->bindValue(
                                ':replaced_by',
                                $column->getReplacementColumn(),
                                \PDO::PARAM_STR
                            );
                            $stmt->bindValue(
                                ':group', $column->getGroup(), \PDO::PARAM_STR
                            );
                            $stmt->bindValue(
                                ':ui_name',
                                $column->getUIName(),
                                \PDO::PARAM_STR
                            );
                            $stmt->bindValue(
                                ':description',
                                $column->getDescription(),
                                \PDO::PARAM_STR
                            );
                            $stmt->bindValue(
                                ':calculation',
                                $column->getCalculation(),
                                \PDO::PARAM_STR
                            );
                            $stmt->bindValue(
                                ':min_template_index',
                                $column->getMinTemplateIndex(),
                                \PDO::PARAM_INT
                            );
                            $stmt->bindValue(
                                ':max_template_index',
                                $column->getMaxTemplateIndex(),
                                \PDO::PARAM_INT
                            );
                            $stmt->bindValue(
                                ':min_template_index_premium',
                                $column->getPremiumMinTemplateIndex(),
                                \PDO::PARAM_INT
                            );
                            $stmt->bindValue(
                                ':max_template_index_premium',
                                $column->getPremiumMaxTemplateIndex(),
                                \PDO::PARAM_INT
                            );
                            $stmt->bindValue(
                                ':allowed_in_segments',
                                $column->isAllowedInSegments(),
                                \PDO::PARAM_INT
                            );
                            $stmt->bindValue(
                                ':deprecated',
                                $column->isDeprecated(),
                                \PDO::PARAM_INT
                            );
                            $stmt->execute();
                        }
                        $stmt = null;
                    }
                    if (array_key_exists('etag', $this->_responseParsed) &&
                        $this->_responseParsed['etag'])
                    {
                        $newETag = $this->_responseParsed['etag'];
                    }
                    else {
                        $responseHeader = $this->getResponseHeaderAsAssociativeArray();
                        if (array_key_exists('ETag', $responseHeader) &&
                            $responseHeader['ETag'])
                        {
                            $newETag = $responseHeader['ETag'];
                        }
                    }
                    if ($newETag) {
                        self::_storeFetchData(
                            'google_analytics_api_columns',
                            count($columns),
                            $newETag
                        );
                    }
                }
                if (!$columns) {
                    $columns = array();
                    $stmt = self::$_dbConn->query(
                        'SELECT * FROM google_analytics_api_columns'
                    );
                    $stmt->bindColumn('name', $name, \PDO::PARAM_STR);
                    $stmt->bindColumn('type', $type, \PDO::PARAM_STR);
                    $stmt->bindColumn('data_type', $dataType, \PDO::PARAM_STR);
                    $stmt->bindColumn(
                        'replaced_by', $replacement, \PDO::PARAM_STR
                    );
                    $stmt->bindColumn('group', $group, \PDO::PARAM_STR);
                    $stmt->bindColumn('ui_name', $uiName, \PDO::PARAM_STR);
                    $stmt->bindColumn(
                        'description', $description, \PDO::PARAM_STR
                    );
                    $stmt->bindColumn(
                        'calculation', $calculation, \PDO::PARAM_STR
                    );
                    /* Null values get cast to zeros if I bind them as
                    integers, so we'll rely on the object's setter to take care
                    of the type casting. */
                    $stmt->bindColumn(
                        'min_template_index',
                        $minTemplateIndex,
                        \PDO::PARAM_STR
                    );
                    $stmt->bindColumn(
                        'max_template_index',
                        $maxTemplateIndex,
                        \PDO::PARAM_STR
                    );
                    $stmt->bindColumn(
                        'min_template_index_premium',
                        $minTemplateIndexPremium,
                        \PDO::PARAM_STR
                    );
                    $stmt->bindColumn(
                        'max_template_index_premium',
                        $maxTemplateIndexPremium,
                        \PDO::PARAM_STR
                    );
                    $stmt->bindColumn(
                        'allowed_in_segments',
                        $allowedInSegments,
                        \PDO::PARAM_BOOL
                    );
                    $stmt->bindColumn(
                        'deprecated', $deprecated, \PDO::PARAM_BOOL
                    );
                    while ($stmt->fetch(\PDO::FETCH_BOUND)) {
                        $column = new Column();
                        $column->setID('ga:' . $name);
                        $column->setReplacementColumn($replacement);
                        $column->setType($type);
                        $column->setDataType($dataType);
                        $column->setGroup($group);
                        $column->setUIName($uiName);
                        $column->setDescription($description);
                        $column->setCalculation($calculation);
                        if ($minTemplateIndex !== null) {
                            $column->setMinTemplateIndex($minTemplateIndex);
                        }
                        if ($maxTemplateIndex !== null) {
                            $column->setMaxTemplateIndex($maxTemplateIndex);
                        }
                        if ($minTemplateIndexPremium !== null) {
                            $column->setPremiumMinTemplateIndex(
                                $minTemplateIndexPremium
                            );
                        }
                        if ($maxTemplateIndexPremium !== null) {
                            $column->setPremiumMaxTemplateIndex(
                                $maxTemplateIndexPremium
                            );
                        }
                        $column->isAllowedInSegments($allowedInSegments);
                        $column->isDeprecated($deprecated);
                        $columns[] = $column;
                    }
                }
            } catch (\PDOException $e) {
                throw new RuntimeException(
                    'Caught database error while refreshing column metadata.',
                    null,
                    $e
                );
            }
        }
        else {
            $columns = $this->_makeRequest(new APIRequest(
                'metadata/ga/columns'
            ));
        }
        foreach ($columns as $column) {
            if ($column->getType() == 'DIMENSION') {
                $table = &self::$_dimensions;
                $list = &self::$_dimensionNames;
            }
            else {
                $table = &self::$_metrics;
                $list = &self::$_metricNames;
            }
            $name = $column->getName();
            $table[$name] = $column;
            if (!$column->isDeprecated()) {
                $list[] = $name;
            }
        }
        usort(self::$_dimensionNames, 'strcasecmp');
        usort(self::$_metricNames, 'strcasecmp');
    }
    
    /**
     * Populates the various properties that hold account/web property/profile
     * information for direct access rather than traversing the objects.
     */
    private function _populateAccountProperties() {
        if (self::$_accountsByName) {
            return;
        }
        if (self::$_dbConn) {
            try {
                /* See whether we can just pull from the database instead of
                hitting the API. */
                $lastFetchData = self::_getLastFetchData(
                    'google_analytics_api_account_summaries'
                );
                if ($lastFetchData) {
                    $fetchDate = (int)$lastFetchData['fetch_date'];
                }
                else {
                    $fetchDate = null;
                }
                $accounts = null;
                if ($this->_bypassAccountCache || !$fetchDate ||
                    $fetchDate <= time() - GOOGLE_ANALYTICS_API_ACCOUNTS_CACHE_DURATION)
                {
                    // Turn this back off again
                    $this->_bypassAccountCache = false;
                    /* Keep lists of the visible account, web property, and
                    profile IDs so that we can update our database regarding
                    anything to which we have lost access since the last time
                    we checked. */
                    $visibleAccounts = array();
                    $visibleWebProperties = array();
                    $visibleProfiles = array();
                    $accounts = $this->_makeRequest(new APIRequest(
                        'management/accountSummaries'
                    ));
                    $selectAccount = self::$_DB_STATEMENTS[
                        'google_analytics_api_account_summaries'
                    ]['select'];
                    $insertAccount = self::$_DB_STATEMENTS[
                        'google_analytics_api_account_summaries'
                    ]['insert'];
                    $selectWebProperty = self::$_DB_STATEMENTS[
                        'google_analytics_api_web_property_summaries'
                    ]['select'];
                    $insertWebProperty = self::$_DB_STATEMENTS[
                        'google_analytics_api_web_property_summaries'
                    ]['insert'];
                    $insertProfile = self::$_DB_STATEMENTS[
                        'google_analytics_api_profile_summaries'
                    ]['insert'];
                    foreach ($accounts as $account) {
                        $accountGID = $account->getID();
                        $visibleAccounts[] = self::$_dbConn->quote($accountGID);
                        $insertAccount->bindValue(
                            ':gid', $accountGID, \PDO::PARAM_STR
                        );
                        $insertAccount->bindValue(
                            ':name', $account->getName(), \PDO::PARAM_STR
                        );
                        $insertAccount->execute();
                        $selectAccount->bindValue(
                            ':gid', $accountGID, \PDO::PARAM_STR
                        );
                        $selectAccount->execute();
                        $selectAccount->bindColumn(
                            'id', $accountID, \PDO::PARAM_INT
                        );
                        $selectAccount->execute();
                        $selectAccount->fetch(\PDO::FETCH_BOUND);
                        $webProperties = $account->getWebPropertySummaries();
                        foreach ($webProperties as $webProperty) {
                            $webPropertyGID = $webProperty->getID();
                            $visibleWebProperties[] = self::$_dbConn->quote(
                                $webPropertyGID
                            );
                            $insertWebProperty->bindValue(
                                ':gid', $webPropertyGID, \PDO::PARAM_STR
                            );
                            $insertWebProperty->bindValue(
                                ':gaaas_id', $accountID, \PDO::PARAM_INT
                            );
                            $insertWebProperty->bindValue(
                                ':name',
                                $webProperty->getName(),
                                \PDO::PARAM_INT
                            );
                            $insertWebProperty->bindValue(
                                ':url', $webProperty->getURL(), \PDO::PARAM_STR
                            );
                            $insertWebProperty->bindValue(
                                ':level',
                                $webProperty->getLevel(),
                                \PDO::PARAM_STR
                            );
                            $insertWebProperty->execute();
                            $selectWebProperty->bindValue(
                                ':gid', $webPropertyGID, \PDO::PARAM_STR
                            );
                            $selectWebProperty->execute();
                            $selectWebProperty->bindColumn(
                                'id', $webPropertyID, \PDO::PARAM_INT
                            );
                            $selectWebProperty->fetch(\PDO::FETCH_BOUND);
                            $profiles = $webProperty->getProfileSummaries();
                            foreach ($profiles as $profile) {
                                $profileGID = $profile->getID();
                                $visibleProfiles[] = self::$_dbConn->quote(
                                    $profileGID
                                );
                                $insertProfile->bindValue(
                                    ':gid', $profileGID, \PDO::PARAM_STR
                                );
                                $insertProfile->bindValue(
                                    ':gaawps_id',
                                    $webPropertyID,
                                    \PDO::PARAM_INT
                                );
                                $insertProfile->bindValue(
                                    ':name',
                                    $profile->getName(),
                                    \PDO::PARAM_STR
                                );
                                $insertProfile->bindValue(
                                    ':type',
                                    $profile->getType(),
                                    \PDO::PARAM_STR 
                                );
                                $insertProfile->execute();
                            }
                        }
                    }
                    self::$_dbConn->exec(
                        'UPDATE google_analytics_api_account_summaries SET ' .
                        'visible = 0 WHERE gid NOT IN (' .
                        implode(', ', $visibleAccounts) . ')'
                    );
                    self::$_dbConn->exec(
                        'UPDATE google_analytics_api_web_property_summaries ' .
                        'SET visible = 0 WHERE gid NOT IN (' .
                        implode(', ', $visibleWebProperties) . ')'
                    );
                    self::$_dbConn->exec(
                        'UPDATE google_analytics_api_profile_summaries SET ' .
                        'visible = 0 WHERE gid NOT IN (' .
                        implode(', ', $visibleProfiles) . ')'
                    );
                    self::_storeFetchData(
                        'google_analytics_api_account_summaries',
                        count($accounts)
                    );
                }
                else {
                    $accounts = array();
                    $q = <<<EOF
SELECT gaaas.gid as account_id,
gaaas.name as account_name,
gaawps.gid as prop_id,
gaawps.name as prop_name,
gaawps.url as prop_url,
gaawps.level as prop_level,
gaaps.gid as profile_id,
gaaps.name as profile_name,
gaaps.type as profile_type
FROM google_analytics_api_account_summaries gaaas
LEFT JOIN google_analytics_api_web_property_summaries gaawps
ON gaaas.id = gaawps.gaaas_id
AND gaawps.visible
LEFT JOIN google_analytics_api_profile_summaries gaaps
ON gaawps.id = gaaps.gaawps_id
AND gaaps.visible
WHERE gaaas.visible
EOF;
                    $stmt = self::$_dbConn->query($q);
                    $accountData = null;
                    $webPropertyData = null;
                    $lastAccountID = null;
                    $lastWebPropertyID = null;
                    while (true) {
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if (!$row || $lastWebPropertyID !== $row['prop_id']) {
                            if ($webPropertyData) {
                                $accountData['webProperties'][] = $webPropertyData;
                                $webPropertyData = null;
                            }
                            if ($row && $row['prop_id']) {
                                $webPropertyData = array(
                                    'kind' => 'analytics#webPropertySummary',
                                    'profiles' => array(),
                                    'id' => $row['prop_id'],
                                    'name' => $row['prop_name'],
                                    'websiteUrl' => $row['prop_url'],
                                    'level' => $row['prop_level']
                                );
                            }
                        }
                        if (!$row || $lastAccountID !== $row['account_id']) {
                            if ($accountData) {
                                $accounts[] = new AccountSummary($accountData);
                            }
                            if ($row) {
                                $accountData = array(
                                    'kind' => 'analytics#accountSummary',
                                    'webProperties' => array(),
                                    'id' => $row['account_id'],
                                    'name' => $row['account_name']
                                );
                            }
                        }
                        if ($row) {
                            if ($row['profile_id']) {
                                $webPropertyData['profiles'][] = array(
                                    'kind' => 'analytics#profileSummary',
                                    'id' => $row['profile_id'],
                                    'name' => $row['profile_name'],
                                    'type' => $row['profile_type']
                                );
                            }
                            $lastAccountID = $row['account_id'];
                            $lastWebPropertyID = $row['prop_id'];
                        }
                        else {
                            break;
                        }
                    }
                }
            } catch (\PDOException $e) {
                throw new RuntimeException(
                    'Caught database error while populating account properties.',
                    null,
                    $e
                );
            }
        }
        else {
            $accounts = $this->_makeRequest(new APIRequest(
                'management/accountSummaries'
            ));
        }
        foreach ($accounts as $account) {
            self::$_accountsByID[$account->getID()] = $account;
            self::_populateProperty(
                $account->getName(), $account, self::$_accountsByName
            );
            $webProperties = $account->getWebPropertySummaries();
            foreach ($webProperties as $webProperty) {
                self::$_webPropertiesByID[
                    $webProperty->getID()
                ] = $webProperty;
                self::_populateProperty(
                    $webProperty->getName(),
                    $webProperty,
                    self::$_webPropertiesByName
                );
                $profiles = $webProperty->getProfileSummaries();
                foreach ($profiles as $profile) {
                    self::$_profilesByID[$profile->getID()] = $profile;
                    self::_populateProperty(
                        $profile->getName(),
                        $profile,
                        self::$_profilesByName
                    );
                }
            }
        }
    }
    
    /**
     * Returns an object or an array of objects from the parsed response.
     *
     * @return array, Google\Analytics\AbstractAPIResponseObject
     */
    protected function _castResponse() {
        if (array_key_exists('nextLink', $this->_responseParsed)) {
            try {
                $this->_nextRequest = clone $this->_request;
                $this->_nextRequest->setURL(new \URL(
                    $this->_responseParsed['nextLink']
                ));
            } catch (\URLException $e) {
                throw new UnexpectedValueException(
                    'Caught error while parsing paginated URL.', null, $e
                );
            }
        }
        else {
            $this->_nextRequest = null;
        }
        if (array_key_exists('items', $this->_responseParsed)) {
            $items = array();
            foreach ($this->_responseParsed['items'] as $item) {
                $items[] = APIResponseObjectFactory::create($item);
            }
            return $items;
        }
        return APIResponseObjectFactory::create($this->_responseParsed);
    }
    
    /** 
     * Reacts to an error condition reported by the API.
     */
    protected function _handleError() {
        if ($this->_responseCode < 400 && !\PFXUtils::nestedArrayKeyExists(
            array('error', 'errors'), $this->_responseParsed
        )) {
            return;
        }
        $this->_activeQuery = null;
        $reason = null;
        $message = null;
        $responseError = null;
        $responseID = uniqid();
        if (!\PFXUtils::nestedArrayKeyExists(
            array('error', 'errors'), $this->_responseParsed
        ) || !$this->_responseParsed['error']['errors']) {
            $responseError = 'Could not find error detail in API response '
                           . '(response code was ' . $this->_responseCode
                           . ', response ID is ' . $responseID . ').';
        }
        elseif (array_key_exists(
            'reason', $this->_responseParsed['error']['errors'][0]
        )) {
            $reason = $this->_responseParsed['error']['errors'][0]['reason'];
        }
        else {
            $responseError = 'Could not find reason code in API response '
                           . '(response code was ' . $this->_responseCode
                           . ', response ID is ' . $responseID . ').';
        }
        if (\PFXUtils::nestedArrayKeyExists(
            array('error', 'message'), $this->_responseParsed
        )) {
            $message = $this->_responseParsed['error']['message'];
        }
        else {
            $message = 'Could not find error message in API response '
                     . '(response code was ' . $this->_responseCode . ').';
        }
        switch ($this->_responseCode) {
            case 400:
                if ($reason == 'invalidParameter') {
                    throw new InvalidParameterException($message);
                }
                if ($reason == 'badRequest') {
                    throw new BadRequestException($message);
                }
                break;
            case 401:
                throw new InvalidCredentialsException($message);
                break;
            case 403:
                if ($reason == 'insufficientPermissions') {
                    throw new InsufficientPermissionsException($message);
                }
                if ($reason == 'dailyLimitExceeded') {
                    throw new DailyLimitExceededException($message);
                }
                if ($reason == 'userRateLimitExceeded') {
                    throw new UserRateLimitExceededException($message);
                }
                if ($reason == 'quotaExceeded') {
                    throw new QuotaExceededException($message);
                }
                break;
            case 503:
                throw new RemoteException($message);
                break;
        }
        if ($responseError) {
            /* The API response didn't contain the error messaging it
            should have, so log it. */
            $this->_logger->log(
                'Raw content of response ID ' . $responseID . ': ' .
                $this->_responseRaw,
                false
            );
            if ($message === null) {
                $message = $responseError;
            }
        }
        throw new RuntimeException($message);
    }
    
    /**
     * Adds the 'ga:' prefix, if necessary, to the argument. If the argument is
     * an array, this method does this on every element in the array. Returns
     * the modified version of the argument.
     *
     * @param string, array $arg
     * @return string, array
     */
    public static function addPrefix($arg) {
        if (is_scalar($arg)) {
            if (substr($arg, 0, 3) != 'ga:') {
                $arg = 'ga:' . $arg;
            }
            return $arg;
        }
        if (is_array($arg)) {
            foreach ($arg as &$val) {
                if (substr($val, 0, 3) != 'ga:') {
                    $val = 'ga:' . $val;
                }
            }
            return $arg;
        }
        throw new InvalidArgumentException(
            'Arguments to this method must be strings or arrays.'
        );
    }
    
    /**
     * Returns an array of Google\Analytics\Segment objects that describe the
     * preset segments to which the effective credentials permit access.
     *
     * @return array
     */
    public function getSegments() {
        return $this->_makeRequest(new APIRequest('management/segments'));
    }
    
    /**
     * Returns the total count of rows fetched in the last query.
     *
     * @return int
     */
    public function getLastFetchedRowCount() {
        return $this->_totalRows;
    }
    
    /**
     * Performs a Google Analytics query with the given
     * Google\Analytics\GaDataQuery object and returns the result as a
     * Google\Analytics\GaData object, or boolean false if the query's data
     * has been exhausted.
     *
     * @param Google\Analytics\GaDataQuery $query
     * @return Google\Analytics\GaData, boolean
     */
    public function query(GaDataQuery $query) {
        $query->setAPIInstance($this);
        $totalResults = $query->getTotalResults();
        do {
            /* If the given query is already active, meaning that we have made
            a call for its first page of results already, don't pass any
            arguments to $this->_makeRequest(). */
            $hash = $query->getHash();
            if ($hash == $this->_activeQuery) {
                if ($totalResults !== null && $this->_totalRows >= $totalResults)
                {
                    // No reason to continue
                    $response = false;
                    break;
                }
                $response = $this->_makeRequest();
            }
            else {
                $this->_activeQuery = $hash;
                $this->_totalRows = 0;
                $response = $this->_makeRequest(new APIRequest(
                    'data/ga', $query->getAsArray()
                ));
            }
            /* The response will be boolean false if the query had data, but it
            has been exhausted. It will be a Google\Analytics\GaData object
            with no rows if the query returned no data at all. If there's no
            chance of achieving a response by iterating, we'll consider the
            operation as having retrieved a response, even if no data came
            back. */
            if (($response && $response->getRows()) ||
                !($query instanceof IterativeGaDataQuery) ||
                !$query->iterate())
            {
                $gotResponse = true;
            }
            else {
                $gotResponse = false;
            }
        } while (!$gotResponse);
        if ($response) {
            if ($query->getSamplingLevel() == GaDataQuery::SAMPLING_LEVEL_NONE &&
                $response->containsSampledData())
            {
                throw new SamplingException(
                    'The response contains sampled data.'
                );
            }
            /* I have to do this here instead of allowing it to happen
            automatically so that I can pass the API instance as an argument.
            */
            $response->setColumnHeaders(
                $this->_responseParsed['columnHeaders'], $this
            );
            // I also want the rows object to be aware of the columns object
            $rows = $response->getRows();
            $rows->setColumnHeaders($response->getColumnHeaders());
            $rowCount = count($rows);
            if ($rowCount) {
                $this->_totalRows += $rowCount;
                if ($totalResults !== null) {
                    $surplus = $this->_totalRows - $totalResults;
                    if ($surplus > 0) {
                        /* Even though we already got the data, behave as we
                        were asked and lop off the extra rows. */
                        $rows->discard($surplus);
                    }
                }
            }
        }
        else {
            $this->_activeQuery = null;
        }
        return $response;
    }
    
    /**
     * Performs a Google Analytics query with the given Google\Analytics\IQuery
     * object and writes the result to a file using the given
     * Google\Analytics\ReportFormatter object. Returns the number of bytes
     * written.
     *
     * @param Google\Analytics\IQuery $query
     * @param Google\Analytics\ReportFormatter $formatter
     * @return int
     */
    public function queryToFile(IQuery $query, ReportFormatter $formatter) {
        $this->_failedIterations = array();
        $isCollection = $query instanceof GaDataQueryCollection;
        if ($isCollection) {
            $formatter->setReportCount(count($query));
        }
        foreach ($query as $queryInstance) {
            if ($isCollection) {
                $queryInstance->setAPIInstance($this);
                $formatter->writeMetadata(
                    $queryInstance->getName(),
                    $queryInstance->getProfile(),
                    $queryInstance->getSummaryStartDate(),
                    $queryInstance->getSummaryEndDate()
                );
            }
            $wroteHeaders = false;
            while (true) {
                try {
                    $data = $this->query($queryInstance);
                } catch (SamplingException $e) {
                    $this->_failedIterations[] = $queryInstance->iteration();
                    // No point in continuing with this iteration
                    continue;
                }
                /* The response will be boolean false if the query had data,
                but it has been exhausted. It will be a Google\Analytics\GaData
                object with no rows if the query returned no data at all. */
                if (!$data || !$data->getRows()) {
                    break;
                }
                if (!$wroteHeaders) {
                    $headers = $data->getColumnHeaders()->getColumnNames();
                    if ($queryInstance instanceof IterativeGaDataQuery) {
                        $headers = array_merge(
                            array($queryInstance->getIterativeName()), $headers
                        );
                    }
                    $formatter->writeHeaders($headers);
                    $wroteHeaders = true;
                }
                $rows = $data->getRows();
                while ($row = $rows->fetch()) {
                    if ($queryInstance instanceof IterativeGaDataQuery) {
                        $row = array_merge(
                            array($queryInstance->iteration()), $row
                        );
                    }
                    $formatter->writeRow($row);
                }
            }
        }
        if ($this->_failedIterations) {
            throw new FailedIterationsException(
                $this->getFailedIterationsMessage()
            );
        }
        return $formatter->getBytesWritten();
    }
    
    /**
     * Performs a Google Analytics query with the given Google\Analytics\IQuery
     * object and attaches the result to the given Email instance. If the email
     * does not yet have a subject, one will be set automatically. If failures
     * took place while running the query (e.g. the query declared a preference
     * for no data sampling and one or more iterations contained sampled data),
     * a message regarding the failure will be appended to the email
     * automatically. If a file path is provided as the fourth argument, the
     * report will be copied to that location in addition to being attached to
     * the email.
     *
     * @param Google\Analytics\IQuery $query
     * @param Google\Analytics\ReportFormatter $formatter
     * @param Email $email
     * @param string $file = null
     */
    public function queryToEmail(
        IQuery $query,
        ReportFormatter $formatter,
        \Email $email,
        $file = null
    ) {
        if ($file && !\PFXUtils::testWritable(dirname($file))) {
            throw new RuntimeException('Cannot write to ' . $file . '.');
        }
        $hasSubject = strlen($email->getSubject()) > 0;
        try {
            $formatter->openTempFile(GOOGLE_ANALYTICS_API_DATA_DIR);
            $exception = null;
            try {
                $this->queryToFile($query, $formatter);
            } catch (FailedIterationsException $e) {
                /* Trap this exception here for now and deliver whatever data
                we can. */
                $exception = $e;
            }
            $bytesWritten = $formatter->getBytesWritten();
            if ($bytesWritten) {
                $tempFile = $formatter->getFileName();
                if ($bytesWritten > GOOGLE_ANALYTICS_API_AUTOZIP_THRESHOLD) {
                    $zip = new \ZipArchive();
                    $tempZip = tempnam(GOOGLE_ANALYTICS_API_DATA_DIR, 'gaz');
                    if ($zip->open($tempZip, \ZipArchive::CREATE) !== true) {
                        throw new RuntimeException(
                            'Failed to initialize ZIP archive. Did not ' .
                            'include the file ' . basename($tempFile) .
                            ' in this message due to its size exceeding ' .
                            GOOGLE_ANALYTICS_API_AUTOZIP_THRESHOLD . ' bytes.'
                        );
                    }
                    $zip->addFile($tempFile, 'ga-report.csv');
                    $zip->close();
                    $email->addAttachment($tempZip, 'ga-report.zip');
                    unlink($tempZip);
                }
                else {
                    $email->addAttachment($tempFile, 'ga-report.csv');
                }
                if ($file && !copy($tempFile, $file)) {
                    throw new RuntimeException(
                        'Unable to copy report to ' . $file . '.'
                    );
                }
                unlink($tempFile);
            }
            if (!$hasSubject) {
                $email->setSubject($query->getEmailSubject());
                $hasSubject = true;
            }
            if ($exception) {
                throw $exception;
            }
            elseif (!$bytesWritten) {
                throw new UnderflowException(
                    'Google returned no data for this query.'
                );
            }
        } catch (\Exception $e) {
            if (!$hasSubject) {
                /* This should only happen if there is an error affecting the
                report as a whole, not just in one or more of its iterations.
                */
                $email->setSubject(
                    'Encountered failure while running ' .
                    $query->getEmailSubject()
                );
            }
            $email->appendMessage($e->getMessage(), true);
        }
    }
    
    /**
     * Gets an array containing the iterations that failed during the previous
     * iterative query (if any) due to the presence of sampled data. The data
     * type contained within this return value will vary depending on what the
     * query object's iteration() method returns.
     *
     * @return array
     */
    public function getFailedIterations() {
        // Cast to an empty array if necessary
        return $this->_failedIterations === null ?
            array() : $this->_failedIterations;
    }
    
    /**
     * Gets a message explaining how many iterations failed in the last query
     * and why.
     *
     * @return string
     */
    public function getFailedIterationsMessage() {
        if ($this->_failedIterations) {
            return \PFXUtils::quantify(
                count($this->_failedIterations), 'iteration'
            ) . ' failed . Failed iterations were ' . \PFXUtils::implodeSemantically(
                ', ', $this->_failedIterations
            ) . '.';
        }
    }
    
    /**
     * Returns the object representation of the specified column.
     *
     * @param string $name
     * @return Google\Analytics\Column
     */
    public function getColumn($name) {
        if (!self::$_dimensions) {
            $this->_refreshColumnMetadata();
        }
        // These are indexed without the prefix
        if (substr($name, 0, 3) == 'ga:') {
            $name = substr($name, 3);
        }
        if (isset(self::$_dimensions[$name])) {
            return self::$_dimensions[$name];
        }
        if (isset(self::$_metrics[$name])) {
            return self::$_metrics[$name];
        }
        throw new InvalidArgumentException(
            'Unrecognized column "' . $name . '".'
        );
    }
    
    /**
     * Returns a list of available dimension names, optionally including
     * deprecated dimensions.
     *
     * @param boolean $includeDeprecated = false
     * @return array
     */
    public function getDimensions($includeDeprecated = false) {
        if (!self::$_dimensions) {
            $this->_refreshColumnMetadata();
        }
        if (!$includeDeprecated) {
            return self::$_dimensionNames;
        }
        $dimensions = array_keys(self::$_dimensions);
        usort($dimensions, 'strcasecmp');
        return $dimensions;
    }
    
    /**
     * Returns a list of available metric names, optionally including
     * deprecated metrics.
     *
     * @param boolean $includeDeprecated = false
     * @return array
     */
    public function getMetrics($includeDeprecated = false) {
        if (!self::$_metrics) {
            $this->_refreshColumnMetadata();
        }
        if (!$includeDeprecated) {
            return self::$_metricNames;
        }
        $metrics = array_keys(self::$_metrics);
        usort($metrics, 'strcasecmp');
        return $metrics;
    }
    
    /**
     * Forces the next call to any of the methods that retrieve account
     * summaries or members thereof to cause a new query to the Google API.
     */
    public function clearAccountCache() {
        self::$_accountsByName = array();
        self::$_accountsByID = array();
        self::$_webPropertiesByName = array();
        self::$_webPropertiesByID = array();
        self::$_profilesByName = array();
        self::$_profilesByID = array();
        $this->_bypassAccountCache = true;
    }
    
    /**
     * Forces the next call to any of the methods that retrieve dimensions or
     * metrics to cause a new query to the Google API.
     */
    public function clearColumnCache() {
        self::$_dimensions = array();
        self::$_dimensionNames = array();
        self::$_metrics = array();
        self::$_metricNames = array();
        $this->_bypassColumnCache = true;
    }
    
    /**
     * Returns an array of Google\Analytics\AccountSummary objects that
     * describe the accounts/profiles/views to which the effective credentials
     * permit access.
     *
     * @return array
     */
    public function getAccountSummaries() {
        $this->_populateAccountProperties();
        return array_values(self::$_accountsByID);
    }
    
    /**
     * @param string $name
     * @return Google\Analytics\AccountSummary
     */
    public function getAccountSummaryByName($name) {
        return $this->_getAccountPropertyByName($name, self::$_accountsByName);
    }
    
    /**
     * @param string $id
     * @return Google\Analytics\AccountSummary
     */
    public function getAccountSummaryByID($id) {
        return $this->_getAccountPropertyByID($id, self::$_accountsByID);
    }
    
    /**
     * @param string $name
     * @return Google\Analytics\WebPropertySummary
     */
    public function getWebPropertySummaryByName($name) {
        return $this->_getAccountPropertyByName(
            $name, self::$_webPropertiesByName
        );
    }
    
    /**
     * @param string $id
     * @return Google\Analytics\WebPropertySummary
     */
    public function getWebPropertySummaryByID($id) {
        return $this->_getAccountPropertyByID($id, self::$_webPropertiesByID);
    }
    
    /**
     * @param string $name
     * @return Google\Analytics\ProfileSummary
     */
    public function getProfileSummaryByName($name) {
        return $this->_getAccountPropertyByName($name, self::$_profilesByName);
    }
    
    /**
     * @param string $id
     * @return Google\Analytics\ProfileSummary
     */
    public function getProfileSummaryByID($id) {
        return $this->_getAccountPropertyByID($id, self::$_profilesByID);
    }
}
?>
