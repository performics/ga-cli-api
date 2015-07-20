<?php
namespace OAuth;
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'Exception.class.php');

interface IService {
    /**
     * @return string
     */
    public function getToken();
}
?>