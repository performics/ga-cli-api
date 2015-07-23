<?php
namespace Google\Analytics;
interface Exception {}
class InvalidArgumentException extends \InvalidArgumentException implements Exception {}
class UnexpectedValueException extends \UnexpectedValueException implements Exception {}
class RuntimeException extends \LoggingExceptions\RuntimeException implements Exception {}
class OverflowException extends \OverflowException implements Exception {}
class UnderflowException extends \UnderflowException implements Exception {}
class LogicException extends \OverflowException implements Exception {}
class OutOfRangeException extends \OutOfRangeException implements Exception {}
class BadMethodCallException extends \BadMethodCallException implements Exception {}
class DomainException extends \DomainException implements Exception {}
class NameConflictException extends \Exception implements Exception {}
class SamplingException extends \Exception implements Exception {}
class FailedIterationsException extends \Exception implements Exception {}
class InvalidParameterException extends RuntimeException {}
class BadRequestException extends RuntimeException {}
class InvalidCredentialsException extends RuntimeException {}
class InsufficientPermissionsException extends RuntimeException {}
class DailyLimitExceededException extends RuntimeException {}
class UserRateLimitExceededException extends RuntimeException {}
class QuotaExceededException extends RuntimeException {}
class RemoteException extends RuntimeException {}
?>
