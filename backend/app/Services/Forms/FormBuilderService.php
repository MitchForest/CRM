<?php

namespace App\Services\Forms;

use App\Models\FormBuilderForm;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Services\CRM\LeadService;
use App\Services\Tracking\ActivityTrackingService;
use Illuminate\Support\Str;

class FormBuilderService
{
    public function __construct(
        private LeadService $leadService,
        private ActivityTrackingService $trackingService
    ) {}
    
    /**
     * Create a new form
     */
    public function createForm(array $data): FormBuilderForm
    {
        $formId = Str::slug($data['name']) . '-' . Str::random(6);
        
        $form = FormBuilderForm::create([
            'name' => $data['name'],
            'form_id' => $formId,
            'fields' => $data['fields'],
            'settings' => array_merge([
                'submit_button_text' => 'Submit',
                'success_message' => 'Thank you for your submission!',
                'redirect_url' => null,
                'notification_email' => null,
                'styling' => [
                    'theme' => 'default',
                    'primary_color' => '#4F46E5',
                    'font' => 'Inter'
                ]
            ], $data['settings'] ?? []),
            'status' => 'active',
            'created_by' => $data['created_by'] ?? null
        ]);
        
        // Generate embed code
        $form->update(['embed_code' => $form->generateEmbedCode()]);
        
        return $form;
    }
    
    /**
     * Update a form
     */
    public function updateForm(string $id, array $data): FormBuilderForm
    {
        $form = FormBuilderForm::findOrFail($id);
        
        $form->update([
            'name' => $data['name'] ?? $form->name,
            'fields' => $data['fields'] ?? $form->fields,
            'settings' => array_merge($form->settings, $data['settings'] ?? []),
            'status' => $data['status'] ?? $form->status,
            'updated_by' => $data['updated_by'] ?? null
        ]);
        
        return $form;
    }
    
    /**
     * Process form submission
     */
    public function processSubmission(string $formId, array $data, array $context = []): FormSubmission
    {
        $form = FormBuilderForm::where('form_id', $formId)->firstOrFail();
        
        if (!$form->isActive()) {
            throw new \Exception('Form is not active');
        }
        
        // Validate required fields
        $this->validateSubmission($form, $data);
        
        // Create submission
        $submission = FormSubmission::create([
            'form_id' => $formId,
            'data' => $data,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'referrer' => $context['referrer'] ?? null,
            'created_at' => new \DateTime()
        ]);
        
        // Track event
        if (!empty($context['session_id'])) {
            $this->trackingService->trackEvent([
                'session_id' => $context['session_id'],
                'event_type' => 'form_submission',
                'form_id' => $formId
            ]);
        }
        
        // Create or update lead if form has email field
        $lead = $this->processLead($form, $submission, $context);
        if ($lead) {
            $submission->update(['lead_id' => $lead->id]);
        }
        
        // Send notifications
        $this->sendNotifications($form, $submission);
        
        return $submission;
    }
    
