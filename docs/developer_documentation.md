# Developer Documentation

While the command-line query runner included in the Performics Google Analytics API Interface provides a good deal of flexibility by default, developers may be interested in working with the application at a lower level. This document provides a brief explanation of the most important PHP classes within this application that developers may instantiate and extend within their own applications, as well as some brief tutorials on how to use and extend these classes. This documentation should not be treated as comprehensive; for a complete understanding of the application's internals, it is best to review the code directly.

## Altering the Report Format

The `query.php` runner supports the customization of report formats via its `--formatter` argument, which allows the specification of an arbitrary subclass of `Google\Analytics\ReportFormatter`. By extending this class, developers may produce reports in any desired format. The following methods are most useful for doing this:

	namespace Google\Analytics;
	
	class ReportFormatter {
		protected $_fileHandle;
		protected $_bytesWritten = 0;
		
		/**
		 * @param array, string $data
		 */
		protected function _write($data) {
			/* Writes either a line of data or an array of data points to the
			file handle, depending on the argument. */
		}
		
		/**
		 * @param array $headers
		 */
		public function writeHeaders(array $headers) {
			$this->_write($headers);
		}
	
		/**
		 * @param array $row
		 */
		public function writeRow(array $row) {
			$this->_write($row);
		}
	}
	
Writing a report formatter that always omits the header row is as simple as doing this:

	class NoHeaderReportFormatter extends Google\Analytics\ReportFormatter {
		public function writeHeaders(array $headers) {
			return;
		}
	}
	
It would also be trivial to write a formatter that embeds the column labels in the data:

	class EmbeddedLabelReportFormatter extends Google\Analytics\ReportFormatter {
		private $_headers;
		private $_headerCount;
		
		public function writeHeaders(array $headers) {
			$this->_headers = $headers;
			$this->_headerCount = count($headers);
		}
		
		public function writeRow(array $row) {
			for ($i = 0; $i < $this->_headerCount; $i++) {
				$row[$i] = sprintf('%s: %s', $this->_headers[$i], $row[$i]);
			}
			$this->_write($row);
		}
	}
	
See the section on the `Google\Analytics\ReportFormatter` class later in this document for a more complete list of this class' methods.
	
## Instantiating Filters and Segments

The Google Analytics API syntax for filters and segments is fairly nuanced. In order to support the programmatic construction of filters and segments, this application makes available a number of PHP classes. The use of these classes is described here.

### Filters

Filters are composed of one or more conditional expressions that take the form _column-name_ _operator_ _operand_. Multiple such expressions may be combined using OR (,) or AND (;) operators. In the Performics Google Analytics API Interface, the smallest unit of a filter is a `Google\Analytics\GaDataConditionalExpression` instance, which is instantiated as follows:

	new Google\Analytics\GaDataConditionalExpression($columnName, $operator, $operand);
	
These units are combined in instances of the `Google\Analytics\GaDataFilterCollection` class. For example, the following code constructs a filter that restricts the data to only those actions that took place in Chicago later than noon in the given time period:

	$filter = new Google\Analytics\GaDataFilterCollection(
		Google\Analytics\GaDataFilterCollection::OP_AND,
		new Google\Analytics\GaDataConditionalExpression(
			'city',
			Google\Analytics\GaDataConditionalExpression::OP_EQ,
			'Chicago'
		),
		new Google\Analytics\GaDataConditionalExpression(
			'hour',
			Google\Analytics\GaDataConditionalExpression::OP_GE,
			'12'
		)
	);
	
The string representation of this filter is `ga:city==Chicago;ga:hour>=12`.

The OR and AND operators may be combined in filters, with OR having greater precedence. In this application, one accomplishes this by nesting one `Google\Analytics\GaDataFilterCollection` instance inside of another. For example, the following code constructs a filter that restricts the data to only those actions that took place in Chicago, either after noon or on Thursday:

	$filter = new Google\Analytics\GaDataFilterCollection(
		Google\Analytics\GaDataFilterCollection::OP_AND,
		new Google\Analytics\GaDataConditionalExpression(
			'city',
			Google\Analytics\GaDataConditionalExpression::OP_EQ,
			'Chicago'
		),
		new Google\Analytics\GaDataFilterCollection(
			Google\Analytics\GaDataFilterCollection::OP_OR,
			new Google\Analytics\GaDataConditionalExpression(
				'hour',
				Google\Analytics\GaDataConditionalExpression::OP_GE,
				'12'
			),
			new Google\Analytics\GaDataConditionalExpression(
				'dayOfWeek',
				Google\Analytics\GaDataConditionalExpression::OP_EQ,
				4
			)
		)
	);
	
The string representation of this filter is `ga:city==Chicago;ga:hour>=12,ga:dayOfWeek==4`.

### Segments

In the Google Analytics API, segments are similar to filters, but more complex in that they permit the isolation of the data to sessions for users for whom a certain condition was true at any point in the time period, or to sessions where a specific sequence of actions took place. There are two types of conditions that could be present in a segment: simple conditions and sequences. These are represented in this application by the `Google\Analytics\GaDataSegmentConditionGroup` and `Google\Analytics\GaDataSegmentSequence` classes respectively. Instances of `Google\Analytics\GaDataSegmentConditionGroup` contain one or more instances of `Google\Analytics\GaDataSegmentSimpleCondition`, while instances of `Google\Analytics\GaDataSegmentSequence` contain one or more instances of `Google\Analytics\GaDataSegmentSequenceCondition`.

