<?php
namespace Google\Analytics;

class GaDataSegmentSimpleCondition extends GaDataConditionalExpression {
	const OP_BETWEEN = '<>';
	const OP_IN = '[]';
	protected static $_constantsByName;
	protected static $_constantsByVal;
	protected static $_maxOperatorLength = 0;
	
	/**
	 * Performs some special handling if the operator requires a range or a
	 * list.
	 *
	 * @param string, array $operand
	 */
	protected function _validateRightOperand($operand) {
		if ($this->_operator == self::OP_BETWEEN ||
		    $this->_operator == self::OP_IN)
		{
			/* This logic doesn't attempt to verify that the ends of a range
			are valid for range comparison, as that's sort of a pain. */
			if (is_array($operand)) {
				if ($this->_operator == self::OP_BETWEEN) {
					if (count($operand) != 2) {
						throw new InvalidArgumentException(
							'When passing an array as an operand to a range ' .
							'operator, it must contain exactly two values.'
						);
					}
					$operand = implode('_', $operand);
				}
				else {
					foreach ($operand as &$opComponent) {
						$opComponent = \PFXUtils::escape($opComponent, '|');
					}
					$operand = implode('|', $operand);
				}
			}
			elseif ($this->_operator == self::OP_BETWEEN) {
				$components = explode('_', $operand);
				if (count($components) != 2) {
					throw new InvalidArgumentException(
						'Operands used with the <> operator must have ' .
						'exactly two boundaries separated by an underscore.'
					);
				}
			}
		}
		return parent::_validateRightOperand($operand);
	}
}
?>