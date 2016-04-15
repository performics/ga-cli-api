<?php
namespace Google\MyBusiness;

class PhotoCollection extends AbstractAPIResponseObject {
    protected static $_SETTER_DISPATCH_MODEL = array(
        'profilePhotoUrl' => 'setProfilePhotoURL',
        'coverPhotoUrl' => 'setCoverPhotoURL',
        'logoPhotoUrl' => 'setLogoPhotoURL',
        'exteriorPhotoUrls' => 'setExteriorPhotoURLs',
        'interiorPhotoUrls' => 'setInteriorPhotoURLs',
        'productPhotoUrls' => 'setProductPhotoURLs',
        'photosAtWorkUrls' => 'setPhotosAtWorkURLs',
        'foodAndDrinkPhotoUrls' => 'setFoodAndDrinkPhotoURLs',
        'menuPhotoUrls' => 'setMenuPhotoURLs',
        'commonAreasPhotoUrls' => 'setCommonAreasPhotoURLs',
        'roomsPhotoUrls' => 'setRoomsPhotoURLs',
        'teamPhotoUrls' => 'setTeamPhotoURLs',
        'additionalPhotoUrls' => 'setAdditionalPhotoURLs'
    );
    protected static $_GETTER_DISPATCH_MODEL = array();
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
    protected $_profilePhotoURL;
    protected $_coverPhotoURL;
    protected $_logoPhotoURL;
    protected $_photoGroupURLs = array();
    
    /**
     * Returns a key that can be used to find a certain group of URLs in
     * an instance's $_photoGroupURLs property. It's just a setter or a getter
     * name with the first three characters removed.
     *
     * @param string $methodName
     * @return string
     */
    private static function _getGroupURLKey($methodName) {
        return substr($methodName, 3);
    }
    
    /**
     * Ensures that an array of URLs is represented as URL object instances.
     *
     * @param array $urls
     * @return array
     */
    private static function _castURLs(array $urls) {
        return array_map(function($url) { return \URL::cast($url); }, $urls);
    }
    
    /**
     * @param string, URL $url
     */
    public function setProfilePhotoURL($url) {
        $this->_profilePhotoURL = \URL::cast($url);
    }
    
    /**
     * @param string, URL $url
     */
    public function setCoverPhotoURL($url) {
        $this->_coverPhotoURL = \URL::cast($url);
    }
    
    /**
     * @param string, URL $url
     */
    public function setLogoPhotoURL($url) {
        $this->_logoPhotoURL = \URL::cast($url);
    }
    
    /**
     * @param array $urls
     */
    public function setExteriorPhotoURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param array $urls
     */
    public function setInteriorPhotoURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param array $urls
     */
    public function setProductPhotoURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param array $urls
     */
    public function setPhotosAtWorkURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param array $urls
     */
    public function setFoodAndDrinkPhotoURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param array $urls
     */
    public function setMenuPhotoURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param array $urls
     */
    public function setCommonAreasPhotoURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param array $urls
     */
    public function setRoomsPhotoURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param array $urls
     */
    public function setTeamPhotoURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param array $urls
     */
    public function setAdditionalPhotoURLs(array $urls) {
        $this->_photoGroupURLs[self::_getGroupURLKey(__FUNCTION__)] = self::_castURLs($urls);
    }
    
    /**
     * @param boolean $asObject = false
     * @return string, URL
     */
    public function getProfilePhotoURL($asObject = false) {
        return $asObject ? $this->_profilePhotoURL : (string)$this->_profilePhotoURL;
    }
    
    /**
     * @param boolean $asObject = false
     * @return string, URL
     */
    public function getCoverPhotoURL($asObject = false) {
        return $asObject ? $this->_coverPhotoURL : (string)$this->_coverPhotoURL;
    }
    
    /**
     * @param boolean $asObject = false
     * @return string, URL
     */
    public function getLogoPhotoURL($asObject = false) {
        return $asObject ? $this->_logoPhotoURL : (string)$this->_logoPhotoURL;
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getExteriorPhotoURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getInteriorPhotoURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getProductPhotoURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getPhotosAtWorkURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getFoodAndDrinkPhotoURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getMenuPhotoURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getCommonAreasPhotoURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getRoomsPhotoURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getTeamPhotoURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getAdditionalPhotoURLs($asObject = false) {
        $key = self::_getGroupURLKey(__FUNCTION__);
        if (isset($this->_photoGroupURLs[$key])) {
            return $asObject ? $this->_photoGroupURLs[$key] : array_map(
                function(\URL $url) { return (string)$url; },
                $this->_photoGroupURLs[$key]
            );
        }
    }
}
?>