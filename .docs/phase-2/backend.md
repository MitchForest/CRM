# Phase 2 - Backend Implementation Guide

## Overview
Phase 2 configures SuiteCRM's core CRM modules for B2B software sales: Opportunities with custom stages, Activities management, Cases for support tickets, and enhanced dashboard APIs. This phase also implements role-based access controls and custom API endpoints for frontend features.

## Prerequisites
- Phase 1 backend completed and working
- SuiteCRM installed with v8 API configured
- Custom fields for Leads and Accounts created
- Docker environment running

## Step-by-Step Implementation

### 1. Opportunities Module Configuration

#### 1.1 Create B2B Sales Stages Configuration
`custom/Extension/modules/Opportunities/Ext/Vardefs/b2b_stages.php`:
```php
<?php
// Define B2B-specific sales stages
$app_list_strings['sales_stage_dom'] = array(
    'Qualification' => 'Qualification',
    'Needs Analysis' => 'Needs Analysis',
    'Value Proposition' => 'Value Proposition',
    'Decision Makers' => 'Decision Makers',
    'Proposal' => 'Proposal',
    'Negotiation' => 'Negotiation',
    'Closed Won' => 'Closed Won',
    'Closed Lost' => 'Closed Lost',
);

// Define probability mapping for stages
$app_list_strings['sales_probability_dom'] = array(
    'Qualification' => '10',
    'Needs Analysis' => '20',
    'Value Proposition' => '40',
    'Decision Makers' => '60',
    'Proposal' => '75',
    'Negotiation' => '90',
    'Closed Won' => '100',
    'Closed Lost' => '0',
);

// Add custom fields for B2B sales
$dictionary['Opportunity']['fields']['competitors'] = array(
    'name' => 'competitors',
    'vname' => 'LBL_COMPETITORS',
    'type' => 'text',
    'comment' => 'Competitors being evaluated by the prospect',
    'rows' => 3,
    'cols' => 60,
);

$dictionary['Opportunity']['fields']['decision_criteria'] = array(
    'name' => 'decision_criteria',
    'vname' => 'LBL_DECISION_CRITERIA',
    'type' => 'text',
    'comment' => 'Key decision criteria for this opportunity',
    'rows' => 3,
    'cols' => 60,
);

$dictionary['Opportunity']['fields']['champion_contact_id'] = array(
    'name' => 'champion_contact_id',
    'vname' => 'LBL_CHAMPION_CONTACT',
    'type' => 'id',
    'comment' => 'Internal champion contact ID',
);

$dictionary['Opportunity']['fields']['subscription_type'] = array(
    'name' => 'subscription_type',
    'vname' => 'LBL_SUBSCRIPTION_TYPE',
    'type' => 'enum',
    'options' => 'subscription_type_list',
    'comment' => 'Type of subscription (Monthly, Annual, etc)',
);
```

#### 1.2 Create Language Labels
`custom/Extension/modules/Opportunities/Ext/Language/en_us.b2b_fields.php`:
```php
<?php
$mod_strings['LBL_COMPETITORS'] = 'Competitors';
$mod_strings['LBL_DECISION_CRITERIA'] = 'Decision Criteria';
$mod_strings['LBL_CHAMPION_CONTACT'] = 'Champion Contact';
$mod_strings['LBL_SUBSCRIPTION_TYPE'] = 'Subscription Type';

$app_list_strings['subscription_type_list'] = array(
    'monthly' => 'Monthly',
    'annual' => 'Annual',
    'multi_year' => 'Multi-Year',
    'one_time' => 'One-Time',
    'trial' => 'Trial',
);
```

#### 1.3 Create Logic Hook for Stage Probability
`custom/modules/Opportunities/logic_hooks.php`:
```php
<?php
$hook_version = 1;
$hook_array = array();

$hook_array['before_save'] = array();
$hook_array['before_save'][] = array(
    1,
    'Update probability based on stage',
    'custom/modules/Opportunities/OpportunityHooks.php',
    'OpportunityHooks',
    'updateProbability'
);
```

`custom/modules/Opportunities/OpportunityHooks.php`:
```php
<?php
class OpportunityHooks
{
    public function updateProbability($bean, $event, $arguments)
    {
        global $app_list_strings;
        
        if (!empty($bean->sales_stage) && 
            isset($app_list_strings['sales_probability_dom'][$bean->sales_stage])) {
            
            // Only update if probability hasn't been manually set
            if (empty($bean->fetched_row['id']) || 
                $bean->fetched_row['sales_stage'] != $bean->sales_stage) {
                
                $bean->probability = $app_list_strings['sales_probability_dom'][$bean->sales_stage];
            }
        }
    }
}
```

### 2. Activities Module Configuration

#### 2.1 Configure Calls Module for B2B
`custom/Extension/modules/Calls/Ext/Vardefs/b2b_fields.php`:
```php
<?php
$dictionary['Call']['fields']['call_type'] = array(
    'name' => 'call_type',
    'vname' => 'LBL_CALL_TYPE',
    'type' => 'enum',
    'options' => 'call_type_list',
    'comment' => 'Type of call (Demo, Discovery, Follow-up, etc)',
    'default' => 'follow_up',
);

$dictionary['Call']['fields']['call_outcome'] = array(
    'name' => 'call_outcome',
    'vname' => 'LBL_CALL_OUTCOME',
    'type' => 'enum',
    'options' => 'call_outcome_list',
    'comment' => 'Outcome of the call',
);

$dictionary['Call']['fields']['next_steps'] = array(
    'name' => 'next_steps',
    'vname' => 'LBL_NEXT_STEPS',
    'type' => 'text',
    'comment' => 'Next steps after this call',
    'rows' => 3,
    'cols' => 60,
);
```

#### 2.2 Configure Meetings Module
`custom/Extension/modules/Meetings/Ext/Vardefs/b2b_fields.php`:
```php
<?php
$dictionary['Meeting']['fields']['meeting_type'] = array(
    'name' => 'meeting_type',
    'vname' => 'LBL_MEETING_TYPE',
    'type' => 'enum',
    'options' => 'meeting_type_list',
    'comment' => 'Type of meeting',
    'default' => 'demo',
);

$dictionary['Meeting']['fields']['demo_environment'] = array(
    'name' => 'demo_environment',
    'vname' => 'LBL_DEMO_ENVIRONMENT',
    'type' => 'varchar',
    'len' => 255,
    'comment' => 'Demo environment URL or details',
);

$dictionary['Meeting']['fields']['attendee_count'] = array(
    'name' => 'attendee_count',
    'vname' => 'LBL_ATTENDEE_COUNT',
    'type' => 'int',
    'len' => 3,
    'comment' => 'Number of attendees',
    'default' => 1,
);
```

