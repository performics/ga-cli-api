# User Documentation

This document provides a brief explanation of how to use the command-line tools included in the Performics Google Analytics API Interface. It assumes that the reader has successfully completed the installation steps for the application and has a basic level of familiarity with Google Analytics and Unix-like systems (familiarity with PHP is not required).

## Service Accounts and Permissions

The Performics Google Analytics API Interface is designed to take advantage of the server-to-server (a.k.a. "two-legged") OAuth flow that Google offers for its services. Rather than acting on behalf of an individual end user, the application has its own dedicated credentials, which you may generate by following [Google's instructions for creating a service account](https://developers.google.com/identity/protocols/OAuth2ServiceAccount). These credentials include a unique email account, which must be granted read permissions for a given Google Analytics account, web property or profile in order for this application to have access to its data. Follow [Google's instructions for assigning permissions in Google Analytics](https://support.google.com/analytics/answer/2884495?hl=en) in order to do this.

## Confirming Permissions

This application includes a script (`list-accounts.php`) for displaying the accounts, web properties, and profiles to which your service account permits access. To see this data, simply run the script with no arguments, i.e.:

`php ga-cli-api/list-accounts.php`

Assuming you have configured your environment properly, the script will print a table that resembles the following:

	(account:12345) Acme, Inc.:
		(web property:UA-12345-1) (Main Website) http://www.acme.com:
			(profile:23456) All traffic
			(profile:64537) Non-US traffic
		(web property:UA-12345-2) (UK Website) http://www.acme.co.uk:
			(profile:94678) All traffic

The example above reflects access to a single Google Analytics account containing a small number of web properties, each of which contains a small number of profiles. This table will, of course, be larger for service accounts that have access to many Google Analytics accounts.

## Running Queries

The `query.php` script is the command-line query runner provided as part of the Performics Google Analytics API interface. This script allows you to define the parameters of one or more Google Analytics API reports you wish to run. These reports may be saved to a file, sent via email to one or more recipients, or both.

### Command-line arguments

Use the following command-line parameters to define your Google Analytics API reports. With the exception of the `segment` argument, for any argument or component thereof for which the Google Analytics API requires a "ga:" prefix internally (e.g. profile IDs, column names), you may include or omit this prefix at your preference. The application will automatically add it where necessary.

#### `profile-id`

The ID of the Google Analytics profile for which you want to run the report. You can discover this ID through the `list-accounts.php` script.

This argument is __required__ unless the `profile-name` argument is used.

#### `profile-name`

The name of the Google Analytics profile for which you want to run the report. This may be used instead of the `profile-id` argument to specify the profile for which the report is to be run, unless your service account exposes more than one Google Analytics profile with this name, in which case the `profile-id` argument must be used instead.

This argument is __required__ unless the `profile-id` argument is used.

#### `start-date`

The beginning of the report's date range. This may be specified in one of two ways: as a literal date in YYYY-MM-DD format, or as one of several date shortcuts that the Performics Google Analytics API Interface understands. These shortcuts take the form `(THIS|LAST)_(WEEK|ISO_WEEK|MONTH|YEAR)_(START|END)[_YOY]`, e.g.:

* `THIS_WEEK_START`
* `LAST_MONTH_END`
* `THIS_ISO_WEEK_END_YOY`

When one of these shortcuts is used, the appropriate date is calculated relative to the current date. This allows for the easy scheduling of recurring reports without needing to rely on external means to calculate the date. The `_YOY` suffix, which stands for "year over year", makes the calculation relative to the same day of the previous year instead of the current day. The `WEEK` and `ISO_WEEK` periods differ in that the former is defined as Sunday through Saturday, while the latter is defined as Monday through Sunday.

This argument is __required__.

#### `end-date`

The end of the report's date range. As with the `start-date` argument, this may be provided either as a literal date or as a date shortcut.

This argument is __required__.

#### `email`

One or more email addresses to which to email the report, expressed as a comma-delimited list.

This argument is __required__ unless the `file` argument is used.

#### `file`

The path to a file to which to write the report.

This argument is __required__ unless the `email` argument is used.

#### `metric`

Between one and ten Google Analytics metrics to include in the report, expressed as a comma-delimited list. Please see Google's [Dimensions & Metrics Explorer](https://developers.google.com/analytics/devguides/reporting/core/dimsmets) for a full list of available metrics.

This argument is __required__.

#### `dimension`

Up to seven Google Analytics dimensions by which to aggregate the report, expressed as a comma-delimited list. Please see Google's [Dimensions & Metrics Explorer](https://developers.google.com/analytics/devguides/reporting/core/dimsmets) for a full list of available metrics.

#### `sort`

A string describing how the report should be sorted. This should be expressed as a comma-delimited list of dimensions or metrics, with each successive entry in the list describing another level of the sort. The sort direction is ascending by default and can be made descending by using the prefix "-". For example, the following string would query the Google Analytics API for data sorted first by city name in ascending order, then by browser in ascending order, then by session count in descending order:

`city,browser,-sessions`

#### `filter`

A string describing how the Google Analytics API should filter the report data. The format of a filter expression as understood by the Performics Google Analytics API Interface matches that described in [Google's API documentation](https://developers.google.com/analytics/devguides/reporting/core/v3/reference#filters), with the exception that this application does not require the use of the "ga:" prefix before dimension and metric names, and operators should not be URL-encoded.

#### `segment`

A string describing the segment from which the data should be drawn. Segments are conceptually similar to filters, but are more complex in that they allow the user to narrow the scope of the report to all activity performed by website users matching a certain set of characteristics. See Google's [Segments Overview](https://developers.google.com/analytics/devguides/reporting/core/v3/segments-feature-reference) for a description of how segments work. Segment strings should be provided exactly as specified in Google's [Segments Dev Guide](https://developers.google.com/analytics/devguides/reporting/core/v3/segments).

#### `split-queries-by`

Causes the query runner to perform separate queries for each applicable date interval in the report. Valid values for this argument are "day", "week", "month", and "year". When this argument is used, the first column in the report will behave like a dimension reflecting the date interval, and it will contain the beginning date in the applicable range. This is similar to using a Google Analytics dimension to aggregate over the given unit of time, but depending on the Google Analytics profile in question, this method may allow for data sampling to be avoided.

#### `date-format-string`

This argument is only used in conjunction with the `split-queries-by` argument. Its value should be a format string that is meaningful to PHP's `date()` function, and this string will be used to format the date that is displayed in the report's first column.

#### `sampling-level`

Specifies the desired level of sampling in the report. Valid values for this argument are "default", "faster", "higher_precision", and "none". Note that specifying "none" does not guarantee the successful generation of a report that is based on unsampled data; rather, it causes the application to report an error if any of the queries making up the report were executed in a sample space based on less than 100% of sessions. To avoid this error while still maintaining the assertion against sampled data, try using the `split-queries-by` option to run multiple queries in smaller time intervals.

#### `name`

An arbitrary name for the report. This is used to build the email's subject line when emailing a report.

#### `group-name`

This serves a similar purpose to the `name` argument, but provides a single name to use for collections of multiple queries. See the section below entitled "Running Multiple Queries" for details on this mode of operation.

#### `formatter`

The fully-qualified name of a PHP class to instantiate in order to format the report. See the developer documentation regarding the Google\Analytics\ReportFormatter class for information on this feature.

#### `conf`

The path to an XML configuration file that describes the parameters of the report to be run. If this argument is provided, all other arguments will be ignored. See the section below entitled "XML Configuration" for more information on this feature.

#### `help`

Displays a help message and exits.

### Running Multiple Queries

The query runner included in the Performics Google Analytics API Interface is capable of delivering the results of multiple queries in a single report. While the recommended way to do this is by providing an XML configuration file as described in the next section, it is also possible to do so by passing multiple instances of the relevant command-line arguments. Any arguments of which there are multiple instances will be grouped according to their numeric position in the argument list, while single instances of any argument will be assumed to apply to every report in the list. For example, the following command would produce a file containing metric1 and metric2 for profile 123, and metric3 and metric4 for profile 456, both for the date range of January 1st, 2015 through January 7th, 2015:

`php query.php --profile-id=123 --metric=metric1,metric2 --profile-id=456 --metric=metric3,metric4 --start-date=2015-01-01 --end-date=2015-01-07 --file=/some/output/file.csv`

When running multiple reports in this way, all command line options of which there are multiple instances must have the same number of instances. If a particular parameter is necessary for one report in the group, but not the others, the placeholder "_" may be passed as an argument value in order to bring the option counts in line, as in the following example:

`php query.php --profile-id=123 --metric=metric1,metric2 --sort=_ --profile-id=456 --metric=metric3,metric4 --sort=-metric3 --start-date=2015-01-01 --end-date=2015-01-07 --file=/some/output/file.csv`

If a literal "_" needs to be passed in this circumstance, it may be escaped with a backslash.

### XML Configuration

Due to the fact that building complex reports at the command line becomes unwieldy quickly, especially when including multiple queries in a single report, `query.php` provides support for reading its arguments from an XML configuration file. The use of a configuration file eliminates many of the cumbersome disambiguations necessary when enumerating multiple queries at the command line. For example, the following XML content is equivalent to the first command described in the previous section:

	<?xml version="1.0" encoding="UTF-8"?>
	<conf file="/some/output/file.csv" start-date="2015-01-01" end-date="2015-01-07">
		<queries>
			<query profile-id="123" metric="metric1,metric2"/>
			<query profile-id="456" metric="metric3,metric4"/>
		</queries>
	</conf>
	
Each of the available command-line arguments is represented as an attribute of an XML element. Arguments that specify an aspect of the entire report (e.g. its output file or email recipient) are attributes of the `<conf>` element; any parameters that will apply to every query in the list may be specified as attributes of this element as well. All other arguments should be supplied as attributes of a `<query>` element. An example [configuration file template](https://github.com/performics/ga-cli-api/blob/master/conf_template.xml) is provided in this repository.

## Settings Documentation

The following settings affect the behavior of the Performics Google Analytics API Interface. These settings should be defined as constants in the application's `_settings.php` file. All settings that are not explicitly described as required are optional.

#### `GOOGLE_ANALYTICS_API_DATA_DIR`

The full path to a general-purpose data directory, writable by the user under which the application runs.

This setting is __required__.

#### `GOOGLE_ANALYTICS_API_LOG_FILE`

The full path the application log file.

This setting is __required__.

#### `GOOGLE_ANALYTICS_API_AUTH_EMAIL`

The email address associated with the Google API service account to be used for authorization.

This setting is __required__.

#### `GOOGLE_ANALYTICS_API_AUTH_KEYFILE`

Full path to the private key file associated with the Google API service account to be used for authorization.

This setting is __required__.

#### `GOOGLE_ANALYTICS_API_AUTH_SCOPE`

The required scope for Analytics API read access (defaults to "https://www.googleapis.com/auth/analytics.readonly").

#### `GOOGLE_ANALYTICS_API_AUTH_TARGET`

The OAuth endpoint from which to request authorization (defaults to "https://www.googleapis.com/oauth2/v3/token").

#### `GOOGLE_ANALYTICS_API_LOG_EMAIL`

Defines an email address to which log messages will be sent automatically.

#### `GOOGLE_ANALYTICS_API_METADATA_CACHE_DURATION`

Defines the number of seconds that account summaries will be cached in the local database (defaults to 86400).

#### `GOOGLE_ANALYTICS_API_ACCOUNTS_CACHE_DURATION`

Defines the number of seconds that account summaries will be cached in the local database (defaults to 86400).

#### `GOOGLE_ANALYTICS_API_AUTOZIP_THRESHOLD`

Defines the maximum size of a file can be (in bytes) before it is automatically compressed when being sent via email (defaults to 1048576).

#### `GOOGLE_ANALYTICS_API_AUTH_KEYFILE_PASSWORD`

Defines the password for the Google API private key file (defaults to "notasecret").

#### `GOOGLE_ANALYTICS_API_PAGE_SIZE`

Defines the number of results (up to 1000) for which the application will ask the Google Analytics API per query (defaults to 500).

#### `OAUTH_DB_DSN`

The database DSN. If left empty, the application will not attempt to connect to a database. See [PHP's documentation](http://php.net/manual/en/ref.pdo-mysql.connection.php) for information on how to construct a DSN for MySQL.

#### `OAUTH_DB_USER`

The database username (required if `OAUTH_DB_DSN` has a value).

#### `OAUTH_DB_PASSWORD`

The database password (required if `OAUTH_DB_DSN` has a value).

#### `PFX_CA_BUNDLE`

Path to SSL certificate bundle. Normally this is not required, but may be necessary if the environment's cURL installation does not automatically load the certificate bundle necessary to connect to Google's API endpoints.