<?php
namespace Google\MyBusiness;

class Location extends AbstractAPIResponseObject {
    const AVAILABILITY_UNSPECIFIED = 1;
    const AVAILABILITY_OPEN = 2;
    const AVAILABILITY_CLOSED_PERMANENTLY = 3;
    protected static $_SETTER_DISPATCH_MODEL = array(
        'name' => 'setName',
        'storeCode' => 'setStoreCode',
        'locationName' => 'setLocationName',
        'address' => array(
            'country' => 'setCountryCode',
            'addressLines' => 'setAddressLines',
            'subLocality' => 'setSubLocality',
            'locality' => 'setCity',
            'administrativeArea' => 'setDistrict',
            'postalCode' => 'setPostalCode'
        ),
        'primaryPhone' => 'setPrimaryPhone',
        'additionalPhones' => 'setAdditionalPhones',
        'primaryCategory' => 'setPrimaryCategory',
        'additionalCategories' => 'setAdditionalCategories',
        'websiteUrl' => 'setURL',
        'regularHours' => 'setHours',
        'specialHours' => 'setSpecialHours',
        'locationKey' => array(
            'plusPageId' => 'setPlusPageID',
            'placeId' => 'setPlaceID'
        ),
        'labels' => 'setLabels',
        'photos' => 'setPhotos',
        'openInfo' => array(
            'status' => 'setAvailability'
        ),
        'locationState' => array(
            'isGoogleUpdated' => 'hasGoogleUpdates',
            'isDuplicate' => 'isDuplicate',
            'isSuspended' => 'isSuspended',
            'canUpdate' => 'isUpdatable',
            'canDelete' => 'isDeletable'
        )
    );
    protected static $_GETTER_DISPATCH_MODEL = array(
        'address' => 'getAddress',
        'name' => null,
        'locationKey' => null,
        'locationState' => null
    );
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
    private static $_constantsByName;
    private static $_constantsByVal;
    protected $_address;
    protected $_additionalPhoneCount;
    protected $_ownerAccountID;
    protected $_storeCode;
    protected $_locationName;
    protected $_primaryCategory;
    protected $_additionalCategories;
    protected $_url;
    protected $_hours;
    protected $_specialHours;
    protected $_plusPageID;
    protected $_placeID;
    protected $_labels = array();
    protected $_photos;
    protected $_availability = self::AVAILABILITY_UNSPECIFIED;
    protected $_hasGoogleUpdates;
    protected $_isDuplicate;
    protected $_isSuspended;
    protected $_isUpdatable;
    protected $_isDeletable;
    protected $_languageCode = 'en-US';
    
    /**
     * @param array $apiData = null
     */
    public function __construct(array $apiData = null) {
        if (!self::$_constantsByName) {
            $r = new \ReflectionClass($this);
            self::$_constantsByName = $r->getConstants();
            self::$_constantsByVal = array_flip(self::$_constantsByName);
        }
        $this->_address = new \PhysicalAddress\Address();
        parent::__construct($apiData);
    }
    
    /**
     * @param array, Google\MyBusiness\Category $primaryCategory
     */
    private static function _normalizeCategory($category) {
        if (is_array($category)) {
            $category = new Category($category);
        }
        elseif (!is_object($category) || !($category instanceof Category)) {
            throw new InvalidArgumentException(
                'Categories must be passed as arrays or as ' . __NAMESPACE__ .
                '\Category instances.'
            );
        }
        else {
            /* For now I'm only enforcing database validation on categories
            when they are passed as objects. */
            $category->validate();
        }
        return $category;
    }
    
    /**
     * @param string $name
     */
    public function setName($name) {
        $errMessage = 'Location names must be in the format '
                    . '"accounts/{account_id}/locations/{location_id}".';
        if (substr($name, 0, 9) != 'accounts/') {
            throw new InvalidArgumentException($errMessage);
        }
        $pos = strpos($name, '/', 9);
        if (substr($name, $pos, 11) != '/locations/') {
            throw new InvalidArgumentException($errMessage);
        }
        $this->setOwnerAccountID(substr($name, 9, $pos - 9));
        $this->setID(substr($name, $pos + 11));
    }
    
    /**
     * @param string $id
     */
    public function setOwnerAccountID($id) {
        $this->_ownerAccountID = $id;
    }
    
    /**
     * @param string $storeCode
     */
    public function setStoreCode($storeCode) {
        $this->_storeCode = (string)$storeCode;
    }
    
    /**
     * @param string $name
     */
    public function setLocationName($name) {
        $this->_locationName = $name;
    }
    
