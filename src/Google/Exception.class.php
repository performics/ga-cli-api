<?php
namespace Google;
interface Exception {}
class InvalidArgumentException extends \InvalidArgumentException implements Exception {}
class UnexpectedValueException extends \UnexpectedValueException implements Exception {}
class RuntimeException extends \LoggingExceptions\RuntimeException implements Exception {}
class OverflowException extends \OverflowException implements Exception {}
class LogicException extends \OverflowException implements Exception {}
class OutOfRangeException extends \OutOfRangeException implements Exception {}
class BadMethodCallException extends \BadMethodCallException implements Exception {}
class DomainException extends \DomainException implements Exception {}
?>