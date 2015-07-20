<?php
namespace Google\Analytics;

class GaDataSegmentSimpleConditionTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the string representation of this object is as expected.
     */
    public function testStringification() {
        $condition = new GaDataSegmentSimpleCondition(
            'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
        );
        $this->assertEquals('ga:foo==bar', (string)$condition);
        $condition = new GaDataSegmentSimpleCondition(
            'foo', GaDataSegmentSimpleCondition::OP_BETWEEN, 'foo,bar_bar;baz'
        );
        $this->assertEquals('ga:foo<>foo\,bar_bar\;baz', (string)$condition);
        $condition = new GaDataSegmentSimpleCondition(
            'foo', GaDataSegmentSimpleCondition::OP_IN, 'bar|baz|bam'
        );
        $this->assertEquals('ga:foo[]bar|baz|bam', (string)$condition);
        // Right operands may be arrays if the operator is <> or []
        $condition = new GaDataSegmentSimpleCondition(
            'bar', GaDataSegmentSimpleCondition::OP_BETWEEN, array(2, 6)
        );
        $this->assertEquals('ga:bar<>2_6', (string)$condition);
        $condition = new GaDataSegmentSimpleCondition(
            'baz', GaDataSegmentSimpleCondition::OP_IN, array('a', 'b,c', 'd')
        );
        $this->assertEquals('ga:baz[]a|b\,c|d', (string)$condition);
    }
    
    /**
     * Tests the parsing of segment conditions from their string
     * representations.
     */
    public function testParsingFromString() {
        $condition = new GaDataSegmentSimpleCondition(
            'ga:foo<>foo\,bar_bar\;baz'
        );
        $this->assertEquals('ga:foo', $condition->getLeftOperand());
        $this->assertEquals(
            GaDataSegmentSimpleCondition::OP_BETWEEN, $condition->getOperator()
        );
        $this->assertEquals('foo\,bar_bar\;baz', $condition->getRightOperand());
        $condition = new GaDataSegmentSimpleCondition('ga:foo[]bar|baz|bam');
        $this->assertEquals('ga:foo', $condition->getLeftOperand());
        $this->assertEquals(
            GaDataSegmentSimpleCondition::OP_IN, $condition->getOperator()
        );
        $this->assertEquals('bar|baz|bam', $condition->getRightOperand());
    }
    
    /**
     * Confirms that bad input throws exceptions.
     */
    public function testValidation() {
        // Arrays aren't allowed if the operator isn't <> or []
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSimpleCondition(
                    'foo', GaDataSegmentSimpleCondition::OP_NE, array('bar', 'baz')
                );
            }
        );
        // If the operator is <>, there must be exactly two range values
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSimpleCondition(
                    'foo', GaDataSegmentSimpleCondition::OP_BETWEEN, array('bar')
                );
            }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSimpleCondition(
                    'foo', GaDataSegmentSimpleCondition::OP_BETWEEN, array('bar', 'baz', 'bam')
                );
            }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSimpleCondition(
                    'foo', GaDataSegmentSimpleCondition::OP_BETWEEN, 'bar'
                );
            }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSimpleCondition(
                    'foo', GaDataSegmentSimpleCondition::OP_BETWEEN, 'bar_baz_bam'
                );
            }
        );
    }
}
?>