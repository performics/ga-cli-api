<?php
class SettingException extends UnexpectedValueException {}
class PFXUtils {
    const ARRAY_SEARCH_STRICTMATCH = 1;
    const ARRAY_SEARCH_NEXTLOWEST = 2;
    const ARRAY_SEARCH_NEXTHIGHEST = 3;
    private static $_FILE_UPLOAD_ERRORS = array(
        1 => 'File exceeds maximum size specified by server configuration.',
        2 => 'File exceeds maximum size specified by HTML form.',
        3 => 'File upload was incomplete.',
        4 => 'No file was uploaded.',
        6 => 'File upload location not defined.',
        7 => 'Unable to write file to disk.',
        8 => 'File upload suppressed by server configuration.'
    );
    /* This caches PDO objects and time zone offsets in a structure keyed on a
    hash based on the database DSN, user name, password, and attributes. The
    structure is as follows:
    (string) hash key => array(
        'db' => (PDO),
        'time_zone_offset' => (int)
    )
    */
    private static $_dbCache = array();
    /* Statements built programmatically by self::lookupEntityID() are cached
    here and reused where possible. */
    private static $_queryCache = array();
    /* This caches table column length restrictions retrieved from
    INFORMATION_SCHEMA. */
    private static $_colData = array();
    /* This stores the database time zone offset from UTC, provided that at
    least one database connection exists and no two database connections with
    conflicting time zone offsets have been made. */
    private static $_dbTimeZoneOffset;
    /* This is a cache for the results of function_exists(), ini_get, or
    extension_exists() calls (so it isn't only for functions per se). */
    private static $_availableFunctions = array();
    private static $_memLimit;
    private static $_validatedSettings = array();
    private static $_registeredTwigAutoloader = false;
    private static $_notifyOnEntityLookupTruncate = true;
    private static $_lastUsedEncoding;
    private static $_lastUsedEncodingEOL;
    
    /**
     * Helper method for self::searchArray().
     *
     * @param array $arr
     * @param int $index
     * @param callable $getter = null
     * @return mixed
     */
    private static function _getValueAtArrayIndex(
        array $arr,
        $index,
        $getter = null
    ) {
        return $getter === null ? $arr[$index] : $getter($arr[$index]);
    }
    
    /**
     * Registers the Twig autoloader after first ensuring that Twig_Autoloader
     * is a valid class, which it should be assuming the Twig package has been
     * installed into classes/Twig. If this is not true, a
     * BadMethodCallException is thrown.
     */
    private static function _registerTwigAutoloader() {
        if (self::$_registeredTwigAutoloader) {
            return;
        }
        if (!class_exists('Twig_Autoloader')) {
            throw new BadMethodCallException(
                'The Twig_Autoloader class does not exist.'
            );
        }
        Twig_Autoloader::register();
        self::$_registeredTwigAutoloader = true;
    }
    
    /**
     * Helper method for self::escape() and self::explodeUnescaped().
     *
     * @param string $str
     * @param int $pos
     * @param string $escapeChar = '\'
     * @return boolean
     */
    private static function _isEscaped($str, $pos, $escapeChar = '\\') {
        return $pos > 0
            && $str[$pos - 1] == $escapeChar
            && !self::_isEscaped($str, $pos - 1, $escapeChar);
    }
    
    /**
     * Helper method for self::xmlToArray().
     *
     * @param SimpleXMLElement $xml
     * @return array
     */
    private static function _getXMLNodeValue(SimpleXMLElement $xml) {
        if ($xml->count()) {
            $val = array();
            foreach ($xml->children() as $child) {
                $childName = $child->getName();
                $childVal = self::_getXMLNodeValue($child);
                if (isset($val[$childName])) {
                    /* If there is already a value at this node name, turn it
                    into a numerically-indexed array. */
                    if (!is_array($val[$childName]) ||
                        !array_key_exists(0, $val[$childName]))
                    {
                        $val[$childName] = array($val[$childName]);
                    }
                    $val[$childName][] = $childVal;
                }
                else {
                    $val[$childName] = $childVal;
                }
            }
            return $val;
        }
        else {
            return trim((string)$xml);
        }
    }
    
    /**
     * Helper method for self::lookupEntityID().
     *
     * @param string $databaseName
     * @param string $tableName
     * @param string $columnName
     * @param boolean $isSelectQuery = true
     * @param PDOStatement $stmt = null
     * @return PDOStatement, boolean
     */
    private static function _getOrCacheStatement(
        $databaseName,
        $tableName,
        $columnName,
        $isSelectQuery = true,
        PDOStatement $stmt = null
    ) {
        $cacheKey = md5(implode(chr(31), array(
            $databaseName,
            $tableName,
            $columnName,
            $isSelectQuery ? '1' : '0'
        )));
        // If a statement was passed, we cache it; otherwise we return it
        if (isset(self::$_queryCache[$cacheKey])) {
            if (!$stmt) {
                return self::$_queryCache[$cacheKey];
            }
        }
        elseif ($stmt) {
            self::$_queryCache[$cacheKey] = $stmt;
        }
        else {
            return false;
        }
    }
    
    /**
     * Takes an associative array of setting names to default values and an
     * associative array of setting names to test types. The first thing this
     * method does is to verify that each setting is defined as a constant, and
     * if not, to define a constant using the default value provided. Then it
     * calls PFXUtils::testSettingTypes to verify that each setting meets
     * certain requirements.
     *
     * @param array $settings
     * @param array $settingTests
     */
    public static function validateSettings(
        array $settings,
        array $settingTests
    ) {
        foreach ($settings as $settingName => $settingVal) {
            if (!defined($settingName)) {
                define($settingName, $settingVal);
            }
        }
        self::testSettingTypes($settingTests);
    }

