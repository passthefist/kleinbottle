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
        'index' => 'GET',
        'create' => 'POST',
        'bulkUpdate' => 'PUT',
        'deleteAll' => 'DELETE',
    );

    public static function api() {
        return new static();
    }

    public static function raw() {
        return new RawWrapper(new static());
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
        $routableMethods = array_merge(
            $this->getCollectionMethods(),
            $this->getResourceMethods()
        );
        return in_array($method, $routableMethods);
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

    public function customResourceHandler($action, $method) {
        $this->Mapping[$method] = $action;
    }

    public function customCollectionHandler($action, $method) {
        $this->collectionMapping[$method] = $action;
    }

    public function customHandler($action, $method) {
        $this->customCollectionHandler($action, $method);
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

        $params = json_decode(json_encode($params));

        return $this->$method($params);
    }

    public function invokeWithRequest($action, $request, $response) {
        $params = $request->params();

        $result = $this->invoke($action,$params);
        $result = $this->formatResponse($action,$result, $request->format);

        $response->body($result);
    }

    protected function defaultFormat($result, $format) {
        switch($format) {
        default:
            return json_encode($result);
        }
    }

    protected function formatResponse($action,$result,$format) {
        $format = $format?:'json';
        $action = ucfirst($action);

        $formatMethod = "format$action";

        if(method_exists($this,$formatMethod)) {
            return $this->$formatMethod($result, $format);
        } else {
            return $this->defaultFormat($result, $format);
        }
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
    
    public function renderDocumentation($key, $item, $depth, $isLast = false){

        $comma = "";
        if(!$isLast){
            $comma = ",";
        }

        echo "<article class='tools-api-doc'>";

        if(!empty($item['required'])){
            echo '<span title="required" class="required">★</span>';
        }

        if(isset($key)){
            echo "<h2 class='tools-api-doc'>\"$key\": </h2>";
        }

        echo "<h2 class='tools-api-doc-value' >&nbsp;&lt;";

        if(!empty($item['type'])){
            echo $item['type'];
        }
        
        echo "&gt; </h2>";

        if(isset($key)){

            if(!empty($item['title'])){
                echo "<h3 class='tools-api-doc'>".$item['title']."</h3>";
            }

            $comment = "";
            if(isset($item['description'])){
                $comment .= $item['description'];
            }

            $tmp = array_flip(array(
                "minimum",
                "maximum",
                "default"
            ));
            $info = array_intersect_key($item, $tmp);

            if($info){
                $comment .= "\n\n   ";
                foreach($info as $key => $value){
                    $pairs[] = " $key: ".var_export($value, true);
                }

                $comment .= implode(",", $pairs);
            }

            echo $this->makeComment($comment);

        }

        if(!empty($item['type'])){
            switch($item['type']){
                case "object":
                    if($depth==0){
                        if(isset($item['name'])){
                            echo "
                            <header>
                             <h1>".$item['name']."</h1>";
                            if(isset($item['description'])){
                                echo "<p class='tools-api-doc'>".$item['description']."</p>";
                            }

                            echo "</header>
                        ";
                        }
                    }
                    echo "<span class='tools-api-doc-container'>{</span>";

                    $i = 0;
                    $numProps = count($item['properties']);
                    foreach($item['properties'] as $key => $prop){
                        $this->renderDocumentation($key, $prop, $depth+1, $i==($numProps-1));
                        $i++;
                    }

                    echo "<span class='tools-api-doc-container'>}$comma</span>";
                    break;
                case "array":
                    echo "<span class='tools-api-doc-container'>[</span>";

                    $this->renderDocumentation($key, $item['items'], $depth+1, true);

                    echo "<span class='tools-api-doc-container'>]$comma</span>";

                    break;
                case "number":
                    break;
            }
        }
        echo "</article>";
    }
    
    protected function makeComment($str){
        $wrapStr = wordwrap($str, 80, "\n    ");
        return "<pre class='tools-api-doc'>/*  ".$wrapStr." */</pre>";
    }

    public function __call($action, $args) {
        return $this->invoke($action, $request, $response);
    }
}