The `Google\Analytics\GaDataSegmentSimpleCondition` class extends `Google\Analytics\GaDataConditionalExpression`, and it is instantiated as described in the section about filters above. The difference between these two classes is that `Google\Analytics\GaDataSegmentSimpleCondition` adds support for two operators that do not exist in filters, both of which accept arrays as operands; these are the "between" (<>) and "in" ([]) operators. For example, the following condition dictates that unique purchases be between 1 and 5:

	new Google\Analytics\GaDataSegmentSimpleCondition(
		'uniquePurchases',
		Google\Analytics\GaDataSegmentSimpleCondition::OP_BETWEEN,
		array(1, 5)
	);
	
The `Google\Analytics\GaDataSegmentSequenceCondition` class extends the `Google\Analytics\GaDataSegmentSimpleCondition` class to add support for sequence operators. For example, the following condition specifies a visit from the Firefox browser that takes place after some other condition (not specified here):

	new Google\Analytics\GaDataSegmentSequenceCondition(
		'browser',
		Google\Analytics\GaDataSegmentSequenceCondition::OP_EQ,
		'Firefox',
		Google\Analytics\GaDataSegmentSequenceCondition::OP_FOLLOWED_BY
	);
	
The segment as a whole is represented by an instance of the `Google\Analytics\GaDataSegment` class, which has as its members one or more instances of the `Google\Analytics\GaDataSegmentConditionGroup` or `Google\Analytics\GaDataSegmentSequence` classes. Accompanying each such instance must be a declaration of scope; this may be equal to either "users" or "sessions" (represented by the `Google\Analytics\GaDataSegment::SCOPE_USERS` and `Google\Analytics\GaDataSegment::SCOPE_SESSIONS` constants respectively). For example, the following segment selects sessions from the city of London from users that had at least 1 session where the Chrome browser was used:

	new Google\Analytics\GaDataSegment(
        new Google\Analytics\GaDataSegmentConditionGroup(
                new Google\Analytics\GaDataSegmentSimpleCondition(
                        'browser',
                        Google\Analytics\GaDataSegmentSimpleCondition::OP_EQ,
                        'Chrome'
                )
        ),
        Google\Analytics\GaDataSegment::SCOPE_USERS,
        new Google\Analytics\GaDataSegmentConditionGroup(
                new Google\Analytics\GaDataSegmentSimpleCondition(
                        'city',
                        Google\Analytics\GaDataSegmentSimpleCondition::OP_EQ,
                        'London'
                )
        ),
        Google\Analytics\GaDataSegment::SCOPE_SESSIONS
	);

The string representation of this segment is `users::condition::ga:browser==Chrome;sessions::condition::ga:city==London`.

Segments may incorporate both simple conditions and sequences. The following segment selects users that had at least 1 session where the Chrome browser was used, and that had their first interaction with the website via London, followed at any point by an interaction via Oxford:

	new Google\Analytics\GaDataSegment(
        new Google\Analytics\GaDataSegmentConditionGroup(
                new Google\Analytics\GaDataSegmentSimpleCondition(
                        'browser',
                        Google\Analytics\GaDataSegmentSimpleCondition::OP_EQ,
                        'Chrome'
                )
        ),
        Google\Analytics\GaDataSegment::SCOPE_USERS,
        new Google\Analytics\GaDataSegmentSequence(
                new Google\Analytics\GaDataSegmentSequenceCondition(
                        'city',
                        Google\Analytics\GaDataSegmentSequenceCondition::OP_EQ,
                        'London',
                        Google\Analytics\GaDataSegmentSequenceCondition::OP_FIRST_HIT_MATCHES_FIRST_STEP
                ),
                new Google\Analytics\GaDataSegmentSequenceCondition(
                        'city',
                        Google\Analytics\GaDataSegmentSequenceCondition::OP_EQ,
                        'Oxford',
                        Google\Analytics\GaDataSegmentSequenceCondition::OP_FOLLOWED_BY
                )
        ),
        Google\Analytics\GaDataSegment::SCOPE_USERS
	);
	
The string representation of this segment is `users::condition::ga:browser==Chrome;users::sequence::^ga:city==London;->>ga:city==Oxford`.

## Interfaces

This application defines several interfaces that developers may use to provide their own implementations of various components.

### `OAuth\IService`

This interface makes it possible for developers to substitute their own OAuth backend implementations in subclasses of `Google\Analytics\API`. It specifies the following method:

##### `string getToken()`

This method should return a valid OAuth token.

### `Google\Analytics\IQuery extends Iterator`

This interface facilitates alternative implementations of `Google\Analytics\GaDataQuery`. In addition to the `Iterator` methods, it specifies the following:

##### `string getEmailSubject()`

This method should return a string that will be used as the email subject when generating an email containing the results of this query.

##### `string iteration()`

This method should return a string that describes what iteration the query is currently on.

## Classes

The PHP classes described here provide the majority of the functionality in the Performics Google Analytics API Interface.

### `Google\Analytics\ReportFormatter`

Defines how Google Analytics API data should be written in files.

#### Public Methods

##### `__construct()`

##### `void setFileName(string $fileName)`

Opens up a handle to the given file name and prepares to receive data.

##### `void openTempFile(string $dir)`

Creates a temporary file in the given directory and prepares to receive data.

##### `void setSeparator(string $separator)`

Sets a new separator, which will be written between individual report instances in the file.

##### `void setReportCount(int $count)`

Sets the total number of reports to be written by this instance.

##### `void writeMetadata(string $description, Google\Analytics\ProfileSummary $profile, DateTime $startDate, DateTime $endDate)`

Writes a set of metadata to precede a report.

##### `void writeHeaders(array $headers)`

Writes a row of headers (i.e. Google Analytics column names) in a report.

##### `void writeRow(array $row)`

Writes a row of data in a report.

##### `string getFileName()`

Returns the instance's file name.

##### `int getBytesWritten()`

Returns the number of bytes written by the instance.

##### `string getSeparator()`

Returns the instance's inter-query separator string.

#### Protected Properties and Methods