    /**
     * Given an associative array of setting constant names to tests, ensures
     * that each setting's value passes the appropriate validation test.
     * If a setting is defined as a value that does not pass the selected test,
     * a SettingException is thrown. The available tests are expressed as
     * strings, and work as follows:
     *
     * boolean, bool: Validates that the value is a boolean literal.
     *
     * string, str: Validates that the value is a string literal.
     *
     * integer, int: Tests the value against PHP's FILTER_VALIDATE_INT logic.
     *
     * num, number, numeric: Validates that the value is numeric according to
     * PHP's is_numeric() logic.
     *
     * ratio: Validates that the value is a number between 0 and 1 (inclusive).
     *
     * file: Validates that the value is a path to a real file or a link to a
     * real file.
     *
     * dir, directory: Validates that the value is a path to a real directory,
     * and that it does not end in a directory separator.
     *
     * writable: Validates that the value is a path to a file to which the
     * effective user has the ability to write. Note that this test will
     * succeed if the file does not exist, provided the effective user has the
     * ability to create new files in the containing directory.
     *
     * email: Validates that the value appears to be an email address, or
     * multiple email addresses in a comma-delimited list.
     *
     * executable: Validates that the value is a valid file system path with
     * the executable bit set.
     *
     * url: Tests whether the value is a valid URL.
     *
     * In addition, it is also possible to pass a Perl-formatted regular
     * expression in the same format that the PHP's PCRE functions accept; in
     * this case, the value will be tested for a match against the regular
     * expression.
     *
     * A setting may be defined as optional by using the prefix '?' on any
     * test (including regular expressions). When this flag is present and the
     * corresponding constant has not been defined, it will be automatically
     * defined as null.
     *
     * @param array $tests
     */
    public static function testSettingTypes(array $tests) {
        foreach ($tests as $setting => $test) {
            if (array_key_exists($setting, self::$_validatedSettings)) {
                /* These are constants so there's no point in checking them
                more than once. */
                continue;
            }
            if ($test[0] == '?') {
                $optional = true;
                $test = substr($test, 1);
            }
            else {
                $optional = false;
            }
            if (defined($setting)) {
                $settingVal = constant($setting);
            }
            elseif ($optional) {
                $settingVal = null;
            }
            else {
                throw new SettingException(
                    'Required setting ' . $setting . ' is not defined.'
                );
            }
            if ($optional && $settingVal === null) {
                continue;
            }
            if (substr($test, 0, 1) == '/') {
                if (!preg_match($test, $settingVal)) {
                    throw new SettingException(
                        'Setting ' . $setting . ' failed the required ' .
                        'regular expression match.'
                    );
                }
                continue;
            }
            $exceptionMessage = null;
            switch ($test) {
                case 'boolean':
                case 'bool':
                    if (!is_bool($settingVal)) {
                        $exceptionMessage = 'Setting ' . $setting . ' must be '
                                          . 'a boolean value.';
                    }
                    break;
                case 'string':
                case 'str':
                    if (!is_string($settingVal)) {
                        $exceptionMessage = 'Setting ' . $setting . ' must be '
                                          . 'a string value.';
                    }
                    break;
                case 'integer':
                case 'int':
                    if (filter_var($settingVal, FILTER_VALIDATE_INT) === false)
                    {
                        $exceptionMessage = 'Setting ' . $setting . ' must be '
                                          . 'an integer value.';
                    }
                    break;
                case 'num':
                case 'number':
                case 'numeric':
                    if (!is_numeric($settingVal)) {
                        $exceptionMessage = 'Setting ' . $setting . ' must be '
                                          . 'a numeric value.';
                    }
                    break;
                case 'ratio':
                    if (!is_numeric($settingVal) ||
                        $settingVal < 0 || $settingVal > 1)
                    {
                        $exceptionMessage = 'Setting ' . $setting . ' must be '
                                          . 'a ratio.';
                    }
                    break;
                case 'file':
                    if (!is_file($settingVal) && !is_link($settingVal)) {
                        $exceptionMessage = 'Setting ' . $setting . ' must be '
                                          . 'a path to a real file.';
                    }
                    break;
                case 'dir':
                case 'directory':
                    // Don't allow trailing slashes 
                    if (substr($settingVal, -1) == DIRECTORY_SEPARATOR) {
                        $exceptionMessage = 'Setting ' . $setting . ' must '
                                          . 'not contain a trailing slash.';
                    }
                    elseif (!is_dir($settingVal)) {
                        $exceptionMessage = 'Setting ' . $setting . ' must be '
                                          . 'a real directory.';
                    }
                    break;
                case 'writable':
                    if (!strlen($settingVal) ||
                        !self::testWritable($settingVal))
                    {
                        $exceptionMessage = 'Setting ' . $setting . ' must be '
                                          . 'a writable file.';
                    }
                    break;
                case 'email':
                    // Allow comma-delimited instances of multiple addresses
                    $addresses = explode(',', $settingVal);
                    foreach ($addresses as $address) {
                        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                            $exceptionMessage =
                                'Setting ' . $setting . ' must be either a ' .
                                'single email address, or a comma-delimited ' .
                                'list of email addresses.';
                                break;
                        }
                    }
                    break;
                case 'executable':
                    if (!is_executable($settingVal)) {
                        $exceptionMessage = 'Setting ' . $setting . ' must be '
                                          . 'an executable file.';
                    }
                    break;
                case 'url':
                    try {
                        $url = new URL($settingVal);
                        // Require the presence of a scheme
                        if (strpos($settingVal, $url->getScheme() . '://') !== 0)
                        {
                            $exceptionMessage = 'The value of setting '
                                              . $setting . ' is missing a '
                                              . 'scheme.';
                        }
                    } catch (URLException $e) {
                        $exceptionMessage = 'Setting ' . $setting . ' must '
                                          . 'be a valid URL.';
                    }
                    break;
                default:
                    throw new SettingException(
                        'Unrecognized setting test "' . $test . '".'
                    );
            }
            if ($exceptionMessage) {
                throw new SettingException($exceptionMessage);
            }
            self::$_validatedSettings[$setting] = null;
        }
    }
    
    /**
     * Echoes a message to standard output or sends it to an email address,
     * optionally with a date prepended.
     *
     * @deprecated
     * @param string $message,
     * @param boolean $dateStamped
     * @param string $notifyEmail
     * @param string $emailSubject
     * @return string
     */
    public static function notify($message,
                                  $dateStamped = false,
                                  $notifyEmail = null,
                                  $emailSubject = null) {
        if ($dateStamped) {
            $message = strftime('%Y-%m-%d %H:%M:%s') . ': ' . $message;
        }
        if ($notifyEmail && self::hasMail()) {
            if (!$emailSubject) {
                $emailSubject = 'Automatic notifcation from Performics Robot';
            }
            mail($notifyEmail, $emailSubject, $message);
        }
        else {
            echo $message . "\n";
        }
        return $message;
    }
    
    /**
     * Appends a message to a referenced buffer array, optionally with a date
     * prepended.
     *
     * @deprecated
     * @param string $message
     * @param array &$msgBuffer
     * @param boolean $dateStamped
     */
    public static function notifyBuffer($message,
                                        &$msgBuffer,
                                        $dateStamped = false) {
        if ($dateStamped) {
            $message = strftime('%F %T') . ': ' . $message;
        }
        $msgBuffer[] = $message;
        return $message;
    }
    
    /**
     * Prevents PFXUtils::lookupEntityID() from printing a message to standard
     * out when it truncates a string to match a column length.
     *
     * @deprecated
     */
    public static function silenceLookupEntityIDNotifications() {
        self::$_notifyOnEntityLookupTruncate = false;
    }
    
    /**
     * Activates the truncation notifications produced by
     * PFXUtils::lookupEntityID().
     *
     * @deprecated
     */
    public static function activateLookupEntityIDNotifications() {
        self::$_notifyOnEntityLookupTruncate = true;
    }
    
    /**
     * Looks up a text string in a given column in a given table in a given
     * database and returns the contents of the table's ID field. Unless
     * directed otherwise, if the record is not found in the table, this
     * method will insert it and then return the new ID.
     *
     * @param PDO $dbConn
     * @param string $text
     * @param string $columnName
     * @param string $tableName
     * @param string $databaseName = null
     * @param string $idColumnName = null
     * @param boolean $suppressInsert = false
     * @return int
     */
    public static function lookupEntityID(
        PDO $dbConn,
        $text,
        $columnName,
        $tableName,
        $databaseName = null,
        $idColumnName = null,
        $suppressInsert = false
    ) {
        if (!strlen($text)) {
            return;
        }
        if (!isset(self::$_availableFunctions['mbstring'])) {
            self::$_availableFunctions['mbstring'] = extension_loaded(
                'mbstring'
            );
        }
        if ($idColumnName === null) {
            $idColumnName = 'id';
        }
        /* We will need to make sure that the length of the thing we're trying
        to look up is no greater than what the column can hold. Otherwise we
        could get false negatives. */
        if ($databaseName === null) {
            $resultStmt = $dbConn->query('SELECT DATABASE()');
            $row = $resultStmt->fetch(PDO::FETCH_NUM);
            $resultStmt->closeCursor();
            $databaseName = $row[0];
        }
        self::initNestedArrays(
            array($databaseName, $tableName, $columnName), self::$_colData
        );
        if (self::$_colData[$databaseName][$tableName][$columnName] === null) {
            $q = <<<EOF
SELECT CHARACTER_MAXIMUM_LENGTH,
COLLATION_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = ?
AND TABLE_NAME = ?
AND COLUMN_NAME = ?
EOF;
            $stmt = $dbConn->prepare($q);
            $stmt->bindValue(1, $databaseName, PDO::PARAM_STR);
            $stmt->bindValue(2, $tableName, PDO::PARAM_STR);
            $stmt->bindValue(3, $columnName, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            self::$_colData[$databaseName][$tableName][$columnName] = $row;
        }
        $colLength = self::$_colData[$databaseName][$tableName][
            $columnName
        ]['CHARACTER_MAXIMUM_LENGTH'];
        if (strlen($text) > $colLength) {
            if (self::$_notifyOnEntityLookupTruncate) {
                self::notify(
                    'Truncated data to ' . $colLength . ' characters ' .
                    'prior to selection from/insertion into ' .
                    implode('.', array($databaseName, $tableName, $columnName))
                );
            }
            if (self::$_availableFunctions['mbstring']) {
                /* This is very important, because if we use substr(), we might
                cut the string off in the middle of a multi-byte string, which
                will result in a trailing malformed byte. This will probably be
                dropped silently at the database level, which means that the
                next time somebody tries to look up the same string, we'll get
                a false negative, which will either cause the same string to be
                inserted again or trigger a uniqueness constraint violation. */
                $text = mb_strcut($text, 0, $colLength);
            }
            else {
                $text = substr($text, 0, $colLength);
            }
        }
        // Prefer the cached statement if available
        $selStmt = self::_getOrCacheStatement(
            $databaseName, $tableName, $columnName
        );
        if (!$selStmt) {
            /* If the column's collation is binary, we need to use the BINARY
            keyword in the comparison here; it prevents MySQL from using case-
            insensitive comparison and silently discarding trailing spaces. */
            $q = 'SELECT ' . $idColumnName . ' FROM ' . $databaseName . '.'
               . $tableName . ' WHERE ' . $columnName . ' = ';
            if (substr(
                self::$_colData[$databaseName][$tableName][$columnName]['COLLATION_NAME'],
                -4
            ) == '_bin')
            {
                $q .= 'BINARY ';
            }
            $selStmt = $dbConn->prepare($q . '?');
            self::_getOrCacheStatement(
                $databaseName, $tableName, $columnName, true, $selStmt
            );
        }
        $selStmt->bindValue(1, $text, PDO::PARAM_STR);
        $selStmt->execute();
        $resultID = $selStmt->fetchColumn();
        $selStmt->closeCursor();
        if ($resultID) {
            return (int)$resultID;
        }
        elseif (!$suppressInsert) {
            /* If there's already a transaction going, we will not be doing any
            committing in this context. */
            if ($dbConn->inTransaction()) {
                $useTrans = false;
            }
            else {
                $useTrans = true;
            }
            $insStmt = self::_getOrCacheStatement(
                $databaseName, $tableName, $columnName, false
            );
            if (!$insStmt) {
                $q = 'INSERT INTO ' . $databaseName . '.' . $tableName
                   . ' (' . $columnName . ') VALUES (?)';
                $insStmt = $dbConn->prepare($q);
                self::_getOrCacheStatement(
                    $databaseName, $tableName, $columnName, false, $insStmt
                );
            }
            $insStmt->bindValue(1, $text, PDO::PARAM_STR);
            if ($useTrans) {
                $dbConn->beginTransaction();
            }
            $insStmt->execute();
            $resultID = $dbConn->lastInsertId();
            if ($useTrans) {
                $dbConn->commit();
            }
            return (int)$resultID;
        }
    }
    
    /**
     * Takes an array of arrays (representing lines and field data
     * respectively) and converts its values to a string suitable for
     * inserting into a CSV file. Delimiter defaults to a comma, but this may
     * be overridden. Elements that contain the delimiter are wrapped in double
     * quotes. If such an element also contains a double quote, these quotes
     * are themselves doubled.
     *
     * @param array $dataToFormat
     * @param string $delimiter
     * @return string
     */
    public static function arrayToCSV(array $dataToFormat, $delimiter = ',') {
        $returnArray = array();
        foreach ($dataToFormat as $line) {
            if (!is_array($line)) {
                $line = (array)$line;
            }
            foreach ($line as &$element) {
                // Enclose the field if it contains the delimiter or an EOL
                if (strpos($element, $delimiter) !== false ||
                    strpos($element, "\n") !== false ||
                    strpos($element, "\r") !== false)
                {
                    if (strpos($element, '"') !== false) {
                        $element = str_replace('"', '""', $element);
                    }
                    $element = '"' . $element . '"';
                }
            }
            $returnArray[] = implode($delimiter, $line);
        }
        return implode(PHP_EOL, $returnArray);
    }
    
    /**
     * Takes an associative array of keys to values and writes the result as an
     * .ini file to the file whose name is provided. Handles arrays, but they
     * are assumed to be numerically-indexed (i.e. key information is
     * discarded). Throws a RuntimeException if it was not possible to write to
     * the specified file.
     *
     * @param array $settings
     * @param string $fileName
     * @param boolean $append
     */
    public static function writeINIFile(
        array $settings,
        $fileName,
        $append = false
    ) {
        if ($append) {
            $fileHandle = fopen($fileName, 'ab');
        }
        else {
            $fileHandle = fopen($fileName, 'wb');
        }
        if (!$fileHandle) {
            throw new RuntimeException(
                'Unable to open ' . $fileName . ' for writing.'
            );
        }
        foreach ($settings as $key => $val) {
            if (is_array($val)) {
                $key .= '[]';
            }
            else {
                $val = array($val);
            }
            foreach ($val as $element) {
                if (!is_numeric($element)) {
                    $element = '"' . str_replace('"', '\"', $element) . '"';
                }
                fwrite($fileHandle, $key . ' = ' . $element . "\n");
            }
        }
        fclose($fileHandle);
    }
    
    /**
     * Attemps to create a PDO object based on the parameters and configured
     * to throw exceptions on errors.
     *
     * @param string $dsn
     * @param string $user
     * @param string $password
     * @param array $attrs = null
     * @return PDO
     */
    public static function getDBConn(
        $dsn,
        $user,
        $password,
        array $attrs = null
    ) {
        // First check the cache
        $dbKey = md5(implode(
            chr(31), array($dsn, $user, $password, serialize($attrs))
        ));
        if (array_key_exists($dbKey, self::$_dbCache)) {
            return self::$_dbCache[$dbKey]['db'];
        }
        try {
            $dbConn = new PDO($dsn, $user, $password, $attrs);
            $dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Get the time zone offset
            $q = <<<EOF
SELECT TIMESTAMPDIFF(
 SECOND, CONVERT_TZ(NOW(), @@session.time_zone, '+00:00'), NOW()
)
EOF;
            $stmt = $dbConn->query($q);
            $offset = $stmt->fetchColumn();
            /* This will come out as a string, which is necessary because we
            need to be able to distinguish 0 and null. */
            if ($offset === null) {
                // This really shouldn't happen
                throw new UnexpectedValueException(
                    'Got unexpected result when querying database for time ' .
                    'zone offset.'
                );
            }
            // Now that that test is done, we can cast as an int
            $offset = (int)$offset;
            if (self::$_dbTimeZoneOffset === null) {
                /* If this property is null, but we've already cached a
                database connection, it means we've already encountered
                multiple different time zone offsets. */
                if (!self::$_dbCache) {
                    self::$_dbTimeZoneOffset = $offset;
                }
            }
            elseif (self::$_dbTimeZoneOffset !== $offset) {
                self::$_dbTimeZoneOffset = null;
            }
            self::$_dbCache[$dbKey] = array(
                'db' => $dbConn, 'time_zone_offset' => $offset
            );
            return $dbConn;
        } catch (PDOException $e) {
            throw new RuntimeException('Caught PDOException.', null, $e);
        }
    }
    
    /**
     * Clean problematic script elements from an HTML string. PHP's DOMDocument
     * class seems to get confused by <script> elements that contain HTML tags
     * within their CDATA section. Note that this method may remove inline
     * JavaScript that does not contain HTML tags, because it identifies
     * content by looking for the '<' and '>' characters.
     *
     * @param string $html
     * @return string
     */
    public static function emptyBadScriptTags($html) {
        $scriptLocs = array();
        $length = strlen($html);
        $lcHTML = strtolower($html);
        $cleanHTML = '';
        /* Find every instance of the string '<script'. This should cover the
        various possibilities of what the tag may look like. */
        $offset = 0;
        $c = 0;
        while ($offset < $length - 1) {
            $c++;
            $loc = strpos($lcHTML, '<script', $offset);
            if ($loc === false) {
                break;
            }
            $scriptLocs[] = $loc;
            $offset = $loc + 1;
        }
        if (!$scriptLocs) {
            return $html;
        }
        $lastClosed = 0;
        /* Note that with in this loop, we're working with $lcHTML whenever we
        need to match a string; however, for the purposes of building the clean
        HTML string, we're always grabbing pieces of $html. */
        foreach ($scriptLocs as $loc) {
            // Find out where the opening tag ends
            $openTagClosure = strpos($html, '>', $loc) + 1;
            /* Append HTML from last script tag closure up to the point where
            the next opening script tag is closed. */
            $cleanHTML .= substr($html, $lastClosed, $openTagClosure - $lastClosed);
            // Determine where tag is closed
            $tagClose = strpos($lcHTML, '</script>', $loc);
            // Set $lastClosed for next iteration
            $lastClosed = $tagClose + 9;
            // Isolate the string between the script tags
            $scriptStr = substr($html, $openTagClosure, $tagClose - $openTagClosure);
            /* If the script is free of angle brackets, it's safe to add to
            $cleanHTML. Otherwise, simply close the script tag and throw away
            the script contents. We can ignore angle brackets used for the
            purpose of opening and closing CDATA tags, but unfortunately I
            haven't thought of a fast way to distinguish angle brackets used
            in HTML tags from angle brackets used as comparison operators. We
            have to live with some JavaScript being thrown away that would not
            technically pose any problem. */
            $scriptCopy = str_replace('<![cdata[', '', strtolower($scriptStr));
            $scriptCopy = str_replace(']]>', '', $scriptCopy);
            if (strpos($scriptCopy, '<') === false &&
                strpos($scriptCopy, '>') === false)
            {
                $cleanHTML .= $scriptStr . '</script>';
            }
            else {
                $cleanHTML .= '"removed";</script>';
            }
        }
        // Append the final part of the HTML
        $cleanHTML .= substr($html, $lastClosed);
        return $cleanHTML;
    }
    
    /**
     * Takes an array containing expected short arguments and an array
     * containing expected long arguments, calls getopt(), and returns an
     * array associating the verbose argument name with its value.
     * As in getopt(), the equivalent arguments in $shortArgs and $longArgs
     * must be in the same order. This method also recognizes the prefix '!',
     * which may be used to indicate that the argument is mandatory; this will
     * be enforced if the prefix is applied to either or both of the short and
     * long forms. If the third argument is true, the "help" argument was
     * passed, and the PFX_USAGE_MESSAGE constant is defined, this method will
     * automatically call PFXUtils::printUsage() before throwing an exception
     * due to improper arguments passed by the user. To parse short or long
     * options only, pass an empty array as the second or first argument
     * respectively. To support only the short or long form of a particular
     * option, pass an empty string in the corresponding position of the other
     * array.
     *
     * @param array $shortArgs
     * @param array $longArgs = array()
     * @param boolean $detectHelp = true
     * @return array
     */
    public static function collapseArgs(
        array $shortArgs,
        array $longArgs = array(),
        $detectHelp = true
    ) {
        /* Corresponding indexes in $shortArgs and $longArgs will be treated as
        denoting alternate forms of the same argument (provided that both have
        a length), and the argument's value will be placed into the returned
        array under the key corresponding to the long form of the argument.
        Obviously, no such normalization takes place with an argument that only
        has one form. */
        $shortCount = count($shortArgs);
        $longCount = $longArgs ? count($longArgs) : 0;
        $mandatoryArgs = array();
        $passableShortArgs = array();
        $passableLongArgs = array();
        $canonicalArgMap = array();
        for ($i = 0; $i < $longCount; $i++) {
            if (!strlen($longArgs[$i])) {
                continue;
            }
            $mandatory = false;
            if ($longArgs[$i][0] == '!') {
                $longArgs[$i] = substr($longArgs[$i], 1);
                $mandatory = true;
            }
            $passableLongArgs[] = $longArgs[$i];
            $longArgs[$i] = rtrim($longArgs[$i], ':');
            /* No need to put the long forms in the map; we only need to add
            the forms that should map to something different, which will only
            be the short forms. */
            if ($mandatory) {
                $mandatoryArgs[] = $longArgs[$i];
            }
        }
        for ($i = 0; $i < $shortCount; $i++) {
            if (!strlen($shortArgs[$i])) {
                continue;
            }
            $mandatory = false;
            if ($shortArgs[$i][0] == '!') {
                $shortArgs[$i] = substr($shortArgs[$i], 1);
                $mandatory = true;
            }
            $passableShortArgs[] = $shortArgs[$i];
            $cleanArg = rtrim($shortArgs[$i], ':');
            if (isset($longArgs[$i]) && strlen($longArgs[$i])) {
                $canonicalArgMap[$cleanArg] = $longArgs[$i];
            }
            if ($mandatory) {
                $canonicalArg = isset($canonicalArgMap[$cleanArg]) ?
                    $canonicalArgMap[$cleanArg] : $cleanArg;
                if (!in_array($canonicalArg, $mandatoryArgs)) {
                    $mandatoryArgs[] = $canonicalArg;
                }
            }
        }
        $rawArgs = getopt(
            implode('', $passableShortArgs), $passableLongArgs
        );
        if ($rawArgs === false) {
            throw new RuntimeException(
                'Encountered error while parsing command-line arguments.'
            );
        }
        $processedArgs = array();
        foreach ($rawArgs as $argKey => $argVal) {
            $canonicalKey = isset($canonicalArgMap[$argKey]) ?
                $canonicalArgMap[$argKey] : $argKey;
            /* For some reason getopt() uses a boolean false as the option
            value if no argument was passed; to me boolean true makes more
            sense. */
            if ($argVal === false) {
                $argVal = true;
            }
            if (isset($processedArgs[$canonicalKey])) {
                if (!is_array($processedArgs[$canonicalKey])) {
                    $processedArgs[$canonicalKey] = array(
                        $processedArgs[$canonicalKey]
                    );
                }
                $processedArgs[$canonicalKey][] = $argVal;
            }
            else {
                $processedArgs[$canonicalKey] = $argVal;
            }
        }
        if ($detectHelp && isset($processedArgs['help']) &&
            defined('PFX_USAGE_MESSAGE'))
        {
            self::printUsage();
        }
        foreach ($mandatoryArgs as $arg) {
            if (!isset($processedArgs[$arg])) {
                throw new InvalidArgumentException(
                    'The "' . $arg . '" argument is required.'
                );
            }
        }
        return $processedArgs;
    }

    /**
     * This method builds a readable exception trace array from an exception
     * chain.
     *
     * @param Exception $e
     * @return array
     */
    public static function buildExceptionTraceAsArray(Exception $e) {
        $message = array('Exception trace (outermost first:)');
        $testException = $e;
        $i = 0;
        while ($testException) {
            $i++;
            $message[] = '(#' . $i . ') ' . get_class($testException) . ': '
                       . $testException->getMessage() . ' (in '
                       . $testException->getFile() . ', line '
                       . $testException->getLine() . ')';
            $testException = $testException->getPrevious();
        }
        return $message;
    }
    
    /**
     * This method builds a readable exception trace string from an exception
     * chain.
     *
     * @param Exception $e
     * @return string
     */
    public static function buildExceptionTrace(Exception $e) {
        return implode(PHP_EOL, self::buildExceptionTraceAsArray($e));
    }
    
    /**
     * Returns a boolean value indicating whether the exception chain passed in
     * the first argument contains an exception of the type passed in the
     * second argument. The second argument may be an exception instance, a
     * class name expressed as a string, or multiple instances of either of
     * these argument types passed as an array.
     *
     * @param Exception $chain
     * @param string, array, Exception $type
     * @return boolean
     */
    public static function exceptionContains($chain, $type) {
        $e = $chain;
        if (!is_array($type)) {
            $type = array($type);
        }
        while ($e) {
            foreach ($type as $typeOption) {
                if ($e instanceof $typeOption) {
                    return true;
                }
            }
            $e = $e->getPrevious();
        }
        return false;
    }
    
    /**
     * Get current timestamp expressed as milliseconds since January 1, 1970.
     *
     * @return int
     */
    public static function millitime() {
        return round(1000 * microtime(true));
    }

    /**
     * Check the $_FILES array for an uploaded file under the form input name
     * provided in the first argument. If successful, attempt to move it to
     * the directory specified in the second argument and assign it a temporary
     * name, optionally prefixed with the string supplied in the third 
     * argument. Returns the full path to the uploaded file on success.
     *
     * @param string $inputName
     * @param string $destPath
     * @param string $tempNamePrefix
     * @param boolean $preserveExtension
     * @return string
     */
    public static function handleHTTPUpload(
        $inputName,
        $destPath,
        $tempNamePrefix = '',
        $preserveExtension = true
    ) {
        if (!array_key_exists($inputName, $_FILES)) {
            throw new Exception('Bad file field name: ' . $inputName);
        }
        $errCode = $_FILES[$inputName]['error'];
        if ($errCode != 0) {
            throw new Exception(self::$_FILE_UPLOAD_ERRORS[$errCode]);
        }
        // Ensure no trailing slash on $destPath
        if (substr($destPath, -1, 1) == '/') {
            $destPath = rtrim($destPath, '/');
        }
        $fileName = tempnam($destPath, $tempNamePrefix);
        if (!$fileName) {
            throw new Exception(
                'Unable to initialize temporary file name.'
            );
        }
        if ($preserveExtension) {
            $pPos = strrpos($_FILES[$inputName]['name'], '.');
            if ($pPos) {
                $newFileName = $fileName . '.' . substr(
                    $_FILES[$inputName]['name'], $pPos + 1
                );
                if (!rename($fileName, $newFileName)) {
                    throw new Exception(
                        'Unable to initialize temporary file with extension.'
                    );
                }
                $fileName = $newFileName;
            }
        }
        if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $fileName)) {
            throw new Exception(
                'Unable to move uploaded file.'
            );
        }
        return $fileName;
    }
    
    /**
     * Reports on whether this system is properly configured to send email.
     *
     * @return boolean
     */
    public static function hasMail() {
        if (!isset(self::$_availableFunctions['sendmail_path'])) {
            $smPath = ini_get('sendmail_path');
            $pos = strpos($smPath, ' ');
            if ($pos) {
                $smPath = substr($smPath, 0, $pos);
            }
            if (!$smPath || !file_exists($smPath)) {
                self::$_availableFunctions['sendmail_path'] = false;
            }
            else {
                self::$_availableFunctions['sendmail_path'] = true;
            }
        }
        return self::$_availableFunctions['sendmail_path'];
    }
    
    /**
     * XML-to-array method.
     *
     * @param string, SimpleXMLElement $xml
     * @return array, boolean
     */
    public static function xmlToArray($xml) {
        if (is_string($xml)) {
            $xml = new SimpleXMLElement($xml);
        }
        elseif (!is_object($xml) || !($xml instanceof SimpleXMLElement)) {
            throw new InvalidArgumentException(
                'This method expects either a string representation of XML ' .
                'data or a SimpleXMLElement instance.'
            );
        }
        return array($xml->getName() => self::_getXMLNodeValue($xml));
    }
    
    /**
     * Returns the amount of memory available to PHP, minus the amount
     * currently being used, in bytes.
     *
     * @param boolean $realUsage
     * @param boolean $forceLimitCheck
     * @return int
     */
    public static function getAvailableMemory($realUsage = false, 
                                              $forceLimitCheck = false)
    {
        if (!self::$_memLimit || $forceLimitCheck) {
            $iniVal = ini_get('memory_limit');
            if ($iniVal == '-1') {
                /* Default to a gig, though this does mean that this method
                could return a negative number. */
                self::$_memLimit = pow(1024, 3);
            }
            elseif (ctype_digit($iniVal)) {
                self::$_memLimit = (int)$iniVal;
            }
            else {
                $lastChar = strtolower(substr($iniVal, -1));
                $intPortion = (int)substr($iniVal, 0, strlen($iniVal) - 1);
                if ($lastChar == 'k') {
                    $multiplier = 1024;
                }
                elseif ($lastChar == 'm') {
                    $multiplier = pow(1024, 2);
                }
                elseif ($lastChar == 'g') {
                    $multiplier = pow(1024, 3);
                }
                else {
                    // Shouldn't happen
                    return false;
                }
                self::$_memLimit = $intPortion * $multiplier;
            }
        }
        return self::$_memLimit - memory_get_usage($realUsage);
    }
    
    /**
     * Traverses through the array provided in the first argument and ensures
     * that the contents of the array provided in the second argument are
     * existing array keys (in the order that they appear). The value of the
     * third argument to this method is used as the value of the tree's deepest
     * node.
     *
     * @param array $keys
     * @param array &$tree
     * @param mixed $deepest = null
     */
    public static function initNestedArrays(
        array $keys,
        array &$tree,
        $deepest = null
    ) {
        $keyCount = count($keys);
        for ($i = 0; $i < $keyCount; $i++) {
            if (!array_key_exists($keys[$i], $tree)) {
                $tree[$keys[$i]] = $i + 1 == $keyCount ? $deepest : array();
            }
            $tree = &$tree[$keys[$i]];
        }
    }
    
    /**
     * Traverses through the array provided in the first argument looking for
     * keys in the order in which they are provided in the array in the second
     * argument. Returns true if all keys exist, otherwise returns false.
     *
     * @param array $keys
     * @param array $tree
     * @return boolean
     */
    public static function nestedArrayKeyExists(array $keys, array $tree) {
        foreach ($keys as $key) {
            if (!is_array($tree) || !array_key_exists($key, $tree)) {
                return false;
            }
            $tree = $tree[$key];
        }
        return true;
    }
    
    /**
     * Checks a file's extension to make a guess about whether it is
     * compressed. If so, attempts to decompress it. If successful, removes the
     * compressed file and returns the full path to the new uncompressed file.
     *
     * @param string $file
     * @return string
     */
    public static function inflate($file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $dirName = dirname($file);
        if ($ext == 'gz') {
            // Preserve sub-extension, if any
            $subExt = pathinfo(
                pathinfo($file, PATHINFO_FILENAME), PATHINFO_EXTENSION
            );
            $inflated = tempnam($dirName, 'f');
            if (!$inflated) {
                throw new Exception(
                    'Failed to get temporary file name while inflating ' .
                    'gzipped file.'
                );
            }
            if ($subExt) {
                /* This is relying on the possibly brittle assumption that if
                the file returned by the tempnam() call didn't overwrite
                anything in that directory, we also won't be overwriting an
                existing file by adding this extension. */
                $newInflated = $inflated . '.' . $subExt;
                rename($inflated, $newInflated);
                $inflated = $newInflated;
            }
            // Get the uncompressed size
            $zHandle = fopen($file, 'rb');
            if (!$zHandle) {
                throw new Exception(
                    'Error reading gzipped file while attempting to inflate ' .
                    'it.'
                );
            }
            fseek($zHandle, -4, SEEK_END);
            $data = unpack('V', fread($zHandle, 4));
            $size = end($data);
            fclose($zHandle);
            $zHandle = gzopen($file, 'rb');
            $handle = fopen($inflated, 'wb');
            if (!$handle) {
                throw new Exception(
                    'Failed to open temporary file for writing while ' .
                    'inflating gzipped file.'
                );
            }
            while ($size > 0) {
                $chunkSize = min(4096, $size);
                fwrite($handle, gzread($zHandle, $chunkSize));
                $size -= $chunkSize;
            }
            gzclose($zHandle);
            fclose($handle);
            unlink($file);
            $file = $inflated;
        }
        elseif ($ext == 'zip') {
            if (!function_exists('zip_open')) {
                throw new Exception(
                    'Zip extension unavailable in server environment.'
                );
            }
            $zip = zip_open($file);
            if (!is_resource($zip)) {
                throw new Exception(
                    'Error reading zip file while attempting to inflate it.'
                );
            }
            $entryCount = 0;
            $lastEntry = null;
            while ($entry = zip_read($zip)) {
                $lastEntry = $entry;
                $entryCount++;
            }
            if ($entryCount != 1) {
                throw new Exception(
                    'Error inflating zip file: did not find exactly one ' .
                    'entry within the archive.'
                );
            }
            $inflated = tempnam($dirName, 'f');
            if (!$inflated) {
                throw new Exception(
                    'Failed to get temporary file name while inflating ' .
                    'zipped file.'
                );
            }
            // Preserve extension, if any
            $ext = pathinfo(zip_entry_name($lastEntry), PATHINFO_EXTENSION);
            if ($ext) {
                /* See the note in the corresponding section of the gzip
                branch re: the possible brittleness of this assumption. */
                $newInflated = $inflated . '.' . $ext;
                rename($inflated, $newInflated);
                $inflated = $newInflated;
            }
            $handle = fopen($inflated, 'wb');
            if (!$handle) {
                throw new Exception(
                    'Failed to open temporary file for writing while ' .
                    'inflating zipped file.'
                );
            }
            while ($chunk = zip_entry_read($lastEntry)) {
                fwrite($handle, $chunk);
            }
            zip_close($zip);
            fclose($handle);
            unlink($file);
            $file = $inflated;
        }
        return $file;
    }
    
    /**
     * Performs a divide-and-conquer search on the array passed, optionally
     * sliced using the third and fourth arguments, and returns the index
     * containing the matching value. The matching logic depends on the value
     * of the fifth argument:
     * PFXUtils::ARRAY_SEARCH_STRICTMATCH: looks for an exact match against the
     * search argument (taking type into account)
     * PFXUtils::ARRAY_SEARCH_NEXTLOWEST: first tries finding an exact match,
     * and if that is not possible, returns an index such that the value there
     * is lesser than the comparison value, but the next highest index contains
     * a greater value
     * PFXUtils::ARRAY_SEARCH_NEXTHIGHEST: first tries finding an exact match,
     * and if that is not possible, returns an index such that the value there
     * is greater than the comparison value, but the next lowest index contains
     * a lesser value
     * This method assumes that the argument array has already been sorted
     * (ascending). If the sixth argument is provided, it should be a callback
     * that extracts a comparison value from whatever is stored under an array
     * index; otherwise, the literal value at that array index is used in the
     * comparison. Note that this method returns boolean false in a situation
     * where a match is required but not found, so strict checking of the
     * returned value is necessary. Also note that this method requires that
     * the array submitted be indexed sequentially and numerically, but does
     * not test for this other than ensuring that index 0 exists.
     *
     * @param mixed $val
     * @param array $arr
     * @param int $lowerBound = 0
     * @param int $upperBound = null
     * @param int $searchType = self::ARRAY_SEARCH_STRICTMATCH
     * @param callable $compValueGetter = null
     * @return int
     */
    public static function searchArray(
        $val,
        array $arr,
        $lowerBound = null,
        $upperBound = null,
        $searchType = self::ARRAY_SEARCH_STRICTMATCH,
        $compValueGetter = null
    ) {
        if (!is_scalar($val)) {
            throw new InvalidArgumentException(
                'Comparison value must be a scalar.'
            );
        }
        if (!$arr) {
            return false;
        }
        if (!array_key_exists(0, $arr)) {
            throw new InvalidArgumentException(
                'Cannot search an array that is not numerically and ' . 
                'sequentially indexed.'
            );
        }
        if ($lowerBound === null) {
            $lowerBound = 0;
        }
        if ($upperBound === null) {
            $upperBound = count($arr) - 1;
        }
        if ($upperBound == $lowerBound) {
            $mid = $lowerBound;
        }
        else {
            $mid = $lowerBound + (int)ceil(($upperBound - $lowerBound) / 2);
        }
        if ($compValueGetter !== null) {
            if (!is_callable($compValueGetter)) {
                throw new InvalidArgumentException(
                    'A non-callback was passed as an argument in a context ' .
                    'that expects a callback.'
                );
            }
            $valAtIndex = $compValueGetter($arr[$mid]);
        }
        else {
            $valAtIndex = $arr[$mid];
        }
        $nextHighestIndex = null;
        $nextHighestValue = null;
        $nextLowestIndex = null;
        $nextLowestValue = null;
        // This is true no matter what the search type is
        if ($valAtIndex === $val) {
            return $mid;
        }
        /* This block does the double duty of testing whether the midpoint
        index we picked out is the one we want and ensuring that the search
        type is valid. */
        if ($searchType == self::ARRAY_SEARCH_NEXTLOWEST) {
            $next = $mid + 1;
            if (array_key_exists($next, $arr)) {
                $nextHighestIndex = $next;
                $nextHighestValue = self::_getValueAtArrayIndex(
                    $arr, $next, $compValueGetter
                );
            }
            if ($nextHighestIndex !== null && $valAtIndex < $val &&
                $nextHighestValue > $val)
            {
                return $mid;
            }
        }
        elseif ($searchType == self::ARRAY_SEARCH_NEXTHIGHEST) {
            $next = $mid - 1;
            if (array_key_exists($next, $arr)) {
                $nextLowestIndex = $next;
                $nextLowestValue = self::_getValueAtArrayIndex(
                    $arr, $next, $compValueGetter
                );
            }
            if ($nextLowestIndex !== null && $valAtIndex > $val &&
                $nextLowestValue < $val)
            {
                return $mid;
            }
        }
        elseif ($searchType != self::ARRAY_SEARCH_STRICTMATCH) {
            throw new InvalidArgumentException('Invalid array search type.');
        }
        /* OK, so we've determined the midpoint we picked out isn't the index
        we want. If the value we're looking for is less than the value at the
        midpoint, we need to search between the lower bound and the next lowest
        index below the midpoint, unless the midpoint and the lower bound are
        the same, in which case we can abandon the search. */
        if ($val < $valAtIndex) {
            if ($mid == $lowerBound) {
                return $searchType == self::ARRAY_SEARCH_NEXTHIGHEST ? $mid : false;
            }
            return self::searchArray(
                $val,
                $arr,
                $lowerBound,
                $mid - 1,
                $searchType,
                $compValueGetter
            );
        }
        /* Otherwise, search the other slice, unless the midpoint equals the
        upper bound. */
        if ($mid == $upperBound) {
            return $searchType == self::ARRAY_SEARCH_NEXTLOWEST ? $mid : false;
        }
        return self::searchArray(
            $val,
            $arr,
            $mid + 1,
            $upperBound,
            $searchType,
            $compValueGetter
        );
    }
    
    /**
     * Calls htmlspecialchars() on each value in the array submitted (or $_GET
     * if none is specified). This is deprecated but I can't remove it until I
     * rewrite the credential storage application to escape on output instead
     * of input.
     *
     * @deprecated
     * @param array &$input = null
     * @param boolean $trim = true
     */
    public static function sanitizeWebInput(
        array &$input = null,
        $trim = true
    ) {
        if (!$input) {
            $input = &$_GET;
        }
        foreach ($input as &$data) {
            $data = htmlspecialchars($data);
            if ($trim) {
                $data = trim($data);
            }
        }
    }
    
    /**
     * Begins a transaction if and only if the database connection passed is
     * not already in a transaction. Returns true or false depending on whether
     * the transaction was begun.
     *
     * @param PDO $dbConn
     * @return boolean
     */
    public static function beginTransactionSafe(PDO $dbConn) {
        if (!$dbConn->inTransaction()) {
            $dbConn->beginTransaction();
            return true;
        }
        return false;
    }
    
    /**
     * Joins the elements provided into a relative path using the value of the
     * DIRECTORY_SEPARATOR constant. It will not contain a trailing slash. This
     * method (naively) assumes that the contents of the array are all scalars,
     * so woe be you if this isn't the case.
     *
     * @param array $pathElements
     * @return string
     */
    public static function createRelativePath(array $pathElements) {
        return implode(DIRECTORY_SEPARATOR, $pathElements);
    }
    
    /**
     * Joins the elements provided into an absolute path using the value of the
     * DIRECTORY_SEPARATOR constant. This method is simply a shorthand for
     * calling PFXUtils::createRelativePath() and prepending a directory
     * separator, unless the second argument is true, in which case the output
     * will be sent through realpath() to resolve any aliases or relativity.
     *
     * @param array $pathElements
     * @param boolean $realPath = false
     * @return string
     */
    public static function createAbsolutePath(
        array $pathElements,
        $realPath = false
    ) {
        $path = self::createRelativePath($pathElements);
        return $realPath ? realpath($path) : DIRECTORY_SEPARATOR . $path;
    }
    
    /**
     * Attempts to guess the character encoding of a file stream based on its
     * BOM, if it has one. Note that this method will move the file handle's
     * position back to the beginning if it is not already there, and it will
     * move the position to the next byte beyond the BOM.
     *
     * @param resource $fh
     * @return string
     */
    public static function guessEncoding($fh) {
        if (ftell($fh) !== 0) {
            rewind($fh);
        }
        $chunk = fread($fh, 4);
        $encoding = null;
        $seekPos = 0;
        if (strncmp($chunk, "\xef\xbb\xbf", 3) == 0) {
            $encoding = 'UTF-8';
            $seekPos = 3;
        }
        elseif (strcmp($chunk, "\x00\x00\xfe\xff") == 0) {
            $encoding = 'UTF-32BE';
            $seekPos = 4;
        }
        elseif (strcmp($chunk, "\xfe\xff\x00\x00") == 0) {
            $encoding = 'UTF-32LE';
            $seekPos = 4;
        }
        elseif (strncmp($chunk, "\xfe\xff", 2) == 0) {
            $encoding = 'UTF-16BE';
            $seekPos = 2;
        }
        elseif (strncmp($chunk, "\xff\xfe", 2) == 0) {
            $encoding = 'UTF-16LE';
            $seekPos = 2;
        }
        else {
            /* Otherwise assume that the BOM was removed transparently and this
            is UTF-8. */
            $encoding = 'UTF-8';
        }
        fseek($fh, $seekPos, SEEK_SET);
        return $encoding;
    }
    
    /**
     * Attempts to determine the EOL character used within a file by reading
     * characters until it encounters a newline, then looking to see whether
     * the previous character is a carriage return.
     *
     * @param resource $fh
     * @return string
     */
    public static function guessEOL($fh) {
        $returnPos = ftell($fh);
        rewind($fh);
        $lastChar = null;
        while (!feof($fh)) {
            $thisChar = fgetc($fh);
            if ($thisChar == "\n") {
                fseek($fh, $returnPos, SEEK_SET);
                if ($lastChar == "\r") {
                    return "\r\n";
                }
                return "\n";
            }
            elseif ($lastChar == "\r") {
                fseek($fh, $returnPos, SEEK_SET);
                return "\r";
            }
            // Skip null bytes when setting last character
            if ($thisChar !== "\000") {
                $lastChar = $thisChar;
            }
        }
        // There are no newlines in the file, so we're just guessing
        fseek($fh, $returnPos, SEEK_SET);
        return PHP_EOL;
    }
    
    /**
     * Like PFXUtils::guessEOL(), but operates on a string instead of a file
     * handle.
     *
     * @param string $input
     * @return string
     */
    public static function guessEOLInString($input) {
        $fh = fopen('php://memory', 'wb');
        fwrite($fh, $input);
        $eol = self::guessEOL($fh);
        fclose($fh);
        return $eol;
    }
    
    /**
     * This method provides a multibyte-safe fgets() equivalent with
     * transparent character set conversion.
     *
     * @param resource $fh
     * @param string &$buffer
     * @param string $sourceEncoding = null
     * @param string $destEncoding = 'UTF-8'
     * @param string $eol = PHP_EOL
     * @return string, boolean
     */
    public static function fgetsMB(
        $fh,
        &$buffer,
        $sourceEncoding = null,
        $destEncoding = 'UTF-8',
        $eol = PHP_EOL
    ) {
        /* If character set conversion isn't required and the EOL character
        ends with "\n", it's (at least in principle) more efficient to fall
        back to the native fgets(). */
        if ($sourceEncoding !== null && $sourceEncoding == $destEncoding &&
            substr($eol, -1) == "\n")
        {
            return fgets($fh);
        }
        /* When we look for EOL characters in the data, we need to look for
        their encoded representations; this is a caching mechanism that
        prevents us from having to do the conversion on every call. */
        if ($sourceEncoding !== self::$_lastUsedEncoding) {
            self::$_lastUsedEncoding = $sourceEncoding;
            /* This is an array because there could be multiple possible EOL
            sequences we try to look for on the same encoding. */
            self::$_lastUsedEncodingEOL = array();
        }
        if (!isset(self::$_lastUsedEncodingEOL[$eol])) {
            /* This assumes that the EOL is being passed in the same encoding
            to which we are being asked to convert. */
            self::$_lastUsedEncodingEOL[$eol] = $sourceEncoding === null ?
                $eol : mb_convert_encoding($eol, $sourceEncoding, $destEncoding);
        }
        $eolLen = strlen(self::$_lastUsedEncodingEOL[$eol]);
        $buffer = (string)$buffer;
        $bufSize = strlen($buffer);
        $line = '';
        $eofReached = feof($fh);
        // First deal with the contents of the buffer, if any
        if ($bufSize) {
            $eolPos = strpos($buffer, self::$_lastUsedEncodingEOL[$eol]);
            if ($eolPos !== false) {
                $eolPos += $eolLen;
                $line .= substr($buffer, 0, $eolPos);
                $buffer = substr($buffer, $eolPos);
                if ($sourceEncoding !== null &&
                    $sourceEncoding != $destEncoding)
                {
                    $line = mb_convert_encoding(
                        $line, $destEncoding, $sourceEncoding
                    );
                }
                return $line;
            }
            elseif ($eofReached) {
                /* We only want to return the remainder of the buffer if we've
                reached EOF. Otherwise we always want to append the next chunk
                that we read to the remainder of the buffer, because that's the
                only way we will be able to detect a line ending in a case
                where EOL is represented by a sequence of more than one
                character and the last line read split the EOL character. */
                $line = $buffer;
                $buffer = '';
            }
        }
        elseif ($eofReached) {
            return false;
        }
        $chunk = $buffer;
        $buffer = '';
        $eolPos = false;
        while ($eolPos === false && !feof($fh)) {
            $chunklet = fread($fh, 4096);
            $chunk .= $chunklet;
            $eolPos = strpos($chunk, self::$_lastUsedEncodingEOL[$eol]);
        }
        if ($eolPos === false) {
            $line .= $chunk;
        }
        else {
            $eolPos += $eolLen;
            $line .= substr($chunk, 0, $eolPos);
            $buffer = substr($chunk, $eolPos);
        }
        if ($sourceEncoding !== null &&
            $sourceEncoding != $destEncoding)
        {
            /* We are allowing this to fail if mbstring isn't available,
            because code that requires this method should fail in such an
            environment. */
            $line = mb_convert_encoding(
                $line, $destEncoding, $sourceEncoding
            );
        }
        return $line;
    }
    
    /**
     * Calls get_included_files(), looks for a script named 'bootstrap.php' in
     * the result, and returns the full path to the file if found. This method
     * only returns a value if there is one and only one such included file;
     * otherwise it returns false.
     *
     * @return string
     */
    public static function getBootstrapFile() {
        $res = array_filter(get_included_files(), function($file) {
            $pos = strrpos($file, DIRECTORY_SEPARATOR);
            if ($pos === false) {
                $pos = 0;
            }
            else {
                $pos++;
            }
            if (substr($file, $pos) == 'bootstrap.php') {
                return true;
            }
            return false;
        });
        if (count($res) == 1) {
            return array_shift($res);
        }
        return false;
    }
    
    /**
     * Helper method to get a Twig loader. By default the loader returned is of
     * the type Twig_Loader_Filesystem; another type may be specified by
     * passing the name of a class that exists and implements
     * Twig_LoaderInterface as the first argument to this method. Any remaining
     * arguments to this method are passed to the loader's constructor. If the
     * loader being instantiated is to be of the Twig_Loader_Filesystem type,
     * and no constructor argument is provided, this method will assume that it
     * should use a subdirectory called 'templates' inside the same directory
     * as the effective bootstrap script, if any. If no such script can be
     * found, or there is no additional argument passed and a type other
     * than Twig_Loader_String was requested, an InvalidArgumentException is
     * thrown. Note that this method does not test that the directory being
     * passed to the Twig_Loader_Filesystem constructor is a valid one;
     * instead, it allows invalid directories so that the expected
     * Twig_Error_Loader exception will be thrown upon instantiation. It is
     * also possible to get a Twig_Loader_Filesystem object that does not have
     * a registered path by explicitly passing null as its constructor
     * argument.
     *
     * [@param string $loaderType]
     * [@param mixed $loaderArg...]
     * @return Twig_LoaderInterface
     */
    public static function getTwigLoader() {
        self::_registerTwigAutoloader();
        $args = func_get_args();
        $argCount = count($args);
        $loaderType = null;
        $constructorArgs = array();
        for ($i = 0; $i < $argCount; $i++) {
            /* For the first argument only, we will treat it as the name of
            the class to instantiate instead of a constructor argument if it is
            a valid class that implements Twig_LoaderInterface. */
            if ($i == 0 && class_exists($args[$i]) &&
                is_subclass_of($args[$i], 'Twig_LoaderInterface'))
            {
                $loaderType = $args[$i];
            }
            else {
                $constructorArgs[] = $args[$i];
            }
        }
        if ($loaderType === null) {
            $loaderType = 'Twig_Loader_Filesystem';
        }
        if (!class_exists($loaderType)) {
            throw new InvalidArgumentException(
                'The class "' . $loaderType . '" does not exist.'
            );
        }
        /* Only attempt to autodetermine the template path if it wasn't
        passed. */
        if ($loaderType == 'Twig_Loader_Filesystem' && (
            !$constructorArgs || $constructorArgs[0] === null
        )) {
            $bootstrapFile = self::getBootstrapFile();
            if ($bootstrapFile === false ) {
                throw new InvalidArgumentException(
                    'Could not find included bootstrap file in order to ' .
                    'autogenerate a template path.'
                );
            }
            $constructorArgs[0] = dirname($bootstrapFile)
                                . DIRECTORY_SEPARATOR . 'templates';
        }
        elseif ($loaderType != 'Twig_Loader_String' && !$constructorArgs) {
            throw new InvalidArgumentException(
                'This type of Twig loader requires at least one argument.'
            );
        }
        $r = new ReflectionClass($loaderType);
        return $r->newInstanceArgs($constructorArgs);
    }
    
    /**
     * Convenience method to ensure that the PHPExcel autoloader is
     * initialized.
     */
    public static function initializePHPExcel() {
        require_once(
            dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' .
            DIRECTORY_SEPARATOR . 'PHPExcel' . DIRECTORY_SEPARATOR .
            'PHPExcel.php'
        );
    }
    
    /**
     * Tests whether it is possible for the current user to write to the
     * specified path.
     *
     * @param string $path
     * @return boolean
     */
    public static function testWritable($path) {
        if (!isset(self::$_availableFunctions['posix_access'])) {
            self::$_availableFunctions['posix_access'] = function_exists(
                'posix_access'
            );
        }
        /* I'll work out a non-POSIX implementation of this when/if it becomes
        necessary. */
        if (self::$_availableFunctions['posix_access']) {
            // If file doesn't exist yet, test parent directory
            if (!file_exists($path)) {
                $path = dirname($path);
            }
            return posix_access($path, POSIX_W_OK);
        }
        return true;
    }
    
    /**
     * Translates a boolean value into either 'Yes' or 'No'.
     *
     * @param string $bool
     * @return string
     */
    public static function booleanToEnglish($bool) {
        return $bool ? 'Yes' : 'No';
    }
    
    /**
     * Convenience method for printing a usage message in CLI scripts. A custom
     * message and an exit code may both optionally be passed; if no exit code
     * is specified, the exit code emitted will be 0 by default, unless a
     * message was passed, in which case it will be 1. This method will look
     * for a generic usage message in the PFX_SHORT_USAGE_MESSAGE constant if
     * it is defined and if the third argument is true, otherwise it will look
     * for this message in the PFX_USAGE_MESSAGE constant. If defined, its
     * value will be printed to standard output after the custom message.
     * Because a common use case is to print a nicely-formatted list of
     * possible command-line arguments, there are two possible placeholder
     * values that may be included inside the constant's value: {FILE}, which
     * will be substituted with the base name of the executing script; and
     * {PAD}, which will be dynamically replaced with a number of spaces equal
     * to the length of the beginning of the line on which the placeholder
     * appears through the end of the executing script's name after it has been
     * inserted into the line.
     *
     * @param string $message = ''
     * @param int $exitCode = null
     * @param boolean $preferShort = false
     */
    public static function printUsage(
        $message = '',
        $exitCode = null,
        $preferShort = false
    ) {
        $hasCustomMessage = strlen($message) > 0;
        if ($exitCode === null) {
            $exitCode = $hasCustomMessage ? 1 : 0;
        }
        $constMessage = null;
        if ($preferShort && defined('PFX_SHORT_USAGE_MESSAGE')) {
            $constMessage = PFX_SHORT_USAGE_MESSAGE;
        }
        elseif (defined('PFX_USAGE_MESSAGE')) {
            $constMessage = PFX_USAGE_MESSAGE;
        }
        if ($constMessage) {
            $fileName = basename($_SERVER['SCRIPT_FILENAME']);
            $fileNameLength = strlen($fileName);
            $genericMessage = '';
            $offset = 0;
            $eol = self::guessEOLInString($constMessage);
            $eolLength = strlen($eol);
            $padLength = null;
            $lastPadLength = null;
            do {
                $pos = strpos(
                    $constMessage,
                    '{FILE}',
                    $offset
                );
                if ($pos === false) {
                    $genericMessage .= str_replace(
                        '{PAD}',
                        str_repeat(' ', $padLength),
                        substr($constMessage, $offset)
                    );
                    break;
                }
                /* This takes into account the possibility that we could have
                gotten to the second line or farther before finding an instance
                of {FILE}. We want the pad to reflect the start of the line,
                not the start of the entire string. */
                $lastEOLPos = strrpos(substr($constMessage, 0, $pos), $eol);
                if ($lastEOLPos === false) {
                    /* Set the position to an integer that can be added to the
                    EOL character length to get 0. */
                    $lastEOLPos = $eolLength * -1;
                }
                $lastPadLength = $padLength;
                $padLength = $pos + $fileNameLength - (
                    $lastEOLPos + $eolLength
                );
                /* The pad length that we just computed will be relevant for
                the pieces of the string we extract in subsequent loops. For
                the piece of the string we're about to add, we want whatever
                was the previous pad length we found. If this is the first pad
                length we found, this substring shouldn't contain any {PAD}
                placeholders anyway. */
                $chunk = substr($constMessage, $offset, $pos - $offset);
                if ($lastPadLength !== null) {
                    $chunk = str_replace(
                        '{PAD}', str_repeat(' ', $lastPadLength), $chunk
                    );
                }
                $genericMessage .= $chunk . $fileName;
                $offset = $pos + 6;
            } while ($pos !== false);
            if ($hasCustomMessage) {
                $message = $message . str_repeat(PHP_EOL, 2) . $genericMessage;
            }
            else {
                $message = $genericMessage;
            }
        }
        echo $message . PHP_EOL;
        exit($exitCode);
    }
    
    /**
     * Converts a database-style name (i.e. one in lower case with words
     * separated by underscores) to one in camel case, with the specified
     * prefix (which is an underscore by default), after first verifying via
     * the ReflectionClass object passed that it is a valid property name.
     * 
     * @return string
     */
    public static function getPropertyNameFromDatabaseName(
        $dbName,
        ReflectionClass $reflector,
        $prefix = '_'
    ) {
        $words = explode('_', $dbName);
        $propertyName = $prefix . array_shift($words);
        foreach ($words as $word) {
            if (!strlen($word)) {
                continue;
            }
            $propertyName .= strtoupper($word[0]) . substr($word, 1);
        }
        if (!$reflector->hasProperty($propertyName)) {
            throw new InvalidArgumentException(
                'Database name "' . $dbName . '" does not map to a valid ' .
                'property name.'
            );
        }
        return $propertyName;
    }
    
    /**
     * Escapes characters in the string passed in the first argument and
     * returns the escaped string. Any character in the string of characters
     * passed as the second argument will be escaped; note that the escape
     * character itself is implicitly added to the list of characters to be
     * escaped.
     *
     * @param string $str
     * @param string $escapedChars
     * @param string $escapeChar = '\'
     * @return string
     */
    public static function escape($str, $escapedChars, $escapeChar = '\\') {
        $escapedChars = str_split($escapedChars);
        // This logic assumes a single-character escape sequence
        if (strlen($escapeChar) != 1) {
            throw new InvalidArgumentException(
                'The escape sequence must have a length of 1.'
            );
        }
        if (!in_array($escapeChar, $escapedChars)) {
            $escapedChars[] = $escapeChar;
        }
        foreach ($escapedChars as $char) {
            $escaped = '';
            $offset = 0;
            while (true) {
                $pos = strpos($str, $char, $offset);
                if ($pos === false) {
                    $escaped .= substr($str, $offset);
                    break;
                }
                /* If the character we're looking at is a backslash, make sure
                that it isn't escaping something else. Whatever this character
                is, ensure that it is itself escaped. */
                if ($char == $escapeChar && in_array(
                    $str[$pos + 1], $escapedChars
                )) {
                    $escaped .= substr(
                        $str, $offset, $pos + 2 - $offset
                    );
                    $offset = $pos + 2;
                }
                elseif (self::_isEscaped($str, $pos, $escapeChar)) {
                    $escaped .= substr($str, $offset, $pos + 1 - $offset);
                    $offset = $pos + 1;
                }
                else {
                    $escaped .= substr(
                        $str, $offset, $pos - $offset
                    ) . $escapeChar . $str[$pos];
                    $offset = $pos + 1;
                }
            }
            $str = $escaped;
        }
        return $str;
    }
    
    /**
     * Analogous to PHP's explode(), but only permits a single character as the
     * delimiter and only breaks the string on unescaped instances of the
     * delimiter. 
     *
     * @param string $separator
     * @param string $str
     * @param string $escapeChar = '\'
     * @return array
     */
    public static function explodeUnescaped(
        $separator,
        $str,
        $escapeChar = '\\'
    ) {
        if (strlen($separator) != 1) {
            throw new InvalidArgumentException(
                'The separator must have a length of 1.'
            );
        }
        if (strlen($escapeChar) != 1) {
            throw new InvalidArgumentException(
                'The escape sequence must have a length of 1.'
            );
        }
        $pieces = array();
        $offset = 0;
        $last = -1;
        while (true) {
            $pos = strpos($str, $separator, $offset);
            if ($pos === false) {
                $pieces[] = substr($str, $last + 1);
                break;
            }
            if (!self::_isEscaped($str, $pos, $escapeChar)) {
                $pieces[] = substr($str, $last + 1, $pos - $last - 1);
                $last = $pos;
            }
            $offset = $pos + 1;
        }
        return $pieces;
    }
    
    /**
     * Helper method for building strings like "1 cat", "2 dogs", and so on,
     * without needing to resort to clumsy constructions like "1 cat(s)" simply
     * because it's easier. A string describing the thing that is being
     * quantified should be passed as the second argument; if its plural form
     * is something other than the singular form with an "s" on the end, it
     * should be passed as the third argument.
     *
     * @param int $count
     * @param string $noun
     * @param string $pluralNoun = null
     * @return string
     */
    public static function quantify($count, $noun, $pluralNoun = null) {
        if ($count != 1) {
            if ($pluralNoun === null) {
                $noun .= 's';
            }
            else {
                $noun = $pluralNoun;
            }
        }
        return $count . ' ' . $noun;
    }
    
    /**
     * Joins a list in the same manner as php's implode(), but if the list
     * contains more than one element, it will prefix the last item in the list
     * with the third argument, so you end up with e.g. "foo and bar" or "foo,
     * bar, and baz".
     *
     * @param string $glue
     * @param array $pieces
     * @param string $lastItemPrefix = 'and'
     */
    public static function implodeSemantically(
        $glue,
        array $pieces,
        $lastItemPrefix = 'and'
    ) {
        $len = count($pieces);
        if ($len > 1) {
            $pieces[$len - 1] = $lastItemPrefix . ' ' . $pieces[$len - 1];
            if ($len == 2) {
                return implode(' ', $pieces);
            }
        }
        return implode($glue, $pieces);
    }
}
?>