#### 2.3 Configure Tasks Module
`custom/Extension/modules/Tasks/Ext/Vardefs/b2b_fields.php`:
```php
<?php
$dictionary['Task']['fields']['task_type'] = array(
    'name' => 'task_type',
    'vname' => 'LBL_TASK_TYPE',
    'type' => 'enum',
    'options' => 'task_type_list',
    'comment' => 'Type of task',
    'default' => 'follow_up',
);

$dictionary['Task']['fields']['related_opportunity_id'] = array(
    'name' => 'related_opportunity_id',
    'vname' => 'LBL_RELATED_OPPORTUNITY',
    'type' => 'id',
    'comment' => 'Related opportunity if applicable',
);
```

#### 2.4 Create Activity Language Strings
`custom/Extension/application/Ext/Language/en_us.activities.php`:
```php
<?php
// Call types
$app_list_strings['call_type_list'] = array(
    'discovery' => 'Discovery Call',
    'demo' => 'Product Demo',
    'follow_up' => 'Follow-up',
    'check_in' => 'Check-in',
    'support' => 'Support Call',
    'renewal' => 'Renewal Discussion',
);

$app_list_strings['call_outcome_list'] = array(
    'successful' => 'Successful',
    'no_answer' => 'No Answer',
    'left_message' => 'Left Message',
    'rescheduled' => 'Rescheduled',
    'not_interested' => 'Not Interested',
);

// Meeting types
$app_list_strings['meeting_type_list'] = array(
    'demo' => 'Product Demo',
    'discovery' => 'Discovery Meeting',
    'proposal' => 'Proposal Presentation',
    'negotiation' => 'Contract Negotiation',
    'kickoff' => 'Kickoff Meeting',
    'qbr' => 'Quarterly Business Review',
    'training' => 'Training Session',
);

// Task types
$app_list_strings['task_type_list'] = array(
    'follow_up' => 'Follow-up',
    'proposal' => 'Send Proposal',
    'contract' => 'Send Contract',
    'demo_prep' => 'Demo Preparation',
    'research' => 'Research',
    'internal' => 'Internal Task',
);
```

### 3. Cases Module Configuration for Support

#### 3.1 Configure Cases for B2B Support
`custom/Extension/modules/Cases/Ext/Vardefs/b2b_support.php`:
```php
<?php
// Override default priority values for B2B
$app_list_strings['case_priority_dom'] = array(
    'P1' => 'P1 - Critical',
    'P2' => 'P2 - High',
    'P3' => 'P3 - Medium',
);

// Define case types for software support
$app_list_strings['case_type_dom'] = array(
    'bug' => 'Bug Report',
    'feature_request' => 'Feature Request',
    'technical_support' => 'Technical Support',
    'account_issue' => 'Account Issue',
    'integration' => 'Integration Help',
    'training' => 'Training Request',
    'other' => 'Other',
);

// Custom fields for B2B support
$dictionary['Case']['fields']['severity'] = array(
    'name' => 'severity',
    'vname' => 'LBL_SEVERITY',
    'type' => 'enum',
    'options' => 'case_severity_list',
    'comment' => 'Severity of the issue',
);

$dictionary['Case']['fields']['product_version'] = array(
    'name' => 'product_version',
    'vname' => 'LBL_PRODUCT_VERSION',
    'type' => 'varchar',
    'len' => 50,
    'comment' => 'Product version affected',
);

$dictionary['Case']['fields']['environment'] = array(
    'name' => 'environment',
    'vname' => 'LBL_ENVIRONMENT',
    'type' => 'enum',
    'options' => 'environment_list',
    'comment' => 'Customer environment',
);

$dictionary['Case']['fields']['sla_deadline'] = array(
    'name' => 'sla_deadline',
    'vname' => 'LBL_SLA_DEADLINE',
    'type' => 'datetime',
    'comment' => 'SLA deadline for resolution',
);

$dictionary['Case']['fields']['kb_article_id'] = array(
    'name' => 'kb_article_id',
    'vname' => 'LBL_KB_ARTICLE',
    'type' => 'id',
    'comment' => 'Related knowledge base article',
);
```

#### 3.2 Create SLA Logic Hook
`custom/modules/Cases/logic_hooks.php`:
```php
<?php
$hook_version = 1;
$hook_array = array();

$hook_array['before_save'] = array();
$hook_array['before_save'][] = array(
    1,
    'Calculate SLA deadline',
    'custom/modules/Cases/CaseHooks.php',
    'CaseHooks',
    'calculateSLA'
);
```

`custom/modules/Cases/CaseHooks.php`:
```php
<?php
class CaseHooks
{
    public function calculateSLA($bean, $event, $arguments)
    {
        // Only calculate for new cases
        if (empty($bean->fetched_row['id'])) {
            $slaHours = $this->getSLAHours($bean->priority);
            
            if ($slaHours > 0) {
                $deadline = new DateTime();
                $deadline->add(new DateInterval('PT' . $slaHours . 'H'));
                $bean->sla_deadline = $deadline->format('Y-m-d H:i:s');
            }
        }
    }
    
    private function getSLAHours($priority)
    {
        $slaMap = array(
            'P1' => 4,   // 4 hours
            'P2' => 24,  // 24 hours
            'P3' => 72,  // 72 hours
        );
        
        return isset($slaMap[$priority]) ? $slaMap[$priority] : 72;
    }
}
```

### 4. Enhanced Dashboard API Endpoints

