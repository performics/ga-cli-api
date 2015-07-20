<?php
namespace Google\Analytics;

class GaDataRowCollectionTestCase extends \TestHelpers\TestCase {
    /**
     * Tests the row fetching behavior. Note that this must be run in its own
     * process due to the API instance mocking and the settings that requires.
     */
    public function testFetching() {
        /* We need a mock API instance; all it needs to do is throw an
        exception when it is asked for a column. We're bypassing the original
        constructor so we don't have to worry about having usable settings. */
        $api = $this->getMockBuilder(__NAMESPACE__ . '\API')
                    ->disableOriginalConstructor()
                    ->setMethods(array('getColumn'))->getMock();
        $api->method('getColumn')->will(
            $this->throwException(new InvalidArgumentException())
        );
        $rows = array(
            array('foo', 'bar', '3'),
            array('a', 'b', 'c'),
            array('', 7, '3.6'),
            array(6.609, 'q', 'asoidfj')
        );
        $rowCollection = new GaDataRowCollection($rows);
        $rowCollection->setColumnHeaders(new GaDataColumnHeaderCollection(
            array(
                array(
                    'name' => 'ga:column1',
                    'columnType' => 'METRIC',
                    'dataType' => 'STRING'
                ),
                array(
                    'name' => 'ga:column2',
                    'columnType' => 'METRIC',
                    'dataType' => 'STRING'
                ),
                array(
                    'name' => 'ga:column3',
                    'columnType' => 'METRIC',
                    'dataType' => 'STRING'
                )
            ),
            $api
        ));
        $fetched = array();
        while ($row = $rowCollection->fetch()) {
            $fetched[] = $row;
        }
        $this->assertSame($rows, $fetched);
        // We can have the numbers typecast
        $rows = array(
            array('foo', 'bar', 3),
            array('a', 'b', 'c'),
            array('', 7, 3.6),
            array(6.609, 'q', 'asoidfj')
        );
        $rowCollection->reset();
        $fetched = array();
        $fetchStyle = GaDataRowCollection::FETCH_NUM | GaDataRowCollection::FETCH_TYPECAST;
        while ($row = $rowCollection->fetch($fetchStyle)) {
            $fetched[] = $row;
        }
        $this->assertSame($rows, $fetched);
        // We can get the data as an associative array
        $rows = array(
            array(
                'column1' => 'foo',
                'column2' => 'bar',
                'column3' => '3'
            ),
            array(
                'column1' => 'a',
                'column2' => 'b',
                'column3' => 'c'
            ),
            array(
                'column1' => '',
                'column2' => 7,
                'column3' => '3.6'
            ),
            array(
                'column1' => 6.609,
                'column2' => 'q',
                'column3' => 'asoidfj'
            )
        );
        $fetched = array();
        $rowCollection->reset();
        while ($row = $rowCollection->fetch(GaDataRowCollection::FETCH_ASSOC)) {
            $fetched[] = $row;
        }
        $this->assertSame($rows, $fetched);
        // We can combine those fetch styles
        $rows = array(
            array(
                'column1' => 'foo',
                'column2' => 'bar',
                'column3' => 3
            ),
            array(
                'column1' => 'a',
                'column2' => 'b',
                'column3' => 'c'
            ),
            array(
                'column1' => '',
                'column2' => 7,
                'column3' => 3.6
            ),
            array(
                'column1' => 6.609,
                'column2' => 'q',
                'column3' => 'asoidfj'
            )
        );
        $fetched = array();
        $rowCollection->reset();
        $fetchStyle = GaDataRowCollection::FETCH_ASSOC | GaDataRowCollection::FETCH_TYPECAST;
        while ($row = $rowCollection->fetch($fetchStyle)) {
            $fetched[] = $row;
        }
        $this->assertSame($rows, $fetched);
        // Nonsensical fetch styles should throw an exception
        $rowCollection->reset();
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            array($rowCollection, 'fetch'),
            array(GaDataRowCollection::FETCH_NUM | GaDataRowCollection::FETCH_ASSOC)
        );
        /* But after the exception is thrown, it should still be possible to
        fetch everything. */
        $fetched = array();
        while ($row = $rowCollection->fetch($fetchStyle)) {
            $fetched[] = $row;
        }
        $this->assertSame($rows, $fetched);
    }
}
?>