<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class GaDataColumnHeaderCollection {
	private $_columns = array();
	private $_columnIndicesByName = array();
	
	/**
	 * Constructs an instance from a list of column headers as returned by the
	 * Google Analytics API.
	 *
	 * @param array $headers
	 * @param Google\Analytics\API $api
	 */
	public function __construct(array $headers, API $api) {
		$headerCount = count($headers);
		for ($i = 0; $i < $headerCount; $i++) {
			self::_validateHeader($headers[$i]);
			try {
				$column = $api->getColumn($headers[$i]['name']);
			} catch (InvalidArgumentException $e) {
				/* I don't expect this to happen but in case it does, we can
				create a representation of the column by building it on the
				fly, though it will be missing most of the available
				properties. */
				$column = new Column();
				$column->setID(API::addPrefix($headers[$i]['name']));
				$column->setType($headers[$i]['columnType']);
				$column->setDataType($headers[$i]['dataType']);
			}
			$this->_columns[] = $column;
			$this->_columnIndicesByName[$column->getName()] = $i;
		}
	}
	
	/**
	 * Validates that a header representation as returned by the Google
	 * Analytics API has the expected properties.
	 *
	 * @param array $header
	 */
	private static function _validateHeader(array $header) {
		if (!isset($header['name']) || !isset($header['columnType']) ||
		    !isset($header['dataType']))
		{
			throw new InvalidArgumentException(
				'Missing one or more required column header properties.'
			);
		}
	}
	
	/**
	 * Returns a column instance given its name.
	 *
	 * @param string $name
	 * @return Google\Analytics\Column
	 */
	public function getColumn($name) {
		if (!isset($this->_columnIndicesByName[$name])) {
			throw new InvalidArgumentException(
				'Unrecognized column "' . $name . '".'
			);
		}
		return $this->_columns[$this->_columnIndicesByName[$name]];
	}
	
	/**
	 * Returns a column instance given its 0-based numeric index.
	 *
	 * @param int $index
	 * @return Google\Analytics\Column
	 */
	public function getColumnByIndex($index) {
		if (!isset($this->_columns[$index])) {
			throw new InvalidArgumentException('Invalid column index.');
		}
		return $this->_columns[$index];
	}
	
	/**
	 * Returns an associative array of columns to indices.
	 *
	 * @return array
	 */
	public function getColumnIndicesByName() {
		return $this->_columnIndicesByName;
	}
	
	/**
	 * Returns a numerically-indexed array of column names.
	 *
	 * @return array
	 */
	public function getColumnNames() {
		$colNames = array();
		foreach ($this->_columns as $column) {
			$colNames[] = $column->getName();
		}
		return $colNames;
	}
	
	/**
	 * Returns a numerically-indexed array of totals. In positions
	 * corresponding with columns that have no totals (e.g. dimensions), the
	 * returned array will contain a null value, so this array may be used
	 * directly when doing things such as generating CSVs.
	 *
	 * @return array
	 */
	public function getTotals() {
		$totals = array();
		foreach ($this->_columns as $column) {
			$totals[] = $column->getTotal();
		}
		return $totals;
	}
}
?>