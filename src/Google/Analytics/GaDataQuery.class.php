<?php
namespace Google\Analytics;

class GaDataQuery extends AbstractNamedAPIResponseObject implements IQuery {
	const SAMPLING_LEVEL_DEFAULT = 1;
	const SAMPLING_LEVEL_FASTER = 2;
	const SAMPLING_LEVEL_HIGHER_PRECISION = 3;
	/* This is a special case in that it does not correspond to a sampling
	level that is available via the Google API. */
	const SAMPLING_LEVEL_NONE = 4;
	/* These constants are provided as convenient shortcuts for commonly-needed
	date ranges (e.g. last full month). */
	const THIS_WEEK_START = 100;
	const THIS_WEEK_END = 101;
	const LAST_WEEK_START = 102;
	const LAST_WEEK_END = 103;
	const THIS_ISO_WEEK_START = 104;
	const THIS_ISO_WEEK_END = 105;
	const LAST_ISO_WEEK_START = 106;
	const LAST_ISO_WEEK_END = 107;
	const THIS_MONTH_START = 108;
	const THIS_MONTH_END = 109;
	const LAST_MONTH_START = 110;
	const LAST_MONTH_END = 111;
	const THIS_YEAR_START = 112;
	const THIS_YEAR_END = 113;
	const LAST_YEAR_START = 114;
	const LAST_YEAR_END = 115;
	/* These variants are what we would get with any the corresponding
	constants above, but with a year subtracted. */
	const THIS_WEEK_START_YOY = 200;
	const THIS_WEEK_END_YOY = 201;
	const LAST_WEEK_START_YOY = 302;
	const LAST_WEEK_END_YOY = 203;
	const THIS_ISO_WEEK_START_YOY = 204;
	const THIS_ISO_WEEK_END_YOY = 205;
	const LAST_ISO_WEEK_START_YOY = 206;
	const LAST_ISO_WEEK_END_YOY = 207;
	const THIS_MONTH_START_YOY = 208;
	const THIS_MONTH_END_YOY = 209;
	const LAST_MONTH_START_YOY = 210;
	const LAST_MONTH_END_YOY = 211;
	const THIS_YEAR_START_YOY = 212;
	const THIS_YEAR_END_YOY = 213;
	const LAST_YEAR_START_YOY = 214;
	const LAST_YEAR_END_YOY = 215;
	private static $_SETTINGS = array(
		'GOOGLE_ANALYTICS_API_PAGE_SIZE' => 500
	);
	private static $_SETTING_TESTS = array(
		'GOOGLE_ANALYTICS_API_PAGE_SIZE' => 'integer'
	);
	private static $_constantsByName = array();
	private static $_constantsByVal = array();
	protected static $_SETTER_DISPATCH_MODEL = array(
		'ids' => 'setProfile',
		'start-date' => 'setStartDate',
		'end-date' => 'setEndDate',
		'metrics' => 'setMetrics',
		'dimensions' => 'setDimensions',
		'sort' => 'setSort',
		'filters' => 'setFilter',
		'segment' => 'setSegment',
		'samplingLevel' => 'setSamplingLevel',
		'start-index' => 'setStartIndex',
		'max-results' => 'setMaxResults'
	);
	protected static $_GETTER_DISPATCH_MODEL = array();
	protected static $_MERGE_DISPATCH_MODELS = true;
	protected static $_dispatchModelReady = false;
	protected $_api;
	protected $_profile;
	/* Since we lazily load the profile object, we need separate properties to
	cache the ID or name. */
	protected $_profileID;
	protected $_profileName;
	protected $_startDate;
	protected $_endDate;
	protected $_metrics;
	protected $_dimensions;
	protected $_sort;
	protected $_filter;
	protected $_segment;
	protected $_samplingLevel;
	protected $_startIndex = 1;
	protected $_maxResults;
	protected $_totalResults;
	// This property is for satisfying the Iterator requirements
	protected $_index = 0;
	// This is used by $this->iteration() when formatting the start date
	protected $_formatString = 'Y-m-d';	
	
	/**
	 * @param array $apiData = null
	 */
	public function __construct(array $apiData = null) {
		if (!self::$_constantsByName) {
			self::_initStaticProperties();
		}
		parent::__construct($apiData);
	}
	
