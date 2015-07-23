<?php
namespace Google\Analytics;

class GaDataSegmentConditionGroup extends GaDataSegmentGroup {
	const PREFIX = 'condition';
	const SCOPE_PER_HIT = 'perHit';
	const SCOPE_PER_SESSION = 'perSession';
	const SCOPE_PER_USER = 'perUser';
	private static $_constantsByName;
	private static $_constantsByVal;
	
	private static function _initStaticProperties() {
		$r = new \ReflectionClass(__CLASS__);
		self::$_constantsByName = $r->getConstants();
		self::$_constantsByVal = array_flip(self::$_constantsByName);
	}
	
	/**
	 * Adds a condition to this group.
	 *
	 * @param Google\Analytics\GaDataSegmentSimpleCondition $condition
	 */
	protected function _addMember($member) {
		if (!is_object($member) ||
		    get_class($member) != __NAMESPACE__ . '\GaDataSegmentSimpleCondition')
		{
			throw new InvalidArgumentException(
				'Group members must be ' .
				__NAMESPACE__ . '\GaDataSegmentSimpleCondition instances.'
			);
		}
		$this->_members[] = $member;
	}
	
	/**
	 * Parses a string representation of this class into an object instance.
	 *
	 * @todo Implement the damn thing
	 * @param string $str
	 * @return self
	 */
	public static function createFromString($str) {
		
	}
	
	/**
	 * Sets this group's metric scope.
	 *
	 * @param string $scope
	 */
	public function setScope($scope) {
		if (!self::$_constantsByName) {
			self::_initStaticProperties();
		}
		if (!isset(self::$_constantsByVal[$scope]) ||
		    substr(self::$_constantsByVal[$scope], 0, 5) != 'SCOPE')
		{
			throw new InvalidArgumentException(
				'Invalid scope "' . $scope . '".'
			);
		}
		$this->_scope = $scope;
	}
}
?>