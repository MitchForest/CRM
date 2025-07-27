#!/bin/bash

# Fix Laravel dependencies in backend codebase

echo "Fixing Laravel dependencies in backend..."

# 1. Fix Collection imports (already done)
echo "✓ Collection imports already fixed"

# 2. Fix now() helpers in Services
echo "Fixing now() helpers in Services..."

# ContactService.php
sed -i '' "s/now()/new \\\\DateTime()/g" app/Services/CRM/ContactService.php
sed -i '' "s/->subDays(30)/->modify('-30 days')/g" app/Services/CRM/ContactService.php
sed -i '' "s/->subMonths(3)/->modify('-3 months')/g" app/Services/CRM/ContactService.php
sed -i '' "s/->subDays(7)/->modify('-7 days')/g" app/Services/CRM/ContactService.php

# CaseService.php
sed -i '' "s/now()/new \\\\DateTime()/g" app/Services/CRM/CaseService.php
sed -i '' "s/->subDay()/->modify('-1 day')/g" app/Services/CRM/CaseService.php
sed -i '' "s/->subDays(3)/->modify('-3 days')/g" app/Services/CRM/CaseService.php
sed -i '' "s/->subDays(2)/->modify('-2 days')/g" app/Services/CRM/CaseService.php

# KnowledgeBaseService.php
sed -i '' "s/'created_at' => now()/'created_at' => new \\\\DateTime()/g" app/Services/CRM/KnowledgeBaseService.php
sed -i '' "s/'generated_at' => now()/'generated_at' => new \\\\DateTime()/g" app/Services/CRM/KnowledgeBaseService.php
sed -i '' "s/now()->subDays(\$i)/(new \\\\DateTime())->modify(\"-\$i days\")/g" app/Services/CRM/KnowledgeBaseService.php

# FormBuilderService.php
sed -i '' "s/'created_at' => now()/'created_at' => new \\\\DateTime()/g" app/Services/Forms/FormBuilderService.php

# ActivityTrackingService.php
sed -i '' "s/'created_at' => now()/'created_at' => new \\\\DateTime()/g" app/Services/Tracking/ActivityTrackingService.php
sed -i '' "s/'ended_at' => now()/'ended_at' => new \\\\DateTime()/g" app/Services/Tracking/ActivityTrackingService.php
sed -i '' "s/'started_at' => now()/'started_at' => new \\\\DateTime()/g" app/Services/Tracking/ActivityTrackingService.php
sed -i '' "s/now()->diffInSeconds/(new \\\\DateTime())->diff/g" app/Services/Tracking/ActivityTrackingService.php
sed -i '' "s/now()->subMinutes(30)/(new \\\\DateTime())->modify('-30 minutes')/g" app/Services/Tracking/ActivityTrackingService.php

# ChatbotService.php
sed -i '' "s/'started_at' => now()/'started_at' => new \\\\DateTime()/g" app/Services/AI/ChatbotService.php
sed -i '' "s/'created_at' => now()/'created_at' => new \\\\DateTime()/g" app/Services/AI/ChatbotService.php
sed -i '' "s/'ended_at' => now()/'ended_at' => new \\\\DateTime()/g" app/Services/AI/ChatbotService.php
sed -i '' "s/'timestamp' => now()/'timestamp' => new \\\\DateTime()/g" app/Services/AI/ChatbotService.php

# 3. Fix Log facade usage
echo "Fixing Log facade usage..."
find app/Services -name "*.php" -type f -exec sed -i '' 's/\\Log::/error_log(/g; s/Log::/error_log(/g' {} \;

# Fix the error_log format for proper logging
find app/Services -name "*.php" -type f -exec sed -i '' 's/error_log(->info(/error_log(/g' {} \;
find app/Services -name "*.php" -type f -exec sed -i '' 's/error_log(->warning(/error_log("WARNING: " . /g' {} \;
find app/Services -name "*.php" -type f -exec sed -i '' 's/error_log(->error(/error_log("ERROR: " . /g' {} \;

echo "✓ Laravel dependencies fixed!"
echo ""
echo "Summary of changes:"
echo "- Fixed Collection imports (Illuminate\Support -> Illuminate\Database\Eloquent)"
echo "- Replaced now() helpers with new DateTime()"
echo "- Replaced Log facade with error_log()"
echo ""
echo "Next steps:"
echo "1. Review the changes to ensure they're correct"
echo "2. Test all affected services"
echo "3. Update any remaining Laravel-specific code"