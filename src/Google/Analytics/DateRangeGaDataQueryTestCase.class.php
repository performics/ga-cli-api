<?php
namespace Google\Analytics;

class DateRangeGaDataQueryTestCase extends \TestHelpers\TestCase {
    /**
     * Tests iteration over various date intervals.
     */
    public function testIteration() {
        $q = new DateRangeGaDataQuery();
        $q->setSummaryStartDate('2012-01-01');
        $q->setSummaryEndDate('2012-04-30');
        $q->setIterationInterval(new \DateInterval('P1M'));
        $profile = new ProfileSummary();
        $profile->setID('789');
        $profile->setName('Foo');
        $q->setProfile($profile);
        $q->setMetrics(array('foo', 'bar'));
        $sort = new GaDataSortOrder();
        $sort->addField('foo');
        $sort->addField('bar', SORT_DESC);
        $q->setSort($sort);
        $segment = new GaDataSegment(
            new GaDataSegmentConditionGroup(
                new GaDataSegmentSimpleCondition(
                    'foo', GaDataSegmentSimpleCondition::OP_LT, 20
                )
            ),
            GaDataSegment::SCOPE_USERS
        );
        $q->setSegment($segment);
        $expected = array();
        $expectedInstance = array(
            'start-date' => '2012-01-01',
            'end-date' => '2012-01-31',
            'ids' => 'ga:789',
            'metrics' => 'ga:foo,ga:bar',
            'start-index' => 1,
            'max-results' => GOOGLE_ANALYTICS_API_PAGE_SIZE,
            'sort' => (string)$sort,
            'segment' => (string)$segment
        );
        $expected[] = $expectedInstance;
        $expectedInstance['start-date'] = '2012-02-01';
        $expectedInstance['end-date'] = '2012-02-29';
        $expected[] = $expectedInstance;
        $expectedInstance['start-date'] = '2012-03-01';
        $expectedInstance['end-date'] = '2012-03-31';
        $expected[] = $expectedInstance;
        $expectedInstance['start-date'] = '2012-04-01';
        $expectedInstance['end-date'] = '2012-04-30';
        $expected[] = $expectedInstance;
        $result = array();
        do {
            $result[] = $q->getAsArray();
        } while ($q->iterate());
        $this->assertEquals($expected, $result);
        /* If the end date falls in the middle of an interval, the interval
        should be shortened. */
        $q->setSummaryStartDate('2015-05-03');
        $q->setSummaryEndDate('2015-05-20');
        $q->setIterationInterval(new \DateInterval('P1W'));
        $expected = array();
        $expectedInstance['start-date'] = '2015-05-03';
        $expectedInstance['end-date'] = '2015-05-09';
        $expected[] = $expectedInstance;
        $expectedInstance['start-date'] = '2015-05-10';
        $expectedInstance['end-date'] = '2015-05-16';
        $expected[] = $expectedInstance;
        $expectedInstance['start-date'] = '2015-05-17';
        $expectedInstance['end-date'] = '2015-05-20';
        $expected[] = $expectedInstance;
        $result = array();
        do {
            $result[] = $q->getAsArray();
        } while ($q->iterate());
        $this->assertEquals($expected, $result);
    }
}
?>