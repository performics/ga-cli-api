<?php
class EmailTestCase extends TestHelpers\TempFileTestCase {
    private function _runAssertionList(
        array $assertions,
        $strictComparison = true
    ) {
        foreach ($assertions as $key => $value) {
            if ($strictComparison) {
                $this->assertSame(
                    $value,
                    $GLOBALS['__lastEmailSent'][$key],
                    'Assertion failed when comparing value for key "' . $key . '".'
                );
            }
            else {
                $this->assertEquals(
                    $value,
                    $GLOBALS['__lastEmailSent'][$key],
                    'Assertion failed when comparing value for key "' . $key . '".'
                );
            }
        }
    }
    
    /**
     * Tests whether sensible default properties are used to indicate the
     * origin of this message when the settings that control this are not used.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDefaultProperties() {
        $email = new Email('somebody@somewhere.com');
        $email->mail();
        // These defaults depend on environment variables
        $expectedHostName = getenv('HOSTNAME');
        if (!$expectedHostName) {
            $expectedHostName = 'local.host';
        }
        $expectedUser = getenv('USER');
        if (!$expectedUser) {
            $expectedUser = 'noreply';
        }
        $expectedFromName = getenv('EMAIL_FROM');
        if (!$expectedFromName) {
            $expectedFromName = 'Nobody';
        }
        $this->assertEquals(
            $expectedUser . '@' . $expectedHostName,
            $GLOBALS['__lastEmailSent']['from']
        );
        $this->assertEquals(
            $expectedUser . '@' . $expectedHostName,
            $GLOBALS['__lastEmailSent']['reply_to']
        );
        $this->assertEquals(
            $expectedFromName, $GLOBALS['__lastEmailSent']['from_name']
        );
        $this->assertEquals(
            ini_get('default_charset'), $GLOBALS['__lastEmailSent']['charset']
        );
    }
    
    /**
     * Tests whether the settings that control various email properties are
     * used where required.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSettingUsage() {
        define('EMAIL_FROM_HOSTNAME', 'space.com');
        define('EMAIL_FROM_USER', 'the_moon');
        define('EMAIL_FROM_NAME', 'Big Moon Man');
        define('EMAIL_REPLY_TO', 'noreply@space.com');
        $email = new Email('somebody@somewhere.com');
        $email->mail();
        $this->assertEquals(
            EMAIL_FROM_USER . '@' . EMAIL_FROM_HOSTNAME,
            $GLOBALS['__lastEmailSent']['from']
        );
        $this->assertEquals(
            EMAIL_REPLY_TO, $GLOBALS['__lastEmailSent']['reply_to']
        );
        $this->assertEquals(
            EMAIL_FROM_NAME, $GLOBALS['__lastEmailSent']['from_name']
        );
    }
    
    /**
     * Tests whether validation fails when the EMAIL_FROM_USER setting is
     * defined as something that does not work as the local part of an email
     * address.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @expectedException EmailException
     * @expectedExceptionMessage Invalid origin address.
     */
    public function testBadUserSetting() {
        define('EMAIL_FROM_USER', 'foo@bar');
        new Email('you@you.com');
    }
    
    /**
     * Tests whether validation fails when the EMAIL_FROM_HOSTNAME setting is
     * defined as something that does not work as an email domain.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @expectedException EmailException
     * @expectedExceptionMessage Invalid origin address.
     */
    public function testBadHostnameSetting() {
        define('EMAIL_FROM_HOSTNAME', 'foo@bar');
        new Email('you@you.com');
    }
    
    /**
     * Tests whether validation fails when the EMAIL_REPLY_TO setting is
     * defined as something other than a single valid email address.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @expectedException EmailException
     * @expectedExceptionMessage Invalid reply-to address.
     */
    public function testBadReplyToSetting() {
        /* PFXUtils::validateSettings() is already guaranteed to test whether
        the setting is a valid email address or list of email addresses, so the
        best thing to test here is a list of valid email addresses, which
        passes the setting validation but should fail the property validation.
        */
        define('EMAIL_REPLY_TO', 'me@you.com,you@me.com');
        new Email('you@you.com');
    }
    
