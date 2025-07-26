<?php
require_once('include/utils.php');

echo "🚀 Seeding Knowledge Base with essential content...\n\n";

global $db, $current_user;

// Create categories
$categories = [
    ['name' => 'Getting Started', 'slug' => 'getting-started', 'icon' => 'book-open', 'sort_order' => 1],
    ['name' => 'Features', 'slug' => 'features', 'icon' => 'layers', 'sort_order' => 2],
    ['name' => 'Installation', 'slug' => 'installation', 'icon' => 'download', 'sort_order' => 3],
    ['name' => 'FAQ', 'slug' => 'faq', 'icon' => 'help-circle', 'sort_order' => 4],
];

$categoryIds = [];
foreach ($categories as $cat) {
    $id = create_guid();
    $categoryIds[$cat['slug']] = $id;
    
    $query = "INSERT INTO kb_categories (id, name, slug, description, icon, sort_order, created_by, date_created, date_modified, deleted)
             VALUES (
                '$id',
                '{$cat['name']}',
                '{$cat['slug']}',
                'Articles about {$cat['name']}',
                '{$cat['icon']}',
                {$cat['sort_order']},
                '1',
                NOW(),
                NOW(),
                0
             )";
    
    $db->query($query);
    echo "✅ Created category: {$cat['name']}\n";
}

