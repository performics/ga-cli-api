<?php
if (!defined('PFX_UNIT_TEST')) {
	define('PFX_UNIT_TEST', false);
}
if (PFX_UNIT_TEST) {
	/* This is a little hacky, but this is a global container for properties
	associated with the last email sent. This allows the $this->mail() method
	to set those properties here via $GLOBALS rather than attempting to
	actually send the email, and for unit tests to inspect this variable in the
	same way in order to test whether expected emails were sent. The member
	keys are as follows:
	
	* sent_time
	* to
	* subject
	* from
	* from_name
	* reply_to
	* charset
	* message
	* attachment_filenames
	* attachment_content
	
	The two attachment-related properties are numerically-indexed arrays.
	*/
	$__lastEmailSent = array();
}
class EmailException extends Exception {}
class Email {
	private static $_SETTINGS = array(
		'EMAIL_FROM_HOSTNAME' => null,
		'EMAIL_FROM_USER' => null,
		'EMAIL_FROM_NAME' => null,
		'EMAIL_REPLY_TO' => null
	);
	private static $_SETTING_TESTS = array(
		'EMAIL_FROM_HOSTNAME' => '?string',
		'EMAIL_FROM_USER' => '?string',
		'EMAIL_FROM_NAME' => '?string',
		'EMAIL_REPLY_TO' => '?email'
	);
	private static $_DEFAULT_FROM_HOSTNAME;
	private static $_DEFAULT_FROM_USER;
	private static $_DEFAULT_FROM_NAME;
	private static $_DEFAULT_REPLY_TO;
	private static $_validator;
	private static $_defaultCharset;
	private $_from;
	private $_fromName;
	private $_replyTo;
	private $_to;
	private $_subject;
	private $_message;
	private $_attachmentFilenames = array();
	private $_attachmentContent = array();
	private $_charset;
	private $_sent = false;
	
	/**
	 * Constructor. Note that the fourth argument is treated as a file name,
	 * not content to be attached. To attach data without using a file,
	 * instantiate the object with just the first three parameters and call
	 * the $this->setAttachmentContent() method to attach the data.
	 *
	 * @param string $to
	 * @param string $subject = null
	 * @param string $message = null
	 * @param string $attachment = null
	 * @param string $attachmentFilename = null
	 */
	public function __construct(
		$to,
		$subject = null,
		$message = null,
		$attachment = null, 
		$attachmentFilename = null
	) {
		if (!self::$_DEFAULT_FROM_HOSTNAME) {
			self::_setDefaults();
			/* We want to call some setters in order to ensure that the setting
			values add up to usable properties, but we don't want to do it on
			this instance, or we will get an odd situation where the first
			Email instance in a process will have certain property values that
			any remaining instances won't have. We'll do it on a dummy instance
			that won't outlive this scope. */
			$dummy = new self('dummy@default.com');
			$dummy->setFromName(self::$_DEFAULT_FROM_NAME);
			$dummy->setFromAddress(
				self::$_DEFAULT_FROM_USER . '@' . self::$_DEFAULT_FROM_HOSTNAME
			);
			$dummy->setReplyToAddress(self::$_DEFAULT_REPLY_TO);
		}
		$this->_to = self::$_validator->email(
			$to, 'Invalid email address.', Validator::ASSERT_TRUTH
		);
		$this->setSubject($subject);
		$this->setMessage($message);
		if ($attachment) {
			$this->addAttachment($attachment, $attachmentFilename);
		}
	}
	
