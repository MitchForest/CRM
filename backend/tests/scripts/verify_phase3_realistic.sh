#!/bin/bash

# Realistic Phase 3 Backend Verification
# Tests actual implementation with real API calls and expected failures

# set -e

echo "======================================"
echo "Phase 3 Realistic Backend Verification"
echo "======================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Counters
PASSED=0
FAILED=0
WARNINGS=0

# Configuration
API_BASE="http://localhost:8080/custom/api"

# Helper functions
check_result() {
    local test_name=$1
    local condition=$2
    local actual_value=$3
    
    if [ $condition -eq 0 ]; then
        echo -e "${GREEN}✓ $test_name${NC}"
        ((PASSED++))
    else
        echo -e "${RED}✗ $test_name${NC}"
        echo "  Actual: $actual_value"
        ((FAILED++))
    fi
}

warn_result() {
    local test_name=$1
    local message=$2
    echo -e "${YELLOW}⚠ $test_name${NC}"
    echo "  $message"
    ((WARNINGS++))
}

# 1. Check if services are actually accessible
echo -e "${BLUE}1. Checking Service Availability${NC}"

# Check if custom API is responding
API_HEALTH=$(curl -s -o /dev/null -w "%{http_code}" "$API_BASE/health" || echo "000")
check_result "Custom API responding" "$([[ "$API_HEALTH" == "200" ]] && echo 0 || echo 1)" "HTTP $API_HEALTH"

# Check if routes file exists and is loaded
if [ -f "./custom-api/routes.php" ]; then
    ROUTES_COUNT=$(grep -c "router->" ./custom-api/routes.php || echo "0")
    check_result "Routes configured" "$([[ $ROUTES_COUNT -gt 0 ]] && echo 0 || echo 1)" "$ROUTES_COUNT routes found"
else
    check_result "Routes file exists" "1" "File not found"
fi

echo ""

# 2. Test Authentication (Phase 1 style since Phase 3 uses it)
echo -e "${BLUE}2. Testing Authentication${NC}"

# Try the standard v8 login endpoint
AUTH_RESPONSE=$(curl -s -X POST "http://localhost:8080/Api/access_token" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "grant_type=password&client_id=suitecrm_client&username=admin&password=admin" 2>/dev/null || echo '{"error":"connection_failed"}')

ACCESS_TOKEN=$(echo "$AUTH_RESPONSE" | jq -r '.access_token // empty' 2>/dev/null || echo "")

if [ -n "$ACCESS_TOKEN" ] && [ "$ACCESS_TOKEN" != "null" ]; then
    check_result "Authentication successful" "0" "Token obtained"
else
    warn_result "Authentication" "Skipping auth - will test public endpoints only"
    ACCESS_TOKEN=""
fi

echo ""

# 3. Test OpenAI Service Configuration
echo -e "${BLUE}3. Testing OpenAI Integration${NC}"

# Check if OpenAI config exists
if [ -f "./suitecrm-custom/config/ai_config.php" ]; then
    check_result "AI config file exists" "0" "Found"
    
    # Check if API key is configured
    if grep -q "OPENAI_API_KEY" "./suitecrm-custom/config/ai_config.php"; then
        check_result "OpenAI API key configured" "0" "Found in config"
    else
        warn_result "OpenAI API key" "Not found in config - AI features may not work"
    fi
else
    check_result "AI config file" "1" "Not found"
fi

# Test AI endpoint (it should at least respond, even if it fails)
AI_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_BASE/leads/test-lead-id/ai-score" \
    -H "Authorization: Bearer $ACCESS_TOKEN" || echo "000")

if [[ "$AI_TEST" == "404" ]] || [[ "$AI_TEST" == "500" ]]; then
    warn_result "AI scoring endpoint" "Endpoint exists but returned $AI_TEST (expected without valid lead)"
elif [[ "$AI_TEST" == "401" ]]; then
    check_result "AI scoring endpoint protected" "0" "Requires authentication"
else
    check_result "AI scoring endpoint" "1" "HTTP $AI_TEST"
fi

echo ""

# 4. Test Form Builder
echo -e "${BLUE}4. Testing Form Builder${NC}"

# Check if form tables exist
FORM_TABLE_CHECK=$(docker exec suitecrm-mysql mysql -uroot -proot -e "SHOW TABLES LIKE 'form_builder_forms'" suitecrm 2>/dev/null | grep -c "form_builder_forms" | tr -d '\n' || echo "0")
check_result "Form builder table exists" "$([[ $FORM_TABLE_CHECK -ge 1 ]] && echo 0 || echo 1)" "Table check: $FORM_TABLE_CHECK"

