<?php
namespace GenericAPI;

abstract class RESTObject {
	/* This property should associate property names as found in the service's
	parsed response either to setter methods which will be called automatically
	and to which will be passed the property value as an argument, or to nested
	arrays describing this same structure. The latter is useful for flattening
	nested structures from the raw response. The setters will be called in the
	order that they are specified in the array. In an inheritance chain,
	the distinct dispatch models defined in each of a class' parents are
	merged to create the end class' final model (this behavior may be turned
	off by defining the $_dispatchModelReady property as true). Note that the
	use of a property name in any given model short-circuits inheritance of
	anything related to that property in a parent. Consider the following
	example:
	
	class Foo extends \GenericAPI\RestObject {
		protected static $_SETTER_DISPATCH_MODEL = array(
			'foo' => 'setFoo',
			'complex_object' => array(
				'bar' => 'setBar',
				'baz' => 'setBaz'
			)
		);
		protected static $_dispatchModelReady = false;
	}
	
	class Bar extends Foo {
		protected static $_SETTER_DISPATCH_MODEL = array(
			'complex_object' => array(
				'flarf' => 'setFlarf',
				'biz' => 'setBiz'
			)
		);
		protected static $_dispatchModelReady = false;
	}
	
	After inheritance resolution, Bar's setter dispatch model will look like
	this:
	
	array(
		'foo' => 'setFoo',
		'complex_object' => array(
			'flarf' => 'setFlarf',
			'biz' => 'setBiz'
		)
	)
	
	Note that it discarded the setters that Foo defined in association with the
	'complex_object' property.
	*/
	protected static $_SETTER_DISPATCH_MODEL = array();
	/* Along similar lines, this property should associate property names in
	REST-encoded instances of this type with the getters that should be called
	to populate them. Each of these getters should return the proper RESTful
	representation of the relevant property without requiring any arguments to
	be passed. */
	protected static $_GETTER_DISPATCH_MODEL = array();
	/* If this property is declared as true, it will merge in getters derived
	from the setter dispatch model by replacing the string "set" (if found)
	with "get" for any property not explicitly declared in the model. This
	merging is after resolving the setter dispatch model against the parent,
	but before resolving the getter model. If there are properties that are
	present in the setter model but not desired in the getter model, they may
	be suppressed by declaring them with a null value in the getter model. */
	protected static $_MERGE_DISPATCH_MODELS;
	/* Every subclass must redeclare this property. If it is redeclared with a
	false value, it will signal this class' constructor to perform a one-time
	merging of all the setter dispatch models in the inheritance chain. Giving
	it a true value short-circuits this inheritance. */
	protected static $_dispatchModelReady = false;
	/* This property determines whether properties whose value resolves to null
	are included in the RESTful representation of this instance. */
	protected $_skipNullProperties = false;
	
	/**
	 * Instantiates a new object and iterates through the argument array (if
	 * provided) to call the setters defined in static::$_SETTER_DISPATCH_MODEL
	 * against the values stored under the corresponding keys.
	 *
	 * @param array $apiData = null
	 */
	public function __construct(array $apiData = null) {
		if (!static::$_dispatchModelReady) {
			$r = new \ReflectionClass($this);
			static::_resolveDispatchModels($r);
			self::_validateMethods(static::$_SETTER_DISPATCH_MODEL, $r);
			self::_validateMethods(static::$_GETTER_DISPATCH_MODEL, $r);
		}
		if ($apiData && !static::_dispatchSetters(
			$apiData, $this, static::$_SETTER_DISPATCH_MODEL
		)) {
			throw new \RuntimeException(
				'No data was set. Does the data type match the object type?'
			);
		}
	}
	
	/**
	 * @param string, array $node
	 * @return string, array
	 */
	private static function _getGetterFromSetterModelNode($node) {
		if (is_array($node)) {
			$getters = array();
			foreach ($node as $property => $value) {
				$getters[$property] = self::_getGetterFromSetterModelNode($value);
			}
			return $getters;
		}
		if (substr($node, 0, 3) == 'set') {
			$node = 'get' . substr($node, 3);
		}
		return $node;
	}
	
	/**
	 * Walks a dispatch model and validates that each of the methods referenced
	 * actually exists.
	 *
	 * @param array $model
	 * @param ReflectionClass $r
	 */
	private static function _validateMethods(array $model, \ReflectionClass $r)
	{
		foreach ($model as $node) {
			if (is_array($node)) {
				self::_validateMethods($node, $r);
			}
			elseif ($node !== null && !$r->hasMethod($node)) {
				throw new \BadMethodCallException(
					'The method "' . $node . '" is not present in the class ' .
					$r->getName() . '.'
				);
			}
		}
	}
	
