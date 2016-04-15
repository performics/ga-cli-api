<?php
/* The purpose of this script is to assist in testing PFXUtils::collapseArgs(),
which cannot be unit tested in the traditional way because of the difficulty in
spoofing what getopt() sees in the argument vector. It should be invoked with
whatever command line arguments are intended to be requested, followed by the
string "--" and a serialized representation of the arguments to be passed to
PFXUtils::collapseArgs() (in array form, the same way they would be passed to
call_user_func_array()). This may optionally be followed by a string to define
as PFX_USAGE_MESSAGE, which may optionally be followed by a string to define as
PFX_SHORT_USAGE_MESSAGE. */
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'PFXUtils.class.php');
try {
    $modifiers = new SplStack();
    while ($argv) {
        $arg = array_pop($argv);
        if ($arg == '--') {
            break;
        }
        $modifiers->push($arg);
    }
    $args = unserialize($modifiers->pop());
    if ($args === false) {
        throw new RuntimeException(
            'Could not unserialize the first item in the argument vector.'
        );
    }
    try {
        define('PFX_USAGE_MESSAGE', $modifiers->pop());
        try {
            define('PFX_SHORT_USAGE_MESSAGE', $modifiers->pop());
        } catch (RuntimeException $e) {
            // Short message was not passed
        }
    } catch (RuntimeException $e) {
        // Message was not passed
    }
    $parsedArgs = call_user_func_array(array('PFXUtils', 'collapseArgs'), $args);
    echo serialize($parsedArgs);
    exit(0);
} catch (Exception $e) {
    PFXUtils::printUsage(
        sprintf('%s: %s', get_class($e), $e->getMessage()),
        1,
        true
    );
}
?>