<?php
namespace Api;

class Router {
    private $routes = [];
    private $middleware = [];
    
    public function addMiddleware($middleware) {
        $this->middleware[] = $middleware;
    }
    
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put($path, $handler) {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function delete($path, $handler) {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    private function addRoute($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'pattern' => $this->convertPathToRegex($path)
        ];
    }
    
    private function convertPathToRegex($path) {
        $pattern = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Remove both /custom/api and /custom/api/index.php
        $path = preg_replace('#^/custom/api(/index\.php)?#', '', $path);
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
        
        // Run middleware
        foreach ($this->middleware as $middleware) {
            $result = $middleware->handle($request);
            if ($result === false) {
                return;
            }
        }
        
        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                // Extract route parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->setParams($params);
                
                // Call handler
                list($class, $method) = explode('::', $route['handler']);
                $controller = new $class();
                $response = $controller->$method($request);
                
                // Send response
                $this->sendResponse($response);
                return;
            }
        }
        
        // No route found
        $this->sendError(404, 'Route not found');
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