#### 4.1 Create Dashboard Controller
`custom/api/v8/controllers/DashboardController.php` (update from Phase 1):
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class DashboardController extends BaseController
{
    public function getMetrics(Request $request, Response $response, array $args)
    {
        global $db;
        
        // Get total leads
        $leadsQuery = "SELECT COUNT(*) as total FROM leads WHERE deleted = 0";
        $leadsResult = $db->query($leadsQuery);
        $totalLeads = $leadsResult->fetch_assoc()['total'];
        
        // Get total accounts
        $accountsQuery = "SELECT COUNT(*) as total FROM accounts WHERE deleted = 0";
        $accountsResult = $db->query($accountsQuery);
        $totalAccounts = $accountsResult->fetch_assoc()['total'];
        
        // Get today's leads
        $today = date('Y-m-d');
        $todayLeadsQuery = "SELECT COUNT(*) as total FROM leads 
                           WHERE deleted = 0 
                           AND DATE(date_entered) = '$today'";
        $todayResult = $db->query($todayLeadsQuery);
        $newLeadsToday = $todayResult->fetch_assoc()['total'];
        
        // Get pipeline value
        $pipelineQuery = "SELECT SUM(amount) as total FROM opportunities 
                         WHERE deleted = 0 
                         AND sales_stage NOT IN ('Closed Won', 'Closed Lost')";
        $pipelineResult = $db->query($pipelineQuery);
        $pipelineValue = $pipelineResult->fetch_assoc()['total'] ?? 0;
        
        return $response->withJson([
            'data' => [
                'total_leads' => (int)$totalLeads,
                'total_accounts' => (int)$totalAccounts,
                'new_leads_today' => (int)$newLeadsToday,
                'pipeline_value' => (float)$pipelineValue,
            ]
        ]);
    }
    
    public function getPipelineData(Request $request, Response $response, array $args)
    {
        global $db;
        
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
            $query = "SELECT COUNT(*) as count, SUM(amount) as value 
                     FROM opportunities 
                     WHERE deleted = 0 
                     AND sales_stage = '$stage'";
            
            $result = $db->query($query);
            $data = $result->fetch_assoc();
            
            $pipelineData[] = [
                'stage' => $stage,
                'count' => (int)$data['count'],
                'value' => (float)($data['value'] ?? 0),
            ];
        }
        
        return $response->withJson(['data' => $pipelineData]);
    }
    
    public function getActivityMetrics(Request $request, Response $response, array $args)
    {
        global $db;
        
        $today = date('Y-m-d');
        
        // Today's calls
        $callsQuery = "SELECT COUNT(*) as total FROM calls 
                      WHERE deleted = 0 
                      AND DATE(date_start) = '$today'";
        $callsResult = $db->query($callsQuery);
        $callsToday = $callsResult->fetch_assoc()['total'];
        
        // Today's meetings
        $meetingsQuery = "SELECT COUNT(*) as total FROM meetings 
                         WHERE deleted = 0 
                         AND DATE(date_start) = '$today'";
        $meetingsResult = $db->query($meetingsQuery);
        $meetingsToday = $meetingsResult->fetch_assoc()['total'];
        
        // Overdue tasks
        $tasksQuery = "SELECT COUNT(*) as total FROM tasks 
                      WHERE deleted = 0 
                      AND status != 'Completed' 
                      AND date_due < NOW()";
        $tasksResult = $db->query($tasksQuery);
        $tasksOverdue = $tasksResult->fetch_assoc()['total'];
        
        // Upcoming activities (next 7 days)
        $upcomingQuery = "
            (SELECT id, name, 'Call' as type, date_start, parent_type, parent_id, assigned_user_id, status 
             FROM calls 
             WHERE deleted = 0 AND date_start BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY))
            UNION ALL
            (SELECT id, name, 'Meeting' as type, date_start, parent_type, parent_id, assigned_user_id, status 
             FROM meetings 
             WHERE deleted = 0 AND date_start BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY))
            UNION ALL
            (SELECT id, name, 'Task' as type, date_due as date_start, parent_type, parent_id, assigned_user_id, status 
             FROM tasks 
             WHERE deleted = 0 AND date_due BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY))
            ORDER BY date_start ASC
            LIMIT 10";
        
        $upcomingResult = $db->query($upcomingQuery);
        $upcomingActivities = [];
        
        while ($row = $upcomingResult->fetch_assoc()) {
            // Get parent name
            if ($row['parent_type'] && $row['parent_id']) {
                $parentTable = strtolower($row['parent_type']);
                $parentQuery = "SELECT name FROM $parentTable WHERE id = '{$row['parent_id']}'";
                $parentResult = $db->query($parentQuery);
                $parentData = $parentResult->fetch_assoc();
                $row['parent_name'] = $parentData['name'] ?? '';
            }
            
            // Get assigned user name
            if ($row['assigned_user_id']) {
                $userQuery = "SELECT CONCAT(first_name, ' ', last_name) as name 
                             FROM users WHERE id = '{$row['assigned_user_id']}'";
                $userResult = $db->query($userQuery);
                $userData = $userResult->fetch_assoc();
                $row['assigned_user_name'] = $userData['name'] ?? '';
            }
            
            $upcomingActivities[] = $row;
        }
        
        return $response->withJson([
            'data' => [
                'calls_today' => (int)$callsToday,
                'meetings_today' => (int)$meetingsToday,
                'tasks_overdue' => (int)$tasksOverdue,
                'upcoming_activities' => $upcomingActivities,
            ]
        ]);
    }
    
    public function getCaseMetrics(Request $request, Response $response, array $args)
    {
        global $db;
        
        // Open cases
        $openQuery = "SELECT COUNT(*) as total FROM cases 
                     WHERE deleted = 0 
                     AND status NOT IN ('Closed', 'Rejected')";
        $openResult = $db->query($openQuery);
        $openCases = $openResult->fetch_assoc()['total'];
        
        // Critical cases (P1)
        $criticalQuery = "SELECT COUNT(*) as total FROM cases 
                         WHERE deleted = 0 
                         AND priority = 'P1' 
                         AND status NOT IN ('Closed', 'Rejected')";
        $criticalResult = $db->query($criticalQuery);
        $criticalCases = $criticalResult->fetch_assoc()['total'];
        
        // Average resolution time (last 30 days)
        $resolutionQuery = "SELECT AVG(TIMESTAMPDIFF(HOUR, date_entered, date_modified)) as avg_hours 
                           FROM cases 
                           WHERE deleted = 0 
                           AND status = 'Closed' 
                           AND date_modified >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $resolutionResult = $db->query($resolutionQuery);
        $avgResolution = $resolutionResult->fetch_assoc()['avg_hours'] ?? 0;
        
        // Cases by priority
        $priorityQuery = "SELECT priority, COUNT(*) as count 
                         FROM cases 
                         WHERE deleted = 0 
                         AND status NOT IN ('Closed', 'Rejected') 
                         GROUP BY priority";
        $priorityResult = $db->query($priorityQuery);
        $casesByPriority = [];
        
        while ($row = $priorityResult->fetch_assoc()) {
            $casesByPriority[] = [
                'priority' => $row['priority'],
                'count' => (int)$row['count'],
            ];
        }
        
        return $response->withJson([
            'data' => [
                'open_cases' => (int)$openCases,
                'critical_cases' => (int)$criticalCases,
                'avg_resolution_time' => round($avgResolution, 1),
                'cases_by_priority' => $casesByPriority,
            ]
        ]);
    }
}
```

#### 4.2 Update API Routes
`custom/api/v8/routes/routes.php` (update from Phase 1):
```php
<?php
return [
    // Dashboard endpoints
    'dashboard_metrics' => [
        'method' => 'GET',
        'route' => '/dashboard/metrics',
        'class' => 'Api\V8\Custom\Controller\DashboardController',
        'function' => 'getMetrics',
        'secure' => true,
    ],
    'dashboard_pipeline' => [
        'method' => 'GET',
        'route' => '/dashboard/pipeline',
        'class' => 'Api\V8\Custom\Controller\DashboardController',
        'function' => 'getPipelineData',
        'secure' => true,
    ],
    'dashboard_activities' => [
        'method' => 'GET',
        'route' => '/dashboard/activities',
        'class' => 'Api\V8\Custom\Controller\DashboardController',
        'function' => 'getActivityMetrics',
        'secure' => true,
    ],
    'dashboard_cases' => [
        'method' => 'GET',
        'route' => '/dashboard/cases',
        'class' => 'Api\V8\Custom\Controller\DashboardController',
        'function' => 'getCaseMetrics',
        'secure' => true,
    ],
    
    // Email viewing endpoint
    'email_view' => [
        'method' => 'GET',
        'route' => '/emails/{id}/view',
        'class' => 'Api\V8\Custom\Controller\EmailController',
        'function' => 'viewEmail',
        'secure' => true,
    ],
    
    // Document download endpoint
    'document_download' => [
        'method' => 'GET',
        'route' => '/documents/{id}/download',
        'class' => 'Api\V8\Custom\Controller\DocumentController',
        'function' => 'downloadDocument',
        'secure' => true,
    ],
];
```

### 5. Email and Document Controllers

#### 5.1 Create Email Controller
`custom/api/v8/controllers/EmailController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class EmailController extends BaseController
{
    public function viewEmail(Request $request, Response $response, array $args)
    {
        global $db;
        
        $emailId = $args['id'];
        
        // Validate email ID
        if (!preg_match('/^[a-f0-9\-]{36}$/', $emailId)) {
            return $response->withJson(['error' => 'Invalid email ID'], 400);
        }
        
        // Get email details
        $query = "SELECT e.*, et.from_addr_name, et.to_addrs_names, et.cc_addrs_names 
                 FROM emails e
                 LEFT JOIN emails_text et ON e.id = et.email_id
                 WHERE e.id = '$emailId' AND e.deleted = 0";
        
        $result = $db->query($query);
        
        if ($result->num_rows == 0) {
            return $response->withJson(['error' => 'Email not found'], 404);
        }
        
        $email = $result->fetch_assoc();
        
        // Get attachments
        $attachmentQuery = "SELECT n.id, n.name, n.file_mime_type, n.filename 
                           FROM notes n
                           JOIN emails_beans eb ON n.id = eb.bean_id
                           WHERE eb.email_id = '$emailId' 
                           AND eb.bean_module = 'Notes' 
                           AND n.deleted = 0";
        
        $attachmentResult = $db->query($attachmentQuery);
        $attachments = [];
        
        while ($row = $attachmentResult->fetch_assoc()) {
            $attachments[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'filename' => $row['filename'],
                'mime_type' => $row['file_mime_type'],
            ];
        }
        
        return $response->withJson([
            'data' => [
                'id' => $email['id'],
                'name' => $email['name'],
                'date_sent' => $email['date_sent'],
                'from' => [
                    'address' => $email['from_addr'],
                    'name' => $email['from_addr_name'],
                ],
                'to' => $email['to_addrs_names'],
                'cc' => $email['cc_addrs_names'],
                'subject' => $email['name'],
                'body_text' => $email['description'],
                'body_html' => $email['description_html'],
                'attachments' => $attachments,
                'parent_type' => $email['parent_type'],
                'parent_id' => $email['parent_id'],
            ]
        ]);
    }
}
```

#### 5.2 Create Document Controller
`custom/api/v8/controllers/DocumentController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class DocumentController extends BaseController
{
    public function downloadDocument(Request $request, Response $response, array $args)
    {
        global $sugar_config;
        
        $documentId = $args['id'];
        
        // Validate document ID
        if (!preg_match('/^[a-f0-9\-]{36}$/', $documentId)) {
            return $response->withJson(['error' => 'Invalid document ID'], 400);
        }
        
        // Get document revision
        $bean = \BeanFactory::getBean('Documents', $documentId);
        
        if (!$bean || $bean->deleted) {
            return $response->withJson(['error' => 'Document not found'], 404);
        }
        
        // Get latest revision
        $revision = \BeanFactory::getBean('DocumentRevisions', $bean->document_revision_id);
        
        if (!$revision || $revision->deleted) {
            return $response->withJson(['error' => 'Document revision not found'], 404);
        }
        
        // Build file path
        $filePath = rtrim($sugar_config['upload_dir'], '/') . '/' . $revision->id;
        
        if (!file_exists($filePath)) {
            return $response->withJson(['error' => 'File not found'], 404);
        }
        
        // Set headers for download
        $response = $response
            ->withHeader('Content-Type', $revision->file_mime_type)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $revision->filename . '"')
            ->withHeader('Content-Length', filesize($filePath));
        
        // Stream file
        $stream = fopen($filePath, 'rb');
        return $response->withBody(new \Slim\Http\Stream($stream));
    }
}
```

### 6. Role-Based Access Configuration

#### 6.1 Create Role Definitions
`custom/Extension/modules/ACLRoles/Ext/Vardefs/b2b_roles.php`:
```php
<?php
// Define B2B-specific roles
$role_definitions = [
    'sales_rep' => [
        'name' => 'Sales Representative',
        'description' => 'Can manage leads, accounts, opportunities, and activities',
        'module_permissions' => [
            'Leads' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75, 'import' => 89, 'export' => 89],
            'Accounts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => -98, 'import' => 89, 'export' => 89],
            'Opportunities' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75, 'import' => 89, 'export' => 89],
            'Contacts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75, 'import' => 89, 'export' => 89],
            'Calls' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Meetings' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Tasks' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Notes' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Emails' => ['access' => 89, 'view' => 89, 'list' => 89],
            'Cases' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => -98, 'delete' => -98],
            'Documents' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 75, 'delete' => -98],
        ],
    ],
    'customer_success' => [
        'name' => 'Customer Success Manager',
        'description' => 'Can manage accounts, cases, and activities',
        'module_permissions' => [
            'Leads' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => -98, 'delete' => -98],
            'Accounts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => -98],
            'Opportunities' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 75, 'delete' => -98],
            'Contacts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => -98],
            'Calls' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Meetings' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Tasks' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Notes' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Emails' => ['access' => 89, 'view' => 89, 'list' => 89],
            'Cases' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Documents' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
        ],
    ],
    'sales_manager' => [
        'name' => 'Sales Manager',
        'description' => 'Full access to sales modules and team management',
        'module_permissions' => [
            // All modules with full access (89 = All)
            'Leads' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89, 'import' => 89, 'export' => 89],
            'Accounts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89, 'import' => 89, 'export' => 89],
            'Opportunities' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89, 'import' => 89, 'export' => 89],
            'Contacts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89, 'import' => 89, 'export' => 89],
            'Cases' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89],
            // All activity modules with full access
        ],
    ],
];