	/**
	 * Establishes default values for identifying the source of emails sent by
	 * this class.
	 */
	private static function _setDefaults() {
		PFXUtils::validateSettings(self::$_SETTINGS, self::$_SETTING_TESTS);
		if (EMAIL_FROM_HOSTNAME) {
			self::$_DEFAULT_FROM_HOSTNAME = EMAIL_FROM_HOSTNAME;
		}
		else {
			// Look for a HOSTNAME environment variable
			self::$_DEFAULT_FROM_HOSTNAME = getenv('HOSTNAME');
			if (!self::$_DEFAULT_FROM_HOSTNAME) {
				/* Not too creative, but a reasonably sensible default. The
				dot is necessary because 'localhost' does not pass PHP's email
				validation filter when used as the domain part. */
				self::$_DEFAULT_FROM_HOSTNAME = 'local.host';
			}
		}
		if (EMAIL_FROM_USER) {
			self::$_DEFAULT_FROM_USER = EMAIL_FROM_USER;
		}
		else {
			// There really should be a USER environment variable
			self::$_DEFAULT_FROM_USER = getenv('USER');
			if (!self::$_DEFAULT_FROM_USER) {
				// Again, not too creative, but it probably won't come up
				self::$_DEFAULT_FROM_USER = 'noreply';
			}
		}
		if (EMAIL_FROM_NAME) {
			self::$_DEFAULT_FROM_NAME = EMAIL_FROM_NAME;
		}
		else {
			// Unlike above, this isn't a standard environment variable name
			self::$_DEFAULT_FROM_NAME = getenv('EMAIL_FROM');
			if (!self::$_DEFAULT_FROM_NAME) {
				self::$_DEFAULT_FROM_NAME = 'Nobody';
			}
		}
		if (EMAIL_REPLY_TO) {
			self::$_DEFAULT_REPLY_TO = EMAIL_REPLY_TO;
		}
		else {
			self::$_DEFAULT_REPLY_TO = self::$_DEFAULT_FROM_USER . '@'
			                         . self::$_DEFAULT_FROM_HOSTNAME;
		}
		self::$_validator = new Validator();
		self::$_validator->setExceptionType('EmailException');
		self::$_defaultCharset = ini_get('default_charset');
	}
	
	/**
	 * Sets the origin address of this email message to something other than
	 * the default. If an empty value is passed, it unsets this email's origin
	 * address so that the default will be used.
	 *
	 * @param string $fromAddress
	 */
	public function setFromAddress($fromAddress) {
		$this->_from = self::$_validator->email(
			$fromAddress,
			'Invalid origin address.',
			Validator::FILTER_DEFAULT | Validator::ASSERT_SINGLE_EMAIL
		);
	}
	
	/**
	 * Sets the visible from name in this email message to something other than
	 * the default. If an empty value is passed, it unsets this email's from
	 * name so that the default will be used.
	 *
	 * @param string $fromName
	 */
	public function setFromName($fromName) {
		$this->_fromName = self::$_validator->string(
			$fromName, 'Invalid origin name.'
		);
	}
	
	/**
	 * Sets the reply-to address in this email message to something other than
	 * the default, which is the email's from address (either the default or
	 * one that has been explicitly set with $this->setFromAddress()).
	 *
	 * @param string $replyTo
	 */
	public function setReplyToAddress($replyTo) {
		$this->_replyTo = self::$_validator->email(
			$replyTo,
			'Invalid reply-to address.',
			Validator::FILTER_DEFAULT | Validator::ASSERT_SINGLE_EMAIL
		);
	}
	
	/**
	 * Sets the subject line.
	 *
	 * @param string $subject
	 */
	public function setSubject($subject) {
		$this->_subject = $subject;
		$this->_sent = false;
	}
	
	/**
	 * Sets the message content.
	 *
	 * @param string $message
	 */
	public function setMessage($message) {
		$this->_message = $message;
		$this->_sent = false;
	}
	
	/**
	 * Sets the message character set.
	 *
	 * @param string $charset
	 */
	public function setCharset($charset) {
		$this->_charset = $charset;
	}
	