    /**
     * @param PhysicalAddress\Address $address
     */
    public function setAddress(\PhysicalAddress\Address $address) {
        /* Because the phone numbers are stored inside the address instance, if
        there are any already, we need to port them into this new instance. */
        $phone = $this->_address->getPhoneNumber();
        if (strlen($phone)) {
            $address->setPhoneNumber($phone);
        }
        for ($i = 0; $i < $this->_additionalPhoneCount; $i++) {
            $address->setPhoneNumber($this->_address->getPhoneNumber($i), $i);
        }
        $this->_address = $address;
    }
    
    /**
     * @param string $countryCode
     */
    public function setCountryCode($countryCode) {
        $this->_address->setCountryCode($countryCode);
    }
    
    /**
     * @param array $addressLines
     */
    public function setAddressLines(array $addressLines) {
        if (!$addressLines) {
            throw new InvalidArgumentException(
                'Cannot set an empty list of address lines.'
            );
        }
        $this->_address->setAddressLine(array_shift($addressLines));
        if ($addressLines) {
            $this->_address->setAddressLine2(implode(', ', $addressLines));
        }
    }
    
    /**
     * @param string $subLocality
     */
    public function setSubLocality($subLocality) {
        $this->_address->setSubLocality($subLocality);
    }
    
    /**
     * @param string $city
     */
    public function setCity($city) {
        $this->_address->setCity($city);
    }
    
    /**
     * @param string $district
     */
    public function setDistrict($district) {
        /* These will probably tend to be district codes, but they could be
        district names. */
        try {
            $this->_address->setDistrictCode($district);
        } catch (\PhysicalAddress\InvalidArgumentException $e) {
            $this->_address->setDistrict($district);
        }
    }
    
    /**
     * @param string $postalCode
     */
    public function setPostalCode($postalCode) {
        $this->_address->setPostalCode($postalCode);
    }
    
    /**
     * @param string $phone
     */
    public function setPrimaryPhone($phone) {
        $this->_address->setPhoneNumber($phone);
    }
    
    /**
     * @param array $phones
     */
    public function setAdditionalPhones(array $phones) {
        $this->_additionalPhoneCount = count($phones);
        for ($i = 0; $i < $this->_additionalPhoneCount; $i++) {
            $this->_address->setPhoneNumber($phones[$i], $i);
        }
    }
    
    /**
     * @param array, Google\MyBusiness\Category $primaryCategory
     */
    public function setPrimaryCategory($primaryCategory) {
        $this->_primaryCategory = self::_normalizeCategory($primaryCategory);
    }
    
    /**
     * @param array $additionalCategories
     */
    public function setAdditionalCategories(array $additionalCategories) {
        $this->_additionalCategories = array();
        foreach ($additionalCategories as $category) {
            $this->_additionalCategories[] = self::_normalizeCategory($category);
        }
    }
    
    /**
     * @param string, URL $url
     */
    public function setURL($url) {
        $this->_url = \URL::cast($url);
    }
    
    /**
     * @param array, StoreHourFormatter $hours
     */
    public function setHours($hours) {
        if (is_array($hours)) {
            if (!isset($hours['periods'])) {
                throw new InvalidArgumentException(
                    'Could not find "periods" property in array.'
                );
            }
            try {
                $hoursObj = new \StoreHourFormatter(
                    \StoreHourFormatter::FORMAT_GOOGLE_MYBUSINESS_API
                );
                foreach ($hours['periods'] as $period) {
                    if (!isset($period['openDay']) ||
                        !isset($period['closeDay']) ||
                        !isset($period['openTime']) ||
                        !isset($period['closeTime']))
                    {
                        throw new UnexpectedValueException(
                            'Could not find one or more expected fields in ' .
                            'one of the time periods passed.'
                        );
                    }
                    if ($period['openDay'] != $period['closeDay']) {
                        throw new LogicException(
                            'Time periods spanning more than one day are ' .
                            'not supported.'
                        );
                    }
                    $day = $hoursObj->getDayCode($period['openDay']);
                    $hoursObj->setOpeningTime($period['openTime'], $day);
                    $hoursObj->setClosingTime($period['closeTime'], $day);
                }
            } catch (\StoreHourFormatterException $e) {
                throw new UnexpectedValueException(
                    'Caught error while parsing hours.', null, $e
                );
            }
        }
        elseif (is_object($hours) && $hours instanceof \StoreHourFormatter) {
            $hoursObj = $hours;
            $hoursObj->setFormat(
                \StoreHourFormatter::FORMAT_GOOGLE_MYBUSINESS_API
            );
        }
        else {
            throw new InvalidArgumentException(
                'Hours of operation must be passed as an array or as a ' .
                'StoreHourFormatter instance.'
            );
        }
        $this->_hours = $hoursObj;
    }
    
