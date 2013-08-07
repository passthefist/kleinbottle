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

    public function __construct($controller) {
        $this->controller = $controller;
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
}
