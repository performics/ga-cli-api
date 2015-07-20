<?php
namespace Google\Analytics;

class GaDataSegmentSequenceConditionTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the string representation of this object is as expected.
     */
    public function testStringification() {
        $condition = new GaDataSegmentSequenceCondition(
            'foo', GaDataSegmentSequenceCondition::OP_GT, 'bar'
        );
        $this->assertEquals('ga:foo>bar', (string)$condition);
        $condition = new GaDataSegmentSequenceCondition(
            'dateOfSession', GaDataSegmentSequenceCondition::OP_LE, '2015-01-01'
        );
        // Note the special case here
        $this->assertEquals('dateOfSession<=2015-01-01', (string)$condition);
        // Sequence conditions may involve multiple conditions
        $condition->addCondition(new GaDataSegmentSimpleCondition(
            'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
        ));
        $this->assertEquals(
            'dateOfSession<=2015-01-01;ga:foo==bar', (string)$condition
        );
        $condition->addCondition(new GaDataSegmentSimpleCondition(
            'bar', GaDataSegmentSimpleCondition::OP_BETWEEN, array(2, 10)
        ));
        $this->assertEquals(
            'dateOfSession<=2015-01-01;ga:foo==bar;ga:bar<>2_10',
            (string)$condition
        );
        $condition = new GaDataSegmentSequenceCondition(
            'baz',
            GaDataSegmentSequenceCondition::OP_IN,
            array('a', 'b', 'c'),
            GaDataSegmentSequenceCondition::OP_FIRST_HIT_MATCHES_FIRST_STEP
        );
        $this->assertEquals('^ga:baz[]a|b|c', (string)$condition);
        $condition = new GaDataSegmentSequenceCondition(
            'foo',
            GaDataSegmentSequenceCondition::OP_NE,
            'bar;baz',
            GaDataSegmentSequenceCondition::OP_FOLLOWED_BY
        );
        $this->assertEquals('->>ga:foo!=bar\;baz', (string)$condition);
        $condition = new GaDataSegmentSequenceCondition(
            'asdf',
            GaDataSegmentSequenceCondition::OP_GT,
            '2,3',
            GaDataSegmentSequenceCondition::OP_FOLLOWED_BY_IMMEDIATE,
            new GaDataSegmentSimpleCondition(
                'bar', GaDataSegmentSimpleCondition::OP_NOT_CONTAINS, 'baz'
            )
        );
        $this->assertEquals('->ga:asdf>2\,3;ga:bar!@baz', (string)$condition);
    }
    
    /**
     * Tests the parsing of sequence conditions from their string
     * representations.
     */
    public function testParsingFromString() {
        $condition = new GaDataSegmentSequenceCondition('->>ga:foo!=bar\;baz');
        $this->assertEquals(
            GaDataSegmentSequenceCondition::OP_FOLLOWED_BY,
            $condition->getConstraintAgainstPrevious()
        );
        $this->assertEquals('ga:foo', $condition->getLeftOperand());
        $this->assertEquals(
            GaDataSegmentSequenceCondition::OP_NE, $condition->getOperator()
        );
        $this->assertEquals('bar\;baz', $condition->getRightOperand());
        $condition = new GaDataSegmentSequenceCondition(
            '->ga:asdf>2\,3;ga:bar!@baz'
        );
        $this->assertEquals(
            GaDataSegmentSequenceCondition::OP_FOLLOWED_BY_IMMEDIATE,
            $condition->getConstraintAgainstPrevious()
        );
        $this->assertEquals('ga:asdf', $condition->getLeftOperand());
        $this->assertEquals(
            GaDataSegmentSequenceCondition::OP_GT, $condition->getOperator()
        );
        $this->assertEquals('2\,3', $condition->getRightOperand());
        $this->assertEquals(array(new GaDataSegmentSimpleCondition(
            'bar', GaDataSegmentSimpleCondition::OP_NOT_CONTAINS, 'baz'
        )), $condition->getAdditionalConditions());
        $condition = new GaDataSegmentSequenceCondition(
            'dateOfSession<=2015-01-01'
        );
        $this->assertNull($condition->getConstraintAgainstPrevious());
        $this->assertEquals('dateOfSession', $condition->getLeftOperand());
        $this->assertEquals(
            GaDataSegmentSequenceCondition::OP_LE, $condition->getOperator()
        );
        $this->assertEquals('2015-01-01', $condition->getRightOperand());
    }
    
    /**
     * Confirms that bad input throws exceptions.
     */
    public function testValidation() {
        /* The validation rules from
        Google\Analytics\GaDataSegmentSimpleCondition should apply. */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequenceCondition(
                    'foo', GaDataSegmentSequenceCondition::OP_NE, array('bar', 'baz')
                );
            }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequenceCondition(
                    'foo', GaDataSegmentSequenceCondition::OP_BETWEEN, array('bar')
                );
            }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequenceCondition(
                    'foo', GaDataSegmentSequenceCondition::OP_BETWEEN, array('bar', 'baz', 'bam')
                );
            }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequenceCondition(
                    'foo', GaDataSegmentSequenceCondition::OP_BETWEEN, 'bar'
                );
            }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequenceCondition(
                    'foo', GaDataSegmentSequenceCondition::OP_BETWEEN, 'bar_baz_bam'
                );
            }
        );
        // dateOfSession conditions can't have a previous constraint
        $this->assertThrows(
            __NAMESPACE__ . '\LogicException',
            function() {
                new GaDataSegmentSequenceCondition(
                    'dateOfSession',
                    GaDataSegmentSequenceCondition::OP_EQ,
                    '2010-01-01',
                    GaDataSegmentSequenceCondition::OP_FIRST_HIT_MATCHES_FIRST_STEP
                );
            }
        );
        // The previous constraint may only be one of three things
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequenceCondition(
                    'foo',
                    GaDataSegmentSequenceCondition::OP_EQ,
                    '2010-01-01',
                    GaDataSegmentSequenceCondition::OP_NE
                );
            }
        );
        /* Additional conditions may not be subclasses of
        Google\Analytics\GaDataSegmentSimpleCondition. */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequenceCondition(
                    'foo',
                    GaDataSegmentSequenceCondition::OP_EQ,
                    '2010-01-01',
                    null,
                    new GaDataSegmentSequenceCondition(
                        'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
                    )
                );
            }
        );
        $condition = new GaDataSegmentSequenceCondition(
            'foo', GaDataSegmentSequenceCondition::OP_EQ, '2010-01-01'
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            array($condition, 'addCondition'),
            array(new GaDataSegmentSequenceCondition(
                'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
            ))
        );
    }
}
?>