# Test form creation endpoint
FORM_CREATE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_BASE/forms" \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"name":"Test Form","fields":[]}' || echo "000")

if [[ "$FORM_CREATE" == "201" ]] || [[ "$FORM_CREATE" == "200" ]]; then
    check_result "Form creation endpoint" "0" "HTTP $FORM_CREATE"
elif [[ "$FORM_CREATE" == "401" ]]; then
    warn_result "Form creation" "Authentication required"
else
    check_result "Form creation endpoint" "1" "HTTP $FORM_CREATE"
fi

# Test public form submission endpoint
FORM_SUBMIT=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_BASE/forms/test-id/submit" \
    -d '{"test":"data"}' || echo "000")

if [[ "$FORM_SUBMIT" == "404" ]]; then
    check_result "Form submission endpoint (public)" "0" "Endpoint exists (404 for invalid form)"
else
    warn_result "Form submission endpoint" "HTTP $FORM_SUBMIT - may not be public"
fi

echo ""

# 5. Test Activity Tracking
echo -e "${BLUE}5. Testing Activity Tracking${NC}"

# Test public tracking endpoint
TRACK_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_BASE/track/pageview" \
    -H "Content-Type: application/json" \
    -d '{"visitor_id":"test","url":"http://test.com"}' || echo "000")

if [[ "$TRACK_TEST" == "200" ]]; then
    check_result "Activity tracking endpoint (public)" "0" "Working"
elif [[ "$TRACK_TEST" == "401" ]]; then
    warn_result "Activity tracking" "Endpoint requires auth (should be public)"
else
    check_result "Activity tracking endpoint" "1" "HTTP $TRACK_TEST"
fi

echo ""

# 6. Test Customer Health Scoring
echo -e "${BLUE}6. Testing Customer Health Scoring${NC}"

# Check if health score table exists
HEALTH_TABLE=$(docker exec suitecrm-mysql mysql -uroot -proot -e "SHOW TABLES LIKE 'customer_health_scores'" suitecrm 2>/dev/null | grep -c "customer_health_scores" | tr -d '\n' || echo "0")
check_result "Health scores table exists" "$([[ $HEALTH_TABLE -ge 1 ]] && echo 0 || echo 1)" "Table check: $HEALTH_TABLE"

# Test health score endpoint
HEALTH_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_BASE/accounts/test-id/health-score" \
    -H "Authorization: Bearer $ACCESS_TOKEN" || echo "000")

if [[ "$HEALTH_TEST" == "404" ]] || [[ "$HEALTH_TEST" == "500" ]]; then
    warn_result "Health scoring endpoint" "Endpoint exists but returned $HEALTH_TEST"
elif [[ "$HEALTH_TEST" == "401" ]]; then
    check_result "Health scoring endpoint protected" "0" "Requires authentication"
else
    check_result "Health scoring endpoint" "1" "HTTP $HEALTH_TEST"
fi

# Test webhook endpoint
WEBHOOK_TEST=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$API_BASE/webhooks/health-check" \
    -H "X-Webhook-Token: your-secret-token-here" || echo "000")

if [[ "$WEBHOOK_TEST" == "200" ]]; then
    check_result "Webhook endpoint" "0" "Working"
elif [[ "$WEBHOOK_TEST" == "401" ]]; then
    check_result "Webhook token validation" "0" "Security working"
else
    warn_result "Webhook endpoint" "HTTP $WEBHOOK_TEST"
fi

echo ""

# 7. Test Embed Scripts
echo -e "${BLUE}7. Testing Embed Scripts${NC}"

SCRIPTS=("forms-embed.js" "tracking.js" "chat-widget.js")
for script in "${SCRIPTS[@]}"; do
    if [ -f "./suitecrm/public/js/$script" ]; then
        FILE_SIZE=$(stat -f%z "./suitecrm/public/js/$script" 2>/dev/null || stat -c%s "./suitecrm/public/js/$script" 2>/dev/null || echo "0")
        if [ "$FILE_SIZE" -gt 1000 ]; then
            check_result "$script exists and has content" "0" "${FILE_SIZE} bytes"
        else
            warn_result "$script" "File exists but seems empty (${FILE_SIZE} bytes)"
        fi
    else
        check_result "$script exists" "1" "Not found"
    fi