    /**
     * Validate form submission
     */
    private function validateSubmission(FormBuilderForm $form, array $data): void
    {
        $errors = [];
        
        foreach ($form->fields as $field) {
            $fieldName = $field['name'];
            $fieldValue = $data[$fieldName] ?? null;
            
            // Check required
            if ($field['required'] ?? false) {
                if (empty($fieldValue)) {
                    $errors[$fieldName] = "{$field['label']} is required";
                }
            }
            
            // Type validation
            if (!empty($fieldValue)) {
                switch ($field['type']) {
                    case 'email':
                        if (!filter_var($fieldValue, FILTER_VALIDATE_EMAIL)) {
                            $errors[$fieldName] = "{$field['label']} must be a valid email";
                        }
                        break;
                    
                    case 'phone':
                        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $fieldValue)) {
                            $errors[$fieldName] = "{$field['label']} must be a valid phone number";
                        }
                        break;
                    
                    case 'url':
                        if (!filter_var($fieldValue, FILTER_VALIDATE_URL)) {
                            $errors[$fieldName] = "{$field['label']} must be a valid URL";
                        }
                        break;
                }
            }
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException(json_encode($errors));
        }
    }
    
    /**
     * Process lead from form submission
     */
    private function processLead(FormBuilderForm $form, FormSubmission $submission, array $context): ?Lead
    {
        // Map form fields to lead fields
        $fieldMapping = [
            'email' => 'email',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'name' => 'full_name',
            'company' => 'company',
            'phone' => 'phone',
            'title' => 'title',
            'website' => 'website'
        ];
        
        $leadData = [];
        foreach ($fieldMapping as $formField => $leadField) {
            $value = $submission->getFieldValue($formField);
            if ($value) {
                $leadData[$leadField] = $value;
            }
        }
        
        // Handle full name
        if (!empty($leadData['full_name']) && empty($leadData['first_name'])) {
            $parts = explode(' ', $leadData['full_name'], 2);
            $leadData['first_name'] = $parts[0];
            $leadData['last_name'] = $parts[1] ?? '';
            unset($leadData['full_name']);
        }
        
        // Need at least email or name to create lead
        if (empty($leadData['email']) && empty($leadData['first_name'])) {
            return null;
        }
        
        // Check for existing lead
        $lead = null;
        if (!empty($leadData['email'])) {
            $lead = Lead::where('email', $leadData['email'])->first();
        }
        
        if ($lead) {
            // Update existing lead
            $lead->update(array_filter($leadData));
        } else {
            // Create new lead
            $leadData['source'] = 'Form: ' . $form->name;
            $leadData['status'] = 'new';
            $leadData['visitor_id'] = $context['visitor_id'] ?? null;
            
            $lead = $this->leadService->create($leadData);
        }
        
        return $lead;
    }
    
    /**
     * Send form notifications
     */
    private function sendNotifications(FormBuilderForm $form, FormSubmission $submission): void
    {
        $settings = $form->settings;
        
        // Email notification to admin
        if (!empty($settings['notification_email'])) {
            // In production, implement email sending
            // For now, just log
            error_log("Form submission notification - form: {$form->name}, to: {$settings['notification_email']}, submission_id: {$submission->id}");
        }
        
        // Auto-responder to submitter
        if (!empty($settings['auto_responder']) && $submission->getFieldValue('email')) {
            // In production, send auto-response email
            $email = $submission->getFieldValue('email');
            $subject = $settings['auto_responder']['subject'] ?? 'Thank you for your submission';
            error_log("Auto-responder email - to: {$email}, subject: {$subject}");
        }
    }
    
    /**
     * Get form analytics
     */
    public function getFormAnalytics(string $formId, array $dateRange = []): array
    {
        $form = FormBuilderForm::where('form_id', $formId)->firstOrFail();
        
        $query = $form->submissions();
        
        if (!empty($dateRange['start'])) {
            $query->where('created_at', '>=', $dateRange['start']);
        }
        if (!empty($dateRange['end'])) {
            $query->where('created_at', '<=', $dateRange['end']);
        }
        
        $submissions = $query->get();
        
        return [
            'total_submissions' => $submissions->count(),
            'unique_leads' => $submissions->whereNotNull('lead_id')->pluck('lead_id')->unique()->count(),
            'submissions_by_day' => $submissions->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            })->map->count(),
            'top_referrers' => $submissions->groupBy('referrer')->map->count()->sortDesc()->take(5),
            'field_completion_rates' => $this->calculateFieldCompletionRates($form, $submissions),
            'conversion_rate' => $this->calculateConversionRate($form, $dateRange)
        ];
    }
    
    /**
     * Calculate field completion rates
     */
    private function calculateFieldCompletionRates(FormBuilderForm $form, $submissions): array
    {
        $rates = [];
        
        foreach ($form->fields as $field) {
            $fieldName = $field['name'];
            $completed = $submissions->filter(function ($submission) use ($fieldName) {
                return !empty($submission->getFieldValue($fieldName));
            })->count();
            
            $rates[$fieldName] = [
                'label' => $field['label'],
                'completion_rate' => $submissions->count() > 0 
                    ? round(($completed / $submissions->count()) * 100, 1) 
                    : 0
            ];
        }
        
        return $rates;
    }
    
    /**
     * Calculate conversion rate (views to submissions)
     */
    private function calculateConversionRate(FormBuilderForm $form, array $dateRange): ?float
    {
        // This would need form view tracking
        // For now, return null
        return null;
    }
}