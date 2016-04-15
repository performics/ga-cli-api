<?php
namespace Google\MyBusiness;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class LocationFilterCollection {
    const OP_AND = 'AND';
    const OP_OR = 'OR';
    private static $_validator;
    private $_members = array();
    private $_operator;
    private $_limit;
    private $_offset;
    
    /**
     * Constructs a new filter collection. This type may be used to pass filter
     * parameters to the Google My Business API's location list endpoint, as
     * well as to instruct the API instance to return only a particular slice
     * of locations via the limit and offset parameters. The constructor
     * accepts zero or more arguments, the first of which must be one of this
     * class' OP_* constants, and the remainder of which must be instances of
     * either Google\MyBusiness\LocationFilter or
     * Google\MyBusiness\LocationFilterCollection. Instances of the latter will
     * be enclosed in parentheses in the string representation.
     *
     * [@param string $logicalOperator]
     * [@param Google\MyBusiness\LocationFilter, Google\MyBusiness\LocationFilterCollection $filter...]
     */
    public function __construct() {
        if (!self::$_validator) {
            self::$_validator = new \Validator(__NAMESPACE__);
        }
        $args = func_get_args();
        if ($args) {
            $argCount = count($args);
            if ($args[0] != self::OP_AND && $args[0] != self::OP_OR) {
                throw new InvalidArgumentException(
                    'Invalid operator "' . $args[0] . '".'
                );
            }
            $this->_operator = $args[0];
            for ($i = 1; $i < $argCount; $i++) {
                if (!($args[$i] instanceof self || $args[$i] instanceof LocationFilter))
                {
                    throw new InvalidArgumentException(
                        'Filters must be passed as ' . __NAMESPACE__ .
                        '\LocationFilter or ' . __CLASS__ . ' instances.'
                    );
                }
                $this->_members[] = $args[$i];
            }
        }
    }
    
    /**
     * @return string
     */
    public function __toString() {
        return implode(' ' . $this->_operator . ' ', array_map(
            function($member) {
                return $member instanceof LocationFilter ? (string)$member : '(' . $member . ')';
            },
            $this->_members
        ));
    }
    
    /**
     * @param int $limit
     */
    public function setLimit($limit) {
        $this->_limit = self::$_validator->number(
            $limit,
            'Limits must be positive integers.',
            \Validator::ASSERT_INT_DEFAULT
        );
    }
    
    /**
     * @param int $offset
     */
    public function setOffset($offset) {
        $this->_offset = self::$_validator->number(
            $offset,
            'Offsets must be positive integers.',
            \Validator::ASSERT_INT_DEFAULT
        );
    }
    
    /**
     * @return int
     */
    public function getLimit() {
        return $this->_limit;
    }
    
    /**
     * @return int
     */
    public function getOffset() {
        return $this->_offset;
    }
}
?>