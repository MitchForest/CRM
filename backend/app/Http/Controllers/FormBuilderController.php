<?php

namespace App\Http\Controllers;

use App\Models\FormBuilderForm;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\ActivityTrackingVisitor;
use App\Services\Forms\FormBuilderService;
use App\Services\CRM\LeadService;
use App\Services\Tracking\ActivityTrackingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class FormBuilderController extends Controller
{
    private FormBuilderService $formService;
    
    public function __construct()
    {
        parent::__construct();
        // Temporarily disable service dependencies for testing
        // $leadService = new LeadService();
        // $trackingService = new ActivityTrackingService();
        // $this->formService = new FormBuilderService($leadService, $trackingService);
    }
    
    /**
     * Get all forms
     * GET /api/v8/forms
     */
    public function getForms(Request $request, Response $response, array $args): Response
    {
        $query = FormBuilderForm::where('deleted', 0);
        
        $params = $request->getQueryParams();
        
        // Filter by active status
        if (isset($params['is_active'])) {
            $query->where('is_active', (bool)$params['is_active']);
        }
        
        // Pagination
        $page = intval($params['page'] ?? 1);
        $limit = min(intval($params['limit'] ?? 20), 100);
        
        $forms = $query->orderBy('date_modified', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);
        
        // Format response
        $data = $forms->map(function ($form) {
            return [
                'id' => $form->id,
                'name' => $form->name,
                'description' => $form->description,
                'fields' => $form->fields,
                'settings' => $form->settings,
                'embed_code' => $form->embed_code,
                'is_active' => $form->is_active,
                'created_by' => $form->created_by,
                'date_entered' => $form->date_entered ? (new \DateTime($form->date_entered))->format('c') : null,
                'date_modified' => $form->date_modified ? (new \DateTime($form->date_modified))->format('c') : null
            ];
        });
        
        return $this->json($response, [
            'data' => $data,
            'meta' => [
                'total' => $forms->total(),
                'page' => $forms->currentPage(),
                'limit' => $forms->perPage(),
                'pages' => $forms->lastPage()
            ]
        ]);
    }
    
    /**
     * Get single form
     * GET /api/v8/forms/{id}
     */
    public function getForm(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found', 404);
        }
        
        // Get submission stats
        $stats = FormSubmission::where('form_id', $id)
            ->where('deleted', 0)
            ->select(DB::raw('
                COUNT(*) as total_submissions,
                COUNT(DISTINCT lead_id) as unique_leads,
                MAX(date_entered) as last_submission
            '))
            ->first();
        
        return $this->json($response, [
            'data' => [
                'id' => $form->id,
                'name' => $form->name,
                'description' => $form->description,
                'fields' => $form->fields,
                'settings' => $form->settings,
                'embed_code' => $form->embed_code,
                'is_active' => $form->is_active,
                'created_by' => $form->created_by,
                'date_entered' => $form->date_entered ? (new \DateTime($form->date_entered))->format('c') : null,
                'date_modified' => $form->date_modified ? (new \DateTime($form->date_modified))->format('c') : null,
                'stats' => [
                    'total_submissions' => $stats->total_submissions ?? 0,
                    'unique_leads' => $stats->unique_leads ?? 0,
                    'last_submission' => $stats->last_submission
                ]
            ]
        ]);
    }
    
    /**
     * Create new form
     * POST /api/v8/forms
     */
    public function createForm(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'fields' => 'required|array|max:50',
            'fields.*.type' => 'required|string|in:text,email,phone,textarea,select,checkbox,radio,file,hidden',
            'fields.*.name' => 'required|string',
            'fields.*.label' => 'required|string',
            'fields.*.required' => 'sometimes|boolean',
            'fields.*.options' => 'required_if:fields.*.type,select,checkbox,radio|array',
            'settings' => 'sometimes|array'
        ]);
        
        $embedCode = $this->generateEmbedCode();
        
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
        
        $settings = array_merge($defaultSettings, $data['settings'] ?? []);
        
        $form = FormBuilderForm::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'fields' => $data['fields'],
            'settings' => $settings,
            'embed_code' => $embedCode,
            'is_active' => true,
            'created_by' => $request->getAttribute('user_id') ?? '1'
        ]);
        
        return $this->json($response, [
            'data' => [
                'id' => $form->id,
                'name' => $form->name,
                'embed_code' => $form->embed_code,
                'created' => true
            ]
        ], 201);
    }
    
    /**
     * Update form
     * PUT /api/v8/forms/{id}
     */
    public function updateForm(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found', 404);
        }
        
        $data = $this->validate($request, [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'fields' => 'sometimes|array|max:50',
            'fields.*.type' => 'required|string|in:text,email,phone,textarea,select,checkbox,radio,file,hidden',
            'fields.*.name' => 'required|string',
            'fields.*.label' => 'required|string',
            'settings' => 'sometimes|array',
            'is_active' => 'sometimes|boolean'
        ]);
        
        if (isset($data['name'])) {
            $form->name = $data['name'];
        }
        
        if (isset($data['description'])) {
            $form->description = $data['description'];
        }
        
        if (isset($data['fields'])) {
            $form->fields = $data['fields'];
        }
        
        if (isset($data['settings'])) {
            $form->settings = array_merge($form->settings ?? [], $data['settings']);
        }
        
        if (isset($data['is_active'])) {
            $form->is_active = (bool)$data['is_active'];
        }
        
        $form->save();
        
        return $this->json($response, [
            'data' => [
                'id' => $form->id,
                'updated' => true
            ]
        ]);
    }
    
    /**
     * Delete form
     * DELETE /api/v8/forms/{id}
     */
    public function deleteForm(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found', 404);
        }
        
        $form->deleted = 1;
        $form->save();
        
        return $this->json($response, [
            'message' => 'Form deleted successfully'
        ]);
    }
    
    /**
     * Get form submissions
     * GET /api/v8/forms/{id}/submissions
     */
    public function getSubmissions(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found', 404);
        }
        
        $query = FormSubmission::with(['lead', 'contact'])
            ->where('form_id', $id)
            ->where('deleted', 0);
        
        $params = $request->getQueryParams();
        
        // Date filter
        if (isset($params['start_date'])) {
            $query->where('date_entered', '>=', $params['start_date']);
        }
        
        if (isset($params['end_date'])) {
            $query->where('date_entered', '<=', $params['end_date']);
        }
        
        // Pagination
        $page = intval($params['page'] ?? 1);
        $limit = min(intval($params['limit'] ?? 20), 100);
        
        $submissions = $query->orderBy('date_entered', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);
        
        // Format response
        $data = $submissions->map(function ($submission) {
            return [
                'id' => $submission->id,
                'form_id' => $submission->form_id,
                'lead_id' => $submission->lead_id,
                'contact_id' => $submission->contact_id,
                'data' => $submission->data,
                'metadata' => $submission->data['metadata'] ?? [],
                'date_submitted' => $submission->date_entered?->toIso8601String(),
                'lead' => $submission->lead ? [
                    'id' => $submission->lead->id,
                    'name' => $submission->lead->full_name,
                    'email' => $submission->lead->email1,
                    'company' => $submission->lead->account_name
                ] : null,
                'contact' => $submission->contact ? [
                    'id' => $submission->contact->id,
                    'name' => $submission->contact->full_name,
                    'email' => $submission->contact->email1
                ] : null
            ];
        });
        
        return $this->json($response, [
            'data' => $data,
            'meta' => [
                'total' => $submissions->total(),
                'page' => $submissions->currentPage(),
                'limit' => $submissions->perPage(),
                'pages' => $submissions->lastPage()
            ]
        ]);
    }
    
    /**
     * Submit form (public endpoint)
     * POST /api/v8/forms/{id}/submit
     */
    public function submitForm(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)
            ->where('is_active', 1)
            ->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found or inactive', 404);
        }
        
        // Validate submission against form fields
        $rules = [];
        foreach ($form->fields as $field) {
            $fieldName = $field['name'];
            $fieldRules = [];
            
            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'sometimes';
            }
            
            switch ($field['type']) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'phone':
                    $fieldRules[] = 'string|regex:/^[\d\s\-\+\(\)]+$/';
                    break;
                case 'file':
                    $fieldRules[] = 'file|max:10240'; // 10MB max
                    break;
                default:
                    $fieldRules[] = 'string';
            }
            
            $rules[$fieldName] = implode('|', $fieldRules);
        }
        
        $data = $this->validate($request, $rules);
        
        DB::beginTransaction();
        
        try {
            // Extract form data
            $formData = [];
            foreach ($form->fields as $field) {
                $fieldName = $field['name'];
                if (isset($data[$fieldName])) {
                    $formData[$fieldName] = $data[$fieldName];
                }
            }
            
            // Capture metadata
            $metadata = [
                'ip_address' => $request->getServerParams()['REMOTE_ADDR'] ?? '',
                'user_agent' => $request->getHeaderLine('User-Agent'),
                'referrer' => $request->getHeaderLine('Referer'),
                'submission_time' => (new \DateTime())->format('c')
            ];
            
            // Capture UTM parameters if enabled
            if ($form->settings['capture_utm'] ?? true) {
                $utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
                $params = $request->getQueryParams();
                foreach ($utmParams as $param) {
                    if (isset($params[$param])) {
                        $metadata[$param] = $params[$param];
                    }
                }
            }
            
            // Try to identify visitor
            $visitorId = $data['visitor_id'] ?? null;
            $visitor = null;
            if ($visitorId) {
                $visitor = ActivityTrackingVisitor::where('visitor_id', $visitorId)->first();
            }
            
            // Create or find lead
            $leadId = null;
            if (isset($formData['email'])) {
                $lead = Lead::where('email1', $formData['email'])
                    ->where('deleted', 0)
                    ->first();
                
                if (!$lead) {
                    $lead = Lead::create([
                        'first_name' => $formData['first_name'] ?? '',
                        'last_name' => $formData['last_name'] ?? '',
                        'email1' => $formData['email'],
                        'phone_work' => $formData['phone'] ?? '',
                        'account_name' => $formData['company'] ?? '',
                        'lead_source' => 'Web Form',
                        'status' => 'New',
                        'assigned_user_id' => '1',
                        'description' => "Form submission: {$form->name}\nSubmitted: " . (new \DateTime())->format('Y-m-d H:i:s')
                    ]);
                }
                
                $leadId = $lead->id;
                
                // Link visitor to lead
                if ($visitor) {
                    $visitor->update(['lead_id' => $leadId]);
                }
            }
            
            // Save submission
            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'lead_id' => $leadId,
                'visitor_id' => $visitor?->visitor_id,
                'data' => array_merge($formData, ['metadata' => $metadata]),
                'date_entered' => new \DateTime()
            ]);
            
            // Send notification email if configured
            if (!empty($form->settings['notification_email'])) {
                // TODO: Implement email notification
                error_log("Form notification email would be sent to: {$form->settings['notification_email']}");
            }
            
            DB::commit();
            
            // Prepare response
            $responseData = [
                'success' => true,
                'message' => $form->settings['success_message'] ?? 'Thank you for your submission!'
            ];
            
            // Add redirect URL if configured
            if (!empty($form->settings['redirect_url'])) {
                $responseData['redirect_url'] = $form->settings['redirect_url'];
            }
            
            return $this->json($response, $responseData);
            
        } catch (\Exception $e) {
            DB::rollBack();
            error_log('Form submission error: ' . $e->getMessage());
            
            return $this->error($response, 'Failed to process submission. Please try again later.', 500);
        }
    }
    
    /**
     * Get form embed code
     * GET /api/v8/forms/{id}/embed
     */
    public function getEmbedCode(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found', 404);
        }
        
        // Generate embed HTML inline for now
        $apiUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        $embedHtml = <<<HTML
