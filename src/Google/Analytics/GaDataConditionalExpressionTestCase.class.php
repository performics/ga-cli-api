<?php
namespace Google\Analytics;

class GaDataConditionalExpressionTestCase extends \TestHelpers\TestCase {
    /**
     * Tests that the string representation of this object is as expected.
     */
    public function testStringification() {
        $x = new GaDataConditionalExpression(
            'foo', GaDataConditionalExpression::OP_EQ, 'bar'
        );
        $this->assertEquals('ga:foo==bar', (string)$x);
        $x = new GaDataConditionalExpression(
            'bar', GaDataConditionalExpression::OP_NOT_CONTAINS, 234.5
        );
        $this->assertEquals('ga:bar!@234.5', (string)$x);
        /* Make sure the chararacters that represent AND and OR (; and ,) are
        escaped. */
        $x = new GaDataConditionalExpression(
            'baz', GaDataConditionalExpression::OP_REGEXP, 'foo,bar'
        );
        $this->assertEquals('ga:baz=~foo\,bar', (string)$x);
        $x = new GaDataConditionalExpression(
            'bumf', GaDataConditionalExpression::OP_NOT_REGEXP, 'a;b;c;d;'
        );
        $this->assertEquals('ga:bumf!~a\;b\;c\;d\;', (string)$x);
    }
    
    /**
     * Tests the parsing of conditional expressions from their string
     * representations.
     */
    public function testParsingFromString() {
        $x = new GaDataConditionalExpression('ga:foo==bar');
        $this->assertEquals('ga:foo', $x->getLeftOperand());
        $this->assertEquals(
            GaDataConditionalExpression::OP_EQ, $x->getOperator()
        );
        $this->assertEquals('bar', $x->getRightOperand());
        $x = new GaDataConditionalExpression('ga:bar!@234.5');
        $this->assertEquals('ga:bar', $x->getLeftOperand());
        $this->assertEquals(
            GaDataConditionalExpression::OP_NOT_CONTAINS, $x->getOperator()
        );
        $this->assertEquals(234.5, $x->getRightOperand());
        $x = new GaDataConditionalExpression('ga:baz=~foo\,bar');
        $this->assertEquals('ga:baz', $x->getLeftOperand());
        $this->assertEquals(
            GaDataConditionalExpression::OP_REGEXP, $x->getOperator()
        );
        $this->assertEquals('foo\,bar', $x->getRightOperand());
        $x = new GaDataConditionalExpression('ga:bumf!~a\;b\;c\;d\;');
        $this->assertEquals('ga:bumf', $x->getLeftOperand());
        $this->assertEquals(
            GaDataConditionalExpression::OP_NOT_REGEXP, $x->getOperator()
        );
        $this->assertEquals('a\;b\;c\;d\;', $x->getRightOperand());
        // The parsing should also perform escaping where necessary
        $x = new GaDataConditionalExpression('ga:asdf>=2,5');
        $this->assertEquals('ga:asdf', $x->getLeftOperand());
        $this->assertEquals(
            GaDataConditionalExpression::OP_GE, $x->getOperator()
        );
        $this->assertEquals('2\,5', $x->getRightOperand());
    }
    
    /**
     * Confirms that bad input throws exceptions.
     */
    public function testValidation() {
        // Empty input isn't allowed
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataConditionalExpression(null); }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataConditionalExpression(''); }
        );
        // Exactly one or three arguments must be passed
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataConditionalExpression('foo', GaDataConditionalExpression::OP_EQ); }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataConditionalExpression('foo', null, 'bar'); }
        );
        // Operators must be recognized
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataConditionalExpression('foo', '===', 'bar'); }
        );
        /* The left operand can't contain anything but letters and numbers,
        with the sole exception of the 'ga:' prefix. */
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataConditionalExpression('*', GaDataConditionalExpression::OP_EQ, 'bar'); }
        );
        // Only scalars are permitted as the right operand
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataConditionalExpression('*', GaDataConditionalExpression::OP_EQ, array('bar')); }
        );
        // String expressions must be parseable
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataConditionalExpression('foo==bar'); }
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            function() { new GaDataConditionalExpression('ga:foo=>bar'); }
        );
    }
}
?>