<?php
namespace Api\Controllers;

use Api\Response;
use Api\Request;

class DashboardController extends BaseController
{
    public function getMetrics(Request $request): Response
    {
        try {
            $db = $this->getDb();
            
            // Get total leads
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM leads WHERE deleted = 0");
            $stmt->execute();
            $totalLeads = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Get total accounts
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM accounts WHERE deleted = 0");
            $stmt->execute();
            $totalAccounts = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Get today's leads
            $today = date('Y-m-d');
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM leads 
                                 WHERE deleted = 0 
                                 AND DATE(date_entered) = :today");
            $stmt->execute(['today' => $today]);
            $newLeadsToday = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Get pipeline value
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM opportunities 
                                 WHERE deleted = 0 
                                 AND sales_stage NOT IN ('Closed Won', 'Closed Lost')");
            $stmt->execute();
            $pipelineValue = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            return Response::json([
                'data' => [
                    'total_leads' => (int)$totalLeads,
                    'total_accounts' => (int)$totalAccounts,
                    'new_leads_today' => (int)$newLeadsToday,
                    'pipeline_value' => (float)$pipelineValue,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    public function getPipelineData(Request $request): Response
    {
        try {
            $db = $this->getDb();
            
            $stages = [
                'Qualification',
                'Needs Analysis',
                'Value Proposition',
                'Decision Makers',
                'Proposal',
                'Negotiation',
                'Closed Won',
                'Closed Lost'
            ];
            
            $pipelineData = [];
            
            foreach ($stages as $stage) {
                $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as value 
                                     FROM opportunities 
                                     WHERE deleted = 0 
                                     AND sales_stage = :stage");
                $stmt->execute(['stage' => $stage]);
                $data = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                $pipelineData[] = [
                    'stage' => $stage,
                    'count' => (int)$data['count'],
                    'value' => (float)$data['value'],
                ];
            }
            
            return Response::json(['data' => $pipelineData]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    public function getActivityMetrics(Request $request): Response
    {
        try {
            $db = $this->getDb();
            $today = date('Y-m-d');
            
            // Today's calls
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM calls 
                                 WHERE deleted = 0 
                                 AND DATE(date_start) = :today");
            $stmt->execute(['today' => $today]);
            $callsToday = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Today's meetings
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM meetings 
                                 WHERE deleted = 0 
                                 AND DATE(date_start) = :today");
            $stmt->execute(['today' => $today]);
            $meetingsToday = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Overdue tasks
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks 
                                 WHERE deleted = 0 
                                 AND status != 'Completed' 
                                 AND date_due < NOW()");
            $stmt->execute();
            $tasksOverdue = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Upcoming activities (next 7 days) - simplified for now
            $upcomingActivities = [];
            
            // Get upcoming calls
            $stmt = $db->prepare("SELECT id, name, 'Call' as type, date_start, parent_type, parent_id, assigned_user_id, status 
                                 FROM calls 
                                 WHERE deleted = 0 
                                 AND date_start BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                                 ORDER BY date_start ASC
                                 LIMIT 5");
            $stmt->execute();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $upcomingActivities[] = $this->enrichActivity($row, $db);
            }
            
            // Get upcoming meetings
            $stmt = $db->prepare("SELECT id, name, 'Meeting' as type, date_start, parent_type, parent_id, assigned_user_id, status 
                                 FROM meetings 
                                 WHERE deleted = 0 
                                 AND date_start BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                                 ORDER BY date_start ASC
                                 LIMIT 5");
            $stmt->execute();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $upcomingActivities[] = $this->enrichActivity($row, $db);
            }
            
            // Sort combined activities by date
            usort($upcomingActivities, function($a, $b) {
                return strtotime($a['date_start']) - strtotime($b['date_start']);
            });
            
            // Keep only top 10
            $upcomingActivities = array_slice($upcomingActivities, 0, 10);
            
            return Response::json([
                'data' => [
                    'calls_today' => (int)$callsToday,
                    'meetings_today' => (int)$meetingsToday,
                    'tasks_overdue' => (int)$tasksOverdue,
                    'upcoming_activities' => $upcomingActivities,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    public function getCaseMetrics(Request $request): Response
    {
        try {
            $db = $this->getDb();
            
            // Open cases
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM cases 
                                 WHERE deleted = 0 
                                 AND status NOT IN ('Closed', 'Rejected')");
            $stmt->execute();
            $openCases = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Critical cases (P1)
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM cases 
                                 WHERE deleted = 0 
                                 AND priority = 'P1' 
                                 AND status NOT IN ('Closed', 'Rejected')");
            $stmt->execute();
            $criticalCases = $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            // Average resolution time (last 30 days)
            $stmt = $db->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, date_entered, date_modified)) as avg_hours 
                                 FROM cases 
                                 WHERE deleted = 0 
                                 AND status = 'Closed' 
                                 AND date_modified >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $avgResolution = $stmt->fetch(\PDO::FETCH_ASSOC)['avg_hours'] ?? 0;
            
            // Cases by priority
            $stmt = $db->prepare("SELECT priority, COUNT(*) as count 
                                 FROM cases 
                                 WHERE deleted = 0 
                                 AND status NOT IN ('Closed', 'Rejected') 
                                 GROUP BY priority");
            $stmt->execute();
            $casesByPriority = [];
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $casesByPriority[] = [
                    'priority' => $row['priority'],
                    'count' => (int)$row['count'],
                ];
            }
            
            return Response::json([
                'data' => [
                    'open_cases' => (int)$openCases,
                    'critical_cases' => (int)$criticalCases,
                    'avg_resolution_time' => round($avgResolution, 1),
                    'cases_by_priority' => $casesByPriority,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
    
    private function enrichActivity($activity, $db)
    {
        // Get parent name if exists
        if (!empty($activity['parent_type']) && !empty($activity['parent_id'])) {
            $parentTable = strtolower($activity['parent_type']);
            if (in_array($parentTable, ['accounts', 'contacts', 'leads', 'opportunities'])) {
                $stmt = $db->prepare("SELECT name FROM $parentTable WHERE id = :id");
                $stmt->execute(['id' => $activity['parent_id']]);
                $parent = $stmt->fetch(\PDO::FETCH_ASSOC);
                $activity['parent_name'] = $parent['name'] ?? '';
            }
        }
        
        // Get assigned user name
        if (!empty($activity['assigned_user_id'])) {
            $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name 
                                 FROM users WHERE id = :id");
            $stmt->execute(['id' => $activity['assigned_user_id']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            $activity['assigned_user_name'] = $user['name'] ?? '';
        }
        
        return $activity;
    }
}