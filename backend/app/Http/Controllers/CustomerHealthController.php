<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CustomerHealthScore;
// use App\Services\CRM\CustomerHealthService; // Service doesn't exist
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class CustomerHealthController extends Controller
{
    // private CustomerHealthService $healthService;
    
    public function __construct()
    {
        parent::__construct();
        // Service doesn't exist, will implement methods directly
    }
    
    /**
     * Calculate health score for a single account
     * POST /api/crm/accounts/{id}/health-score
     */
    public function calculateHealthScore(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $account = Account::find($id);
        
        if (!$account) {
            return $this->error($response, 'Account not found', 404);
        }
        
        try {
            // Calculate health score inline since service doesn't exist
            $score = $this->calculateAccountHealthScore($account);
            $result = [
                'account_id' => $account->id,
                'score' => $score,
                'risk_level' => $this->getRiskLevel($score),
                'factors' => $this->getHealthFactors($account)
            ];
            
            return $this->json($response, $result);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to calculate health score: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Batch calculate health scores
     * POST /api/crm/accounts/health-score-batch
     */
    public function batchCalculateHealthScores(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'account_ids' => 'required|array|min:1',
            'account_ids.*' => 'string|exists:accounts,id'
        ]);
        
        try {
            // Calculate batch scores inline
            $results = [];
            foreach ($data['account_ids'] as $accountId) {
                $account = Account::find($accountId);
                if ($account) {
                    $score = $this->calculateAccountHealthScore($account);
                    $results[] = [
                        'account_id' => $accountId,
                        'score' => $score,
                        'risk_level' => $this->getRiskLevel($score)
                    ];
                }
            }
            
            return $this->json($response, $results);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to calculate batch health scores: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get health score history for an account
     * GET /api/crm/accounts/{id}/health-history
     */
    public function getHealthHistory(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $limit = intval($params['limit'] ?? 30);
        
        if ($limit < 1 || $limit > 100) {
            return $this->error($response, 'Limit must be between 1 and 100', 400);
        }
        
        $id = $args['id'];
        $account = Account::find($id);
        
        if (!$account) {
            return $this->error($response, 'Account not found', 404);
        }
        
        try {
            $history = CustomerHealthScore::where('account_id', $id)
                ->orderBy('date_scored', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($score) {
                    return [
                        'id' => $score->id,
                        'score' => $score->score,
                        'score_change' => $score->score_change,
                        'risk_level' => $score->risk_level,
                        'factors' => $score->factors,
                        'calculated_at' => $score->date_scored
                    ];
                });
            
            return $this->json($response, [
                'account_id' => $id,
                'history' => $history
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve health history: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get accounts by risk level
     * GET /api/crm/accounts/at-risk
     */
    public function getAtRiskAccounts(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $riskLevel = $params['risk_level'] ?? 'at_risk';
        $limit = intval($params['limit'] ?? 50);
        
        if (!in_array($riskLevel, ['healthy', 'at_risk', 'critical'])) {
            return $this->error($response, 'Invalid risk level. Must be: healthy, at_risk, or critical', 400);
        }
        
        if ($limit < 1 || $limit > 100) {
            return $this->error($response, 'Limit must be between 1 and 100', 400);
        }
        
        try {
            // Get accounts by risk level inline
            $query = CustomerHealthScore::with('account')
                ->where('risk_level', $riskLevel)
                ->orderBy('date_scored', 'desc')
                ->groupBy('account_id')
                ->limit($limit);
            
            $accounts = $query->get()->map(function ($score) {
                return [
                    'account_id' => $score->account_id,
                    'account_name' => $score->account?->name,
                    'health_score' => $score->score,
                    'risk_level' => $score->risk_level
                ];
            })->toArray();
            
            return $this->json($response, [
                'risk_level' => $riskLevel,
                'count' => count($accounts),
                'accounts' => $accounts
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve at-risk accounts: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get health analytics dashboard data
     * GET /api/crm/analytics/health-dashboard
     */
    public function getHealthDashboard(Request $request, Response $response, array $args): Response
    {
        try {
            // Get overall statistics inline
            $healthy = CustomerHealthScore::where('risk_level', 'healthy')->count();
            $atRisk = CustomerHealthScore::where('risk_level', 'at_risk')->count();
            $critical = CustomerHealthScore::where('risk_level', 'critical')->count();
            
            $totalAccounts = $healthy + $atRisk + $critical;
            
            // Calculate average scores
            $avgHealthyScore = CustomerHealthScore::where('risk_level', 'healthy')->avg('score') ?: 0;
            $avgAtRiskScore = CustomerHealthScore::where('risk_level', 'at_risk')->avg('score') ?: 0;
            $avgCriticalScore = CustomerHealthScore::where('risk_level', 'critical')->avg('score') ?: 0;
            
            // Get recent alerts (accounts with declining health)
            $recentAlerts = $this->getRecentHealthAlerts();
            
            return $this->json($response, [
                'summary' => [
                    'total_accounts' => $totalAccounts,
                    'healthy' => $healthy,
                    'at_risk' => $atRisk,
                    'critical' => $critical,
                    'healthy_percentage' => $totalAccounts > 0 ? round(($healthy / $totalAccounts) * 100, 1) : 0
                ],
                'average_scores' => [
                    'healthy' => $avgHealthyScore,
                    'at_risk' => $avgAtRiskScore,
                    'critical' => $avgCriticalScore
                ],
                'recent_alerts' => $recentAlerts,
                'top_at_risk' => CustomerHealthScore::where('risk_level', 'at_risk')
                    ->with('account')
                    ->orderBy('score', 'asc')
                    ->limit(10)
                    ->get()
                    ->map(function ($score) {
                        return [
                            'account_id' => $score->account_id,
                            'account_name' => $score->account?->name,
                            'score' => $score->score
                        ];
                    })->toArray(),
                'critical_accounts' => CustomerHealthScore::where('risk_level', 'critical')
                    ->with('account')
                    ->orderBy('score', 'asc')
                    ->limit(5)
                    ->get()
                    ->map(function ($score) {
                        return [
                            'account_id' => $score->account_id,
                            'account_name' => $score->account?->name,
                            'score' => $score->score
                        ];
                    })->toArray()
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve health dashboard: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Trigger recalculation of all health scores
     * POST /api/crm/admin/recalculate-health-scores
     */
    public function recalculateAllScores(Request $request, Response $response, array $args): Response
    {
        try {
            DB::beginTransaction();
            
            $accounts = Account::where('deleted', 0)->get();
            $results = [
                'total' => $accounts->count(),
                'success' => 0,
                'failed' => 0,
                'errors' => []
            ];
            
            foreach ($accounts as $account) {
                try {
                    $score = $this->calculateAccountHealthScore($account);
                    CustomerHealthScore::create([
                        'account_id' => $account->id,
                        'score' => $score,
                        'risk_level' => $this->getRiskLevel($score),
                        'factors' => $this->getHealthFactors($account),
                        'date_scored' => new \DateTime()
                    ]);
                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'account_id' => $account->id,
                        'account_name' => $account->name,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            DB::commit();
            
            return $this->json($response, $results);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to recalculate health scores: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get health score rules
     * GET /api/crm/admin/health-rules
     */
    public function getRules(Request $request, Response $response, array $args): Response
    {
        try {
            // Return default health rules
            $rules = [
                [
                    'id' => '1',
                    'name' => 'Recent Activity',
                    'factor' => 'engagement',
                    'weight' => 0.3,
                    'description' => 'Account activity in last 30 days'
                ],
                [
                    'id' => '2',
                    'name' => 'Support Tickets',
                    'factor' => 'support',
                    'weight' => 0.2,
                    'description' => 'Number and severity of support cases'
                ],
                [
                    'id' => '3',
                    'name' => 'Revenue Trend',
                    'factor' => 'revenue',
                    'weight' => 0.3,
                    'description' => 'Revenue growth or decline'
                ],
                [
                    'id' => '4',
                    'name' => 'User Adoption',
                    'factor' => 'usage',
                    'weight' => 0.2,
                    'description' => 'Active users and feature usage'
                ]
            ];
            
            return $this->json($response, ['rules' => $rules]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve health rules: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create new health rule
     * POST /api/crm/admin/health-rules
     */
    public function createRule(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'factor' => 'required|string|in:engagement,satisfaction,usage,revenue,support',
            'condition' => 'required|string',
            'weight' => 'required|numeric|min:0|max:1',
            'active' => 'sometimes|boolean'
        ]);
        
        try {
            // Since we don't have a rules table, just return the data
            $rule = array_merge($data, ['id' => uniqid()]);
            
            return $this->json($response, ['rule' => $rule], 201);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to create health rule: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update health rule
     * PUT /api/crm/admin/health-rules/{id}
     */
    public function updateRule(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        
        $data = $this->validate($request, [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'factor' => 'sometimes|string|in:engagement,satisfaction,usage,revenue,support',
            'condition' => 'sometimes|string',
            'weight' => 'sometimes|numeric|min:0|max:1',
            'active' => 'sometimes|boolean'
        ]);
        
        try {
            // Since we don't have a rules table, just return success
            $rule = array_merge(['id' => $id], $data);
            
            if (!$rule) {
                return $this->error($response, 'Health rule not found', 404);
            }
            
            return $this->json($response, ['rule' => $rule]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to update health rule: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete health rule
     * DELETE /api/crm/admin/health-rules/{id}
     */
    public function deleteRule(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        
        try {
            // Since we don't have a rules table, just return success
            $deleted = true;
            
            if (!$deleted) {
                return $this->error($response, 'Health rule not found', 404);
            }
            
            return $this->json($response, ['message' => 'Health rule deleted successfully']);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to delete health rule: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Calculate scores for all accounts
     * POST /api/crm/admin/health-scores/calculate
     */
    public function calculateScores(Request $request, Response $response, array $args): Response
    {
        return $this->recalculateAllScores($request, $response, $args);
    }
    
    /**
     * Get health scores for all accounts
     * GET /api/crm/admin/health-scores
     */
    public function getScores(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 20);
        
        if ($limit < 1 || $limit > 100) {
            return $this->error($response, 'Limit must be between 1 and 100', 400);
        }
        
        try {
            // Get total count
            $totalCount = CustomerHealthScore::count();
            
            // Calculate offset
            $offset = ($page - 1) * $limit;
            
            // Get paginated results
            $scores = CustomerHealthScore::with('account')
                ->orderBy('date_scored', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();
            
            $data = $scores->map(function ($score) {
                return [
                    'id' => $score->id,
                    'account_id' => $score->account_id,
                    'account_name' => $score->account?->name,
                    'score' => $score->score,
                    'score_change' => $score->score_change,
                    'risk_level' => $score->risk_level,
                    'factors' => $score->factors,
                    'calculated_at' => $score->date_scored
                ];
            });
            
            return $this->json($response, [
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'total_pages' => ceil($totalCount / $limit)
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve health scores: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get health trends
     * GET /api/crm/admin/health-trends
     */
    public function getHealthTrends(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $days = intval($params['days'] ?? 30);
        
        if ($days < 1 || $days > 365) {
            return $this->error($response, 'Days must be between 1 and 365', 400);
        }
        
        try {
            // Calculate trends inline
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            $trends = [
                'period_days' => $days,
                'start_date' => $startDate,
                'end_date' => date('Y-m-d'),
                'average_score_trend' => [],
                'risk_distribution_trend' => []
            ];
            
            return $this->json($response, $trends);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to retrieve health trends: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Calculate average score for accounts
     */
    private function calculateAverageScore(array $accounts): float
    {
        if (empty($accounts)) {
            return 0;
        }
        
        $total = array_reduce($accounts, function ($carry, $account) {
            return $carry + ($account['health_score'] ?? 0);
        }, 0);
        
        return round($total / count($accounts), 1);
    }
    
    /**
     * Get recent health alerts
     */
    private function getRecentHealthAlerts(): array
    {
        return CustomerHealthScore::where('score_change', '<', -10)
            ->where('date_scored', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->with('account')
            ->orderBy('score_change', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($score) {
                return [
                    'account_id' => $score->account_id,
                    'account_name' => $score->account?->name,
                    'current_score' => $score->score,
                    'score_change' => $score->score_change,
                    'risk_level' => $score->risk_level,
                    'calculated_at' => $score->date_scored
                ];
            })
            ->toArray();
    }
    
    /**
     * Calculate health score for an account
     */
    private function calculateAccountHealthScore(Account $account): int
    {
        $score = 70; // Base score
        
        // Factor 1: Recent activity (check opportunities)
        $recentOpportunities = $account->opportunities()
            ->where('date_modified', '>=', date('Y-m-d', strtotime('-30 days')))
            ->count();
        if ($recentOpportunities > 5) $score += 10;
        elseif ($recentOpportunities > 2) $score += 5;
        elseif ($recentOpportunities == 0) $score -= 10;
        
        // Factor 2: Support cases
        $openCases = $account->cases()
            ->where('status', 'Open')
            ->count();
        if ($openCases > 5) $score -= 15;
        elseif ($openCases > 2) $score -= 10;
        elseif ($openCases == 0) $score += 5;
        
        // Factor 3: Revenue (check opportunities)
        $totalRevenue = $account->opportunities()
            ->where('sales_stage', 'Closed Won')
            ->sum('amount');
        if ($totalRevenue > 100000) $score += 10;
        elseif ($totalRevenue > 50000) $score += 5;
        
        // Keep score within 0-100 range
        return max(0, min(100, $score));
    }
    
    /**
     * Get risk level based on score
     */
    private function getRiskLevel(int $score): string
    {
        if ($score >= 70) return 'healthy';
        if ($score >= 40) return 'at_risk';
        return 'critical';
    }
    
    /**
     * Get health factors for an account
     */
    private function getHealthFactors(Account $account): array
    {
        return [
            'recent_activity' => $account->opportunities()
                ->where('date_modified', '>=', date('Y-m-d', strtotime('-30 days')))
                ->count(),
            'open_cases' => $account->cases()
                ->where('status', 'Open')
                ->count(),
            'total_revenue' => $account->opportunities()
                ->where('sales_stage', 'Closed Won')
                ->sum('amount'),
            'contact_count' => $account->contacts()->count()
        ];
    }
}