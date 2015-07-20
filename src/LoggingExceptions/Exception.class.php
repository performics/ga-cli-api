<?php
namespace LoggingExceptions;

class Exception extends \Exception {
	protected static $_logger;
	protected static $_autoEmail;
	
	public function __construct(
		$message = null,
		$code = 0,
		\Exception $previous = null
	) {
		parent::__construct($message, $code, $previous);
		if (self::$_logger) {
			self::$_logger->log($this->_buildMessage(), self::$_autoEmail);
		}
	}
	
	/**
	 * Builds the loggable message. The message will represent the exception
	 * chain (if any) until it reaches the next LoggingException or the end
	 * of the chain, whichever comes first.
	 *
	 * @return string
	 */
	private function _buildMessage() {
		$exceptions = array($this);
		$previous = $this->getPrevious();
		while ($previous && !($previous instanceof self)) {
			$exceptions[] = $previous;
			$previous = $previous->getPrevious();
		}
		$exceptionCount = count($exceptions);
		$message = '';
		for ($i = 0; $i < $exceptionCount; $i++) {
			$message .= get_class($exceptions[$i]);
			if ($exceptionCount > 1) {
				$message .= ' (' . ($i + 1) . ' of ' . $exceptionCount . ')';
			}
			$message .= ': ' . $exceptions[$i]->message . ' (in '
			          . $exceptions[$i]->file . ', line '
					  . $exceptions[$i]->line . ')';
			if ($exceptionCount > 1 && $i + 1 < $exceptionCount) {
				$message .= PHP_EOL;
			}
		}
		return $message;
	}
	
	/**
	 * Associates a Logger instance with this exception class. This causes each
	 * exception message to be logged, and if the second argument to this
	 * method is true, these messages will be automatically emailed to the
	 * Logger's email recipient (if any).
	 *
	 * @param Logger $logger
	 * @param boolean $autoEmail = true
	 */
	public static function registerLogger(
		\Logger $logger,
		$autoEmail = true
	) {
		/* Because this property is shared across the entire inheritance chain,
		everything has to go into a single file. We will need to throw an
		exception here if somebody tries to register a Logger instance with
		a log file and/or email address that doesn't match this one. */
		if (self::$_logger && (
			$autoEmail != self::$_autoEmail ||
			$logger->getFile() != self::$_logger->getFile() ||
			$logger->getEmailRecipient() != self::$_logger->getEmailRecipient()
		)) {
			throw new \OverflowException(
				'A logger with a conflicting properties has already been ' .
				'registered.'
			);
		}
		self::$_logger = $logger;
		self::$_autoEmail = $autoEmail;
	}
	
	/**
	 * Clears out any loggers. This isn't normally useful but is required in
	 * certain testing scenarios.
	 */
	public static function unregisterLogger() {
		self::$_logger = null;
		self::$_autoEmail = null;
	}
	
	/**
	 * Reports on whether a Logger has been registered to this class (at any
	 * point in the inheritance chain).
	 *
	 * @return boolean
	 */
	public static function hasLogger() {
		return self::$_logger !== null;
	}
	
	/**
	 * @return Logger
	 */
	public static function getLogger() {
		return self::$_logger;
	}
}
?>