Subclasses of `Google\Analytics\ReportFormatter` have access to the following protected properties and methods:

##### `$_fileName`

The instance's file name.

##### `$_fileHandle`

The instance's file handle.

##### `$_bytesWritten`

The number of bytes written by the instance.

##### `$_reportIndex`

The 1-based numeric index of the report the instance is in the process of writing. This is incremented with each call to the default implementation of `Google\Analytics\ReportFormatter::writeMetadata()`.

##### `$_reportCount`

The total number of reports to be written by the instance.

##### `$_separator`

The string that will be written between each individual data set when writing multiple queries to the same file.

##### `void _write(array, string $data)`

Writes data to the file handle. If the argument is an array, it is written using `fputcsv()`; otherwise it is written using `fwrite()` and terminated with `PHP_EOL`.

### `Google\Analytics\API extends Google\ServiceAccountAPI`

Handles all aspects of communication with the Google Analytics API.

#### Public Methods

##### `__construct()`

##### `static string, array addPrefix(string, array $arg)`

Adds the 'ga:' prefix, if necessary, to the argument. If the argument is an array, this method does this on every element in the array. Returns the modified version of the argument.

##### `Google\Analytics\GaData query(Google\Analytics\GaDataQuery $query)`

Performs a Google Analytics query with the given `Google\Analytics\GaDataQuery` object and returns the result as a `Google\Analytics\GaData` object.

##### `int queryToFile(Google\Analytics\IQuery $query, Google\Analytics\ReportFormatter $formatter)`

Performs a Google Analytics query with the given `Google\Analytics\IQuery` object and writes the result to a file using the given `Google\Analytics\ReportFormatter` object. Returns the number of bytes written.

##### `void queryToEmail(Google\Analytics\IQuery $query, Google\Analytics\ReportFormatter $formatter, Email $email, string $file = null)`

Performs a Google Analytics query with the given `Google\Analytics\IQuery` object and attaches the result to the given `Email` instance. If the email does not yet have a subject, one will be set automatically. If failures took place while running the query (e.g. the query declared a preference for no data sampling and one or more iterations contained sampled data), a message regarding the failure will be appended to the email automatically. If a file path is provided as the fourth argument, the report will be copied to that location in addition to being attached to the email.

##### `array getFailedIterations()`

Gets an array containing the iterations that failed during the previous iterative query (if any) due to the presence of sampled data. The data type contained within this return value will vary depending on what the query object's iteration() method returns.

##### `string getFailedIterationsMessage()`

Gets a message explaining how many iterations failed in the last query and why.

##### `Google\Analytics\Column getColumn(string $name)`

Returns the object representation of the specified column.

##### `array getDimensions(boolean $includeDeprecated = false)`

Returns a list of available dimension names, optionally including deprecated dimensions.

##### `array getMetrics(boolean $includeDeprecated = false)`

Returns a list of available metric names, optionally including deprecated metric.

##### `array getSegments()`

Returns an array of `Google\Analytics\Segment` objects that describe the preset segments to which the effective credentials permit access.

##### `array getAccountSummaries()`

Returns an array of `Google\Analytics\AccountSummary` objects that describe the accounts/profiles/views to which the effective credentials permit access.

##### `Google\Analytics\AccountSummary getAccountSummaryByID(string $id)`

Given an account ID, returns a `Google\Analytics\AccountSummary` object.

##### `Google\Analytics\AccountSummary getAccountSummaryByName(string $name)`

Given an account name, returns a `Google\Analytics\AccountSummary` object.

##### `Google\Analytics\WebPropertySummary getWebPropertySummaryByID(string $id)`

Given a web property ID, returns a `Google\Analytics\WebPropertySummary` object.

##### `Google\Analytics\WebPropertySummary getWebPropertySummaryByName(string $name)`

Given a web property name, returns a `Google\Analytics\WebPropertySummary` object.

##### `Google\Analytics\ProfileSummary getProfileSummaryByID(string $id)`

Given a profile ID, returns a `Google\Analytics\ProfileSummary` object.

##### `Google\Analytics\ProfileSummary getProfileSummaryByName(string $name)`

Given a profile name, returns a `Google\Analytics\ProfileSummary` object.

##### `void clearAccountCache()`

Forces the next call to any of the methods that retrieve account summaries or members thereof to cause a new query to the Google API.

##### `void clearColumnCache()`

Forces the next call to any of the methods that retrieve dimensions or metrics to cause a new query to the Google API.

#### Protected Methods

##### `OAuth\IService _getOAuthService()`

Returns an instance of the OAuth service through which this instance will get its authorization tokens. Subclasses may override this method to integrate a custom interface for retrieving OAuth tokens.

### `Google\Analytics\GaDataQuery extends Google\Analytics\AbstractNamedAPIResponseObject implements IQuery`

An object representation of a query to the Google Analytics API.

#### Class Constants

##### `SAMPLING_LEVEL_DEFAULT`

Represents the Google Analytics API's default sampling level.

##### `SAMPLING_LEVEL_FASTER`

Represents the Google Analytics API's sampling level that achieves a faster response at the expense of more sampling.

##### `SAMPLING_LEVEL_HIGHER_PRECISION`

Represents the Google Analytics API's sampling level that achieves greater precision at the expense of a slower response.

##### `SAMPLING_LEVEL_NONE`

Represents an assertion that will cause an error to take place if the Google Analytics API returns a response that contains sampled data.

##### `THIS_WEEK_START`

A shortcut equivalent to the beginning of the current week, with the week understood as Sunday through Saturday.

##### `THIS_WEEK_END`

A shortcut equivalent to the end of the current week, with the week understood as Sunday through Saturday.

##### `LAST_WEEK_START`

A shortcut equivalent to the beginning of the previous week, with the week understood as Sunday through Saturday.

