<?php
class ValidatorTestCase extends TestHelpers\TestCase {
    /**
     * Tests whether the Validator constructor treates an argument as a
     * namespace and attempts to discover an InvalidArgumentException class in
     * it.
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Bogus_Namespace\InvalidArgumentException
     */
    public function testNamespaceResolution() {
        /* Because we can't dynamically generate a namespace that is guaranteed
        to contain the appropriate exception type, we will test this by passing
        a bogus namespace and ensuring that the raised exception refers to the
        correct class name. */
        new Validator('Bogus_Namespace');
    }
    
    /**
     * Tests whether we can declare the kind of exception we want to be thrown
     * when validation fails.
     */
    public function testExceptionDeclaration() {
        $validator = new Validator();
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array('asdf')
        );
        $validator->setExceptionType('LogicException');
        $this->assertThrows(
            'LogicException',
            array($validator, 'number'),
            array('asdf')
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'setExceptionType'),
            array('stdClass')
        );
    }
    
    /**
     * Tests the ability to enable and disable exceptions from a Validator
     * instance.
     */
    public function testExceptionDisabling() {
        $validator = new Validator();
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array('asdf')
        );
        $validator->disableExceptions();
        $this->assertSame(
            false, $validator->number('asdf')
        );
        $validator->enableExceptions();
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array('asdf')
        );
    }
    
    /**
     * Tests the ability to pass a custom exception message.
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Bad call, Jack
     */
    public function testCustomExceptionMessage() {
        $validator = new Validator();
        $validator->number('asdf', 'Bad call, Jack');
    }
    
    /**
     * Tests the various forms of string filtering and validation.
     */
    public function testStringValidation() {
        $validator = new Validator();
        $this->assertInternalType('string', $validator->string(234));
        // Enforce a type match
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'string'),
            array(234, null, Validator::ASSERT_TYPE_MATCH)
        );
        // Nulls are OK
        $this->assertNull($validator->string(null));
        // Empty strings are normally coerced to null
        $this->assertNull($validator->string(''));
        // As are strings consisting solely of whitespace
        $this->assertNull($validator->string("\t"));
        // But this may be disabled
        $this->assertSame('', $validator->string(null, null, 0));
        $this->assertSame(
            '', $validator->string("\t", null, Validator::FILTER_TRIM)
        );
        $this->assertSame("\t", $validator->string("\t", null, 0));
        // And we can assert that nulls fail validation
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'string'),
            array(null, null, Validator::ASSERT_NOT_NULL)
        );
        // We can assert that both nulls and empty strings fail validation
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'string'),
            array(null, null, Validator::ASSERT_TRUTH)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'string'),
            array('', null, Validator::ASSERT_TRUTH)
        );
        // We can automatically trim whitespace
        $input = <<<EOF
    foo
    