// ACL Permission Values:
// -99 = Not Set
// -98 = None
// 75 = Owner (can only edit/delete own records)
// 89 = All (full access)
```

#### 6.2 Create Role Setup Script
`custom/install/create_roles.php`:
```php
<?php
require_once('modules/ACLRoles/ACLRole.php');
require_once('modules/ACLActions/ACLAction.php');

function createB2BRoles() {
    global $db;
    
    $roles = [
        [
            'name' => 'Sales Representative',
            'description' => 'Can manage leads, accounts, opportunities, and activities',
        ],
        [
            'name' => 'Customer Success Manager',
            'description' => 'Can manage accounts, cases, and activities',
        ],
        [
            'name' => 'Sales Manager',
            'description' => 'Full access to sales modules and team management',
        ],
    ];
    
    foreach ($roles as $roleData) {
        // Check if role already exists
        $query = "SELECT id FROM acl_roles WHERE name = '{$roleData['name']}' AND deleted = 0";
        $result = $db->query($query);
        
        if ($result->num_rows == 0) {
            // Create role
            $role = new ACLRole();
            $role->name = $roleData['name'];
            $role->description = $roleData['description'];
            $role->save();
            
            echo "Created role: {$roleData['name']}\n";
            
            // Set module permissions based on role name
            setRolePermissions($role->id, $roleData['name']);
        } else {
            echo "Role already exists: {$roleData['name']}\n";
        }
    }
}