	/**
	 * Appends to the existing message. The value of the second argument
	 * determines whether to automatically place the appended string in a new
	 * paragraph by preceding it with two newlines (if there is already message
	 * content).
	 *
	 * @param string $message
	 * @param boolean $asNewParagraph = false
	 */
	public function appendMessage($message, $asNewParagraph = false) {
		if ($asNewParagraph && strlen($this->_message)) {
			$this->_message .= PHP_EOL . PHP_EOL;
		}
		$this->_message .= $message;
		$this->_sent = false;
	}
	
	/**
	 * Adds to the list of attached content.
	 *
	 * @param string $content
	 * @param string $attachmentName
	 */
	public function addAttachmentContent($content, $attachmentName) {
		if (!strlen($attachmentName)) {
			throw new EmailException(
				'Attachments must have an associated non-empty file name.'
			);
		}
		$this->_attachmentContent[] = chunk_split(
			base64_encode($content), 76, "\r\n"
		);
		$this->_attachmentFilenames[] = $attachmentName;
		$this->_sent = false;
	}
	
	/**
	 * Like $this->addAttachmentContent(), but first empties out any
	 * attachments that have already been added.
	 *
	 * @param string $content
	 * @param string $attachmentName
	 */
	public function setAttachmentContent($content, $attachmentName) {
		$this->_attachmentContent = array();
		$this->_attachmentFilenames = array();
		$this->addAttachmentContent($content, $attachmentName);
	}
	
	/**
	 * Adds to the list of files attached to this email.
	 *
	 * @param string $file
	 * @param string $attachmentName = null
	 */
	public function addAttachment($file, $attachmentName = null) {
		if (!file_exists($file)) {
			throw new EmailException(
				'File "' . $file . '" does not exist.'
			);
		}
		$content = file_get_contents($file);
		if (!$attachmentName) {
			$attachmentName = basename($file);
		}
		$this->addAttachmentContent($content, $attachmentName);
	}
	
	/**
	 * Like $this->addAttachment, but first empties out any attachments that
	 * have already been added.
	 *
	 * @param string $file
	 * @param string $attachmentName = null
	 */
	public function setAttachment($file, $attachmentName = null) {
		$this->_attachmentContent = array();
		$this->_attachmentFilenames = array();
		$this->addAttachment($file, $attachmentName);
	}

	/**
	 * Sends the message and returns a boolean value indicating whether or not
	 * the sending took place. The reasons why this method might return a false
	 * value include the following:
	 *
	 * 1) This system does not support sending email via PHP's mail() function
	 * 2) This email was already sent, without the subject, message or
	 * attachment content having been changed
	 * 3) PHP's mail() function returned a false value
	 *
	 * @return boolean
	 */
	public function mail() {
		$attachmentCount = count($this->_attachmentContent);
		if ($this->_sent) {
			return false;
		}
		elseif (PFX_UNIT_TEST) {
			$GLOBALS['__lastEmailSent'] = array(
				'sent_time' => time(),
				'to' => $this->getRecipient(),
				'subject' => $this->getSubject(),
				'from' => $this->getFromAddress(),
				'from_name' => $this->getFromName(),
				'reply_to' => $this->getReplyTo(),
				'charset' => $this->getCharset(),
				'message' => $this->getMessage(),
				'attachment_filenames' => $this->_attachmentFilenames,
				'attachment_content' => array()
			);
			foreach ($this->_attachmentContent as $attachment) {
				$GLOBALS['__lastEmailSent'][
					'attachment_content'
				][] = base64_decode($attachment);
			}
			$result = true;
		}
		elseif (!PFXUtils::hasMail()) {
			echo 'Mailable content (' . $attachmentCount . ' attachments):'
			   . PHP_EOL . PHP_EOL . $this->_message . PHP_EOL;
			if ($attachmentCount) {
				$cwd = getcwd();
				echo 'Attempting to dump attachments in ' . $cwd . PHP_EOL;
				for ($i = 0; $i < $attachmentCount; $i++) {
					$file = $cwd . DIRECTORY_SEPARATOR
						  . $this->_attachmentFilenames[$i];
					file_put_contents(
						$file,
						base64_decode($this->_attachmentContent[$i])
					);
				}
			}
			$result = false;
		}
		else {
			$uid = uniqid();
			$content = sprintf(
				"From: %s <%s>\r\nReply-To: %s\r\nMIME-Version: 1.0\r\n" .
				"Content-Type: multipart/mixed; boundary=\"%s\"\r\n" .
				"This is a multi-part message in MIME format.\r\n" .
				"--%s\r\nContent-type: text/plain; charset=%s\r\n" .
				"Content-Transfer-Encoding: 7bit\r\n\r\n%s\r\n",
				$this->getFromName(),
				$this->getFromAddress(),
				$this->getReplyTo(),
				$uid,
				$uid,
				$this->getCharset(),
				$this->getMessage()
			);
			for ($i = 0; $i < $attachmentCount; $i++) {
				$content .= sprintf(
					"--%s\r\n" .
					"Content-Type: application/octet-stream; name=\"%s\"\r\n" .
					"Content-Transfer-Encoding: base64\r\n" .
					"Content-Disposition: attachment; filename=\"%s\"\r\n" .
					"\r\n%s\r\n",
					$uid,
					$this->_attachmentFilenames[$i],
					$this->_attachmentFilenames[$i],
					$this->_attachmentContent[$i]
				);
			}
			$content .= '--' . $uid . '--';
			$result = mail(
				$this->getRecipient(), $this->getSubject(), '', $content
			);
		}
		$this->_sent = true;
		return $result;
	}