	private static function _initStaticProperties() {
		\PFXUtils::validateSettings(self::$_SETTINGS, self::$_SETTING_TESTS);
		$reflector = new \ReflectionClass(__CLASS__);
		self::$_constantsByName = $reflector->getConstants();
		self::$_constantsByVal = array_flip(self::$_constantsByName);
	}
	
	/**
	 * Returns a DateTime representation of the argument, if necessary.
	 *
	 * @param string, int, DateTime $date
	 */
	protected static function _castToDateTime($date) {
		if ($date instanceof \DateTime) {
			return $date;
		}
		if (is_int($date)) {
			$date = self::$_validator->number(
				$date, null, \Validator::ASSERT_INT_DEFAULT
			);
			if (!isset(self::$_constantsByVal[$date])) {
				throw new UnexpectedValueException(
					'Encountered unrecognized date constant.'
				);
			}
			$const = self::$_constantsByVal[$date];
			if (substr($const, -4) == '_YOY') {
				$yearOverYear = true;
				$const = substr($const, 0, strlen($const) - 4);
			}
			else {
				$yearOverYear = false;
			}
			$frame = substr($const, 0, 4);
			if ($frame != 'THIS' && $frame != 'LAST') {
				throw new UnexpectedValueException(
					'Encountered unrecognized date constant.'
				);
			}
			$period = substr($const, 5);
			$pos = strrpos($period, '_');
			$orientation = substr($period, $pos + 1);
			$period = substr($period, 0, $pos);
			$date = new \DateTime();
			if ($yearOverYear) {
				$date->sub(new \DateInterval('P1Y'));
			}
			/* If we're trying to do this by the week, we'll need to normalize
			the current date to the beginning of the week first. */
			if (strpos($period, 'WEEK') !== false) {
				$interval = new \DateInterval('P7D');
				$formatStr = 'Y-m-d';
				$date->sub(
					new \DateInterval('P' . $date->format('w') . 'D')
				);
				if ($period == 'ISO_WEEK') {
					// Starts on Monday
					$date->add(new \DateInterval('P1D'));
				}
			}
			else {
				if ($period == 'YEAR') {
					$interval = new \DateInterval('P1Y');
					$formatStr = 'Y-01-01';
				}
				else {
					$interval = new \DateInterval('P1M');
					$formatStr = 'Y-m-01';
				}
			}
			if ($orientation == 'START') {
				/* For the start of the period, subtract if necessary,
				format, recast as a DateTime instance, and return. */
				if ($frame == 'LAST') {
					$date->sub($interval);
				}
				return new \DateTime($date->format($formatStr));
			}
			/* Otherwise we want the end of the period, which we can get to
			by formatting and subtracting a day (after correcting for the
			frame of reference if necessary). */
			$newDate = new \DateTime($date->format($formatStr));
			if ($frame == 'THIS') {
				$newDate->add($interval);
			}
			$newDate->sub(new \DateInterval('P1D'));
			return $newDate;
		}
		try {
			return new \DateTime($date);
		} catch (\Exception $e) {
			throw new InvalidArgumentException(
				'Caught error while parsing datetime string "' . $date . '".',
				null,
				$e
			);
		}
	}
	
	/* Iterator methods. They are basically no-ops in this context but they are
	implemented in order to make it simpler to iterate subclasses such as
	Google/Analytics/GaDataQueryCollection. */
	
	/**
	 * @return Google\Analytics\GaDataQuery
	 */
	public function current() {
		return $this;
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
		return $this->_index == 0;
	}
	
	/**
	 * @return string
	 */
	public function iteration() {
		return $this->_startDate->format($this->_formatString);
	}
	
	/**
	 * Sets the Google Analytics profile from which the query will pull its
	 * data. This may be passed either as a numeric profile ID or as a
	 * Google\Analytics\ProfileSummary object.
	 *
	 * @param Google\Analytics\ProfileSummary, string $profile
	 */
	public function setProfile($profile) {
		// Clear out any existing profile properties first
		$this->_profile = null;
		$this->_profileID = null;
		$this->_profileName = null;
		if (is_string($profile)) {
			$this->_profileID = $profile;
		}
		elseif (is_object($profile) && $profile instanceof ProfileSummary) {
			$this->_profile = $profile;
		}
		else {
			throw new InvalidArgumentException(
				'Profiles must be passed either as raw IDs or as ' .
				'Google\Analytics\ProfileSummary instances.'
			);
		}
	}
	
