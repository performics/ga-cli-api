<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class QueryConfiguration {
    /* This class serves as an interface for parsing command line options or
    XML configurations for the GA query runner. */
    private $_query;
    private $_formatter;
    private $_email;
    private $_fileName;
    
    /**
     * Helper method to create a single query instance from an associative
     * array of command-line arguments or XML configuration values.
     *
     * @param array $args
     * @return Google\Analytics\GaDataQuery
     */
    private static function _getQuery(array $args) {
        if (!isset($args['profile-name']) && !isset($args['profile-id'])) {
            throw new InvalidArgumentException(
                'A profile name or ID must be specified.'
            );
        }
        if (!isset($args['metric'])) {
            throw new InvalidArgumentException(
                'At least one metric must be specified.'
            );
        }
        if (!isset($args['start-date']) || !isset($args['end-date'])) {
            throw new InvalidArgumentException(
                'A start date and an end date must be specified.'
            );
        }
        // See whether we have to resolve date shortcuts
        $dateKeys = array('start-date', 'end-date');
        foreach ($dateKeys as $key) {
            if (preg_match('/^[A-Z]+_[_A-Z]+_[A-Z]+$/', $args[$key])) {
                $r = new \ReflectionClass(__NAMESPACE__ . '\GaDataQuery');
                try {
                    $args[$key] = $r->getConstant($args[$key]);
                } catch (\ReflectionException $e) {
                    throw new InvalidArgumentException(
                        '"' . $args[$key] . '" is not a valid date shortcut.'
                    );
                }
            }
        }
        if (isset($args['split-queries-by'])) {
            $interval = strtoupper($args['split-queries-by']);
            if ($interval != 'DAY' && $interval != 'WEEK' &&
                $interval != 'MONTH' && $interval != 'YEAR')
            {
                throw new InvalidArgumentException(
                    'Queries may only be split by day, week, month, or year.'
                );
            }
            $q = new DateRangeGaDataQuery(
                null,
                $args['start-date'],
                $args['end-date'],
                new \DateInterval('P1' . $interval[0])
            );
            $q->setIterativeName(ucfirst(strtolower($interval)));
            if (isset($args['date-format-string'])) {
                $q->setFormatString($args['date-format-string']);
            }
        }
        else {
            $q = new GaDataQuery();
            $q->setStartDate($args['start-date']);
            $q->setEndDate($args['end-date']);
        }
        if (isset($args['name'])) {
            $q->setName($args['name']);
        }
        if (isset($args['profile-id'])) {
            $q->setProfile($args['profile-id']);
        }
        else {
            $q->setProfileName($args['profile-name']);
        }
        $q->setMetrics(explode(',', $args['metric']));
        if (isset($args['dimension'])) {
            $q->setDimensions(explode(',', $args['dimension']));
        }
        if (isset($args['sort'])) {
            $sort = new GaDataSortOrder();
            $sortStrings = explode(',', $args['sort']);
            foreach ($sortStrings as $sortString) {
                if (!strlen($sortString)) {
                    continue;
                }
                if ($sortString[0] == '-') {
                    $order = SORT_DESC;
                    $sortString = substr($sortString, 1);
                }
                else {
                    $order = SORT_ASC;
                }
                $sort->addField($sortString, $order);
            }
            $q->setSort($sort);
        }
        if (isset($args['filter'])) {
            $q->setFilter($args['filter']);
        }
        if (isset($args['segment'])) {
            $q->setSegment($args['segment']);
        }
        if (isset($args['sampling-level'])) {
            $q->setSamplingLevel(strtoupper($args['sampling-level']));
        }
        return $q;
    }
    
    /**
     * Helper method to return a query or query collection based on command-
     * line arguments (not XML configuration values).
     *
     * @param array $args
     * @return Google\Analytics\IQuery
     */
    private static function _getQueryFromCommandLineArgs(array $args) {
        /* Sort out which arguments have been invoked once and which ones have
        been invoked multiple times. */
        $multipleCount = null;
        $singleInvocations = array();
        $multipleInvocations = array();
        foreach ($args as $argKey => $argValue) {
            if (is_array($argValue)) {
                $count = count($argValue);
                if ($multipleCount === null) {
                    $multipleCount = $count;
                }
                elseif ($count != $multipleCount) {
                    throw new InvalidArgumentException(
                        'Found ' . $count . ' uses of the "' . $argKey . '" ' .
                        'argument; expected ' . $multipleCount . '. Please ' .
                        'ensure that all arguments are invoked either a ' .
                        'single time only or the same number of times.'
                    );
                }
                $multipleInvocations[$argKey] = $argValue;
            }
            else {
                $singleInvocations[$argKey] = $argValue;
            }
        }
        if (!$multipleCount) {
            // No need to navigate any multiple instances of anything
            return self::_getQuery($args);
        }
        $r = new \ReflectionClass(__NAMESPACE__ . '\GaDataQueryCollection');
        $queries = array();
        for ($i = 0; $i < $multipleCount; $i++) {
            $args = $singleInvocations;
            foreach ($multipleInvocations as $argKey => $argValue) {
                // Skip the placeholder instances
                if ($argValue[$i] == '_') {
                    continue;
                }
                // Unescape the underscore if necessary
                if ($argValue[$i] == '\_') {
                    $argValue[$i] = '_';
                }
                $args[$argKey] = $argValue[$i];
            }
            $queries[] = self::_getQuery($args);
        }
        $q = $r->newInstanceArgs($queries);
        if (isset($args['group-name'])) {
            $q->setName($args['group-name']);
        }
        return $q;
    }
    
    /**
     * Validates the high-level arguments (not the arguments related to
     * queries) and sets the appropriate properties.
     *
     * @param array &$args
     */
    private function _validateArgs(array &$args) {
        if (isset($args['formatter'])) {
            if (class_exists($args['formatter']) && (is_subclass_of(
                $args['formatter'], 'Google\Analytics\ReportFormatter'
            ) || $args['formatter'] == 'Google\Analytics\ReportFormatter')) {
                $this->_formatter = new $args['formatter']();
            }
            else {
                throw new InvalidArgumentException(
                    'Invalid report formatter class name.'
                );
            }
        }
        else {
            $this->_formatter = new ReportFormatter();
        }
        if (isset($args['email'])) {
            $v = new \Validator();
            $email = $v->email(
                $args['email'],
                '"' . $args['email'] . '" is not a valid email address.',
                \Validator::FILTER_TRIM | \Validator::ASSERT_TRUTH
            );
            $this->_email = new \Email($email);
        }
        if (isset($args['file'])) {
            $this->_fileName = $args['file'];
        }
        if (!$this->_email && !$this->_fileName) {
            throw new InvalidArgumentException(
                'An email address and/or an output file must be specified.'
            );
        }
    }
    
    /**
     * @param array $args
     * @return Google\Analytics\QueryConfiguration
     */
    public static function createFromCommandLineArgs(array $args) {
        if (isset($args['conf'])) {
            return self::createFromXML($args['conf']);
        }
        $config = new self();
        // This validation step is only a concern in a command-line context
        $singleInstanceArgs = array('email', 'file', 'formatter', 'group-name');
        foreach ($singleInstanceArgs as $arg) {
            if (isset($args[$arg]) && is_array($args[$arg])) {
                throw new InvalidArgumentException(
                    'The "' . $arg . '" argument may not be used multiple ' .
                    'times.'
                );
            }
        }
        $config->_validateArgs($args);
        $config->_query = self::_getQueryFromCommandLineArgs($args);
        return $config;
    }
    
    /**
     * @param string $configFile
     * @return Google\Analytics\QueryConfiguration
     */
    public static function createFromXML($configFile) {
        if (!file_exists($configFile)) {
            throw new InvalidArgumentException(
                $configFile . ' does not exist.'
            );
        }
        $xmlContent = file_get_contents($configFile);
        if ($xmlContent === false) {
            throw new RuntimeException(
                'Unable to load XML from ' . $configFile . '.'
            );
        }
        $xml = new \DOMDocument();
        if (!$xml->loadXML($xmlContent)) {
            throw new RuntimeException(
                'Encountered error while parsing XML content.'
            );
        }
        $globalArgs = array();
        $xPath = new \DOMXPath($xml);
        $result = $xPath->query('/conf');
        if ($result->length != 1) {
            throw new UnexpectedValueException(
                'The XML configuration file must contain exactly one <conf> ' .
                'element at the outermost level.'
            );
        }
        $conf = $result->item(0);
        $result = $xPath->query('queries', $conf);
        if ($result->length != 1) {
            throw new UnexpectedValueException(
                'The <conf> element in the XML configuration file must ' .
                'contain exactly one <queries> element.'
            );
        }
        $queries = $xPath->query('query', $result->item(0));
        $queryCount = $queries->length;
        if (!$queryCount) {
            throw new UnexpectedValueException(
                'The <queries> element in the XML configuration must ' .
                'contain at least one <query> element.'
            );
        }
        $config = new self();
        // Any attribute on the conf element is a global configuration value
        foreach ($conf->attributes as $attName => $attVal) {
            $globalArgs[$attName] = $attVal->nodeValue;
        }
        // This isn't allowed, obviously
        if (isset($globalArgs['conf'])) {
            throw new UnexpectedValueException(
                'Cannot specify an XML configuration file within an XML ' .
                'configuration file.'
            );
        }
        $config->_validateArgs($globalArgs);
        /* Merge the global arguments with each query's arguments as we go
        through and build the queries. */
        if ($queryCount == 1) {
            foreach ($queries->item(0)->attributes as $attName => $attVal) {
                $globalArgs[$attName] = $attVal->nodeValue;
            }
            $config->_query = self::_getQuery($globalArgs);
        }
        else {
            $r = new \ReflectionClass(__NAMESPACE__ . '\GaDataQueryCollection');
            $queryList = array();
            for ($i = 0; $i < $queryCount; $i++) {
                $args = $globalArgs;
                foreach ($queries->item($i)->attributes as $attName => $attVal)
                {
                    $args[$attName] = $attVal->nodeValue;
                }
                $queryList[] = self::_getQuery($args);
            }
            $config->_query = $r->newInstanceArgs($queryList);
            if (isset($globalArgs['group-name'])) {
                $config->_query->setName($globalArgs['group-name']);
            }
        }
        return $config;
    }
    
    /**
     * Runs the query with the given Google\Analytics\API instance and sends
     * the email (if applicable).
     *
     * @param Google\Analytics\API
     */
    public function run(API $api) {
        if ($this->_email) {
            $api->queryToEmail(
                $this->_query,
                $this->_formatter,
                $this->_email,
                $this->_fileName
            );
            $this->_email->mail();
        }
        else {
            $this->_formatter->setFileName($this->_fileName);
            $api->queryToFile($this->_query, $this->_formatter);
            $failedIterations = $api->getFailedIterationsMessage();
            if ($failedIterations) {
                echo $failedIterations . PHP_EOL;
            }
            elseif (!$api->getLastFetchedRowCount()) {
                /* This logic is okay for now but would result in a false
				positive in the case of a query collection or an iterative
				query in which every iteration returned data except the final
				one. */
				echo 'Google returned no data for this query.' . PHP_EOL;
            }
        }
    }
    
    /**
     * @return Google\Analytics\IQuery
     */
    public function getQuery() {
        return $this->_query;
    }
    
    /**
     * @return Email
     */
    public function getEmail() {
        return $this->_email;
    }
    
    /**
     * @return string
     */
    public function getFileName() {
        return $this->_fileName;
    }
    
    /**
     * @return Google\Analytics\ReportFormatter
     */
    public function getReportFormatter() {
        return $this->_formatter;
    }
}
?>