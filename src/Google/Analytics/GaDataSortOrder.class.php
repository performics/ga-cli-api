<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class GaDataSortOrder {
	private $_fieldCount = 0;
	private $_fields = array();
	private $_orders = array();
	
	public function __toString() {
		$resolvedFields = array();
		for ($i = 0; $i < $this->_fieldCount; $i++) {
			$field = $this->_fields[$i];
			if ($this->_orders[$i] == SORT_DESC) {
				$field = '-' . $field;
			}
			$resolvedFields[] = $field;
		}
		return implode(',', $resolvedFields);
	}
	
	/**
	 * Instantiates an object using a string formatted according to the Google
	 * Analytics API's syntax.
	 *
	 * @param string $sortString
	 * @return Google\Analytics\GaDataSortOrder
	 */
	public static function createFromString($sortString) {
		$components = explode(',', $sortString);
		$instance = new self();
		foreach ($components as $component) {
			if ($component[0] == '-') {
				$component = substr($component, 1);
				$order = SORT_DESC;
			}
			else {
				$order = SORT_ASC;
			}
			$instance->addField($component, $order);
		}
		return $instance;
	}
	
	/**
	 * Adds a field to the sort order.
	 *
	 * @param string $field
	 * @param int $order = SORT_ASC
	 */
	public function addField($field, $order = SORT_ASC) {
		if ($order != SORT_ASC && $order != SORT_DESC) {
			throw new InvalidArgumentException(
				"Sort order must be expressed using one of PHP's SORT_ASC " .
				'or SORT_DESC constants.'
			);
		}
		$field = API::addPrefix($field);
		if (in_array($field, $this->_fields)) {
			throw new OverflowException(
				'The field "' . $field . '" is already present in this ' .
				'sort directive.'
			);
		}
		$this->_fields[] = $field;
		$this->_orders[] = $order;
		$this->_fieldCount++;
	}
}
?>