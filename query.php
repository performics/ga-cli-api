#!/usr/bin/env php
<?php
define('PFX_SHORT_USAGE_MESSAGE', <<<EOF
Usage: {FILE} --profile-name=<profile name>|--profile-id=<profile ID>
{PAD} --metric=<metric name>
{PAD} --start-date=<YYYY-MM-DD>
{PAD} --end-date=<YYYY-MM-DD>
{PAD} --email=<email address>|--file=<output file>
{PAD} [--dimension=<dimension name>]
{PAD} [--sort=[-]<dimension or metric name>]
{PAD} [--filter=<filter string>]
{PAD} [--segment=<segment string>]
{PAD} [--split-queries-by=day|week|month|year]
{PAD} [--date-format-string=<string meaningful to PHP's date() function>]
{PAD} [--sampling-level=default|faster|higher_precision|none]
{PAD} [--name=<arbitrary report name>]
{PAD} [--group-name=<arbitrary report group name>]
{PAD} [--formatter=<formatter class>]
{PAD} [--conf=<path to XML configuration file>]
{PAD} [--help]

EOF
);
define('PFX_USAGE_MESSAGE', PFX_SHORT_USAGE_MESSAGE . <<<EOF

This script may be invoked in one of two ways: you may specify the report's parameters via the
command-line arguments listed above, or via an XML configuration file provided using the --conf
argument (see conf_template.xml for this configuration file's structure). The argument names,
expected values, and behavior are the same in both invocation methods.

If the user credentials expose more than one profile with the same name, it must be specified by ID
rather than name. Inclusion of the "ga:" prefix on arguments such as the profile ID, metric names,
and dimension names is not required.

A series of shortcuts for dates (e.g. LAST_MONTH_START and THIS_WEEK_END) exists; these may be
invoked by specifying the shortcut as a string in all caps. The shortcuts take the following form:

(THIS|LAST)_(WEEK|ISO_WEEK|MONTH|YEAR)_(START|END)[_YOY]

Any of these shortcuts may have the suffix _YOY, which provides the corresponding date from the
previous year.

At least one metric must be specified. Multiple metrics, dimensions, and sort parameters may be
specified by passing them as a comma-delimited list. Specify a descending sort order for a given
dimension or metric by giving it the prefix "-".

Filters and segments should be specified using Google's syntax; see
https://developers.google.com/analytics/devguides/reporting/core/v3/reference for details on how
to form these strings properly.

To include more than one report in a single file, use multiple instances of the appropriate
command line arguments. Any arguments of which there are multiple instances will be grouped
according to their numeric position in the argument list, while single instances of any argument
will be assumed to apply to every report in the list. For example, the following command would
produce a file containing metric1 and metric2 for profile 123, and metric3 and metric4 for profile
456, both for the date range of January 1st, 2015 through January 7th, 2015:

query.php --profile-id=123 --metric=metric1,metric2 --profile-id=456 --metric=metric3,metric4 --start-date=2015-01-01 --end-date=2015-01-07 --file=/some/output/file.csv

When running multiple reports in this way, all command line options of which there are multiple
instances must have the same number of instances. If a particular parameter is necessary for one
report in the group, but not the others, the placeholder "_" may be passed as an argument value in
order to bring the option counts in line, as in the following example:

query.php --profile-id=123 --metric=metric1,metric2 --sort=_ --profile-id=456 --metric=metric3,metric4 --sort=-metric3 --start-date=2015-01-01 --end-date=2015-01-07 --file=/some/output/file.csv

If a literal "_" needs to be passed in this circumstance, it may be escaped with a backslash.

The --split-queries-by option provides a mechanism to perform a series of queries over the given
time period rather than a single query. When using this option, the corresponding time period will
automatically be a de facto dimension, so it is unnecessary to specify it with the --dimension
option.

The --date-format-string only makes sense in conjunction with the --split-queries-by option, and
it should be a format string that is meaningful to PHP's date() function. The start date of each
individual query in the overall range will be formatted using this string for inclusion in the
report.

The --sampling-level argument provides a way to express a preference regarding the degree of
sampling in the Google Analytics data. If the option "none" is used, the report will be treated
as having failed if Google reports sampling as being present in the results; in this case the user
must specify a shorter time interval in the --split-queries-by option in order to avoid data
sampling.

The --formatter argument may be used to specify the name of a PHP class that inherits from
Google\Analytics\ReportFormatter; an instance of this class will be used to format the report's
contents.

The --name argument only makes sense when emailing a report; it allows for the specification of a
meaningful name to describe the report's contents.

The --group-name argument fulfills a similar function to the --name argument, but applies when
including multiple reports in the same file, and will be used to describe the entire collection.
This argument is ignored if specified when running only a single report.

EOF
);
require_once('bootstrap.php');
try {
    try {
        $args = PFXUtils::collapseArgs(
            array(),
            array(
                'profile-name:',
                'profile-id:',
                'metric:',
                'start-date:',
                'end-date:',
                'email:',
                'file:',
                'dimension:',
                'sort:',
                'filter:',
                'segment:',
                'split-queries-by:',
                'date-format-string:',
                'sampling-level:',
                'name:',
                'group-name:',
                'formatter:',
                'conf:',
                'help'
            )
        );
        $ga = new Google\Analytics\API();
        Google\Analytics\QueryConfiguration::createFromCommandLineArgs($args)->run($ga);
    } catch (InvalidArgumentException $e) {
        PFXUtils::printUsage($e->getMessage(), 1, true);
    }
} catch (Exception $e) {
    echo PFXUtils::buildExceptionTrace($e) . "\n";
}
?>
