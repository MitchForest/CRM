<?php

namespace Database\Seeders;

use App\Models\KnowledgeBaseArticle;
use Illuminate\Database\Capsule\Manager as DB;

class KnowledgeBaseSeeder extends BaseSeeder
{
    private $articles = [
        [
            'title' => 'Getting Started with TechFlow',
            'slug' => 'getting-started-with-techflow',
            'content' => '<h2>Welcome to TechFlow!</h2><p>TechFlow is a powerful project management platform designed for modern tech teams. This guide will help you get started quickly.</p><h3>First Steps</h3><ol><li>Create your workspace</li><li>Invite your team members</li><li>Set up your first project</li><li>Create tasks and assign them</li></ol><h3>Key Features</h3><ul><li>Kanban boards for visual workflow</li><li>Sprint planning tools</li><li>Time tracking and reporting</li><li>Integration with development tools</li></ul><p>Ready to dive deeper? Check out our advanced features guide.</p>',
            'summary' => 'Learn how to get started with TechFlow project management platform in just a few minutes.',
            'category' => 'Getting Started',
            'is_published' => true,
            'view_count' => 1523,
            'helpful_count' => 287,
            'not_helpful_count' => 12,
        ],
        [
            'title' => 'Understanding Sprint Planning',
            'slug' => 'understanding-sprint-planning',
            'content' => '<h2>Sprint Planning Made Easy</h2><p>Sprint planning is crucial for agile teams. TechFlow makes it simple and effective.</p><h3>Before Your Sprint</h3><ul><li>Review your product backlog</li><li>Estimate story points</li><li>Check team capacity</li></ul><h3>During Sprint Planning</h3><ol><li>Set sprint goals</li><li>Select user stories</li><li>Break down into tasks</li><li>Assign team members</li></ol><h3>Sprint Metrics</h3><p>Track velocity, burndown charts, and team performance all in one place.</p>',
            'summary' => 'Master the art of sprint planning with TechFlow\'s comprehensive sprint management tools.',
            'category' => 'Features',
            'is_published' => true,
            'view_count' => 892,
            'helpful_count' => 156,
            'not_helpful_count' => 8,
        ],
        [
            'title' => 'Integration Guide: GitHub',
            'slug' => 'integration-guide-github',
            'content' => '<h2>Connect TechFlow with GitHub</h2><p>Seamlessly integrate your development workflow with TechFlow.</p><h3>Setup Steps</h3><ol><li>Go to Settings > Integrations</li><li>Click on GitHub</li><li>Authorize TechFlow</li><li>Select repositories</li></ol><h3>Features</h3><ul><li>Auto-link commits to tasks</li><li>View PR status in TechFlow</li><li>Sync issue status</li><li>Development insights</li></ul><h3>Best Practices</h3><p>Use branch naming conventions like "TF-123-feature-name" to auto-link.</p>',
            'summary' => 'Learn how to integrate GitHub with TechFlow for seamless development workflow.',
            'category' => 'Integrations',
            'is_published' => true,
            'view_count' => 673,
            'helpful_count' => 124,
            'not_helpful_count' => 5,
        ],
        [
            'title' => 'Time Tracking Best Practices',
            'slug' => 'time-tracking-best-practices',
            'content' => '<h2>Effective Time Tracking</h2><p>Accurate time tracking helps teams improve estimates and productivity.</p><h3>Why Track Time?</h3><ul><li>Improve project estimates</li><li>Identify bottlenecks</li><li>Bill clients accurately</li><li>Measure team productivity</li></ul><h3>Tips for Success</h3><ol><li>Use the timer for real-time tracking</li><li>Log time daily</li><li>Add descriptive notes</li><li>Review weekly reports</li></ol><h3>Reporting</h3><p>Generate detailed time reports by project, team member, or date range.</p>',
            'summary' => 'Discover how to effectively track time and improve your team\'s productivity.',
            'category' => 'Best Practices',
            'is_published' => true,
            'view_count' => 456,
            'helpful_count' => 89,
            'not_helpful_count' => 3,
        ],
        [
            'title' => 'Managing User Permissions',
            'slug' => 'managing-user-permissions',
            'content' => '<h2>User Permissions and Roles</h2><p>Control access and maintain security with granular permissions.</p><h3>Role Types</h3><ul><li><strong>Admin:</strong> Full system access</li><li><strong>Project Manager:</strong> Manage projects and teams</li><li><strong>Developer:</strong> Task management and time tracking</li><li><strong>Viewer:</strong> Read-only access</li></ul><h3>Custom Permissions</h3><p>Create custom roles with specific permissions for your organization\'s needs.</p><h3>Security Best Practices</h3><ol><li>Regular permission audits</li><li>Use principle of least privilege</li><li>Enable two-factor authentication</li></ol>',
            'summary' => 'Learn how to manage user permissions and maintain security in your TechFlow workspace.',
            'category' => 'Administration',
            'is_published' => true,
            'view_count' => 342,
            'helpful_count' => 67,
            'not_helpful_count' => 2,
        ],
        [
            'title' => 'API Documentation Overview',
            'slug' => 'api-documentation-overview',
            'content' => '<h2>TechFlow API</h2><p>Build custom integrations with our RESTful API.</p><h3>Authentication</h3><pre><code>Authorization: Bearer YOUR_API_TOKEN</code></pre><h3>Core Endpoints</h3><ul><li>GET /api/projects - List projects</li><li>POST /api/tasks - Create task</li><li>PUT /api/tasks/:id - Update task</li><li>GET /api/reports/time - Time reports</li></ul><h3>Rate Limits</h3><p>1000 requests per hour per API key. Contact support for higher limits.</p><h3>SDKs Available</h3><ul><li>JavaScript/TypeScript</li><li>Python</li><li>Ruby</li><li>PHP</li></ul>',
            'summary' => 'Complete guide to using the TechFlow API for custom integrations.',
            'category' => 'Developers',
            'is_published' => true,
            'view_count' => 567,
            'helpful_count' => 98,
            'not_helpful_count' => 7,
        ],
        [
            'title' => 'Troubleshooting Common Issues',
            'slug' => 'troubleshooting-common-issues',
            'content' => '<h2>Common Issues and Solutions</h2><h3>Login Problems</h3><p><strong>Issue:</strong> Can\'t log in<br><strong>Solution:</strong> Check caps lock, try password reset, clear browser cache.</p><h3>Performance Issues</h3><p><strong>Issue:</strong> Slow loading<br><strong>Solution:</strong> Check internet connection, try different browser, disable extensions.</p><h3>Sync Problems</h3><p><strong>Issue:</strong> Changes not syncing<br><strong>Solution:</strong> Check connection status, refresh page, contact support if persists.</p><h3>Integration Errors</h3><p><strong>Issue:</strong> GitHub not syncing<br><strong>Solution:</strong> Re-authorize integration, check webhook settings.</p>',
            'summary' => 'Quick solutions to common issues you might encounter while using TechFlow.',
            'category' => 'Support',
            'is_published' => true,
            'view_count' => 823,
            'helpful_count' => 234,
            'not_helpful_count' => 15,
        ],
        [
            'title' => 'Mobile App Features',
            'slug' => 'mobile-app-features',
            'content' => '<h2>TechFlow Mobile</h2><p>Stay productive on the go with our mobile apps.</p><h3>Key Features</h3><ul><li>View and update tasks</li><li>Track time</li><li>Receive notifications</li><li>Comment on tasks</li><li>View reports</li></ul><h3>Offline Mode</h3><p>Work offline and sync when connected. Perfect for remote locations.</p><h3>Platform Support</h3><ul><li>iOS 14+ (iPhone and iPad)</li><li>Android 8+</li></ul><h3>Download</h3><p>Available on App Store and Google Play Store.</p>',
            'summary' => 'Discover what you can do with TechFlow mobile apps for iOS and Android.',
            'category' => 'Mobile',
            'is_published' => true,
            'view_count' => 445,
            'helpful_count' => 78,
            'not_helpful_count' => 4,
        ],
        [
            'title' => 'Data Export and Backup',
            'slug' => 'data-export-and-backup',
            'content' => '<h2>Export Your Data</h2><p>Keep control of your data with comprehensive export options.</p><h3>Export Formats</h3><ul><li>CSV for spreadsheets</li><li>JSON for developers</li><li>PDF for reports</li></ul><h3>What Can Be Exported</h3><ul><li>Projects and tasks</li><li>Time tracking data</li><li>User information</li><li>Activity logs</li></ul><h3>Automated Backups</h3><p>Enterprise plans include automated daily backups with 30-day retention.</p><h3>GDPR Compliance</h3><p>Request full data export for GDPR compliance within 48 hours.</p>',
            'summary' => 'Learn how to export and backup your TechFlow data for security and compliance.',
            'category' => 'Administration',
            'is_published' => true,
            'view_count' => 289,
            'helpful_count' => 56,
            'not_helpful_count' => 1,
        ],
        [
            'title' => 'Keyboard Shortcuts Guide',
            'slug' => 'keyboard-shortcuts-guide',
            'content' => '<h2>Master TechFlow with Keyboard Shortcuts</h2><h3>Navigation</h3><ul><li><code>G + P</code> - Go to projects</li><li><code>G + T</code> - Go to tasks</li><li><code>G + R</code> - Go to reports</li></ul><h3>Task Management</h3><ul><li><code>N</code> - New task</li><li><code>E</code> - Edit selected task</li><li><code>Space</code> - Toggle task complete</li><li><code>Del</code> - Delete task</li></ul><h3>Quick Actions</h3><ul><li><code>Cmd/Ctrl + K</code> - Quick search</li><li><code>T</code> - Start/stop timer</li><li><code>?</code> - Show all shortcuts</li></ul>',
            'summary' => 'Speed up your workflow with TechFlow keyboard shortcuts.',
            'category' => 'Tips & Tricks',
            'is_published' => true,
            'view_count' => 378,
            'helpful_count' => 92,
            'not_helpful_count' => 2,
        ],
        [
            'title' => 'Team Collaboration Features',
            'slug' => 'team-collaboration-features',
            'content' => '<h2>Collaborate Effectively</h2><p>TechFlow brings teams together with powerful collaboration tools.</p><h3>Real-time Updates</h3><p>See changes as they happen with live updates across all devices.</p><h3>Comments and Mentions</h3><ul><li>@mention team members</li><li>Thread discussions</li><li>File attachments</li><li>Rich text formatting</li></ul><h3>Activity Feed</h3><p>Stay informed with a centralized activity feed showing all project updates.</p><h3>Video Conferencing</h3><p>Start instant video calls right from tasks with our Zoom integration.</p>',
            'summary' => 'Explore TechFlow\'s collaboration features that keep your team connected and productive.',
            'category' => 'Features',
            'is_published' => true,
            'view_count' => 512,
            'helpful_count' => 103,
            'not_helpful_count' => 6,
        ],
        [
            'title' => 'Custom Fields and Workflows',
            'slug' => 'custom-fields-and-workflows',
            'content' => '<h2>Customize TechFlow for Your Team</h2><h3>Custom Fields</h3><p>Add custom fields to capture information specific to your workflow:</p><ul><li>Text, number, date fields</li><li>Dropdown selections</li><li>Multi-select options</li><li>User assignments</li></ul><h3>Workflow Automation</h3><p>Create rules to automate repetitive tasks:</p><ul><li>Auto-assign based on criteria</li><li>Status change triggers</li><li>Due date reminders</li><li>Custom notifications</li></ul><h3>Templates</h3><p>Save time with reusable project and task templates.</p>',
            'summary' => 'Learn how to customize TechFlow with custom fields and automated workflows.',
            'category' => 'Advanced',
            'is_published' => true,
            'view_count' => 234,
            'helpful_count' => 45,
            'not_helpful_count' => 3,
        ],
    ];
    
