<?php
namespace Google\Analytics;

abstract class GaDataSegmentElement extends GaDataLogicalCollection {
	// The OR operator doesn't make sense in segments
	protected $_operator = self::OP_AND;
	
	/**
	 * Instantiates a segment element.
	 *
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
}
?>