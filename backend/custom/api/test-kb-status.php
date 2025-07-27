<?php
// Quick test to see article status
require_once __DIR__ . '/init.php';

global $db;

echo "Checking article status in database:\n\n";

$query = "SELECT id, name, status FROM aok_knowledgebase WHERE deleted = 0 LIMIT 10";
$result = $db->query($query);

while ($row = $db->fetchByAssoc($result)) {
    echo "Article: {$row['name']}\n";
    echo "  ID: {$row['id']}\n";
    echo "  Status: {$row['status']}\n\n";
}

echo "\nChecking status distribution:\n";
$query = "SELECT status, COUNT(*) as count FROM aok_knowledgebase WHERE deleted = 0 GROUP BY status";
$result = $db->query($query);

while ($row = $db->fetchByAssoc($result)) {
    echo "  Status '{$row['status']}': {$row['count']} articles\n";
}