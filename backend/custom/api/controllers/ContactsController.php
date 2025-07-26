<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class ContactsController extends BaseController
{
    protected $module = 'Contacts';
    
    /**
     * Get unified contact detail with all related data
     */
    public function getUnifiedView(Request $request, $id)
    {
        global $db;
        
        try {
            // Get contact basic info
            $contact = \BeanFactory::getBean('Contacts', $id);
            if (!$contact || $contact->deleted) {
                return Response::error('Contact not found', 404);
            }
            
            $contactData = [
                'id' => $contact->id,
                'firstName' => $contact->first_name,
                'lastName' => $contact->last_name,
                'email' => $contact->email1,
                'phone' => $contact->phone_work,
                'mobile' => $contact->phone_mobile,
                'title' => $contact->title,
                'department' => $contact->department,
                'accountId' => $contact->account_id,
                'accountName' => $contact->account_name,
                'isCompany' => $contact->is_company ?? false,
                'companyName' => $contact->account_name,
                'dateEntered' => $contact->date_entered,
                'dateModified' => $contact->date_modified,
                'assignedUserId' => $contact->assigned_user_id,
                'description' => $contact->description
            ];
            
            // Get all activities in chronological order
            $activities = [];
            
            // Get visitor tracking data
            $visitorQuery = "SELECT * FROM activity_tracking_visitors 
                           WHERE contact_id = '$id' 
                           ORDER BY last_visit DESC";
            $visitorResult = $db->query($visitorQuery);
            while ($visitor = $db->fetchByAssoc($visitorResult)) {
                $activities[] = [
                    'type' => 'website_visit',
                    'date' => $visitor['last_visit'],
                    'data' => [
                        'totalVisits' => $visitor['total_visits'],
                        'totalPageViews' => $visitor['total_page_views'],
                        'totalTimeSpent' => $visitor['total_time_spent'],
                        'engagementScore' => $visitor['engagement_score'],
                        'browser' => $visitor['browser'],
                        'device' => $visitor['device_type']
                    ]
                ];
            }
            
            // Get AI chat conversations
            $chatQuery = "SELECT c.*, 
                         (SELECT COUNT(*) FROM ai_chat_messages WHERE conversation_id = c.id) as message_count
                         FROM ai_chat_conversations c
                         WHERE c.contact_id = '$id'
                         ORDER BY c.started_at DESC";
            $chatResult = $db->query($chatQuery);
            while ($chat = $db->fetchByAssoc($chatResult)) {
                $activities[] = [
                    'type' => 'ai_chat',
                    'date' => $chat['started_at'],
                    'data' => [
                        'conversationId' => $chat['id'],
                        'status' => $chat['status'],
                        'messageCount' => $chat['message_count'],
                        'duration' => strtotime($chat['ended_at']) - strtotime($chat['started_at'])
                    ]
                ];
            }
            
            // Get calls
            $callQuery = "SELECT c.*, u.user_name as assigned_user_name 
                         FROM calls c
                         LEFT JOIN users u ON c.assigned_user_id = u.id
                         WHERE c.deleted = 0 
                         AND EXISTS (
                             SELECT 1 FROM calls_contacts cc 
                             WHERE cc.call_id = c.id AND cc.contact_id = '$id'
                         )
                         ORDER BY c.date_start DESC";
            $callResult = $db->query($callQuery);
            while ($call = $db->fetchByAssoc($callResult)) {
                $activities[] = [
                    'type' => 'call',
                    'date' => $call['date_start'],
                    'data' => [
                        'id' => $call['id'],
                        'name' => $call['name'],
                        'status' => $call['status'],
                        'direction' => $call['direction'],
                        'duration' => $call['duration_hours'] * 60 + $call['duration_minutes'],
                        'assignedTo' => $call['assigned_user_name'],
                        'description' => $call['description']
                    ]
                ];
            }
            
            // Get meetings
            $meetingQuery = "SELECT m.*, u.user_name as assigned_user_name 
                           FROM meetings m
                           LEFT JOIN users u ON m.assigned_user_id = u.id
                           WHERE m.deleted = 0 
                           AND EXISTS (
                               SELECT 1 FROM meetings_contacts mc 
                               WHERE mc.meeting_id = m.id AND mc.contact_id = '$id'
                           )
                           ORDER BY m.date_start DESC";
            $meetingResult = $db->query($meetingQuery);
            while ($meeting = $db->fetchByAssoc($meetingResult)) {
                $activities[] = [
                    'type' => 'meeting',
                    'date' => $meeting['date_start'],
                    'data' => [
                        'id' => $meeting['id'],
                        'name' => $meeting['name'],
                        'status' => $meeting['status'],
                        'location' => $meeting['location'],
                        'duration' => $meeting['duration_hours'] * 60 + $meeting['duration_minutes'],
                        'assignedTo' => $meeting['assigned_user_name'],
                        'description' => $meeting['description']
                    ]
                ];
            }
            
            // Get emails
            $emailQuery = "SELECT e.*, u.user_name as assigned_user_name 
                          FROM emails e
                          LEFT JOIN users u ON e.assigned_user_id = u.id
                          WHERE e.deleted = 0 
                          AND EXISTS (
                              SELECT 1 FROM emails_beans eb 
                              WHERE eb.email_id = e.id 
                              AND eb.bean_module = 'Contacts' 
                              AND eb.bean_id = '$id'
                          )
                          ORDER BY e.date_sent DESC";
            $emailResult = $db->query($emailQuery);
            while ($email = $db->fetchByAssoc($emailResult)) {
                $activities[] = [
                    'type' => 'email',
                    'date' => $email['date_sent'],
                    'data' => [
                        'id' => $email['id'],
                        'name' => $email['name'],
                        'status' => $email['status'],
                        'assignedTo' => $email['assigned_user_name'],
                        'description' => strip_tags($email['description'])
                    ]
                ];
            }
            
            // Get notes
            $noteQuery = "SELECT n.*, u.user_name as created_by_name 
                         FROM notes n
                         LEFT JOIN users u ON n.created_by = u.id
                         WHERE n.deleted = 0 
                         AND n.parent_type = 'Contacts' 
                         AND n.parent_id = '$id'
                         ORDER BY n.date_entered DESC";
            $noteResult = $db->query($noteQuery);
            while ($note = $db->fetchByAssoc($noteResult)) {
                $activities[] = [
                    'type' => 'note',
                    'date' => $note['date_entered'],
                    'data' => [
                        'id' => $note['id'],
                        'name' => $note['name'],
                        'createdBy' => $note['created_by_name'],
                        'description' => $note['description']
                    ]
                ];
            }
            
            // Sort activities by date
            usort($activities, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            
            // Get support tickets
            $ticketsQuery = "SELECT * FROM cases 
                           WHERE deleted = 0 
                           AND account_id = '{$contact->account_id}'
                           ORDER BY date_entered DESC";
            $ticketsResult = $db->query($ticketsQuery);
            $tickets = [];
            while ($ticket = $db->fetchByAssoc($ticketsResult)) {
                $tickets[] = [
                    'id' => $ticket['id'],
                    'name' => $ticket['name'],
                    'status' => $ticket['status'],
                    'priority' => $ticket['priority'],
                    'type' => $ticket['type'],
                    'dateEntered' => $ticket['date_entered']
                ];
            }
            
            // Get health/lead score
            $score = null;
            if ($contact->account_id) {
                // Customer health score
                $scoreQuery = "SELECT * FROM customer_health_scores 
                             WHERE contact_id = '$id' 
                             ORDER BY calculated_at DESC 
                             LIMIT 1";
                $scoreResult = $db->query($scoreQuery);
                if ($scoreData = $db->fetchByAssoc($scoreResult)) {
                    $score = [
                        'type' => 'health',
                        'value' => $scoreData['score'],
                        'riskLevel' => $scoreData['risk_level'],
                        'factors' => json_decode($scoreData['factors'], true),
                        'calculatedAt' => $scoreData['calculated_at']
                    ];
                }
            }
            
            // Get related opportunities
            $opportunities = [];
            $oppQuery = "SELECT o.* FROM opportunities o
                        WHERE o.deleted = 0 
                        AND EXISTS (
                            SELECT 1 FROM opportunities_contacts oc 
                            WHERE oc.opportunity_id = o.id AND oc.contact_id = '$id'
                        )
                        ORDER BY o.date_entered DESC";
            $oppResult = $db->query($oppQuery);
            while ($opp = $db->fetchByAssoc($oppResult)) {
                $opportunities[] = [
                    'id' => $opp['id'],
                    'name' => $opp['name'],
                    'amount' => $opp['amount'],
                    'salesStage' => $opp['sales_stage'],
                    'probability' => $opp['probability'],
                    'closeDate' => $opp['date_closed']
                ];
            }
            
            return Response::json([
                'data' => [
                    'contact' => $contactData,
                    'activities' => $activities,
                    'tickets' => $tickets,
                    'opportunities' => $opportunities,
                    'score' => $score,
                    'stats' => [
                        'totalActivities' => count($activities),
                        'openTickets' => count(array_filter($tickets, function($t) { 
                            return !in_array($t['status'], ['Closed', 'Resolved']); 
                        })),
                        'totalOpportunities' => count($opportunities)
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return Response::error('Failed to retrieve contact data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get contacts list with account unification
     */
    public function index(Request $request, Response $response)
    {
        global $db;
        
        try {
            // Get pagination parameters
            list($limit, $offset) = $this->getPaginationParams($request);
            
            // Build query for both contacts and accounts (unified)
            $query = "SELECT c.*, 
                     CASE WHEN c.is_company = 1 THEN c.account_name ELSE CONCAT(c.first_name, ' ', c.last_name) END as display_name
                     FROM contacts c 
                     WHERE c.deleted = 0";
            
            // Add search filter
            $search = $request->get('search');
            if ($search) {
                $searchEscaped = $db->quote("%$search%");
                $query .= " AND (c.first_name LIKE $searchEscaped 
                          OR c.last_name LIKE $searchEscaped 
                          OR c.account_name LIKE $searchEscaped
                          OR c.email1 LIKE $searchEscaped)";
            }
            
            // Get total count
            $countQuery = str_replace('SELECT c.*,', 'SELECT COUNT(*) as total', $query);
            $countResult = $db->query($countQuery);
            $totalRow = $db->fetchByAssoc($countResult);
            $total = $totalRow['total'] ?? 0;
            
            // Add sorting
            $query .= " ORDER BY c.date_entered DESC";
            
            // Add pagination
            $query .= " LIMIT $limit OFFSET $offset";
            
            // Execute query
            $result = $db->query($query);
            $contacts = [];
            
            while ($row = $db->fetchByAssoc($result)) {
                $contacts[] = [
                    'id' => $row['id'],
                    'firstName' => $row['first_name'],
                    'lastName' => $row['last_name'],
                    'displayName' => $row['display_name'],
                    'email' => $row['email1'],
                    'phone' => $row['phone_work'],
                    'mobile' => $row['phone_mobile'],
                    'title' => $row['title'],
                    'accountName' => $row['account_name'],
                    'isCompany' => (bool)$row['is_company'],
                    'dateEntered' => $row['date_entered'],
                    'dateModified' => $row['date_modified']
                ];
            }
            
            return Response::json([
                'data' => $contacts,
                'pagination' => [
                    'page' => floor($offset / $limit) + 1,
                    'pageSize' => $limit,
                    'total' => (int)$total,
                    'totalPages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (\Exception $e) {
            return Response::error('Failed to retrieve contacts: ' . $e->getMessage(), 500);
        }
    }
}