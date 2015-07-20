<?php
namespace Google\Analytics;

class ProfileSummary extends AbstractNamedAPIResponseObject {
	protected static $_SUPPLEMENTAL_SETTER_DISPATCH_MODEL = array(
		'type' => 'setType'
	);
	protected $_type;
	
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
	 * @param string $type
	 */
	public function setType($type) {
		$this->_type = $type;
	}
	
	/**
	 * @return string
	 */
	public function getType() {
		return $this->_type;
	}
}
?>