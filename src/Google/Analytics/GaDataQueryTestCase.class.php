<?php
namespace Google\Analytics;

class GaDataQueryTestCase extends \TestHelpers\TestCase {
    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        /* Since we will have set up a temporary file to log exceptions by
        virtue of instantiating the mock API object, we need to undo it to set
        up any remaining tests correctly. */
        RuntimeException::unregisterLogger();
    }
    
    /**
     * Tests that we get the proper result when setting a profile summary,
     * whether it's by ID, by name, or the object itself.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProfileSetting() {
        // For this test we will need a mock API object
        define('GOOGLE_ANALYTICS_API_AUTH_EMAIL', 'foo@bar.baz');
        define('GOOGLE_ANALYTICS_API_AUTH_KEYFILE', __FILE__);
        define('GOOGLE_ANALYTICS_API_DATA_DIR', sys_get_temp_dir());
        define('GOOGLE_ANALYTICS_API_LOG_FILE', GOOGLE_ANALYTICS_API_DATA_DIR . DIRECTORY_SEPARATOR . 'log.txt');
        $profile1 = new ProfileSummary();
        $profile1->setID('1');
        $profile1->setName('Foo');
        $profile2 = new ProfileSummary();
        $profile2->setID('2');
        $profile2->setName('Bar');
        $profile3 = new ProfileSummary();
        $profile3->setID('3');
        $profile3->setName('Baz');
        $api = $this->getMockBuilder(__NAMESPACE__ . '\API')
                    ->setMethods(array(
                        'getProfileSummaryByID', 'getProfileSummaryByName'
                    ))->getMock();
        $api->method('getProfileSummaryByID')->will($this->returnValue($profile1));
        $api->method('getProfileSummaryByName')->will($this->returnValue($profile2));
        $q = new GaDataQuery();
        // First of all, make sure this exception is thrown when necessary
        $q->setProfile('1');
        $this->assertThrows(
            __NAMESPACE__ . '\LogicException', array($q, 'getProfile')
        );
        $q->setAPIInstance($api);
        /* Now we should get profile #1 (which has nothing to do with the
        parameter we passed when we set the profile; it's because of the mocked
        method). */
        $this->assertSame($profile1, $q->getProfile());
        /* If we set a profile by its name, the existing one should be cleared
        out. */
        $q->setProfileName('Bar');
        $this->assertSame($profile2, $q->getProfile());
        // And if we set a profile object explicitly, we should always get that
        foreach (array($profile1, $profile2, $profile3) as $profile) {
            $q->setProfile($profile);
            $this->assertSame($profile, $q->getProfile());
        }
    }
    
    /**
     * Tests to make sure that the setters for dimensions and metrics handle
     * the presence or absence of the 'ga:' prefix gracefully.
     */
    public function testColumnPrefixAutoFill() {
        $q = new GaDataQuery();
        $q->setMetrics('foo,bar,baz');
        $expected = array('ga:foo', 'ga:bar', 'ga:baz');
        $this->assertEquals($expected, $q->getMetrics());
        $q->setDimensions('foo,ga:bar,baz');
        $this->assertEquals($expected, $q->getDimensions());
        $q->setMetrics(array('ga:foo', 'bar', 'ga:baz'));
        $this->assertEquals($expected, $q->getMetrics());
        $q->setDimensions(array('foo', 'bar', 'baz'));
        $this->assertEquals($expected, $q->getDimensions());
    }
    
    /**
     * Tests to make sure we can set sampling levels either by their string
     * values or constant values and that attempts to pass bogus values are
     * intercepted.
     */
    public function testSetSamplingLevel() {
        $q = new GaDataQuery();
        $q->setSamplingLevel('FASTER');
        $this->assertSame(
            GaDataQuery::SAMPLING_LEVEL_FASTER, $q->getSamplingLevel()
        );
        $q->setSamplingLevel(GaDataQuery::SAMPLING_LEVEL_HIGHER_PRECISION);
        // We can get it as a string too
        $this->assertEquals('HIGHER_PRECISION', $q->getSamplingLevel(true));
        $q->setSamplingLevel('NONE');
        // Special case if we get this as a string
        $this->assertEquals('DEFAULT', $q->getSamplingLevel(true));
        $this->assertSame(
            GaDataQuery::SAMPLING_LEVEL_NONE, $q->getSamplingLevel()
        );
        $this->assertThrows(
            __NAMESPACE__ . '\InvalidArgumentException',
            array($q, 'setSamplingLevel'),
            array('asdfoij')
        );
    }
    
    /**
     * Tests whether date formatting is respected.
     */
    public function testDateFormatting() {
        $q = new GaDataQuery();
        $q->setStartDate('2015-06-01');
        $this->assertEquals('2015-06-01', $q->iteration());
        $q->setFormatString('d/m/Y');
        $this->assertEquals('01/06/2015', $q->iteration());
    }
    
    /**
     * Tests whether queries with different parameters return different hashes.
     */
    public function testHashing() {
        $profile = new ProfileSummary();
        $profile->setID('1');
        $profile->setName('Foo');
        $q = new GaDataQuery();
        $q->setStartDate('2014-07-01');
        $q->setEndDate('2014-08-01');
        $q->setProfile($profile);
        $q->setMetrics(array('foo', 'bar', 'baz'));
        $hash = $q->getHash();
        $q2 = clone $q;
        $this->assertEquals($hash, $q2->getHash());
        // Changing the profile name shouldn't matter
        $q2->getProfile()->setName('Bar');
        $this->assertEquals($hash, $q2->getHash());
        // But changing its ID will
        $q2->getProfile()->setID('2');
        $this->assertNotEquals($hash, $q2->getHash());
        // Unless the change is just adding the prefix
        $q2->getProfile()->setID('ga:1');
        $this->assertEquals($hash, $q2->getHash());
        // Adding a new parameter should change the hash
        $q2->setDimensions('a,b,c');
        $this->assertNotEquals($hash, $q2->getHash());
        // Reset
        $q->setDimensions($q2->getDimensions());
        $hash = $q->getHash();
        $this->assertEquals($hash, $q2->getHash());
        /* Using the same list elements but changing their order should change
        the hash. */
        $q2->setMetrics(array('bar', 'baz', 'foo'));
        $this->assertNotEquals($hash, $q2->getHash());
        // Reset
        $q2->setMetrics($q->getMetrics());
        $this->assertEquals($hash, $q2->getHash());
        // This is a special case and sholudn't affect the hash
        $q2->setSamplingLevel('NONE');
        $this->assertEquals($hash, $q2->getHash());
    }
    
    /**
     * Tests to make sure the array representation of the query is accurate.
     */
    public function testArrayRepresentation() {
        $q = new GaDataQuery();
        /* It shouldn't be possible to get the query's array representation
        until the minimum required properties have been set. */
        $this->assertThrows(
            __NAMESPACE__ . '\LogicException',
            array($q, 'getAsArray')
        );
        $profile = new ProfileSummary();
        $profile->setID('123');
        $profile->setName('foo');
        $q->setProfile($profile);
        $this->assertThrows(
            __NAMESPACE__ . '\LogicException',
            array($q, 'getAsArray')
        );
        $q->setStartDate('2015-03-15');
        $this->assertThrows(
            __NAMESPACE__ . '\LogicException',
            array($q, 'getAsArray')
        );
        $q->setEndDate('2015-03-31');
        $this->assertThrows(
            __NAMESPACE__ . '\LogicException',
            array($q, 'getAsArray')
        );
        $q->setMetrics(array('a_metric', 'another_metric'));
        $expected = array(
            'ids' => 'ga:123',
            'start-date' => '2015-03-15',
            'end-date' => '2015-03-31',
            'metrics' => 'ga:a_metric,ga:another_metric',
            'start-index' => 1,
            'max-results' => GOOGLE_ANALYTICS_API_PAGE_SIZE
        );
        $this->assertEquals($expected, $q->getAsArray());
        $q->setMaxResults(234);
        $expected['max-results'] = 234;
        $this->assertEquals($expected, $q->getAsArray());
        $q->setStartIndex(1000);
        $expected['start-index'] = 1000;
        $this->assertEquals($expected, $q->getAsArray());
        $q->setMetrics('foo,bar,baz');
        $expected['metrics'] = 'ga:foo,ga:bar,ga:baz';
        $this->assertEquals($expected, $q->getAsArray());
        $profile = new ProfileSummary();
        $profile->setID('987987');
        $profile->setName('asdf');
        $q->setProfile($profile);
        $expected['ids'] = 'ga:987987';
        $this->assertEquals($expected, $q->getAsArray());
        $q->setStartDate('2000-01-01');
        $q->setEndDate('2000-12-31');
        $expected['start-date'] = '2000-01-01';
        $expected['end-date'] = '2000-12-31';
        $this->assertEquals($expected, $q->getAsArray());
        $q->setSamplingLevel(GaDataQuery::SAMPLING_LEVEL_FASTER);
        $expected['samplingLevel'] = 'FASTER';
        $this->assertEquals($expected, $q->getAsArray());
        // Note the special case here
        $q->setSamplingLevel(GaDataQuery::SAMPLING_LEVEL_NONE);
        unset($expected['samplingLevel']);
        $this->assertEquals($expected, $q->getAsArray());
        $q->setDimensions(array('asdfasdf', 'oijoij'));
        $expected['dimensions'] = 'ga:asdfasdf,ga:oijoij';
        $this->assertEquals($expected, $q->getAsArray());
        $sort = new GaDataSortOrder();
        $sort->addField('foo');
        $sort->addField('bar', SORT_DESC);
        $q->setSort($sort);
        $expected['sort'] = (string)$sort;
        $this->assertEquals($expected, $q->getAsArray());
        $filter = new GaDataFilterCollection(
            GaDataFilterCollection::OP_AND,
            new GaDataFilterCollection(
                GaDataFilterCollection::OP_OR,
                new GaDataConditionalExpression('foo', GaDataConditionalExpression::OP_GE, 1)
            ),
            new GaDataFilterCollection(
                GaDataFilterCollection::OP_OR,
                new GaDataConditionalExpression('bar', GaDataConditionalExpression::OP_NE, 'baz')
            )
        );
        $q->setFilter($filter);
        $expected['filters'] = (string)$filter;
        $this->assertEquals($expected, $q->getAsArray());
        $segment = new GaDataSegment(
            new GaDataSegmentConditionGroup(
                new GaDataSegmentSimpleCondition(
                    'foo', GaDataSegmentSimpleCondition::OP_LT, 20
                )
            ),
            GaDataSegment::SCOPE_USERS,
            new GaDataSegmentSequence(
                new GaDataSegmentSequenceCondition(
                    'bar', GaDataSegmentSequenceCondition::OP_EQ, 'baz'
                ),
                new GaDataSegmentSequenceCondition(
                    'baz', GaDataSegmentSequenceCondition::OP_GT, 0, GaDataSegmentSequenceCondition::OP_FOLLOWED_BY_IMMEDIATE
                )
            ),
            GaDataSegment::SCOPE_SESSIONS
        );
        $q->setSegment($segment);
        $expected['segment'] = (string)$segment;
        $this->assertEquals($expected, $q->getAsArray());
    }
    
    /**
     * Tests to make sure the email subject line reflects the name property
     * where present.
     */
    public function testEmailSubject() {
        $q = new GaDataQuery();
        $q->setStartDate('2013-07-28');
        $q->setEndDate('2013-11-03');
        $profile = new ProfileSummary();
        $profile->setID('123');
        $profile->setName('Foo');
        $q->setProfile($profile);
        $this->assertEquals(
            'Google Analytics report for profile "Foo" for 2013-07-28 through 2013-11-03',
            $q->getEmailSubject()
        );
        $q->setName('Bar');
        $this->assertEquals(
            'Google Analytics report "Bar" for 2013-07-28 through 2013-11-03',
            $q->getEmailSubject()
        );
    }
}
?>