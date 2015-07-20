<?php
namespace Google\Analytics;

class GaDataSegmentSequenceTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the string representation of this object is as expected.
     */
    public function testStringification() {
        $sequence = new GaDataSegmentSequence(
            new GaDataSegmentSequenceCondition(
                'foo', GaDataSegmentSequenceCondition::OP_EQ, 'bar'
            )
        );
        $this->assertEquals('sequence::ga:foo==bar', (string)$sequence);
        $sequence->isNegated(true);
        $this->assertEquals('sequence::!ga:foo==bar', (string)$sequence);
        $sequence = new GaDataSegmentSequence(
            new GaDataSegmentSequenceCondition(
                'foo',
                GaDataSegmentSequenceCondition::OP_LT,
                '3',
                GaDataSegmentSequenceCondition::OP_FIRST_HIT_MATCHES_FIRST_STEP
            ),
            new GaDataSegmentSequenceCondition(
                'bar',
                GaDataSegmentSequenceCondition::OP_BETWEEN,
                '1_10',
                GaDataSegmentSequenceCondition::OP_FOLLOWED_BY
            ),
            new GaDataSegmentSequenceCondition(
                'baz',
                GaDataSegmentSequenceCondition::OP_REGEXP,
                'asdf',
                GaDataSegmentSequenceCondition::OP_FOLLOWED_BY_IMMEDIATE
            )
        );
        $this->assertEquals(
            'sequence::^ga:foo<3;->>ga:bar<>1_10;->ga:baz=~asdf',
            (string)$sequence
        );
        $sequence = new GaDataSegmentSequence(
            new GaDataSegmentSequenceCondition(
                'foo', GaDataSegmentSequenceCondition::OP_LT, '2015-01-01'
            ),
            new GaDataSegmentSequenceCondition(
                'dateOfSession',
                GaDataSegmentSequenceCondition::OP_NE,
                '2010-01-01'
            ),
            new GaDataSegmentSequenceCondition(
                'baz',
                GaDataSegmentSequenceCondition::OP_NOT_REGEXP,
                'asdf',
                GaDataSegmentSequenceCondition::OP_FOLLOWED_BY
            )
        );
        $sequence->isNegated(true);
        $this->assertEquals(
            'sequence::!ga:foo<2015-01-01;dateOfSession!=2010-01-01;->>ga:baz!~asdf',
            (string)$sequence
        );
    }
    
    /**
     * Confirms that bad input throws exceptions.
     */
    public function testValidation() {
        /* Arguments to the Google\Analytics\GaDataSegmentSequence constructor
        may only be Google\Analytics\GaDataSegmentSequenceCondition instances.
        */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataSegmentSequence('foo'); }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequence(new GaDataSegmentSimpleCondition(
                    'foo', GaDataSegmentSimpleCondition::OP_EQ, 'bar'
                ));
            }
        );
        // There must be at least one argument
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataSegmentSequence(); }
        );
        /* The first condition in a sequence may not have a constraint against
        the previous condition other than the assertion that the first hit
        matches the first step. */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequence(new GaDataSegmentSequenceCondition(
                    'foo',
                    GaDataSegmentSequenceCondition::OP_EQ,
                    'bar',
                    GaDataSegmentSequenceCondition::OP_FOLLOWED_BY
                ));
            }
        );
        /* Only the first condition in a sequence may assert that the first
        hit matches the first step. */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequence(
                    new GaDataSegmentSequenceCondition(
                        'foo',
                        GaDataSegmentSequenceCondition::OP_EQ,
                        'bar'
                    ),
                    new GaDataSegmentSequenceCondition(
                        'bar',
                        GaDataSegmentSequenceCondition::OP_LE,
                        '7',
                        GaDataSegmentSequenceCondition::OP_FIRST_HIT_MATCHES_FIRST_STEP
                    )
                );
            }
        );
        /* Steps in a sequence beyond the first must have some constraint
        against the previous (unless they are dateOfSession restrictions). */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() {
                new GaDataSegmentSequence(
                    new GaDataSegmentSequenceCondition(
                        'foo',
                        GaDataSegmentSequenceCondition::OP_EQ,
                        'bar'
                    ),
                    new GaDataSegmentSequenceCondition(
                        'bar',
                        GaDataSegmentSequenceCondition::OP_LE,
                        '7'
                    )
                );
            }
        );
    }
}
?>