	/**
	 * @param string $profileName
	 */
	public function setProfileName($profileName) {
		$this->_profileName = $profileName;
		$this->_profileID = null;
		$this->_profile = null;
	}
	
	/**
	 * Sets the query's start date, expressed as a string, one of the date
	 * shortcut constants defined in the Google\Analytics\GaDataQuery class, or
	 * a DateTime instance.
	 *
	 * @param string, int, DateTime $startDate
	 */
	public function setStartDate($startDate) {
		$this->_startDate = self::_castToDateTime($startDate);
	}
	
	/**
	 * Sets the query's end date, expressed as a string, one of the date
	 * shortcut constants defined in the Google\Analytics\GaDataQuery class, or
	 * a DateTime instance.
	 *
	 * @param string, int, DateTime $endDate
	 */
	public function setEndDate($endDate) {
		$this->_endDate = self::_castToDateTime($endDate);
	}
	
	/**
	 * Sets the metrics to be used in the query, either as a comma-delimited
	 * string or as an array.
	 *
	 * @param string, array $metrics
	 */
	public function setMetrics($metrics) {
		if (is_string($metrics)) {
			$metrics = explode(',', $metrics);
		}
		$this->_metrics = API::addPrefix($metrics);
	}
	
	/**
	 * Sets the dimensions to be used in the query, either as a comma-delimited
	 * string or as an array.
	 *
	 * @param string, array $dimensions
	 */
	public function setDimensions($dimensions) {
		if (is_string($dimensions)) {
			$dimensions = explode(',', $dimensions);
		}
		$this->_dimensions = API::addPrefix($dimensions);
	}
	
	/**
	 * Sets the sort order for the data to be returned from the query, either
	 * as a formatted (comma-delimited) string, an array of sort order
	 * component strings, or a Google\Analytics\GaDataSort instance.
	 *
	 * @param string, array, Google\Analytics\GaDataSortOrder $sort
	 */
	public function setSort($sort) {
		if (is_string($sort)) {
			$sort = GaDataSortOrder::createFromString($sort);
		}
		elseif (is_array($sort)) {
			$sort = GaDataSortOrder::createFromString(implode(',', $sort));
		}
		elseif (!is_object($sort) || !($sort instanceof GaDataSortOrder)) {
			throw new InvalidArgumentException(
				'Sort orders must be provided as formatted strings, arrays, ' .
				'or ' . __NAMESPACE__ . '\GaDataSortOrder instances.'
			);
		}
		$this->_sort = $sort;
	}
	
	/**
	 * Sets a filter to be imposed on the query, either as a string formatted
	 * as described in Google's API documentation
	 * (https://developers.google.com/analytics/devguides/reporting/core/v3/reference#filters)
	 * or as a Google\Analytics\GaDataFilterCollection instance.
	 *
	 * @param string, Google\Analytics\GaDataFilterCollection $filter
	 */
	public function setFilter($filter) {
		if (is_string($filter)) {
			$filter = GaDataFilterCollection::createFromString($filter);
		}
		elseif (!is_object($filter) ||
		    !($filter instanceof GaDataFilterCollection)
		) {
			throw new InvalidArgumentException(
				'Filters must be provided as formatted strings or as ' .
				__NAMESPACE__ . '\GaDataFilterCollection instances.'
			);
		}
		$this->_filter = $filter;
	}
	
	/**
	 * Sets a segment from which to pull the data for this query, either as a
	 * string formatted as described in Google's Segments Dev Guide
	 * (https://developers.google.com/analytics/devguides/reporting/core/v3/segments)
	 * or as a Google\Analytics\GaDataSegment instance.
	 *
	 * @param int, string, Google\Analytics\GaDataSegment $segment
	 */
	public function setSegment($segment) {
		if (filter_var($segment, FILTER_VALIDATE_INT) !== false) {
			$this->_segment = 'gaid::' . $segment;
		}
		elseif (is_string($segment)) {
			$this->_segment = GaDataSegment::createFromString($segment);
		}
		elseif (is_object($segment) && $segment instanceof GaDataSegment) {
			$this->_segment = $segment;
		}
		else {
			throw new InvalidArgumentException(
				'Segments must be provided as numeric IDs, formatted ' .
				'strings, or ' . __NAMESPACE__ . '\GaDataSegment instances.'
			);
		}
	}
	
