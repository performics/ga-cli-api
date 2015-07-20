<?php
namespace Google\Analytics;

class GaDataSegmentSequenceCondition extends GaDataSegmentSimpleCondition {
	const OP_FOLLOWED_BY = '->>';
	const OP_FOLLOWED_BY_IMMEDIATE = '->';
	const OP_FIRST_HIT_MATCHES_FIRST_STEP = '^';
	protected static $_constantsByName;
	protected static $_constantsByVal;
	protected static $_maxOperatorLength = 0;
	private $_constraintAgainstPrevious;
	private $_additionalConditions = array();
	
	/**
	 * Instantiates a new sequence condition, either from a single string or
	 * from its distinct components.
	 *
	 * @param string $expression
	 * @param string $operator = null
	 * @param string, array $rightOperand = null
	 * @param string $constraintAgainstPrevious = null
	 * [@param Google\Analytics\GaDataSegmentSimpleCondition $additionalCondition...]
	 */
	public function __construct(
		$expression,
		$operator = null,
		$rightOperand = null,
		$constraintAgainstPrevious = null
	) {
		parent::__construct($expression, $operator, $rightOperand);
		/* This argument only applies if the operator argument was passed;
		otherwise it should have been parsed from the entire string. */
		if ($operator !== null) {
			$this->_constraintAgainstPrevious = $this->_validateConstraintAgainstPrevious(
				$constraintAgainstPrevious
			);
			// See if any additional conditions were passed
			$args = func_get_args();
			$argCount = count($args);
			for ($i = 4; $i < $argCount; $i++) {
				$this->addCondition($args[$i]);
			}
		}
	}
	
	public function __toString() {
		$additional = array();
		foreach ($this->_additionalConditions as $condition) {
			$additional[] = (string)$condition;
		}
		$additional = implode(GaDataLogicalCollection::OP_AND, $additional);
		if ($additional) {
			$additional = GaDataLogicalCollection::OP_AND . $additional;
		}
		return $this->_constraintAgainstPrevious . $this->_leftOperand
		     . $this->_operator . $this->_rightOperand . $additional;
	}
	
	/**
	 * Overrides parent logic to handle the 'dateOfSession' special case.
	 *
	 * @param string $str
	 * @return array
	 */
	protected function _splitStringExpression($str) {
		if (substr($str, 0, 13) == 'dateOfSession') {
			return array('dateOfSession', substr($str, 13));
		}
		return parent::_splitStringExpression($str);
	}
	
	/**
	 * Validates the left operand and returns it if valid.
	 *
	 * @param string $operand
	 * @return string
	 */
	protected function _validateLeftOperand($operand) {
		// This is a special case
		if ($operand == 'dateOfSession') {
			return $operand;
		}
		return parent::_validateLeftOperand($operand);
	}
	
	/**
	 * Validates the constraint against the previous step in the sequence and
	 * returns it if valid.
	 *
	 * @param string $constraint
	 * @return string
	 */
	protected function _validateConstraintAgainstPrevious($constraint) {
		// Another special case
		if ($this->_leftOperand == 'dateOfSession' && strlen($constraint)) {
			throw new LogicException(
				'dateOfSession conditions may not have a constraint against ' .
				'the previous sequence step.'
			);
		}
		if (strlen($constraint) &&
		    $constraint != self::OP_FOLLOWED_BY &&
		    $constraint != self::OP_FOLLOWED_BY_IMMEDIATE &&
			$constraint != self::OP_FIRST_HIT_MATCHES_FIRST_STEP)
		{
			throw new InvalidArgumentException(
				'The argument "' . $constraint . '" is not valid as a ' .
				'constraint against the previous step in the sequence.'
			);
		}
		return $constraint;
	}
	
	/**
	 * Parses a sequence condition into its component pieces.
	 *
	 * @param string $str
	 */
	protected function _setPropertiesFromString($str) {
		/* This isn't quite as slick as the approach that
		Google\Analytics\GaDataConditionalExpression uses to find the operator,
		but it does ensure that we only match the correct operators, and it's
		unlikely this will need much (if any) maintenance in the future. */
		$operators = array(
			self::OP_FOLLOWED_BY,
			self::OP_FOLLOWED_BY_IMMEDIATE,
			self::OP_FIRST_HIT_MATCHES_FIRST_STEP
		);
		foreach ($operators as $operator) {
			$len = strlen($operator);
			if (substr($str, 0, $len) == $operator) {
				$this->_constraintAgainstPrevious = $operator;
				$str = substr($str, $len);
				break;
			}
		}
		// See if there were any additional conditions
		$conditions = \PFXUtils::explodeUnescaped(
			GaDataLogicalCollection::OP_AND, $str
		);
		$str = array_shift($conditions);
		foreach ($conditions as $condition) {
			$this->addCondition(new GaDataSegmentSimpleCondition($condition));
		}
		parent::_setPropertiesFromString($str);
	}
	
	/**
	 * Adds another condition to this sequence step.
	 *
	 * @param Google\Analytics\GaDataSegmentSimpleCondition $condition
	 */
	public function addCondition(GaDataSegmentSimpleCondition $condition) {
		// Don't allow any subclasses here
		if (get_class($condition) != __NAMESPACE__ . '\GaDataSegmentSimpleCondition')
		{
			throw new InvalidArgumentException(
				'Additional conditions within a sequence step must be ' .
				__NAMESPACE__ . '\GaDataSegmentSimpleCondition instances.'
			);
		}
		$this->_additionalConditions[] = $condition;
	}
	
	/**
	 * @return string
	 */
	public function getConstraintAgainstPrevious() {
		return $this->_constraintAgainstPrevious;
	}
	
	/**
	 * @return array
	 */
	public function getAdditionalConditions() {
		return $this->_additionalConditions;
	}
}
?>