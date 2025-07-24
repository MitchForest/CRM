#!/bin/bash

# Phase 2 Frontend-Backend Integration Test Script
# Tests all Phase 2 API endpoints and integration points

set -e

# Configuration
API_BASE_URL="${API_BASE_URL:-http://localhost:8080/custom-api}"
TEST_USERNAME="${TEST_USERNAME:-admin}"
TEST_PASSWORD="${TEST_PASSWORD:-admin123}"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "========================================"
echo "Phase 2 Integration Tests"
echo "API URL: $API_BASE_URL"
echo "========================================"

# Function to print test results
print_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}"
    else
        echo -e "${RED}✗ $2${NC}"
        exit 1
    fi
}

# Function to extract JSON value
get_json_value() {
    echo "$1" | grep -o "\"$2\":[^,}]*" | cut -d':' -f2- | tr -d ' "'
}

# 1. Authenticate
echo -e "\n${YELLOW}1. Testing Authentication...${NC}"
AUTH_RESPONSE=$(curl -s -X POST "$API_BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d "{\"username\":\"$TEST_USERNAME\",\"password\":\"$TEST_PASSWORD\"}")

TOKEN=$(echo "$AUTH_RESPONSE" | jq -r '.data.token // empty')

if [ -z "$TOKEN" ]; then
    echo -e "${RED}✗ Authentication failed${NC}"
    echo "Response: $AUTH_RESPONSE"
    exit 1
fi

print_result 0 "Authentication successful"

# 2. Test Dashboard Metrics
echo -e "\n${YELLOW}2. Testing Dashboard Metrics...${NC}"
METRICS_RESPONSE=$(curl -s -X GET "$API_BASE_URL/dashboard/metrics" \
    -H "Authorization: Bearer $TOKEN")

METRICS_STATUS=$(echo "$METRICS_RESPONSE" | jq -r '.data // empty')
if [ -n "$METRICS_STATUS" ]; then
    TOTAL_LEADS=$(echo "$METRICS_RESPONSE" | jq -r '.data.total_leads // 0')
    PIPELINE_VALUE=$(echo "$METRICS_RESPONSE" | jq -r '.data.pipeline_value // 0')
    print_result 0 "Dashboard metrics retrieved (Leads: $TOTAL_LEADS, Pipeline: \$$PIPELINE_VALUE)"
else
    print_result 1 "Failed to retrieve dashboard metrics"
fi

# 3. Test Pipeline Data
echo -e "\n${YELLOW}3. Testing Pipeline Data...${NC}"
PIPELINE_RESPONSE=$(curl -s -X GET "$API_BASE_URL/dashboard/pipeline" \
    -H "Authorization: Bearer $TOKEN")

STAGE_COUNT=$(echo "$PIPELINE_RESPONSE" | jq '.data | length // 0')
if [ "$STAGE_COUNT" -eq 8 ]; then
    print_result 0 "Pipeline data includes all 8 stages"
    
    # Display pipeline summary
    echo "Pipeline Summary:"
    echo "$PIPELINE_RESPONSE" | jq -r '.data[] | "  - \(.stage): \(.count) opportunities, $\(.value)"'
else
    print_result 1 "Pipeline data incomplete (found $STAGE_COUNT stages, expected 8)"
fi

# 4. Test Activity Metrics
echo -e "\n${YELLOW}4. Testing Activity Metrics...${NC}"
ACTIVITY_RESPONSE=$(curl -s -X GET "$API_BASE_URL/dashboard/activities" \
    -H "Authorization: Bearer $TOKEN")

HAS_METRICS=$(echo "$ACTIVITY_RESPONSE" | jq 'has("data") and (.data | has("calls_today")) and (.data | has("meetings_today"))')
if [ "$HAS_METRICS" == "true" ]; then
    CALLS_TODAY=$(echo "$ACTIVITY_RESPONSE" | jq -r '.data.calls_today // 0')
    MEETINGS_TODAY=$(echo "$ACTIVITY_RESPONSE" | jq -r '.data.meetings_today // 0')
    TASKS_OVERDUE=$(echo "$ACTIVITY_RESPONSE" | jq -r '.data.tasks_overdue // 0')
    print_result 0 "Activity metrics retrieved (Calls: $CALLS_TODAY, Meetings: $MEETINGS_TODAY, Overdue: $TASKS_OVERDUE)"
else
    print_result 1 "Activity metrics failed"
fi

# 5. Test Case Metrics
echo -e "\n${YELLOW}5. Testing Case Metrics...${NC}"
CASE_RESPONSE=$(curl -s -X GET "$API_BASE_URL/dashboard/cases" \
    -H "Authorization: Bearer $TOKEN")

OPEN_CASES=$(echo "$CASE_RESPONSE" | jq -r '.data.open_cases // -1')
if [ "$OPEN_CASES" -ge 0 ]; then
    CRITICAL_CASES=$(echo "$CASE_RESPONSE" | jq -r '.data.critical_cases // 0')
    AVG_RESOLUTION=$(echo "$CASE_RESPONSE" | jq -r '.data.avg_resolution_time // 0')
    print_result 0 "Case metrics retrieved (Open: $OPEN_CASES, Critical: $CRITICAL_CASES, Avg Resolution: ${AVG_RESOLUTION}h)"
else
    print_result 1 "Case metrics failed"
fi

# 6. Test Email Endpoint (with invalid ID - should return 400)
echo -e "\n${YELLOW}6. Testing Email View Endpoint...${NC}"
EMAIL_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    "$API_BASE_URL/emails/invalid-id/view" \
    -H "Authorization: Bearer $TOKEN")

