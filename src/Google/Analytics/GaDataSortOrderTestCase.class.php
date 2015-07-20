<?php
namespace Google\Analytics;

class GaDataSortOrderTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the string representation of this object is as expected.
     */
    public function testStringification() {
        $sort = new GaDataSortOrder();
        $sort->addField('foo');
        $this->assertEquals('ga:foo', (string)$sort);
        $sort->addField('bar', SORT_DESC);
        $this->assertEquals('ga:foo,-ga:bar', (string)$sort);
        $sort = new GaDataSortOrder();
        $sort->addField('ga:baz', SORT_DESC);
        $this->assertEquals('-ga:baz', (string)$sort);
        $sort->addField('foo');
        $sort->addField('bar', SORT_DESC);
        $this->assertEquals('-ga:baz,ga:foo,-ga:bar', (string)$sort);
    }
    
    /**
     * Tests the parsing of sort expressions from their string
     * representations.
     */
    public function testParsingFromString() {
        $sort = GaDataSortOrder::createFromString('ga:foo,ga:bar');
        $this->assertEquals('ga:foo,ga:bar', (string)$sort);
        $sort = GaDataSortOrder::createFromString('foo,-bar');
        $this->assertEquals('ga:foo,-ga:bar', (string)$sort);
        $sort->addField('baz');
        $this->assertEquals('ga:foo,-ga:bar,ga:baz', (string)$sort);
    }
    
    /**
     * Confirms that bad input throws exceptions.
     */
    public function testValidation() {
        $sort = new GaDataSortOrder();
        // Sort order can only be SORT_ASC or SORT_DESC
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            array($sort, 'addField'),
            array('foo', 'asc')
        );
        $sort->addField('foo', SORT_ASC);
        // No double-dipping
        $this->assertThrows(
            __NAMESPACE__ . '\OverflowException',
            array($sort, 'addField'),
            array('foo')
        );
        $this->assertThrows(
            __NAMESPACE__ . '\OverflowException',
            array(__NAMESPACE__ . '\GaDataSortOrder', 'createFromString'),
            array('ga:foo,-ga:bar,ga:foo')
        );
    }
}
?>