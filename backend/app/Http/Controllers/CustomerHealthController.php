<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CustomerHealthScore;
use App\Services\CRM\CustomerHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerHealthController extends Controller
{
    private CustomerHealthService $healthService;
    
    public function __construct(CustomerHealthService $healthService)
    {
        $this->healthService = $healthService;
    }
    
    /**
     * Calculate health score for a single account
     * POST /api/crm/accounts/{id}/health-score
     */
    public function calculateHealthScore(Request $request, string $id): JsonResponse
    {
        $account = Account::find($id);
        
        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }
        
        try {
            $result = $this->healthService->calculateHealthScore($account);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to calculate health score',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Batch calculate health scores
     * POST /api/crm/accounts/health-score-batch
     */
    public function batchCalculateHealthScores(Request $request): JsonResponse
    {
        $request->validate([
            'account_ids' => 'required|array|min:1',
            'account_ids.*' => 'string|exists:accounts,id'
        ]);
        
        try {
            $results = $this->healthService->batchCalculateHealthScores($request->input('account_ids'));
            
            return response()->json($results);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to calculate batch health scores',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get health score history for an account
     * GET /api/crm/accounts/{id}/health-history
     */
    public function getHealthHistory(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);
        
        $account = Account::find($id);
        
        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }
        
        try {
            $limit = $request->input('limit', 30);
            
            $history = CustomerHealthScore::where('account_id', $id)
                ->orderBy('calculated_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($score) {
                    return [
                        'id' => $score->id,
                        'score' => $score->score,
                        'score_change' => $score->score_change,
                        'risk_level' => $score->risk_level,
                        'factors' => $score->factors,
                        'calculated_at' => $score->calculated_at
                    ];
                });
            
            return response()->json([
                'account_id' => $id,
                'history' => $history
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve health history',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get accounts by risk level
     * GET /api/crm/accounts/at-risk
     */
    public function getAtRiskAccounts(Request $request): JsonResponse
    {
        $request->validate([
            'risk_level' => 'sometimes|string|in:healthy,at_risk,critical',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);
        
        $riskLevel = $request->input('risk_level', 'at_risk');
        $limit = $request->input('limit', 50);
        
        try {
            $accounts = $this->healthService->getAccountsByRiskLevel($riskLevel, $limit);
            
            return response()->json([
                'risk_level' => $riskLevel,
                'count' => count($accounts),
                'accounts' => $accounts
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve at-risk accounts',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get health analytics dashboard data
     * GET /api/crm/analytics/health-dashboard
     */
    public function getHealthDashboard(Request $request): JsonResponse
    {
        try {
            // Get overall statistics
            $healthy = $this->healthService->getAccountsByRiskLevel('healthy', 1000);
            $atRisk = $this->healthService->getAccountsByRiskLevel('at_risk', 1000);
            $critical = $this->healthService->getAccountsByRiskLevel('critical', 1000);
            
            $totalAccounts = count($healthy) + count($atRisk) + count($critical);
            
            // Calculate average scores
            $avgHealthyScore = $this->calculateAverageScore($healthy);
            $avgAtRiskScore = $this->calculateAverageScore($atRisk);
            $avgCriticalScore = $this->calculateAverageScore($critical);
            
            // Get recent alerts (accounts with declining health)
            $recentAlerts = $this->getRecentHealthAlerts();
            
            return response()->json([
                'summary' => [
                    'total_accounts' => $totalAccounts,
                    'healthy' => count($healthy),
                    'at_risk' => count($atRisk),
                    'critical' => count($critical),
                    'healthy_percentage' => $totalAccounts > 0 ? round((count($healthy) / $totalAccounts) * 100, 1) : 0
                ],
                'average_scores' => [
                    'healthy' => $avgHealthyScore,
                    'at_risk' => $avgAtRiskScore,
                    'critical' => $avgCriticalScore
                ],
                'recent_alerts' => $recentAlerts,
                'top_at_risk' => array_slice($atRisk, 0, 10),
                'critical_accounts' => array_slice($critical, 0, 5)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve health dashboard',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Trigger recalculation of all health scores
     * POST /api/crm/admin/recalculate-health-scores
     */
    public function recalculateAllScores(Request $request): JsonResponse
    {
        // Check admin permissions
        if (!$request->user()->isAdmin()) {
            return response()->json(['error' => 'Admin access required'], 403);
        }
        
        try {
            // This should ideally be run as a background job
            $result = $this->healthService->calculateAllHealthScores();
            
            return response()->json([
                'message' => 'Health scores recalculation completed',
                'processed' => $result['processed'],
                'errors' => $result['errors'],
                'completed_at' => $result['completed_at']
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to recalculate health scores',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Webhook endpoint for periodic health checks
     * POST /api/webhooks/health-check
     */
    public function webhookHealthCheck(Request $request): JsonResponse
    {
        // Simple auth token for webhook
        $token = $request->header('X-Webhook-Token');
        $expectedToken = env('HEALTH_CHECK_WEBHOOK_TOKEN', 'your-secret-token-here');
        
        if ($token !== $expectedToken) {
            return response()->json(['error' => 'Invalid webhook token'], 401);
        }
        
        try {
            // Check for inactive accounts
            $this->healthService->checkInactiveAccounts();
            
            // Calculate scores for at-risk accounts only
            $atRiskAccounts = $this->healthService->getAccountsByRiskLevel('at_risk', 20);
            $processed = 0;
            
            foreach ($atRiskAccounts as $account) {
                try {
                    $this->healthService->calculateHealthScore(
                        Account::find($account['account_id'])
                    );
                    $processed++;
                } catch (\Exception $e) {
                    \Log::error("Failed to calculate health score for account {$account['account_id']}: " . $e->getMessage());
                }
            }
            
            return response()->json([
                'message' => 'Health check completed',
                'inactive_accounts_checked' => true,
                'at_risk_accounts_processed' => $processed,
                'timestamp' => now()->toIso8601String()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Webhook health check failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get health score recommendations
     * GET /api/crm/accounts/{id}/health-recommendations
     */
    public function getHealthRecommendations(Request $request, string $id): JsonResponse
    {
        $account = Account::find($id);
        
        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }
        
        try {
            $latestScore = CustomerHealthScore::where('account_id', $id)
                ->orderBy('calculated_at', 'desc')
                ->first();
            
            if (!$latestScore) {
                // Calculate if no score exists
                $result = $this->healthService->calculateHealthScore($account);
                $latestScore = CustomerHealthScore::where('account_id', $id)
                    ->orderBy('calculated_at', 'desc')
                    ->first();
            }
            
            return response()->json([
                'account_id' => $id,
                'current_score' => $latestScore->score,
                'risk_level' => $latestScore->risk_level,
                'recommendations' => $latestScore->recommendations,
                'factors' => $latestScore->factors
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get health recommendations',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Calculate average score from accounts array
     */
    private function calculateAverageScore(array $accounts): float
    {
        if (empty($accounts)) {
            return 0;
        }
        
        $total = array_sum(array_column($accounts, 'score'));
        return round($total / count($accounts), 1);
    }
    
    /**
     * Get recent health alerts
     */
    private function getRecentHealthAlerts(int $limit = 10): array
    {
        return CustomerHealthScore::with(['account.assignedUser'])
            ->where('score_change', '<', -20)
            ->where('calculated_at', '>=', now()->subDays(7))
            ->orderBy('score_change', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($score) {
                return [
                    'id' => $score->id,
                    'account_id' => $score->account_id,
                    'account_name' => $score->account->name,
                    'assigned_to' => $score->account->assignedUser->full_name ?? null,
                    'score' => $score->score,
                    'score_change' => $score->score_change,
                    'risk_level' => $score->risk_level,
                    'factors' => $score->factors,
                    'recommendations' => $score->recommendations,
                    'calculated_at' => $score->calculated_at
                ];
            })
            ->toArray();
    }
}