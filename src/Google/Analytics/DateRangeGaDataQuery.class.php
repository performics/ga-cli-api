<?php
namespace Google\Analytics;

class DateRangeGaDataQuery extends IterativeGaDataQuery {
	protected static $_SETTER_DISPATCH_MODEL = array();
	protected static $_GETTER_DISPATCH_MODEL = array();
	protected static $_MERGE_DISPATCH_MODELS = true;
	protected $_rangeStartDate;
	protected $_rangeEndDate;
	protected $_iterationReady = false;
	protected $_iterationInterval;
	
	/**
	 * Creates a new instance with the given start date, end date, and
	 * iteration interval.
	 *
	 * @param array $apiData = null
	 * @param string, int, DateTime $startDate = null
	 * @param string, int, DateTime $endDate = null
	 * @param DateInterval $interval = null
	 */
	public function __construct(
		array $apiData = null,
		$startDate = null,
		$endDate = null,
		\DateInterval $interval = null
	) {
		parent::__construct($apiData);
		if ($startDate) {
			$this->setSummaryStartDate($startDate);
		}
		if ($endDate) {
			$this->setSummaryEndDate($endDate);
		}
		if ($interval) {
			$this->setIterationInterval($interval);
		}
	}
	
	/**
	 * Sets the values of the $this->_startDate and $this->_endDate properties
	 * based on the value of $this->_rangeStartDate. This should only be used
	 * by $this->getAsArray() and $this->reset(); $this->iterate() will work by
	 * adding the date interval to both $this->_startDate and $this->_endDate.
	 */
	private function _setCurrent() {
		if (!$this->_rangeStartDate || !$this->_rangeEndDate ||
		    !$this->_iterationInterval)
		{
			throw new InvalidArgumentException(
				'Queries to be iterated across a date range must have a ' .
				'start date, an end date, and an iteration interval.'
			);
		}
		$this->_startDate = clone $this->_rangeStartDate;
		$date = clone $this->_startDate;
		$date->add($this->_iterationInterval);
		$date->sub(new \DateInterval('P1D'));
		$this->_endDate = $date;
		$this->_iterationReady = true;
	}
	
	/**
	 * @return boolean
	 */
	public function iterate() {
		$this->_startDate->add($this->_iterationInterval);
		$this->_endDate = clone $this->_startDate;
		$this->_endDate->add($this->_iterationInterval);
		$this->_endDate->sub(new \DateInterval('P1D'));
		if ($this->_startDate <= $this->_rangeEndDate) {
			// We're doing at least one more iteration, but is it partial?
			if ($this->_endDate > $this->_rangeEndDate) {
				$this->_endDate = clone $this->_rangeEndDate;
			}
			return true;
		}
		return false;
	}
	
	/**
	 * @return void
	 */
	public function reset() {
		$this->_setCurrent();
	}
	
	/**
	 * @param string, int, DateTime $date
	 */
	public function setSummaryStartDate($date) {
		$this->_rangeStartDate = self::_castToDateTime($date);
		$this->_iterationReady = false;
	}
	
	/**
	 * @param string, int, DateTime $date
	 */
	public function setSummaryEndDate($date) {
		$this->_rangeEndDate = self::_castToDateTime($date);
		$this->_iterationReady = false;
	}
	
	/**
	 * @param DateInterval $interval
	 */
	public function setIterationInterval(\DateInterval $interval) {
		$this->_iterationInterval = $interval;
		$this->_iterationReady = false;
	}
	
	/**
	 * @return DateTime
	 */
	public function getSummaryStartDate() {
		return $this->_rangeStartDate;
	}
	
	/**
	 * @return DateTime
	 */
	public function getSummaryEndDate() {
		return $this->_rangeEndDate;
	}
	
	/**
	 * @return DateInterval
	 */
	public function getIterationInterval() {
		return $this->_iterationInterval;
	}
	
	/**
	 * Handles deferred initialization of current iteration before returning
	 * the query's array representation.
	 * 
	 * @return array
	 */
	public function getAsArray() {
		if (!$this->_iterationReady) {
			$this->_setCurrent();
		}
		return parent::getAsArray();
	}
}
?>