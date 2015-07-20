<?php
namespace Google\Analytics;

class GaDataFilterCollectionTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the string representation of this object is as expected.
     */
    public function testStringification() {
        $filter = new GaDataFilterCollection(
            GaDataFilterCollection::OP_AND,
            new GaDataConditionalExpression(
                'foo', GaDataConditionalExpression::OP_EQ, 'bar'
            )
        );
        $this->assertEquals('ga:foo==bar', (string)$filter);
        $filter = new GaDataFilterCollection(
            GaDataFilterCollection::OP_AND,
            new GaDataConditionalExpression(
                'foo', GaDataConditionalExpression::OP_EQ, 'bar'
            ),
            new GaDataConditionalExpression(
                'baz', GaDataConditionalExpression::OP_LT, 3
            )
        );
        $this->assertEquals('ga:foo==bar;ga:baz<3', (string)$filter);
        $filter = new GaDataFilterCollection(
            GaDataFilterCollection::OP_OR,
            new GaDataConditionalExpression(
                'foo', GaDataConditionalExpression::OP_EQ, 'bar'
            ),
            new GaDataConditionalExpression(
                'baz', GaDataConditionalExpression::OP_LT, 3
            ),
            new GaDataConditionalExpression(
                'boo', GaDataConditionalExpression::OP_NOT_REGEXP, 'ASDOFASDF'
            )
        );
        $this->assertEquals(
            'ga:foo==bar,ga:baz<3,ga:boo!~ASDOFASDF', (string)$filter
        );
        // Filter collections may be nested
        $filter = new GaDataFilterCollection(
            GaDataFilterCollection::OP_OR,
            new GaDataFilterCollection(
                GaDataFilterCollection::OP_AND,
                new GaDataConditionalExpression(
                    'flarf', GaDataConditionalExpression::OP_CONTAINS, '--290$&'
                ),
                new GaDataConditionalExpression(
                    'blorf', GaDataConditionalExpression::OP_NE, '1134'
                )
            ),
            new GaDataFilterCollection(
                GaDataFilterCollection::OP_AND,
                new GaDataConditionalExpression(
                    'foo', GaDataConditionalExpression::OP_LE, 3
                ),
                new GaDataConditionalExpression(
                    'bar', GaDataConditionalExpression::OP_REGEXP, '^foo'
                )
            )
        );
        $this->assertEquals(
            'ga:flarf=@--290$&;ga:blorf!=1134,ga:foo<=3;ga:bar=~^foo',
            (string)$filter
        );
    }
}
?>