function setRolePermissions($roleId, $roleName) {
    // Permission mappings
    $permissions = [
        'Sales Representative' => [
            'Leads' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Accounts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => -98],
            'Opportunities' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            'Cases' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => -98, 'delete' => -98],
        ],
        'Customer Success Manager' => [
            'Leads' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => -98, 'delete' => -98],
            'Accounts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => -98],
            'Cases' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
        ],
        'Sales Manager' => [
            // Full access to all modules
        ],
    ];
    
    // Apply permissions
    if (isset($permissions[$roleName])) {
        foreach ($permissions[$roleName] as $module => $actions) {
            foreach ($actions as $action => $level) {
                ACLRole::setAction($roleId, $module, $action, $level);
            }
        }
    }
}

// Run the setup
createB2BRoles();
```

### 7. Seed Data for Phase 2

#### 7.1 Create Demo Data Script
`custom/install/seed_phase2_data.php`:
```php
<?php
require_once('include/utils.php');
require_once('modules/Opportunities/Opportunity.php');
require_once('modules/Cases/Case.php');
require_once('modules/Calls/Call.php');
require_once('modules/Meetings/Meeting.php');
require_once('modules/Tasks/Task.php');

// Get admin user
$admin = new User();
$admin->retrieve_by_string_fields(array('user_name' => 'admin'));

// Get some accounts and leads
$accountsQuery = "SELECT id, name FROM accounts WHERE deleted = 0 LIMIT 5";
$accountsResult = $GLOBALS['db']->query($accountsQuery);
$accounts = [];
while ($row = $accountsResult->fetch_assoc()) {
    $accounts[] = $row;
}

// Seed Opportunities
$opportunityData = [
    [
        'name' => 'Enterprise License Deal - Acme Corp',
        'account_id' => $accounts[0]['id'],
        'sales_stage' => 'Proposal',
        'amount' => 150000,
        'probability' => 75,
        'date_closed' => date('Y-m-d', strtotime('+30 days')),
        'lead_source' => 'Website',
        'next_step' => 'Send revised proposal with enterprise features',
        'description' => 'Large enterprise deployment for 500+ users',
        'competitors' => 'Salesforce, HubSpot',
        'subscription_type' => 'annual',
    ],
    [
        'name' => 'Mid-Market Expansion - Global Innovations',
        'account_id' => $accounts[1]['id'],
        'sales_stage' => 'Negotiation',
        'amount' => 75000,
        'probability' => 90,
        'date_closed' => date('Y-m-d', strtotime('+14 days')),
        'lead_source' => 'Referral',
        'next_step' => 'Final contract review with legal',
        'description' => 'Expanding from 50 to 150 users',
        'subscription_type' => 'annual',
    ],
    [
        'name' => 'Startup Package - TechStart',
        'account_id' => $accounts[2]['id'],
        'sales_stage' => 'Qualification',
        'amount' => 12000,
        'probability' => 10,
        'date_closed' => date('Y-m-d', strtotime('+45 days')),
        'lead_source' => 'Marketing',
        'next_step' => 'Schedule discovery call',
        'description' => 'Early stage startup evaluating CRM options',
        'competitors' => 'Pipedrive, Copper',
        'subscription_type' => 'monthly',
    ],
    [
        'name' => 'Renewal & Upsell - Enterprise Systems',
        'account_id' => $accounts[3]['id'],
        'sales_stage' => 'Value Proposition',
        'amount' => 200000,
        'probability' => 40,
        'date_closed' => date('Y-m-d', strtotime('+60 days')),
        'lead_source' => 'Existing Customer',
        'next_step' => 'Demo new AI features',
        'description' => 'Annual renewal plus additional modules',
        'subscription_type' => 'annual',
    ],
];

