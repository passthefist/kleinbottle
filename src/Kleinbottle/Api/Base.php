<?php
namespace Kleinbottle\Api;

class Base {
    private $filters = array();

    private $inputSchemas = array();
    private $outputSchemas = array();

    // Defines the mapping between HTTP methods
    // and the controller actions
    protected $resourceMapping = array(
        'fetch' => 'GET',
        'update' => 'PUT',
        'delete' => 'DELETE',
    );

    protected $collectionMapping = array(
        'find' => 'GET',
        'create' => 'POST',
        'bulkUpdate' => 'PUT',
        'deleteAll' => 'DELETE',
    );

    public static function api() {
        return new static();
    }

    protected function registerInputSchema($method, $schema) {
        $this->inputSchemas[$method] = new \Kleinbottle\Schema\Validator($schema);
    }

    protected function registerOutputSchema($method, $schema) {
        $this->ouputSchemas[$method] = new \Kleinbottle\Schema\Validator($schema);
    }

    protected function registerCustomAction($method, $action = "POST") {
        $this->actionMethods[$method]= $action;
    }

    public function respondsTo($method) {
        return method_exists($this, $method);
    }

    public function actionFor($method) {
        $map = $this->methodMapping();
        return $map[$method];
    }

    public function methodMapping() {
        return array_merge(
            $this->resourceMapping,
            $this->collectionMapping
        );
    }

    public function getCollectionMethods() {
        return array_intersect(
            get_class_methods($this),
            array_keys($this->collectionMapping)
        );
    }

    public function getResourceMethods() {
        return array_intersect(
            get_class_methods($this),
            array_keys($this->resourceMapping)
        );
    }

    public function inputSchemaFor($method) {
        if (!isset($this->inputSchemas[$method])) {
            return new \Kleinbottle\Schema\Validator(array());
        }
        return $this->inputSchemas[$method];
    }

    public function outputSchemaFor($method) {
        if (!isset($this->outputSchemas[$method])) {
            return array();
        }
        return $this->outputSchemas[$method];
    }

    protected function validateInputFor($method, $args) {
        return $this->inputSchemaFor($method)->validate($args);
    }

    //TODO ensure method is protected
    protected function registerFilter($method, $filter) {
        if (!isset($this->filters[$method])) {
            $this->filters[$method] = array();
        }
        $this->filters[$method][]= $filter;
    }

    protected function registerFilters($method, $filters) {
        if (!isset($this->filters[$method])) {
            $this->filters[$method] = array();
        }

        array_merge($this->filters[$method], $filters);
    }

    public function filtersFor($methodName) {
        return $this->filters[$method];
    }

    // This can be replaced by slim's middleware implementation?
    public function loadChainFor($method) {
        $chain = new \ehough_chaingang_impl_StandardChain();

        $beforeActions = $this->filters($action);

        if (is_array($beforeActions)) {
            foreach($beforeActions as $command) {
                $chain->addCommand($command);
            }
        }
        return $chain;
    }

    public function hasFilters($action) {
        return isset($this->filters[$method]);
    }

    public function invoke($method, $params) {
        $params = $this->validateInputFor($method, $params);

        return $this->$method($params);
    }

    public function invokeWithRequest($action, $request, $response) {
        $params = $request->params();

        $result = $this->invoke($action,$params);

        $response->body($result);
    }

    protected function invokeWithFilters($action, $params) {
        $chain = $this->loadChainFor($action);

        $context = new \KlienBottle\Context();
        $context->put('params',$request);

        if (!$chain->execute($context)) {
            $this->$action($request, $response);
        } else {
            // ??
        }
    }

    // Stub. Will add documentation later
    public function getDocumentation() {
        return new ApiDocumentor($this);
    }

    public function __call($action, $args) {
        return $this->invoke($action, $request, $response);
    }
}
