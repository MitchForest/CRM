# Phase 4 - Backend Implementation Guide

## Overview
Phase 4 completes the backend by implementing customer health scoring, advanced chatbot features with meeting scheduling, activity-based alerts, comprehensive demo data, and public endpoints for the marketing website. This phase focuses on integration and preparing for production deployment following the 90/30 approach.

## Prerequisites
- Phases 1-3 backend completed
- OpenAI integration working
- Custom tables created
- Docker environment stable
- Redis configured for real-time features

## Step-by-Step Implementation

### 1. Customer Health Scoring System

#### 1.1 Create Health Score Service
`custom/services/HealthScoreService.php`:
```php
<?php
namespace Custom\Services;

use Exception;

class HealthScoreService
{
    private $factors = [
        'login_frequency' => 0.20,
        'feature_usage' => 0.20,
        'support_tickets' => 0.15,
        'user_growth' => 0.15,
        'contract_value' => 0.15,
        'engagement_trend' => 0.15,
    ];
    
    /**
     * Calculate health score for an account
     */
    public function calculateHealthScore($accountId)
    {
        global $db;
        
        // Get account data
        $account = \BeanFactory::getBean('Accounts', $accountId);
        if (!$account || $account->deleted) {
            throw new Exception('Account not found');
        }
        
        // Calculate individual factors
        $factors = [
            'login_frequency' => $this->calculateLoginFrequency($accountId),
            'feature_usage' => $this->calculateFeatureUsage($accountId),
            'support_tickets' => $this->calculateSupportTickets($accountId),
            'user_growth' => $this->calculateUserGrowth($accountId),
            'contract_value' => $this->calculateContractValue($account),
            'engagement_trend' => $this->calculateEngagementTrend($accountId),
        ];
        
        // Calculate weighted score
        $score = 0;
        foreach ($factors as $factor => $value) {
            $score += $value * $this->factors[$factor];
        }
        
        // Determine risk level and churn probability
        $riskLevel = $this->determineRiskLevel($score);
        $churnProbability = $this->calculateChurnProbability($score, $factors);
        
        // Get AI recommendations
        $recommendations = $this->getAIRecommendations($account, $factors, $score);
        
        // Save to database
        $this->saveHealthScore($accountId, $score, $factors, $riskLevel, $churnProbability, $recommendations);
        
        return [
            'account_id' => $accountId,
            'score' => round($score),
            'factors' => $factors,
            'risk_level' => $riskLevel,
            'churn_probability' => $churnProbability,
            'recommendations' => $recommendations,
            'calculated_at' => gmdate('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Calculate login frequency score (0-20)
     */
    private function calculateLoginFrequency($accountId)
    {
        global $db;
        
        // Get login data for last 30 days
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        
        $query = "SELECT COUNT(DISTINCT u.id) as active_users,
                 COUNT(DISTINCT DATE(l.date_entered)) as active_days
                 FROM users u
                 JOIN tracker l ON u.id = l.user_id
                 WHERE u.deleted = 0
                 AND l.action = 'login'
                 AND l.date_entered >= '$thirtyDaysAgo'
                 AND EXISTS (
                     SELECT 1 FROM accounts_contacts ac
                     JOIN contacts c ON ac.contact_id = c.id
                     WHERE ac.account_id = '$accountId'
                     AND c.deleted = 0
                     AND c.email = u.email
                 )";
        
        $result = $db->query($query);
        $data = $db->fetchByAssoc($result);
        
        // Score based on active days (max 20 for daily usage)
        $activeDays = $data['active_days'] ?? 0;
        return min(20, ($activeDays / 30) * 20);
    }
    
    /**
     * Calculate feature usage score (0-20)
     */
    private function calculateFeatureUsage($accountId)
    {
        global $db;
        
        // Check various feature usage
        $features = [
            'opportunities' => "SELECT COUNT(*) as count FROM opportunities WHERE account_id = '$accountId' AND deleted = 0 AND date_entered >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'cases' => "SELECT COUNT(*) as count FROM cases WHERE account_id = '$accountId' AND deleted = 0 AND date_entered >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'activities' => "SELECT COUNT(*) as count FROM calls WHERE parent_type = 'Accounts' AND parent_id = '$accountId' AND deleted = 0 AND date_entered >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'forms' => "SELECT COUNT(*) as count FROM form_submissions fs JOIN leads l ON fs.lead_id = l.id WHERE l.account_name = (SELECT name FROM accounts WHERE id = '$accountId') AND fs.date_submitted >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        ];
        
        $usageScore = 0;
        foreach ($features as $feature => $sql) {
            $result = $db->query($sql);
            $row = $db->fetchByAssoc($result);
            if ($row['count'] > 0) {
                $usageScore += 5; // 5 points per feature used
            }
        }
        
        return min(20, $usageScore);
    }
    
    /**
     * Calculate support tickets score (0-20)
     */
    private function calculateSupportTickets($accountId)
    {
        global $db;
        
        // Get ticket metrics
        $query = "SELECT 
                 COUNT(*) as total_tickets,
                 SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_tickets,
                 SUM(CASE WHEN priority = 'P1' THEN 1 ELSE 0 END) as critical_tickets,
                 AVG(CASE WHEN status = 'Closed' THEN TIMESTAMPDIFF(HOUR, date_entered, date_modified) END) as avg_resolution_time
                 FROM cases
                 WHERE account_id = '$accountId'
                 AND deleted = 0
                 AND date_entered >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        
        $result = $db->query($query);
        $data = $db->fetchByAssoc($result);
        
        $totalTickets = $data['total_tickets'] ?? 0;
        $criticalTickets = $data['critical_tickets'] ?? 0;
        $avgResolutionTime = $data['avg_resolution_time'] ?? 0;
        
        // Lower tickets = higher score
        $score = 20;
        if ($totalTickets > 10) $score -= 5;
        if ($totalTickets > 20) $score -= 5;
        if ($criticalTickets > 2) $score -= 5;
        if ($avgResolutionTime > 48) $score -= 5; // Over 48 hours average
        
        return max(0, $score);
    }
    
    /**
     * Calculate user growth score (0-20)
     */
    private function calculateUserGrowth($accountId)
    {
        global $db;
        
        // Get user count trend
        $currentQuery = "SELECT COUNT(DISTINCT c.id) as current_users
                        FROM contacts c
                        JOIN accounts_contacts ac ON c.id = ac.contact_id
                        WHERE ac.account_id = '$accountId'
                        AND c.deleted = 0
                        AND ac.deleted = 0";
        
        $previousQuery = "SELECT COUNT(DISTINCT c.id) as previous_users
                         FROM contacts c
                         JOIN accounts_contacts ac ON c.id = ac.contact_id
                         WHERE ac.account_id = '$accountId'
                         AND c.deleted = 0
                         AND ac.deleted = 0
                         AND c.date_entered < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        
        $currentResult = $db->query($currentQuery);
        $currentData = $db->fetchByAssoc($currentResult);
        $currentUsers = $currentData['current_users'] ?? 0;
        
        $previousResult = $db->query($previousQuery);
        $previousData = $db->fetchByAssoc($previousResult);
        $previousUsers = $previousData['previous_users'] ?? 0;
        
        // Calculate growth rate
        if ($previousUsers > 0) {
            $growthRate = (($currentUsers - $previousUsers) / $previousUsers) * 100;
        } else {
            $growthRate = $currentUsers > 0 ? 100 : 0;
        }
        
        // Score based on growth
        if ($growthRate >= 20) return 20;
        if ($growthRate >= 10) return 15;
        if ($growthRate >= 0) return 10;
        if ($growthRate >= -10) return 5;
        return 0;
    }
    
    /**
     * Calculate contract value score (0-20)
     */
    private function calculateContractValue($account)
    {
        // Get MRR
        $mrr = $account->mrr ?? 0;
        
        // Score based on MRR tiers
        if ($mrr >= 10000) return 20;
        if ($mrr >= 5000) return 15;
        if ($mrr >= 2000) return 10;
        if ($mrr >= 500) return 5;
        return 0;
    }
    
    /**
     * Calculate engagement trend score (0-20)
     */
    private function calculateEngagementTrend($accountId)
    {
        global $db;
        
        // Compare current month vs previous month activity
        $currentMonthQuery = "SELECT COUNT(*) as activities
                             FROM (
                                 SELECT id FROM calls WHERE parent_type = 'Accounts' AND parent_id = '$accountId' AND deleted = 0 AND MONTH(date_entered) = MONTH(NOW())
                                 UNION ALL
                                 SELECT id FROM meetings WHERE parent_type = 'Accounts' AND parent_id = '$accountId' AND deleted = 0 AND MONTH(date_entered) = MONTH(NOW())
                                 UNION ALL
                                 SELECT id FROM emails WHERE parent_type = 'Accounts' AND parent_id = '$accountId' AND deleted = 0 AND MONTH(date_entered) = MONTH(NOW())
                             ) as activities";
        
        $previousMonthQuery = str_replace('MONTH(NOW())', 'MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))', $currentMonthQuery);
        
        $currentResult = $db->query($currentMonthQuery);
        $currentData = $db->fetchByAssoc($currentResult);
        $currentActivities = $currentData['activities'] ?? 0;
        
        $previousResult = $db->query($previousMonthQuery);
        $previousData = $db->fetchByAssoc($previousResult);
        $previousActivities = $previousData['activities'] ?? 0;
        
        // Score based on trend
        if ($previousActivities > 0) {
            $trend = (($currentActivities - $previousActivities) / $previousActivities) * 100;
            if ($trend >= 20) return 20;
            if ($trend >= 0) return 15;
            if ($trend >= -20) return 10;
            return 5;
        }
        
        return $currentActivities > 0 ? 10 : 0;
    }
    
    /**
     * Determine risk level based on score
     */
    private function determineRiskLevel($score)
    {
        if ($score >= 80) return 'low';
        if ($score >= 60) return 'medium';
        return 'high';
    }
    
    /**
     * Calculate churn probability
     */
    private function calculateChurnProbability($score, $factors)
    {
        // Simple formula - in production, use ML model
        $baseChurn = (100 - $score) / 100;
        
        // Adjust based on critical factors
        if ($factors['login_frequency'] < 5) $baseChurn += 0.1;
        if ($factors['support_tickets'] < 5) $baseChurn += 0.1;
        if ($factors['contract_value'] < 5) $baseChurn += 0.1;
        
        return min(1, max(0, $baseChurn));
    }
    
    /**
     * Get AI recommendations using OpenAI
     */
    private function getAIRecommendations($account, $factors, $score)
    {
        try {
            $aiService = new OpenAIService();
            
            $prompt = sprintf(
                "Analyze this customer account and provide 3-5 specific recommendations to improve retention:\n" .
                "Account: %s\n" .
                "Industry: %s\n" .
                "MRR: $%s\n" .
                "Health Score: %d/100\n" .
                "Factors:\n" .
                "- Login Frequency: %d/20\n" .
                "- Feature Usage: %d/20\n" .
                "- Support Tickets: %d/20\n" .
                "- User Growth: %d/20\n" .
                "- Contract Value: %d/20\n" .
                "- Engagement Trend: %d/20\n\n" .
                "Provide actionable recommendations as a JSON array of strings.",
                $account->name,
                $account->industry ?? 'Unknown',
                number_format($account->mrr ?? 0),
                $score,
                $factors['login_frequency'],
                $factors['feature_usage'],
                $factors['support_tickets'],
                $factors['user_growth'],
                $factors['contract_value'],
                $factors['engagement_trend']
            );
            
            $response = $aiService->getCompletion($prompt, 0.3, 500);
            $recommendations = json_decode($response, true);
            
            if (!is_array($recommendations)) {
                throw new Exception('Invalid AI response');
            }
            
            return $recommendations;
            
        } catch (Exception $e) {
            // Fallback recommendations
            $fallback = [];
            
            if ($factors['login_frequency'] < 10) {
                $fallback[] = 'Schedule regular check-ins to increase platform engagement';
            }
            if ($factors['feature_usage'] < 10) {
                $fallback[] = 'Provide training on underutilized features to increase adoption';
            }
            if ($factors['support_tickets'] < 10) {
                $fallback[] = 'Proactively address support issues to improve satisfaction';
            }
            if ($factors['user_growth'] < 10) {
                $fallback[] = 'Identify and onboard additional team members to expand usage';
            }
            
            return $fallback;
        }
    }
    
    /**
     * Save health score to database
     */
    private function saveHealthScore($accountId, $score, $factors, $riskLevel, $churnProbability, $recommendations)
    {
        global $db;
        
        $id = create_guid();
        
        $query = "INSERT INTO customer_health_scores 
                 (id, account_id, score, factors, risk_level, churn_probability, recommendations, calculated_at)
                 VALUES (
                    '$id',
                    '$accountId',
                    $score,
                    '" . json_encode($factors) . "',
                    '$riskLevel',
                    $churnProbability,
                    '" . json_encode($recommendations) . "',
                    NOW()
                 )";
        
        $db->query($query);
        
        // Update account with latest score
        $updateQuery = "UPDATE accounts SET health_score = $score WHERE id = '$accountId'";
        $db->query($updateQuery);
        
        // Create alert if high risk
        if ($riskLevel === 'high') {
            $this->createHealthAlert($accountId, $score, $churnProbability);
        }
    }
    
    /**
     * Create health alert
     */
    private function createHealthAlert($accountId, $score, $churnProbability)
    {
        $alertService = new AlertService();
        
        $account = \BeanFactory::getBean('Accounts', $accountId);
        
        $alertService->createAlert([
            'type' => 'health_risk',
            'severity' => 'critical',
            'title' => "High churn risk: {$account->name}",
            'message' => sprintf(
                'Health score dropped to %d with %d%% churn probability. Immediate action recommended.',
                $score,
                round($churnProbability * 100)
            ),
            'data' => [
                'account_id' => $accountId,
                'score' => $score,
                'churn_probability' => $churnProbability,
            ],
        ]);
    }
    
    /**
     * Get health score trends
     */
    public function getHealthScoreTrends($accountId, $days = 90)
    {
        global $db;
        
        $startDate = date('Y-m-d', strtotime("-$days days"));
        
        $query = "SELECT DATE(calculated_at) as date, score, risk_level
                 FROM customer_health_scores
                 WHERE account_id = '$accountId'
                 AND calculated_at >= '$startDate'
                 ORDER BY calculated_at ASC";
        
        $result = $db->query($query);
        $trends = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $trends[] = [
                'date' => $row['date'],
                'score' => (int)$row['score'],
                'risk_level' => $row['risk_level'],
            ];
        }
        
        return $trends;
    }
    
    /**
     * Get at-risk accounts
     */
    public function getAtRiskAccounts($limit = 10)
    {
        global $db;
        
        $query = "SELECT a.id, a.name, a.mrr, h.score, h.risk_level, h.churn_probability
                 FROM accounts a
                 JOIN customer_health_scores h ON a.id = h.account_id
                 WHERE h.calculated_at = (
                     SELECT MAX(calculated_at) 
                     FROM customer_health_scores 
                     WHERE account_id = a.id
                 )
                 AND h.risk_level IN ('high', 'medium')
                 AND a.deleted = 0
                 ORDER BY h.churn_probability DESC
                 LIMIT $limit";
        
        $result = $db->query($query);
        $accounts = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $accounts[] = $row;
        }
        
        return $accounts;
    }
}
```

