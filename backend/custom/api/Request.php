<?php
namespace Api;

class Request {
    private $method;
    private $path;
    private $data;
    private $params = [];
    private $headers = [];
    public $user = null;
    
    public function __construct($method, $path, $data) {
        $this->method = $method;
        $this->path = $path;
        $this->data = $data;
        $this->headers = getallheaders();
    }
    
    public function getMethod() {
        return $this->method;
    }
    
    public function getPath() {
        return $this->path;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function get($key, $default = null) {
        return $this->data[$key] ?? $default;
    }
    
    public function setParams($params) {
        $this->params = $params;
    }
    
    public function getParam($key, $default = null) {
        return $this->params[$key] ?? $default;
    }
    
    public function getHeader($key) {
        return $this->headers[$key] ?? null;
    }
    
    public function getAuthToken() {
        $auth = $this->getHeader('Authorization');
        if ($auth && strpos($auth, 'Bearer ') === 0) {
            return substr($auth, 7);
        }
        return null;
    }
}