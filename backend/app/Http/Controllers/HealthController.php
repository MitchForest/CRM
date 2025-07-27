<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * System health check endpoint
     * GET /api/health
     */
    public function check(Request $request): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => []
        ];
        
        // Check database connection
        $health['checks']['database'] = $this->checkDatabase();
        if ($health['checks']['database']['status'] === 'error') {
            $health['status'] = 'unhealthy';
        }
        
        // Check cache connection
        $health['checks']['cache'] = $this->checkCache();
        if ($health['checks']['cache']['status'] === 'error') {
            $health['status'] = 'degraded';
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
            'name' => config('app.name', 'Sassy CRM'),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ];
        
        $statusCode = $health['status'] === 'healthy' ? 200 : 503;
        
        return response()->json($health, $statusCode);
    }
    
    /**
     * Detailed system status
     * GET /api/health/status
     */
    public function status(Request $request): JsonResponse
    {
        // Check admin permissions
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['error' => 'Admin access required'], 403);
        }
        
        $status = [
            'timestamp' => now()->toIso8601String(),
            'database' => $this->getDatabaseStatus(),
            'cache' => $this->getCacheStatus(),
            'queue' => $this->getQueueStatus(),
            'storage' => $this->getStorageStatus(),
            'services' => $this->getServicesStatus()
        ];
        
        return response()->json($status);
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
                if (!DB::getSchemaBuilder()->hasTable($table)) {
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
                'driver' => DB::getDriverName()
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database check failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check cache connection
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 60);
            $value = Cache::get($key);
            Cache::forget($key);
            
            if ($value === 'test') {
                return [
                    'status' => 'ok',
                    'message' => 'Cache is working',
                    'driver' => config('cache.default')
                ];
            }
            
            return [
                'status' => 'warning',
                'message' => 'Cache test failed'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache check failed: ' . $e->getMessage()
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
                'storage/app' => storage_path('app'),
                'storage/logs' => storage_path('logs'),
                'storage/framework/cache' => storage_path('framework/cache')
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
            $path = storage_path();
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
     * Get detailed database status
     */
    private function getDatabaseStatus(): array
    {
        try {
            $pdo = DB::connection()->getPdo();
            
            return [
                'connected' => true,
                'driver' => DB::getDriverName(),
                'version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'database' => DB::getDatabaseName(),
                'tables_count' => count(DB::getSchemaBuilder()->getAllTables())
            ];
            
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get cache status
     */
    private function getCacheStatus(): array
    {
        return [
            'driver' => config('cache.default'),
            'stores' => array_keys(config('cache.stores', [])),
            'prefix' => config('cache.prefix')
        ];
    }
    
    /**
     * Get queue status
     */
    private function getQueueStatus(): array
    {
        return [
            'driver' => config('queue.default'),
            'connections' => array_keys(config('queue.connections', []))
        ];
    }
    
    /**
     * Get storage status
     */
    private function getStorageStatus(): array
    {
        return [
            'default' => config('filesystems.default'),
            'disks' => array_keys(config('filesystems.disks', []))
        ];
    }
    
    /**
     * Get services status
     */
    private function getServicesStatus(): array
    {
        $services = [];
        
        // Check if OpenAI service is configured
        if (config('services.openai.key')) {
            $services['openai'] = [
                'configured' => true,
                'model' => config('services.openai.model', 'gpt-3.5-turbo')
            ];
        } else {
            $services['openai'] = ['configured' => false];
        }
        
        // Check if email service is configured
        $services['email'] = [
            'driver' => config('mail.default'),
            'from_address' => config('mail.from.address')
        ];
        
        return $services;
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
}