<?php
/**
 * Event-driven health score calculation hooks
 * Triggers health score updates based on significant events
 */

class HealthScoreHooks
{
    /**
     * After save hook for Cases (support tickets)
     * Recalculate health when new ticket is created or priority changes
     */
    public function afterSaveCase($bean, $event, $arguments)
    {
        if (empty($bean->account_id)) {
            return;
        }
        
        // Only recalculate if new ticket or priority changed to High
        if ($arguments['isUpdate'] && !isset($arguments['dataChanges']['priority'])) {
            return;
        }
        
        if (!$arguments['isUpdate'] || $bean->priority === 'High') {
            $this->scheduleHealthScoreUpdate($bean->account_id, 'support_ticket');
        }
    }
    
    /**
     * After save hook for Meetings/Calls
     * Positive signal - improve health score
     */
    public function afterSaveActivity($bean, $event, $arguments)
    {
        if (empty($bean->parent_id) || $bean->parent_type !== 'Accounts') {
            return;
        }
        
        // Only for completed activities
        if ($bean->status === 'Held' || $bean->status === 'Completed') {
            $this->scheduleHealthScoreUpdate($bean->parent_id, 'positive_engagement');
        }
    }
    
    /**
     * Daily check for inactive accounts (can be triggered by a simple webhook)
     */
    public function checkInactiveAccounts()
    {
        global $db;
        
        // Find accounts with no activity in 30 days
        $query = "SELECT DISTINCT a.id 
                 FROM accounts a
                 WHERE a.deleted = 0
                 AND a.account_type != 'Prospect'
                 AND NOT EXISTS (
                     SELECT 1 FROM meetings m
                     JOIN accounts_meetings am ON m.id = am.meeting_id
                     WHERE am.account_id = a.id
                     AND m.date_start > DATE_SUB(NOW(), INTERVAL 30 DAY)
                     AND m.deleted = 0
                 )
                 AND NOT EXISTS (
                     SELECT 1 FROM calls c
                     JOIN accounts_calls ac ON c.id = ac.call_id
                     WHERE ac.account_id = a.id
                     AND c.date_start > DATE_SUB(NOW(), INTERVAL 30 DAY)
                     AND c.deleted = 0
                 )
                 LIMIT 50";
        
        $result = $db->query($query);
        while ($row = $db->fetchByAssoc($result)) {
            $this->scheduleHealthScoreUpdate($row['id'], 'inactivity_check');
        }
    }
    
    /**
     * Schedule health score update (immediate or queued)
     */
    private function scheduleHealthScoreUpdate($accountId, $trigger)
    {
        try {
            // For 80/20 approach, calculate immediately
            require_once(__DIR__ . '/../services/CustomerHealthService.php');
            
            $healthService = new \SuiteCRM\Custom\Services\CustomerHealthService();
            $result = $healthService->calculateHealthScore($accountId);
            
            // Log the trigger
            $GLOBALS['log']->info("Health score updated for account $accountId. Trigger: $trigger. New score: {$result['score']}");
            
            // If score dropped significantly, create immediate alert
            if ($result['score_change'] < -20 || $result['risk_level'] === 'critical') {
                $this->createImmediateAlert($accountId, $result);
            }
            
        } catch (Exception $e) {
            $GLOBALS['log']->error("Health score calculation failed for account $accountId: " . $e->getMessage());
        }
    }
    
    /**
     * Create immediate alert for critical accounts
     */
    private function createImmediateAlert($accountId, $healthData)
    {
        $account = \BeanFactory::getBean('Accounts', $accountId);
        if (!$account || empty($account->assigned_user_id)) {
            return;
        }
        
        // Create notification (in SuiteCRM 7, we'll create a task)
        $alert = \BeanFactory::newBean('Tasks');
        $alert->name = "URGENT: {$account->name} - Critical Health Score ({$healthData['score']})";
        $alert->description = "Account health dropped to {$healthData['score']} (Risk: {$healthData['risk_level']})\n\n" .
                             "Top recommendations:\n";
        
        foreach (array_slice($healthData['recommendations'], 0, 3) as $rec) {
            $alert->description .= "- {$rec['action']}: {$rec['reason']}\n";
        }
        
        $alert->priority = 'High';
        $alert->status = 'Not Started';
        $alert->date_due = date('Y-m-d');
        $alert->assigned_user_id = $account->assigned_user_id;
        $alert->parent_type = 'Accounts';
        $alert->parent_id = $accountId;
        $alert->save();
    }
}

// Register hooks
$hook_version = 1;
$hook_array = [];

// Cases (Support Tickets)
$hook_array['after_save'][] = [
    1,
    'Calculate health score on case save',
    __FILE__,
    'HealthScoreHooks',
    'afterSaveCase'
];

// Register for Meetings
if (!isset($GLOBALS['logic_hooks']['Meetings'])) {
    $GLOBALS['logic_hooks']['Meetings'] = [];
}
$GLOBALS['logic_hooks']['Meetings']['after_save'][] = [
    1,
    'Calculate health score on meeting save',
    __FILE__,
    'HealthScoreHooks',
    'afterSaveActivity'
];

// Register for Calls
if (!isset($GLOBALS['logic_hooks']['Calls'])) {
    $GLOBALS['logic_hooks']['Calls'] = [];
}
$GLOBALS['logic_hooks']['Calls']['after_save'][] = [
    1,
    'Calculate health score on call save',
    __FILE__,
    'HealthScoreHooks',
    'afterSaveActivity'
];