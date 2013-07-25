<?php
namespace Kleinbottle;

class Router {
    private $urlRoot;
    private $routes;
    private $basePath;
    private $klein;
    private $devMode;
    private $captureMode;

    // Defines the mapping between HTTP methods
    // and the controller actions
    private $collectionMapping = array(
        'index' => 'GET',
        'create' => 'POST',
        'bulkUpdate' => 'PUT',
        'deleteAll' => 'DELETE',
    );

    private $resourceMapping = array(
        'fetch' => 'GET',
        'update' => 'PUT',
        'delete' => 'DELETE',
    );

    public function __construct($urlRoot, $routes, $controllerBasePath, $controllerNamespace) {
        $this->routes = $this->flattenRoutes($routes);
        $this->urlRoot = $urlRoot;
        $this->basePath = $controllerBasePath;
        $this->baseNamespace = $controllerNamespace;

        $this->klein = new \Klein\Klein();
        $this->devMode = false;
        $this->sendOutput();
    }

    public function getRoutes() {
        return $this->routes;
    }

    public function collectionResponseMap(array $map) {
        $this->collectionMapping = $map;
    }

    public function resourceResponseMap(array $map) {
        $this->resourceMapping = $map;
    }

    public function sendOutput() {
        $this->captureMode = \Klein\Klein::DISPATCH_NO_CAPTURE;
    }

    public function captureOutput() {
        $this->captureMode = \Klein\Klein::DISPATCH_CAPTURE_AND_RETURN;
    }

    public function loadRoutes() {
        $router = $this;
        $routes = $this->routes;

        $this->klein->with($this->urlRoot, function () use($routes, $router) {
            foreach($routes as $route => $controller) {
                if ($router->isCollectionRoute($route)) {
                    $router->routeCollection($route, $controller);
                } elseif ($router->isResourceRoute($route)) {
                    $router->routeResource($route, $controller);
                } else {
                    throw new Exception(
<<<MSG
Route '$route' not routable.
Valid routes look like '/base/[i:intParam]/subpath/[a:alphanumParam]'

The values intParam and alphanumParam will be passed to the controller
by those names.

The parser is strict, so trailing backslashes, unmatched braces, slashes
in braces, etc. are not routeable. Variables and paths do not need to
alternate, but it usually makes for better APIs.
MSG
                    );
                }
            }
        });
    }

    public function routeRequest($requestUrl = null, $method = null) {
        // Use the provided url for the request,
        $overrides = array_filter(array(
            'REQUEST_URI' => $requestUrl,
            'REQUEST_METHOD' => $method
        ));
        // Or load from the SEVER global
        $serverParams = array_merge($_SERVER, $overrides);

        $request = new \Klein\Request(
            $_GET,
            $_POST,
            $_COOKIE,
            $serverParams,
            $_FILES,
            null // Let our content getter take care of the "body"
        );

        $response = new \Klein\Response();
        $this->klein->dispatch($request, $response, true, $this->captureMode);

        // This is primarily for testing. The klein dispatcher
        // writes $response->body() to the HTTP response
        return $response;
    }

    /**
     * Returns the name of the class for the controller
     */
    public function getControllerClass($controller) {
        return "$this->baseNamespace\\$controller";
    }

    /**
     * Return a live object that is the controller class
     */
    public function loadController($controller) {
        $class = $this->getControllerClass($controller);
        $filePath = "$this->basePath/$controller";

        require_once "$filePath.php";
        return new $class;
    }

    /**
     * Is this a route to a collection or a resource?
     *
     * Determined by looking at the last part of the route's
     * path, and seeing if it's a matchstring ([:variable]).
     *
     * If the last part is a matchstring, then this is for
     * a resource
     */
    public function isResourceRoute($route) {
        $last = array_pop(explode('/',$route));

        return preg_match('#^\[.?:[\w_]+]$/?#',$last) === 1;
    }

    /**
     * Is this a route to a collection or a resource?
     *
     * Determined by looking at the last part of the route's
     * path, and seeing if it's a 'word' (alpanum + _)
     * aka valid php variable name.
     *
     * If the last part is a word, then this is for
     * a collection
     */
    public function isCollectionRoute($route) {
        $last = array_pop(explode('/',$route));

        return preg_match('#^[\w_]+$#',$last) === 1;
    }

    /**
     * Route a URL to a controller, using the restful actions for
     * a collection.
     *
     * HTTP GET: controller->index()
     * HTTP POST: controller->create()
     * HTTP PUT: controller->bulkUpdate()
     * HTTP DELETE: controller->deleteAll()
     *
     * Collections support arbitray actions, like
     *
     * www.api.web.com/messages/search
     *
     * Would call the message search() on the messages controller
     */
    public function routeCollection($route, $controller) {
        $class = $this->loadController($controller);

        $actions = get_class_methods($class);

        foreach($actions as $action) {
            if (isset($this->collectionMapping[$action])) {
                $httpMethod = $this->collectionMapping[$action];

                $mappedRoute = $this->makeRoute(
                    $route,
                    $httpMethod,
                    $controller,
                    $action
                );
            } elseif (!isset($this->resourceMapping[$action])) {
                // Collections allow calling actions on the url
                $actionPath = $route."/$action";

                $this->makeRoute($actionPath,'POST',$controller,$action);
                // If we're in development, allow get requests to
                // call the action as well
                if ($this->devMode) {
                    $this->makeRoute($actionPath,'GET',$controller,$action);
                }
            }
        }
    }

    /**
     * Route a URL to a controller, using the restful actions for
     * a resource.
     *
     * HTTP GET: controller->fetch()
     * HTTP PUT: controller->update()
     * HTTP DELETE: controller->delete()
     */
    public function routeResource($route, $controller) {
        $class = $this->loadController($controller);

        $actions = get_class_methods($class);

        foreach($actions as $action) {
            if (isset($this->resourceMapping[$action])) {
                $httpMethod = $this->resourceMapping[$action];

                $mappedRoute = $this->makeRoute(
                    $route,
                    $httpMethod,
                    $controller,
                    $action
                );
            }
        }
    }

    /**
     * Actually map the route to the controller and it's action.
     *
     * $route the route to match
     * $httpMethod the http method to respond to
     * $path the namespace contollers live in
     * $controller the relative path/name of controller to load
     * $action the method to call on the controller
     */
    public function makeRoute($route, $httpMethod, $controller, $action) {
        $router = $this;

        $mappedRoute = $this->klein->respond($httpMethod, $route, function ($request, $response) use ($router, $controller, $action) {
            $router->loadController($controller)->$action($request, $response);
        });

        return $mappedRoute;
    }

    /**
     * Resolve the nesting of the routes array
     */
    private function flattenRoutes($routes, $parent = null) {
        $flattened = array();
        foreach($routes as $base => $action) {
            if (is_string($action)) {
                if ($parent !== null) {
                    $flattened[$parent.$base] = $action;
                } else {
                    $flattened[$base] = $action;
                }
            } elseif (is_array($action)) {
                $child = $this->flattenRoutes($action, $base);

                foreach( $child as $childBase => $childAction) {
                    if ($parent !== null) {
                        $flattened[$parent.$childBase] = $childAction;
                    } else {
                        $flattened[$childBase] = $childAction;
                    }
                }
            }
        }

        return $flattened;
    }
}