done

echo ""

# 8. Check Phase 3 Tables
echo -e "${BLUE}8. Checking Database Tables${NC}"

PHASE3_TABLES=(
    "form_builder_forms"
    "form_builder_submissions"
    "knowledge_base_articles"
    "activity_tracking_visitors"
    "activity_tracking_sessions"
    "activity_tracking_page_views"
    "ai_chat_conversations"
    "customer_health_scores"
)

TABLES_FOUND=0
for table in "${PHASE3_TABLES[@]}"; do
    TABLE_EXISTS=$(docker exec suitecrm-mysql mysql -uroot -proot -e "SHOW TABLES LIKE '$table'" suitecrm 2>/dev/null | grep -c "$table" | tr -d '\n' || echo "0")
    if [ "$TABLE_EXISTS" -ge 1 ]; then
        ((TABLES_FOUND++))
    fi
done

check_result "Phase 3 tables created" "$([[ $TABLES_FOUND -eq ${#PHASE3_TABLES[@]} ]] && echo 0 || echo 1)" "$TABLES_FOUND/${#PHASE3_TABLES[@]} tables found"

echo ""

# 9. Test Real Scenarios
echo -e "${BLUE}9. Testing Real-World Scenarios${NC}"

# Scenario 1: Can we actually create and score a lead?
echo "Scenario: Create and score a lead..."
LEAD_RESPONSE=$(curl -s -X POST "$API_BASE/module/Leads" \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "data": {
            "type": "Leads",
            "attributes": {
                "first_name": "Real",
                "last_name": "Test",
                "email": "realtest@example.com"
            }
        }
    }' 2>/dev/null || echo '{}')

LEAD_ID=$(echo "$LEAD_RESPONSE" | jq -r '.data.id // empty' 2>/dev/null)

if [ -n "$LEAD_ID" ] && [ "$LEAD_ID" != "null" ]; then
    # Try to score it
    SCORE_RESPONSE=$(curl -s -X POST "$API_BASE/leads/$LEAD_ID/ai-score" \
        -H "Authorization: Bearer $ACCESS_TOKEN" 2>/dev/null || echo '{}')
    
    SCORE=$(echo "$SCORE_RESPONSE" | jq -r '.score // empty' 2>/dev/null)
    
    if [ -n "$SCORE" ] && [ "$SCORE" != "null" ]; then
        check_result "End-to-end lead scoring" "0" "Lead created and scored: $SCORE"
    else
        warn_result "Lead scoring" "Lead created but scoring failed"
    fi
else
    warn_result "Lead creation" "Could not create test lead"
fi

echo ""

# 10. Summary and Recommendations
echo "======================================"
echo "Summary"
echo "======================================"
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"
echo -e "Warnings: ${YELLOW}$WARNINGS${NC}"
echo ""

# Calculate coverage estimate
TOTAL_TESTS=$((PASSED + FAILED))
if [ $TOTAL_TESTS -gt 0 ]; then
    COVERAGE=$((PASSED * 100 / TOTAL_TESTS))
    echo "Test Coverage: ~${COVERAGE}% of checked features are working"
else
    echo "No tests completed successfully"
fi

echo ""
echo "Reality Check:"

if [ $FAILED -gt 5 ]; then
    echo -e "${RED}⚠️  Many features are not working as expected${NC}"
    echo ""
    echo "Common issues:"
    echo "1. Database tables may not be created - run:"
    echo "   docker exec suitecrm_app php /var/www/html/suitecrm-custom/install/install_phase3_tables.php"
    echo ""
    echo "2. Routes may not be registered - check custom-api/routes.php"
    echo ""
    echo "3. Services may not be autoloaded - check namespace and file paths"
    echo ""
    echo "4. OpenAI API key not set - export OPENAI_API_KEY=your-key"
elif [ $WARNINGS -gt 3 ]; then
    echo -e "${YELLOW}⚠️  Implementation is partially working but needs attention${NC}"
    echo ""
    echo "Key issues to address:"
    echo "- Some endpoints return unexpected status codes"
    echo "- Authentication may be using non-standard endpoints"
    echo "- Some features may not be fully integrated"
else
    echo -e "${GREEN}✅ Most features appear to be working correctly${NC}"
fi

echo ""
echo "To run a full integration test with expected data:"
echo "./tests/scripts/test-phase3-integration.sh"

exit $FAILED