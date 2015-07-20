<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class GaDataConditionalExpression {
	const OP_EQ = '==';
	const OP_NE = '!=';
	const OP_GT = '>';
	const OP_LT = '<';
	const OP_GE = '>=';
	const OP_LE = '<=';
	const OP_CONTAINS = '=@';
	const OP_NOT_CONTAINS = '!@';
	const OP_REGEXP = '=~';
	const OP_NOT_REGEXP = '!~';
	protected static $_constantsByName;
	protected static $_constantsByVal;
	protected static $_maxOperatorLength = 0;
	protected $_operator;
	protected $_leftOperand;
	protected $_rightOperand;
	
	/**
	 * Instantiates a new representation of an expression, either from a single
	 * string or from its distinct components.
	 *
	 * @param string $expression
	 * @param string $operator = null
	 * @param string $rightOperand = null
	 */
	public function __construct(
		$expression,
		$operator = null,
		$rightOperand = null
	) {
		if (!static::$_constantsByVal) {
			static::_initStaticProperties();
		}
		if (!strlen($expression)) {
			throw new InvalidArgumentException(
				'A non-empty expression or operand is required.'
			);
		}
		if ($operator !== null xor $rightOperand !== null) {
			throw new InvalidArgumentException(
				'If the entire expression is not provided in the first ' .
				'argument, both an operator and a right operand must be ' .
				'provided.'
			);
		}
		elseif (strlen($operator)) {
			$this->_operator = $this->_validateOperator($operator);
			$this->_leftOperand = $this->_validateLeftOperand($expression);
			$this->_rightOperand = $this->_validateRightOperand($rightOperand);
		}
		else {
			$this->_setPropertiesFromString($expression);
		}
	}
	
	public function __toString() {
		return $this->_leftOperand . $this->_operator . $this->_rightOperand;
	}
	
	protected static function _initStaticProperties() {
		$reflector = new \ReflectionClass(get_called_class());
		static::$_constantsByName = $reflector->getConstants();
		static::$_constantsByVal = array_flip(static::$_constantsByName);
		// We need the maximum operator length for the parsing stage
		foreach (static::$_constantsByName as $constant) {
			$len = strlen($constant);
			if ($len > static::$_maxOperatorLength) {
				static::$_maxOperatorLength = $len;
			}
		}
	}
	
	/**
	 * Validates the operator and returns it if valid.
	 *
	 * @param string $operator
	 * @return string
	 */
	protected function _validateOperator($operator) {
		if (!isset(static::$_constantsByVal[$operator]) ||
		    substr(static::$_constantsByVal[$operator], 0, 3) != 'OP_')
		{
			throw new InvalidArgumentException('Invalid operator specified.');
		}
		return $operator;
	}
	
	/**
	 * Validates the left operand and returns it if valid.
	 *
	 * @param string $operand
	 * @return string
	 */
	protected function _validateLeftOperand($operand) {
		/* This logic expects the left operand to be a simple Google Analytics
		column name. This logic needs to be overridden for more complex
		expressions (e.g. sequence expressions in segments). */
		$operand = API::addPrefix($operand);
		if (preg_match('/[^a-zA-Z0-9]/', substr($operand, 3))) {
			throw new InvalidArgumentException(
				'Invalid characters detected in dimension or metric ' .
				'name "' . $operand . '".'
			);
		}
		return $operand;
	}
	
	/**
	 * Validates the right operand and returns it if valid.
	 *
	 * @param string $operand
	 * @return string
	 */
	protected function _validateRightOperand($operand) {
		if (!is_scalar($operand)) {
			throw new InvalidArgumentException(
				'Operands must be passed as scalar values.'
			);
		}
		// This does nothing other than escaping ANDs and ORs
		return \PFXUtils::escape(
			$operand,
			GaDataLogicalCollection::OP_AND . GaDataLogicalCollection::OP_OR
		);
	}
	
	/**
	 * Splits a conditional expression string into an array of which the first
	 * element is the left operand and the second is the remainder of the
	 * string. This also performs some basic validation in that it expects that
	 * the string will begin with ga: followed by a column name followed by
	 * an operator.
	 *
	 * @param string $str
	 * @return array
	 */
	protected function _splitStringExpression($str) {
		$matches = array();
		if (!preg_match(
			'/^(ga:[a-zA-Z0-9]+)([^a-zA-Z0-9].*)$/', $str, $matches
		)) {
			throw new InvalidArgumentException(
				'Unable to extract dimension or metric name from expression.'
			);
		}
		// Shift the full string off before returning
		array_shift($matches);
		return $matches;
	}
	
	/**
	 * Parses a conditional expression into its three component parts and sets
	 * them in the appropriate object properties.
	 *
	 * @param string $str
	 */
	protected function _setPropertiesFromString($str) {
		$components = $this->_splitStringExpression($str);
		$this->_leftOperand = $this->_validateLeftOperand($components[0]);
		/* Find the operator by iteratively testing the first n characters of
		the second match group, where n is the maximum operator length, and
		iterate down to 1 until we find a match. */
		for ($i = static::$_maxOperatorLength; $i > 0; $i--) {
			$testString = substr($components[1], 0, $i);
			try {
				$this->_operator = $this->_validateOperator($testString);
				$this->_rightOperand = $this->_validateRightOperand(
					substr($components[1], $i)
				);
				break;
			} catch (InvalidArgumentException $e) {}
		}
		if ($this->_operator === null) {
			throw new InvalidArgumentException(
				'Unable to extract operator from expression.'
			);
		}
	}
	
	/**
	 * @return string
	 */
	public function getOperator() {
		return $this->_operator;
	}
	
	/**
	 * @return string
	 */
	public function getLeftOperand() {
		return $this->_leftOperand;
	}
	
	/**
	 * @return string
	 */
	public function getRightOperand() {
		return $this->_rightOperand;
	}
}
?>