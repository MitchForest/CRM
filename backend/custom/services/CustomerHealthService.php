<?php
/**
 * Customer Health Scoring Service
 * Calculates and manages customer health scores based on various factors
 */

namespace SuiteCRM\Custom\Services;

use Exception;
use BeanFactory;
use DBManagerFactory;

class CustomerHealthService
{
    private $db;
    private $aiService;
    
    // Weight factors for health score calculation
    private $weights = [
        'support_tickets' => 0.25,      // Recent support issues
        'activity_level' => 0.20,       // Engagement and usage
        'contract_value' => 0.15,       // MRR/ARR
        'payment_history' => 0.15,      // Payment reliability
        'feature_adoption' => 0.15,     // Product feature usage
        'relationship_length' => 0.10,  // Customer tenure
    ];
    
    // Risk thresholds
    private $riskThresholds = [
        'healthy' => 80,
        'at_risk' => 60,
        'critical' => 40,
    ];
    
    public function __construct()
    {
        $this->db = DBManagerFactory::getInstance();
        
        // Initialize AI service for advanced analysis
        try {
            $this->aiService = new OpenAIService();
        } catch (Exception $e) {
            error_log('AI Service not available for health scoring: ' . $e->getMessage());
            $this->aiService = null;
        }
    }
    
    /**
     * Calculate health score for a single account
     * 
     * @param string $accountId
     * @return array Health score data
     */
    public function calculateHealthScore($accountId)
    {
        $account = BeanFactory::getBean('Accounts', $accountId);
        
        if (!$account || $account->deleted) {
            throw new Exception('Account not found');
        }
        
        // Gather all relevant data
        $healthData = $this->gatherHealthData($account);
        
        // Calculate individual factor scores
        $factors = [
            'support_tickets' => $this->calculateSupportScore($healthData),
            'activity_level' => $this->calculateActivityScore($healthData),
            'contract_value' => $this->calculateContractScore($healthData),
            'payment_history' => $this->calculatePaymentScore($healthData),
            'feature_adoption' => $this->calculateAdoptionScore($healthData),
            'relationship_length' => $this->calculateTenureScore($healthData),
        ];
        
        // Calculate weighted overall score
        $overallScore = 0;
        foreach ($factors as $factor => $score) {
            $overallScore += $score * $this->weights[$factor];
        }
        $overallScore = round($overallScore);
        
        // Determine risk level
        $riskLevel = $this->determineRiskLevel($overallScore);
        
        // Get AI recommendations if available
        $recommendations = [];
        $churnProbability = null;
        
        if ($this->aiService) {
            try {
                $aiAnalysis = $this->aiService->analyzeCustomerHealth($healthData);
                $recommendations = $aiAnalysis['recommendations'] ?? [];
                $churnProbability = $aiAnalysis['churn_probability'] ?? null;
            } catch (Exception $e) {
                error_log('AI health analysis failed: ' . $e->getMessage());
            }
        } else {
            // Fallback recommendations based on low scores
            $recommendations = $this->generateRecommendations($factors, $overallScore);
            $churnProbability = $this->estimateChurnProbability($overallScore, $factors);
        }
        
        // Save to database
        $this->saveHealthScore($accountId, [
            'score' => $overallScore,
            'factors' => $factors,
            'risk_level' => $riskLevel,
            'churn_probability' => $churnProbability,
            'recommendations' => $recommendations,
        ]);
        
        // Update account record
        $account->health_score_c = $overallScore;
        $account->health_score_date_c = gmdate('Y-m-d H:i:s');
        $account->save();
        
        return [
            'account_id' => $accountId,
            'account_name' => $account->name,
            'score' => $overallScore,
            'previous_score' => $healthData['previous_score'],
            'score_change' => $overallScore - ($healthData['previous_score'] ?? $overallScore),
            'factors' => $factors,
            'risk_level' => $riskLevel,
            'churn_probability' => $churnProbability,
            'recommendations' => $recommendations,
            'calculated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Batch calculate health scores for multiple accounts
     * 
     * @param array $accountIds
     * @return array Results for each account
     */
    public function batchCalculateHealthScores(array $accountIds)
    {
        $results = [];
        
        foreach ($accountIds as $accountId) {
            try {
                $results[$accountId] = $this->calculateHealthScore($accountId);
            } catch (Exception $e) {
                $results[$accountId] = [
                    'error' => $e->getMessage(),
                    'account_id' => $accountId,
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Calculate health scores for all active accounts
     * Used by cron job
     */
    public function calculateAllHealthScores()
    {
        $query = "SELECT id FROM accounts 
                 WHERE deleted = 0 
                 AND account_type != 'Prospect'
                 ORDER BY annual_revenue DESC";
        
        $result = $this->db->query($query);
        $processed = 0;
        $errors = 0;
        
        while ($row = $this->db->fetchByAssoc($result)) {
            try {
                $this->calculateHealthScore($row['id']);
                $processed++;
                
                // Add small delay to avoid overloading
                if ($processed % 10 === 0) {
                    usleep(100000); // 0.1 second
                }
            } catch (Exception $e) {
                error_log("Health score calculation failed for account {$row['id']}: " . $e->getMessage());
                $errors++;
            }
        }
        
        return [
            'processed' => $processed,
            'errors' => $errors,
            'completed_at' => gmdate('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Get health score history for an account
     * 
     * @param string $accountId
     * @param int $limit
     * @return array Score history
     */
    public function getHealthScoreHistory($accountId, $limit = 30)
    {
        $query = "SELECT * FROM customer_health_scores 
                 WHERE account_id = ? 
                 ORDER BY date_calculated DESC 
                 LIMIT ?";
        
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute([$accountId, $limit]);
        
        $history = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $row['factors'] = json_decode($row['factors'], true);
            $row['recommendations'] = json_decode($row['recommendations'], true);
            $history[] = $row;
        }
        
        return $history;
    }
    
    /**
     * Get accounts by risk level
     * 
     * @param string $riskLevel
     * @param int $limit
     * @return array Accounts at risk
     */
    public function getAccountsByRiskLevel($riskLevel, $limit = 50)
    {
        $query = "SELECT 
                    chs.*, 
                    a.name as account_name,
                    a.assigned_user_id,
                    u.user_name as assigned_to
                 FROM customer_health_scores chs
                 JOIN accounts a ON chs.account_id = a.id
                 LEFT JOIN users u ON a.assigned_user_id = u.id
                 WHERE chs.risk_level = ?
                 AND chs.id IN (
                    SELECT MAX(id) FROM customer_health_scores 
                    GROUP BY account_id
                 )
                 ORDER BY chs.churn_probability DESC
                 LIMIT ?";
        
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute([$riskLevel, $limit]);
        
        $accounts = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $row['factors'] = json_decode($row['factors'], true);
            $row['recommendations'] = json_decode($row['recommendations'], true);
            $accounts[] = $row;
        }
        
        return $accounts;
    }
    
    // Private helper methods
    
    private function gatherHealthData($account)
    {
        $accountId = $account->id;
        $data = [
            'account' => $account,
            'previous_score' => $account->health_score_c,
        ];
        
        // Get support ticket data
        $data['support_tickets'] = $this->getSupportTicketData($accountId);
        
        // Get activity data
        $data['activities'] = $this->getActivityData($accountId);
        
        // Get contract/financial data
        $data['financials'] = $this->getFinancialData($account);
        
        // Get usage/adoption data
        $data['usage'] = $this->getUsageData($accountId);
        
        // Get relationship data
        $data['relationship'] = $this->getRelationshipData($account);
        
        return $data;
    }
    
    private function getSupportTicketData($accountId)
    {
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $query = "SELECT 
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN status IN ('Open', 'In Progress') THEN 1 ELSE 0 END) as open_tickets,
                    SUM(CASE WHEN priority = 'High' THEN 1 ELSE 0 END) as high_priority,
                    AVG(TIMESTAMPDIFF(HOUR, date_entered, date_modified)) as avg_resolution_hours
                 FROM cases
                 WHERE account_id = ?
                 AND date_entered >= ?
                 AND deleted = 0";
        
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute([$accountId, $thirtyDaysAgo]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    private function getActivityData($accountId)
    {
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Get meeting and call data
        $query = "SELECT 
                    (SELECT COUNT(*) FROM meetings m
                     JOIN accounts_meetings am ON m.id = am.meeting_id
                     WHERE am.account_id = ? AND m.date_start >= ? AND m.deleted = 0) as meetings,
                    (SELECT COUNT(*) FROM calls c
                     JOIN accounts_calls ac ON c.id = ac.call_id
                     WHERE ac.account_id = ? AND c.date_start >= ? AND c.deleted = 0) as calls,
                    (SELECT MAX(date_modified) FROM notes n
                     JOIN accounts_notes an ON n.id = an.note_id
                     WHERE an.account_id = ? AND n.deleted = 0) as last_interaction";
        
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute([$accountId, $thirtyDaysAgo, $accountId, $thirtyDaysAgo, $accountId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    private function getFinancialData($account)
    {
        return [
            'annual_revenue' => $account->annual_revenue ?? 0,
            'contract_value' => $account->contract_value_c ?? 0,
            'payment_terms' => $account->payment_terms_c ?? 'net30',
            // In a real implementation, you'd query payment history
            'late_payments' => 0,
            'total_payments' => 12,
        ];
    }
    
    private function getUsageData($accountId)
    {
        // This would typically integrate with your product analytics
        // For now, we'll simulate based on activity
        $query = "SELECT COUNT(DISTINCT visitor_id) as unique_users,
                        COUNT(*) as total_sessions,
                        AVG(total_time) as avg_session_time
                 FROM website_sessions
                 WHERE account_id = ?
                 AND date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute([$accountId]);
        $usage = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Simulate feature adoption
        $usage['features_used'] = rand(5, 15);
        $usage['total_features'] = 20;
        
        return $usage;
    }
    
    private function getRelationshipData($account)
    {
        $createdDate = new \DateTime($account->date_entered);
        $now = new \DateTime();
        $monthsActive = $createdDate->diff($now)->m + ($createdDate->diff($now)->y * 12);
        
        return [
            'months_active' => $monthsActive,
            'account_type' => $account->account_type,
            'industry' => $account->industry,
        ];
    }
    
    private function calculateSupportScore($healthData)
    {
        $tickets = $healthData['support_tickets'];
        
        if ($tickets['total_tickets'] == 0) {
            return 100; // No tickets is good
        }
        
        $score = 100;
        
        // Deduct for open tickets
        $score -= min(30, $tickets['open_tickets'] * 10);
        
        // Deduct for high priority tickets
        $score -= min(20, $tickets['high_priority'] * 10);
        
        // Deduct for slow resolution
        if ($tickets['avg_resolution_hours'] > 48) {
            $score -= 10;
        }
        
        return max(0, $score);
    }
    
    private function calculateActivityScore($healthData)
    {
        $activities = $healthData['activities'];
        
        $score = 50; // Base score
        
        // Add points for meetings and calls
        $score += min(25, ($activities['meetings'] + $activities['calls']) * 5);
        
        // Add points for recent interaction
        if ($activities['last_interaction']) {
            $daysSinceInteraction = (time() - strtotime($activities['last_interaction'])) / 86400;
            if ($daysSinceInteraction < 7) {
                $score += 25;
            } elseif ($daysSinceInteraction < 30) {
                $score += 15;
            }
        }
        
        return min(100, $score);
    }
    
    private function calculateContractScore($healthData)
    {
        $financials = $healthData['financials'];
        
        // Base score from contract value
        $contractValue = $financials['contract_value'] ?: $financials['annual_revenue'] / 12;
        
        if ($contractValue >= 10000) {
            $score = 100;
        } elseif ($contractValue >= 5000) {
            $score = 80;
        } elseif ($contractValue >= 1000) {
            $score = 60;
        } else {
            $score = 40;
        }
        
        return $score;
    }
    
    private function calculatePaymentScore($healthData)
    {
        $financials = $healthData['financials'];
        
        if ($financials['total_payments'] == 0) {
            return 100; // New customer
        }
        
        $onTimeRate = ($financials['total_payments'] - $financials['late_payments']) / $financials['total_payments'];
        
        return round($onTimeRate * 100);
    }
    
    private function calculateAdoptionScore($healthData)
    {
        $usage = $healthData['usage'];
        
        $score = 0;
        
        // Active users score
        if ($usage['unique_users'] >= 10) {
            $score += 30;
        } elseif ($usage['unique_users'] >= 5) {
            $score += 20;
        } elseif ($usage['unique_users'] >= 1) {
            $score += 10;
        }
        
        // Session frequency score
        if ($usage['total_sessions'] >= 100) {
            $score += 30;
        } elseif ($usage['total_sessions'] >= 50) {
            $score += 20;
        } elseif ($usage['total_sessions'] >= 10) {
            $score += 10;
        }
        
        // Feature adoption score
        $adoptionRate = $usage['features_used'] / $usage['total_features'];
        $score += round($adoptionRate * 40);
        
        return min(100, $score);
    }
    
    private function calculateTenureScore($healthData)
    {
        $months = $healthData['relationship']['months_active'];
        
        if ($months >= 24) {
            return 100;
        } elseif ($months >= 12) {
            return 80;
        } elseif ($months >= 6) {
            return 60;
        } elseif ($months >= 3) {
            return 40;
        } else {
            return 20;
        }
    }
    
    private function determineRiskLevel($score)
    {
        if ($score >= $this->riskThresholds['healthy']) {
            return 'healthy';
        } elseif ($score >= $this->riskThresholds['at_risk']) {
            return 'at_risk';
        } else {
            return 'critical';
        }
    }
    
    private function generateRecommendations($factors, $overallScore)
    {
        $recommendations = [];
        
        // Find lowest scoring factors
        asort($factors);
        $lowestFactors = array_slice($factors, 0, 3, true);
        
        foreach ($lowestFactors as $factor => $score) {
            if ($score < 60) {
                switch ($factor) {
                    case 'support_tickets':
                        $recommendations[] = [
                            'priority' => 'high',
                            'action' => 'Schedule support review meeting',
                            'reason' => 'High number of support tickets indicates potential product issues',
                        ];
                        break;
                    
                    case 'activity_level':
                        $recommendations[] = [
                            'priority' => 'high',
                            'action' => 'Reach out for quarterly business review',
                            'reason' => 'Low engagement may indicate declining interest',
                        ];
                        break;
                    
                    case 'feature_adoption':
                        $recommendations[] = [
                            'priority' => 'medium',
                            'action' => 'Offer product training session',
                            'reason' => 'Low feature adoption limits value realization',
                        ];
                        break;
                    
                    case 'payment_history':
                        $recommendations[] = [
                            'priority' => 'high',
                            'action' => 'Review payment terms and setup',
                            'reason' => 'Payment issues may indicate budget constraints',
                        ];
                        break;
                }
            }
        }
        
        // Add general recommendations based on overall score
        if ($overallScore < 40) {
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'Executive escalation required',
                'reason' => 'Account at high risk of churn',
            ];
        }
        
        return $recommendations;
    }
    
    private function estimateChurnProbability($score, $factors)
    {
        // Simple churn probability estimation
        $churnProb = 0;
        
        // Base probability from overall score
        if ($score < 40) {
            $churnProb = 0.7;
        } elseif ($score < 60) {
            $churnProb = 0.4;
        } elseif ($score < 80) {
            $churnProb = 0.2;
        } else {
            $churnProb = 0.05;
        }
        
        // Adjust based on critical factors
        if ($factors['support_tickets'] < 50) {
            $churnProb += 0.1;
        }
        if ($factors['activity_level'] < 30) {
            $churnProb += 0.15;
        }
        if ($factors['payment_history'] < 70) {
            $churnProb += 0.1;
        }
        
        return min(0.95, $churnProb);
    }
    
    private function saveHealthScore($accountId, $data)
    {
        $id = \Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
        
        // Get previous score for comparison
        $prevQuery = "SELECT score FROM customer_health_scores 
                     WHERE account_id = ? 
                     ORDER BY date_calculated DESC 
                     LIMIT 1";
        
        $stmt = $this->db->getConnection()->prepare($prevQuery);
        $stmt->execute([$accountId]);
        $prev = $stmt->fetch(\PDO::FETCH_ASSOC);
        $previousScore = $prev ? $prev['score'] : null;
        
        // Insert new score
        $query = "INSERT INTO customer_health_scores 
                 (id, account_id, score, previous_score, score_change, factors, 
                  risk_level, churn_probability, recommendations, date_calculated)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute([
            $id,
            $accountId,
            $data['score'],
            $previousScore,
            $previousScore ? $data['score'] - $previousScore : 0,
            json_encode($data['factors']),
            $data['risk_level'],
            $data['churn_probability'],
            json_encode($data['recommendations']),
        ]);
        
        // Create alert if score dropped significantly
        if ($previousScore && ($previousScore - $data['score']) >= 20) {
            $this->createHealthAlert($accountId, $data['score'], $previousScore);
        }
    }
    
    private function createHealthAlert($accountId, $newScore, $oldScore)
    {
        // Create a task for the account owner
        $account = BeanFactory::getBean('Accounts', $accountId);
        
        $task = BeanFactory::newBean('Tasks');
        $task->name = "Health Score Alert: {$account->name}";
        $task->description = "Customer health score dropped from {$oldScore} to {$newScore}. Immediate attention required.";
        $task->priority = 'High';
        $task->status = 'Not Started';
        $task->date_due = date('Y-m-d', strtotime('+2 days'));
        $task->assigned_user_id = $account->assigned_user_id;
        $task->parent_type = 'Accounts';
        $task->parent_id = $accountId;
        $task->save();
    }
}