	/**
	 * Sets the sampling level for the query, either as one of the
	 * Google\Analytics\GaDataQuery::SAMPLING_LEVEL_* constants or its all-caps
	 * string equivalent.
	 *
	 * @param string, int $samplingLevel
	 */
	public function setSamplingLevel($samplingLevel) {
		if (is_string($samplingLevel)) {
			$constName = 'SAMPLING_LEVEL_' . $samplingLevel;
			if (isset(self::$_constantsByName[$constName])) {
				$this->_samplingLevel = self::$_constantsByName[$constName];
			}
			else {
				throw new InvalidArgumentException(
					'Invalid sampling level "' . $samplingLevel . '".'
				);
			}
		}
		elseif (isset(self::$_constantsByVal[$samplingLevel])) {
			$this->_samplingLevel = $samplingLevel;
		}
		else {
			throw new InvalidArgumentException(
				'Invalid sampling level "' . $samplingLevel . '".'
			);
		}
	}
	
	/**
	 * @param int $startIndex
	 */
	public function setStartIndex($startIndex) {
		$this->_startIndex = self::$_validator->number(
			$startIndex,
			null,
			\Validator::ASSERT_INT | \Validator::ASSERT_POSITIVE
		);
	}
	
	/**
	 * Sets the maximum number of results to request per query iteration.
	 *
	 * @param int $maxResults
	 */
	public function setMaxResults($maxResults) {
		$this->_maxResults = self::$_validator->number(
			$maxResults, null, \Validator::ASSERT_INT_DEFAULT, null, 10000
		);
	}
	
	/**
	 * Sets the maximum total number of results to request.
	 *
	 * @param int $totalResults
	 */
	public function setTotalResults($totalResults) {
		$this->_totalResults = self::$_validator->number(
			$totalResults, null, \Validator::ASSERT_INT_DEFAULT
		);
	}
	
	/**
	 * Sets a format string, compatible with PHP's date() function, that will
	 * be used by Google\Analytics\GaDataQuery::iteration() to format the start
	 * date into a readable value.
	 *
	 * @param string $format
	 */
	public function setFormatString($format) {
		$this->_formatString = $format;
	}
	
	/**
	 * Associates a Google\Analytics\API instance with the query so that
	 * profile IDs or names may be resolved to Google\Analytics\ProfileSummary
	 * instances where necessary.
	 *
	 * @param Google\Analytics\API $api
	 */
	public function setAPIInstance(API $api) {
		$this->_api = $api;
	}
	
	/**
	 * Returns a Google\Analytics\ProfileSummary instance. Note that if a
	 * profile was set by ID or name previously, and no API instance has yet
	 * been set via Google\Analytics\GaDataQuery::setAPIInstance(), this method
	 * will throw a Google\Analytics\LogicException.
	 *
	 * @return Google\Analytics\ProfileSummary
	 */
	public function getProfile() {
		if (is_object($this->_profile)) {
			return $this->_profile;
		}
		if (!$this->_api) {
			throw new LogicException(
				'If a profile is set by name or ID, an API instance must be ' .
				'set prior to retrieving the profile object.'
			);
		}
		if ($this->_profileID !== null) {
			return $this->_api->getProfileSummaryByID($this->_profileID);
		}
		if ($this->_profileName !== null) {
			return $this->_api->getProfileSummaryByName($this->_profileName);
		}
	}
	
	/**
	 * @return DateTime
	 */
	public function getStartDate() {
		return $this->_startDate;
	}
	
	/**
	 * @return DateTime
	 */
	public function getEndDate() {
		return $this->_endDate;
	}
	
	/**
	 * This method and its counterpart are provided as a way to make it easy to
	 * get the entire date range from subclasses that use a range.
	 *
	 * @return DateTime
	 */
	public function getSummaryStartDate() {
		return $this->_startDate;
	}
	
	/**
	 * This method and its counterpart are provided as a way to make it easy to
	 * get the entire date range from subclasses that use a range.
	 *
	 * @return DateTime
	 */
	public function getSummaryEndDate() {
		return $this->_endDate;
	}
	
	/**
	 * @return array
	 */
	public function getMetrics() {
		return $this->_metrics;
	}
	
	/**
	 * @return array
	 */
	public function getDimensions() {
		return $this->_dimensions;
	}
	