    public function run(): void
    {
        echo "Seeding knowledge base articles...\n";
        
        $userIds = json_decode(file_get_contents(__DIR__ . '/user_ids.json'), true);
        $adminId = $userIds['john.smith'];
        
        foreach ($this->articles as $index => $articleData) {
            $createdDate = $this->randomDate('-5 months', '-1 week');
            $lastModified = $this->randomDate($createdDate->format('Y-m-d'), '-1 day');
            
            $article = array_merge($articleData, [
                'id' => $this->generateUuid(),
                'author_id' => $adminId,
                'date_entered' => $createdDate->format('Y-m-d H:i:s'),
                'date_modified' => $lastModified->format('Y-m-d H:i:s'),
                'date_published' => $articleData['is_published'] ? $createdDate->format('Y-m-d H:i:s') : null,
                'deleted' => 0,
                'is_featured' => $this->randomProbability(20), // 20% chance of being featured
                'tags' => json_encode($this->generateTags($articleData['category'])),
            ]);
            
            DB::table('knowledge_base_articles')->insert($article);
            echo "  Created article: {$articleData['title']}\n";
        }
    }
    
    private function generateTags(string $category): array
    {
        $tagPool = [
            'Getting Started' => ['onboarding', 'basics', 'tutorial', 'guide', 'quickstart'],
            'Features' => ['features', 'functionality', 'capabilities', 'tools', 'workflow'],
            'Integrations' => ['integration', 'api', 'connect', 'sync', 'third-party'],
            'Best Practices' => ['tips', 'best-practices', 'productivity', 'efficiency', 'workflow'],
            'Administration' => ['admin', 'settings', 'configuration', 'management', 'security'],
            'Developers' => ['api', 'development', 'code', 'technical', 'integration'],
            'Support' => ['troubleshooting', 'help', 'support', 'issues', 'problems'],
            'Mobile' => ['mobile', 'ios', 'android', 'app', 'offline'],
            'Tips & Tricks' => ['tips', 'shortcuts', 'productivity', 'efficiency', 'tricks'],
            'Advanced' => ['advanced', 'power-user', 'automation', 'custom', 'workflow'],
        ];
        
        $tags = $tagPool[$category] ?? ['general'];
        // Pick 2-4 tags
        $selectedTags = array_rand($tags, mt_rand(2, min(4, count($tags))));
        if (!is_array($selectedTags)) {
            $selectedTags = [$selectedTags];
        }
        
        return array_map(function($index) use ($tags) {
            return $tags[$index];
        }, $selectedTags);
    }
}