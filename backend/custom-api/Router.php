<?php
namespace Api;

class Router {
    private $routes = [];
    private $middleware = [];
    
    public function addMiddleware($middleware) {
        $this->middleware[] = $middleware;
    }
    
    public function get($path, $handler, $options = []) {
        $this->addRoute('GET', $path, $handler, $options);
    }
    
    public function post($path, $handler, $options = []) {
        $this->addRoute('POST', $path, $handler, $options);
    }
    
    public function put($path, $handler, $options = []) {
        $this->addRoute('PUT', $path, $handler, $options);
    }
    
    public function delete($path, $handler, $options = []) {
        $this->addRoute('DELETE', $path, $handler, $options);
    }
    
    public function patch($path, $handler, $options = []) {
        $this->addRoute('PATCH', $path, $handler, $options);
    }
    
    private function addRoute($method, $path, $handler, $options = []) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'pattern' => $this->convertPathToRegex($path),
            'options' => $options
        ];
    }
    
    private function convertPathToRegex($path) {
        $pattern = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Remove various API prefixes
        $path = preg_replace('#^(/custom)?/api(/index\.php)?#', '', $path);
        if (empty($path)) {
            $path = '/';
        }
        
        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        // Create request object
        $request = new Request($method, $path, $this->getRequestData());
        
        // Find matching route first to check options
        $matchedRoute = null;
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                $matchedRoute = $route;
                // Extract route parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->setParams($params);
                break;
            }
        }
        
        if (!$matchedRoute) {
            $this->sendError(404, 'Route not found');
            return;
        }
        
        // Run middleware only if skipAuth is not set
        if (empty($matchedRoute['options']['skipAuth'])) {
            foreach ($this->middleware as $middleware) {
                $result = $middleware->handle($request);
                if ($result === false) {
                    return;
                }
            }
        }
        
        // Call handler
        list($class, $method) = explode('::', $matchedRoute['handler']);
        $controller = new $class();
        
        // Check if route has parameters
        if (!empty($params)) {
            $response = $controller->$method($request, $params);
        } else {
            $response = $controller->$method($request);
        }
        
        // Send response
        $this->sendResponse($response);
    }
    
    private function getRequestData() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        
        return $_POST;
    }
    
    private function sendResponse($response) {
        header('Content-Type: application/json');
        
        if ($response instanceof Response) {
            http_response_code($response->getStatusCode());
            echo json_encode($response->getData());
        } else {
            echo json_encode($response);
        }
    }
    
    private function sendError($code, $message) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
    }
}