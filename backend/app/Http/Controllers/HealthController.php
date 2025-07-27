<?php

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class HealthController extends Controller
{
    /**
     * System health check endpoint
     * GET /api/health
     */
    public function check(Request $request, Response $response, array $args): Response
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => (new \DateTime())->format('c'),
            'checks' => []
        ];
        
        // Check database connection
        $health['checks']['database'] = $this->checkDatabase();
        if ($health['checks']['database']['status'] === 'error') {
            $health['status'] = 'unhealthy';
        }
        
        // Check filesystem
        $health['checks']['filesystem'] = $this->checkFilesystem();
        if ($health['checks']['filesystem']['status'] === 'error') {
            $health['status'] = 'degraded';
        }
        
        // Check API response time
        $health['checks']['api_performance'] = $this->checkApiPerformance();
        
        // Check memory usage
        $health['checks']['memory'] = $this->checkMemoryUsage();
        
        // Check disk space
        $health['checks']['disk_space'] = $this->checkDiskSpace();
        
        // Add application info
        $health['application'] = [
            'name' => $_ENV['APP_NAME'] ?? 'Sassy CRM',
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'debug_mode' => $_ENV['APP_DEBUG'] ?? false,
            'timezone' => $_ENV['TZ'] ?? 'UTC',
            'php_version' => PHP_VERSION
        ];
        
        $statusCode = $health['status'] === 'healthy' ? 200 : 503;
        
        return $this->json($response, $health, $statusCode);
    }
    
    /**
     * Check database connection
     */
    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            
            // Check if key tables exist
            $tables = ['users', 'leads', 'contacts', 'accounts', 'opportunities'];
            $missingTables = [];
            
            foreach ($tables as $table) {
                $tableExists = DB::select("SHOW TABLES LIKE ?", [$table]);
                if (empty($tableExists)) {
                    $missingTables[] = $table;
                }
            }
            
            if (!empty($missingTables)) {
                return [
                    'status' => 'error',
                    'message' => 'Missing tables: ' . implode(', ', $missingTables)
                ];
            }
            
            return [
                'status' => 'ok',
                'message' => 'Database connection successful',
                'driver' => DB::connection()->getConfig('driver')
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check filesystem
     */
    private function checkFilesystem(): array
    {
        try {
            $directories = [
                'storage/app' => $this->storagePath('app'),
                'storage/logs' => $this->storagePath('logs'),
                'storage/framework/cache' => $this->storagePath('framework/cache')
            ];
            
            $issues = [];
            
            foreach ($directories as $name => $path) {
                if (!is_dir($path)) {
                    $issues[] = "$name does not exist";
                } elseif (!is_writable($path)) {
                    $issues[] = "$name is not writable";
                }
            }
            
            if (empty($issues)) {
                return [
                    'status' => 'ok',
                    'message' => 'All directories are accessible'
                ];
            }
            
            return [
                'status' => 'warning',
                'message' => 'Filesystem issues: ' . implode(', ', $issues)
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Filesystem check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check API performance
     */
    private function checkApiPerformance(): array
    {
        $startTime = microtime(true);
        
        try {
            // Simple query to test performance
            DB::table('users')->count();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($responseTime < 100) {
                $status = 'ok';
                $message = 'API response time is good';
            } elseif ($responseTime < 500) {
                $status = 'warning';
                $message = 'API response time is acceptable';
            } else {
                $status = 'error';
                $message = 'API response time is slow';
            }
            
            return [
                'status' => $status,
                'message' => $message,
                'response_time_ms' => $responseTime
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Performance check failed'
            ];
        }
    }
    
    /**
     * Check memory usage
     */
    private function checkMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        // Convert memory limit to bytes
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        
        if ($memoryLimitBytes > 0) {
            $usagePercent = round(($memoryUsage / $memoryLimitBytes) * 100, 2);
            
            if ($usagePercent < 80) {
                $status = 'ok';
            } elseif ($usagePercent < 90) {
                $status = 'warning';
            } else {
                $status = 'error';
            }
        } else {
            $status = 'ok';
            $usagePercent = 0;
        }
        
        return [
            'status' => $status,
            'message' => "Memory usage: {$usagePercent}%",
            'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'limit' => $memoryLimit
        ];
    }
    
    /**
     * Check disk space
     */
    private function checkDiskSpace(): array
    {
        try {
            $path = $this->storagePath();
            $freeSpace = disk_free_space($path);
            $totalSpace = disk_total_space($path);
            
            if ($totalSpace > 0) {
                $usagePercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
                $freeGB = round($freeSpace / 1024 / 1024 / 1024, 2);
                
                if ($freeGB > 1 && $usagePercent < 90) {
                    $status = 'ok';
                } elseif ($freeGB > 0.5 && $usagePercent < 95) {
                    $status = 'warning';
                } else {
                    $status = 'error';
                }
                
                return [
                    'status' => $status,
                    'message' => "Disk usage: {$usagePercent}%, Free: {$freeGB}GB",
                    'free_gb' => $freeGB,
                    'usage_percent' => $usagePercent
                ];
            }
            
            return [
                'status' => 'warning',
                'message' => 'Unable to determine disk space'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Disk space check failed'
            ];
        }
    }
    
    /**
     * Convert memory string to bytes
     */
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int)$value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Get storage path
     */
    private function storagePath(string $path = ''): string
    {
        $basePath = dirname(dirname(dirname(__DIR__))) . '/storage';
        return $path ? $basePath . '/' . $path : $basePath;
    }
}