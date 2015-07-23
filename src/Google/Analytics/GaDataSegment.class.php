<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class GaDataSegment {
	const SCOPE_USERS = 'users';
	const SCOPE_SESSIONS = 'sessions';
	private static $_constantsByName;
	private static $_constantsByVal;
	private $_memberCount = 0;
	private $_members = array();
	private $_scopes = array();
	
	/**
	 * Instantiates a representation of a segment containing one or more scoped
	 * segment groups (either Google\Analytics\GaDataSegmentConditionGroup or
	 * Google\Analytics\GaDataSegmentSequence) objects.
	 *
	 * @param Google\Analytics\GaDataSegmentGroup $segmentGroup
	 * @param string $scope
	 * [@param Google\Analytics\GaDataSegmentGroup $segmentGroup
	 * @param string $scope...]
	 */
	public function __construct() {
		if (!self::$_constantsByName) {
			self::_initStaticProperties();
		}
		$args = func_get_args();
		if (!$args || count($args) % 2) {
			throw new InvalidArgumentException(
				'This class must be instantiated with an even number of ' .
				'arguments.'
			);
		}
		while ($args) {
			$segmentGroup = array_shift($args);
			$scope = array_shift($args);
			if (!is_object($segmentGroup) ||
			    !($segmentGroup instanceof GaDataSegmentGroup))
			{
				throw new InvalidArgumentException(
					'Segment attributes must be provided in the form of ' .
					__NAMESPACE__ . '\GaDataSegmentConditionGroup or ' .
					__NAMESPACE__ . '\GaDataSegmentSequence instances.'
				);
			}
			if (!isset(self::$_constantsByVal[$scope])) {
				throw new InvalidArgumentException(
					'Invalid scope "' . $scope . '".'
				);
			}
			$this->_memberCount++;
			$this->_members[] = $segmentGroup;
			$this->_scopes[] = $scope;
		}
	}
	
	public function __toString() {
		$members = array();
		for ($i = 0; $i < $this->_memberCount; $i++) {
			$members[] = $this->_scopes[$i] . '::'
			           . (string)$this->_members[$i];
		}
		return implode(GaDataLogicalCollection::OP_AND, $members);
	}
	
	private static function _initStaticProperties() {
		$r = new \ReflectionClass(__CLASS__);
		self::$_constantsByName = $r->getConstants();
		self::$_constantsByVal = array_flip(self::$_constantsByName);
	}
	
	/**
	 * Creates and returns an instance from its string representation.
	 *
	 * @todo Implement this
	 * @param string $str
	 * @return Google\Analytics\GaDataSegment
	 */
	public static function createFromString($str) {
		/* I'm leaving this unimplemented for now because there are still some
		nuances to the segment syntax that my logic doesn't support. It's not
		crucial and can be deferred. */
		return $str;
		// Each atomic element will be preceded by one of the valid scopes
		if (!self::$_constantsByName) {
			self::_initStaticProperties();
		}
		$scopes = array();
		$groups = array();
		$offset = 0;
		while (true) {
			$positions = array();
			foreach (self::$_constantsByName as $scope) {
				$pos = strpos($str, $scope . '::', $offset);
				if ($pos !== false) {
					$positions[] = $pos;
				}
			}
			if (!$positions) {
				$groups[] = substr($str, $offset);
				break;
			}
			$pos = min($positions);
			// How do we know which one we found? Look for the double colon
			$scope = substr($str, $pos, strpos($str, '::', $pos) - $pos);
			$scopes[] = $scope;
			if ($pos > 0) {
				$groups[] = substr($str, $offset, $pos - $offset);
			}
			$offset = $pos + strlen($scope) + 2;
		}
		$groupCount = count($groups);
		if (count($scopes) != $groupCount) {
			throw new InvalidArgumentException(
				'Encountered error while parsing string.'
			);
		}
		$groupInstances = array();
		for ($i = 0; $i < $groupCount; $i++) {
			/* The syntax specifies that groups be AND-ed together (which
			didn't help us in the parsing stage because we can't easily
			disambiguate which operators separate different groups and which
			ones separate conditions within a group), so every group but the
			last one should have a trailing semicolon. The syntax also allows
			spaces, so there may be spaces to clean as well. */
			$group = rtrim($groups[$i], ' ' . GaDataLogicalCollection::OP_AND);
			// This will either be a simple condition or a sequence
			if (strpos($group, GaDataSegmentConditionGroup::PREFIX . '::') === 0)
			{
				$class = __NAMESPACE__ . '\GaDataSegmentConditionGroup';
			}
			elseif (strpos($group, GaDataSegmentSequence::PREFIX . '::') === 0)
			{
				$class = __NAMESPACE__ . '\GaDataSegmentSequence';
			}
			else {
				/* The syntax seems to allow omission of the scope specifier if
				one was declared previously. In that case, we should be able to
				just inspect the last object we instantiated. */
				if (isset($groupInstances[$i - 1])) {
					$class = get_class($groupInstances[$i - 1]);
				}
				else {
					throw new InvalidArgumentException(
						'The group at index ' . $i .
						' is missing a valid scope.'
					);
				}
			}
		}
	}
}
?>