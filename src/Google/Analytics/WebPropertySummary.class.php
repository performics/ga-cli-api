<?php
namespace Google\Analytics;

class WebPropertySummary extends AbstractNamedAPIResponseObject {
	protected static $_SUPPLEMENTAL_SETTER_DISPATCH_MODEL = array(
		'level' => 'setLevel',
		'websiteUrl' => 'setURL',
		'profiles' => 'setProfileSummaries'
	);
	protected $_level;
	protected $_url;
	protected $_profileSummariesByName = array();
	protected $_profileSummariesByID = array();
	
	public function __construct(array $apiData = null) {
		if (!isset(static::$_SETTER_DISPATCH_MODEL[
			key(self::$_SUPPLEMENTAL_SETTER_DISPATCH_MODEL)
		])) {
			static::$_SETTER_DISPATCH_MODEL = array_merge(
				static::$_SETTER_DISPATCH_MODEL,
				self::$_SUPPLEMENTAL_SETTER_DISPATCH_MODEL
			);
		}
		parent::__construct($apiData);
	}
	
	/**
	 * @param string $level
	 */
	public function setLevel($level) {
		$this->_level = $level;
	}
	
	/**
	 * @param string, URL $url
	 */
	public function setURL($url) {
		$this->_url = \URL::cast($url);
	}
	
	/**
	 * @param array $profiles
	 */
	public function setProfileSummaries($profiles) {
		foreach ($profiles as $profile) {
			if (is_array($profile)) {
				$profile = \Google\APIResponseObjectFactory::create(
					$profile
				);
			}
			$this->_profileSummariesByName[$profile->getName()] =
				$this->_profileSummariesByID[$profile->getID()] = $profile;
		}
	}
	
	/**
	 * @return string
	 */
	public function getLevel() {
		return $this->_level;
	}
	
	/**
	 * @return URL
	 */
	public function getURL() {
		return $this->_url;
	}
	
	/**
	 * @return array
	 */
	public function getProfileSummaries() {
		return array_values($this->_profileSummariesByID);
	}
	
	/**
	 * @param string $name
	 * @return Google\Analytics\ProfileSummary
	 */
	public function getProfileSummaryByName($name) {
		if (isset($this->_profileSummariesByName[$name])) {
			return $this->_profileSummariesByName[$name];
		}
		throw new InvalidArgumentException(
			'Unrecognized profile "' . $name . '".'
		);
	}
	
	/**
	 * @param string $id
	 * @return Google\Analytics\ProfileSummary
	 */
	public function getProfileSummaryByID($id) {
		if (isset($this->_profileSummariesByID[$id])) {
			return $this->_profileSummariesByID[$id];
		}
 		throw new InvalidArgumentException(
			'Unrecognized profile ID "' . $id . '".'
		);
	}
}
?>