EOF;
        $this->assertEquals(
            'foo', $validator->string($input, null, Validator::FILTER_TRIM)
        );
        // Certain assertions do not make sense for this method
        $this->assertEquals(
            'asdf', $validator->string('asdf', null, Validator::ASSERT_INT)
        );
        $this->assertEquals(
            '-1', $validator->string('-1', null, Validator::ASSERT_POSITIVE)
        );
        $this->assertEquals(
            'you@hotmail.com,me@hotmail.com',
            $validator->string(
                'you@hotmail.com,me@hotmail.com',
                null,
                Validator::ASSERT_SINGLE_EMAIL
            )
        );
        $this->assertEquals('www.website.com', $validator->string(
            'www.website.com', null, Validator::FILTER_ADD_SCHEME
        ));
        // We can also assert a maximum length
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'string'),
            array('abcdefg', null, Validator::FILTER_DEFAULT, 6)
        );
        // This should only take effect after the trimming of whitespace
        $this->assertEquals('abcde', $validator->string(
            '    abcde ', null, Validator::FILTER_DEFAULT, 6
        ));
        // Filter to lowercase
        $this->assertEquals('foo', $validator->string(
            'FoO', null, Validator::FILTER_LOWERCASE
        ));
        $this->assertEquals('foo', $validator->string(
            '  FOO', null, Validator::FILTER_TRIM | Validator::FILTER_LOWERCASE
        ));
        $this->assertNull($validator->string(
            null, null, Validator::FILTER_DEFAULT | Validator::FILTER_LOWERCASE
        ));
        $this->assertNull($validator->string(
            '', null, Validator::FILTER_DEFAULT | Validator::FILTER_LOWERCASE
        ));
        /* The uppercase filter only takes effect if the lowercase filter isn't
        in the bitmask. */
        $this->assertEquals('foo', $validator->string(
            '  FOO',
            null,
            Validator::FILTER_TRIM | Validator::FILTER_LOWERCASE | Validator::FILTER_UPPERCASE
        ));
        // Filter to uppercase
        $this->assertEquals('FOO', $validator->string(
            'foo    ', null, Validator::FILTER_TRIM | Validator::FILTER_UPPERCASE
        ));
        $this->assertNull($validator->string(
            null, null, Validator::FILTER_DEFAULT | Validator::FILTER_UPPERCASE
        ));
        $this->assertNull($validator->string(
            '', null, Validator::FILTER_DEFAULT | Validator::FILTER_UPPERCASE
        ));
    }
    
    /**
     * Tests the various forms of numeric filtering and validation.
     */
    public function testNumericValidation() {
        $validator = new Validator();
        /* By default, anything that PHP considers a number is allowed, and
        typecast as appropriate. */
        $this->assertSame(5, $validator->number(5));
        $this->assertSame(5, $validator->number('5'));
        $this->assertSame(-5 / 3, $validator->number(-5 / 3));
        $this->assertSame(-1.667, $validator->number(round(-5 / 3, 3)));
        $this->assertSame((float)123450000, $validator->number('+0123.45e6'));
        $this->assertSame(16103058, $validator->number(0xf5b692));
        // We can assert that something looks like an integer
        $this->assertSame(
            5, $validator->number('5', null, Validator::ASSERT_INT)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(5.3, null, Validator::ASSERT_INT)
        );
        // Or actually is an integer
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array('5', null, Validator::ASSERT_TYPE_MATCH | Validator::ASSERT_INT)
        );
        $this->assertSame(5, $validator->number(
            5, null, Validator::ASSERT_TYPE_MATCH | Validator::ASSERT_INT
        ));
        // Or maybe we just want to assert it's any numeric type
        $this->assertSame(5.2, $validator->number(
            5.2, null, Validator::ASSERT_TYPE_MATCH
        ));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array('5.3', null, Validator::ASSERT_TYPE_MATCH)
        );
        // Nulls are not permitted by default
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(null)
        );
        // But we can override that
        $this->assertNull($validator->number(
            null, null, Validator::ASSERT_ALLOW_NULL
        ));
        // This takes precedence over assertions about the type
        $this->assertNull($validator->number(
            null, null, Validator::ASSERT_ALLOW_NULL | Validator::ASSERT_INT | Validator::ASSERT_TYPE_MATCH
        ));
        /* All of the above also works on empty strings if we assert that they
        should be coerced to null. */
        $this->assertNull($validator->number(
            '', null, Validator::ASSERT_ALLOW_NULL | Validator::FILTER_TO_NULL
        ));
        $this->assertNull($validator->number(
            '', null, Validator::ASSERT_ALLOW_NULL | Validator::FILTER_TO_NULL | Validator::ASSERT_INT | Validator::ASSERT_TYPE_MATCH
        ));
        // Truth assertions work here as expected
        $this->assertSame(-5, $validator->number(
            '-5', null, Validator::ASSERT_TRUTH
        ));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(0, null, Validator::ASSERT_TRUTH)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array('0', null, Validator::ASSERT_TRUTH)
        );    
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(0, null, Validator::ASSERT_TRUTH | Validator::ASSERT_INT)
        );
        // This assertion does what it says on the box
        $this->assertSame(5, $validator->number(
            5, null, Validator::ASSERT_POSITIVE
        ));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(-5, null, Validator::ASSERT_POSITIVE)
        );
        $this->assertEquals(0, $validator->number(
            '-0', null, Validator::ASSERT_POSITIVE
        ));
        // Assertions/filters that are meaningless for numbers do nothing
        $noopAssertions = array(
            Validator::ASSERT_NOT_NULL,
            Validator::ASSERT_SINGLE_EMAIL,
            Validator::FILTER_TO_NULL,
            Validator::FILTER_TRIM,
            Validator::FILTER_ADD_SCHEME
        );
        foreach ($noopAssertions as $assertion) {
            $this->assertSame(0, $validator->number(0, null, $assertion));
            $this->assertSame(5, $validator->number(5, null, $assertion));
            $this->assertSame(5, $validator->number('5', null, $assertion));
            $this->assertSame(-5 / 3, $validator->number(-5 / 3, null, $assertion));
            $this->assertSame(-1.667, $validator->number(round(-5 / 3, 3), null, $assertion));
            $this->assertSame((float)123450000, $validator->number('+0123.45e6', null, $assertion));
            $this->assertSame(16103058, $validator->number(0xf5b692, null, $assertion));
        }
        // We can assert a range
        $this->assertSame(2, $validator->number(2, null, 0, null, 3));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(5, null, 0, null, 3)
        );
        $this->assertSame(2, $validator->number(2, null, 0, 2));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(1, null, 0, 2)
        );
        $this->assertSame(
            -4.7, $validator->number(-4.7, null, 0, -5.928, -2.3826)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(-7.7, null, 0, -5.928, -2.3826)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(2, null, 0, -5.928, -2.3826)
        );
        /* We should get an exception if we pass something other than a number
        as a range boundary. */
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(5, null, 0, 'asdf')
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(5, null, 0, 0, 'asdf')
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'number'),
            array(5, null, 0, 'asdf', 'asdf')
        );
    }
    
    public function testPhoneValidation() {
        $validator = new Validator();
        /* The default phone number format is in three groups of digits with
        space separators. */
        $phoneNumbers = array(
            '123 456 7890',
            '123-456-7890',
            '(123) 456-7890',
            '  $  (123)  456 ** 7890 '
        );
        foreach ($phoneNumbers as $phoneNumber) {
            $this->assertEquals(
                '123 456 7890',
                $validator->phone($phoneNumber)
            );
        }
        // It won't like a country code though
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'phone'),
            array('1 (123) 456-4890')
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'phone'),
            array('00 852 3164 5429')
        );
        // Obviously it won't like junk like this
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'phone'),
            array('   asodfia ')
        );
        /* Input is passed through the string validator first, so the
        corresponding assertions and filters make sense. */
        $this->assertNull($validator->phone(null));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'phone'),
            array(null, null, Validator::ASSERT_NOT_NULL)
        );
        $this->assertNull($validator->phone(''));
        /* But passing an empty string without filtering to null doesn't work,
        because the idea is that the caller is going to the trouble to disable
        the filtering, she or he must really not want the data molested. */
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'phone'),
            array('', null, 0)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'phone'),
            array('', null, Validator::ASSERT_NOT_NULL | Validator::ASSERT_TRUTH)
        );
        $this->assertEquals('123 456 7890', $validator->phone(1234567890));
        $this->assertEquals('987 654 0123', $validator->phone(98765.40123));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'phone'),
            array(1234567890, null, Validator::ASSERT_TYPE_MATCH)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'phone'),
            array(98765.40123, null, Validator::ASSERT_TYPE_MATCH)
        );
        /* Because the format string is passed directly to sprintf(), we can
        format the number any way we choose. */
        $this->assertEquals('1 (012) 345-6789', $validator->phone(
            '012 345 6789', null, Validator::FILTER_DEFAULT, '1 (%s) %s-%s'
        ));
        $this->assertEquals('012x345x6789', $validator->phone(
            '012 345 6789', null, Validator::FILTER_DEFAULT, '%sx%sx%s'
        ));
        $this->assertEquals('foo', $validator->phone(
            '012 345 6789', null, Validator::FILTER_DEFAULT, 'foo'
        ));
        /* Since this call involves a nested call to another validator, make
        sure that the false return value still passes through correctly on bad
        input if exceptions are disabled. */
        $validator->disableExceptions();
        $this->assertFalse($validator->phone('asodifj'));
        /* Just to make sure that $this->assertFalse() does strict type
        comparison, the way it looks like it does in the code. */
        $this->assertNotFalse($validator->phone(''));
    }
    
    public function testURLValidation() {
        $validator = new Validator();
        /* This method attempts to construct a URL instance under the hood, so
        the same input that works there should work here. */
        $this->assertSame('http://www.foo.bar', $validator->URL(
            'http://www.foo.bar'
        ));
        $this->assertSame('https://www.foo.bar/?q=x', $validator->URL(
            'https://www.foo.bar/?q=x'
        ));
        // The default behavior trims whitespace and adds a scheme if missing
        $this->assertSame('http://www.foo.bar/baz', $validator->URL(
            'www.foo.bar/baz'
        ));
        $this->assertSame('https://www.foo.bar/baz#asdf', $validator->URL(
            "    https://www.foo.bar/baz#asdf\n"
        ));
        $this->assertSame(
            'http://www.foo.co.uk/asdf?foo=bar&bar=baz',
            $validator->URL("\twww.foo.co.uk/asdf?foo=bar&bar=baz   ")
        );
        // We can also select one or the other of those behaviors, or neither
        $this->assertSame('www.foo.bar/baz', $validator->URL(
            'www.foo.bar/baz', null, Validator::FILTER_TRIM
        ));
        $this->assertSame('https://www.foo.bar/baz#asdf', $validator->URL(
            "    https://www.foo.bar/baz#asdf\n", null, Validator::FILTER_TRIM
        ));
        $this->assertSame(
            'www.foo.co.uk/asdf?foo=bar&bar=baz',
            $validator->URL(
                "\twww.foo.co.uk/asdf?foo=bar&bar=baz   ",
                null,
                Validator::FILTER_TRIM
            )
        );
        $this->assertSame('http://www.foo.bar/baz', $validator->URL(
            'www.foo.bar/baz', null, Validator::FILTER_ADD_SCHEME
        ));
        $this->assertSame("    https://www.foo.bar/baz#asdf\n", $validator->URL(
            "    https://www.foo.bar/baz#asdf\n",
            null,
            Validator::FILTER_ADD_SCHEME
        ));
        $this->assertSame(
            "\thttp://www.foo.co.uk/asdf?foo=bar&bar=baz   ",
            $validator->URL(
                "\twww.foo.co.uk/asdf?foo=bar&bar=baz   ",
                null,
                Validator::FILTER_ADD_SCHEME
            )
        );
        $this->assertSame('www.foo.bar/baz', $validator->URL(
            'www.foo.bar/baz', null, 0
        ));
        $this->assertSame("    https://www.foo.bar/baz#asdf\n", $validator->URL(
            "    https://www.foo.bar/baz#asdf\n", null, 0
        ));
        $this->assertSame(
            "\twww.foo.co.uk/asdf?foo=bar&bar=baz   ",
            $validator->URL(
                "\twww.foo.co.uk/asdf?foo=bar&bar=baz   ", null, 0
            )
        );
        // We can filter to upper- or lowercase
        $this->assertSame('http://www.foo.com/asdf', $validator->URL(
            '    www.Foo.Com/ASDF',
            null,
            Validator::FILTER_DEFAULT_URL | Validator::FILTER_LOWERCASE
        ));
        $this->assertSame('HTTPS://WWW.FOO.COM/ASDF', $validator->URL(
            'https://www.Foo.Com/ASDF',
            null,
            Validator::FILTER_DEFAULT_URL | Validator::FILTER_UPPERCASE
        ));
        /* Passing a URL object doesn't work because the string validation that
        happens as the first stage expects a scalar. */
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'URL'),
            array(new URL('http://www.google.com/'))
        );
        // This is technically valid
        $this->assertEquals('http://7', $validator->URL(7));
        // Unless we assert the correct type
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'URL'),
            array(7, null, Validator::ASSERT_TYPE_MATCH)
        );
        // This is also technically valid
        $this->assertEquals('http://asdf', $validator->URL('asdf'));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'URL'),
            array('-289376;aA$O^*&://www.foo.com')
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'URL'),
            array('http://-foo.com')
        );
        // The default filter trims and coerces empty values to null
        $this->assertNull($validator->URL(null));
        $this->assertNull($validator->URL(''));
        $this->assertNull($validator->URL("   \n"));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'URL'),
            array(null, null, 0)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'URL'),
            array('', null, 0)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'URL'),
            array("   \n", null, 0)
        );
        /* We can assert a maximum length, though not a minimum length, because
        I can't see the utility of that. */
        $this->assertEquals('http://www.foobarbaz.com', $validator->URL(
            'http://www.foobarbaz.com'
        ));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'URL'),
            array('http://www.foobarbaz.com', null, 0, 10)
        );
    }
    
    public function testEmailValidation() {
        $validator = new Validator();
        $this->assertEquals('me@website.com', $validator->email(
            'me@website.com'
        ));
        $this->assertEquals(
            'me@website.com,you@some-other-website.com',
            $validator->email('me@website.com,you@some-other-website.com')
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'email'),
            array('me@you')
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'email'),
            array('asdfasdf')
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'email'),
            array(42)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'email'),
            array('me@website.com,asdf')
        );
        // Extra whitespace is trimmed by default
        $this->assertEquals('some.guy@www.some-website.com', $validator->email(
            "\nsome.guy@www.some-website.com    "
        ));
        $this->assertEquals(
            'some.guy@www.some-website.com,mailer-daemon@yahoo.biz',
            $validator->email(
                "\nsome.guy@www.some-website.com , mailer-daemon@yahoo.biz"
            )
        );
        // This may be disabled
        $this->assertEquals(
            "\nsome.guy@www.some-website.com    ",
            $validator->email("\nsome.guy@www.some-website.com    ", null, 0)
        );
        $this->assertEquals(
            "\nsome.guy@www.some-website.com    , mailer-daemon@yahoo.biz",
            $validator->email(
                "\nsome.guy@www.some-website.com    , mailer-daemon@yahoo.biz",
                null,
                0
            )
        );
        // Filter to upper- or lowercase
        $this->assertEquals('hello@me.com', $validator->email(
            'hELLO@ME.COM  ',
            null,
            Validator::FILTER_DEFAULT | Validator::FILTER_LOWERCASE
        ));
        
        $this->assertEquals('HELLO@ME.COM,HELLO@YOU.COM', $validator->email(
            'hELLO@ME.COM , Hello@you.com',
            null,
            Validator::FILTER_DEFAULT | Validator::FILTER_UPPERCASE
        ));
        // The default filtering trims and coerces empty values to null
        $this->assertNull($validator->email(null));
        $this->assertNull($validator->email(''));
        $this->assertNull($validator->email("   \n"));
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'email'),
            array(null, null, 0)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'email'),
            array('', null, 0)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'email'),
            array("   \n", null, 0)
        );
        /* By default, comma-delimited lists of email address are treated as
        valid. This may be disabled with an assertion. */
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'email'),
            array(
                'me@website.com,you@some-other-website.com',
                null,
                Validator::FILTER_DEFAULT | Validator::ASSERT_SINGLE_EMAIL
            )
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'email'),
            array(
                "\nsome.guy@www.some-website.com , mailer-daemon@yahoo.biz",
                null,
                Validator::FILTER_DEFAULT | Validator::ASSERT_SINGLE_EMAIL
            )
        );
    }
    
    public function testEnumValidation() {
        $validator = new Validator();
        // This method should fail if we haven't set up the enum list
        $this->assertThrows(
            'LogicException',
            array($validator, 'enum'),
            array('foo')
        );
        $validator->setEnumValues(array(
            '',
            '0',
            0,
            false,
            '1',
            'foo',
            ' BAR '
        ));
        // Passing anything that's not a scalar should throw an exception
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'enum'),
            array(array())
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'enum'),
            array(new stdClass())
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'enum'),
            array(null)
        );
        // But we can get nulls to validate if we pass the proper assertion
        $this->assertNull(
            $validator->enum(null, null, Validator::ASSERT_ALLOW_NULL)
        );
        /* If we don't pass any options, we get loose matching. Note that
        boolean true was not included in the enumeration. */
        $this->assertSame(true, $validator->enum(true));
        // Asserting the type should stop that from working
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'enum'),
            array(true, null, Validator::ASSERT_TYPE_MATCH)
        );
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'enum'),
            array(1, null, Validator::ASSERT_TYPE_MATCH)
        );
        $this->assertSame(
            '1', $validator->enum('1', null, Validator::ASSERT_TYPE_MATCH)
        );
        $this->assertSame(
            false, $validator->enum(false, null, Validator::ASSERT_TYPE_MATCH)
        );
        $this->assertSame(
            '0', $validator->enum('0', null, Validator::ASSERT_TYPE_MATCH)
        );
        $this->assertSame(
            0, $validator->enum(0, null, Validator::ASSERT_TYPE_MATCH)
        );
        $this->assertSame(
            '', $validator->enum('', null, Validator::ASSERT_TYPE_MATCH)
        );
        // The trim filter could also be useful
        $this->assertSame(
            '', $validator->enum('     ', null, Validator::FILTER_TRIM)
        );
        $this->assertSame(
            'foo', $validator->enum('    foo', null, Validator::FILTER_TRIM)
        );
        /* This won't work though, because the string is trimmed before it is
        tested for presence in the enumeration. */
        $this->assertThrows(
            'InvalidArgumentException',
            array($validator, 'enum'),
            array(' BAR ', null, Validator::FILTER_TRIM)
        );
    }
}
?>