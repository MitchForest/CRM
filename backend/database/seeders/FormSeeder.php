<?php

namespace Database\Seeders;

use App\Models\FormBuilderForm;
use App\Models\FormSubmission;
use Illuminate\Database\Capsule\Manager as DB;

class FormSeeder extends BaseSeeder
{
    private $forms = [
        [
            'name' => 'Request a Demo',
            'slug' => 'request-demo',
            'description' => 'Main demo request form for potential customers',
            'type' => 'demo_request',
            'fields' => [
                ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'Work Email', 'required' => true],
                ['name' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'required' => false],
                ['name' => 'employees', 'type' => 'select', 'label' => 'Company Size', 'required' => true, 
                 'options' => ['1-50', '51-200', '201-1000', '1000+']],
                ['name' => 'message', 'type' => 'textarea', 'label' => 'Tell us about your needs', 'required' => false],
            ],
            'submit_button_text' => 'Request Demo',
            'success_message' => 'Thank you! We\'ll contact you within 24 hours to schedule your demo.',
            'is_active' => true,
        ],
        [
            'name' => 'Contact Sales',
            'slug' => 'contact-sales',
            'description' => 'General sales inquiry form',
            'type' => 'contact',
            'fields' => [
                ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                ['name' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => false],
                ['name' => 'subject', 'type' => 'select', 'label' => 'Subject', 'required' => true,
                 'options' => ['Pricing', 'Features', 'Enterprise', 'Partnership', 'Other']],
                ['name' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true],
            ],
            'submit_button_text' => 'Send Message',
            'success_message' => 'Your message has been sent. Our sales team will respond shortly.',
            'is_active' => true,
        ],
        [
            'name' => 'Support Ticket',
            'slug' => 'support-ticket',
            'description' => 'Customer support request form',
            'type' => 'support',
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'label' => 'Your Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                ['name' => 'account_id', 'type' => 'text', 'label' => 'Account ID', 'required' => false],
                ['name' => 'priority', 'type' => 'select', 'label' => 'Priority', 'required' => true,
                 'options' => ['Low', 'Medium', 'High', 'Critical']],
                ['name' => 'category', 'type' => 'select', 'label' => 'Category', 'required' => true,
                 'options' => ['Technical Issue', 'Billing', 'Feature Request', 'Account Access', 'Other']],
                ['name' => 'description', 'type' => 'textarea', 'label' => 'Describe your issue', 'required' => true],
            ],
            'submit_button_text' => 'Submit Ticket',
            'success_message' => 'Support ticket created. You\'ll receive an email confirmation shortly.',
            'is_active' => true,
        ],
        [
            'name' => 'Newsletter Signup',
            'slug' => 'newsletter',
            'description' => 'Subscribe to our product updates and tips',
            'type' => 'newsletter',
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
                ['name' => 'interests', 'type' => 'checkbox', 'label' => 'Interests', 'required' => false,
                 'options' => ['Product Updates', 'Tips & Tricks', 'Webinars', 'Case Studies']],
            ],
            'submit_button_text' => 'Subscribe',
            'success_message' => 'You\'re subscribed! Check your email to confirm.',
            'is_active' => true,
        ],
        [
            'name' => 'Free Trial',
            'slug' => 'free-trial',
            'description' => 'Start your 14-day free trial',
            'type' => 'trial',
            'fields' => [
                ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'Work Email', 'required' => true],
                ['name' => 'company', 'type' => 'text', 'label' => 'Company Name', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'required' => true],
                ['name' => 'team_size', 'type' => 'number', 'label' => 'Team Size', 'required' => true],
                ['name' => 'password', 'type' => 'password', 'label' => 'Choose Password', 'required' => true],
            ],
            'submit_button_text' => 'Start Free Trial',
            'success_message' => 'Welcome to TechFlow! Check your email to activate your account.',
            'is_active' => true,
        ],
    ];
    
    public function run(): void
    {
        echo "Seeding forms and form submissions...\n";
        
        $userIds = json_decode(file_get_contents(__DIR__ . '/user_ids.json'), true);
        
        // Check if lead and contact files exist (they're created by other seeders)
        $leadIds = [];
        $contactIds = [];
        
        if (file_exists(__DIR__ . '/lead_ids.json')) {
            $leadIds = json_decode(file_get_contents(__DIR__ . '/lead_ids.json'), true);
        }
        
        if (file_exists(__DIR__ . '/contact_ids.json')) {
            $contactIds = json_decode(file_get_contents(__DIR__ . '/contact_ids.json'), true);
        }
        
        $formIds = [];
        
        // Create forms
        foreach ($this->forms as $formData) {
            $formId = $this->generateUuid();
            $createdDate = $this->randomDate('-6 months', '-3 months');
            
            $settings = [
                'submit_button_text' => $formData['submit_button_text'],
                'success_message' => $formData['success_message'],
                'type' => $formData['type'],
                'slug' => $formData['slug'],
            ];
            
            $form = [
                'id' => $formId,
                'name' => $formData['name'],
                'description' => $formData['description'],
                'fields' => json_encode($formData['fields']),
                'settings' => json_encode($settings),
                'is_active' => $formData['is_active'] ? 1 : 0,
                'created_by' => $userIds['john.smith'],
                'date_entered' => $createdDate->format('Y-m-d H:i:s'),
                'date_modified' => $createdDate->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ];
            
            DB::table('form_builder_forms')->insert($form);
            $formIds[$formData['slug']] = $formId;
            
            echo "  Created form: {$formData['name']}\n";
        }
        
        // Create form submissions
        $this->createFormSubmissions($formIds, $leadIds, $contactIds);
    }
    
    private function createFormSubmissions(array $formIds, array $leadIds, array $contactIds): void
    {
        $submissionCount = 0;
        
        // Demo requests - link to leads
        $demoFormId = $formIds['request-demo'];
        
        if (!empty($leadIds)) {
            $demoLeads = array_slice($leadIds, 0, 150); // First 150 leads came from demo requests
        } else {
            $demoLeads = [];
        }
        
        foreach ($demoLeads as $leadId) {
            $lead = DB::table('leads')->where('id', $leadId)->first();
            $submissionDate = new \DateTime($lead->date_entered);
            $submissionDate->modify('-1 hour'); // Submission happened before lead creation
            
            $formData = [
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email' => $lead->email1,
                'company' => $lead->account_name,
                'phone' => $lead->phone_work,
                'employees' => $this->faker->randomElement(['1-50', '51-200', '201-1000', '1000+']),
                'message' => 'We are looking for a project management solution for our ' . 
                             $this->faker->randomElement(['development', 'marketing', 'sales', 'operations']) . ' team.',
            ];
            
            DB::table('form_builder_submissions')->insert([
                'id' => $this->generateUuid(),
                'form_id' => $demoFormId,
                'form_data' => json_encode($formData),
                'lead_id' => $leadId,
                'contact_id' => null,
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'date_entered' => $submissionDate->format('Y-m-d H:i:s'),
                'date_modified' => $submissionDate->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);
            $submissionCount++;
        }
        
        // Contact form submissions
        $contactFormId = $formIds['contact-sales'];
        for ($i = 0; $i < 50; $i++) {
            $submissionDate = $this->randomWeekday('-5 months', 'now');
            $leadId = (!empty($leadIds) && $this->randomProbability(60)) ? $leadIds[array_rand($leadIds)] : null;
            
            $formData = [
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'email' => $this->faker->safeEmail(),
                'company' => $this->faker->company(),
                'subject' => $this->faker->randomElement(['Pricing', 'Features', 'Enterprise', 'Partnership', 'Other']),
                'message' => $this->generateInquiryMessage(),
            ];
            
            DB::table('form_builder_submissions')->insert([
                'id' => $this->generateUuid(),
                'form_id' => $contactFormId,
                'form_data' => json_encode($formData),
                'lead_id' => $leadId,
                'contact_id' => null,
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'date_entered' => $submissionDate->format('Y-m-d H:i:s'),
                'date_modified' => $submissionDate->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);
            $submissionCount++;
        }
        
        // Support tickets - link to contacts
        $supportFormId = $formIds['support-ticket'];
        
        if (!empty($contactIds)) {
            for ($i = 0; $i < 75; $i++) {
                $submissionDate = $this->randomWeekday('-3 months', 'now');
                $contactId = $contactIds[array_rand($contactIds)];
            $contact = DB::table('contacts')->where('id', $contactId)->first();
            
            $formData = [
                'name' => $contact->first_name . ' ' . $contact->last_name,
                'email' => $contact->email1,
                'account_id' => 'ACC-' . mt_rand(1000, 9999),
                'priority' => $this->faker->randomElement(['Low', 'Medium', 'High', 'Critical']),
                'category' => $this->faker->randomElement(['Technical Issue', 'Billing', 'Feature Request', 'Account Access', 'Other']),
                'description' => $this->generateSupportTicketDescription(),
            ];
            
            DB::table('form_builder_submissions')->insert([
                'id' => $this->generateUuid(),
                'form_id' => $supportFormId,
                'form_data' => json_encode($formData),
                'lead_id' => null,
                'contact_id' => $contactId,
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'date_entered' => $submissionDate->format('Y-m-d H:i:s'),
                'date_modified' => $submissionDate->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);
            $submissionCount++;
            }
        }
        
        // Newsletter signups
        $newsletterFormId = $formIds['newsletter'];
        for ($i = 0; $i < 200; $i++) {
            $submissionDate = $this->randomDate('-6 months', 'now');
            
            $formData = [
                'email' => $this->faker->unique()->safeEmail(),
                'interests' => $this->faker->randomElements(['Product Updates', 'Tips & Tricks', 'Webinars', 'Case Studies'], mt_rand(1, 3)),
            ];
            
            DB::table('form_builder_submissions')->insert([
                'id' => $this->generateUuid(),
                'form_id' => $newsletterFormId,
                'form_data' => json_encode($formData),
                'lead_id' => null,
                'contact_id' => null,
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'date_entered' => $submissionDate->format('Y-m-d H:i:s'),
                'date_modified' => $submissionDate->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);
            $submissionCount++;
        }
        
        echo "  Created {$submissionCount} form submissions\n";
    }
    
    private function generateInquiryMessage(): string
    {
        $templates = [
            'Can you send me pricing information for %d users?',
            'We\'re evaluating project management tools. How does TechFlow compare to %s?',
            'Do you offer %s? This is a requirement for our team.',
            'We need a solution that integrates with %s. Is this supported?',
            'Looking for an enterprise solution for our %s team. Can we schedule a call?',
        ];
        
        $template = $this->faker->randomElement($templates);
        
        if (strpos($template, '%d') !== false) {
            return sprintf($template, mt_rand(10, 500));
        } elseif (strpos($template, '%s') !== false) {
            $options = ['Jira', 'GitHub', 'Slack', 'SSO authentication', 'API access', 'development', 'marketing'];
            return sprintf($template, $this->faker->randomElement($options));
        }
        
        return $template;
    }
    
    private function generateSupportTicketDescription(): string
    {
        $issues = [
            'Technical Issue' => [
                'Cannot log in to my account. Password reset is not working.',
                'The dashboard is loading very slowly for our team.',
                'Export function returns an error when trying to export reports.',
                'Integration with GitHub stopped syncing yesterday.',
                'Getting 500 errors when trying to create new projects.',
            ],
            'Billing' => [
                'Please update our credit card on file.',
                'We need to add 10 more user licenses to our account.',
                'Can you send us the invoice for last month?',
                'We want to upgrade from Team to Enterprise plan.',
                'Question about the charges on our latest invoice.',
            ],
            'Feature Request' => [
                'Would love to see Gantt chart functionality added.',
                'Can you add support for custom fields on tasks?',
                'Need ability to set recurring tasks.',
                'Request for dark mode in the interface.',
                'Would like to see time tracking on mobile app.',
            ],
            'Account Access' => [
                'New employee needs access to our workspace.',
                'Please remove access for a former employee.',
                'Need to transfer admin rights to another user.',
                'Lost access after changing email address.',
                'Two-factor authentication is not working.',
            ],
        ];
        
        $category = $this->faker->randomKey($issues);
        return $this->faker->randomElement($issues[$category]);
    }
}