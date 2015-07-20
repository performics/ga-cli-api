<?php
namespace OAuth;
interface Exception {}
class LogicException extends \LogicException implements Exception {}
class InvalidArgumentException extends \InvalidArgumentException implements Exception {}
class UnexpectedValueException extends \UnexpectedValueException implements Exception {}
class OutOfRangeException extends \OutOfRangeException implements Exception {}
class BadMethodCallException extends \BadMethodCallException implements Exception {}
class RuntimeException extends \LoggingExceptions\RuntimeException implements Exception {}
class AuthenticationException extends RuntimeException {}
class AuthorizationException extends RuntimeException {}
?>