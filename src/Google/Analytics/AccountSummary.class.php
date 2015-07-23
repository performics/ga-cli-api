<?php
namespace Google\Analytics;

class AccountSummary extends AbstractNamedAPIResponseObject {
	protected static $_SUPPLEMENTAL_SETTER_DISPATCH_MODEL = array(
		'webProperties' => 'setWebPropertySummaries'
	);
	protected $_webPropertySummariesByName = array();
	protected $_webPropertySummariesByID = array();
	
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
	 * @param array $webProperties
	 */
	public function setWebPropertySummaries(array $webProperties) {
		foreach ($webProperties as $webProperty) {
			if (is_array($webProperty)) {
				$webProperty = \Google\APIResponseObjectFactory::create(
					$webProperty
				);
			}
			$this->_webPropertySummariesByName[$webProperty->getName()] =
				$this->_webPropertySummariesByID[$webProperty->getID()] =
					$webProperty;
		}
	}
	
	/**
	 * @return array
	 */
	public function getWebPropertySummaries() {
		return array_values($this->_webPropertySummariesByID);
	}
	
	/**
	 * @param string $name
	 * @return Google\Analytics\WebPropertySummary
	 */
	public function getWebPropertySummaryByName($name) {
		if (isset($this->_webPropertySummariesByName[$name])) {
			return $this->_webPropertySummariesByName[$name];
		}
		throw new InvalidArgumentException(
			'Unrecognized web property "' . $name . '".'
		);
	}
	
	/**
	 * @param string $id
	 * @return Google\Analytics\WebPropertySummary
	 */
	public function getWebPropertySummaryByID($id) {
		if (isset($this->_webPropertySummariesByID[$id])) {
			return $this->_webPropertySummariesByID[$id];
		}
		throw new InvalidArgumentException(
			'Unrecognized web property ID "' . $id . '".'
		);
	}
}
?>