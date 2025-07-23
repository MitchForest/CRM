<?php
namespace Api\Controllers;

use Api\Response;

class HealthController extends BaseController {
    
    /**
     * Health check endpoint
     * No authentication required
     */
    public function check($request) {
        $response = new Response();
        global $db, $sugar_config;
        
        $health = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => []
        ];
        
        // Check database connection
        try {
            if ($db && $db->checkConnection()) {
                $health['checks']['database'] = [
                    'status' => 'ok',
                    'message' => 'Database connection successful'
                ];
            } else {
                $health['checks']['database'] = [
                    'status' => 'error',
                    'message' => 'Database connection failed'
                ];
                $health['status'] = 'unhealthy';
            }
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'error',
                'message' => 'Database check failed: ' . $e->getMessage()
            ];
            $health['status'] = 'unhealthy';
        }
        
        // Check SuiteCRM configuration
        if (!empty($sugar_config)) {
            $health['checks']['configuration'] = [
                'status' => 'ok',
                'message' => 'SuiteCRM configuration loaded'
            ];
        } else {
            $health['checks']['configuration'] = [
                'status' => 'error',
                'message' => 'SuiteCRM configuration not loaded'
            ];
            $health['status'] = 'unhealthy';
        }
        
        // Check API directory
        if (is_writable(__DIR__ . '/..')) {
            $health['checks']['filesystem'] = [
                'status' => 'ok',
                'message' => 'API directory is writable'
            ];
        } else {
            $health['checks']['filesystem'] = [
                'status' => 'warning',
                'message' => 'API directory is not writable'
            ];
        }
        
        $statusCode = $health['status'] === 'healthy' ? 200 : 503;
        
        return new Response($health, $statusCode);
    }
}