#### 1.2 Create Health Score Controller
`custom/api/v8/controllers/HealthScoreController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;
use Custom\Services\HealthScoreService;

class HealthScoreController extends BaseController
{
    private $healthService;
    
    public function __construct()
    {
        $this->healthService = new HealthScoreService();
    }
    
    /**
     * Calculate health score for account
     */
    public function calculateHealthScore(Request $request, Response $response, array $args)
    {
        try {
            $accountId = $args['id'];
            
            $score = $this->healthService->calculateHealthScore($accountId);
            
            return $response->withJson($score);
            
        } catch (\Exception $e) {
            return $response->withJson(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get health score for account
     */
    public function getHealthScore(Request $request, Response $response, array $args)
    {
        global $db;
        
        $accountId = $args['id'];
        
        // Get latest score
        $query = "SELECT * FROM customer_health_scores
                 WHERE account_id = '$accountId'
                 ORDER BY calculated_at DESC
                 LIMIT 1";
        
        $result = $db->query($query);
        $score = $db->fetchByAssoc($result);
        
        if (!$score) {
            // Calculate if not exists
            try {
                $score = $this->healthService->calculateHealthScore($accountId);
            } catch (\Exception $e) {
                return $response->withJson(['error' => 'Unable to calculate score'], 500);
            }
        } else {
            $score['factors'] = json_decode($score['factors'], true);
            $score['recommendations'] = json_decode($score['recommendations'], true);
        }
        
        return $response->withJson($score);
    }
    
    /**
     * Get health score trends
     */
    public function getHealthTrends(Request $request, Response $response, array $args)
    {
        $accountId = $args['id'];
        $days = $request->getQueryParam('days', 90);
        
        $trends = $this->healthService->getHealthScoreTrends($accountId, $days);
        
        return $response->withJson($trends);
    }
    
    /**
     * Get at-risk accounts
     */
    public function getAtRiskAccounts(Request $request, Response $response, array $args)
    {
        $limit = $request->getQueryParam('limit', 10);
        
        $accounts = $this->healthService->getAtRiskAccounts($limit);
        
        return $response->withJson($accounts);
    }
    
    /**
     * Update health factors manually
     */
    public function updateHealthFactors(Request $request, Response $response, array $args)
    {
        // This would be used for manual adjustments
        // Implementation depends on business requirements
        
        return $response->withJson(['message' => 'Not implemented']);
    }
}
```

