<?php
namespace Google\Analytics;

abstract class IterativeGaDataQuery extends GaDataQuery {
	protected static $_SETTER_DISPATCH_MODEL = array();
	protected static $_GETTER_DISPATCH_MODEL = array();
	protected static $_MERGE_DISPATCH_MODELS = true;
	protected $_iterativeName = 'Iteration';
	
	/**
	 * Sets an arbitrary name to use for the "column" header represented by
	 * this instance's iterative property.
	 *
	 * @param string $iterativeName
	 */
	public function setIterativeName($iterativeName) {
		$this->_iterativeName = self::$_validator->string(
			$iterativeName, null, \Validator::FILTER_TRIM
		);
	}
	
	/**
	 * @return string
	 */
	public function getIterativeName() {
		return $this->_iterativeName;
	}
	
	/**
	 * Attempts to advance to the next iteration. Returns a boolean true if
	 * there was in fact a next iteration, and false otherwise.
	 *
	 * @return boolean
	 */
	abstract public function iterate();
	
	abstract public function reset();
}
?>