	/**
	 * Resolves this class' defined dispatch models against those of its parent
	 * class.
	 *
	 * @param ReflectionClass $r
	 */
	protected static function _resolveDispatchModels(\ReflectionClass $r) {
		/* If this class' models have already been handled (or the class
		short-circuits dispatch model inheritance), or we have reached the
		ultimate parent, we are done. */
		$className = $r->getName();
		if (static::$_dispatchModelReady || $className == __CLASS__) {
			return;
		}
		/* Test whether this class defines its own dispatch model properties.
		Since we'll be modifying them, allowing that modification to happen on
		the static properties belonging to a parent class could lead to weird
		problems. */
		$properties = array('_SETTER_DISPATCH_MODEL', '_GETTER_DISPATCH_MODEL');
		foreach ($properties as $propName) {
			$prop = $r->getProperty($propName);
			if ($prop->class != $className) {
				throw new \LogicException(
					'Every class descended from ' . __CLASS__ . ' must ' .
					'define its own static $_SETTER_DISPATCH_MODEL and ' .
					'$_GETTER_DISPATCH_MODEL properties.'
				);
			}
		}
		$r = $r->getParentClass();
		// Make sure the parent is itself resolved before we proceed
		call_user_func(array($r->getName(), __FUNCTION__), $r);
		$prop = $r->getProperty('_SETTER_DISPATCH_MODEL');
		$prop->setAccessible(true);
		$parentSetters = $prop->getValue();
		$prop = $r->getProperty('_GETTER_DISPATCH_MODEL');
		$prop->setAccessible(true);
		$parentGetters = $prop->getValue();
		// Always prefer the declarations of the called class
		static::$_SETTER_DISPATCH_MODEL = array_merge(
			$parentSetters, static::$_SETTER_DISPATCH_MODEL
		);
		if (static::$_MERGE_DISPATCH_MODELS) {
			/* Any getters that we would derive from the resolved setter
			dispatch model should be added before merging in the parent class'
			getter dispatch model. */
			foreach (static::$_SETTER_DISPATCH_MODEL as $property => $node) {
				if (!array_key_exists(
					$property, static::$_GETTER_DISPATCH_MODEL
				)) {
					static::$_GETTER_DISPATCH_MODEL[
						$property
					] = self::_getGetterFromSetterModelNode($node);
				}
			}
		}
		static::$_GETTER_DISPATCH_MODEL = array_merge(
			$parentGetters, static::$_GETTER_DISPATCH_MODEL
		);
		static::$_dispatchModelReady = true;
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
				else {
					$object->$val($data[$key]);
					$settersCalled++;
				}
			}
		}
		return $settersCalled;
	}
	
	/**
	 * Walks the model provided and calls each of the specified methods on the
	 * object instance passed, returning the structure as an array.
	 *
	 * @param self $object
	 * @param array $model
	 * @return array
	 */
	private static function _dispatchGetters(self $object, array $model) {
		$restObject = array();
		foreach ($model as $property => $node) {
			if (is_array($node)) {
				$restObject[$property] = self::_dispatchGetters($object, $node);
			}
			elseif ($node !== null) {
				$value = $object->$node();
				if (!($value === null && $object->_skipNullProperties)) {
					$restObject[$property] = $value;
				}
			}
		}
		return $restObject;
	}
	
	/**
	 * Helper method to resolve getter calls for properties that are themselves
	 * descendents of this class, which may need to be returned as themselves,
	 * or as their RESTful representations (if set). The first argument may be
	 * either an object instance or an array of object instances.
	 *
	 * @param GenericAPI\RESTObject, array $arg
	 * @param boolean $returnAsObject
	 */
	protected static function _resolveObjectGetCall($arg, $returnAsObject)
	{
		if ($returnAsObject || $arg === null) {
			return $arg;
		}
		if (is_array($arg)) {
			return array_map(
				function(RESTObject $obj) { return $obj->toREST(); }, $arg
			);
		}
		elseif (is_object($arg) && $arg instanceof self) {
			return $arg->toREST();
		}
		throw new \InvalidArgumentException(
			"This method's first argument must be a " . __CLASS__ .
			' instance or an array of such instances.'
		);
	}
	
	/**
	 * If no argument is passed, returns a boolean value indicating whether
	 * null properties will be omitted from this instance's RESTful
	 * representation. If an argument is passed, sets this characteristic
	 * according to the argument's boolean value.
	 *
	 * @param boolean $skip = null
	 */
	public function skipNullProperties($skip = null) {
		if ($skip === null) {
			return $this->_skipNullProperties;
		}
		$this->_skipNullProperties = (bool)$skip;
	}
	
	/**
	 * Returns a RESTful representation of this object, suitable for encoding
	 * as JSON or similar.
	 *
	 * @return array
	 */
	public function toREST() {
		return self::_dispatchGetters($this, static::$_GETTER_DISPATCH_MODEL);
	}
}
?>