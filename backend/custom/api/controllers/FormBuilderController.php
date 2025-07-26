<?php
/**
 * Form Builder Controller - Dynamic form creation and management
 * Phase 3 Implementation
 */

namespace Api\Controllers;

use Api\Controllers\BaseController;
use Api\Request;
use Api\Response;
use Exception;

class FormBuilderController extends BaseController
{
    private $db;
    private $config;
    
    public function __construct()
    {
        // parent::__construct(); // BaseController has no constructor
        
        global $db;
        $this->db = $db;
        
        // Load configuration
        $this->config = require(__DIR__ . '/../../config/ai_config.php');
    }
    
    /**
     * Get all forms
     * GET /api/v8/forms
     */
    public function getForms(Request $request, Response $response)
    {
        try {
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);
            $limit = min((int)($params['limit'] ?? 20), 100);
            $offset = ($page - 1) * $limit;
            $isActive = $params['is_active'] ?? null;
            
            // Build query
            $where = "WHERE deleted = 0";
            $queryParams = [];
            
            if ($isActive !== null) {
                $where .= " AND is_active = ?";
                $queryParams[] = (int)$isActive;
            }
            
            // Get total count
            $countQuery = "SELECT COUNT(*) FROM form_builder_forms $where";
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute($queryParams);
            $totalCount = $stmt->fetchColumn();
            
            // Get forms
            $query = "SELECT * FROM form_builder_forms 
                     $where 
                     ORDER BY date_modified DESC 
                     LIMIT ? OFFSET ?";
            
            $queryParams[] = $limit;
            $queryParams[] = $offset;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($queryParams);
            $forms = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Parse JSON fields
            foreach ($forms as &$form) {
                $form['fields'] = json_decode($form['fields'], true);
                $form['settings'] = json_decode($form['settings'], true);
            }
            
            return $response->withJson([
                'data' => $forms,
                'meta' => [
                    'total' => $totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Get forms error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get forms',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single form
     * GET /api/v8/forms/{id}
     */
    public function getForm(Request $request, Response $response, array $args)
    {
        try {
            $formId = $args['id'] ?? null;
            if (!$formId) {
                return $response->withJson([
                    'error' => 'Form ID is required'
                ], 400);
            }
            
            $query = "SELECT * FROM form_builder_forms 
                     WHERE id = ? AND deleted = 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$formId]);
            $form = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$form) {
                return $response->withJson([
                    'error' => 'Form not found'
                ], 404);
            }
            
            // Parse JSON fields
            $form['fields'] = json_decode($form['fields'], true);
            $form['settings'] = json_decode($form['settings'], true);
            
            // Get submission stats
            $statsQuery = "SELECT 
                          COUNT(*) as total_submissions,
                          COUNT(DISTINCT lead_id) as unique_leads,
                          MAX(date_submitted) as last_submission
                          FROM form_builder_submissions 
                          WHERE form_id = ? AND deleted = 0";
            
            $stmt = $this->db->prepare($statsQuery);
            $stmt->execute([$formId]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $form['stats'] = $stats;
            
            return $response->withJson([
                'data' => $form
            ]);
            
        } catch (Exception $e) {
            error_log('Get form error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get form',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create new form
     * POST /api/v8/forms
     */
    public function createForm(Request $request, Response $response)
    {
        try {
            $body = $request->getParsedBody();
            
            // Validate required fields
            if (empty($body['name'])) {
                return $response->withJson([
                    'error' => 'Form name is required'
                ], 400);
            }
            
            if (empty($body['fields']) || !is_array($body['fields'])) {
                return $response->withJson([
                    'error' => 'Form fields are required'
                ], 400);
            }
            
            // Validate field count
            if (count($body['fields']) > $this->config['form_builder']['max_fields']) {
                return $response->withJson([
                    'error' => 'Too many fields. Maximum allowed: ' . $this->config['form_builder']['max_fields']
                ], 400);
            }
            
            // Validate field types
            $allowedTypes = array_keys($this->config['form_builder']['field_types']);
            foreach ($body['fields'] as $field) {
                if (!in_array($field['type'], $allowedTypes)) {
                    return $response->withJson([
                        'error' => 'Invalid field type: ' . $field['type']
                    ], 400);
                }
            }
            
            $formId = $this->generateUUID();
            $embedCode = $this->generateEmbedCode($formId);
            
            // Default settings
            $defaultSettings = [
                'submit_button_text' => 'Submit',
                'success_message' => 'Thank you for your submission!',
                'redirect_url' => '',
                'notification_email' => '',
                'capture_utm' => true,
                'require_opt_in' => false,
                'custom_css' => '',
            ];
            
            $settings = array_merge($defaultSettings, $body['settings'] ?? []);
            
            $query = "INSERT INTO form_builder_forms 
                     (id, name, description, fields, settings, embed_code, is_active, 
                      created_by, date_entered, date_modified)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $formId,
                $body['name'],
                $body['description'] ?? '',
                json_encode($body['fields']),
                json_encode($settings),
                $embedCode,
                1, // Active by default
                $this->getCurrentUserId()
            ]);
            
            return $response->withJson([
                'data' => [
                    'id' => $formId,
                    'name' => $body['name'],
                    'embed_code' => $embedCode,
                    'created' => true
                ]
            ], 201);
            
        } catch (Exception $e) {
            error_log('Create form error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to create form',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update form
     * PUT /api/v8/forms/{id}
     */
    public function updateForm(Request $request, Response $response, array $args)
    {
        try {
            $formId = $args['id'] ?? null;
            if (!$formId) {
                return $response->withJson([
                    'error' => 'Form ID is required'
                ], 400);
            }
            
            $body = $request->getParsedBody();
            
            // Check if form exists
            $query = "SELECT * FROM form_builder_forms WHERE id = ? AND deleted = 0";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$formId]);
            $form = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$form) {
                return $response->withJson([
                    'error' => 'Form not found'
                ], 404);
            }
            
            // Build update query
            $updates = [];
            $params = [];
            
            if (isset($body['name'])) {
                $updates[] = "name = ?";
                $params[] = $body['name'];
            }
            
            if (isset($body['description'])) {
                $updates[] = "description = ?";
                $params[] = $body['description'];
            }
            
            if (isset($body['fields'])) {
                // Validate fields
                if (count($body['fields']) > $this->config['form_builder']['max_fields']) {
                    return $response->withJson([
                        'error' => 'Too many fields'
                    ], 400);
                }
                
                $updates[] = "fields = ?";
                $params[] = json_encode($body['fields']);
            }
            
            if (isset($body['settings'])) {
                $currentSettings = json_decode($form['settings'], true);
                $newSettings = array_merge($currentSettings, $body['settings']);
                $updates[] = "settings = ?";
                $params[] = json_encode($newSettings);
            }
            
            if (isset($body['is_active'])) {
                $updates[] = "is_active = ?";
                $params[] = (int)$body['is_active'];
            }
            
            if (empty($updates)) {
                return $response->withJson([
                    'error' => 'No fields to update'
                ], 400);
            }
            
            $updates[] = "date_modified = NOW()";
            $updates[] = "modified_user_id = ?";
            $params[] = $this->getCurrentUserId();
            $params[] = $formId;
            
            $query = "UPDATE form_builder_forms SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $response->withJson([
                'data' => [
                    'id' => $formId,
                    'updated' => true
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Update form error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to update form',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete form
     * DELETE /api/v8/forms/{id}
     */
    public function deleteForm(Request $request, Response $response, array $args)
    {
        try {
            $formId = $args['id'] ?? null;
            if (!$formId) {
                return $response->withJson([
                    'error' => 'Form ID is required'
                ], 400);
            }
            
            // Soft delete
            $query = "UPDATE form_builder_forms 
                     SET deleted = 1, date_modified = NOW() 
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$formId]);
            
            if ($stmt->rowCount() === 0) {
                return $response->withJson([
                    'error' => 'Form not found'
                ], 404);
            }
            
            return $response->withJson([
                'data' => [
                    'id' => $formId,
                    'deleted' => true
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Delete form error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to delete form',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Submit form (public endpoint)
     * POST /api/v8/forms/{id}/submit
     */
    public function submitForm(Request $request, Response $response, array $args)
    {
        try {
            $formId = $args['id'] ?? null;
            if (!$formId) {
                return $response->withJson([
                    'error' => 'Form ID is required'
                ], 400);
            }
            
            // Get form
            $query = "SELECT * FROM form_builder_forms 
                     WHERE id = ? AND is_active = 1 AND deleted = 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$formId]);
            $form = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$form) {
                return $response->withJson([
                    'error' => 'Form not found or inactive'
                ], 404);
            }
            
            $fields = json_decode($form['fields'], true);
            $settings = json_decode($form['settings'], true);
            $submission = $request->getParsedBody();
            
            // Validate required fields
            $errors = [];
            foreach ($fields as $field) {
                if ($field['required'] && empty($submission[$field['name']])) {
                    $errors[] = $field['label'] . ' is required';
                }
                
                // Validate email fields
                if ($field['type'] === 'email' && !empty($submission[$field['name']])) {
                    if (!filter_var($submission[$field['name']], FILTER_VALIDATE_EMAIL)) {
                        $errors[] = $field['label'] . ' must be a valid email';
                    }
                }
            }
            
            if (!empty($errors)) {
                return $response->withJson([
                    'error' => 'Validation failed',
                    'errors' => $errors
                ], 400);
            }
            
            // Spam protection
            if ($settings['spam_protection'] ?? false) {
                // Simple honeypot check
                if (!empty($submission['_hp'])) {
                    // Silently accept but don't save
                    return $response->withJson([
                        'data' => ['success' => true]
                    ]);
                }
            }
            
            // Check domain whitelist
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (!$this->isAllowedDomain($referer)) {
                return $response->withJson([
                    'error' => 'Submissions not allowed from this domain'
                ], 403);
            }
            
            // Save submission
            $submissionId = $this->generateUUID();
            
            $query = "INSERT INTO form_builder_submissions 
                     (id, form_id, data, ip_address, user_agent, referrer_url, date_submitted)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $submissionId,
                $formId,
                json_encode($submission),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $referer
            ]);
            
            // Update submission count
            $query = "UPDATE form_builder_forms 
                     SET submissions_count = submissions_count + 1 
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$formId]);
            
            // Create or update lead
            $leadId = $this->processLead($submission, $formId);
            if ($leadId) {
                $query = "UPDATE form_builder_submissions 
                         SET lead_id = ? 
                         WHERE id = ?";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([$leadId, $submissionId]);
            }
            
            // Send notification email if configured
            if (!empty($settings['notification_email'])) {
                $this->sendNotificationEmail($settings['notification_email'], $form, $submission);
            }
            
            // Trigger webhook
            $this->triggerWebhook('form_submitted', [
                'form_id' => $formId,
                'submission_id' => $submissionId,
                'lead_id' => $leadId,
                'data' => $submission
            ]);
            
            // Return success response
            $responseData = ['success' => true];
            
            if (!empty($settings['success_message'])) {
                $responseData['message'] = $settings['success_message'];
            }
            
            if (!empty($settings['redirect_url'])) {
                $responseData['redirect'] = $settings['redirect_url'];
            }
            
            return $response->withJson([
                'data' => $responseData
            ]);
            
        } catch (Exception $e) {
            error_log('Form submission error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to submit form',
                'message' => 'Please try again later'
            ], 500);
        }
    }
    
    /**
     * Get form submissions
     * GET /api/v8/forms/{id}/submissions
     */
    public function getSubmissions(Request $request, Response $response, array $args)
    {
        try {
            $formId = $args['id'] ?? null;
            if (!$formId) {
                return $response->withJson([
                    'error' => 'Form ID is required'
                ], 400);
            }
            
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);
            $limit = min((int)($params['limit'] ?? 50), 100);
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $countQuery = "SELECT COUNT(*) FROM form_builder_submissions 
                          WHERE form_id = ? AND deleted = 0";
            
            $stmt = $this->db->prepare($countQuery);
            $stmt->execute([$formId]);
            $totalCount = $stmt->fetchColumn();
            
            // Get submissions
            $query = "SELECT s.*, l.first_name, l.last_name, l.email as lead_email 
                     FROM form_builder_submissions s
                     LEFT JOIN leads l ON s.lead_id = l.id
                     WHERE s.form_id = ? AND s.deleted = 0
                     ORDER BY s.date_submitted DESC
                     LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$formId, $limit, $offset]);
            $submissions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Parse JSON data
            foreach ($submissions as &$submission) {
                $submission['data'] = json_decode($submission['data'], true);
            }
            
