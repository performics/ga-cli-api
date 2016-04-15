<?php
namespace Google\Analytics;

class Column extends AbstractNamedAPIResponseObject {
	protected static $_SETTER_DISPATCH_MODEL = array(
		'attributes' => array(
			'replacedBy' => 'setReplacementColumn',
			'type' => 'setType',
			'dataType' => 'setDataType',
			'group' => 'setGroup',
			'status' => 'isDeprecated',
			'uiName' => 'setUIName',
			'description' => 'setDescription',
			'calculation' => 'setCalculation',
			'minTemplateIndex' => 'setMinTemplateIndex',
			'maxTemplateIndex' => 'setMaxTemplateIndex',
			'premiumMinTemplateIndex' => 'setPremiumMinTemplateIndex',
			'premiumMaxTemplateIndex' => 'setPremiumMaxTemplateIndex',
			'allowedInSegments' => 'isAllowedInSegments'
		)
	);
	protected static $_GETTER_DISPATCH_MODEL = array();
	protected static $_MERGE_DISPATCH_MODELS = true;
	protected static $_dispatchModelReady = false;
	protected $_replacementColumn;
	protected $_type;
	protected $_dataType;
	protected $_group;
	protected $_deprecated;
	protected $_uiName;
	protected $_description;
	protected $_calculation;
	protected $_minTemplateIndex;
	protected $_maxTemplateIndex;
	protected $_minTemplateIndexPremium;
	protected $_maxTemplateIndexPremium;
	protected $_allowedInSegments = false;
	private $_total;
	
	/**
	 * @param string $id
	 */
	public function setID($id) {
		$this->_id = $id;
		/* In this case there's no "name" property returned from the API, but
		we'll use the ID without the "ga:" portion as the name. */
		$this->_name = substr($this->_id, 3);
	}
	
	/**
	 * @param string $replacementColumn
	 */
	public function setReplacementColumn($replacementColumn) {
		$this->_replacementColumn = $replacementColumn;
	}
	
	/**
	 * @param string $type
	 */
	public function setType($type) {
		$this->_type = $type;
	}
	
	/**
	 * @param string $dataType
	 */
	public function setDataType($dataType) {
		$this->_dataType = $dataType;
	}
	
	/**
	 * @param string $group
	 */
	public function setGroup($group) {
		$this->_group = $group;
	}
	
	/**
	 * @param string $uiName
	 */
	public function setUIName($uiName) {
		$this->_uiName = $uiName;
	}
	
	/**
	 * @param string $description
	 */
	public function setDescription($description) {
		$this->_description = $description;
	}
	
	/**
	 * @param string $calculation
	 */
	public function setCalculation($calculation) {
		$this->_calculation = $calculation;
	}
	
	/**
	 * @param int $minTemplateIndex
	 */
	public function setMinTemplateIndex($minTemplateIndex) {
		$this->_minTemplateIndex = self::$_validator->number(
			$minTemplateIndex, null, \Validator::ASSERT_INT
		);
	}
	
	/**
	 * @param int $maxTemplateIndex
	 */
	public function setMaxTemplateIndex($maxTemplateIndex) {
		$this->_maxTemplateIndex = self::$_validator->number(
			$maxTemplateIndex, null, \Validator::ASSERT_INT
		);
	}
	
	/**
	 * @param int $minTemplateIndex
	 */
	public function setPremiumMinTemplateIndex($minTemplateIndex) {
		$this->_minTemplateIndexPremium = self::$_validator->number(
			$minTemplateIndex, null, \Validator::ASSERT_INT
		);
	}
	
	/**
	 * @param int $maxTemplateIndex
	 */
	public function setPremiumMaxTemplateIndex($maxTemplateIndex) {
		$this->_maxTemplateIndexPremium = self::$_validator->number(
			$maxTemplateIndex, null, \Validator::ASSERT_INT
		);
	}
	
	/**
	 * When used in the context of a Google\Analytics\GaData object, columns
	 * may be assigned totals representing their totals within the data set.
	 * Note that these come down the wire as strings so we need to inspect the
	 * value in order to know how to cast it.
	 *
	 * @param numeric $total
	 */
	public function setTotal($total) {
		try {
			$this->_total = self::$_validator->number(
				$total, null, \Validator::ASSERT_INT
			);
		} catch (InvalidArgumentException $e) {
			$this->_total = (float)$total;
		}
	}
	
	/**
	 * When called with a string argument, sets the $this->_deprecated property
	 * according to whether the argument's value is 'PUBLIC' or 'DEPRECATED'.
	 * When called with a boolean argument, sets the $this->_deprecated
	 * property accordingly. When called with no argument, returns a boolean
	 * value indicating whether this column is deprecated.
	 *
	 * @param string $status = null
	 * @return boolean
	 */
	public function isDeprecated($status = null) {
		if ($status === null) {
			return $this->_deprecated;
		}
		if (is_string($status)) {
			$this->_deprecated = $status == 'PUBLIC' ? false : true;
		}
		else {
			$this->_deprecated = (bool)$status;
		}
	}
	
	/**
	 * When called with an argument, sets the $this->_allowedInSegments
	 * property according to whether the argument's value is "true" or "false"
	 * (interestingly, this comes back from the API as the string "true", and
	 * it seems to be missing entirely if the column is not allowed in
	 * segments, which is why the property defaults to false at the class
	 * level). When called with no argument, returns a boolean value indicating
	 * whether this column is allowed in segments.
	 *
	 * @param string, boolean $allowedInSegments = null
	 * @return boolean
	 */
	public function isAllowedInSegments($allowedInSegments = null) {
		if (is_string($allowedInSegments) && $allowedInSegments == 'true') {
			$this->_allowedInSegments = true;
		}
		elseif ($allowedInSegments !== null) {
			$this->_allowedInSegments = (bool)$allowedInSegments;
		}
		else {
			return $this->_allowedInSegments;
		}
	}
	
	/**
	 * @return string
	 */
	public function getReplacementColumn() {
		return $this->_replacementColumn;
	}
	
	/**
	 * @return string
	 */
	public function getType() {
		return $this->_type;
	}
	
	/**
	 * @return string
	 */
	public function getDataType() {
		return $this->_dataType;
	}
	
	/**
	 * @return string
	 */
	public function getGroup() {
		return $this->_group;
	}
	
	/**
	 * @return string
	 */
	public function getUIName() {
		return $this->_uiName;
	}
	
	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->_description;
	}
	
	/**
	 * @return string
	 */
	public function getCalculation() {
		return $this->_calculation;
	}
	
	/**
	 * @return int
	 */
	public function getMinTemplateIndex() {
		return $this->_minTemplateIndex;
	}
	
	/**
	 * @return int
	 */
	public function getMaxTemplateIndex() {
		return $this->_maxTemplateIndex;
	}
	
	/**
	 * @return int
	 */
	public function getPremiumMinTemplateIndex() {
		return $this->_minTemplateIndexPremium;
	}
	
	/**
	 * @return int
	 */
	public function getPremiumMaxTemplateIndex() {
		return $this->_maxTemplateIndexPremium;
	}
	
	/**
	 * @return int, float
	 */
	public function getTotal() {
		return $this->_total;
	}
}
?>