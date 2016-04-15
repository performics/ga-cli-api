<?php
namespace Google\Analytics;

class AccountSummary extends AbstractNamedAPIResponseObject {
	protected static $_SETTER_DISPATCH_MODEL = array(
		'webProperties' => 'setWebPropertySummaries'
	);
	protected static $_GETTER_DISPATCH_MODEL = array();
	protected static $_MERGE_DISPATCH_MODELS = true;
	protected static $_dispatchModelReady = false;
	protected $_webPropertySummariesByName = array();
	protected $_webPropertySummariesByID = array();
	
	/**
	 * @param array $webProperties
	 */
	public function setWebPropertySummaries(array $webProperties) {
		foreach ($webProperties as $webProperty) {
			if (is_array($webProperty)) {
				$webProperty = APIResponseObjectFactory::create(
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