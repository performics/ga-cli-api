<?php
namespace Google\Analytics;

class Segment extends AbstractNamedAPIResponseObject {
	protected static $_SUPPLEMENTAL_SETTER_DISPATCH_MODEL = array(
		'definition' => 'setDefinition',
		'type' => 'setType',
		'created' => 'setCreatedTime',
		'updated' => 'setUpdatedTime'
	);
	protected $_definition;
	protected $_type;
	protected $_created;
	protected $_updated;
	
	public function __construct(array $apiData = null) {
		if (!isset(static::$_SETTER_DISPATCH_MODEL[
			key(self::$_SUPPLEMENTAL_SETTER_DISPATCH_MODEL)
		])) {
			static::$_SETTER_DISPATCH_MODEL = array_merge(
				static::$_SETTER_DISPATCH_MODEL,
				self::$_SUPPLEMENTAL_SETTER_DISPATCH_MODEL
			);
		}
		parent::__construct($apiData);
	}
	
	/**
	 * @param string, DateTime $date
	 */
	private static function _castToDateTime($date) {
		if ($date instanceof \DateTime) {
			return $date;
		}
		try {
			return new DateTime($date);
		} catch (\Exception $e) {
			throw new InvalidArgumentException(
				'Caught error while parsing datetime string "' . $date . '".',
				null,
				$e
			);
		}
	}
	
	/**
	 * @param string $id
	 */
	public function setID($id) {
		// This comes down the wire as a string, but we'll want it as an int
		$this->_id = self::$_validator->number(
			$id, null, \Validator::ASSERT_INT
		);
	}
	
	/**
	 * @param string $definition
	 */
	public function setDefinition($definition) {
		$this->_definition = $definition;
	}
	
	/**
	 * @param string $type
	 */
	public function setType($type) {
		$this->_type = $type;
	}
	
	/**
	 * @param string, DateTime $created
	 */
	public function setCreatedTime($created) {
		$this->_created = self::_castToDateTime($created);
	}
	
	/**
	 * @param string, DateTime $updated
	 */
	public function setUpdatedTime($updated) {
		$this->_updated = self::_castToDateTime($updated);
	}
	
	/**
	 * @return string
	 */
	public function getDefinition() {
		return $this->_definition;
	}
	
	/**
	 * @return string
	 */
	public function getType() {
		return $this->_type;
	}
	
	/**
	 * @return DateTime
	 */
	public function getCreatedTime() {
		return $this->_created;
	}
	
	/**
	 * @return DateTime
	 */
	public function getUpdatedTime() {
		return $this->_updated;
	}
}
?>