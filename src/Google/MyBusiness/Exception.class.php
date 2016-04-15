<?php
namespace Google\MyBusiness;
interface Exception {}
class InvalidArgumentException extends \InvalidArgumentException implements Exception {}
class AccountOwnerNotSetException extends InvalidArgumentException {}
class UnexpectedValueException extends \UnexpectedValueException implements Exception {}
class RuntimeException extends \LoggingExceptions\RuntimeException implements Exception {}
class LogicException extends \LogicException implements Exception {}
class NotFoundException extends RuntimeException {}
class BadRequestException extends RuntimeException {}
class RemoteException extends RuntimeException {}
?>