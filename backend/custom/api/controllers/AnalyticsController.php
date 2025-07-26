<?php
namespace Api\Controllers;

use Api\BaseController;

class AnalyticsController extends BaseController {
    
    public function getOverview() {
        try {
            global $db;
            
            // Get date range
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            // Get leads count
            $leadsQuery = "SELECT COUNT(*) as count FROM leads WHERE deleted = 0 AND date_entered BETWEEN '$dateFrom' AND '$dateTo'";
            $leadsResult = $db->query($leadsQuery);
            $leadsCount = $db->fetchByAssoc($leadsResult)['count'] ?? 0;
            
            // Get opportunities count and value
            $oppsQuery = "SELECT COUNT(*) as count, SUM(amount) as total_value FROM opportunities 
                          WHERE deleted = 0 AND date_entered BETWEEN '$dateFrom' AND '$dateTo'";
            $oppsResult = $db->query($oppsQuery);
            $oppsData = $db->fetchByAssoc($oppsResult);
            $oppsCount = $oppsData['count'] ?? 0;
            $oppsValue = $oppsData['total_value'] ?? 0;
            
            // Get won opportunities
            $wonQuery = "SELECT COUNT(*) as count, SUM(amount) as total_value FROM opportunities 
                         WHERE deleted = 0 AND sales_stage = 'Won' AND date_closed BETWEEN '$dateFrom' AND '$dateTo'";
            $wonResult = $db->query($wonQuery);
            $wonData = $db->fetchByAssoc($wonResult);
            $wonCount = $wonData['count'] ?? 0;
            $wonValue = $wonData['total_value'] ?? 0;
            
            // Get cases count
            $casesQuery = "SELECT COUNT(*) as count FROM cases WHERE deleted = 0 AND date_entered BETWEEN '$dateFrom' AND '$dateTo'";
            $casesResult = $db->query($casesQuery);
            $casesCount = $db->fetchByAssoc($casesResult)['count'] ?? 0;
            
            // Get open cases
            $openCasesQuery = "SELECT COUNT(*) as count FROM cases WHERE deleted = 0 AND status IN ('Open', 'In Progress')";
            $openCasesResult = $db->query($openCasesQuery);
            $openCasesCount = $db->fetchByAssoc($openCasesResult)['count'] ?? 0;
            
            // Get activities count
            $activitiesCount = 0;
            $activityTables = ['calls', 'meetings', 'tasks', 'notes'];
            foreach ($activityTables as $table) {
                $actQuery = "SELECT COUNT(*) as count FROM $table WHERE deleted = 0 AND date_entered BETWEEN '$dateFrom' AND '$dateTo'";
                $actResult = $db->query($actQuery);
                $activitiesCount += (int)($db->fetchByAssoc($actResult)['count'] ?? 0);
            }
            
            // Calculate conversion rate
            $conversionRate = $leadsCount > 0 ? round(($oppsCount / $leadsCount) * 100, 2) : 0;
            
            // Calculate win rate
            $winRate = $oppsCount > 0 ? round(($wonCount / $oppsCount) * 100, 2) : 0;
            
            // Get top performers
            $topPerformersQuery = "SELECT u.id, u.first_name, u.last_name, COUNT(o.id) as won_deals, SUM(o.amount) as total_value
                                   FROM users u
                                   JOIN opportunities o ON o.assigned_user_id = u.id
                                   WHERE o.deleted = 0 AND o.sales_stage = 'Won' 
                                   AND o.date_closed BETWEEN '$dateFrom' AND '$dateTo'
                                   GROUP BY u.id
                                   ORDER BY total_value DESC
                                   LIMIT 5";
            $topPerformersResult = $db->query($topPerformersQuery);
            $topPerformers = [];
            while ($row = $db->fetchByAssoc($topPerformersResult)) {
                $topPerformers[] = [
                    'id' => $row['id'],
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'wonDeals' => (int)$row['won_deals'],
                    'totalValue' => (float)$row['total_value']
                ];
            }
            
            return $this->success([
                'overview' => [
                    'leads' => [
                        'count' => (int)$leadsCount,
                        'trend' => $this->calculateTrend('leads', $dateFrom, $dateTo)
                    ],
                    'opportunities' => [
                        'count' => (int)$oppsCount,
                        'totalValue' => (float)$oppsValue,
                        'averageValue' => $oppsCount > 0 ? round($oppsValue / $oppsCount, 2) : 0,
                        'trend' => $this->calculateTrend('opportunities', $dateFrom, $dateTo)
                    ],
                    'wonDeals' => [
                        'count' => (int)$wonCount,
                        'totalValue' => (float)$wonValue,
                        'winRate' => $winRate
                    ],
                    'cases' => [
                        'total' => (int)$casesCount,
                        'open' => (int)$openCasesCount,
                        'resolved' => (int)($casesCount - $openCasesCount)
                    ],
                    'activities' => [
                        'count' => $activitiesCount,
                        'trend' => $this->calculateTrend('activities', $dateFrom, $dateTo)
                    ],
                    'metrics' => [
                        'conversionRate' => $conversionRate,
                        'winRate' => $winRate,
                        'averageDealSize' => $wonCount > 0 ? round($wonValue / $wonCount, 2) : 0
                    ]
                ],
                'topPerformers' => $topPerformers,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to fetch analytics overview: ' . $e->getMessage());
        }
    }
    
    public function getConversionFunnel() {
        try {
            global $db;
            
            // Get date range
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            // Lead stages
            $leadStages = ['New', 'Contacted', 'Qualified'];
            $leadFunnel = [];
            
            foreach ($leadStages as $stage) {
                $query = "SELECT COUNT(*) as count FROM leads 
                          WHERE deleted = 0 AND status = '$stage' 
                          AND date_entered BETWEEN '$dateFrom' AND '$dateTo'";
                $result = $db->query($query);
                $count = $db->fetchByAssoc($result)['count'] ?? 0;
                
                $leadFunnel[] = [
                    'stage' => $stage,
                    'count' => (int)$count
                ];
            }
            
            // Opportunity stages
            $oppStages = ['Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];
            $oppFunnel = [];
            
            foreach ($oppStages as $stage) {
                $query = "SELECT COUNT(*) as count, SUM(amount) as value FROM opportunities 
                          WHERE deleted = 0 AND sales_stage = '$stage' 
                          AND date_entered BETWEEN '$dateFrom' AND '$dateTo'";
                $result = $db->query($query);
                $data = $db->fetchByAssoc($result);
                
                $oppFunnel[] = [
                    'stage' => $stage,
                    'count' => (int)($data['count'] ?? 0),
                    'value' => (float)($data['value'] ?? 0)
                ];
            }
            
            // Calculate conversion metrics
            $totalLeads = array_sum(array_column($leadFunnel, 'count'));
            $qualifiedLeads = $leadFunnel[2]['count'] ?? 0;
            $totalOpps = array_sum(array_column($oppFunnel, 'count'));
            $wonOpps = 0;
            $lostOpps = 0;
            
            foreach ($oppFunnel as $stage) {
                if ($stage['stage'] === 'Won') $wonOpps = $stage['count'];
                if ($stage['stage'] === 'Lost') $lostOpps = $stage['count'];
            }
            
            $leadToOppRate = $totalLeads > 0 ? round(($totalOpps / $totalLeads) * 100, 2) : 0;
            $oppToWonRate = $totalOpps > 0 ? round(($wonOpps / $totalOpps) * 100, 2) : 0;
            
            // Get conversion time metrics
            $avgLeadTime = $this->getAverageLeadTime($dateFrom, $dateTo);
            $avgSalesTime = $this->getAverageSalesTime($dateFrom, $dateTo);
            
            return $this->success([
                'leadFunnel' => $leadFunnel,
                'opportunityFunnel' => $oppFunnel,
                'conversionMetrics' => [
                    'leadToOpportunityRate' => $leadToOppRate,
                    'opportunityToWonRate' => $oppToWonRate,
                    'overallConversionRate' => $totalLeads > 0 ? round(($wonOpps / $totalLeads) * 100, 2) : 0,
                    'averageLeadTime' => $avgLeadTime,
                    'averageSalesTime' => $avgSalesTime
                ],
                'summary' => [
                    'totalLeads' => $totalLeads,
                    'qualifiedLeads' => $qualifiedLeads,
                    'totalOpportunities' => $totalOpps,
                    'wonDeals' => $wonOpps,
                    'lostDeals' => $lostOpps
                ],
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to fetch conversion funnel: ' . $e->getMessage());
        }
    }
    
    public function getLeadSources() {
        try {
            global $db;
            
            // Get date range
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            // Get lead sources performance
            $query = "SELECT 
                        l.lead_source,
                        COUNT(DISTINCT l.id) as lead_count,
                        COUNT(DISTINCT o.id) as opp_count,
                        COUNT(DISTINCT CASE WHEN o.sales_stage = 'Won' THEN o.id END) as won_count,
                        SUM(CASE WHEN o.sales_stage = 'Won' THEN o.amount ELSE 0 END) as won_value
                      FROM leads l
                      LEFT JOIN opportunities o ON o.id IN (
                        SELECT opportunity_id FROM opportunities_contacts oc
                        JOIN contacts c ON c.id = oc.contact_id
                        WHERE c.email1 = l.email1 OR (c.first_name = l.first_name AND c.last_name = l.last_name)
                      )
                      WHERE l.deleted = 0 
                      AND l.date_entered BETWEEN '$dateFrom' AND '$dateTo'
                      GROUP BY l.lead_source
                      ORDER BY lead_count DESC";
            
            $result = $db->query($query);
            $sources = [];
            $totalLeads = 0;
            $totalWonValue = 0;
            
            while ($row = $db->fetchByAssoc($result)) {
                $leadCount = (int)$row['lead_count'];
                $oppCount = (int)$row['opp_count'];
                $wonCount = (int)$row['won_count'];
                $wonValue = (float)$row['won_value'];
                
                $sources[] = [
                    'source' => $row['lead_source'] ?: 'Unknown',
                    'leads' => $leadCount,
                    'opportunities' => $oppCount,
                    'wonDeals' => $wonCount,
                    'revenue' => $wonValue,
                    'conversionRate' => $leadCount > 0 ? round(($oppCount / $leadCount) * 100, 2) : 0,
                    'winRate' => $oppCount > 0 ? round(($wonCount / $oppCount) * 100, 2) : 0,
                    'averageDealSize' => $wonCount > 0 ? round($wonValue / $wonCount, 2) : 0
                ];
                
                $totalLeads += $leadCount;
                $totalWonValue += $wonValue;
            }
            
            // Calculate ROI if we have cost data (placeholder)
            foreach ($sources as &$source) {
                $source['roi'] = 'N/A'; // Would calculate based on cost data if available
                $source['costPerLead'] = 'N/A'; // Would calculate based on cost data if available
            }
            
            // Get trending sources
            $trendingQuery = "SELECT lead_source, COUNT(*) as count 
                              FROM leads 
                              WHERE deleted = 0 
                              AND date_entered >= DATE_SUB('$dateTo', INTERVAL 7 DAY)
                              GROUP BY lead_source
                              ORDER BY count DESC
                              LIMIT 5";
            
            $trendingResult = $db->query($trendingQuery);
            $trending = [];
            
            while ($row = $db->fetchByAssoc($trendingResult)) {
                $trending[] = [
                    'source' => $row['lead_source'] ?: 'Unknown',
                    'recentLeads' => (int)$row['count']
                ];
            }
            
            return $this->success([
                'sources' => $sources,
                'summary' => [
                    'totalSources' => count($sources),
                    'totalLeads' => $totalLeads,
                    'totalRevenue' => $totalWonValue,
                    'topSource' => !empty($sources) ? $sources[0]['source'] : null,
                    'averageLeadsPerSource' => count($sources) > 0 ? round($totalLeads / count($sources), 2) : 0
                ],
                'trending' => $trending,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to fetch lead sources analytics: ' . $e->getMessage());
        }
    }
    
    private function calculateTrend($type, $dateFrom, $dateTo) {
        global $db;
        
        // Calculate the previous period
        $periodDays = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
        $prevDateFrom = date('Y-m-d', strtotime($dateFrom . " -$periodDays days"));
        $prevDateTo = date('Y-m-d', strtotime($dateFrom . ' -1 day'));
        
        $currentCount = 0;
        $previousCount = 0;
        
        switch ($type) {
            case 'leads':
                $currentQuery = "SELECT COUNT(*) as count FROM leads WHERE deleted = 0 AND date_entered BETWEEN '$dateFrom' AND '$dateTo'";
                $previousQuery = "SELECT COUNT(*) as count FROM leads WHERE deleted = 0 AND date_entered BETWEEN '$prevDateFrom' AND '$prevDateTo'";
                break;
                
            case 'opportunities':
                $currentQuery = "SELECT COUNT(*) as count FROM opportunities WHERE deleted = 0 AND date_entered BETWEEN '$dateFrom' AND '$dateTo'";
                $previousQuery = "SELECT COUNT(*) as count FROM opportunities WHERE deleted = 0 AND date_entered BETWEEN '$prevDateFrom' AND '$prevDateTo'";
                break;
                
            case 'activities':
                // Sum all activity types
                $tables = ['calls', 'meetings', 'tasks', 'notes'];
                foreach ($tables as $table) {
                    $currResult = $db->query("SELECT COUNT(*) as count FROM $table WHERE deleted = 0 AND date_entered BETWEEN '$dateFrom' AND '$dateTo'");
                    $currentCount += (int)($db->fetchByAssoc($currResult)['count'] ?? 0);
                    
                    $prevResult = $db->query("SELECT COUNT(*) as count FROM $table WHERE deleted = 0 AND date_entered BETWEEN '$prevDateFrom' AND '$prevDateTo'");
                    $previousCount += (int)($db->fetchByAssoc($prevResult)['count'] ?? 0);
                }
                
                $trend = $previousCount > 0 ? round((($currentCount - $previousCount) / $previousCount) * 100, 2) : 0;
                return [
                    'value' => $trend,
                    'direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat')
                ];
        }
        
        if (isset($currentQuery)) {
            $currentResult = $db->query($currentQuery);
            $currentCount = (int)($db->fetchByAssoc($currentResult)['count'] ?? 0);
            
            $previousResult = $db->query($previousQuery);
            $previousCount = (int)($db->fetchByAssoc($previousResult)['count'] ?? 0);
        }
        
        $trend = $previousCount > 0 ? round((($currentCount - $previousCount) / $previousCount) * 100, 2) : 0;
        
        return [
            'value' => $trend,
            'direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat')
        ];
    }
    
    private function getAverageLeadTime($dateFrom, $dateTo) {
        global $db;
        
        $query = "SELECT AVG(DATEDIFF(date_modified, date_entered)) as avg_days
                  FROM leads
                  WHERE deleted = 0 
                  AND status = 'Qualified'
                  AND date_modified BETWEEN '$dateFrom' AND '$dateTo'";
        
        $result = $db->query($query);
        $avgDays = $db->fetchByAssoc($result)['avg_days'] ?? 0;
        
        return round($avgDays, 1);
    }
    
    private function getAverageSalesTime($dateFrom, $dateTo) {
        global $db;
        
        $query = "SELECT AVG(DATEDIFF(date_closed, date_entered)) as avg_days
                  FROM opportunities
                  WHERE deleted = 0 
                  AND sales_stage = 'Won'
                  AND date_closed BETWEEN '$dateFrom' AND '$dateTo'";
        
        $result = $db->query($query);
        $avgDays = $db->fetchByAssoc($result)['avg_days'] ?? 0;
        
        return round($avgDays, 1);
    }
}