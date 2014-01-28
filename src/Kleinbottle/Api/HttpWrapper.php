<?php
namespace Kleinbottle\Api;

class HttpWrapper extends Base {
    private $controller;

    public function __construct($controller) {
        $this->controller = $controller;
    }

}
