<?php
namespace Google\Analytics;

abstract class GaDataSegmentGroup extends GaDataSegmentElement {
	const PREFIX = 'UNIMPLEMENTED';
	protected $_negated = false;
	/* I'm putting this property in this class even though it only pertains to
	Google\Analytics\GaDataSegmentConditionGroup so that I can use it in
	$this->__toString(). */
	protected $_scope;
	
	public function __toString() {
		return static::PREFIX . '::'
		     . ($this->_scope ? $this->_scope . '::' : '')
			 . ($this->_negated ? '!' : '')
		     . parent::__toString();
	}
	
	/**
	 * When called with no argument, returns a boolean value indicating whether
	 * this group is negated; when called with an argument, negates or un-
	 * negates this instance according to the argument's truthiness.
	 *
	 * @param boolean $negated = null
	 */
	public function isNegated($negated = null) {
		if ($negated === null) {
			return $this->_negated;
		}
		$this->_negated = (bool)$negated;
	}
}