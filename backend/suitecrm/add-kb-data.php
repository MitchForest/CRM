<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

echo "=== Adding Sample KB Data ===\n\n";

// First check if we have any existing articles
$result = $db->query("SELECT COUNT(*) as count FROM aok_knowledgebase WHERE deleted = 0");
$row = $db->fetchByAssoc($result);
echo "Existing articles: " . $row['count'] . "\n";

// Add some sample articles
$articles = [
    [
        'name' => 'Getting Started with SuiteCRM',
        'description' => 'A comprehensive guide to getting started with SuiteCRM, including installation, configuration, and basic usage.',
        'status' => 'published'
    ],
    [
        'name' => 'User Management Guide',
        'description' => 'Learn how to create and manage users, assign roles, and configure security settings in SuiteCRM.',
        'status' => 'published'
    ],
    [
        'name' => 'Email Integration Tips',
        'description' => 'Best practices for integrating email with SuiteCRM, including SMTP setup and email templates.',
        'status' => 'published'
    ],
    [
        'name' => 'Customization Overview',
        'description' => 'How to customize SuiteCRM to meet your business needs, including custom fields and modules.',
        'status' => 'draft'
    ],
    [
        'name' => 'API Documentation',
        'description' => 'Complete documentation for the SuiteCRM REST API, including authentication and endpoints.',
        'status' => 'published'
    ]
];

foreach ($articles as $article) {
    $id = create_guid();
    $name = $db->quote($article['name']);
    $description = $db->quote($article['description']);
    $status = $db->quote($article['status']);
    
    $query = "INSERT INTO aok_knowledgebase (id, name, description, status, date_entered, date_modified, created_by, assigned_user_id, deleted)
              VALUES ('$id', '$name', '$description', '$status', NOW(), NOW(), '1', '1', 0)";
    
    $db->query($query);
    echo "Added: " . $article['name'] . "\n";
}

// Verify
$result = $db->query("SELECT COUNT(*) as count FROM aok_knowledgebase WHERE deleted = 0");
$row = $db->fetchByAssoc($result);
echo "\nTotal articles after insert: " . $row['count'] . "\n";

echo "\n=== DONE ===\n";