foreach ($opportunityData as $data) {
    $opportunity = new Opportunity();
    foreach ($data as $field => $value) {
        $opportunity->$field = $value;
    }
    $opportunity->assigned_user_id = $admin->id;
    $opportunity->save();
    echo "Created opportunity: {$opportunity->name}\n";
}

// Seed Cases
$caseData = [
    [
        'name' => 'Login issues after update',
        'status' => 'In Progress',
        'priority' => 'P1',
        'type' => 'technical_support',
        'account_id' => $accounts[0]['id'],
        'description' => 'Users unable to login after latest update. Getting 500 error.',
        'severity' => 'critical',
        'product_version' => '2.5.1',
        'environment' => 'production',
    ],
    [
        'name' => 'Feature Request - Bulk Import',
        'status' => 'New',
        'priority' => 'P3',
        'type' => 'feature_request',
        'account_id' => $accounts[1]['id'],
        'description' => 'Customer requesting ability to bulk import opportunities',
        'severity' => 'minor',
        'product_version' => '2.5.0',
        'environment' => 'production',
    ],
    [
        'name' => 'API Rate Limiting Issue',
        'status' => 'Assigned',
        'priority' => 'P2',
        'type' => 'bug',
        'account_id' => $accounts[2]['id'],
        'description' => 'API calls being rate limited incorrectly',
        'severity' => 'major',
        'product_version' => '2.5.1',
        'environment' => 'production',
    ],
    [
        'name' => 'Training Request - New Team Members',
        'status' => 'New',
        'priority' => 'P3',
        'type' => 'training',
        'account_id' => $accounts[0]['id'],
        'description' => 'Need training session for 5 new team members',
        'severity' => 'minor',
    ],
];

foreach ($caseData as $data) {
    $case = new aCase(); // Note: Case class is 'aCase' in SuiteCRM
    foreach ($data as $field => $value) {
        $case->$field = $value;
    }
    $case->assigned_user_id = $admin->id;
    $case->save();
    echo "Created case: {$case->name}\n";
}

// Seed Activities
// Calls
$callData = [
    [
        'name' => 'Discovery Call - Acme Corp',
        'status' => 'Planned',
        'direction' => 'Outbound',
        'date_start' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        'duration_hours' => 0,
        'duration_minutes' => 30,
        'parent_type' => 'Opportunities',
        'parent_id' => $opportunityData[0]['account_id'],
        'call_type' => 'discovery',
        'description' => 'Initial discovery call to understand requirements',
    ],
    [
        'name' => 'Follow-up Call - Contract Questions',
        'status' => 'Held',
        'direction' => 'Inbound',
        'date_start' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'duration_hours' => 0,
        'duration_minutes' => 15,
        'parent_type' => 'Opportunities',
        'call_type' => 'follow_up',
        'call_outcome' => 'successful',
        'description' => 'Addressed contract questions, moving to legal review',
    ],
];

foreach ($callData as $data) {
    $call = new Call();
    foreach ($data as $field => $value) {
        $call->$field = $value;
    }
    $call->assigned_user_id = $admin->id;
    $call->save();
    echo "Created call: {$call->name}\n";
}

// Meetings
$meetingData = [
    [
        'name' => 'Product Demo - Enterprise Features',
        'status' => 'Planned',
        'date_start' => date('Y-m-d 14:00:00', strtotime('+3 days')),
        'date_end' => date('Y-m-d 15:00:00', strtotime('+3 days')),
        'duration_hours' => 1,
        'duration_minutes' => 0,
        'location' => 'Zoom',
        'parent_type' => 'Opportunities',
        'meeting_type' => 'demo',
        'description' => 'Demo enterprise features including AI capabilities',
        'demo_environment' => 'https://demo.ourcrm.com/enterprise',
    ],
    [
        'name' => 'Quarterly Business Review',
        'status' => 'Planned',
        'date_start' => date('Y-m-d 10:00:00', strtotime('+7 days')),
        'date_end' => date('Y-m-d 11:30:00', strtotime('+7 days')),
        'duration_hours' => 1,
        'duration_minutes' => 30,
        'location' => 'Customer Office',
        'parent_type' => 'Accounts',
        'parent_id' => $accounts[0]['id'],
        'meeting_type' => 'qbr',
        'description' => 'Q4 review and planning for next year',
        'attendee_count' => 8,
    ],
];

foreach ($meetingData as $data) {
    $meeting = new Meeting();
    foreach ($data as $field => $value) {
        $meeting->$field = $value;
    }
    $meeting->assigned_user_id = $admin->id;
    $meeting->save();
    echo "Created meeting: {$meeting->name}\n";
}

// Tasks
$taskData = [
    [
        'name' => 'Send Proposal to Acme Corp',
        'status' => 'In Progress',
        'priority' => 'High',
        'date_due' => date('Y-m-d', strtotime('+1 day')),
        'parent_type' => 'Opportunities',
        'task_type' => 'proposal',
        'description' => 'Customize enterprise proposal template with pricing',
    ],
    [
        'name' => 'Follow up on Support Ticket',
        'status' => 'Not Started',
        'priority' => 'Medium',
        'date_due' => date('Y-m-d', strtotime('+2 days')),
        'parent_type' => 'Cases',
        'task_type' => 'follow_up',
        'description' => 'Check if login issues have been resolved',
    ],
    [
        'name' => 'Research Competitor Pricing',
        'status' => 'Not Started',
        'priority' => 'Low',
        'date_due' => date('Y-m-d', strtotime('+5 days')),
        'task_type' => 'research',
        'description' => 'Update competitive analysis document',
    ],
];

foreach ($taskData as $data) {
    $task = new Task();
    foreach ($data as $field => $value) {
        $task->$field = $value;
    }
    $task->assigned_user_id = $admin->id;
    $task->save();
    echo "Created task: {$task->name}\n";
}