##### `LAST_WEEK_END`

A shortcut equivalent to the end of the previous week, with the week understood as Sunday through Saturday.

##### `THIS_ISO_WEEK_START`

A shortcut equivalent to the beginning of the current week, with the week understood as Monday through Sunday.

##### `THIS_ISO_WEEK_END`

A shortcut equivalent to the end of the current week, with the week understood as Monday through Sunday.

##### `LAST_ISO_WEEK_START`

A shortcut equivalent to the beginning of the previous week, with the week understood as Monday through Sunday.

##### `LAST_ISO_WEEK_END`

A shortcut equivalent to the end of the previous week, with the week understood as Monday through Sunday.

##### `THIS_MONTH_START`

A shortcut equivalent to the beginning of the current month.

##### `THIS_MONTH_END`

A shortcut equivalent to the end of the current month.

##### `LAST_MONTH_START`

A shortcut equivalent to the beginning of the previous month.

##### `LAST_MONTH_END`

A shortcut equivalent to the end of the previous month.

##### `THIS_YEAR_START`

A shortcut equivalent to the beginning of the current year.

##### `THIS_YEAR_END`

A shortcut equivalent to the end of the current year.

##### `LAST_YEAR_START`

A shortcut equivalent to the beginning of the previous year.

##### `LAST_YEAR_END`

A shortcut equivalent to the end of the previous year.

##### `THIS_WEEK_START_YOY`

A shortcut equivalent to the beginning of the week containing the current date minus one year, with the week understood as Sunday through Saturday.

##### `THIS_WEEK_END_YOY`

A shortcut equivalent to the end of the week containing the current date minus one year, with the week understood as Sunday through Saturday.

##### `LAST_WEEK_START_YOY`

A shortcut equivalent to the beginning of the week containing the current date minus one year and seven days, with the week understood as Sunday through Saturday.

##### `LAST_WEEK_END_YOY`

A shortcut equivalent to the end of the week containing the current date minus one year and seven days, with the week understood as Sunday through Saturday.

##### `THIS_ISO_WEEK_START_YOY`

A shortcut equivalent to the beginning of the week containing the current date minus one year, with the week understood as Monday through Sunday.

##### `THIS_ISO_WEEK_END_YOY`

A shortcut equivalent to the end of the week containing the current date minus one year, with the week understood as Monday through Sunday.

##### `LAST_ISO_WEEK_START_YOY`

A shortcut equivalent to the beginning of the week containing the current date minus one year and seven days, with the week understood as Monday through Sunday.

##### `LAST_ISO_WEEK_END_YOY`

A shortcut equivalent to the end of the week containing the current date minus one year and seven days, with the week understood as Monday through Sunday.

##### `THIS_MONTH_START_YOY`

A shortcut equivalent to the beginning of the month containing the current date minus one year.

##### `THIS_MONTH_END_YOY`

A shortcut equivalent to the end of the month containing the current date minus one year.

##### `LAST_MONTH_START_YOY`

A shortcut equivalent to the beginning of the month containing the current date minus one year and one month.

##### `LAST_MONTH_END_YOY`

A shortcut equivalent to the end of the month containing the current date minus one year and one month.

##### `THIS_YEAR_START_YOY`

A shortcut equivalent to the beginning of the previous year.

##### `THIS_YEAR_END_YOY`

A shortcut equivalent to the end of the previous year.

##### `LAST_YEAR_START_YOY`

A shortcut equivalent to the beginning of the previous year minus one year.

##### `LAST_YEAR_END_YOY`

A shortcut equivalent to the end of the previous year minus one year.

#### Public Methods

##### `__construct(array $apiData = null)`

Constructs a new instance, optionally from an array of data returned by the Google Analytics API.

##### `Google\Analytics\GaDataQuery current()`

Returns the instance. This method is implemented to satisfy the interface requirements but has no usefulness in this context.

##### `int key()`

Returns the value of the internal pointer. This method is implemented to satisfy the interface requirements but has no usefulness in this context.

##### `void next()`

Increments the internal pointer. This method is implemented to satisfy the interface requirements but has no usefulness in this context.

##### `void rewind()`

Resets the internal pointer. This method is implemented to satisfy the interface requirements but has no usefulness in this context.

##### `boolean valid()`

Returns true if the internal pointer equals 0, false otherwise. This method is implemented to satisfy the interface requirements but has no usefulness in this context.

##### `string iteration()`

Returns the instance's start date, formatted according to the instance's format string, which defaults to "Y-m-d".

##### `void setID(string $id)`

Sets an ID for this instance (necessary when creating an instance from a Google Analytics API response).

##### `void setName(string $name)`

Sets a name for this instance (necessary when creating an instance from a Google Analytics API response).

##### `void setProfile(Google\Analytics\ProfileSummary, string $profile)`

Sets the Google Analytics profile from which the query will pull its data. This may be passed either as a numeric profile ID or as a `Google\Analytics\ProfileSummary` object.

##### `void setProfileName(string $profileName)`

Sets the Google Analytics profile, based on its name, from which the query will pull its data.

##### `void setStartDate(string, int, DateTime $startDate)`

Sets the query's start date, expressed as a string, one of the date shortcut constants defined in the `Google\Analytics\GaDataQuery` class, or a `DateTime` instance.

##### `void setEndDate(string, int, DateTime $startDate)`

Sets the query's end date, expressed as a string, one of the date shortcut constants defined in the `Google\Analytics\GaDataQuery` class, or a `DateTime` instance.

##### `void setMetrics(string, array $metrics)`

Sets the metrics to be used in the query, either as a comma-delimited string or as an array.

##### `void setDimensions(string, array $dimensions)`

Sets the dimensions to be used in the query, either as a comma-delimited string or as an array.

