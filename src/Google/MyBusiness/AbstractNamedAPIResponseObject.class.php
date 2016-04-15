<?php
namespace Google\MyBusiness;

abstract class AbstractNamedAPIResponseObject extends AbstractAPIResponseObject {
    protected static $_SETTER_DISPATCH_MODEL = array(
        'name' => 'setName'
    );
    protected static $_GETTER_DISPATCH_MODEL = array();
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
    protected $_name;
    
    /**
     * @param string $name
     */
    public function setName($name) {
        $this->_name = $name;
    }
    
    /**
     * @return string
     */
    public function getName() {
        return $this->_name;
    }
}
?>