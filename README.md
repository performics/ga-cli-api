# Performics Google Analytics API Interface

This repository contains a library for communicating with the Google Analytics Core Reporting API (version 3), as well as a command-line script for running reports. It requires that a Service Account be used for authorization; please see [Google's Service Account authorization guide](https://developers.google.com/identity/protocols/OAuth2ServiceAccount) for instructions on how to enable this.

## System Requirements

This application requires PHP 5.3.29 or higher. The following extensions are required:

* PCRE
* libxml
* cURL
* openssl
* PDO-MySQL (if a database is used)

Access to a MySQL database (version 5.0 or higher) is recommended but not required. Although the documentation assumes that the application will be installed in a Unix-like environment, it is theoretically possible (although untested) to run it in a Windows environment.

## Getting Started
### Installation

Clone the repository using Git:

`git clone https://github.com/performics/ga-cli-api.git`

Alternately, you may [download](https://github.com/performics/ga-cli-api/archive/master.zip) the repository as a ZIP file.

### Configuration

Once the repository has been installed, create and populate the _settings.php file, which must exist at the root of the repository directory. The easiest way to do this is to copy the provided settings template file, e.g.:

`cp ga-cli-api/_settings_template.php ga-cli-api/_settings.php`

Open `ga-cli-api/_settings.php` in a text editor and provide the necessary settings. The following settings are required:

* `GOOGLE_ANALYTICS_API_DATA_DIR`
* `GOOGLE_ANALYTICS_API_LOG_FILE`
* `GOOGLE_ANALYTICS_API_AUTH_EMAIL`
* `GOOGLE_ANALYTICS_API_AUTH_KEYFILE`

Any optional setting that is left unused should be commented out or removed from the file.

### Database Setup

Although a database is not required in order to run this application, the use of a database enables various optimizations such as the caching of OAuth tokens and Google Analytics column definitions. This application assumes that the database type, if used, is MySQL. To enable database support, define the `OAUTH_DB_*` settings appropriately and ensure that each script under the ga-cli-api/schemata/ directory has been run before using the application, e.g.:

	mysql < ga-cli-api/schemata/oauth/oauth.mysql.sql
	mysql < ga-cli-api/schemata/google_analytics_api/google_analytics_api.mysql.sql

### Testing

At this point, it should be possible to execute `ga-cli-api/query.php` by running the following command:

`php ga-cli-api/query.php --help`

This should print a summary of the available command-line options and several paragraphs of usage information to the screen. To test the ability to connect to the Google Analytics API, execute the ```ga-cli-api/list-accounts.php``` script; this should print a tree of the accounts, web properties, and profiles to which the effective credentials permit access. Both of these scripts incorporate a shebang cookie so they may be executed directly in Unix-like environments when the proper permission bit is set.

This repository contains a configuration file (`ga-cli-api/phpunit.xml`) for automated testing via PHPUnit. A database must be configured in order to run the test suites, and the relevant settings must be configured in the `ga-cli-api/bootstrap_test.php` file. As with the normal settings file, a template (`ga-cli-api/bootstrap_test_template.php`) is provided to facilitate this.

## License

© Performics, 2015. Licensed under the [GNU General Public License](https://github.com/performics/ga-cli-api/blob/master/LICENSE).