##### `void setSort(string, array, Google\Analytics\GaDataSort $sort)`

Sets the sort order for the data to be returned from the query, either as a formatted (comma-delimited) string, an array of sort order component strings, or a `Google\Analytics\GaDataSort` instance.

##### `void setFilter(string, Google\Analytics\GaDataFilterCollection $filter)`

Sets a filter to be imposed on the query, either as a string formatted as described in [Google's API documentation](https://developers.google.com/analytics/devguides/reporting/core/v3/reference#filters) or as a `Google\Analytics\GaDataFilterCollection` instance.

##### `void setSegment(string, Google\Analytics\GaDataSegment $segment)`

Sets a segment from which to pull the data for this query, either as a string formatted as described in Google's [Segments Dev Guide](https://developers.google.com/analytics/devguides/reporting/core/v3/segments) or as a `Google\Analytics\GaDataSegment` instance.

##### `void setSamplingLevel(string, int $samplingLevel)`

Sets the sampling level for the query, either as one of the `Google\Analytics\GaDataQuery::SAMPLING_LEVEL_*` constants or its all-caps string equivalent.

##### `void setStartIndex(int $startIndex)`

Sets the 1-based index of the first row of results to be included for this query.

##### `void setMaxResults(int $maxResults)`

Sets the maximum number of results to be returned per iteration of this query.

##### `void setTotalREsults(int $totalResults)`

Sets the maximum total number of results to be returned for this query.

##### `void setFormatString(string $format)`

Sets a format string, compatible with PHP's `date()` function, that will be used by `Google\Analytics\GaDataQuery::iteration()` to format the start date into a readable value.

##### `void setAPIInstance(Google\Analytics\API $api)`

Associates a `Google\Analytics\API` instance with the query so that profile IDs or names may be resolved to `Google\Analytics\ProfileSummary` instances where necessary.

##### `Google\Analytics\ProfileSummary getProfile()`

Returns a `Google\Analytics\ProfileSummary` instance. Note that if a profile was set by ID or name previously, and no API instance has yet been set via `Google\Analytics\GaDataQuery::setAPIInstance()`, this method will throw a `Google\Analytics\LogicException`.

##### `string getID()`

##### `string getName()`

##### `DateTime getStartDate()`

##### `DateTime getEndDate()`

##### `DateTime getSummaryStartDate()`

In this context, this method returns the same value as `Google\Analytics\GaDataQuery::getStartDate()`.

##### `DateTime getSummaryEndDate()`

In this context, this method returns the same value as `Google\Analytics\GaDataQuery::getEndDate()`.

##### `array getMetrics()`

##### `array getDimensions()`

##### `Google\Analytics\GaDataSortOrder getSort()`

##### `Google\Analytics\GaDataFilterCollection getFilter()`

##### `Google\Analytics\GaDataSegment, string getSegment()`

Returns the value that was set previously via `Google\Analytics\GaDataQuery::setSegment()`.

##### `int, string getSamplingLevel(boolean $asString = false)`

Returns the query's sampling level either as an integer constant or its equivalent string value.

##### `int getStartIndex()`

##### `int getMaxResults()`

##### `int getTotalResults()`

##### `array getAsArray()`

Returns a representation of this instance as an array, ready for submission to the API.

##### `string getHash()`

Returns a hash that serves as a unique identifier of this query.

##### `string getEmailSubject()`

##### `string getFormatString()`

#### Protected Properties and Methods

Subclasses of `Google\Analytics\GaDataQuery` have access to the following protected properties and methods:

##### `static $_SETTER_DISPATCH_MODEL`

Dispatches data points returned in JSON object representations to their appropriate setters.

##### `static $_validator`

A `Validator` instance that handles various data validation tasks.

##### `$_id`

The instance's ID.

##### `$_name`

The instance's name.

##### `$_api`

A `Google\Analytics\API` instance.

##### `$_profile`

A `Google\Analytics\ProfileSummary` instance.

##### `$_profileID`

A cached profile ID that will be used for lazy instantiation of a `Google\Analytics\ProfileSummary` instance.

##### `$_profileName`

A cached profile name that will be used for lazy instantiation of a `Google\Analytics\ProfileSummary` instance.

##### `$_startDate`

The query's start date as a `DateTime` instance.

##### `$_endDate`

The query's end date as a `DateTime` instance.

##### `$_metrics`

This query's list of metrics.

##### `$_dimensions`

This query's list of dimensions.

##### `$_sort`

This query's sort order as a `Google\Analytics\GaDataSortOrder` instance.

##### `$_filter`

This query's filter as a `Google\Analytics\GaDataFilterCollection` instance.

##### `$_segment`

This query's segment as a `Google\Analytics\GaDataSegment` instance.

##### `$_samplingLevel`

This query's sampling level as one of the `Google\Analytics\GaDataQuery::SAMPLING_LEVEL_*` constants.

##### `$_startIndex`

The 1-based index of the first row of data to be returned for this query.

##### `$_maxResults`

The maximum number of results to be returned for this query.

##### `$_index`

An internal pointer for satisfying the interface requirements.

##### `$_formatString`

The format string passed to `DateTime::format()` when `Google\Analytics\GaDataQuery::iteration()` is called.

##### `static int _dispatchSetters(array $data, GenericAPI\Response $object, array $model)`

Iterates through the setters declared in the third argument and calls them on the object passed in the second argument, using the data in the first argument. Returns the total number of setters called.

##### `static DateTime _castToDateTime(string, int, DateTime $date)`

Returns a `DateTime` representation of the argument, if necessary.

### `Google\Analytics\GaDataQueryCollection implements Google\Analytics\IQuery, Countable`

Represents a collection of queries to be executed serially.

#### Public Methods

##### `__construct(Google\Analytics\GaDataQuery $query[, Google\Analytics\GaDataQuery...])`

Instantiates a collection of one or more `Google\Analytics\GaDataQuery` instances.

##### `void setName(string $name)`

Assigns an arbitrary name to this instance.

##### `Google\Analytics\GaDataQuery current()`

Returns the current Google\Analytics\GaDataQuery instance in the sequence.

##### `int key()`

Returns the value of the internal pointer.

##### `void next()`

Increments the internal pointer.

##### `void rewind()`

Resets the internal pointer.

##### `boolean valid()`

Returns true if the internal pointer is less than the total count of queries, false otherwise.

##### `string iteration()`

Calls the `getEmailSubject()` method of the current `Google\Analytics\GaDataQuery` instance in the sequence.

##### `string getEmailSubject()`

##### `array getQueries()`

##### `string getName()`

#### Protected Properties

Subclasses of `Google\Analytics\GaDataQueryCollection` have access to the following protected properties:

##### `$_name`

The instance's name.

##### `$_queries`

An array of the instance's queries.

##### `$_queryCount`

The count of queries associated with the instance.

##### `$_index`

The instance's internal pointer.

### `abstract Google\AnalyticsIterativeGaDataQuery extends GaDataQuery`

An abstract base class representing a query that is capable of iterating itself.

#### Public Methods

##### `void setIterativeName(string $iterativeName)`

Sets an arbitrary name to use for the "column" header represented by this instance's iterative property.

##### `string getIterativeName()`

##### `abstract boolean iterate()`

Attempts to advance to the next iteration. Returns a boolean true if there was in fact a next iteration, and false otherwise.

##### `abstract void reset()`

Resets the instance to its initial state.

#### Protected Properties

Subclasses of `Google\AnalyticsIterativeGaDataQuery` have access to the following protected properties:

##### `$_iterativeName`

The instance's iterative name.

### `Google\Analytics\DateRangeGaDataQuery extends Google\Analytics\IterativeGaDataQuery`

Represents a query that iterates through a date range, representing itself as a query with some narrower date range each time.

#### Public Methods

##### `__construct(array $apiData = null, string/int/DateTime $startDate = null, string/int/DateTime $endDate = null, DateInterval $interval = null)`

Creates a new instance with the given start date, end date, and iteration interval.

##### `boolean iterate()`

Attempts to advance to the next iteration. Returns a boolean true if there was in fact a next iteration, and false otherwise.

##### `void reset()`

Resets the instance to its initial state.

##### `void setSummaryStartDate(string, int, DateTime $date)`

Sets the beginning of the instance's date range, expressed as a string, one of the date shortcut constants defined in the `Google\Analytics\GaDataQuery` class, or a `DateTime` instance.

##### `void setSummaryEndDate(string, int, DateTime $date)`

Sets the end of the instance's date range, expressed as a string, one of the date shortcut constants defined in the `Google\Analytics\GaDataQuery` class, or a `DateTime` instance.

##### `void setIterationInterval(DateInterval $interval)`

Sets a `DateInterval` instance that will be added to the instance's start and end dates on each iteration.

##### `DateTime getSummaryStartDate()`

##### `DateTime getSummaryEndDate()`

##### `DateInterval getIterationInterval()`

#### Protected Properties

Subclasses of `Google\Analytics\DateRangeGaDataQuery` have access to the following protected properties:

##### `$_rangeStartDate`

The beginning of the instance's date range (as a `DateTime` instance).

##### `$_rangeEndDate`

The beginning of the instance's date range (as a `DateTime` instance).

##### `$_iterationInterval`

The `DateInterval` instance that controls how the iteration will proceed.

##### `$_iterationReady`

A boolean indicating whether the instance's lazy initialization steps have been completed.

### `Google\Analytics\GaData extends Google\Analytics\AbstractAPIResponseObject`

An object representation of data returned from the Google Analytics API. Note that this documentation omits many of this class' public methods because they do not generally need to be explicitly called in code. The methods documented here are the ones that developers are most likely to find useful.

#### Public Methods

##### `boolean containsSampledData(boolean $containsSampledData = null)`

When called with no argument, returns a boolean indicating whether this instance contains sampled data; otherwise sets the corresponding property.

##### `Google\Analytics\GaDataColumnHeaderCollection getColumnHeaders()`

Returns an object that provides access to the column headers associated with this instance.

##### `Google\Analytics\GaDataRowCollection getRows()`

Returns an object that provides access to the data associated with this instance.

### `Google\Analytics\GaDataColumnHeaderCollection`

An object representation of the collection of column headers associated with a set of data returned from the Google Analytics API.

#### Public Methods

##### `__construct(array $headers, Google\Analytics\API $api)`

Constructs a new instance based on a numerically-indexed array of headers and a `Google\Analytics\API` instance.

##### `Google\Analytics\Column getColumn(string $name)`

Returns a column instance given its name.

##### `Google\Analytics\Column getColumnByIndex(int $index)`

Returns a column instance given its 0-based numeric index.

##### `array getColumnIndicesByName()`

Returns an associative array of columns to indices.

##### `array getColumnNames()`

Returns a numerically-indexed array of column names.

##### `array getTotals()`

Returns a numerically-indexed array of totals. In positions corresponding with columns that have no totals (e.g. dimensions), the returned array will contain a null value, so this array may be used directly when doing things such as generating CSVs.

### `Google\Analytics\GaDataRowCollection`

An object representation of the data content returned from a call to the Google Analytics API.

#### Class Constants

##### `FETCH_NUM`

Indicates that the data should be fetched as a numerically-indexed array.

##### `FETCH_ASSOC`

Indicates that the data should be fetched as an associative array.

##### `FETCH_TYPECAST`

A flag that may be masked with the other fetch styles to ensure that anything that looks like a number is cast as a numeric type before it is returned.

#### Public Methods

##### `__construct(array $rows)`

Constructs an instance from a list of rows as returned from the Google Analytics API.

##### `void setColumnHeaders(Google\Analytics\GaDataColumnHeaderCollection $columns)`

Sets the column headers associated with this instance so that it can generate associative arrays of data.

##### `array, boolean fetch(int $fetchStyle = Google\Analytics\GaDataRowCollection::FETCH_NUM)`

Returns the next row from this data set in a way modeled after PDOStatement and similar database abstraction layers. This method's argument should be one of the `Google\Analytics\GaDataRowCollection::FETCH_NUM` or `Google\Analytics\GaDataRowCollection::FETCH_ASSOC` constants, optionally masked with the `Google\Analytics\GaDataRowCollection::FETCH_TYPECAST` flag, which ensures that any data that looks like a number is returned as a numeric type.

##### `void reset()`

Resets the internal row pointer so that the contents of the collection may be fetched again.

### `Google\Analytics\GaDataSortOrder`

This class provides an object-oriented way to build sort order strings that the Google Analytics API understands.

#### Public Methods

##### `string __toString()`

##### `static Google\Analytics\GaDataSortOrder::createFromString(string $sortString)`

Instantiates an object using a string formatted according to the Google Analytics API's syntax.

##### `void addField(string $field, int $order = SORT_ASC)`

Adds a field to the sort order.

### `abstract Google\Analytics\GaDataLogicalCollection`

An abstract base class representing a collection of logical statements.

#### Class Constants

##### `OP_AND`

Represents the ";" operator, which is a logical "and" in the Google Analytics API.

##### `OP_OR`

Represents the "," operator, which is a logical "or" in the Google Analytics API.

#### Public Methods

##### `__construct(string $operator, mixed $member[, mixed $member...])`

Instantiates a collection with a variable number of members. The operator is expected to be one of the `Google\Analytics\GaDataLogicalCollection::OP_AND` or `Google\Analytics\GaDataLogicalCollection::OP_OR` constants.

##### `string __toString()`

#### Protected Properties and Methods

Subclasses of `Google\Analytics\GaDataLogicalCollection` have access to the following protected properties and methods:

##### `$_operator`

The instance's logical operator.

##### `$_members`

An array of the collection's members.

##### `static string _validateOperator(string $operator)`

Validates the operator and returns it if valid.

##### `abstract void _addMember(mixed $member)`

Adds a member to the collection.

### `Google\Analytics\GaDataFilterCollection extends Google\Analytics\GaDataLogicalCollection`

This class provides an object-oriented way to build filter strings that the Google Analytics API understands. Its members may be instances of `Google\Analytics\GaDataConditionalExpression` or `Google\Analytics\GaDataFilterCollection` (i.e. nested filters).

#### Public Methods

##### `static Google\Analytics\GaDataFilterCollection createFromString(string $filters)`

Instantiates an object using a string formatted according to the Google Analytics API's syntax.

#### Protected Methods

Subclasses of `Google\Analytics\GaDataFilterCollection` have access to the following protected methods:

##### `void _addMember(Google\Analytics\GaDataFilterCollection, Google\Analytics\GaDataConditionalExpression $member)`

Validates that the argument is a `Google\Analytics\GaDataFilterCollection` or `Google\Analytics\GaDataConditionalExpression` instance and adds it to the collection if so.

### `Google\Analytics\GaDataConditionalExpression`

This class provides an object-oriented way to build conditional expressions that the Google Analytics API understands.

#### Class Constants

##### `OP_EQ`

Represents the "==" operator, which is used for equality comparison in the Google Analytics API.

##### `OP_NE`

Represents the "!=" operator, which is used for negated equality comparison in the Google Analytics API.

##### `OP_GT`

Represents the ">" operator, which is used for numeric greater than comparison in the Google Analytics API.

##### `OP_LT`

Represents the "<" operator, which is used for numeric less than comparison in the Google Analytics API.

##### `OP_GE`

Represents the ">=" operator, which is used for numeric greater than or equality comparison in the Google Analytics API.

##### `OP_LE`

Represents the "<=" operator, which is used for numeric less than or equality comparison in the Google Analytics API.

##### `OP_CONTAINS`

Represents the "=@" operator, which is used for substring comparison in the Google Analytics API.

##### `OP_NOT_CONTAINS`

Represents the "!@" operator, which is used for negated substring comparison in the Google Analytics API.

##### `OP_REGEXP`

Represents the "=~" operator, which is used for regular expression comparison in the Google Analytics API.

##### `OP_NOT_REGEXP`

Represents the "!~" operator, which is used for negated regular expression comparison in the Google Analytics API.

#### Public Methods

##### `__construct(string $expression, string $operator = null, string $rightOperand = null)`

Instantiates a new representation of an expression, either from a single string or from its distinct components.

##### `string __toString()`

##### `string getOperator()`

##### `string getLeftOperand()`

##### `string getRightOperand()`

#### Protected Properties and Methods

Subclasses of `Google\Analytics\GaDataConditionalExpression` have access to the following protected properties and methods:

##### `static $_constantsByName`

An associative array of the class constant names to their values.

##### `static $_constantsByVal`

An associative array of the class constant values to their names.

##### `$_operator`

The instance's operator.

##### `$_leftOperand`

The instance's left operand.

##### `$_rightOperand`

The instance's right operand.

##### `static void _initStaticProperties()`

Called upon first instantiation to initialize the class' static properties.

##### `string _validateOperator(string $operator)`

Validates the operator and returns it if valid.

##### `string _validateLeftOperand(string $operand)`

Validates the left operand and returns it if valid.

##### `string _validateRightOperand(string $operand)`

Validates the right operand and returns it if valid.

##### `array _splitStringExpression(string $str)`

Splits a conditional expression string into an array of which the first element is the left operand and the second is the remainder of the string. This also performs some basic validation in that it expects that the string will begin with ga: followed by a column name followed by an operator.

##### `void _setPropertiesFromString(string $str)`

Parses a conditional expression into its three component parts and sets them in the appropriate object properties.

### `Google\Analytics\GaDataSegment`

This class provides an object-oriented way to build segment strings that the Google Analytics API understands.

#### Class Constants

##### `SCOPE_USERS`

Indicates that the segment's scope encompasses users (i.e. any sessions initiated by matching users).

##### `SCOPE_SESSIONS`

Indicates that the segment's scope encompasses sessions (i.e. only the sessions that match the conditions of the segment and no other sessions by the same users).

#### Public Methods

##### `__construct(Google\Analytics\GaDataSegmentGroup $segmentGroup, string $scope[, Google\Analytics\GaDataSegmentGroup $segmentGroup, string $scope...])`

Instantiates a representation of a segment containing one or more scoped segment groups (either `Google\Analytics\GaDataSegmentConditionGroup` or `Google\Analytics\GaDataSegmentSequence`) objects.

### `abstract Google\Analytics\GaDataSegmentGroup extends Google\Analytics\GaDataLogicalCollection`

An abstract base class that restricts some behavior from the parent class that does not make sense in the context of segments.

#### Public Methods

##### `__construct(mixed $member[, mixed $member...])`

Instantiates a segment element containing one or more members.

##### `string __toString()`

##### `boolean isNegated(boolean $negated = null)`

When called with no argument, returns a boolean value indicating whether this group is negated; when called with an argument, negates or un-negates this instance according to the argument's truthiness.

### `Google\Analytics\GaDataSegmentConditionGroup extends Google\Analytics\GaDataSegmentGroup`

This class provides an object-oriented way to build segment condition strings that the Google Analytics API understands.

#### Class Constants

##### `PREFIX`

Equal to "condition". This is used when building the string representation of the segment.

##### `SCOPE_PER_HIT`

Specifies that the scope of the metric conditions in this group is per hit.

##### `SCOPE_PER_SESSION`

Specifies that the scope of the metric conditions in this group is per session.

##### `SCOPE_PER_USER`

Specifies that the scope of the metric conditions in this group is per user.

#### Public Methods

##### `__construct(Google\Analytics\GaDataSegmentSimpleCondition $member[, Google\Analytics\GaDataSegmentSimpleCondition $member...])`

Instantiates a segment condition group containing one or more members. Note that a Google\Analytics\InvalidArgumentException will be thrown if any of the arguments is not an instance of Google\Analytics\GaDataSegmentSimpleCondition; subclasses are not permitted.

##### `void setScope(string $scope)`

Sets this group's metric scope.

### `Google\Analytics\GaDataSegmentSimpleCondition extends Google\Analytics\GaDataSegmentConditionalExpression`

Extends the parent class to add some additional operators and behavior.

#### Class Constants

##### `OP_BETWEEN`

Represents the "<>" operator, which is used to test whether an operand is in a numeric range in the Google Analytics API.

##### `OP_IN`

Represents the "[]" operator, which is used to test whether an operand is contained within a list in the Google Analytics API.

#### Public Methods

##### `__construct(string $expression, string $operator = null, string/array $rightOperand = null)`

Instantiates a new representation of an expression, either from a single string or from its distinct components. This constructor differs from its parent class' in that the right operand is permitted to be an array, provided that the operator is `Google\Analytics\GaDataSegmentSimpleCondition::OP_BETWEEN` or `Google\Analytics\GaDataSegmentSimpleCondition::OP_IN`.

### `Google\Analytics\GaDataSegmentSequence extends Google\Analytics\GaDataSegmentGroup`

This class provides an object-oriented way to build sequence strings that the Google Analytics API understands.

#### Class Constants

##### `PREFIX`

Equal to "sequence". This is used when building the string representation of the segment.

#### Public Methods

##### `__construct(Google\Analytics\GaDataSegmentSequenceCondition $member[, Google\Analytics\GaDataSegmentSequenceCondition $member...])`

Instantiates a segment sequence containing one or more members.

### `Google\Analytics\GaDataSegmentSequenceCondition extends Google\Analytics\GaDataSegmentSimpleCondition`

Extends the parent class to add some additional operators and behavior.

#### Class Constants

##### `OP_FOLLOWED_BY`

Represents the "->>" operator, which asserts that a condition within a sequence occurs chronologically later than the previous one.

##### `OP_FOLLOWED_BY_IMMEDIATE`

Represents the "->" operator, which asserts that a condition within a sequence occurs immediately after the previous one in chronological terms.

##### `OP_FIRST_HIT_MATCHES_FIRST_STEP`

Represents the "^"  operator, which asserts that a condition within a sequence matches the first hit of that user or session.

#### Public Methods

##### `__construct(string $expression, string $operator = null, string/array $rightOperand = null, string $constraintAgainstPrevious = null[, Google\Analytics\GaDataSegmentSimpleCondition $additionalCondition...])`

Instantiates a new sequence condition, either from a single string or from its distinct components, and optionally with additional conditions that extend this one via a logical and.

##### `string __toString()`

##### `void addCondition(Google\Analytics\GaDataSegmentSimpleCondition $condition)`

Adds another condition to this sequence step.

##### `string getConstraintAgainstPrevious()`

##### `array getAdditionalConditions()`

#### Protected Methods

Subclasses of `Google\Analytics\GaDataSegmentSequenceCondition` have access to the following protected methods:

##### `string _validateConstraintAgainstPrevious(string $constraint)`

Validates the constraint against the previous step in the sequence and returns it if valid.