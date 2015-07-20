<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class GaDataQueryCollection implements IQuery, \Countable {
    protected $_name;
    protected $_queries = array();
    protected $_queryCount;
    protected $_index = 0;
    
    /**
     * Initializes a collection of queries.
     *
     * @param Google\Analytics\GaDataQuery...
     */
    public function __construct() {
        $args = func_get_args();
        if (!$args) {
            throw new InvalidArgumentException(
                'Google Analytics API query collections must have at least ' .
                'one query instance.'
            );
        }
        foreach ($args as $arg) {
            if (!is_object($arg) || !($arg instanceof GaDataQuery)) {
                throw new InvalidArgumentException(
                    'Google Analytics API query collections must be ' .
                    'composed entirely of Google\Analytics\GaDataQuery ' .
                    'objects.'
                );
            }
        }
        $this->_queries = $args;
        $this->_queryCount = count($this->_queries);
    }
    
    /**
     * @param string $name
     */
    public function setName($name) {
        $this->_name = $name;
    }
    
    /**
     * @return string
     */
    public function getEmailSubject() {
        if ($this->_name) {
            return sprintf(
                'Google Analytics report collection "%s"', $this->_name
            );
        }
        return 'Google Analytics report collection';
    }
    
    /**
     * @return array
     */
    public function getQueries() {
        return $this->_queries;
    }
    
    /**
     * @return string
     */
    public function getName() {
        return $this->_name;
    }
    
    /**
     * @return string
     */
    public function iteration() {
        return $this->current()->getEmailSubject();
    }
    
    /**
     * @return Google\Analytics\GaDataQuery
     */
    public function current() {
        return $this->_queries[$this->_index];
    }
	
	/**
	 * @return int
	 */
	public function key() {
		return $this->_index;
	}
	
	public function next() {
		$this->_index++;
	}
	
	public function rewind() {
		$this->_index = 0;
	}
    
    /**
     * @return boolean
     */
    public function valid() {
        return $this->_index < $this->_queryCount;
    }
    
    public function count() {
        return $this->_queryCount;
    }
}
?>