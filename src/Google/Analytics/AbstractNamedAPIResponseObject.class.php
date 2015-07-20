<?php
namespace Google\Analytics;

abstract class AbstractNamedAPIResponseObject extends AbstractAPIResponseObject {
	protected static $_SUPPLEMENTAL_SETTER_DISPATCH_MODEL = array(
		'name' => 'setName'
	);
	protected $_name;
	
	public function __construct(array $apiData = null) {
		/* I tried damn hard to find a way to define a method in the base class
		to handle this that would be automatically be called by every class in
		the inheritance chain so I wouldn't have to do this boilerplate stuff,
		but I wasn't able to solve the puzzle. */
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
	 * @param string $name
	 */
	public function setName($name) {
		$this->_name = $name;
	}
	
	/**
	 * @return string
	 */
	public function getName() {
		return $this->_name;
	}
}