	/**
	 * Alias for $this->mail().
	 */
	public function send() {
		$this->mail();
	}
	
	/**
	 * Returns a boolean value indicating whether a message has been set in
	 * this instance.
	 *
	 * @return boolean
	 */
	public function hasMessage() {
		return strlen($this->_message) > 0;
	}
	
	/**
	 * @return string
	 */
	public function getMessage() {
		return $this->_message;
	}
	
	/**
	 * @return int
	 */
	public function getAttachmentCount() {
		return count($this->_attachmentContent);
	}
	
	/**
	 * @return string
	 */
	public function getFromName() {
		return $this->_fromName ? $this->_fromName : self::$_DEFAULT_FROM_NAME;
	}
	
	/**
	 * @return string
	 */
	public function getFromAddress() {
		return $this->_from ? $this->_from :
			self::$_DEFAULT_FROM_USER . '@' . self::$_DEFAULT_FROM_HOSTNAME;
	}
	
	/**
	 * @return string
	 */
	public function getReplyTo() {
		return $this->_replyTo ? $this->_replyTo : self::$_DEFAULT_REPLY_TO;
	}
	
	/**
	 * @return string
	 */
	public function getRecipient() {
		return $this->_to;
	}

	/**
	 * @return string
	 */
	public function getSubject() {
		return $this->_subject;
	}
	
	/**
	 * @return string
	 */
	public function getCharset() {
		return $this->_charset ? $this->_charset : self::$_defaultCharset;
	}
	
	/**
	 * @param int $attachmentIndex
	 * @return string
	 */
	public function getAttachmentName($attachmentIndex) {
		if (!isset($this->_attachmentFilenames[$attachmentIndex])) {
			throw new EmailException(
				'Could not find an attachment at index ' . $attachmentIndex .
				'.'
			);
		}
		return $this->_attachmentFilenames[$attachmentIndex];
	}
	
	/**
	 * @param int $attachmentIndex
	 * @return string
	 */
	public function getAttachmentContent($attachmentIndex) {
		if (!isset($this->_attachmentContent[$attachmentIndex])) {
			throw new EmailException(
				'Could not find an attachment at index ' . $attachmentIndex .
				'.'
			);
		}
		return base64_decode($this->_attachmentContent[$attachmentIndex]);
	}
}
?>
