#!/bin/bash

# CI Pipeline Alignment Check
# This script runs all alignment checks and fails if any issues are found

echo "=== CRM CI ALIGNMENT CHECK ==="
echo "Running comprehensive alignment checks..."
echo

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
BACKEND_DIR="$SCRIPT_DIR/../.."
FRONTEND_DIR="$BACKEND_DIR/../frontend"
EXIT_CODE=0

# Function to run a check and capture result
run_check() {
    local name=$1
    local command=$2
    echo "Running: $name"
    if eval "$command"; then
        echo "✅ $name passed"
    else
        echo "❌ $name failed"
        EXIT_CODE=1
    fi
    echo
}

# 1. Backend Alignment Check
if [ -f "$SCRIPT_DIR/alignment-audit.php" ]; then
    run_check "Backend Alignment Audit" "php $SCRIPT_DIR/alignment-audit.php | grep -q 'Total issues found: 0'"
fi

# 2. Frontend Field Scanner
if [ -f "$FRONTEND_DIR/scripts/scan-field-usage.ts" ]; then
    run_check "Frontend Field Usage" "cd $FRONTEND_DIR && npx tsx scripts/scan-field-usage.ts"
fi

# 3. Check for non-existent table references
echo "Checking for non-existent table references..."
PROBLEM_TABLES=("documents" "email_templates")
for table in "${PROBLEM_TABLES[@]}"; do
    if grep -r "->$table\b" "$BACKEND_DIR/app" --include="*.php" | grep -v "//"; then
        echo "❌ Found references to non-existent table: $table"
        EXIT_CODE=1
    fi
done
echo

# 4. Check for hardcoded field references
echo "Checking for hardcoded non-existent fields..."
PROBLEM_FIELDS=("->converted\b" "->converted_opp_id\b" "->scored_at\b")
for field in "${PROBLEM_FIELDS[@]}"; do
    if grep -r "$field" "$BACKEND_DIR/app/Http/Controllers" --include="*.php" | grep -v "//"; then
        echo "❌ Found references to problematic field: $field"
        EXIT_CODE=1
    fi
done
echo

# 5. Validate Model-Database alignment
echo "Validating Model-Database alignment..."
php -r "
require_once '$BACKEND_DIR/vendor/autoload.php';
\$capsule = require '$BACKEND_DIR/config/database.php';

\$models = glob('$BACKEND_DIR/app/Models/*.php');
\$issues = 0;

foreach (\$models as \$modelFile) {
    \$modelName = basename(\$modelFile, '.php');
    \$modelClass = \"App\\\\Models\\\\\$modelName\";
    
    if (!class_exists(\$modelClass) || (new ReflectionClass(\$modelClass))->isAbstract()) {
        continue;
    }
    
    try {
        \$model = new \$modelClass;
        \$table = \$model->getTable();
        
        // Check if table exists
        \$tableExists = \Illuminate\Database\Capsule\Manager::select(\"SHOW TABLES LIKE '\$table'\");
        if (empty(\$tableExists)) {
            echo \"❌ Model \$modelName references non-existent table: \$table\\n\";
            \$issues++;
        }
    } catch (Exception \$e) {
        // Ignore
    }
}

exit(\$issues > 0 ? 1 : 0);
"
if [ $? -ne 0 ]; then
    EXIT_CODE=1
fi
echo

# Summary
if [ $EXIT_CODE -eq 0 ]; then
    echo "✅ All alignment checks passed!"
else
    echo "❌ Alignment issues detected. Please fix before merging."
fi

exit $EXIT_CODE