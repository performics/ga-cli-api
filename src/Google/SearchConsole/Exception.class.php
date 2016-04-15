<?php
namespace Google\SearchConsole;
interface Exception {}
class InvalidArgumentException extends \InvalidArgumentException implements Exception {}
class UnexpectedValueException extends \UnexpectedValueException implements Exception {}
class RuntimeException extends \LoggingExceptions\RuntimeException implements Exception {}
?>