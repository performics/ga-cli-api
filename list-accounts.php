#!/usr/bin/env php
<?php
define('PFX_USAGE_MESSAGE', <<<EOF
Usage: {FILE} [-f|--force-refresh]
{PAD} [-h|--help]

This script prints a structured list of the Google Analytics properties to which the effective
credentials permit access. If the --force-refresh argument is used, the script will always make a
new query to the Google Analytics API rather than using information from the database.

EOF
);
require_once('bootstrap.php');
try {
    $args = PFXUtils::collapseArgs(
        array('f', 'h'),
        array('force-refresh', 'help')
    );
    $ga = new Google\Analytics\API();
    if (isset($args['force-refresh'])) {
        $ga->clearAccountCache();
    }
    $accounts = $ga->getAccountSummaries();
    sortByName($accounts);
    foreach ($accounts as $account) {
        printf("(account:%s) %s:\n", $account->getID(), $account->getName());
        $webProperties = $account->getWebPropertySummaries();
        sortByName($webProperties);
        foreach ($webProperties as $webProperty) {
            printf(
                "\t\t(web property:%s) (%s) %s:\n",
                $webProperty->getID(),
                $webProperty->getName(),
                $webProperty->getURL()
            );
            $profiles = $webProperty->getProfileSummaries();
            sortByName($profiles);
            foreach ($profiles as $profile) {
                printf(
                    "\t\t\t(profile:%s) %s\n",
                    $profile->getID(),
                    $profile->getName()
                );
            }
        }
    }
} catch (Exception $e) {
    echo PFXUtils::buildExceptionTrace($e) . "\n";
}

function sortByName(&$arr) {
    usort($arr, function($a, $b) { return strcasecmp($a->getName(), $b->getName()); });
}
?>
