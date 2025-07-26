#!/bin/bash

echo "Running comprehensive API tests..."
echo "================================="

# Function to run a PHP test script
run_test() {
    local script=$1
    local name=$2
    
    echo -e "\n📋 Running $name..."
    echo "-------------------"
    
    if [ -f "$script" ]; then
        php "$script"
    else
        echo "❌ Script not found: $script"
    fi
}

# Run all test scripts
run_test "/var/www/html/custom/scripts/test_all_apis.php" "All APIs Test"
run_test "/var/www/html/custom/scripts/test_status_codes.php" "Status Codes Test"
run_test "/var/www/html/custom/scripts/test_lead_capture.php" "Lead Capture Flow Test"

echo -e "\n\n✅ All tests completed!"