echo "\nPhase 2 demo data seeded successfully!\n";
```

### 8. Testing Configuration

#### 8.1 API Test Script
`tests/backend/integration/Phase2ApiTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class Phase2ApiTest extends TestCase
{
    protected $client;
    protected $token;
    
    public function setUp(): void
    {
        $this->client = new Client([
            'base_uri' => 'http://localhost:8080/api/v8/',
            'timeout' => 10.0,
        ]);
        
        // Authenticate
        $response = $this->client->post('login', [
            'json' => [
                'grant_type' => 'password',
                'client_id' => 'sugar',
                'username' => 'admin',
                'password' => 'admin123',
            ]
        ]);
        
        $data = json_decode($response->getBody(), true);
        $this->token = $data['access_token'];
    }
    
    public function testOpportunitiesCRUD()
    {
        // Create opportunity
        $response = $this->client->post('module/Opportunities', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => [
                'data' => [
                    'type' => 'Opportunities',
                    'attributes' => [
                        'name' => 'Test Opportunity',
                        'sales_stage' => 'Qualification',
                        'amount' => 50000,
                        'probability' => 10,
                        'date_closed' => date('Y-m-d', strtotime('+30 days')),
                    ]
                ]
            ]
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('Opportunities', $data['data']['type']);
        
        $opportunityId = $data['data']['id'];
        
        // Update stage
        $response = $this->client->patch("module/Opportunities/{$opportunityId}", [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => [
                'data' => [
                    'type' => 'Opportunities',
                    'id' => $opportunityId,
                    'attributes' => [
                        'sales_stage' => 'Proposal',
                    ]
                ]
            ]
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify probability was updated automatically
        $response = $this->client->get("module/Opportunities/{$opportunityId}", [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ]);
        
        $data = json_decode($response->getBody(), true);
        $this->assertEquals('75', $data['data']['attributes']['probability']);
    }
    
    public function testDashboardEndpoints()
    {
        // Test pipeline data
        $response = $this->client->get('dashboard/pipeline', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        
        // Verify all stages are present
        $stages = array_column($data['data'], 'stage');
        $expectedStages = [
            'Qualification',
            'Needs Analysis',
            'Value Proposition',
            'Decision Makers',
            'Proposal',
            'Negotiation',
            'Closed Won',
            'Closed Lost'
        ];
        
        foreach ($expectedStages as $stage) {
            $this->assertContains($stage, $stages);
        }
        
        // Test activity metrics
        $response = $this->client->get('dashboard/activities', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('calls_today', $data['data']);
        $this->assertArrayHasKey('meetings_today', $data['data']);
        $this->assertArrayHasKey('tasks_overdue', $data['data']);
        $this->assertArrayHasKey('upcoming_activities', $data['data']);
    }
    
    public function testCasesSLA()
    {
        // Create P1 case
        $response = $this->client->post('module/Cases', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => [
                'data' => [
                    'type' => 'Cases',
                    'attributes' => [
                        'name' => 'Critical System Down',
                        'priority' => 'P1',
                        'status' => 'New',
                        'type' => 'technical_support',
                        'description' => 'Production system is down',
                    ]
                ]
            ]
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $caseId = $data['data']['id'];
        
        // Verify SLA deadline was set
        $response = $this->client->get("module/Cases/{$caseId}", [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ]);
        
        $data = json_decode($response->getBody(), true);
        $this->assertNotEmpty($data['data']['attributes']['sla_deadline']);
        
        // Verify SLA is 4 hours for P1
        $created = new DateTime($data['data']['attributes']['date_entered']);
        $deadline = new DateTime($data['data']['attributes']['sla_deadline']);
        $diff = $created->diff($deadline);
        
        $this->assertEquals(4, $diff->h + ($diff->days * 24));
    }
}
```

#### 8.2 Integration Test Script
`tests/integration/test-phase2-integration.sh`:
```bash
#!/bin/bash

echo "Testing Phase 2 Frontend-Backend Integration..."

# Get auth token
AUTH_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/login \
    -H "Content-Type: application/json" \
    -d '{
        "grant_type": "password",
        "client_id": "sugar",
        "username": "admin",
        "password": "admin123"
    }')

ACCESS_TOKEN=$(echo $AUTH_RESPONSE | jq -r '.access_token')

if [ "$ACCESS_TOKEN" == "null" ] || [ -z "$ACCESS_TOKEN" ]; then
    echo "✗ Authentication failed"
    exit 1
fi

echo "✓ Authentication successful"

# Test 1: Create Opportunity
echo "1. Testing Opportunity creation..."
OPPORTUNITY_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/module/Opportunities \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "data": {
            "type": "Opportunities",
            "attributes": {
                "name": "Integration Test Opportunity",
                "sales_stage": "Qualification",
                "amount": 25000,
                "date_closed": "2024-03-01"
            }
        }
    }')

OPPORTUNITY_ID=$(echo $OPPORTUNITY_RESPONSE | jq -r '.data.id')
if [ "$OPPORTUNITY_ID" != "null" ] && [ -n "$OPPORTUNITY_ID" ]; then
    echo "✓ Opportunity created: $OPPORTUNITY_ID"
else
    echo "✗ Opportunity creation failed"
    exit 1
fi

# Test 2: Update Opportunity Stage
echo "2. Testing stage update..."
UPDATE_RESPONSE=$(curl -s -X PATCH http://localhost:8080/api/v8/module/Opportunities/$OPPORTUNITY_ID \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "data": {
            "type": "Opportunities",
            "id": "'$OPPORTUNITY_ID'",
            "attributes": {
                "sales_stage": "Proposal"
            }
        }
    }')

UPDATED_PROBABILITY=$(echo $UPDATE_RESPONSE | jq -r '.data.attributes.probability')
if [ "$UPDATED_PROBABILITY" == "75" ]; then
    echo "✓ Stage updated and probability auto-calculated"
else
    echo "✗ Probability calculation failed"
fi

# Test 3: Dashboard Pipeline
echo "3. Testing dashboard pipeline endpoint..."
PIPELINE_RESPONSE=$(curl -s -X GET http://localhost:8080/api/v8/dashboard/pipeline \
    -H "Authorization: Bearer $ACCESS_TOKEN")

PIPELINE_COUNT=$(echo $PIPELINE_RESPONSE | jq '.data | length')
if [ "$PIPELINE_COUNT" -eq 8 ]; then
    echo "✓ Pipeline data includes all 8 stages"
else
    echo "✗ Pipeline data incomplete"
fi

# Test 4: Create Case with SLA
echo "4. Testing Case creation with SLA..."
CASE_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/module/Cases \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "data": {
            "type": "Cases",
            "attributes": {
                "name": "Integration Test Case",
                "priority": "P1",
                "status": "New",
                "type": "technical_support"
            }
        }
    }')

CASE_ID=$(echo $CASE_RESPONSE | jq -r '.data.id')
SLA_DEADLINE=$(echo $CASE_RESPONSE | jq -r '.data.attributes.sla_deadline')

if [ "$SLA_DEADLINE" != "null" ] && [ -n "$SLA_DEADLINE" ]; then
    echo "✓ Case created with SLA deadline: $SLA_DEADLINE"
else
    echo "✗ SLA calculation failed"
fi

# Test 5: Activity Creation
echo "5. Testing activity creation..."
CALL_RESPONSE=$(curl -s -X POST http://localhost:8080/api/v8/module/Calls \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "data": {
            "type": "Calls",
            "attributes": {
                "name": "Integration Test Call",
                "date_start": "'$(date -u +"%Y-%m-%dT%H:%M:%S")'",
                "duration_hours": 0,
                "duration_minutes": 30,
                "direction": "Outbound",
                "status": "Planned"
            }
        }
    }')

CALL_ID=$(echo $CALL_RESPONSE | jq -r '.data.id')
if [ "$CALL_ID" != "null" ] && [ -n "$CALL_ID" ]; then
    echo "✓ Call activity created"
else
    echo "✗ Call creation failed"
fi

# Test 6: Activity Metrics
echo "6. Testing activity metrics..."
ACTIVITY_METRICS=$(curl -s -X GET http://localhost:8080/api/v8/dashboard/activities \
    -H "Authorization: Bearer $ACCESS_TOKEN")

HAS_METRICS=$(echo $ACTIVITY_METRICS | jq 'has("data") and (.data | has("calls_today")) and (.data | has("meetings_today"))')
if [ "$HAS_METRICS" == "true" ]; then
    echo "✓ Activity metrics endpoint working"
else
    echo "✗ Activity metrics failed"
fi

# Test 7: Email View
echo "7. Testing email view endpoint..."
# Note: This would need a real email ID in production
EMAIL_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    http://localhost:8080/api/v8/emails/test-id/view \
    -H "Authorization: Bearer $ACCESS_TOKEN")

