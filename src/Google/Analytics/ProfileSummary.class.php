<?php
namespace Google\Analytics;

class ProfileSummary extends AbstractNamedAPIResponseObject {
	protected static $_SETTER_DISPATCH_MODEL = array(
		'type' => 'setType'
	);
	protected static $_GETTER_DISPATCH_MODEL = array();
	protected static $_MERGE_DISPATCH_MODELS = true;
	protected static $_dispatchModelReady = false;
	protected $_type;
	
	/**
	 * @param string $type
	 */
	public function setType($type) {
		$this->_type = $type;
	}
	
	/**
	 * @return string
	 */
	public function getType() {
		return $this->_type;
	}
}
?>