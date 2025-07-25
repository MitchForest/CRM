<?php
// Public lead form submission endpoint
// This handles form submissions from the marketing website

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['firstName', 'lastName', 'email', 'company'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

// Initialize SuiteCRM
chdir('../..');
define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Leads/Lead.php');

global $db, $current_user;

try {
    // Create new lead
    $lead = new Lead();
    $lead->first_name = $input['firstName'];
    $lead->last_name = $input['lastName'];
    $lead->email1 = $input['email'];
    $lead->account_name = $input['company'];
    $lead->phone_work = $input['phone'] ?? '';
    $lead->description = $input['message'] ?? '';
    $lead->lead_source = 'Website Form';
    $lead->status = 'New';
    $lead->assigned_user_id = '1'; // Default to admin
    
    $lead->save();
    
    // Trigger AI scoring
    if ($lead->id) {
        require_once('custom/services/OpenAIService.php');
        $aiService = new OpenAIService();
        
        try {
            $score = $aiService->scoreLeadWithAI($lead->id);
            
            // Update lead with AI score
            $query = "UPDATE leads_cstm SET ai_score_c = ?, ai_score_date_c = NOW() WHERE id_c = ?";
            $stmt = $db->getConnection()->prepare($query);
            $stmt->execute([$score, $lead->id]);
        } catch (Exception $e) {
            // Log but don't fail the request
            error_log("AI scoring failed for lead {$lead->id}: " . $e->getMessage());
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'leadId' => $lead->id,
        'message' => 'Thank you for your interest! We\'ll be in touch soon.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create lead']);
    error_log("Lead creation failed: " . $e->getMessage());
}