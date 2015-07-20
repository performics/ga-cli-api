<?php
namespace DataStructures;

class SerializableFixedArray implements \Iterator, \ArrayAccess, \Countable {
    /* This class exists strictly to bridge a gap between PHP 5.3, which I am
    using as I write this, and later verisons, to which I am working to be able
    to upgrade. SplFixedArray objects cannot be deserialized correctly in PHP
    versions below 5.5. This class may not be instantiated directly; instead,
    objects should be produced via the factory() and fromArray() static
    methods. Where possible, these will return native SplFixedArray objects; in
    incompatible environments they will return instances of this class, which
    implements the same methods but uses a plain PHP array for the underlying
    storage. */
    private static $_useSpl;
    private $_array;
    private $_size;
    private $_index;
    
    /**
     * @param int $size
     * @param array $data = null
     */
    private function __construct($size, array $data = null) {
        /* I'm not double-checking that $size matches count($data), so get it
        right! */
        $this->_size = $size;
        if ($size) {
            $this->_index = 0;
        }
        if ($data) {
            $this->_array = $data;
        }
        else {
            $this->_array = $size ? array_fill(0, $size, null) : array();
        }
    }
    
    private static function _checkEnvironment() {
        self::$_useSpl = method_exists('SplFixedArray', '__wakeup');
    }
    
    /**
     * @param int $size = 0
     * @return SplFixedArray, DataStructures\SerializableFixedArray
     */
    public static function factory($size = 0) {
        if (self::$_useSpl === null) {
            self::_checkEnvironment();
        }
        return self::$_useSpl ? new \SplFixedArray($size) : new self($size);
    }
    
    /**
     * @param array $array
     * @param boolean $save_indexes = true
     * @return SplFixedArray, DataStructures\SerializableFixedArray
     */
    public static function fromArray(array $array, $save_indexes = true) {
        if (self::$_useSpl === null) {
            self::_checkEnvironment();
        }
        return self::$_useSpl ?
            \SplFixedArray::fromArray($array, $save_indexes) :
            new self(
                count($array), $save_indexes ? $array : array_values($array)
            );
    }
    
    /**
     * @return int
     */
    public function getSize() {
        return $this->_size;
    }
    
    /**
     * @param int $size
     */
    public function setSize($size) {
        if ($size) {
            $this->_index = 0;
            if ($this->_size < $size) {
                $this->_array = array_merge(
                    $this->_array, array_fill(0, $size - $this->_size, null)
                );
            }
            elseif ($this->_size > $size) {
                array_splice($this->_array, $size);
            }
        }
        $this->_size = $size;
    }
    
    /**
     * @return array
     */
    public function toArray() {
        return $this->_array;
    }
    
    /* Iterator methods */
    
    public function current() {
        return $this->_array[$this->_index];
    }
    
    public function key() {
        return $this->_index;
    }
    
    public function next() {
        $this->_index++;
    }
    
    public function rewind() {
        $this->_index = $this->_array ? 0 : null;
    }
    
    public function valid() {
        return $this->_index < $this->_size;
    }
    
    /* ArrayAccess methods */
    
    /**
     * @param int $offset
     */
    public function offsetExists($offset) {
        return isset($this->_array[$offset]);
    }
    
    /**
     * @param int $offset
     * @return mixed
     */
    public function offsetGet($offset) {
        if (!is_int($offset) || $offset >= $this->_size) {
            throw new \RuntimeException('Index invalid or out of range');
        }
        return $this->_array[$offset];
    }
    
    /**
     * @param int $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        if (!is_int($offset) || $offset >= $this->_size) {
            throw new \RuntimeException('Index invalid or out of range');
        }
        $this->_array[$offset] = $value;
    }
    
    /**
     * @param int $offset
     */
    public function offsetUnset($offset) {
        if (!is_int($offset) || $offset >= $this->_size) {
            throw new \RuntimeException('Index invalid or out of range');
        }
        $this->_array[$offset] = null;
    }
    
    /* Countable methods */
    
    public function count() {
        return $this->_size;
    }
}
?>