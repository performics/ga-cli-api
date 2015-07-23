<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

abstract class AbstractAPIResponseObject extends \Google\AbstractAPIResponseObject {
	protected static $_SETTER_DISPATCH_MODEL = array(
		'id' => 'setID'
	);
	protected static $_validator;
	protected $_id;
	
	public function __construct(array $apiData = null) {
		if (!self::$_validator) {
			self::$_validator = new \Validator(__NAMESPACE__);
		}
		parent::__construct($apiData);
	}
	
	/**
	 * @param string $id
	 */
	public function setID($id) {
		$this->_id = $id;
	}
	
	/**
	 * @return string
	 */
	public function getID() {
		return $this->_id;
	}
}
?>