### 2. Advanced Chatbot Features

#### 2.1 Create Meeting Scheduler Service
`custom/services/MeetingSchedulerService.php`:
```php
<?php
namespace Custom\Services;

use Exception;

class MeetingSchedulerService
{
    /**
     * Get available meeting slots
     */
    public function getAvailableSlots($userId, $startDate, $endDate)
    {
        global $db;
        
        // Business hours configuration
        $businessHours = [
            'start' => 9,  // 9 AM
            'end' => 17,   // 5 PM
            'duration' => 30, // 30 minute slots
            'buffer' => 15, // 15 minute buffer between meetings
        ];
        
        // Get existing meetings
        $query = "SELECT date_start, date_end 
                 FROM meetings 
                 WHERE assigned_user_id = '$userId'
                 AND deleted = 0
                 AND date_start BETWEEN '$startDate' AND '$endDate'
                 ORDER BY date_start";
        
        $result = $db->query($query);
        $existingMeetings = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $existingMeetings[] = [
                'start' => $row['date_start'],
                'end' => $row['date_end'],
            ];
        }
        
        // Generate available slots
        $slots = [];
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        
        while ($current <= $end) {
            // Skip weekends
            if ($current->format('N') >= 6) {
                $current->modify('+1 day');
                continue;
            }
            
            // Generate slots for the day
            $dayStart = clone $current;
            $dayStart->setTime($businessHours['start'], 0);
            
            $dayEnd = clone $current;
            $dayEnd->setTime($businessHours['end'], 0);
            
            $slotStart = clone $dayStart;
            
            while ($slotStart < $dayEnd) {
                $slotEnd = clone $slotStart;
                $slotEnd->modify("+{$businessHours['duration']} minutes");
                
                // Check if slot is available
                $available = true;
                foreach ($existingMeetings as $meeting) {
                    $meetingStart = new \DateTime($meeting['start']);
                    $meetingEnd = new \DateTime($meeting['end']);
                    
                    // Add buffer time
                    $meetingStart->modify("-{$businessHours['buffer']} minutes");
                    $meetingEnd->modify("+{$businessHours['buffer']} minutes");
                    
                    if ($slotStart < $meetingEnd && $slotEnd > $meetingStart) {
                        $available = false;
                        break;
                    }
                }
                
                $slots[] = [
                    'start' => $slotStart->format('Y-m-d H:i:s'),
                    'end' => $slotEnd->format('Y-m-d H:i:s'),
                    'available' => $available,
                ];
                
                $slotStart->modify("+{$businessHours['duration']} minutes");
            }
            
            $current->modify('+1 day');
        }
        
        return $slots;
    }
    
    /**
     * Schedule a meeting
     */
    public function scheduleMeeting($data)
    {
        // Create meeting
        $meeting = \BeanFactory::newBean('Meetings');
        
        $meeting->name = $data['title'] ?? 'Meeting scheduled via chatbot';
        $meeting->date_start = $data['date_start'];
        $meeting->date_end = $data['date_end'];
        $meeting->duration_hours = 0;
        $meeting->duration_minutes = $data['duration'] ?? 30;
        $meeting->location = $data['location'] ?? 'Video Call';
        $meeting->description = $data['description'] ?? '';
        $meeting->status = 'Planned';
        $meeting->meeting_type = $data['type'] ?? 'demo';
        
        // Assign to user
        if (!empty($data['assigned_user_id'])) {
            $meeting->assigned_user_id = $data['assigned_user_id'];
        }
        
        // Link to parent record
        if (!empty($data['parent_type']) && !empty($data['parent_id'])) {
            $meeting->parent_type = $data['parent_type'];
            $meeting->parent_id = $data['parent_id'];
        }
        
        $meeting->save();
        
        // Add invitees
        if (!empty($data['invitees'])) {
            foreach ($data['invitees'] as $invitee) {
                if ($invitee['type'] === 'Contact' && !empty($invitee['id'])) {
                    $meeting->load_relationship('contacts');
                    $meeting->contacts->add($invitee['id']);
                } elseif ($invitee['type'] === 'Lead' && !empty($invitee['id'])) {
                    $meeting->load_relationship('leads');
                    $meeting->leads->add($invitee['id']);
                }
            }
        }
        
        // Send calendar invite
        $this->sendCalendarInvite($meeting, $data);
        
        return $meeting->id;
    }
    
    /**
     * Send calendar invite
     */
    private function sendCalendarInvite($meeting, $data)
    {
        // Get attendee emails
        $attendees = [];
        
        if (!empty($data['invitees'])) {
            foreach ($data['invitees'] as $invitee) {
                if ($invitee['type'] === 'Contact') {
                    $contact = \BeanFactory::getBean('Contacts', $invitee['id']);
                    if ($contact && $contact->email1) {
                        $attendees[] = $contact->email1;
                    }
                } elseif ($invitee['type'] === 'Lead') {
                    $lead = \BeanFactory::getBean('Leads', $invitee['id']);
                    if ($lead && $lead->email1) {
                        $attendees[] = $lead->email1;
                    }
                }
            }
        }
        
        if (empty($attendees)) {
            return;
        }
        
        // Create iCal event
        $ical = $this->createICalEvent($meeting);
        
        // Send email with iCal attachment
        require_once('modules/Emails/Email.php');
        $email = new \Email();
        
        $email->to_addrs = implode(', ', $attendees);
        $email->type = 'out';
        $email->status = 'sent';
        $email->name = "Meeting Invitation: {$meeting->name}";
        $email->description_html = $this->createMeetingEmailBody($meeting);
        $email->from_addr = $GLOBALS['sugar_config']['notify_fromaddress'];
        $email->from_name = $GLOBALS['sugar_config']['notify_fromname'];
        
        // Attach iCal file
        $email->AddStringAttachment($ical, 'meeting.ics', 'base64', 'text/calendar');
        
        $email->send();
    }
    
    /**
     * Create iCal event
     */
    private function createICalEvent($meeting)
    {
        $uid = $meeting->id . '@' . $_SERVER['HTTP_HOST'];
        $dtstart = gmdate('Ymd\THis\Z', strtotime($meeting->date_start));
        $dtend = gmdate('Ymd\THis\Z', strtotime($meeting->date_end));
        $now = gmdate('Ymd\THis\Z');
        
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//AI CRM//Meeting//EN\r\n";
        $ical .= "METHOD:REQUEST\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:$uid\r\n";
        $ical .= "DTSTAMP:$now\r\n";
        $ical .= "DTSTART:$dtstart\r\n";
        $ical .= "DTEND:$dtend\r\n";
        $ical .= "SUMMARY:{$meeting->name}\r\n";
        $ical .= "DESCRIPTION:{$meeting->description}\r\n";
        $ical .= "LOCATION:{$meeting->location}\r\n";
        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "SEQUENCE:0\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";
        
        return $ical;
    }
    
    /**
     * Create meeting email body
     */
    private function createMeetingEmailBody($meeting)
    {
        $html = "<h2>Meeting Invitation</h2>";
        $html .= "<p>You have been invited to the following meeting:</p>";
        $html .= "<ul>";
        $html .= "<li><strong>Subject:</strong> {$meeting->name}</li>";
        $html .= "<li><strong>Date:</strong> " . date('F j, Y', strtotime($meeting->date_start)) . "</li>";
        $html .= "<li><strong>Time:</strong> " . date('g:i A', strtotime($meeting->date_start)) . " - " . date('g:i A', strtotime($meeting->date_end)) . "</li>";
        $html .= "<li><strong>Location:</strong> {$meeting->location}</li>";
        
        if ($meeting->location === 'Video Call') {
            $html .= "<li><strong>Join URL:</strong> <a href='https://meet.jit.si/AICRM-{$meeting->id}'>Click here to join</a></li>";
        }
        
        $html .= "</ul>";
        
        if (!empty($meeting->description)) {
            $html .= "<p><strong>Description:</strong></p>";
            $html .= "<p>{$meeting->description}</p>";
        }
        
        return $html;
    }
}
```

