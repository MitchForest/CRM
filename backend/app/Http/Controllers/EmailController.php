<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Services\EmailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailController extends Controller
{
    private EmailService $emailService;
    
    public function __construct()
    {
        parent::__construct();
        $this->emailService = new EmailService();
    }
    
    /**
     * Get email templates
     * GET /api/admin/emails/templates
     */
    public function getTemplates(Request $request, Response $response, array $args): Response
    {
        try {
            $params = $request->getQueryParams();
            $data = $this->validate($request, [
                'page' => 'sometimes|integer|min:1',
                'limit' => 'sometimes|integer|min:1|max:100',
                'type' => 'sometimes|string|in:lead,opportunity,case,general',
                'search' => 'sometimes|string'
            ]);
            
            $page = intval($data['page'] ?? 1);
            $limit = intval($data['limit'] ?? 20);
            
            $query = EmailTemplate::where('deleted', 0);
            
            // Apply filters
            if (isset($data['type'])) {
                $query->where('type', $data['type']);
            }
            
            if (isset($data['search'])) {
                $search = $data['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('subject', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            // Order by name
            $query->orderBy('name');
            
            // Get total count
            $totalCount = $query->count();
            
            // Apply pagination
            $offset = ($page - 1) * $limit;
            $templates = $query->offset($offset)->limit($limit)->get();
            
            // Format templates
            $formattedTemplates = $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'subject' => $template->subject,
                    'type' => $template->type,
                    'description' => $template->description,
                    'body' => $template->body,
                    'body_html' => $template->body_html,
                    'variables' => $this->getTemplateVariables($template->type),
                    'date_entered' => $template->date_entered,
                    'date_modified' => $template->date_modified
                ];
            });
            
            return $this->json($response, [
                'data' => $formattedTemplates,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'total_pages' => ceil($totalCount / $limit)
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to fetch email templates: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create email template
     * POST /api/admin/emails/templates
     */
    public function createTemplate(Request $request, Response $response, array $args): Response
    {
        DB::beginTransaction();
        
        try {
            $data = $this->validate($request, [
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'type' => 'required|string|in:lead,opportunity,case,general',
                'description' => 'sometimes|string',
                'body' => 'required|string',
                'body_html' => 'sometimes|string'
            ]);
            
            // Check for duplicate name
            $exists = EmailTemplate::where('deleted', 0)
                ->where('name', $data['name'])
                ->exists();
                
            if ($exists) {
                return $this->error($response, 'Template with this name already exists', 400);
            }
            
            $template = EmailTemplate::create([
                'name' => $data['name'],
                'subject' => $data['subject'],
                'type' => $data['type'],
                'description' => $data['description'] ?? '',
                'body' => $data['body'],
                'body_html' => $data['body_html'] ?? $data['body'],
                'text_only' => empty($data['body_html']) ? 1 : 0,
                'published' => 'yes',
                'created_by' => $request->getAttribute('user_id'),
                'modified_user_id' => $request->getAttribute('user_id')
            ]);
            
            DB::commit();
            
            return $this->json($response, [
                'data' => ['id' => $template->id],
                'message' => 'Email template created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to create email template: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update email template
     * PUT /api/admin/emails/templates/{id}
     */
    public function updateTemplate(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        
        $template = EmailTemplate::where('deleted', 0)->find($id);
        if (!$template) {
            return $this->error($response, 'Email template not found', 404);
        }
        
        DB::beginTransaction();
        
        try {
            $data = $this->validate($request, [
                'name' => 'sometimes|string|max:255',
                'subject' => 'sometimes|string|max:255',
                'type' => 'sometimes|string|in:lead,opportunity,case,general',
                'description' => 'sometimes|string',
                'body' => 'sometimes|string',
                'body_html' => 'sometimes|string'
            ]);
            
            // Check for duplicate name if name is being changed
            if (isset($data['name']) && $data['name'] !== $template->name) {
                $exists = EmailTemplate::where('deleted', 0)
                    ->where('name', $data['name'])
                    ->where('id', '!=', $id)
                    ->exists();
                    
                if ($exists) {
                    return $this->error($response, 'Template with this name already exists', 400);
                }
            }
            
            // Update fields
            if (isset($data['name'])) $template->name = $data['name'];
            if (isset($data['subject'])) $template->subject = $data['subject'];
            if (isset($data['type'])) $template->type = $data['type'];
            if (isset($data['description'])) $template->description = $data['description'];
            if (isset($data['body'])) $template->body = $data['body'];
            if (isset($data['body_html'])) {
                $template->body_html = $data['body_html'];
                $template->text_only = 0;
            }
            
            $template->modified_user_id = $request->getAttribute('user_id');
            $template->save();
            
            DB::commit();
            
            return $this->json($response, [
                'data' => ['id' => $template->id],
                'message' => 'Email template updated successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($response, 'Failed to update email template: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete email template
     * DELETE /api/admin/emails/templates/{id}
     */
    public function deleteTemplate(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        
        $template = EmailTemplate::where('deleted', 0)->find($id);
        if (!$template) {
            return $this->error($response, 'Email template not found', 404);
        }
        
        try {
            $template->deleted = 1;
            $template->save();
            
            return $this->json($response, ['message' => 'Email template deleted successfully']);
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to delete email template: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Send test email
     * POST /api/admin/emails/test
     */
    public function sendTestEmail(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->validate($request, [
                'to' => 'required|email',
                'template_id' => 'sometimes|string',
                'subject' => 'required_without:template_id|string|max:255',
                'body' => 'required_without:template_id|string',
                'body_html' => 'sometimes|string',
                'test_data' => 'sometimes|array'
            ]);
            
            $subject = '';
            $bodyText = '';
            $bodyHtml = '';
            
            // Use template if provided
            if (isset($data['template_id'])) {
                $template = EmailTemplate::where('deleted', 0)->find($data['template_id']);
                if (!$template) {
                    return $this->error($response, 'Email template not found', 404);
                }
                
                $testData = $data['test_data'] ?? $this->getTestData($template->type);
                
                $subject = $this->processTemplate($template->subject, $testData);
                $bodyText = $this->processTemplate($template->body, $testData);
                $bodyHtml = $this->processTemplate($template->body_html, $testData);
            } else {
                $subject = $data['subject'];
                $bodyText = $data['body'];
                $bodyHtml = $data['body_html'] ?? $data['body'];
            }
            
            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = $_ENV['SMTP_HOST'] ?? 'localhost';
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['SMTP_USERNAME'] ?? '';
                $mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
                $mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = intval($_ENV['SMTP_PORT'] ?? 587);
                
                // Recipients
                $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com', $_ENV['MAIL_FROM_NAME'] ?? 'CRM System');
                $mail->addAddress($data['to']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $bodyHtml;
                $mail->AltBody = $bodyText;
                
                $mail->send();
                
                return $this->json($response, [
                    'message' => 'Test email sent successfully to ' . $data['to']
                ]);
                
            } catch (Exception $e) {
                return $this->error($response, 'Failed to send email: ' . $mail->ErrorInfo, 500);
            }
            
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to send test email: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get available template variables by type
     */
    private function getTemplateVariables(string $type): array
    {
        $commonVars = [
            '{user_name}' => 'Current user name',
            '{user_email}' => 'Current user email',
            '{company_name}' => 'Company name',
            '{current_date}' => 'Current date',
            '{current_year}' => 'Current year'
        ];
        
        switch ($type) {
            case 'lead':
                return array_merge($commonVars, [
                    '{lead_name}' => 'Lead full name',
                    '{lead_first_name}' => 'Lead first name',
                    '{lead_last_name}' => 'Lead last name',
                    '{lead_email}' => 'Lead email',
                    '{lead_phone}' => 'Lead phone',
                    '{lead_company}' => 'Lead company',
                    '{lead_title}' => 'Lead title',
                    '{lead_source}' => 'Lead source',
                    '{lead_status}' => 'Lead status'
                ]);
                
            case 'opportunity':
                return array_merge($commonVars, [
                    '{opportunity_name}' => 'Opportunity name',
                    '{opportunity_amount}' => 'Opportunity amount',
                    '{opportunity_stage}' => 'Opportunity stage',
                    '{opportunity_close_date}' => 'Expected close date',
                    '{contact_name}' => 'Contact name',
                    '{account_name}' => 'Account name'
                ]);
                
            case 'case':
                return array_merge($commonVars, [
                    '{case_number}' => 'Case number',
                    '{case_subject}' => 'Case subject',
                    '{case_status}' => 'Case status',
                    '{case_priority}' => 'Case priority',
                    '{case_description}' => 'Case description',
                    '{contact_name}' => 'Contact name',
                    '{account_name}' => 'Account name'
                ]);
                
            default:
                return $commonVars;
        }
    }
    
    /**
     * Get test data for template processing
     */
    private function getTestData(string $type): array
    {
        $commonData = [
            'user_name' => 'John Doe',
            'user_email' => 'john.doe@example.com',
            'company_name' => 'Your Company',
            'current_date' => (new \DateTime())->format('Y-m-d'),
            'current_year' => (new \DateTime())->format('Y')
        ];
        
        switch ($type) {
            case 'lead':
                return array_merge($commonData, [
                    'lead_name' => 'Jane Smith',
                    'lead_first_name' => 'Jane',
                    'lead_last_name' => 'Smith',
                    'lead_email' => 'jane.smith@example.com',
                    'lead_phone' => '555-1234',
                    'lead_company' => 'ABC Corp',
                    'lead_title' => 'Marketing Manager',
                    'lead_source' => 'Website',
                    'lead_status' => 'New'
                ]);
                
            case 'opportunity':
                return array_merge($commonData, [
                    'opportunity_name' => 'ABC Corp - Enterprise Deal',
                    'opportunity_amount' => '$50,000',
                    'opportunity_stage' => 'Proposal',
                    'opportunity_close_date' => (new \DateTime())->modify('+30 days')->format('Y-m-d'),
                    'contact_name' => 'Jane Smith',
                    'account_name' => 'ABC Corp'
                ]);
                
            case 'case':
                return array_merge($commonData, [
                    'case_number' => 'CASE-12345',
                    'case_subject' => 'Login Issue',
                    'case_status' => 'Open',
                    'case_priority' => 'High',
                    'case_description' => 'Unable to login to the system',
                    'contact_name' => 'Jane Smith',
                    'account_name' => 'ABC Corp'
                ]);
                
            default:
                return $commonData;
        }
    }
    
    /**
     * Process template with variables
     */
    private function processTemplate(string $template, array $data): string
    {
        $processed = $template;
        
        foreach ($data as $key => $value) {
            $processed = str_replace('{' . $key . '}', $value, $processed);
        }
        
        return $processed;
    }
}