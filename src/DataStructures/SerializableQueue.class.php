<?php
namespace DataStructures;

class SerializableQueue extends \SplQueue implements \Serializable {
    /* Like DataStructures\SerializableFixedArray, this class bridges a gap in
    functionality between PHP 5.3, with which I'm currently working, and a
    later version (5.4 in this case), and it provides a factory method with
    which to work with this class. Unlike in that case, this is a little easier
    because I can simply extend the native class and add the missing methods.
    */
    private static $_useSpl;
    
    private static function _checkEnvironment() {
        self::$_useSpl = is_subclass_of('SplQueue', 'Serializable');
    }
    
    /**
     * @return SplQueue, SerializableQueue
     */
    public static function factory() {
        if (self::$_useSpl === null) {
            self::_checkEnvironment();
        }
        return self::$_useSpl ? new \SplQueue() : new self();
    }
    
    /**
     * @return string
     */
    public function serialize() {
        $data = array();
        $len = count($this);
        for ($i = 0; $i < $len; $i++) {
            $data[] = $this[$i];
        }
        return serialize($data);
    }
    
    /**
     * @param string $data
     */
    public function unserialize($data) {
        $data = unserialize($data);
        foreach ($data as $val) {
            $this->enqueue($val);
        }
    }
}
?>