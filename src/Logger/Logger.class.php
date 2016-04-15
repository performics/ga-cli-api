<?php
class LoggerException extends Exception {}
class Logger {
	private static $_registeredSendFunction = false;
	private static $_emails = array();
	private $_email;
	private $_mutex;
	private $_logFileName;
	private $_logFileHandle;
	private $_useGZip;
	private $_externalBuffer;
	private $_timestampFormat = '%Y-%m-%d %H:%M:%S';
	private $_wroteToHandle = false;
	private $_openFunction;
	private $_closeFunction;
	private $_writeFunction;
	
	/**
	 * Opens a handle to the log file and instantiates a mutex that prevents
	 * concurrent writes.
	 *
	 * @param string $logFile
	 * @param string $emailRecipient = null
	 * @param Mutex $mutex = null
	 * @param boolean $useGZip = null
	 */
	public function __construct(
		$logFile,
		$emailRecipient = null,
		Mutex $mutex = null,
		$useGZip = null
	) {
		if (!self::$_registeredSendFunction) {
			/* We want to ensure that emails get sent even if an error takes
			place that stops the destructor from getting called (e.g. a PHP
			fatal error). However, we can't register any instance methods as
			shutdown functions, because it prevents their refcounts from
			dropping to zero. My solution is to maintain the emails in a
			static property, and the shutdown function will iterate through any
			of them with pending content and send them. */
			register_shutdown_function(array(__CLASS__, 'sendEmail'));
		}
		if ($emailRecipient) {
			try {
				// This default subject can be overridden later
				self::$_emails[] = $this->_email = new Email(
					$emailRecipient, 'Automated log message report'
				);
			} catch (EmailException $e) {
				throw new LoggerException(
					'Caught error while initializing email.', null, $e
				);
			}
		}
		if ($useGZip === null) {
			$useGZip = substr($logFile, -3) == '.gz';
		}
		$this->_useGZip = $useGZip;
		if (!$mutex) {
			try {
				$mutex = new Mutex(__CLASS__);
			} catch (MutexException $e) {
				throw new LoggerException(
					'Unable to instantiate mutex.', null, $e
				);
			}
		}
		$this->_mutex = $mutex;
		$this->_logFileName = $logFile;
		/* We'll defer the actual opening of the file handle until it's needed.
		This is mainly due to the fact that appending to a gzip stream appends
		a gzip header each time, so if we're not going to end up with anything
		to log, it's better not to even open the handle in the first place.
		It doesn't really make a difference for uncompressed files, but it's
		simplest to handle both stream types in the same way. */
		if (!PFXUtils::testWritable($this->_logFileName)) {
			throw new LoggerException(
				$this->_logFileName . ' does not appear to be writable.'
			);
		}
		if ($this->_useGZip) {
			$this->_openFunction = 'gzopen';
			$this->_closeFunction = 'gzclose';
			$this->_writeFunction = 'gzwrite';
		}
		else {
			$this->_openFunction = 'fopen';
			$this->_closeFunction = 'fclose';
			$this->_writeFunction = 'fwrite';
		}
	}
	
	/**
	 * Closes any open resources and flushes buffers.
	 */
	public function __destruct() {
		if ($this->_email) {
			$this->flushMailBuffer();
		}
		$this->_mutex->release();
		if ($this->_logFileHandle) {
			call_user_func($this->_closeFunction, $this->_logFileHandle);
		}
	}
	
	/**
	 * Sends each of the emails in the queue.
	 */
	public static function sendEmail() {
		foreach (self::$_emails as $email) {
			if ($email->hasMessage()) {
				$email->mail();
			}
		}
	}
	
	/**
	 * Dispatches a message to the log.
	 *
	 * @param string $message
	 * @param boolean $includeInEmail = true
	 */
	public function log($message, $includeInEmail = true) {
		if ($this->_timestampFormat) {
			$message = strftime($this->_timestampFormat) . ': ' . $message;
		}
		if ($includeInEmail && $this->_email) {
			$emailMessage = $message;
			if ($this->_email->hasMessage()) {
				$emailMessage = PHP_EOL . $emailMessage;
			}
			$this->_email->appendMessage($emailMessage);
		}
		if ($this->_externalBuffer !== null) {
			$this->_externalBuffer[] = $message;
		}
		$this->_mutex->acquire();
		// Open file handle first, if necessary
		if (!is_resource($this->_logFileHandle)) {
			$this->_logFileHandle = call_user_func_array(
				$this->_openFunction, array($this->_logFileName, 'ab')
			);
			if (!$this->_logFileHandle) {
				$this->_mutex->release();
				throw new LoggerException(
					'Unable to open ' . $this->_logFileHandle . 
					' for writing.'
				);
			}
		}
		call_user_func_array($this->_writeFunction, array(
			$this->_logFileHandle, $message . PHP_EOL
		));
		$this->_wroteToHandle = true;
		$this->_mutex->release();
	}
	
	/**
	 * Emails the contents of the email buffer to the registered recipient.
	 */
	public function flushMailBuffer() {
		if (!$this->_email) {
			throw new LoggerException(
				'There is no email address associated with this instance.'
			);
		}
		if ($this->_email->hasMessage()) {
			$this->_email->mail();
		}
	}
	
	/**
	 * Forces the emptying of PHP's internal file write buffer by closing and
	 * re-opening the file.
	 */
	public function flushWriteBuffer() {
		/* If we haven't written to this handle since it was last opened,
		there's no reason to do this. */
		if (!$this->_wroteToHandle) {
			return;
		}
		$this->_mutex->acquire();
		call_user_func($this->_closeFunction, $this->_logFileHandle);
		$this->_logFileHandle = call_user_func_array(
			$this->_openFunction, array($this->_logFileName, 'ab')
		);
		$this->_mutex->release();
		if (!$this->_logFileHandle) {
			throw new LoggerException(
				'Unopen to reopen ' . $this->_logFileName . ' for writing.'
			);
		}
		$this->_wroteToHandle = false;
	}

	/**
	 * Shorthand method to flush both the mail and the write buffers.
	 */
	public function flush() {
		if ($this->_email) {
			$this->flushMailBuffer();
		}
		$this->flushWriteBuffer();
	}
	
	/**
	 * Registers a timestamp format (which will be passed to strftime()). The
	 * default format is '%Y-%m-%d %H:%M:%S'. The argument to this method may
	 * be null, in which case no timestamp will be included in log messages. If
	 * a format is registered, the timestamp will be prepended to every log
	 * message.
	 *
	 * @param string $format
	 */
	public function setTimestampFormat($format) {
		$this->_timestampFormat = $format;
	}
	
	/**
	 * Registers a subject header to be used in any email sent by this class.
	 *
	 * @param string $subject
	 */
	public function setEmailSubject($subject) {
		if (!$this->_email) {
			throw new LoggerException(
				'There is no email address associated with this instance.'
			);
		}
		$this->_email->setSubject($subject);
	}
	
	/**
	 * Registers an array reference to serve as an external buffer for log
	 * messages. It is up to the caller to deal with its contents.
	 *
	 * @param array &$buf
	 */
	public function setExternalBuffer(array &$buf) {
		$this->_externalBuffer = &$buf;
	}
	
	/**
	 * @return string
	 */
	public function getFile() {
		return $this->_logFileName;
	}
	
	/**
	 * @return string
	 */
	public function getEmailRecipient() {
		return $this->_email->getRecipient();
	}
}
?>