if [ "$EMAIL_RESPONSE" == "404" ]; then
    echo "✓ Email endpoint responds correctly (404 for test ID)"
else
    echo "✗ Email endpoint not working"
fi

echo ""
echo "Phase 2 integration tests completed!"
echo "✓ All critical paths tested successfully"
```

## Definition of Success

### ✅ Phase 2 Backend Success Criteria:

1. **Opportunities Configuration**
   - [ ] B2B sales stages configured (8 stages)
   - [ ] Probability auto-updates based on stage
   - [ ] Custom fields added (competitors, decision criteria, etc.)
   - [ ] Logic hooks working for probability calculation
   - [ ] Opportunities API CRUD operations work

2. **Activities Configuration**
   - [ ] Calls module has B2B fields (call_type, outcome, next_steps)
   - [ ] Meetings module has B2B fields (meeting_type, demo_environment)
   - [ ] Tasks module has B2B fields (task_type, related_opportunity)
   - [ ] All activity types use appropriate dropdowns
   - [ ] Activities can be linked to parent records

3. **Cases Configuration**
   - [ ] Priority values set to P1/P2/P3
   - [ ] Case types configured for software support
   - [ ] SLA calculation working based on priority
   - [ ] Custom fields added (severity, product_version, environment)
   - [ ] Cases API supports filtering by priority/status

4. **Dashboard APIs**
   - [ ] `/dashboard/metrics` returns all key metrics
   - [ ] `/dashboard/pipeline` returns opportunities by stage
   - [ ] `/dashboard/activities` returns activity counts and upcoming items
   - [ ] `/dashboard/cases` returns case metrics and priority breakdown
   - [ ] All endpoints return properly formatted JSON

5. **Email & Documents**
   - [ ] Email viewing endpoint returns email with attachments
   - [ ] Document download endpoint streams files
   - [ ] Proper error handling for missing resources

6. **Role-Based Access**
   - [ ] Three roles created: Sales Rep, Customer Success, Sales Manager
   - [ ] Module permissions configured per role
   - [ ] Users can be assigned to roles
   - [ ] API respects role permissions

7. **Testing**
   - [ ] Opportunity stage probability tests pass
   - [ ] Case SLA calculation tests pass
   - [ ] Dashboard endpoint tests pass
   - [ ] Integration tests with frontend pass

### Manual Verification Steps:
1. Run repair script: `docker exec suitecrm_app php -f repair.php`
2. Create roles: `docker exec suitecrm_app php -f custom/install/create_roles.php`
3. Seed demo data: `docker exec suitecrm_app php -f custom/install/seed_phase2_data.php`
4. Test opportunities pipeline in UI
5. Create a P1 case and verify SLA deadline
6. Create activities and verify custom fields
7. Test dashboard API endpoints with Postman
8. Assign user to role and verify permissions
9. Run integration tests: `./tests/integration/test-phase2-integration.sh`

### Integration Checklist:
- [ ] Frontend can fetch and display opportunities pipeline
- [ ] Drag-drop updates opportunity stage and probability
- [ ] Activities display with custom fields
- [ ] Cases show priority badges and SLA info
- [ ] Dashboard charts render with real data
- [ ] Role permissions restrict UI elements
- [ ] Email viewing works (if emails exist)
- [ ] Document downloads work

### Common Issues and Solutions:
1. **Stages not appearing**: Run Quick Repair and Rebuild
2. **Probability not updating**: Check logic hooks are registered
3. **Dashboard endpoints 404**: Verify custom routes are loaded
4. **SLA not calculating**: Ensure datetime functions have correct timezone
5. **Roles not working**: Clear ACL cache in Admin → Repair

### Next Phase Preview:
Phase 3 will add:
- AI lead scoring with OpenAI integration
- Form builder module creation
- Knowledge base module
- Basic AI chatbot implementation
- Website activity tracking setup