<?php
namespace Google\MyBusiness;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class LocationFilter {
    const OP_EQ = '=';
    const OP_HAS = ':';
    private static $_VALID_FIELDS = array(
        'categories',
        'labels',
        'locationName'
    );
    private static $_validator;
    private $_field;
    private $_value;
    
    /**
     * Constructs a new instance. The first argument should be the name of the
     * field to search on, and it may be passed with or without the "location."
     * prefix that Google's API requires. The second argument is the value to
     * be used in the comparison.
     *
     * @param string $field
     * @param string $value
     */
    public function __construct($field, $value) {
        if (!self::$_validator) {
            self::_initStaticProperties();
        }
        $this->_setField($field);
        $this->_setValue($value);
    }
    
    public function __toString() {
        return sprintf(
            'location.%s%s"%s"',
            $this->_field,
            /* The only filterable field that couldn't have multiple values is
            the location name, so we'll use the "has" operator for anything
            else. */
            $this->_field == 'locationName' ? self::OP_EQ : self::OP_HAS,
            $this->_value
        );
    }
    
    private static function _initStaticProperties() {
        self::$_validator = new \Validator(__NAMESPACE__);
        self::$_validator->setEnumValues(self::$_VALID_FIELDS);
    }
    
    /**
     * @param string $field
     */
    private function _setField($field) {
        if (substr($field, 0, 9) == 'location.') {
            $field = substr($field, 9);
        }
        $this->_field = self::$_validator->enum(
            $field, 'The field "' . $field . '" is not valid for filtering.'
        );
    }
    
    /**
     * @param string $value
     */
    private function _setValue($value) {
        if ($this->_field == 'categories') {
            if (!is_object($value)) {
                $catID = $value;
                $value = new Category();
                $value->setID($catID);
            }
            elseif (!($value instanceof Category)) {
                throw new InvalidArgumentException(
                    'Category values must be passed as string IDs or ' .
                    __NAMESPACE__ . '\Category instances.'
                );
            }
            API::validateCategory($value);
            $this->_value = $value;
        }
        else {
            $this->_value = self::$_validator->string(
                $value, 'Values used in filters must be passed as strings.'
            );
        }
    }
}
?>