#### 2.2 Update AI Chat Controller
`custom/api/v8/controllers/AIController.php` (add methods):
```php
// Add these methods to existing AIController

/**
 * Get available meeting slots
 */
public function getAvailableSlots(Request $request, Response $response, array $args)
{
    try {
        $data = $request->getParsedBody();
        $userId = $data['user_id'] ?? $GLOBALS['current_user']->id;
        $startDate = $data['start_date'] ?? date('Y-m-d');
        $endDate = $data['end_date'] ?? date('Y-m-d', strtotime('+14 days'));
        
        $schedulerService = new \Custom\Services\MeetingSchedulerService();
        $slots = $schedulerService->getAvailableSlots($userId, $startDate, $endDate);
        
        return $response->withJson($slots);
        
    } catch (\Exception $e) {
        return $response->withJson(['error' => $e->getMessage()], 500);
    }
}

/**
 * Schedule meeting via chatbot
 */
public function scheduleMeeting(Request $request, Response $response, array $args)
{
    try {
        $data = $request->getParsedBody();
        
        // Validate required fields
        if (empty($data['date_start'])) {
            return $response->withJson(['error' => 'Meeting date required'], 400);
        }
        
        // Set defaults
        $data['duration'] = $data['duration'] ?? 30;
        $data['date_end'] = date('Y-m-d H:i:s', strtotime($data['date_start']) + ($data['duration'] * 60));
        
        // Find or create lead/contact from chat session
        if (!empty($data['session_id'])) {
            $sessionData = $this->getChatSessionData($data['session_id']);
            if ($sessionData['lead_id']) {
                $data['parent_type'] = 'Leads';
                $data['parent_id'] = $sessionData['lead_id'];
                $data['invitees'] = [
                    ['type' => 'Lead', 'id' => $sessionData['lead_id']]
                ];
            }
        }
        
        $schedulerService = new \Custom\Services\MeetingSchedulerService();
        $meetingId = $schedulerService->scheduleMeeting($data);
        
        return $response->withJson([
            'success' => true,
            'meeting_id' => $meetingId,
            'message' => 'Meeting scheduled successfully. You will receive a calendar invite shortly.',
        ]);
        
    } catch (\Exception $e) {
        return $response->withJson(['error' => $e->getMessage()], 500);
    }
}

/**
 * Enhanced chat method with meeting scheduling
 */
public function chatEnhanced(Request $request, Response $response, array $args)
{
    try {
        $data = $request->getParsedBody();
        $messages = $data['messages'] ?? [];
        $context = $data['context'] ?? [];
        
        // Check if user is asking about scheduling
        $lastMessage = end($messages)['content'] ?? '';
        $isSchedulingRequest = $this->detectSchedulingIntent($lastMessage);
        
        if ($isSchedulingRequest) {
            // Get available slots
            $schedulerService = new \Custom\Services\MeetingSchedulerService();
            $slots = $schedulerService->getAvailableSlots(
                $GLOBALS['current_user']->id,
                date('Y-m-d'),
                date('Y-m-d', strtotime('+7 days'))
            );
            
            // Format slots for response
            $availableSlots = array_filter($slots, function($slot) {
                return $slot['available'];
            });
            
            $slotsText = "I have the following slots available:\n\n";
            $count = 0;
            foreach ($availableSlots as $slot) {
                if ($count >= 5) break;
                $slotsText .= "• " . date('l, F j at g:i A', strtotime($slot['start'])) . "\n";
                $count++;
            }
            
            $response = [
                'content' => $slotsText . "\nWhich time works best for you?",
                'metadata' => [
                    'intent' => 'scheduling',
                    'available_slots' => array_slice($availableSlots, 0, 5),
                ],
            ];
            
            return $response->withJson($response);
        }
        
        // Regular chat processing
        $result = $this->aiService->chat($messages, $context);
        
        return $response->withJson($result);
        
    } catch (\Exception $e) {
        return $response->withJson(['error' => $e->getMessage()], 500);
    }
}

private function detectSchedulingIntent($message)
{
    $schedulingKeywords = [
        'schedule', 'meeting', 'demo', 'call', 'appointment',
        'available', 'calendar', 'book', 'time', 'slot'
    ];
    
    $messageLower = strtolower($message);
    foreach ($schedulingKeywords as $keyword) {
        if (strpos($messageLower, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

private function getChatSessionData($sessionId)
{
    global $db;
    
    $query = "SELECT * FROM ai_chat_sessions WHERE id = '$sessionId'";
    $result = $db->query($query);
    
    return $db->fetchByAssoc($result) ?: [];
}
```