if [ "$EMAIL_RESPONSE" == "400" ]; then
    print_result 0 "Email endpoint correctly rejects invalid ID (400)"
else
    print_result 1 "Email endpoint returned unexpected status: $EMAIL_RESPONSE"
fi

# 7. Test Document Endpoint (with invalid ID - should return 400)
echo -e "\n${YELLOW}7. Testing Document Download Endpoint...${NC}"
DOC_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    "$API_BASE_URL/documents/invalid-id/download" \
    -H "Authorization: Bearer $TOKEN")

if [ "$DOC_RESPONSE" == "400" ]; then
    print_result 0 "Document endpoint correctly rejects invalid ID (400)"
else
    print_result 1 "Document endpoint returned unexpected status: $DOC_RESPONSE"
fi

# 8. Test Unauthorized Access
echo -e "\n${YELLOW}8. Testing Unauthorized Access...${NC}"
UNAUTH_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    "$API_BASE_URL/dashboard/metrics")

if [ "$UNAUTH_RESPONSE" == "401" ]; then
    print_result 0 "Unauthorized access correctly rejected (401)"
else
    print_result 1 "Unauthorized access returned unexpected status: $UNAUTH_RESPONSE"
fi

# 9. Performance Test - All Dashboard Endpoints
echo -e "\n${YELLOW}9. Testing Dashboard Performance...${NC}"
ENDPOINTS=("/dashboard/metrics" "/dashboard/pipeline" "/dashboard/activities" "/dashboard/cases")
TOTAL_TIME=0

for endpoint in "${ENDPOINTS[@]}"; do
    START_TIME=$(date +%s%N)
    curl -s -X GET "$API_BASE_URL$endpoint" \
        -H "Authorization: Bearer $TOKEN" > /dev/null
    END_TIME=$(date +%s%N)
    
    ELAPSED_TIME=$(( ($END_TIME - $START_TIME) / 1000000 ))
    TOTAL_TIME=$(( $TOTAL_TIME + $ELAPSED_TIME ))
    echo "  - $endpoint: ${ELAPSED_TIME}ms"
done

AVG_TIME=$(( $TOTAL_TIME / ${#ENDPOINTS[@]} ))
if [ "$AVG_TIME" -lt 1000 ]; then
    print_result 0 "Average response time: ${AVG_TIME}ms (< 1s)"
else
    print_result 1 "Average response time: ${AVG_TIME}ms (> 1s)"
fi

# Summary
echo -e "\n========================================"
echo -e "${GREEN}✓ All Phase 2 integration tests passed!${NC}"
echo "========================================"

# Test data summary
echo -e "\nCurrent System State:"
echo "- Total Leads: $TOTAL_LEADS"
echo "- Pipeline Value: \$$PIPELINE_VALUE"
echo "- Open Cases: $OPEN_CASES"
echo "- Critical Cases: $CRITICAL_CASES"
echo "- Today's Activities: $CALLS_TODAY calls, $MEETINGS_TODAY meetings"
echo "- Overdue Tasks: $TASKS_OVERDUE"