<?php
namespace Google\Analytics;

abstract class GaDataSegmentGroup extends GaDataLogicalCollection {
	const PREFIX = 'UNIMPLEMENTED';
	// The OR operator doesn't make sense in segments
	protected $_operator = self::OP_AND;
	protected $_negated = false;
	/* I'm putting this property in this class even though it only pertains to
	Google\Analytics\GaDataSegmentConditionGroup so that I can use it in
	$this->__toString(). */
	protected $_scope;
	
	/**
	 * Instantiates a segment element.
	 *
	 * @param mixed $member
	 * [@param mixed $member...]
	 */
	public function __construct() {
		$args = func_get_args();
		if (count($args) < 1) {
			throw new InvalidArgumentException(
				'At least one member is required.'
			);
		}
		foreach ($args as $arg) {
			$this->_addMember($arg);
		}
	}
	
	public function __toString() {
		return static::PREFIX . '::'
		     . ($this->_scope ? $this->_scope . '::' : '')
			 . ($this->_negated ? '!' : '')
		     . parent::__toString();
	}
	
	/**
	 * Validates the operator and returns it if valid. Note that this method
	 * shouldn't even be called in the context of this class, because it
	 * doesn't provide a way to set an operator.
	 *
	 * @param string $operator
	 * @return string
	 */
	protected static function _validateOperator($operator) {
		if ($operator != self::OP_AND) {
			throw new InvalidArgumentException('Invalid operator specified.');
		}
		return $operator;
	}
	
	/**
	 * When called with no argument, returns a boolean value indicating whether
	 * this group is negated; when called with an argument, negates or un-
	 * negates this instance according to the argument's truthiness.
	 *
	 * @param boolean $negated = null
	 */
	public function isNegated($negated = null) {
		if ($negated === null) {
			return $this->_negated;
		}
		$this->_negated = (bool)$negated;
	}
}