<!-- Form Embed Code -->
<div id="form-{$form->embed_code}"></div>
<script>
(function() {
    var script = document.createElement('script');
    script.src = '{$apiUrl}/js/forms-embed.js';
    script.onload = function() {
        if (window.FormEmbed) {
            window.FormEmbed.init({
                formId: '{$form->id}',
                embedCode: '{$form->embed_code}',
                apiUrl: '{$apiUrl}/api/public/forms'
            });
        }
    };
    document.head.appendChild(script);
})();
</script>
<!-- End Form Embed Code -->
HTML;
        
        return $this->json($response, [
            'data' => [
                'form_id' => $form->id,
                'embed_code' => $form->embed_code,
                'html' => $embedHtml,
                'script_url' => '/js/forms-embed.js',
                'api_endpoint' => "/api/public/forms/{$form->id}/submit"
            ]
        ]);
    }
    
    /**
     * Export form submissions
     * GET /api/v8/forms/{id}/export
     */
    public function exportSubmissions(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found', 404);
        }
        
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'csv';
        
        if (!in_array($format, ['csv', 'xlsx', 'json'])) {
            return $this->error($response, 'Invalid export format', 400);
        }
        
        $submissions = FormSubmission::where('form_id', $id)
            ->where('deleted', 0)
            ->orderBy('date_entered', 'DESC')
            ->get();
        
        // Simple export implementation for now
        $filename = "form-{$form->id}-submissions-" . date('Y-m-d-His');
        $exportData = [
            'filename' => "{$filename}.{$format}",
            'url' => "/api/exports/{$filename}.{$format}",
            'expires_at' => (new \DateTime())->modify('+1 hour')->format('c')
        ];
        
        return $this->json($response, [
            'data' => [
                'format' => $format,
                'filename' => $exportData['filename'],
                'download_url' => $exportData['url'],
                'expires_at' => $exportData['expires_at']
            ]
        ]);
    }
    
    /**
     * Get public form for embedding
     * GET /api/public/forms/{id}
     */
    public function getPublicForm(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)
            ->where('is_active', 1)
            ->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found or inactive', 404);
        }
        
        return $this->json($response, [
            'data' => [
                'id' => $form->id,
                'name' => $form->name,
                'description' => $form->description,
                'fields' => $form->fields,
                'settings' => [
                    'submit_button_text' => $form->settings['submit_button_text'] ?? 'Submit',
                    'success_message' => $form->settings['success_message'] ?? 'Thank you!',
                    'custom_css' => $form->settings['custom_css'] ?? ''
                ]
            ]
        ]);
    }
    
    /**
     * Duplicate a form
     * POST /api/admin/forms/{id}/duplicate
     */
    public function duplicateForm(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found', 404);
        }
        
        $newForm = $form->replicate();
        $newForm->name = $form->name . ' (Copy)';
        $newForm->embed_code = $this->generateEmbedCode();
        $newForm->created_by = $request->getAttribute('user_id');
        $newForm->date_entered = new \DateTime();
        $newForm->date_modified = new \DateTime();
        $newForm->save();
        
        return $this->json($response, [
            'data' => [
                'id' => $newForm->id,
                'name' => $newForm->name,
                'embed_code' => $newForm->embed_code
            ]
        ], 201);
    }
    
    /**
     * Get form analytics
     * GET /api/admin/forms/{id}/analytics
     */
    public function getFormAnalytics(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $form = FormBuilderForm::where('deleted', 0)->find($id);
        
        if (!$form) {
            return $this->error($response, 'Form not found', 404);
        }
        
        $params = $request->getQueryParams();
        $startDate = $params['start_date'] ?? (new \DateTime())->modify('-30 days')->format('Y-m-d');
        $endDate = $params['end_date'] ?? (new \DateTime())->format('Y-m-d');
        
        // Get submission analytics
        $analytics = DB::table('form_submissions')
            ->where('form_id', $id)
            ->where('deleted', 0)
            ->whereBetween('date_entered', [$startDate, $endDate])
            ->select(DB::raw('
                COUNT(*) as total_submissions,
                COUNT(DISTINCT lead_id) as unique_leads,
                COUNT(DISTINCT visitor_id) as unique_visitors,
                DATE(date_entered) as submission_date
            '))
            ->groupBy('submission_date')
            ->orderBy('submission_date')
            ->get();
        
        // Get field completion rates
        $fieldStats = [];
        $submissions = FormSubmission::where('form_id', $id)
            ->where('deleted', 0)
            ->whereBetween('date_entered', [$startDate, $endDate])
            ->get();
        
        if ($submissions->count() > 0) {
            foreach ($form->fields as $field) {
                $fieldName = $field['name'];
                $completed = 0;
                
                foreach ($submissions as $submission) {
                    if (!empty($submission->data[$fieldName])) {
                        $completed++;
                    }
                }
                
                $fieldStats[] = [
                    'field' => $fieldName,
                    'label' => $field['label'],
                    'completion_rate' => round(($completed / $submissions->count()) * 100, 2)
                ];
            }
        }
        
        return $this->json($response, [
            'data' => [
                'form_id' => $form->id,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'summary' => [
                    'total_submissions' => $submissions->count(),
                    'unique_leads' => $submissions->unique('lead_id')->count(),
                    'conversion_rate' => 0 // Would need visitor tracking data
                ],
                'daily_submissions' => $analytics,
                'field_completion' => $fieldStats
            ]
        ]);
    }
    
    /**
     * Get embed script
     * GET /api/public/forms-embed.js
     */
    public function getEmbedScript(Request $request, Response $response, array $args): Response
    {
        $script = file_get_contents(__DIR__ . '/../../../public/js/forms-embed.js');
        
        $response->getBody()->write($script);
        return $response
            ->withHeader('Content-Type', 'application/javascript')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
    
    private function generateEmbedCode(): string
    {
        return 'form_' . bin2hex(random_bytes(8));
    }
}