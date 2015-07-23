<?php
namespace Google\Analytics;

class GaData extends AbstractAPIResponseObject {
	/* Note that this model does not call setColumnHeaders(), as that method
	requires an additional argument. */
	protected static $_SUPPLEMENTAL_SETTER_DISPATCH_MODEL = array(
		'containsSampledData' => 'containsSampledData',
		'query' => 'setQuery',
		'itemsPerPage' => 'setItemsPerPage',
		'totalResults' => 'setTotalResults',
		'previousLink' => 'setPreviousLink',
		'nextLink' => 'setNextLink',
		'profileInfo' => 'setProfileInfo',
		'rows' => 'setRows',
		'sampleSize' => 'setSampleSize',
		'sampleSpace' => 'setSampleSpace',
		'totalsForAllResults' => 'setTotals'
	);
	protected $_containsSampledData;
	protected $_query;
	protected $_itemsPerPage;
	protected $_totalResults;
	protected $_previousLink;
	protected $_nextLink;
	protected $_profileInfo;
	protected $_columnHeaders;
	protected $_rows;
	protected $_sampleSize;
	protected $_sampleSpace;
	protected $_totals;
	
	public function __construct(array $apiData = null) {
		if (!isset(static::$_SETTER_DISPATCH_MODEL[
			key(self::$_SUPPLEMENTAL_SETTER_DISPATCH_MODEL)
		])) {
			static::$_SETTER_DISPATCH_MODEL = array_merge(
				static::$_SETTER_DISPATCH_MODEL,
				self::$_SUPPLEMENTAL_SETTER_DISPATCH_MODEL
			);
		}
		parent::__construct($apiData);
	}
	
	/**
	 * @param array $queryData
	 */
	public function setQuery(array $queryData) {
		$this->_query = new GaDataQuery($queryData);
	}
	
	/**
	 * @param int $itemsPerPage
	 */
	public function setItemsPerPage($itemsPerPage) {
		$this->_itemsPerPage = self::$_validator->number(
			$itemsPerPage, null, \Validator::ASSERT_INT
		);
	}
	
	/**
	 * @param int $totalResults
	 */
	public function setTotalResults($totalResults) {
		$this->_totalResults = self::$_validator->number(
			$totalResults, null, \Validator::ASSERT_INT
		);
	}
	
	/**
	 * @param string, URL $previousLink
	 */
	public function setPreviousLink($previousLink) {
		$this->_previousLink = \URL::cast($previousLink);
	}
	
	/**
	 * @param string, URL $nextLink
	 */
	public function setNextLink($nextLink) {
		$this->_nextLink = \URL::cast($nextLink);
	}
	
	/**
	 * @param array $profileInfo
	 */
	public function setProfileInfo(array $profileInfo) {
		$this->_profileInfo = $profileInfo;
	}
	
	/**
	 * @param array $columnHeaders
	 * @param Google\Analytics\API $api
	 */
	public function setColumnHeaders(array $columnHeaders, API $api) {
		$this->_columnHeaders = new GaDataColumnHeaderCollection(
			$columnHeaders, $api
		);
		/* If the totals have been set, move them to the column objects where
		they belong. */
		if ($this->_totals) {
			foreach ($this->_totals as $gaName => $total) {
				$this->_columnHeaders->getColumn(
					substr($gaName, 3)
				)->setTotal($total);
			}
		}
	}
	
	/**
	 * @param array $rows
	 */
	public function setRows(array $rows) {
		$this->_rows = new GaDataRowCollection($rows);
	}
	
	/**
	 * @param string $sampleSize
	 */
	public function setSampleSize($sampleSize) {
		$this->_sampleSize = $sampleSize;
	}
	
	/**
	 * @param string $sampleSpace
	 */
	public function setSampleSpace($sampleSpace) {
		$this->_sampleSpace = $sampleSpace;
	}
	
	/**
	 * @param array $totals
	 */
	public function setTotals(array $totals) {
		$this->_totals = $totals;
		// If the column headers have already been set, put the totals in there
		if ($this->_columnHeaders) {
			foreach ($totals as $gaName => $total) {
				$this->_columnHeaders->getColumn(
					substr($gaName, 3)
				)->setTotal($total);
			}
		}
	}
	
	/**
	 * When called with no argument, returns a boolean indicating whether this
	 * instance contains sampled data; otherwise sets the corresponding
	 * property.
	 *
	 * @param boolean $containsSampledData = null
	 */
	public function containsSampledData($containsSampledData = null) {
		if ($containsSampledData === null) {
			return $this->_containsSampledData;
		}
		$this->_containsSampledData = $containsSampledData;
	}
	
	/**
	 * @return Google\Analytics\GaDataQuery
	 */
	public function getQuery() {
		return $this->_query;
	}
	
	/**
	 * @return int
	 */
	public function getItemsPerPage() {
		return $this->_itemsPerPage;
	}
	
	/**
	 * @return int
	 */
	public function getTotalResults() {
		return $this->_totalResults;
	}
	
	/**
	 * @return URL
	 */
	public function getPreviousLink() {
		return $this->_previousLink;
	}
	
	/**
	 * @return URL
	 */
	public function getNextLink() {
		return $this->_nextLink;
	}
	
	/**
	 * @return array
	 */
	public function getProfileInfo() {
		return $this->_profileInfo;
	}
	
	/**
	 * @return Google\Analytics\GaDataColumnHeaderCollection
	 */
	public function getColumnHeaders() {
		return $this->_columnHeaders;
	}
	
	/**
	 * @return Google\Analytics\GaDataRowCollection
	 */
	public function getRows() {
		return $this->_rows;
	}
	
	/**
	 * @return string
	 */
	public function getSampleSize() {
		return $this->_sampleSize;
	}
	
	/**
	 * @return string
	 */
	public function getSampleSpace() {
		return $this->_sampleSpace;
	}
	
	/**
	 * This returns the raw totals array that the GA API returned to us, but
	 * the preferred way to access the totals is to call the getTotal() method
	 * on the Google\Analytics\Column objects stored in the
	 * $this->_columnHeaders property, or that property's getTotals() method.
	 *
	 * @return array
	 */
	public function getTotals() {
		return $this->_totals;
	}
}
?>