    /**
     * Tests the validation of various email properties.
     */
    public function testValidation() {
        /* A Validator instance handles validation of email addresses in the
        Email class, and as that class has its own unit tests, there's no need
        to go crazy with testing lots of different bad values in this context.
        */
        $this->assertThrows(
            'EmailException',
            function() { new Email(null); }
        );
        $this->assertThrows(
            'EmailException',
            function() { new Email(''); }
        );
        $this->assertThrows(
            'EmailException',
            function() { new Email('aosidfj98gah'); }
        );
        // A list of email addresses should be allowed, though
        $email = new Email('me@somewhere.com,you@somewhere-else.net');
        /* The same validation rules apply to Email::setFromAddress() and
        Email::setReplyToAddress(). */
        foreach (array('setFromAddress', 'setReplyToAddress') as $method) {
            $this->assertThrows(
                'EmailException',
                array($email, $method),
                array('209ag098n')
            );
            $this->assertThrows(
                'EmailException',
                array($email, $method),
                array('email-1@website.com,email-2@website.com')
            );
            $this->assertThrows(
                'EmailException',
                array($email, $method),
                array(array('email-address@website.com'))
            );
            $this->assertThrows(
                'EmailException',
                array($email, $method),
                array(new stdClass())
            );
        }
        /* This method accepts any scalar, and it's okay with nulls too, so we
        have to go to a lot of trouble to fool it. */
        $this->assertThrows(
            'EmailException',
            array($email, 'setFromName'),
            array(array('Joe Blow'))
        );
        $this->assertThrows(
            'EmailException',
            array($email, 'setFromName'),
            array(new stdClass())
        );
        /* Passing a nonexistent file as an attachment either as a constructor
        argument or an argument to Email::addAttachment() should throw an
        exception. */
        $this->assertThrows(
            'EmailException',
            function() { new Email('person@website.com', 'Subject', 'Message', '/some/file/that/does/not/exist'); }
        );
        $this->assertThrows(
            'EmailException',
            array($email, 'addAttachment'),
            array('/some/file/that/does/not/exist')
        );
        /* Trying to add attachment content without a file name should throw an
        exception. */
        $this->assertThrows(
            'EmailException',
            array($email, 'addAttachmentContent'),
            array('content', '')
        );
    }
    
    /**
     * Tests normal usage without attachments.
     */
    public function testUsageWithoutAttachments() {
        $toAddress = 'somebody@someplace.com';
        $subject = null;
        $message = null;
        $fromAddress = null;
        $fromName = null;
        $replyTo = null;
        $charset = ini_get('default_charset');
        $attachmentFilenames = array();
        $attachmentContent = array();
        $expected = array(
            'to' => &$toAddress,
            'subject' => &$subject,
            'from' => &$fromAddress,
            'from_name' => &$fromName,
            'reply_to' => &$replyTo,
            'charset' => &$charset,
            'message' => &$message,
            'attachment_filenames' => &$attachmentFilenames,
            'attachment_content' => &$attachmentContent
        );
        $email = new Email($toAddress);
        /* Until we set them otherwise, these properties will be set to their
        default values. */
        $fromAddress = $email->getFromAddress();
        $fromName = $email->getFromName();
        $replyTo = $email->getReplyTo();
        $charset = $email->getCharset();
        $email->mail();
        $this->_runAssertionList($expected);
        $subject = 'Check out this one weird trick';
        $email = new Email($toAddress, $subject);
        $email->mail();
        $this->_runAssertionList($expected);
        $message = <<<EOF
Psych! Made you click.
I don't actually have any weird tricks to share with you.
EOF;
        $email = new Email($toAddress, $subject, $message);
        $email->mail();
        $this->_runAssertionList($expected);
        /* Once an email is sent, further calls to Email::mail() will not
        resend it until there is a change in the subject, message, or
        attachments. */
        $sentTime = $GLOBALS['__lastEmailSent']['sent_time'];
        sleep(1);
        $email->mail();
        $this->_runAssertionList($expected);
        $this->assertEquals(
            $sentTime, $GLOBALS['__lastEmailSent']['sent_time']
        );
        $subject = 'Hey you! Read my email';
        $email->setSubject($subject);
        $email->mail();
        $this->_runAssertionList($expected);
        $this->assertGreaterThan(
            $sentTime, $GLOBALS['__lastEmailSent']['sent_time']
        );
        $sentTime = $GLOBALS['__lastEmailSent']['sent_time'];
        sleep(1);
        $additionalMessage = " Don't you feel silly now?";
        $message .= $additionalMessage;
        $email->appendMessage($additionalMessage);
        $email->mail();
        $this->_runAssertionList($expected);
        $this->assertGreaterThan(
            $sentTime, $GLOBALS['__lastEmailSent']['sent_time']
        );
        sleep(1);
        $additionalMessage = 'Please disregard.';
        $message .= PHP_EOL . PHP_EOL . $additionalMessage;
        // Append as a new paragraph
        $email->appendMessage($additionalMessage, true);
        $email->mail();
        $this->_runAssertionList($expected);
        $this->assertGreaterThan(
            $sentTime, $GLOBALS['__lastEmailSent']['sent_time']
        );
        // Play with the other attributes that must be set before sending
        $toAddress = 'foo@bar.baz';
        $subject = 'Hello world';
        $message = '';
        $fromAddress = 'me@a-website.com';
        $fromName = 'Happ Happablapp';
        $replyTo = 'noreply@yourmom.org';
        $charset = 'BASE64';
        $email = new Email($toAddress, $subject, $message);
        $email->setFromAddress($fromAddress);
        $email->setFromName($fromName);
        $email->setReplyToAddress($replyTo);
        $email->setCharset($charset);
        $email->mail();
        $this->_runAssertionList($expected);
    }
    
