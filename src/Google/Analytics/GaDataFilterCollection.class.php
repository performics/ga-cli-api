<?php
namespace Google\Analytics;

class GaDataFilterCollection extends GaDataLogicalCollection {
	/**
	 * Validates that the argument is a Google\Analytics\GaDataFilterCollection
	 * or Google\Analytics\GaDataConditionalExpression instance.
	 *
	 * @param Google\Analytics\GaDataFilterCollection, Google\Analytics\GaDataConditionalExpression $member
	 */
	protected function _addMember($member) {
		if (!is_object($member) ||
			(!($member instanceof self) && !($member instanceof GaDataConditionalExpression)))
		{
			throw new InvalidArgumentException(
				'Members of a filter collection must be filters or ' .
				'filter collections.'
			);
		}
		$this->_members[] = $member;
	}
	
	/**
	 * Instantiates an object using a string formatted according to the Google
	 * Analytics API's syntax.
	 *
	 * @param string $filters
	 * @return Google\Analytics\GaDataFilterCollection
	 */
	public static function createFromString($filters) {
		$andFilters = \PFXUtils::explodeUnescaped(self::OP_AND, $filters);
		$andCollections = array();
		$reflector = new \ReflectionClass(__CLASS__);
		foreach ($andFilters as $andFilter) {
			$orFilters = \PFXUtils::explodeUnescaped(self::OP_OR, $andFilter);
			$orFilterInstances = array();
			foreach ($orFilters as $orFilter) {
				$orFilterInstances[] = new GaDataConditionalExpression(
					$orFilter
				);
			}
			$andCollections[] = $reflector->newInstanceArgs(array_merge(
				array(self::OP_OR), $orFilterInstances
			));
		}
		return $reflector->newInstanceArgs(
			array_merge(array(self::OP_AND), $andCollections)
		);
	}
}
?>