<?php
namespace Google\Analytics;

class GaDataSegmentConditionGroupTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the string representation of this object is as expected.
     */
    public function testStringification() {
        $conditions = new GaDataSegmentConditionGroup(
            new GaDataSegmentSimpleCondition(
                'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
            )
        );
        $this->assertEquals('condition::ga:foo==bar', (string)$conditions);
        $conditions->isNegated(true);
        $this->assertEquals('condition::!ga:foo==bar', (string)$conditions);
        $conditions = new GaDataSegmentConditionGroup(
            new GaDataSegmentSimpleCondition(
                'bar', GaDataSegmentSimpleCondition::OP_BETWEEN, '1_879'
            ),
            new GaDataSegmentSimpleCondition(
                'baz', GaDataSegmentSimpleCondition::OP_NE, 'foo'
            )
        );
        $conditions->setScope(GaDataSegmentConditionGroup::SCOPE_PER_HIT);
        $this->assertEquals(
            'condition::perHit::ga:bar<>1_879;ga:baz!=foo', (string)$conditions
        );
        $conditions->setScope(GaDataSegmentConditionGroup::SCOPE_PER_SESSION);
        $this->assertEquals(
            'condition::perSession::ga:bar<>1_879;ga:baz!=foo', (string)$conditions
        );
        $conditions->setScope(GaDataSegmentConditionGroup::SCOPE_PER_USER);
        $this->assertEquals(
            'condition::perUser::ga:bar<>1_879;ga:baz!=foo', (string)$conditions
        );
        $conditions->isNegated(true);
        $this->assertEquals(
            'condition::perUser::!ga:bar<>1_879;ga:baz!=foo', (string)$conditions
        );
    }
    
    /**
     * Confirms that bad input throws exceptions.
     */
    public function testValidation() {
        /* Arguments may only be Google\Analytics\GaDataSegmentSimpleCondition
        instances, and there must be at least one. */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataSegmentConditionGroup(); }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataSegmentConditionGroup('foo'); }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentConditionGroup(
                    new GaDataSegmentSequenceCondition(
                        'foo', GaDataSegmentSequenceCondition::OP_EQ, 'bar'
                    )
                );
            }
        );
        // Can't use any old nonsense value for the scope
        $conditions = new GaDataSegmentConditionGroup(
            new GaDataSegmentSimpleCondition(
                'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
            )
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            array($conditions, 'setScope'),
            array('asdf')
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            array($conditions, 'setScope'),
            array(GaDataSegmentConditionGroup::OP_AND)
        );
    }
}
?>