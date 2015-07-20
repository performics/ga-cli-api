<?php
namespace Google\Analytics;

class GaDataSegmentTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the string representation of this object is as expected.
     */
    public function testStringification() {
        $segment = new GaDataSegment(
            new GaDataSegmentConditionGroup(
                new GaDataSegmentSimpleCondition(
                    'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
                )
            ),
            GaDataSegment::SCOPE_SESSIONS
        );
        $this->assertEquals(
            'sessions::condition::ga:foo==bar', (string)$segment
        );
        $sequence = new GaDataSegmentSequence(
            new GaDataSegmentSequenceCondition(
                'foo', GaDataSegmentSequenceCondition::OP_IN, array('bar', 'baz')
            ),
            new GaDataSegmentSequenceCondition(
                'baz',
                GaDataSegmentSequenceCondition::OP_GT,
                '3',
                GaDataSegmentSequenceCondition::OP_FOLLOWED_BY
            )
        );
        $sequence->isNegated(true);
        $conditions = new GaDataSegmentConditionGroup(
            new GaDataSegmentSimpleCondition(
                'bar', GaDataSegmentSimpleCondition::OP_CONTAINS, 'a'
            )
        );
        $conditions->setScope(GaDataSegmentConditionGroup::SCOPE_PER_HIT);
        $segment = new GaDataSegment(
            $sequence,
            GaDataSegment::SCOPE_USERS,
            $conditions,
            GaDataSegment::SCOPE_SESSIONS
        );
        $this->assertEquals(
            'users::sequence::!ga:foo[]bar|baz;->>ga:baz>3;sessions::condition::perHit::ga:bar=@a',
            (string)$segment
        );
    }
    
    /**
     * Confirms that bad input throws exceptions.
     */
    public function testValidation() {
        /* The Google\Analytics\GaDataSegment constructor requires an even
        number of arguments (at least 2). */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataSegment(); }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegment(
                    new GaDataSegmentConditionGroup(
                        new GaDataSegmentSimpleCondition(
                            'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
                        )
                    )
                );
            }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegment(
                    new GaDataSegmentConditionGroup(
                        new GaDataSegmentSimpleCondition(
                            'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
                        )
                    ),
                    GaDataSegment::SCOPE_SESSIONS,
                    new GaDataSegmentConditionGroup(
                        new GaDataSegmentSimpleCondition(
                            'bar', GaDataSegmentSimpleCondition::OP_EQ, 'baz'
                        )
                    )
                );
            }
        );
        /* The arguments in the odd positions must be
        Google\Analytics\GaDataSegmentGroup instances. */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegment(
                    new GaDataSegmentSimpleCondition(
                        'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
                    ),
                    GaDataSegment::SCOPE_SESSIONS
                );
            }
        );
        // The arguments in the odd positions must be recognized constants
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegment(
                    new GaDataSegmentConditionGroup(
                        new GaDataSegmentSimpleCondition(
                            'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
                        )
                    ),
                    GaDataSegmentSimpleCondition::OP_EQ
                );
            }
        );
    }
}
?>