// Create articles
$articles = [
    // Getting Started
    [
        'category' => 'getting-started',
        'title' => 'Welcome to AI CRM',
        'slug' => 'welcome-to-ai-crm',
        'excerpt' => 'Learn how AI CRM can transform your sales process with intelligent automation.',
        'content' => '<h2>Welcome to AI CRM</h2>
<p>AI CRM is a powerful, self-hosted customer relationship management system enhanced with artificial intelligence capabilities. Built on the proven SuiteCRM foundation, we\'ve added modern AI features that actually help you sell more.</p>

<h3>What Makes AI CRM Different?</h3>
<ul>
<li><strong>AI Lead Scoring</strong> - Automatically qualify leads with GPT-4 powered analysis</li>
<li><strong>Intelligent Chatbot</strong> - Capture and qualify leads 24/7</li>
<li><strong>Activity Tracking</strong> - See what prospects are doing in real-time</li>
<li><strong>Self-Hosted</strong> - Your data stays on your servers</li>
</ul>

<h3>Getting Started</h3>
<p>Follow these steps to get the most out of AI CRM:</p>
<ol>
<li>Set up your OpenAI API key for AI features</li>
<li>Import your existing leads and contacts</li>
<li>Create your first form to capture leads</li>
<li>Install the tracking script on your website</li>
<li>Configure the chatbot with your FAQs</li>
</ol>

<h3>Need Help?</h3>
<p>Check out our other articles or use the chatbot in the bottom right corner for instant assistance!</p>',
        'is_featured' => 1,
    ],
    
    // Features
    [
        'category' => 'features',
        'title' => 'AI Lead Scoring Explained',
        'slug' => 'ai-lead-scoring-explained',
        'excerpt' => 'Understand how our AI analyzes and scores your leads automatically.',
        'content' => '<h2>AI Lead Scoring Explained</h2>
<p>Our AI lead scoring system uses GPT-4 to analyze multiple factors and automatically qualify your leads. No more manual scoring or guesswork!</p>

<h3>How It Works</h3>
<p>The AI analyzes 20+ signals including:</p>
<ul>
<li><strong>Company Information</strong> - Size, industry, technology stack</li>
<li><strong>Behavioral Signals</strong> - Website activity, email engagement, form submissions</li>
<li><strong>Demographic Data</strong> - Job title, seniority, decision-making authority</li>
<li><strong>Intent Signals</strong> - Content consumed, search terms, chatbot interactions</li>
</ul>

<h3>Scoring Factors</h3>
<p>Each lead receives scores in these categories:</p>
<ol>
<li><strong>Company Fit (0-20)</strong> - How well they match your ideal customer profile</li>
<li><strong>Budget Signals (0-20)</strong> - Indicators of purchasing power</li>
<li><strong>Authority (0-20)</strong> - Decision-making capability</li>
<li><strong>Need (0-20)</strong> - Problem-solution fit</li>
<li><strong>Timeline (0-20)</strong> - Urgency and buying timeframe</li>
</ol>

<h3>Using AI Scores</h3>
<p>Leads are automatically categorized:</p>
<ul>
<li><strong>Hot (80-100)</strong> - Ready to buy, contact immediately</li>
<li><strong>Warm (60-79)</strong> - Interested, nurture with targeted content</li>
<li><strong>Cool (40-59)</strong> - Early stage, add to drip campaigns</li>
<li><strong>Cold (0-39)</strong> - Not qualified, revisit later</li>
</ul>

<h3>Best Practices</h3>
<ul>
<li>Review and adjust scores based on actual conversions</li>
<li>Use scores to prioritize outreach efforts</li>
<li>Set up alerts for high-scoring leads</li>
<li>Track score changes over time</li>
</ul>',
        'is_featured' => 1,
    ],
    
    [
        'category' => 'features',
        'title' => 'Setting Up the AI Chatbot',
        'slug' => 'setting-up-ai-chatbot',
        'excerpt' => 'Configure your intelligent chatbot to capture and qualify leads automatically.',
        'content' => '<h2>Setting Up the AI Chatbot</h2>
<p>The AI chatbot is your 24/7 sales assistant that can answer questions, capture leads, and even schedule demos.</p>

<h3>Initial Configuration</h3>
<ol>
<li>Navigate to <strong>Settings > Chatbot</strong> in your CRM</li>
<li>Customize the welcome message and appearance</li>
<li>Set your business hours for human handoff</li>
<li>Configure lead capture fields</li>
</ol>

<h3>Training Your Chatbot</h3>
<p>The chatbot learns from your knowledge base articles. To improve responses:</p>
<ul>
<li>Add comprehensive FAQ articles</li>
<li>Include product/service descriptions</li>
<li>Document common objections and responses</li>
<li>Update pricing and feature information regularly</li>
</ul>

<h3>Embedding on Your Website</h3>
<pre><code>&lt;!-- Add before closing &lt;/body&gt; tag --&gt;
&lt;script src="https://your-crm.com/js/chat-widget.js"&gt;&lt;/script&gt;
&lt;script&gt;
  AIChat.init({
    position: \'bottom-right\',
    primaryColor: \'#3B82F6\',
    companyName: \'Your Company\'
  });
&lt;/script&gt;</code></pre>

<h3>Lead Qualification</h3>
<p>The chatbot automatically:</p>
<ul>
<li>Captures visitor information</li>
<li>Asks qualifying questions</li>
<li>Scores leads using AI</li>
<li>Routes hot leads to sales team</li>
<li>Books demos for qualified prospects</li>
</ul>',
    ],
    
    [
        'category' => 'features',
        'title' => 'Real-Time Activity Tracking',
        'slug' => 'real-time-activity-tracking',
        'excerpt' => 'Monitor what your prospects are doing on your website in real-time.',
        'content' => '<h2>Real-Time Activity Tracking</h2>
<p>See exactly what your prospects are doing on your website and use these insights to close more deals.</p>

<h3>What We Track</h3>
<ul>
<li><strong>Page Views</strong> - Every page visited with time spent</li>
<li><strong>Click Events</strong> - Button clicks, link clicks, form interactions</li>
<li><strong>Scroll Depth</strong> - How far visitors scroll on each page</li>
<li><strong>Session Duration</strong> - Total time on site</li>
<li><strong>Return Visits</strong> - Frequency and patterns</li>
</ul>

<h3>Installing the Tracking Script</h3>
<pre><code>&lt;!-- Add before closing &lt;/head&gt; tag --&gt;
&lt;script&gt;
  (function() {
    var script = document.createElement(\'script\');
    script.src = \'https://your-crm.com/js/tracking.js\';
    script.async = true;
    document.head.appendChild(script);
  })();
&lt;/script&gt;</code></pre>

<h3>Using Activity Data</h3>
<p>Activity tracking helps you:</p>
<ul>
<li>Know when leads are actively researching</li>
<li>Identify high-intent behavior patterns</li>
<li>Time your outreach perfectly</li>
<li>Personalize conversations based on interests</li>
<li>Score leads more accurately</li>
</ul>

<h3>Privacy Compliance</h3>
<p>Our tracking respects privacy:</p>
<ul>
<li>GDPR compliant with consent management</li>
<li>No third-party data sharing</li>
<li>Automatic PII redaction</li>
<li>User opt-out honored</li>
</ul>',
    ],
    
    // Installation
    [
        'category' => 'installation',
        'title' => 'Docker Installation Guide',
        'slug' => 'docker-installation-guide',
        'excerpt' => 'Get AI CRM up and running in 30 minutes with Docker.',
        'content' => '<h2>Docker Installation Guide</h2>
<p>The fastest way to get AI CRM running is with our Docker setup. You\'ll be up and running in about 30 minutes.</p>

<h3>Prerequisites</h3>
<ul>
<li>Linux server (Ubuntu 20.04+ recommended)</li>
<li>Docker and Docker Compose installed</li>
<li>At least 4GB RAM and 20GB storage</li>
<li>OpenAI API key for AI features</li>
</ul>

<h3>Step 1: Clone the Repository</h3>
<pre><code>git clone https://github.com/yourusername/ai-crm.git
cd ai-crm</code></pre>

<h3>Step 2: Configure Environment</h3>
<pre><code>cp .env.example .env
nano .env

# Add these required variables:
OPENAI_API_KEY=your_api_key_here
MYSQL_ROOT_PASSWORD=secure_password_here
JWT_SECRET=random_secret_here</code></pre>

<h3>Step 3: Start Services</h3>
<pre><code>docker-compose up -d</code></pre>

<p>This will start:</p>
<ul>
<li>MySQL database</li>
<li>Redis for caching</li>
<li>PHP application server</li>
<li>Frontend build process</li>
</ul>

<h3>Step 4: Initialize Database</h3>
<pre><code>docker exec -it ai-crm-app php install.php</code></pre>

<h3>Step 5: Access Your CRM</h3>
<p>Open your browser and navigate to <code>http://your-server-ip</code></p>
<p>Default login: admin / password (change immediately!)</p>

<h3>Troubleshooting</h3>
<p>If you encounter issues:</p>
<ul>
<li>Check logs: <code>docker-compose logs</code></li>
<li>Verify ports 80 and 443 are open</li>
<li>Ensure Docker has sufficient resources</li>
<li>Join our community forum for help</li>
</ul>',
        'is_featured' => 1,
    ],
    
    [
        'category' => 'installation',
        'title' => 'OpenAI Configuration',
        'slug' => 'openai-configuration',
        'excerpt' => 'Set up OpenAI integration for AI-powered features.',
        'content' => '<h2>OpenAI Configuration</h2>
<p>AI CRM uses OpenAI\'s GPT-4 for intelligent features. Here\'s how to set it up.</p>

<h3>Getting an API Key</h3>
<ol>
<li>Visit <a href="https://platform.openai.com">platform.openai.com</a></li>
<li>Create an account or sign in</li>
<li>Navigate to API Keys section</li>
<li>Create a new API key</li>
<li>Copy the key (you won\'t see it again!)</li>
</ol>

<h3>Adding to AI CRM</h3>
<p>Method 1: Environment Variable</p>
<pre><code># In your .env file
OPENAI_API_KEY=sk-proj-...</code></pre>

<p>Method 2: Admin Panel</p>
<ol>
<li>Log in as admin</li>
<li>Go to Settings > AI Configuration</li>
<li>Paste your API key</li>
<li>Click Save</li>
</ol>

<h3>Cost Management</h3>
<p>Typical usage costs:</p>
<ul>
<li><strong>Small team (1-10 users)</strong>: $10-30/month</li>
<li><strong>Medium team (11-50 users)</strong>: $30-100/month</li>
<li><strong>Large team (50+ users)</strong>: $100-500/month</li>
</ul>

<h3>Optimizing Costs</h3>
<ul>
<li>Cache AI responses for 24 hours</li>
<li>Batch similar requests</li>
<li>Use scoring limits (e.g., max 100/day)</li>
<li>Monitor usage in OpenAI dashboard</li>
</ul>',
    ],
    
    // FAQ
    [
        'category' => 'faq',
        'title' => 'Frequently Asked Questions',
        'slug' => 'frequently-asked-questions',
        'excerpt' => 'Common questions about AI CRM answered.',
        'content' => '<h2>Frequently Asked Questions</h2>

<h3>General Questions</h3>

<h4>Q: Is AI CRM really free?</h4>
<p>A: Yes! AI CRM is open source and free to self-host. You only pay for:</p>
<ul>
<li>Your server/hosting costs</li>
<li>OpenAI API usage (typically $10-50/month)</li>
<li>Optional premium support</li>
</ul>

<h4>Q: How is this different from Salesforce or HubSpot?</h4>
<p>A: Key differences:</p>
<ul>
<li>Self-hosted - your data stays on your servers</li>
<li>No per-user fees - unlimited users</li>
<li>AI features included, not add-ons</li>
<li>Fully customizable source code</li>
<li>No vendor lock-in</li>
</ul>

<h4>Q: Can I migrate from another CRM?</h4>
<p>A: Yes! We support importing from:</p>
<ul>
<li>CSV files (universal)</li>
<li>Salesforce (via API)</li>
<li>HubSpot (via export)</li>
<li>Pipedrive (via API)</li>
<li>Most other CRMs via CSV</li>
</ul>

<h3>Technical Questions</h3>

<h4>Q: What are the server requirements?</h4>
<p>A: Minimum requirements:</p>
<ul>
<li>2 CPU cores</li>
<li>4GB RAM</li>
<li>20GB storage</li>
<li>Ubuntu 20.04+ or similar</li>
</ul>

<h4>Q: Do I need technical skills to set this up?</h4>
<p>A: Basic Linux command line knowledge helps, but our Docker setup makes it straightforward. Most users are up and running in 30 minutes.</p>

<h4>Q: Is it secure?</h4>
<p>A: Yes! Security features include:</p>
<ul>
<li>All data encrypted at rest</li>
<li>TLS/SSL for all connections</li>
<li>Role-based access control</li>
<li>Regular security updates</li>
<li>No data leaves your servers</li>
</ul>

<h3>AI Questions</h3>

<h4>Q: How accurate is the AI lead scoring?</h4>
<p>A: Our users report 85%+ accuracy in identifying qualified leads. The AI improves over time as it learns from your specific business.</p>

<h4>Q: Can I use it without OpenAI?</h4>
<p>A: Yes, but you\'ll lose AI features like:</p>
<ul>
<li>Automatic lead scoring</li>
<li>Intelligent chatbot responses</li>
<li>Smart recommendations</li>
</ul>
<p>The core CRM functions work without AI.</p>

<h4>Q: Will my data be sent to OpenAI?</h4>
<p>A: We only send the minimum required data for AI processing. No personally identifiable information (PII) is sent. You can review exactly what\'s sent in our codebase.</p>',
        'is_featured' => 1,
    ],
    
    [
        'category' => 'faq',
        'title' => 'Troubleshooting Common Issues',
        'slug' => 'troubleshooting-common-issues',
        'excerpt' => 'Solutions to common problems you might encounter.',
        'content' => '<h2>Troubleshooting Common Issues</h2>

<h3>Installation Issues</h3>

<h4>Docker containers won\'t start</h4>
<p>Check these common causes:</p>
<ul>
<li>Ports 80/443 already in use: <code>sudo netstat -tlnp | grep :80</code></li>
<li>Insufficient memory: <code>free -h</code> (need 4GB minimum)</li>
<li>Docker not running: <code>sudo systemctl status docker</code></li>
</ul>

<h4>Database connection errors</h4>
<pre><code># Check if MySQL is running
docker ps | grep mysql

# View MySQL logs
docker logs ai-crm-mysql

# Test connection
docker exec -it ai-crm-mysql mysql -u root -p</code></pre>

<h3>AI Features Not Working</h3>

<h4>Lead scoring returns errors</h4>
<ul>
<li>Verify OpenAI API key is set correctly</li>
<li>Check API key has credits: <a href="https://platform.openai.com/usage">OpenAI Usage</a></li>
<li>Look for errors in logs: <code>docker logs ai-crm-app | grep -i openai</code></li>
</ul>

<h4>Chatbot not responding</h4>
<ol>
<li>Ensure knowledge base has articles</li>
<li>Check Redis is running: <code>docker ps | grep redis</code></li>
<li>Clear cache: <code>docker exec -it ai-crm-redis redis-cli FLUSHALL</code></li>
<li>Verify embed script is loading</li>
</ol>

<h3>Performance Issues</h3>

<h4>Slow page loads</h4>
<ul>
<li>Enable Redis caching in settings</li>
<li>Check server resources: <code>htop</code></li>
<li>Optimize database: <code>docker exec -it ai-crm-app php repair.php</code></li>
<li>Review activity tracking volume</li>
</ul>

<h4>High server usage</h4>
<ul>
<li>Limit AI scoring frequency</li>
<li>Reduce activity tracking sample rate</li>
<li>Archive old data regularly</li>
<li>Consider scaling horizontally</li>
</ul>

<h3>Getting Help</h3>
<p>If you\'re still stuck:</p>
<ol>
<li>Search our <a href="https://github.com/yourusername/ai-crm/issues">GitHub issues</a></li>
<li>Join our <a href="https://discord.gg/ai-crm">Discord community</a></li>
<li>Post in the <a href="https://forum.ai-crm.com">support forum</a></li>
<li>Check the <a href="https://github.com/yourusername/ai-crm/wiki">wiki</a></li>
</ol>',
    ],
];

// Insert articles
foreach ($articles as $article) {
    $id = create_guid();
    $categoryId = $categoryIds[$article['category']];
    
    $query = "INSERT INTO kb_articles 
             (id, title, slug, content, excerpt, category_id, tags, is_public, is_featured, 
              author_id, views, date_created, date_modified, deleted)
             VALUES (
                '$id',
                '{$db->quote($article['title'])}',
                '{$article['slug']}',
                '{$db->quote($article['content'])}',
                '{$db->quote($article['excerpt'])}',
                '$categoryId',
                '[]',
                1,
                " . (isset($article['is_featured']) ? 1 : 0) . ",
                '1',
                0,
                NOW(),
                NOW(),
                0
             )";
    
    $db->query($query);
    echo "✅ Created article: {$article['title']}\n";
}

echo "\n✅ Knowledge Base seeded successfully!\n";
echo "📊 Created " . count($categories) . " categories and " . count($articles) . " articles\n";