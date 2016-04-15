<?php
namespace Google\MyBusiness;

class Account extends AbstractAPIResponseObject {
    const ACCOUNT_TYPE_UNSPECIFIED = 1;
    const ACCOUNT_TYPE_PERSONAL = 2;
    const ACCOUNT_TYPE_BUSINESS = 3;
    const ACCOUNT_ROLE_UNSPECIFIED = 10;
    const ACCOUNT_ROLE_OWNER = 11;
    const ACCOUNT_ROLE_MANAGER = 12;
    const ACCOUNT_ROLE_COMMUNITY_MANAGER = 13;
    const ACCOUNT_STATUS_UNSPECIFIED = 20;
    const ACCOUNT_STATUS_VERIFIED = 21;
    const ACCOUNT_STATUS_UNVERIFIED = 22;
    const ACCOUNT_STATUS_VERIFICATION_REQUESTED = 23;
    protected static $_SETTER_DISPATCH_MODEL = array(
        'name' => 'setName',
        'accountName' => 'setAccountName',
        'type' => 'setAccountType',
        'role' => 'setAccountRole',
        'state' => array(
            'status' => 'setAccountStatus'
        )
    );
    protected static $_GETTER_DISPATCH_MODEL = array();
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
    private static $_constantsByName;
    private static $_constantsByVal;
    protected $_accountName;
    protected $_type;
    protected $_role;
    protected $_status;
    
    /**
     * @param array $apiData = null
     */
    public function __construct(array $apiData = null) {
        if (!self::$_constantsByName) {
            self::_initStaticProperties();
        }
        parent::__construct($apiData);
    }
    
    private static function _initStaticProperties() {
        $r = new \ReflectionClass(__CLASS__);
        self::$_constantsByName = $r->getConstants();
        self::$_constantsByVal = array_flip(self::$_constantsByName);
    }
    
    /**
     * Sets one of the properties that accepts an enumerable value
     * ($this->_type, $this->_role, or $this->_status) after verifying that the
     * value is appropriate for the given property.
     *
     * @param string $propName
     * @param string, int $val
     */
    private function _setEnumerableProperty($propName, $val) {
        if (is_int($val)) {
            if (!isset(self::$_constantsByVal[$val])) {
                throw new InvalidArgumentException(
                    'Unrecognized enumerable constant value.'
                );
            }
            $constName = self::$_constantsByVal[$val];
        }
        else {
            /* The enumerated values that Google passes don't necessary contain
            a namespacing prefix. Figure out the appropriate one by
            manipulating the property name. */
            $prefix = 'ACCOUNT' . strtoupper($propName) . '_';
            if (strpos($val, $prefix) === 0) {
                $constName = $val;
            }
            else {
                $constName = $prefix . $val;
            }
            if (!isset(self::$_constantsByName[$constName])) {
                throw new InvalidArgumentException(
                    'Unrecognized enumerable value "' . $val . '".'
                );
            }
        }
        if (strpos($constName, strtoupper($propName)) === false) {
            throw new InvalidArgumentException(
                'The specified enumerable value is not valid for this property.'
            );
        }
        $this->$propName = self::$_constantsByName[$constName];
    }
    
    /**
     * Returns an enumerable property value, either in its raw integer form, or
     * resolved to a string using the same logic that Google uses.
     *
     * @param string $propName
     * @param boolean $asConst
     * @return int, string
     */
    private function _getEnumerableProperty($propName, $asConst) {
        if ($asConst) {
            return $this->$propName;
        }
        elseif ($this->$propName !== null) {
            $constName = self::$_constantsByVal[$this->$propName];
            if (substr($constName, -12) == '_UNSPECIFIED') {
                // These are the only ones where Google uses the prefix
                return $constName;
            }
            /* Otherwise we chop off the word 'ACCOUNT' followed by the
            property name plus one underscore. */
            return substr($constName, 8 + strlen($propName));
        }
    }
    
    /**
     * @param string $name
     */
    public function setName($name) {
        if (substr($name, 0, 9) != 'accounts/') {
            throw new InvalidArgumentException(
                'Google My Business account names must always start with ' .
                '"accounts/" and be followed by the account ID.'
            );
        }
        $this->setID(substr($name, 9));
    }
    
    /**
     * @param string $accountName
     */
    public function setAccountName($accountName) {
        $this->_accountName = $accountName;
    }
    
    /**
     * @param string, int $accountType
     */
    public function setAccountType($accountType) {
        $this->_setEnumerableProperty('_type', $accountType);
    }
    
    /**
     * @param string, int $accountRole
     */
    public function setAccountRole($accountRole) {
        $this->_setEnumerableProperty('_role', $accountRole);
    }
    
    /**
     * @param string, int $accountStatus
     */
    public function setAccountStatus($accountStatus) {
        $this->_setEnumerableProperty('_status', $accountStatus);
    }
    
    /**
     * @return string
     */
    public function getID() {
        return $this->_id;
    }
    
    /**
     * @return string
     */
    public function getName() {
        return 'accounts/' . $this->getID();
    }
    
    /**
     * @return string
     */
    public function getAccountName() {
        return $this->_accountName;
    }
    
    /**
     * @param boolean $asConst = false
     * @return int, string
     */
    public function getAccountType($asConst = false) {
        return $this->_getEnumerableProperty('_type', $asConst);
    }
    
    /**
     * @param boolean $asConst = false
     * @return int, string
     */
    public function getAccountRole($asConst = false) {
        return $this->_getEnumerableProperty('_role', $asConst);
    }
    
    /**
     * @param boolean $asConst = false
     * @return int, string
     */
    public function getAccountStatus($asConst = false) {
        return $this->_getEnumerableProperty('_status', $asConst);
    }
}
?>