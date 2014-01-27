<?php
namespace Kleinbottle;

/**
 * the action stuff could probably be it's own class/object
 * That would probably help with potential caching, since
 * much of this is trivially cacheable.
 *
 * I'm pretty sure this class is just a decorator...
 **/
class ControllerInvoker {
    private $controller;
    private $formatter;

    public function __construct($controller) {
        $this->controller = $controller;
        $this->formatter = 'json_decode';
    }

    public function chainDefMethod($action) {
        $action = ucfirst(strtolower($action));
        return "before$action";
    }

    public function getBeforeActionsFor($action) {
        $method = $this->chainDefMethod($action);
        return $this->controller->$method();
    }

    public function loadChainFor($action) {
        $chain = new \ehough_chaingang_impl_StandardChain();

        $beforeActions = $this->getBeforeActionsFor($action);

        if (is_array($beforeActions)) {
            foreach($beforeActions as $command) {
                $chain->addCommand($command);
            }
        }
        return $chain;
    }

    public function hasMiddleware($action) {
        return method_exists(
            $this->controller,
            $this->chainDefMethod($action)
        );
    }

    public function invoke($action, $request, $response) {
        if ($this->hasMiddleware($action)) {
            $this->invokeWithMiddleware($action, $request, $response);
        } else {
            $this->controller->$action($request, $response);
        }
    }

    public function invokeWithMiddleware($action, $request, $response) {
        $chain = $this->loadChainFor($action);

        $context = new \ehough_chaingang_impl_StandardContext();
        $context->put('request',$request);
        $context->put('response',$response);

        if (!$chain->execute($context)) {
            $this->controller->$action($request, $response);
        } else {
            // ??
        }
    }

    public function __call($action, $args) {
        $request = new \Klein\Request(
            $args[0],
            $_POST,
            $_COOKIE,
            $_SERVER,
            $_FILES,
            null // Let our content getter take care of the "body"
        );

        $response = new \Klein\Response();
        $response->shouldOverrideContentType(false);

        ob_start();
        $this->invoke($action, $request, $response);
        $responseBody = ob_get_clean();

        return call_user_func($this->formatter,$responseBody);
    }
}