	/**
	 * @return Google\Analytics\GaDataSortOrder
	 */
	public function getSort() {
		return $this->_sort;
	}
	
	/**
	 * @return Google\Analytics\GaDataFilterCollection
	 */
	public function getFilter() {
		return $this->_filter;
	}
	
	/**
	 * Note that the return value of this method will depend upon the type of
	 * argument that was passed to $this->setSegment() until such time as I
	 * implement Google\Analytics\GaDataSegment::createFromString().
	 *
	 * @return Google\Analytics\GaDataSegment, string
	 */
	public function getSegment() {
		return $this->_segment;
	}
	
	/**
	 * @param boolean $asString = false
	 * @return int, string
	 */
	public function getSamplingLevel($asString = false) {
		if ($asString) {
			// Special case here so we don't submit an invalid value to the API
			if ($this->_samplingLevel == self::SAMPLING_LEVEL_NONE) {
				return 'DEFAULT';
			}
			$str = self::$_constantsByVal[$this->_samplingLevel];
			return str_replace('SAMPLING_LEVEL_', '', $str);
		}
		return $this->_samplingLevel;
	}
	
	/**
	 * @return int
	 */
	public function getStartIndex() {
		return $this->_startIndex;
	}
	
	/**
	 * Returns the maximum number of rows to request per query iteration.
	 *
	 * @return int
	 */
	public function getMaxResults() {
		if (!$this->_maxResults) {
			return GOOGLE_ANALYTICS_API_PAGE_SIZE;
		}
		return $this->_maxResults;
	}
	
	/**
	 * Returns the maximum total number of rows to fetch.
	 *
	 * @return int
	 */
	public function getTotalResults() {
		return $this->_totalResults;
	}
	
	/**
	 * Returns a representation of this instance as an array, ready for
	 * submission to the API.
	 *
	 * @return array
	 */
	public function getAsArray() {
		$profile = $this->getProfile();
		if (!$profile || !$this->_startDate ||
		    !$this->_endDate || !$this->_metrics)
		{
			throw new LogicException(
				'The profile name or ID, start date, end date, and at least ' .
				'one metric are required.'
			);
		}
		$params = array(
			'ids' => API::addPrefix($profile->getID()),
			'start-date' => $this->getStartDate()->format('Y-m-d'),
			'end-date' => $this->getEndDate()->format('Y-m-d'),
			'metrics' => implode(',', $this->getMetrics()),
			'start-index' => $this->getStartIndex(),
			'max-results' => $this->getMaxResults()
		);
		if ($this->_dimensions) {
			$params['dimensions'] = implode(',', $this->getDimensions());
		}
		if ($this->_sort) {
			$params['sort'] = (string)$this->_sort;
		}
		if ($this->_filter) {
			$params['filters'] = (string)$this->_filter;
		}
		if ($this->_segment) {
			$params['segment'] = (string)$this->_segment;
		}
		// Note the special case here
		if ($this->_samplingLevel &&
		    $this->_samplingLevel != self::SAMPLING_LEVEL_NONE)
		{
			$params['samplingLevel'] = $this->getSamplingLevel(true);
		}
		return $params;
	}
	
	/**
	 * Returns a hash that serves as a unique identifier of this query.
	 *
	 * @return string
	 */
	public function getHash() {
		$query = $this->getAsArray();
		$keys = array();
		$values = array();
		foreach ($query as $key => $value) {
			$keys[] = $keys;
			$values[] = $value;
		}
		/* This doesn't get submitted to the API, but it does differentiate the
		query. */
		$totalResults = $this->getTotalResults();
		if ($totalResults !== null) {
			$keys[] = 'total-results';
			$values[] = $totalResults;
		}
		array_multisort($keys, $values);
		return md5(implode('|', $values), true);
	}
	
	/**
	 * @return string
	 */
	public function getEmailSubject() {
		$startDate = $this->getSummaryStartDate();
		$endDate = $this->getSummaryEndDate();
		if ($this->_name) {
			return sprintf(
                'Google Analytics report "%s" for %s through %s',
                $this->_name,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );
		}
		return sprintf(
            'Google Analytics report for profile "%s" for %s through %s',
            $this->getProfile()->getName(),
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
	}
	
	/**
	 * @return string
	 */
	public function getFormatString() {
		return $this->_formatString;
	}
}
?>