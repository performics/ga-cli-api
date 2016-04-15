<?php
namespace Google\MyBusiness;

class Category extends AbstractNamedAPIResponseObject {
    protected static $_SETTER_DISPATCH_MODEL = array(
        'categoryId' => 'setID'
    );
    protected static $_GETTER_DISPATCH_MODEL = array();
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
    protected $_validated = false;
    
    public function __toString() {
        return $this->getID();
    }
    
    /**
     * @param string $id
     */
    public function setID($id) {
        if (substr($id, 0, 5) != 'gcid:') {
            $id = 'gcid:' . $id;
        }
        parent::setID($id);
    }
    
    /**
     * Runs database validation on this instance (if necessary).
     */
    public function validate() {
        if ($this->_validated) {
            return;
        }
        API::validateCategory($this);
        $this->_validated = true;
    }
}
?>