### 3. Activity-Based Alerts System

#### 3.1 Create Alert Service
`custom/services/AlertService.php`:
```php
<?php
namespace Custom\Services;

use Exception;

class AlertService
{
    private $alertTypes = [
        'lead_score' => [
            'title' => 'High-Value Lead Alert',
            'template' => 'New lead %s scored %d - immediate follow-up recommended',
        ],
        'high_activity' => [
            'title' => 'High Website Activity',
            'template' => '%s is actively browsing high-value pages',
        ],
        'health_risk' => [
            'title' => 'Customer Health Alert',
            'template' => '%s health score dropped to %d - churn risk',
        ],
        'form_submission' => [
            'title' => 'New Form Submission',
            'template' => '%s submitted %s form',
        ],
        'chat_qualified' => [
            'title' => 'Chat Lead Qualified',
            'template' => 'Chat visitor qualified as lead: %s',
        ],
    ];
    
    /**
     * Create an alert
     */
    public function createAlert($data)
    {
        global $db, $current_user;
        
        $id = create_guid();
        $type = $data['type'];
        $severity = $data['severity'] ?? 'info';
        
        // Use template if not provided
        if (empty($data['title']) && isset($this->alertTypes[$type])) {
            $data['title'] = $this->alertTypes[$type]['title'];
        }
        
        // Set expiration (24 hours for most alerts)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        if ($severity === 'critical') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));
        }
        
        $query = "INSERT INTO alerts 
                 (id, type, severity, title, message, data, read_flag, created_at, expires_at, assigned_user_id)
                 VALUES (
                    '$id',
                    '{$db->quote($type)}',
                    '{$db->quote($severity)}',
                    '{$db->quote($data['title'])}',
                    '{$db->quote($data['message'])}',
                    '{$db->quote(json_encode($data['data'] ?? []))}',
                    0,
                    NOW(),
                    '$expiresAt',
                    '{$data['assigned_user_id'] ?? $current_user->id}'
                 )";
        
        $db->query($query);
        
        // Send real-time notification
        $this->sendRealtimeNotification($id, $data);
        
        // Check if email notification needed
        if ($severity === 'critical' || !empty($data['send_email'])) {
            $this->sendEmailNotification($data);
        }
        
        return $id;
    }
    
    /**
     * Send real-time notification via Redis
     */
    private function sendRealtimeNotification($alertId, $data)
    {
        try {
            $redis = new \Predis\Client([
                'scheme' => 'tcp',
                'host'   => 'redis',
                'port'   => 6379,
            ]);
            
            $notification = [
                'id' => $alertId,
                'type' => $data['type'],
                'severity' => $data['severity'] ?? 'info',
                'title' => $data['title'],
                'message' => $data['message'],
                'created_at' => gmdate('Y-m-d H:i:s'),
            ];
            
            // Publish to user channel
            $userId = $data['assigned_user_id'] ?? $GLOBALS['current_user']->id;
            $redis->publish("alerts:user:$userId", json_encode($notification));
            
        } catch (Exception $e) {
            \LoggerManager::getLogger()->error('Redis notification failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification($data)
    {
        $userId = $data['assigned_user_id'] ?? $GLOBALS['current_user']->id;
        $user = \BeanFactory::getBean('Users', $userId);
        
        if (!$user || !$user->email1) {
            return;
        }
        
        require_once('modules/Emails/Email.php');
        $email = new \Email();
        
        $email->to_addrs = $user->email1;
        $email->type = 'out';
        $email->status = 'sent';
        $email->name = "[CRM Alert] {$data['title']}";
        $email->description_html = $this->createEmailBody($data);
        $email->from_addr = $GLOBALS['sugar_config']['notify_fromaddress'];
        $email->from_name = 'CRM Alerts';
        
        $email->send();
    }
    
    private function createEmailBody($data)
    {
        $severity = strtoupper($data['severity'] ?? 'INFO');
        $color = [
            'CRITICAL' => '#dc2626',
            'WARNING' => '#f59e0b',
            'INFO' => '#3b82f6',
        ][$severity] ?? '#6b7280';
        
        $html = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: {$color}; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">{$data['title']}</h2>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">Severity: {$severity}</p>
    </div>
    <div style="background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px; color: #374151; margin: 0 0 15px 0;">
            {$data['message']}
        </p>
        <a href="{$GLOBALS['sugar_config']['site_url']}/app/alerts" 
           style="display: inline-block; background: {$color}; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
            View in CRM
        </a>
    </div>
</div>
HTML;
        
        return $html;
    }
    
    /**
     * Get alerts for user
     */
    public function getAlerts($params = [])
    {
        global $db, $current_user;
        
        $userId = $params['user_id'] ?? $current_user->id;
        $unreadOnly = $params['unread'] ?? false;
        $type = $params['type'] ?? null;
        $severity = $params['severity'] ?? null;
        $limit = $params['limit'] ?? 20;
        
        $where = ["assigned_user_id = '$userId'"];
        $where[] = "(expires_at IS NULL OR expires_at > NOW())";
        
        if ($unreadOnly) {
            $where[] = "read_flag = 0";
        }
        
        if ($type) {
            $where[] = "type = '{$db->quote($type)}'";
        }
        
        if ($severity) {
            $where[] = "severity = '{$db->quote($severity)}'";
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = "SELECT * FROM alerts 
                 WHERE $whereClause
                 ORDER BY created_at DESC
                 LIMIT $limit";
        
        $result = $db->query($query);
        $alerts = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $row['data'] = json_decode($row['data'] ?? '{}', true);
            $row['read'] = (bool)$row['read_flag'];
            unset($row['read_flag']);
            $alerts[] = $row;
        }
        
        return $alerts;
    }
    
    /**
     * Mark alert as read
     */
    public function markAsRead($alertId)
    {
        global $db;
        
        $query = "UPDATE alerts SET read_flag = 1 WHERE id = '$alertId'";
        $db->query($query);
    }
    
    /**
     * Mark all alerts as read
     */
    public function markAllAsRead($userId = null)
    {
        global $db, $current_user;
        
        $userId = $userId ?? $current_user->id;
        
        $query = "UPDATE alerts SET read_flag = 1 WHERE assigned_user_id = '$userId' AND read_flag = 0";
        $db->query($query);
    }
    
    /**
     * Create alert rules
     */
    public function createAlertRule($data)
    {
        global $db, $current_user;
        
        $id = create_guid();
        
        $query = "INSERT INTO alert_rules 
                 (id, name, type, conditions, actions, enabled, created_by, date_created)
                 VALUES (
                    '$id',
                    '{$db->quote($data['name'])}',
                    '{$db->quote($data['type'])}',
                    '{$db->quote(json_encode($data['conditions']))}',
                    '{$db->quote(json_encode($data['actions']))}',
                    " . ($data['enabled'] ? 1 : 0) . ",
                    '{$current_user->id}',
                    NOW()
                 )";
        
        $db->query($query);
        
        return $id;
    }
    
    /**
     * Process alert rules
     */
    public function processAlertRules($event, $data)
    {
        global $db;
        
        // Get active rules for this event type
        $query = "SELECT * FROM alert_rules 
                 WHERE type = '{$db->quote($event)}'
                 AND enabled = 1";
        
        $result = $db->query($query);
        
        while ($rule = $db->fetchByAssoc($result)) {
            $conditions = json_decode($rule['conditions'], true);
            $actions = json_decode($rule['actions'], true);
            
            // Check if conditions are met
            if ($this->evaluateConditions($conditions, $data)) {
                // Execute actions
                $this->executeActions($actions, $data, $rule);
            }
        }
    }
    
    private function evaluateConditions($conditions, $data)
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            
            $fieldValue = $data[$field] ?? null;
            
            switch ($operator) {
                case 'equals':
                    if ($fieldValue != $value) return false;
                    break;
                case 'greater_than':
                    if ($fieldValue <= $value) return false;
                    break;
                case 'less_than':
                    if ($fieldValue >= $value) return false;
                    break;
                case 'contains':
                    if (strpos($fieldValue, $value) === false) return false;
                    break;
            }
        }
        
        return true;
    }
    
    private function executeActions($actions, $data, $rule)
    {
        foreach ($actions as $action) {
            switch ($action['type']) {
                case 'notification':
                    $this->createAlert([
                        'type' => $rule['type'],
                        'severity' => $action['config']['severity'] ?? 'info',
                        'title' => $action['config']['title'] ?? $rule['name'],
                        'message' => $this->parseTemplate($action['config']['message'], $data),
                        'data' => $data,
                        'assigned_user_id' => $action['config']['user_id'] ?? null,
                    ]);
                    break;
                    
                case 'email':
                    $this->sendEmailNotification([
                        'title' => $action['config']['subject'],
                        'message' => $this->parseTemplate($action['config']['body'], $data),
                        'assigned_user_id' => $action['config']['user_id'],
                    ]);
                    break;
                    
                case 'webhook':
                    $this->callWebhook($action['config']['url'], $data);
                    break;
            }
        }
    }
    
    private function parseTemplate($template, $data)
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{{$key}}}", $value, $template);
        }
        return $template;
    }
    
    private function callWebhook($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
```