    /**
     * @param array, Google\MyBusiness\SpecialHours $specialHours
     */
    public function setSpecialHours(array $specialHours) {
        if (is_array($specialHours)) {
            $specialHours = new SpecialHours($specialHours);
        }
        elseif (!is_object($specialHours) || !($specialHours instanceof SpecialHours))
        {
            throw new InvalidArgumentException(
                'Special hours must be passed as arrays or as ' .
                __NAMESPACE__ . '\SpecialHours instances.'
            );
        }
        $this->_specialHours = $specialHours;
    }
    
    /**
     * @param string $plusPageID
     */
    public function setPlusPageID($plusPageID) {
        $this->_plusPageID = $plusPageID;
    }
    
    /**
     * @param string $placeID
     */
    public function setPlaceID($placeID) {
        $this->_placeID = $placeID;
    }
    
    /**
     * Sets a list of labels, which are non-user facing free-form tags.
     *
     * @param array $labels
     */
    public function setLabels(array $labels) {
        $this->_labels = array();
        foreach ($labels as $label) {
            $this->addLabel($label);
        }
    }
    
    /**
     * Adds a label.
     *
     * @param string $label
     */
    public function addLabel($label) {
        $this->_labels[] = self::$_validator->string(
            $label,
            'Labels must be strings 255 characters in length or less.',
            \Validator::FILTER_DEFAULT,
            255
        );
    }
    
    /**
     * @param array, Google\MyBusiness\PhotoCollection $photos
     */
    public function setPhotos($photos) {
        if (is_array($photos)) {
            $photos = new PhotoCollection($photos);
        }
        elseif (!is_object($photos) || !($photos instanceof PhotoCollection)) {
            throw new InvalidArgumentException(
                'Photos must be passed as arrays or as ' . __NAMESPACE__ .
                '\PhotoCollection instances.'
            );
        }
        $this->_photos = $photos;
    }
    
    /**
     * @param string, int $availability
     */
    public function setAvailability($availability) {
        if (is_int($availability)) {
            if (!isset(self::$_constantsByVal[$availability])) {
                throw new InvalidArgumentException(
                    'Unrecognized availability constant value.'
                );
            }
            $this->_availability = $availability;
        }
        else {
            if (substr($availability, 0, 18) == 'OPEN_FOR_BUSINESS_') {
                $availability = substr($availability, 18);
            }
            $constName = 'AVAILABILITY_' . $availability;
            if (!isset(self::$_constantsByName[$constName])) {
                throw new InvalidArgumentException(
                    'Unrecognized availability value "' . $availability . '".'
                );
            }
            $this->_availability = self::$_constantsByName[$constName];
        }
    }
    
    /**
     * @param string $languageCode
     */
    public function setLanguageCode($languageCode) {
        $this->_languageCode = self::$_validator->string(
            $languageCode,
            'Language codes must be non-empty strings.',
            \Validator::FILTER_TRIM | \Validator::ASSERT_TRUTH
        );
    }
    
    /**
     * @param boolean $hasUpdates = null
     * @return boolean
     */
    public function hasGoogleUpdates($hasUpdates = null) {
        if ($hasUpdates === null) {
            return $this->_hasGoogleUpdates;
        }
        $this->_hasGoogleUpdates = (bool)$hasUpdates;
    }
    
    /**
     * @param boolean $isDuplicate = null
     * @return boolean
     */
    public function isDuplicate($isDuplicate = null) {
        if ($isDuplicate === null) {
            return $this->_isDuplicate;
        }
        $this->_isDuplicate = (bool)$isDuplicate;
    }
    
    /**
     * @param boolean $isSuspended = null
     * @return boolean
     */
    public function isSuspended($isSuspended = null) {
        if ($isSuspended === null) {
            return $this->_isSuspended;
        }
        $this->_isSuspended = (bool)$isSuspended;
    }
    
    /**
     * @param boolean $isUpdatable = null
     * @return boolean
     */
    public function isUpdatable($isUpdatable = null) {
        if ($isUpdatable === null) {
            return $this->_isUpdatable;
        }
        $this->_isUpdatable = (bool)$isUpdatable;
    }
    
    /**
     * @param boolean $isDeletable = null
     * @return boolean
     */
    public function isDeletable($isDeletable = null) {
        if ($isDeletable === null) {
            return $this->_isDeletable;
        }
        $this->_isDeletable = (bool)$isDeletable;
    }
    
    /**
     * @return string
     */
    public function getName() {
        return 'accounts/' . $this->getOwnerAccountID()
             . '/locations/' . $this->getID();
    }
    
