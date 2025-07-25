<?php
/**
 * Unit tests for Customer Health Service
 */

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use SuiteCRM\Custom\Services\CustomerHealthService;

class CustomerHealthServiceTest extends TestCase
{
    private $healthService;
    
    protected function setUp(): void
    {
        // Mock dependencies if needed
        $this->healthService = $this->getMockBuilder(CustomerHealthService::class)
            ->setMethods(['gatherHealthData', 'saveHealthScore'])
            ->getMock();
    }
    
    /**
     * Test score calculation logic
     */
    public function testScoreCalculation()
    {
        // Test data representing different scenarios
        $testCases = [
            // Healthy customer
            [
                'data' => [
                    'support_tickets' => ['total_tickets' => 0, 'open_tickets' => 0, 'high_priority' => 0],
                    'activities' => ['meetings' => 5, 'calls' => 3, 'last_interaction' => date('Y-m-d H:i:s')],
                    'usage' => ['unique_users' => 10, 'total_sessions' => 100, 'features_used' => 15, 'total_features' => 20],
                    'financials' => ['contract_value' => 10000, 'late_payments' => 0, 'total_payments' => 12],
                    'relationship' => ['months_active' => 24],
                ],
                'expected_risk' => 'healthy',
                'min_score' => 80,
            ],
            // At-risk customer
            [
                'data' => [
                    'support_tickets' => ['total_tickets' => 10, 'open_tickets' => 5, 'high_priority' => 2],
                    'activities' => ['meetings' => 1, 'calls' => 0, 'last_interaction' => date('Y-m-d H:i:s', strtotime('-45 days'))],
                    'usage' => ['unique_users' => 2, 'total_sessions' => 10, 'features_used' => 5, 'total_features' => 20],
                    'financials' => ['contract_value' => 5000, 'late_payments' => 2, 'total_payments' => 12],
                    'relationship' => ['months_active' => 12],
                ],
                'expected_risk' => 'at_risk',
                'min_score' => 40,
                'max_score' => 70,
            ],
            // Critical customer
            [
                'data' => [
                    'support_tickets' => ['total_tickets' => 20, 'open_tickets' => 10, 'high_priority' => 5],
                    'activities' => ['meetings' => 0, 'calls' => 0, 'last_interaction' => null],
                    'usage' => ['unique_users' => 0, 'total_sessions' => 0, 'features_used' => 0, 'total_features' => 20],
                    'financials' => ['contract_value' => 1000, 'late_payments' => 6, 'total_payments' => 12],
                    'relationship' => ['months_active' => 3],
                ],
                'expected_risk' => 'critical',
                'max_score' => 40,
            ],
        ];
        
        foreach ($testCases as $index => $testCase) {
            $reflection = new \ReflectionClass($this->healthService);
            
            // Test individual factor calculations
            $methods = [
                'calculateSupportScore',
                'calculateActivityScore',
                'calculateAdoptionScore',
                'calculatePaymentScore',
                'calculateTenureScore',
            ];
            
            foreach ($methods as $methodName) {
                $method = $reflection->getMethod($methodName);
                $method->setAccessible(true);
                
                $score = $method->invoke($this->healthService, $testCase['data']);
                
                $this->assertGreaterThanOrEqual(0, $score, "Test case $index: $methodName should return score >= 0");
                $this->assertLessThanOrEqual(100, $score, "Test case $index: $methodName should return score <= 100");
            }
            
            // Test risk level determination
            $riskMethod = $reflection->getMethod('determineRiskLevel');
            $riskMethod->setAccessible(true);
            
            $overallScore = isset($testCase['min_score']) ? $testCase['min_score'] + 5 : 30;
            $riskLevel = $riskMethod->invoke($this->healthService, $overallScore);
            
            $this->assertEquals($testCase['expected_risk'], $riskLevel, "Test case $index: Risk level mismatch");
        }
    }
    
    /**
     * Test recommendation generation
     */
    public function testRecommendationGeneration()
    {
        $reflection = new \ReflectionClass($this->healthService);
        $method = $reflection->getMethod('generateRecommendations');
        $method->setAccessible(true);
        
        // Test with low scoring factors
        $factors = [
            'support_tickets' => 30,
            'activity_level' => 20,
            'feature_adoption' => 50,
            'payment_history' => 80,
            'contract_value' => 90,
            'relationship_length' => 70,
        ];
        
        $recommendations = $method->invoke($this->healthService, $factors, 45);
        
        $this->assertIsArray($recommendations);
        $this->assertGreaterThan(0, count($recommendations), "Should generate recommendations for low scores");
        
        // Verify recommendation structure
        foreach ($recommendations as $rec) {
            $this->assertArrayHasKey('priority', $rec);
            $this->assertArrayHasKey('action', $rec);
            $this->assertArrayHasKey('reason', $rec);
            $this->assertContains($rec['priority'], ['critical', 'high', 'medium']);
        }
    }
    
    /**
     * Test churn probability estimation
     */
    public function testChurnProbabilityEstimation()
    {
        $reflection = new \ReflectionClass($this->healthService);
        $method = $reflection->getMethod('estimateChurnProbability');
        $method->setAccessible(true);
        
        $testCases = [
            ['score' => 90, 'expected_max' => 0.15],
            ['score' => 70, 'expected_range' => [0.15, 0.35]],
            ['score' => 50, 'expected_range' => [0.35, 0.55]],
            ['score' => 30, 'expected_min' => 0.65],
        ];
        
        foreach ($testCases as $testCase) {
            $factors = [
                'support_tickets' => $testCase['score'],
                'activity_level' => $testCase['score'],
                'feature_adoption' => $testCase['score'],
                'payment_history' => $testCase['score'],
                'contract_value' => $testCase['score'],
                'relationship_length' => $testCase['score'],
            ];
            
            $probability = $method->invoke($this->healthService, $testCase['score'], $factors);
            
            $this->assertGreaterThanOrEqual(0, $probability, "Churn probability should be >= 0");
            $this->assertLessThanOrEqual(1, $probability, "Churn probability should be <= 1");
            
            if (isset($testCase['expected_max'])) {
                $this->assertLessThanOrEqual($testCase['expected_max'], $probability);
            }
            if (isset($testCase['expected_min'])) {
                $this->assertGreaterThanOrEqual($testCase['expected_min'], $probability);
            }
            if (isset($testCase['expected_range'])) {
                $this->assertGreaterThanOrEqual($testCase['expected_range'][0], $probability);
                $this->assertLessThanOrEqual($testCase['expected_range'][1], $probability);
            }
        }
    }
}