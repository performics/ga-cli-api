<?php
namespace Google\Analytics;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

class APIRequest extends \Google\ServiceAccountAPIRequest {
    protected static $_baseURL = 'https://www.googleapis.com/analytics/v3/';
    protected static $_oauthService;
}
?>