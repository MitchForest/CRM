<?php
/**
 * Customer Health Controller
 * Manages customer health scoring endpoints
 */

namespace Api\Controllers;

use Api\Response;
use SuiteCRM\Custom\Services\CustomerHealthService;

class CustomerHealthController extends BaseController
{
    private $healthService;
    
    public function __construct()
    {
        // parent::__construct(); // BaseController has no constructor
        $this->healthService = new CustomerHealthService();
    }
    
    /**
     * Calculate health score for a single account
     * POST /api/v8/accounts/{id}/health-score
     */
    public function calculateHealthScore($request, $args)
    {
        $this->requireAuth();
        
        $accountId = $args['id'] ?? null;
        
        if (!$accountId) {
            return new Response(['error' => 'Account ID is required'], 400);
        }
        
        try {
            $result = $this->healthService->calculateHealthScore($accountId);
            return new Response($result);
        } catch (\Exception $e) {
            error_log('Health score calculation error: ' . $e->getMessage());
            return new Response(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Batch calculate health scores
     * POST /api/v8/accounts/health-score-batch
     */
    public function batchCalculateHealthScores($request)
    {
        $this->requireAuth();
        
        $data = $request->getParsedBody();
        $accountIds = $data['account_ids'] ?? [];
        
        if (empty($accountIds)) {
            return new Response(['error' => 'Account IDs are required'], 400);
        }
        
        try {
            $results = $this->healthService->batchCalculateHealthScores($accountIds);
            return new Response($results);
        } catch (\Exception $e) {
            error_log('Batch health score calculation error: ' . $e->getMessage());
            return new Response(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get health score history for an account
     * GET /api/v8/accounts/{id}/health-history
     */
    public function getHealthHistory($request, $args)
    {
        $this->requireAuth();
        
        $accountId = $args['id'] ?? null;
        $limit = $request->getQueryParam('limit', 30);
        
        if (!$accountId) {
            return new Response(['error' => 'Account ID is required'], 400);
        }
        
        try {
            $history = $this->healthService->getHealthScoreHistory($accountId, $limit);
            return new Response([
                'account_id' => $accountId,
                'history' => $history,
            ]);
        } catch (\Exception $e) {
            error_log('Health history retrieval error: ' . $e->getMessage());
            return new Response(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get accounts by risk level
     * GET /api/v8/accounts/at-risk
     */
    public function getAtRiskAccounts($request)
    {
        $this->requireAuth();
        
        $riskLevel = $request->getQueryParam('risk_level', 'at_risk');
        $limit = $request->getQueryParam('limit', 50);
        
        if (!in_array($riskLevel, ['healthy', 'at_risk', 'critical'])) {
            return new Response(['error' => 'Invalid risk level'], 400);
        }
        
        try {
            $accounts = $this->healthService->getAccountsByRiskLevel($riskLevel, $limit);
            
            return new Response([
                'risk_level' => $riskLevel,
                'count' => count($accounts),
                'accounts' => $accounts,
            ]);
        } catch (\Exception $e) {
            error_log('At-risk accounts retrieval error: ' . $e->getMessage());
            return new Response(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get health analytics dashboard data
     * GET /api/v8/analytics/health-dashboard
     */
    public function getHealthDashboard($request)
    {
        $this->requireAuth();
        
        try {
            // Get overall statistics
            $healthy = $this->healthService->getAccountsByRiskLevel('healthy', 1000);
            $atRisk = $this->healthService->getAccountsByRiskLevel('at_risk', 1000);
            $critical = $this->healthService->getAccountsByRiskLevel('critical', 1000);
            
            $totalAccounts = count($healthy) + count($atRisk) + count($critical);
            
            // Calculate average scores and trends
            $avgHealthyScore = $this->calculateAverageScore($healthy);
            $avgAtRiskScore = $this->calculateAverageScore($atRisk);
            $avgCriticalScore = $this->calculateAverageScore($critical);
            
            // Get recent alerts (accounts with declining health)
            $recentAlerts = $this->getRecentHealthAlerts();
            
            return new Response([
                'summary' => [
                    'total_accounts' => $totalAccounts,
                    'healthy' => count($healthy),
                    'at_risk' => count($atRisk),
                    'critical' => count($critical),
                    'healthy_percentage' => $totalAccounts > 0 ? round((count($healthy) / $totalAccounts) * 100, 1) : 0,
                ],
                'average_scores' => [
                    'healthy' => $avgHealthyScore,
                    'at_risk' => $avgAtRiskScore,
                    'critical' => $avgCriticalScore,
                ],
                'recent_alerts' => $recentAlerts,
                'top_at_risk' => array_slice($atRisk, 0, 10),
                'critical_accounts' => array_slice($critical, 0, 5),
            ]);
            
        } catch (\Exception $e) {
            error_log('Health dashboard error: ' . $e->getMessage());
            return new Response(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Trigger recalculation of all health scores
     * POST /api/v8/admin/recalculate-health-scores
     */
    public function recalculateAllScores($request)
    {
        $this->requireAuth();
        
        // Check admin permissions
        global $current_user;
        if (!$current_user->isAdmin()) {
            return new Response(['error' => 'Admin access required'], 403);
        }
        
        try {
            // This should ideally be run as a background job
            $result = $this->healthService->calculateAllHealthScores();
            
            return new Response([
                'message' => 'Health scores recalculation completed',
                'processed' => $result['processed'],
                'errors' => $result['errors'],
                'completed_at' => $result['completed_at'],
            ]);
            
        } catch (\Exception $e) {
            error_log('Health score recalculation error: ' . $e->getMessage());
            return new Response(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Webhook endpoint for periodic health checks
     * POST /api/v8/webhooks/health-check
     * 
     * Can be called by external services (Zapier, IFTTT, CloudWatch, etc.)
     * or a simple curl command from any scheduler
     */
    public function webhookHealthCheck($request)
    {
        // Simple auth token for webhook
        $token = $request->getHeaderLine('X-Webhook-Token');
        $expectedToken = getenv('HEALTH_CHECK_WEBHOOK_TOKEN') ?: 'your-secret-token-here';
        
        if ($token !== $expectedToken) {
            return new Response(['error' => 'Invalid webhook token'], 401);
        }
        
        try {
            // Check for inactive accounts
            require_once(__DIR__ . '/../../hooks/health_score_hooks.php');
            $hooks = new \HealthScoreHooks();
            $hooks->checkInactiveAccounts();
            
            // Calculate scores for at-risk accounts only
            $atRiskAccounts = $this->healthService->getAccountsByRiskLevel('at_risk', 20);
            $processed = 0;
            
            foreach ($atRiskAccounts as $account) {
                try {
                    $this->healthService->calculateHealthScore($account['account_id']);
                    $processed++;
                } catch (\Exception $e) {
                    error_log("Failed to calculate health score for account {$account['account_id']}: " . $e->getMessage());
                }
            }
            
            return new Response([
                'message' => 'Health check completed',
                'inactive_accounts_checked' => true,
                'at_risk_accounts_processed' => $processed,
                'timestamp' => gmdate('Y-m-d H:i:s'),
            ]);
            
        } catch (\Exception $e) {
            error_log('Webhook health check error: ' . $e->getMessage());
            return new Response(['error' => $e->getMessage()], 500);
        }
    }
    
    // Helper methods
    
    private function calculateAverageScore($accounts)
    {
        if (empty($accounts)) {
            return 0;
        }
        
        $total = array_sum(array_column($accounts, 'score'));
        return round($total / count($accounts), 1);
    }
    
    private function getRecentHealthAlerts($limit = 10)
    {
        global $db;
        
        // Get accounts with significant score drops in the last 7 days
        $query = "SELECT 
                    chs.*,
                    a.name as account_name,
                    u.user_name as assigned_to
                 FROM customer_health_scores chs
                 JOIN accounts a ON chs.account_id = a.id
                 LEFT JOIN users u ON a.assigned_user_id = u.id
                 WHERE chs.score_change < -20
                 AND chs.date_calculated >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY chs.score_change ASC
                 LIMIT ?";
        
        $stmt = $db->getConnection()->prepare($query);
        $stmt->execute([$limit]);
        
        $alerts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $row['factors'] = json_decode($row['factors'], true);
            $row['recommendations'] = json_decode($row['recommendations'], true);
            $alerts[] = $row;
        }
        
        return $alerts;
    }
}