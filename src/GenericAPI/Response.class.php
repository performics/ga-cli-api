<?php
namespace GenericAPI;

abstract class Response {
	protected static $_SETTER_DISPATCH_MODEL = array();
	
	/**
	 * Instantiates a new object and iterates through the argument array (if
	 * provided) to call the setters defined in self::$_SETTER_DISPATCH_MODEL
	 * against the values stored under the corresponding keys.
	 *
	 * @param array $apiData = null
	 */
	public function __construct(array $apiData = null) {
		if ($apiData && !static::_dispatchSetters(
			$apiData, $this, static::$_SETTER_DISPATCH_MODEL
		)) {
			throw new \RuntimeException(
				'No data was set. Does the data type match the object type?'
			);
		}
	}
	
	/**
	 * Iterates through the setters declared in the third argument and calls
	 * them on the object passed in the second argument, using the data in the
	 * first argument. Returns the total number of setters called.
	 *
	 * @param array $data
	 * @param GenericAPI\Response $object
	 * @param array $model
	 * @return int
	 */
	protected static function _dispatchSetters(
		array $data,
		self $object,
		array $model
	) {
		$settersCalled = 0;
		foreach ($model as $key => $val) {
			if (array_key_exists($key, $data)) {
				if (is_array($val)) {
					if (is_array($data[$key])) {
						$settersCalled += self::_dispatchSetters(
							$data[$key], $object, $val
						);
					}
				}
				elseif (method_exists($object, $val)) {
					$object->$val($data[$key]);
					$settersCalled++;
				}
				else {
					throw new \BadMethodCallException(
						'The method "' . $val . '" does not exist in this ' .
						'object.'
					);
				}
			}
		}
		return $settersCalled;
	}
}
?>