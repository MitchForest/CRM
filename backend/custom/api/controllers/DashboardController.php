<?php
namespace Api\Controllers;

use Api\Response;
use Api\Request;

class DashboardController extends BaseController
{
    public function getMetrics(Request $request)
    {
        global $db;
        
        try {
            $metrics = [];
            
            // Get total leads
            $result = $db->query("SELECT COUNT(*) as total FROM leads WHERE deleted = 0");
            $row = $db->fetchByAssoc($result);
            $metrics['totalLeads'] = (int)($row['total'] ?? 0);
            
            // Get total accounts
            $result = $db->query("SELECT COUNT(*) as total FROM accounts WHERE deleted = 0");
            $row = $db->fetchByAssoc($result);
            $metrics['totalAccounts'] = (int)($row['total'] ?? 0);
            
            // Get new leads today
            $today = date('Y-m-d');
            $result = $db->query("SELECT COUNT(*) as total FROM leads WHERE deleted = 0 AND DATE(date_entered) = '$today'");
            $row = $db->fetchByAssoc($result);
            $metrics['newLeadsToday'] = (int)($row['total'] ?? 0);
            
            // Get pipeline value
            $result = $db->query("SELECT SUM(amount) as total FROM opportunities WHERE deleted = 0 AND sales_stage NOT IN ('Closed Won', 'Closed Lost')");
            $row = $db->fetchByAssoc($result);
            $metrics['pipelineValue'] = (float)($row['total'] ?? 0);
            
            return Response::json(['data' => $metrics]);
        } catch (\Exception $e) {
            return Response::json([
                'error' => 'Failed to retrieve dashboard metrics: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getPipelineData(Request $request)
    {
        global $db;
        
        try {
            $pipelineData = [];
            
            // Map actual stages to simplified stages
            $stageMapping = [
                'Qualification' => 'Qualified',
                'Needs Analysis' => 'Qualified',
                'Value Proposition' => 'Qualified',
                'Proposal/Price Quote' => 'Proposal',
                'Negotiation/Review' => 'Negotiation',
                'Closed Won' => 'Won',
                'Closed Lost' => 'Lost'
            ];
            
            // Group by our simplified stages
            $simplifiedData = [
                'Qualified' => ['count' => 0, 'value' => 0],
                'Proposal' => ['count' => 0, 'value' => 0],
                'Negotiation' => ['count' => 0, 'value' => 0]
            ];
            
            // Get all opportunities and group them
            $query = "SELECT sales_stage, COUNT(*) as count, SUM(amount) as value 
                     FROM opportunities 
                     WHERE deleted = 0 
                     AND sales_stage NOT IN ('Closed Won', 'Closed Lost')
                     GROUP BY sales_stage";
                     
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $stage = $row['sales_stage'];
                if (isset($stageMapping[$stage])) {
                    $mappedStage = $stageMapping[$stage];
                    if (isset($simplifiedData[$mappedStage])) {
                        $simplifiedData[$mappedStage]['count'] += (int)$row['count'];
                        $simplifiedData[$mappedStage]['value'] += (float)$row['value'];
                    }
                }
            }
            
            // Convert to array format
            foreach ($simplifiedData as $stage => $data) {
                $pipelineData[] = [
                    'stage' => $stage,
                    'count' => $data['count'],
                    'value' => $data['value']
                ];
            }
            
            return Response::json(['data' => $pipelineData]);
        } catch (\Exception $e) {
            return Response::json([
                'error' => 'Failed to retrieve pipeline data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getActivityMetrics(Request $request)
    {
        global $db;
        
        try {
            $today = date('Y-m-d');
            $metrics = [];
            
            // Get calls today
            $result = $db->query("SELECT COUNT(*) as total FROM calls WHERE deleted = 0 AND DATE(date_start) = '$today'");
            $row = $db->fetchByAssoc($result);
            $metrics['callsToday'] = (int)($row['total'] ?? 0);
            
            // Get meetings today
            $result = $db->query("SELECT COUNT(*) as total FROM meetings WHERE deleted = 0 AND DATE(date_start) = '$today'");
            $row = $db->fetchByAssoc($result);
            $metrics['meetingsToday'] = (int)($row['total'] ?? 0);
            
            // Get overdue tasks
            $now = date('Y-m-d H:i:s');
            $result = $db->query("SELECT COUNT(*) as total FROM tasks WHERE deleted = 0 AND status != 'Completed' AND date_due < '$now'");
            $row = $db->fetchByAssoc($result);
            $metrics['tasksOverdue'] = (int)($row['total'] ?? 0);
            
            // Get upcoming activities
            $upcomingActivities = [];
            
            // Get upcoming calls
            $query = "SELECT c.id, c.name, 'Call' as type, c.date_start, 
                     COALESCE(a.name, CONCAT(cont.first_name, ' ', cont.last_name)) as related_to
                     FROM calls c
                     LEFT JOIN accounts a ON c.parent_type = 'Accounts' AND c.parent_id = a.id
                     LEFT JOIN contacts cont ON c.parent_type = 'Contacts' AND c.parent_id = cont.id
                     WHERE c.deleted = 0 
                     AND c.date_start >= '$now'
                     AND c.status != 'Held'
                     ORDER BY c.date_start ASC
                     LIMIT 5";
                     
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $upcomingActivities[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'dateStart' => $row['date_start'],
                    'relatedTo' => $row['related_to'] ?? 'Unknown'
                ];
            }
            
            $metrics['upcomingActivities'] = $upcomingActivities;
            
            return Response::json(['data' => $metrics]);
        } catch (\Exception $e) {
            return Response::json([
                'error' => 'Failed to retrieve activity metrics: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getRecentLeads(Request $request)
    {
        global $db;
        
        try {
            $leads = [];
            
            $query = "SELECT id, first_name, last_name, email1 as email, phone_work as phone, 
                     account_name as company, lead_source, status, date_entered
                     FROM leads
                     WHERE deleted = 0
                     ORDER BY date_entered DESC
                     LIMIT 10";
                     
            $result = $db->query($query);
            while ($row = $db->fetchByAssoc($result)) {
                $leads[] = [
                    'id' => $row['id'],
                    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'company' => $row['company'],
                    'leadSource' => $row['lead_source'],
                    'status' => $row['status'],
                    'dateEntered' => $row['date_entered']
                ];
            }
            
            return Response::json(['data' => $leads]);
        } catch (\Exception $e) {
            return Response::json([
                'error' => 'Failed to retrieve recent leads: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getCaseMetrics(Request $request)
    {
        global $db;
        
        try {
            $metrics = [];
            
            // Get open cases count
            $result = $db->query("SELECT COUNT(*) as total FROM cases WHERE deleted = 0 AND status LIKE 'Open_%'");
            $row = $db->fetchByAssoc($result);
            $metrics['openCases'] = (int)($row['total'] ?? 0);
            
            // Get closed cases this month
            $monthStart = date('Y-m-01');
            $result = $db->query("SELECT COUNT(*) as total FROM cases WHERE deleted = 0 AND status LIKE 'Closed_%' AND date_modified >= '$monthStart'");
            $row = $db->fetchByAssoc($result);
            $metrics['closedThisMonth'] = (int)($row['total'] ?? 0);
            
            // Get high priority cases
            $result = $db->query("SELECT COUNT(*) as total FROM cases WHERE deleted = 0 AND priority = 'High' AND status LIKE 'Open_%'");
            $row = $db->fetchByAssoc($result);
            $metrics['highPriority'] = (int)($row['total'] ?? 0);
            
            // Get average resolution time (simplified - just counting days)
            $result = $db->query("SELECT AVG(DATEDIFF(date_modified, date_entered)) as avg_days FROM cases WHERE deleted = 0 AND status LIKE 'Closed_%'");
            $row = $db->fetchByAssoc($result);
            $metrics['avgResolutionDays'] = round((float)($row['avg_days'] ?? 0), 1);
            
            return Response::json(['data' => $metrics]);
        } catch (\Exception $e) {
            return Response::json([
                'error' => 'Failed to retrieve case metrics: ' . $e->getMessage()
            ], 500);
        }
    }
}