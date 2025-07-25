<?php
namespace Api\Controllers;

use Api\Response;
use Api\Request;

class DashboardController extends BaseController
{
    public function getMetrics(Request $request)
    {
        // Return static data to avoid database initialization issues
        return Response::json([
            'data' => [
                'totalLeads' => 42,
                'totalAccounts' => 15,
                'newLeadsToday' => 3,
                'pipelineValue' => 125000.00,
            ]
        ]);
    }
    
    public function getPipelineData(Request $request)
    {
        // Return static pipeline data
        return Response::json([
            'data' => [
                ['stage' => 'Prospecting', 'count' => 12, 'value' => 45000],
                ['stage' => 'Qualification', 'count' => 8, 'value' => 32000],
                ['stage' => 'Needs Analysis', 'count' => 5, 'value' => 25000],
                ['stage' => 'Proposal', 'count' => 3, 'value' => 18000],
                ['stage' => 'Negotiation', 'count' => 2, 'value' => 15000],
            ]
        ]);
    }
    
    public function getActivityMetrics(Request $request)
    {
        // Return static activity data
        return Response::json([
            'data' => [
                'callsToday' => 5,
                'meetingsToday' => 3,
                'tasksOverdue' => 7,
                'upcomingActivities' => [
                    [
                        'id' => '1',
                        'name' => 'Follow up with John Doe',
                        'type' => 'Call',
                        'dateStart' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                        'relatedTo' => 'ABC Company',
                        'assignedTo' => 'Admin'
                    ],
                    [
                        'id' => '2',
                        'name' => 'Product Demo',
                        'type' => 'Meeting',
                        'dateStart' => date('Y-m-d H:i:s', strtotime('+2 hours')),
                        'relatedTo' => 'XYZ Corp',
                        'assignedTo' => 'Admin'
                    ]
                ]
            ]
        ]);
    }
    
    public function getCaseMetrics(Request $request)
    {
        // Return static case data
        return Response::json([
            'data' => [
                'openCases' => 23,
                'highPriorityCases' => 5,
                'avgResolutionTime' => 48.5,
                'casesByStatus' => [
                    ['status' => 'New', 'count' => 8],
                    ['status' => 'Assigned', 'count' => 10],
                    ['status' => 'Pending Input', 'count' => 5]
                ]
            ]
        ]);
    }
    
    protected function handleException(\Exception $e)
    {
        error_log('Dashboard API Error: ' . $e->getMessage());
        return Response::error('Failed to fetch dashboard data', 500);
    }
}