    /**
     * @return string
     */
    public function getOwnerAccountID() {
        return $this->_ownerAccountID;
    }
    
    /**
     * @return string
     */
    public function getStoreCode() {
        return $this->_storeCode;
    }
    
    /**
     * @return string
     */
    public function getLocationName() {
        return $this->_locationName;
    }
    
    /**
     * Returns the address associated with this instance, either in its RESTful
     * form or as the underlying PhysicalAddress\Address object, depending on
     * the argument.
     *
     * @param boolean $asObject = false
     * @return array, PhysicalAddress\Address
     */
    public function getAddress($asObject = false) {
        if ($asObject) {
            return $this->_address;
        }
        $addressLines = array($this->_address->getAddressLine());
        $line2 = $this->_address->getAddressLine2();
        if (strlen($line2)) {
            $addressLines[] = $line2;
        }
        return array(
            'country' => $this->_address->getCountryCode(),
            'addressLines' => $addressLines,
            'subLocality' => $this->_address->getSubLocality(),
            'locality' => $this->_address->getCity(),
            'administrativeArea' => $this->_address->getDistrictCode(),
            'postalCode' => $this->_address->getPostalCode()
        );
    }
    
    /**
     * @return string
     */
    public function getPrimaryPhone() {
        return $this->_address->getPhoneNumber();
    }
    
    /**
     * @return array
     */
    public function getAdditionalPhones() {
        $phones = array();
        for ($i = 0; $i < $this->_additionalPhoneCount; $i++) {
            $phones[] = $this->_address->getPhoneNumber($i);
        }
    }
    
    /**
     * @param boolean $asObject = false
     * @return Google\MyBusiness\Category
     */
    public function getPrimaryCategory($asObject = false) {
        return self::_resolveObjectGetCall($this->_primaryCategory, $asObject);
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getAdditionalCategories($asObject = false) {
        return self::_resolveObjectGetCall(
            $this->_additionalCategories, $asObject
        );
    }
    
    /**
     * @param boolean $asObject = false
     * @return string, URL
     */
    public function getURL($asObject = false) {
        return $asObject ? $this->_url : (string)$this->_url;
    }
    
    /**
     * Returns the location's hours in the format required by Google's API, or
     * as the underlying object if a true argument is passed.
     *
     * @param boolean $asObject = false
     * @return array, StoreHourFormatter
     */
    public function getHours($asObject = false) {
        if ($asObject || !$this->_hours) {
            return $this->_hours;
        }
        $hours = array('periods' => array());
        for ($i = 0; $i < 7; $i++) {
            $day = $this->_hours->getDay($i);
            $opening = $this->_hours->getOpeningTime($i, true);
            if ($opening === null) {
                // I'm guessing this is how you represent being closed
                continue;
            }
            $hours['periods'][] = array(
                'openDay' => $day,
                'closeDay' => $day,
                'openTime' => $opening,
                'closeTime' => $this->_hours->getClosingTime($i, true)
            );
        }
        return $hours;
    }
    
    /**
     * @param boolean $asObject = false
     * @return array, Google\MyBusiness\SpecialHours
     */
    public function getSpecialHours($asObject = false) {
        return self::_resolveObjectGetCall($this->_specialHours, $asObject);
    }
    
    /**
     * @return string
     */
    public function getPlusPageID() {
        return $this->_plusPageID;
    }
    
    /**
     * @return string
     */
    public function getPlaceID() {
        return $this->_placeID;
    }
    
    /**
     * @return array
     */
    public function getLabels() {
        return $this->_labels;
    }
    
    /**
     * @param boolean $asObject = false
     * @return array, Google\MyBusiness\PhotoCollection
     */
    public function getPhotos($asObject = false) {
        return self::_resolveObjectGetCall($this->_photos, $asObject);
    }
    
    /**
     * @param boolean $asConst = false
     * @return string, int
     */
    public function getAvailability($asConst = false) {
        if ($asConst) {
            return $this->_availability;
        }
        if ($this->_availability == self::AVAILABILITY_UNSPECIFIED) {
            return 'OPEN_FOR_BUSINESS_UNSPECIFIED';
        }
        return substr(self::$_constantsByVal[$this->_availability], 13);
    }
    
    /**
     * @return string
     */
    public function getLanguageCode() {
        return $this->_languageCode;
    }
    
    /**
     * Returns an MD5 hash that uniquely identifies this location for the
     * purposes of calling the Google My Business API's locations.create
     * endpoint.
     *
     * @return string
     */
    public function getHash() {
        return md5(implode('|', array(
            $this->_address->getHash(),
            $this->_storeCode,
            $this->_locationName,
            $this->_url
        )));
    }
}
?>