<?php
namespace Google;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

abstract class ServiceAccountAPIRequest extends \GenericAPI\Request {
    protected static $_oauthService;
    // We won't get a response back if our request triggers a 304
    protected $_expectResponseLength = false;
    
    /**
     * @param OAuth\IService $service
     */
    public static function registerOAuthService(\OAuth\IService $service) {
        static::$_oauthService = $service;
    }
    
    /**
     * @return boolean
     */
    public static function hasOAuthService() {
        return static::$_oauthService !== null;
    }
    
    /**
     * Silently discards any Authorization header that a caller attempts to set
     * (these are added automatically by the registered OAuth service).
     *
     * @param string $header
     * [@param string $header]
     */
    public function setHeader() {
        call_user_func_array('parent::setHeader', func_get_args());
        /* The size of the array could change during the loop, so we have to
        test it on every iteration. */
        for ($i = 0; $i < count($this->_extraHeaders); $i++) {
            if (strtolower(
                substr(trim($this->_extraHeaders[$i]), 0, 13)
            ) == 'authorization') {
                array_splice($this->_extraHeaders, $i, 1);
                /* Now the header array will be shorter so we have to adjust
                the offset to compensate. */
                $i--;
            }
        }
    }
    
    /**
     * Extends the parent method to handle the automatic passing of the
     * appropriate Content-Type parameter for JSON payloads.
     *
     * @param array, string $args
     */
    public function setPayload($args, $contentType = 'application/json') {
        parent::setPayload($args, $contentType);
    }
    
    /**
     * @return array
     */
    public function getHeaders() {
        if (!static::$_oauthService) {
            throw new LogicException(
                'An OAuth service must be registered before this request ' .
                'can be used.'
            );
        }
        $headers = parent::getHeaders();
        $headers[] = 'Authorization: Bearer '
                   . static::$_oauthService->getToken();
        return $headers;
    }
}
?>
