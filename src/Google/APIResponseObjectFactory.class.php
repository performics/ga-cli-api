<?php
namespace Google;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class APIResponseObjectFactory {
	/**
	 * Factory method to create an object from a Google API response.
	 *
	 * @param array $apiResponse
	 */
	public static function create(array $apiResponse) {
		if (!array_key_exists('kind', $apiResponse)) {
			throw new InvalidArgumentException(
				'Could not find the resource type in the API response data.'
			);
		}
		$components = explode('#', $apiResponse['kind']);
		if (count($components) != 2) {
			throw new UnexpectedValueException(
				'Encountered unexpected resource type "' .
				$apiResponse['kind'] . '".'
			);
		}
		$namespace = ucfirst($components[0]);
		$class = ucfirst($components[1]);
		$className = implode('\\', array(__NAMESPACE__, $namespace, $class));
		if (class_exists($className)) {
			return new $className($apiResponse);
		}
		throw new BadMethodCallException(
			'The class "' . $className . '" does not exist.'
		);
	}
}
?>