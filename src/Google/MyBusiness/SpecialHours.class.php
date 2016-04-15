<?php
namespace Google\MyBusiness;

class SpecialHours extends AbstractAPIResponseObject {
    protected static $_SETTER_DISPATCH_MODEL = array(
        'specialHourPeriods' => 'setSpecialHourPeriods'
    );
    protected static $_GETTER_DISPATCH_MODEL = array();
    protected static $_MERGE_DISPATCH_MODELS = true;
    protected static $_dispatchModelReady = false;
    protected $_hourPeriods = array();
    
    /**
     * Sets special hour periods given an array containing either raw array
     * representations of the hour periods or
     * Google\MyBusiness\SpecialHoursPeriod instances.
     *
     * @param array $hourPeriods
     */
    public function setSpecialHourPeriods(array $hourPeriods) {
        $this->_hourPeriods = array();
        foreach ($hourPeriods as $period) {
            if (is_array($period)) {
                $period = new SpecialHoursPeriod($period);
            }
            elseif (!is_object($period) || !($period instanceof SpecialHoursPeriod))
            {
                throw new InvalidArgumentException(
                    'Special hour periods must be passed as arrays or as ' .
                    __NAMESPACE__ . '\SpecialHoursPeriod instances.'
                );
            }
            $this->addSpecialHoursPeriod($period);
        }
    }
    
    /**
     * @param Google\MyBusiness\SpecialHoursPeriod $period
     */
    public function addSpecialHoursPeriod(SpecialHoursPeriod $period) {
        $period->validate();
        $this->_hourPeriods[] = $period;
    }
    
    /**
     * @param boolean $asObject = false
     * @return array
     */
    public function getSpecialHourPeriods($asObject = false) {
        return self::_resolveObjectGetCall($this->_hourPeriods, $asObject);
    }
}
?>