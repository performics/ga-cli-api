<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

abstract class GaDataLogicalCollection {
	const OP_AND = ';';
	const OP_OR = ',';
	protected $_operator;
	protected $_members = array();
	
	/**
	 * Instantiates a collection with a variable number of members.
	 *
	 * @param string $operator
	 * @param mixed $member
	 * [@param mixed $member...]
	 */
	public function __construct() {
		$args = func_get_args();
		if (count($args) < 2) {
			throw new InvalidArgumentException(
				'An operator and at least one member are required arguments.'
			);
		}
		$this->_operator = static::_validateOperator(array_shift($args));
		foreach ($args as $arg) {
			$this->_addMember($arg);
		}
	}
	
	public function __toString() {
		$members = array();
		foreach ($this->_members as $member) {
			$members[] = (string)($member);
		}
		return implode($this->_operator, $members);
	}
	
	/**
	 * Validates the operator and returns it if valid.
	 *
	 * @param string $operator
	 * @return string
	 */
	protected static function _validateOperator($operator) {
		if ($operator != self::OP_AND && $operator != self::OP_OR) {
			throw new InvalidArgumentException('Invalid operator specified.');
		}
		return $operator;
	}
	
	/**
	 * Adds a member to the collection.
	 *
	 * @param mixed $member
	 */
	abstract protected function _addMember($member);
	
	/**
	 * Parses a string representation of this class into an object instance.
	 *
	 * @param string $str
	 * @return self
	 */
	public static function createFromString($str) {
		throw new BadMethodCallException(
			'This method must be implemented by a subclass.'
		);
	}
}
?>