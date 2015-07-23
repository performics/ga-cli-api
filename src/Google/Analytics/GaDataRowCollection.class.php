<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class GaDataRowCollection {
	/* The presence of this flag instructs $this->fetch() to typecast
	everything that looks like a number as a number (either an integer or a
	float). */
	const FETCH_TYPECAST = 1;
	const FETCH_NUM = 2;
	const FETCH_ASSOC = 4;
	private static $_validator;
	private $_columns;
	private $_columnIndicesByName;
	private $_rows;
	private $_rowCount;
	private $_rowPointer = 0;
	
	/**
	 * Constructs an instance from a list of rows as returned from the Google
	 * Analytics API.
	 *
	 * @param array $rows
	 */
	public function __construct(array $rows) {
		if (!self::$_validator) {
			self::$_validator = new \Validator(__NAMESPACE__);
		}
		$this->_rows = $rows;
		$this->_rowCount = count($rows);
	}
	
	/**
	 * Sets the column headers associated with this instance so that it can
	 * generate associative arrays of data.
	 *
	 * @param Google\Analytics\GaDataColumnHeaderCollection $columns
	 */
	public function setColumnHeaders(GaDataColumnHeaderCollection $columns) {
		$this->_columns = $columns;
		$this->_columnIndicesByName = $columns->getColumnIndicesByName();
	}
	
	/**
	 * Returns the next row from this data set in a way modeled after
	 * PDOStatement and similar database abstraction layers. This method's
	 * argument should be one of the
	 * Google\Analytics\GaDataRowCollection::FETCH_NUM or
	 * Google\Analytics\GaDataRowCollection::FETCH_ASSOC constants, optionally
	 * masked with the Google\Analytics\GaDataRowCollection::FETCH_TYPECAST
	 * flag, which ensures that any data that looks like a number is returned
	 * as a numeric type.
	 *
	 * @param int $fetchStyle = self::FETCH_NUM
	 * @return array, boolean
	 */
	public function fetch($fetchStyle = self::FETCH_NUM) {
		if ($this->_rowPointer >= $this->_rowCount) {
			return false;
		}
		$row = $this->_rows[$this->_rowPointer++];
		if ($fetchStyle & self::FETCH_TYPECAST) {
			foreach ($row as &$dataPoint) {
				if (is_numeric($dataPoint)) {
					try {
						$dataPoint = self::$_validator->number(
							$dataPoint, null, \Validator::ASSERT_INT
						);
					} catch (InvalidArgumentException $e) {
						$dataPoint = (float)$dataPoint;
					}
				}
			}
			$fetchStyle ^= self::FETCH_TYPECAST;
		}
		switch ($fetchStyle) {
			case self::FETCH_NUM:
				return $row;
			case self::FETCH_ASSOC:
				$cols = $this->_columnIndicesByName;
				foreach ($this->_columnIndicesByName as $col => $index) {
					$cols[$col] = $row[$index];
				}
				return $cols;
			default:
				/* Back up the pointer so it's possible to try getting this row
				again. */
				$this->_rowPointer--;
				throw new InvalidArgumentException('Invalid fetch style.');
		}
	}
	
	/**
	 * Resets the internal row pointer so that the contents of the collection
	 * may be fetched again.
	 */
	public function reset() {
		$this->_rowPointer = 0;
	}
}
?>