#### 3.2 Create Alert Controller
`custom/api/v8/controllers/AlertController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;
use Custom\Services\AlertService;

class AlertController extends BaseController
{
    private $alertService;
    
    public function __construct()
    {
        $this->alertService = new AlertService();
    }
    
    /**
     * Get alerts
     */
    public function getAlerts(Request $request, Response $response, array $args)
    {
        $params = [
            'unread' => $request->getQueryParam('unread') === 'true',
            'type' => $request->getQueryParam('type'),
            'severity' => $request->getQueryParam('severity'),
            'limit' => $request->getQueryParam('limit', 20),
        ];
        
        $alerts = $this->alertService->getAlerts($params);
        
        return $response->withJson($alerts);
    }
    
    /**
     * Mark alert as read
     */
    public function markAsRead(Request $request, Response $response, array $args)
    {
        $alertId = $args['id'];
        
        $this->alertService->markAsRead($alertId);
        
        return $response->withJson(['success' => true]);
    }
    
    /**
     * Mark all as read
     */
    public function markAllAsRead(Request $request, Response $response, array $args)
    {
        $this->alertService->markAllAsRead();
        
        return $response->withJson(['success' => true]);
    }
    
    /**
     * Get alert rules
     */
    public function getAlertRules(Request $request, Response $response, array $args)
    {
        global $db;
        
        $query = "SELECT * FROM alert_rules ORDER BY name";
        $result = $db->query($query);
        $rules = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $row['conditions'] = json_decode($row['conditions'], true);
            $row['actions'] = json_decode($row['actions'], true);
            $row['enabled'] = (bool)$row['enabled'];
            $rules[] = $row;
        }
        
        return $response->withJson($rules);
    }
    
    /**
     * Create alert rule
     */
    public function createAlertRule(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();
        
        $ruleId = $this->alertService->createAlertRule($data);
        
        return $response->withJson([
            'id' => $ruleId,
            'success' => true,
        ], 201);
    }
    
    /**
     * Update alert rule
     */
    public function updateAlertRule(Request $request, Response $response, array $args)
    {
        global $db;
        
        $ruleId = $args['id'];
        $data = $request->getParsedBody();
        
        $updates = [];
        if (isset($data['name'])) $updates[] = "name = '{$db->quote($data['name'])}'";
        if (isset($data['conditions'])) $updates[] = "conditions = '{$db->quote(json_encode($data['conditions']))}'";
        if (isset($data['actions'])) $updates[] = "actions = '{$db->quote(json_encode($data['actions']))}'";
        if (isset($data['enabled'])) $updates[] = "enabled = " . ($data['enabled'] ? 1 : 0);
        
        if (!empty($updates)) {
            $query = "UPDATE alert_rules SET " . implode(', ', $updates) . " WHERE id = '$ruleId'";
            $db->query($query);
        }
        
        return $response->withJson(['success' => true]);
    }
    
    /**
     * Delete alert rule
     */
    public function deleteAlertRule(Request $request, Response $response, array $args)
    {
        global $db;
        
        $ruleId = $args['id'];
        
        $query = "DELETE FROM alert_rules WHERE id = '$ruleId'";
        $db->query($query);
        
        return $response->withStatus(204);
    }
    
    /**
     * Test alert rule
     */
    public function testAlertRule(Request $request, Response $response, array $args)
    {
        $rule = $request->getParsedBody();
        
        // Create test data
        $testData = [
            'lead_name' => 'Test Lead',
            'lead_score' => 85,
            'account_name' => 'Test Account',
            'value' => 50000,
        ];
        
        // Test with sample data
        $this->alertService->executeActions($rule['actions'], $testData, $rule);
        
        return $response->withJson([
            'success' => true,
            'message' => 'Test alert sent',
        ]);
    }
}
```