    /**
     * Runs tests focused on the usage of attachments.
     */
    public function testUsageWithAttachments() {
        $tempFile = self::_createTempFile(
            null,
            'This is an attached text file.'
        );
        $toAddress = 'somebody@someplace.com';
        $subject = 'An email with attachments';
        $message = 'This email has attachments. I hope you enjoy them.';
        $fromAddress = null;
        $fromName = null;
        $replyTo = null;
        $charset = ini_get('default_charset');
        $attachmentFilenames = array(basename($tempFile));
        $attachmentContent = array(file_get_contents($tempFile));
        $expected = array(
            'to' => &$toAddress,
            'subject' => &$subject,
            'from' => &$fromAddress,
            'from_name' => &$fromName,
            'reply_to' => &$replyTo,
            'charset' => &$charset,
            'message' => &$message,
            'attachment_filenames' => &$attachmentFilenames,
            'attachment_content' => &$attachmentContent
        );
        $email = new Email($toAddress, $subject, $message, $tempFile);
        $fromAddress = $email->getFromAddress();
        $fromName = $email->getFromName();
        $replyTo = $email->getReplyTo();
        $charset = $email->getCharset();
        $email->mail();
        $this->_runAssertionList($expected);
        // Try the same attachment with a different file name
        $email->setAttachment($tempFile, 'foo.txt');
        $attachmentFilenames[0] = 'foo.txt';
        $email->mail();
        $this->_runAssertionList($expected);
        // Try adding a second attachment
        $tempFile2 = self::_createTempFile(
            null, openssl_random_pseudo_bytes(1024 * 12)
        );
        $attachmentContent[] = file_get_contents($tempFile2);
        $attachmentFilenames[] = basename($tempFile2);
        $email->addAttachment($tempFile2);
        $email->mail();
        $this->_runAssertionList($expected);
        // Try adding content directly
        $content = openssl_random_pseudo_bytes(1024 * 4);
        $attachmentContent[] = $content;
        $attachmentFilenames[] = 'somefile.bin';
        $email->addAttachmentContent($content, 'somefile.bin');
        $email->mail();
        $this->_runAssertionList($expected);
        // Do the same, but blow away existing attachments
        $content = openssl_random_pseudo_bytes(1024 * 4);
        $attachmentContent = array($content);
        $attachmentFilenames = array('somefile.bin');
        $email->setAttachmentContent($content, 'somefile.bin');
        $email->mail();
        $this->_runAssertionList($expected);
    }
}
?>