            return $response->withJson([
                'data' => $submissions,
                'meta' => [
                    'total' => $totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Get submissions error: ' . $e->getMessage());
            return $response->withJson([
                'error' => 'Failed to get submissions',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Private helper methods
     */
    
    private function generateEmbedCode($formId)
    {
        $siteUrl = $GLOBALS['sugar_config']['site_url'] ?? 'http://localhost:8080';
        
        return <<<HTML
<!-- CRM Form Embed -->
<div data-form-id="{$formId}"></div>
<script>
window.SUITECRM_URL = '{$siteUrl}';
</script>
<script src="{$siteUrl}/custom/public/js/forms-embed.js"></script>
<!-- End CRM Form Embed -->
HTML;
    }
    
    private function processLead($submission, $formId)
    {
        // Look for email field
        $email = null;
        foreach ($submission as $key => $value) {
            if (strpos($key, 'email') !== false && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $email = $value;
                break;
            }
        }
        
        if (!$email) {
            return null;
        }
        
        // Check if lead exists
        $query = "SELECT id FROM leads WHERE email = ? AND deleted = 0";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$email]);
        $leadId = $stmt->fetchColumn();
        
        if ($leadId) {
            // Update existing lead
            $query = "UPDATE leads SET date_modified = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$leadId]);
        } else {
            // Create new lead
            $leadId = $this->generateUUID();
            
            // Extract name if available
            $firstName = $submission['first_name'] ?? $submission['fname'] ?? '';
            $lastName = $submission['last_name'] ?? $submission['lname'] ?? '';
            $company = $submission['company'] ?? $submission['organization'] ?? '';
            $phone = $submission['phone'] ?? $submission['tel'] ?? '';
            
            $query = "INSERT INTO leads 
                     (id, first_name, last_name, email, account_name, phone_mobile, 
                      lead_source, status, date_entered, date_modified)
                     VALUES (?, ?, ?, ?, ?, ?, 'Form', 'New', NOW(), NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $leadId,
                $firstName,
                $lastName,
                $email,
                $company,
                $phone
            ]);
        }
        
        return $leadId;
    }
    
    private function isAllowedDomain($url)
    {
        if (empty($url)) {
            return true; // Allow if no referer
        }
        
        $allowedDomains = $this->config['form_builder']['allowed_domains'];
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';
        
        foreach ($allowedDomains as $domain) {
            if (strpos($host, $domain) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function sendNotificationEmail($to, $form, $submission)
    {
        // TODO: Implement email sending
        // This would use SuiteCRM's email system or PHPMailer
    }
    
    private function triggerWebhook($event, $data)
    {
        global $sugar_config;
        $webhookUrl = $sugar_config['ai']['webhooks'][$event] ?? '';
        
        if (empty($webhookUrl)) {
            return;
        }
        
        // Queue webhook for async processing
        $query = "INSERT INTO webhook_events 
                 (id, event_type, payload, webhook_url, status, date_created)
                 VALUES (?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $this->generateUUID(),
            $event,
            json_encode($data),
            $webhookUrl
        ]);
    }
    
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function getCurrentUserId()
    {
        // TODO: Get from JWT token
        return '1'; // Admin user for now
    }
    
    /**
     * Get active forms only
     * GET /api/forms/active
     */
    public function getActiveForms(Request $request)
    {
        try {
            global $db;
            
            $query = "SELECT * FROM form_builder_forms 
                      WHERE status = 'active' AND deleted = 0 
                      ORDER BY date_created DESC";
            
            $result = $db->query($query);
            $forms = [];
            
            while ($row = $db->fetchByAssoc($result)) {
                $forms[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'fields' => json_decode($row['fields'], true),
                    'status' => $row['status'],
                    'embedCode' => $this->generateEmbedCode($row['id']),
                    'submissionCount' => $this->getSubmissionCount($row['id']),
                    'dateCreated' => $row['date_created'],
                    'dateModified' => $row['date_modified']
                ];
            }
            
            return $this->success([
                'data' => $forms,
                'total' => count($forms)
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to fetch active forms: ' . $e->getMessage());
        }
    }
    
    /**
     * Get embed code for a form
     * GET /api/forms/{id}/embed
     */
    public function getEmbedCode(Request $request)
    {
        try {
            $formId = $request->getParam('id');
            
            if (!$formId) {
                return $this->error('Form ID is required');
            }
            
            // Verify form exists
            global $db;
            $query = "SELECT id, name FROM form_builder_forms WHERE id = ? AND deleted = 0";
            $stmt = $db->prepare($query);
            $stmt->execute([$formId]);
            $form = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$form) {
                return $this->error('Form not found', 404);
            }
            
            $embedCode = $this->generateEmbedCode($formId);
            
            return $this->success([
                'formId' => $formId,
                'formName' => $form['name'],
                'embedCode' => $embedCode,
                'iframeCode' => $this->generateIframeCode($formId),
                'scriptCode' => $this->generateScriptCode($formId)
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to generate embed code: ' . $e->getMessage());
        }
    }
    
    /**
     * Convert form submission to lead
     * POST /api/forms/{id}/submissions/{submission_id}/convert
     */
    public function convertSubmissionToLead(Request $request)
    {
        try {
            $formId = $request->getParam('id');
            $submissionId = $request->getParam('submission_id');
            
            if (!$formId || !$submissionId) {
                return $this->error('Form ID and submission ID are required');
            }
            
            // Get submission data
            global $db;
            $query = "SELECT * FROM form_builder_submissions 
                      WHERE id = ? AND form_id = ? AND deleted = 0";
            $stmt = $db->prepare($query);
            $stmt->execute([$submissionId, $formId]);
            $submission = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$submission) {
                return $this->error('Submission not found', 404);
            }
            
            $submissionData = json_decode($submission['data'], true);
            
            // Check if already converted
            if (!empty($submission['lead_id'])) {
                return $this->error('Submission already converted to lead', 400);
            }
            
            // Create lead
            $leadId = $this->generateUUID();
            
            // Map form fields to lead fields
            $leadData = [
                'id' => $leadId,
                'first_name' => $submissionData['firstName'] ?? $submissionData['name'] ?? '',
                'last_name' => $submissionData['lastName'] ?? '',
                'email1' => $submissionData['email'] ?? '',
                'phone_work' => $submissionData['phone'] ?? '',
                'account_name' => $submissionData['company'] ?? '',
                'title' => $submissionData['title'] ?? '',
                'description' => $submissionData['message'] ?? $submissionData['comments'] ?? '',
                'lead_source' => 'Web Form',
                'status' => 'New',
                'assigned_user_id' => $this->getCurrentUserId(),
                'date_entered' => 'NOW()',
                'date_modified' => 'NOW()',
                'created_by' => $this->getCurrentUserId(),
                'modified_user_id' => $this->getCurrentUserId(),
                'deleted' => 0
            ];
            
            // Build insert query
            $fields = array_keys($leadData);
            $values = array_values($leadData);
            $placeholders = array_map(function($field) use ($leadData) {
                return $leadData[$field] === 'NOW()' ? 'NOW()' : '?';
            }, $fields);
            
            $query = "INSERT INTO leads (" . implode(', ', $fields) . ") 
                      VALUES (" . implode(', ', $placeholders) . ")";
            
            // Remove NOW() values from values array
            $values = array_filter($values, function($value) {
                return $value !== 'NOW()';
            });
            
            $stmt = $db->prepare($query);
            $stmt->execute($values);
            
            // Update submission with lead ID
            $query = "UPDATE form_builder_submissions 
                      SET lead_id = ?, converted_at = NOW() 
                      WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$leadId, $submissionId]);
            
            // Create activity record
            $this->createLeadActivity($leadId, 
                "Lead created from form submission: " . ($submission['form_name'] ?? 'Unknown Form'));
            
            return $this->success([
                'leadId' => $leadId,
                'message' => 'Successfully converted submission to lead'
            ]);
            
        } catch (\Exception $e) {
            return $this->error('Failed to convert submission: ' . $e->getMessage());
        }
    }
    
    private function generateEmbedCode($formId)
    {
        $baseUrl = $this->getBaseUrl();
        return sprintf(
            '<div id="crm-form-%s"></div><script src="%s/api/forms/%s/embed.js"></script>',
            $formId,
            $baseUrl,
            $formId
        );
    }
    
    private function generateIframeCode($formId)
    {
        $baseUrl = $this->getBaseUrl();
        return sprintf(
            '<iframe src="%s/forms/embed/%s" width="100%%" height="600" frameborder="0"></iframe>',
            $baseUrl,
            $formId
        );
    }
    
    private function generateScriptCode($formId)
    {
        $baseUrl = $this->getBaseUrl();
        return sprintf(
            "(function(){var s=document.createElement('script');s.src='%s/api/forms/%s/embed.js';s.async=true;document.body.appendChild(s);})();",
            $baseUrl,
            $formId
        );
    }
    
    private function getSubmissionCount($formId)
    {
        global $db;
        $query = "SELECT COUNT(*) as count FROM form_builder_submissions 
                  WHERE form_id = ? AND deleted = 0";
        $stmt = $db->prepare($query);
        $stmt->execute([$formId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }
    
    private function getBaseUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
    
    private function createLeadActivity($leadId, $description)
    {
        global $db;
        $activityId = $this->generateUUID();
        
        $query = "INSERT INTO notes 
                  (id, name, description, parent_type, parent_id, 
                   assigned_user_id, date_entered, date_modified, 
                   created_by, modified_user_id, deleted)
                  VALUES (?, 'Form Submission', ?, 'Leads', ?, ?, 
                          NOW(), NOW(), ?, ?, 0)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $activityId,
            $description,
            $leadId,
            $this->getCurrentUserId(),
            $this->getCurrentUserId(),
            $this->getCurrentUserId()
        ]);
    }
}