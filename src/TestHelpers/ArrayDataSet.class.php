<?php
namespace TestHelpers;

class ArrayDataSet extends \PHPUnit_Extensions_Database_DataSet_AbstractDataSet {
    protected $_tables = array();
    
    /**
     * @param array $data
     */
    public function __construct(array $data) {
        foreach ($data as $tableName => $rows) {
            $meta = new \PHPUnit_Extensions_Database_DataSet_DefaultTableMetaData(
                $tableName, isset($rows[0]) ? array_keys($rows[0]) : array()
            );
            $table = new \PHPUnit_Extensions_Database_DataSet_DefaultTable($meta);
            foreach ($rows as $row) {
                $table->addRow($row);
            }
            $this->_tables[$tableName] = $table;
        }
    }
    
    /**
     * @param boolean $reverse = false
     * @return PHPUnit_Extensions_Database_DataSet_DefaultTableIterator
     */
    protected function createIterator($reverse = false) {
        return new \PHPUnit_Extensions_Database_DataSet_DefaultTableIterator(
            $this->_tables, $reverse
        );
    }
    
    /**
     * @param string $tableName
     * @return PHPUnit_Extensions_Database_DataSet_DefaultTable
     */
    public function getTable($tableName) {
        if (isset($this->_tables[$tableName])) {
            return $this->_tables[$tableName];
        }
        throw new \InvalidArgumentException(
            'Unrecognized table name "' . $tableName . '".'
        );
    }
}
?>