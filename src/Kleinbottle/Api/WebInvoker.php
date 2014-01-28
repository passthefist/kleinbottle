<?php
namespace Kleinbottle\Filter;

require __DIR__.'/../validation/jsv4.php';

class WebInvoker {
    private $controller;

    public function __construct($controller) {
        $this->controller = $controller;
    }

    public function __call($method, $args) {
        $request = $args[0];
        $response = $args[0];

        $params = $request->paramsNamed()->all();

        $result = call_user_func_array(
            array($this->controller, $method),
            array($params)
        );

        // TODO allow customization
        return json_encode($result);
    }
}

