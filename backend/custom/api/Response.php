<?php
namespace Api;

class Response {
    private $data;
    private $statusCode;
    
    public function __construct($data = null, $statusCode = 200) {
        $this->data = $data;
        $this->statusCode = $statusCode;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getStatusCode() {
        return $this->statusCode;
    }
    
    public static function success($data = null) {
        return new self(['success' => true, 'data' => $data], 200);
    }
    
    public static function created($data = null) {
        return new self(['success' => true, 'data' => $data], 201);
    }
    
    public static function error($message, $statusCode = 400) {
        return new self(['success' => false, 'error' => $message], $statusCode);
    }
    
    public static function notFound($message = 'Resource not found') {
        return new self(['success' => false, 'error' => $message], 404);
    }
    
    public static function unauthorized($message = 'Unauthorized') {
        return new self(['success' => false, 'error' => $message], 401);
    }
    
    public function withJson($data, $statusCode = null) {
        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }
        $this->data = $data;
        return $this;
    }
    
    public function json($data, $statusCode = null) {
        return $this->withJson($data, $statusCode);
    }
    
    public function send() {
        http_response_code($this->statusCode);
        header('Content-Type: application/json');
        echo json_encode($this->data);
        exit;
    }
}