<?php
namespace Google\Analytics;

abstract class IterativeGaDataQuery extends GaDataQuery {
	protected $_iterativeName = 'Iteration';
	
	/**
	 * Sets an arbitrary name to use for the "column" represented by this
	 * interval.
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