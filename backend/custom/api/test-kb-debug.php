<?php
// Debug KB queries
require_once __DIR__ . '/init.php';

global $db;

echo "Testing getArticles query directly:\n\n";

$where = "WHERE deleted = 0";
$limit = 5;
$offset = 0;

// Test the exact query from getArticles
$query = "SELECT id, name as title, name as slug, description as summary, 
                '' as category, '' as tags, 
                status = 'published' as is_published, 0 as is_featured, 0 as view_count, 
                0 as helpful_count, 0 as not_helpful_count, 
                created_by as author_id, date_entered as date_published, date_modified
         FROM aok_knowledgebase 
         $where 
         ORDER BY date_entered DESC 
         LIMIT $limit OFFSET $offset";

echo "Query: $query\n\n";
         
$result = $db->query($query);
$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    echo "Article $count:\n";
    echo "  ID: {$row['id']}\n";
    echo "  Title: {$row['title']}\n";
    echo "  is_published: {$row['is_published']}\n";
    echo "  date_published: {$row['date_published']}\n\n";
    $count++;
}

echo "Total articles returned: $count\n";