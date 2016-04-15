<?php
namespace Google\MyBusiness;

class SpecialHoursPeriod extends AbstractAPIResponseObject {
    protected static $_SETTER_DISPATCH_MODEL = array(
        'startDate' => 'setStartDate',
        'endDate' => 'setEndDate',
        'openTime' => 'setOpeningTime',
        'closeTime' => 'setClosingTime',
        'isClosed' => 'isClosed'
    );
    protected static $_GETTER_DISPATCH_MODEL = array();
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
    protected static $_hourFormatter;
    protected $_startDate;
    protected $_endDate;
    protected $_isClosed;
    
    /**
     * @param array $apiData = null
     */
    public function __construct(array $apiData = null) {
        if (!self::$_hourFormatter) {
            self::$_hourFormatter = new \StoreHourFormatter();
        }
        $this->_startDate = new \DateTime('1900-01-01');
        $this->_endDate = new \DateTime('1900-01-01');
        parent::__construct($apiData);
    }
    
    /**
     * Returns a date in a DateTime representation.
     *
     * @param array, DateTime $date
     * @return DateTime
     */
    private static function _normalizeDate($date) {
        if (is_array($date)) {
            if (!isset($date['year']) || !isset($date['month']) ||
                !isset($date['day']))
            {
                throw new InvalidArgumentException(
                    'One or more required properties is missing.'
                );
            }
            return new \DateTime(sprintf(
                '%s-%s-%s',
                $date['year'],
                $date['month'],
                $date['day']
            ));
        }
        if (is_object($date) && $date instanceof \DateTime) {
            return $date;
        }
        throw new InvalidArgumentException(
            'Dates must be passed as arrays or as DateTime instances.'
        );
    }
    
    /**
     * Given a DateTime instance and an integer representation of a time as
     * provided by StoreHourFormatter::parseTime(), sets the time in the
     * DateTime instance.
     */
    private static function _setTime($intTime, \DateTime $date) {
        $date->setTime(floor($intTime / 100), $intTime % 100);
    }
    
    /**
     * @param array, DateTime $startDate
     */
    public function setStartDate($startDate) {
        $startDate = self::_normalizeDate($startDate);
        $this->_startDate->setDate(
            $startDate->format('Y'),
            $startDate->format('n'),
            $startDate->format('j')
        );
    }
    
    /**
     * @param array, DateTime $endDate
     */
    public function setEndDate($endDate) {
        $endDate = self::_normalizeDate($endDate);
        $this->_endDate->setDate(
            $endDate->format('Y'),
            $endDate->format('n'),
            $endDate->format('j')
        );
    }
    
    /**
     * @param string $time
     */
    public function setOpeningTime($time) {
        try {
            self::_setTime(
                self::$_hourFormatter->parseTime($time), $this->_startDate
            );
        } catch (\StoreHourFormatterException $e) {
            throw new UnexpectedValueException(
                'Caught error while parsing time.', null, $e
            );
        }
    }
    
    /**
     * @param string $time
     */
    public function setClosingTime($time) {
        try {
            self::_setTime(
                self::$_hourFormatter->parseTime($time), $this->_endDate
            );
        } catch (\StoreHourFormatterException $e) {
            throw new UnexpectedValueException(
                'Caught error while parsing time.', null, $e
            );
        }
    }
    
    /**
     * Ensures that the date range represented in this instance is valid.
     */
    public function validate() {
        if (!$this->isClosed()) {
            if (!$this->_startDate || !$this->_endDate) {
                throw new LogicException(
                    'Both a start date and an end date must be set.'
                );
            }
            $diff = $this->_endDate->getTimestamp() - $this->_startDate->getTimestamp();
            if ($diff < 1 || $diff > 86400) {
                throw new LogicException(
                    'A special hours period must represent 24 hours or less.'
                );
            }
        }
    }
    
    /**
     * When called with no arguments, returns a boolean value indicating
     * whether this period represents a closure. When called with an argument,
     * sets this property according to the argument's boolean representation.
     *
     * @param boolean $isClosed = null
     * @return boolean
     */
    public function isClosed($isClosed = null) {
        if ($isClosed === null) {
            return $this->_isClosed;
        }
        $this->_isClosed = (bool)$isCLosed;
    }
    
    /**
     * Returns the start date as a string, unless a true argument is passed, in
     * which case it is returned as a DateTime instance.
     *
     * @param boolean $asObject = false
     * @return string, DateTime
     */
    public function getStartDate($asObject = false) {
        return $asObject ? $this->_startDate : $this->_startDate->format('Y-m-d');
    }
    
    /**
     * Returns the end date as a string, unless a true argument is passed, in
     * which case it is returned as a DateTime instance.
     *
     * @param boolean $asObject = false
     * @return string, DateTime
     */
    public function getEndDate($asObject = false) {
        return $asObject ? $this->_endDate : $this->_endDate->format('Y-m-d');
    }
    
    /**
     * @return string
     */
    public function getOpeningTime() {
        return $this->_startDate->format('H:i');
    }
    
    /**
     * @return string
     */
    public function getClosingTime() {
        $timeStr = $this->_endDate->format('H:i');
        return $timeStr == '00:00' ? '24:00' : $timeStr;
    }
}
?>