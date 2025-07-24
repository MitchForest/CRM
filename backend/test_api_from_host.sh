#!/bin/bash

# Comprehensive API test script to run from host
# Tests all CRUD operations after integration fixes

BASE_URL="http://localhost:8080/Api/V8"
TOKEN_URL="http://localhost:8080/Api/access_token"
USERNAME="apiuser"
PASSWORD="apiuser123"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0

# Function to run a test
run_test() {
    local test_name="$1"
    local test_command="$2"
    
    ((TOTAL_TESTS++))
    echo -e "\n${test_name}: "
    
    if eval "$test_command"; then
        echo -e "${GREEN}✓ PASSED${NC}"
        ((PASSED_TESTS++))
        return 0
    else
        echo -e "${RED}✗ FAILED${NC}"
        return 1
    fi
}

echo -e "=== SuiteCRM v8 API Comprehensive Integration Test ===\n"

# 1. Get OAuth2 token
echo "Authenticating..."
TOKEN_RESPONSE=$(curl -s -X POST $TOKEN_URL \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&client_id=suitecrm_client&client_secret=secret123&username=$USERNAME&password=$PASSWORD")

TOKEN=$(echo $TOKEN_RESPONSE | jq -r '.access_token')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
    echo -e "${RED}Failed to authenticate${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Authentication successful${NC}"

echo -e "\n${BLUE}=== LEADS MODULE TESTS ===${NC}"

# Test 1: Create Lead
run_test "Create Lead with email field" '
    RESPONSE=$(curl -s -X POST "$BASE_URL/module" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/vnd.api+json" \
      -H "Accept: application/vnd.api+json" \
      -d '\''{"data":{"type":"Leads","attributes":{"first_name":"Integration","last_name":"Test Lead","email1":"integration.test@example.com","phone_work":"555-1234","status":"New","lead_source":"Website","description":"Created by integration test"}}}'\'' \
      -w "\n%{http_code}")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | head -n -1)
    
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "201" ]; then
        LEAD_ID=$(echo "$BODY" | jq -r ".data.id")
        [ ! -z "$LEAD_ID" ] && [ "$LEAD_ID" != "null" ]
    else
        echo "  HTTP Code: $HTTP_CODE"
        echo "  Response: $BODY" | jq . 2>/dev/null || echo "$BODY"
        false
    fi
'

# Test 2: Read Lead and verify fields
run_test "Read Lead and verify email field" '
    if [ ! -z "$LEAD_ID" ]; then
        RESPONSE=$(curl -s -X GET "$BASE_URL/module/Leads/$LEAD_ID" \
          -H "Authorization: Bearer $TOKEN" \
          -H "Accept: application/vnd.api+json")
        
        EMAIL=$(echo "$RESPONSE" | jq -r ".data.attributes.email1")
        FIRST_NAME=$(echo "$RESPONSE" | jq -r ".data.attributes.first_name")
        
        # Check custom fields
        AI_SCORE=$(echo "$RESPONSE" | jq -r ".data.attributes.ai_score")
        AI_INSIGHTS=$(echo "$RESPONSE" | jq -r ".data.attributes.ai_insights")
        AI_SCORE_DATE=$(echo "$RESPONSE" | jq -r ".data.attributes.ai_score_date")
        
        echo "  Email: $EMAIL (expected: integration.test@example.com)"
        echo "  First Name: $FIRST_NAME (expected: Integration)"
        echo "  Custom fields present: AI Score exists: $([[ "$AI_SCORE" != "null" ]] && echo "Yes" || echo "No")"
        
        [ "$EMAIL" = "integration.test@example.com" ] && [ "$FIRST_NAME" = "Integration" ]
    else
        false
    fi
'

# Test 3: Update Lead using PATCH
run_test "Update Lead using PATCH /module" '
    if [ ! -z "$LEAD_ID" ]; then
        UPDATE_RESPONSE=$(curl -s -X PATCH "$BASE_URL/module" \
          -H "Authorization: Bearer $TOKEN" \
          -H "Content-Type: application/vnd.api+json" \
          -H "Accept: application/vnd.api+json" \
          -d "{\"data\":{\"type\":\"Leads\",\"id\":\"$LEAD_ID\",\"attributes\":{\"first_name\":\"Updated\",\"status\":\"Assigned\",\"description\":\"Updated by integration test\"}}}" \
          -w "\n%{http_code}")
        
        HTTP_CODE=$(echo "$UPDATE_RESPONSE" | tail -n 1)
        
        if [ "$HTTP_CODE" = "200" ]; then
            # Verify update
            VERIFY_RESPONSE=$(curl -s -X GET "$BASE_URL/module/Leads/$LEAD_ID" \
              -H "Authorization: Bearer $TOKEN" \
              -H "Accept: application/vnd.api+json")
            
            UPDATED_NAME=$(echo "$VERIFY_RESPONSE" | jq -r ".data.attributes.first_name")
            UPDATED_STATUS=$(echo "$VERIFY_RESPONSE" | jq -r ".data.attributes.status")
            
            echo "  Updated name: $UPDATED_NAME (expected: Updated)"
            echo "  Updated status: $UPDATED_STATUS (expected: Assigned)"
            
            [ "$UPDATED_NAME" = "Updated" ] && [ "$UPDATED_STATUS" = "Assigned" ]
        else
            echo "  HTTP Code: $HTTP_CODE"
            echo "$UPDATE_RESPONSE" | head -n -1 | jq . 2>/dev/null
            false
        fi
    else
        false
    fi
'

# Test 4: List Leads
run_test "List Leads with pagination" '
    RESPONSE=$(curl -s -X GET "$BASE_URL/module/Leads?page[number]=1&page[size]=5" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Accept: application/vnd.api+json")
    
    DATA_COUNT=$(echo "$RESPONSE" | jq ".data | length")
    HAS_META=$(echo "$RESPONSE" | jq "has(\"meta\")")
    
    echo "  Records returned: $DATA_COUNT"
    echo "  Has pagination meta: $HAS_META"
    
    [ "$DATA_COUNT" -ge 0 ] && [ "$HAS_META" = "true" ]
'

echo -e "\n${BLUE}=== ACCOUNTS MODULE TESTS ===${NC}"

# Test 5: Create Account
run_test "Create Account" '
    RESPONSE=$(curl -s -X POST "$BASE_URL/module" \
      -H "Authorization: Bearer $TOKEN" \
      -H "Content-Type: application/vnd.api+json" \
      -H "Accept: application/vnd.api+json" \
      -d '\''{"data":{"type":"Accounts","attributes":{"name":"Integration Test Account","industry":"Technology","website":"https://test.example.com","phone_office":"555-5678","billing_address_city":"Test City","billing_address_country":"USA"}}}'\'' \
      -w "\n%{http_code}")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | head -n -1)
    
    if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "201" ]; then
        ACCOUNT_ID=$(echo "$BODY" | jq -r ".data.id")
        [ ! -z "$ACCOUNT_ID" ] && [ "$ACCOUNT_ID" != "null" ]
    else
        echo "  HTTP Code: $HTTP_CODE"
        false
    fi
'

# Test 6: Update Account
run_test "Update Account using PATCH" '
    if [ ! -z "$ACCOUNT_ID" ]; then
        UPDATE_RESPONSE=$(curl -s -X PATCH "$BASE_URL/module" \
          -H "Authorization: Bearer $TOKEN" \
          -H "Content-Type: application/vnd.api+json" \
          -H "Accept: application/vnd.api+json" \
          -d "{\"data\":{\"type\":\"Accounts\",\"id\":\"$ACCOUNT_ID\",\"attributes\":{\"name\":\"Updated Test Account\",\"industry\":\"Finance\"}}}" \
          -w "\n%{http_code}")
        
        HTTP_CODE=$(echo "$UPDATE_RESPONSE" | tail -n 1)
        
        if [ "$HTTP_CODE" = "200" ]; then
            # Verify update
            VERIFY_RESPONSE=$(curl -s -X GET "$BASE_URL/module/Accounts/$ACCOUNT_ID" \
              -H "Authorization: Bearer $TOKEN" \
              -H "Accept: application/vnd.api+json")
            
            UPDATED_NAME=$(echo "$VERIFY_RESPONSE" | jq -r ".data.attributes.name")
            
            echo "  Updated name: $UPDATED_NAME"
            [ "$UPDATED_NAME" = "Updated Test Account" ]
        else
            echo "  HTTP Code: $HTTP_CODE"
            false
        fi
    else
        false
    fi
'

echo -e "\n${BLUE}=== CLEANUP ===${NC}"

# Test 7: Delete Lead
run_test "Delete Lead" '
    if [ ! -z "$LEAD_ID" ]; then
        DELETE_RESPONSE=$(curl -s -X DELETE "$BASE_URL/module/Leads/$LEAD_ID" \
          -H "Authorization: Bearer $TOKEN" \
          -w "\n%{http_code}")
        
        HTTP_CODE=$(echo "$DELETE_RESPONSE" | tail -n 1)
        
        if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "204" ]; then
            # Verify deletion
            VERIFY_RESPONSE=$(curl -s -X GET "$BASE_URL/module/Leads/$LEAD_ID" \
              -H "Authorization: Bearer $TOKEN" \
              -w "\n%{http_code}")
            
            VERIFY_CODE=$(echo "$VERIFY_RESPONSE" | tail -n 1)
            [ "$VERIFY_CODE" = "404" ]
        else
            false
        fi
    else
        false
    fi
'

# Test 8: Delete Account
run_test "Delete Account" '
    if [ ! -z "$ACCOUNT_ID" ]; then
        DELETE_RESPONSE=$(curl -s -X DELETE "$BASE_URL/module/Accounts/$ACCOUNT_ID" \
          -H "Authorization: Bearer $TOKEN" \
          -w "\n%{http_code}")
        
        HTTP_CODE=$(echo "$DELETE_RESPONSE" | tail -n 1)
        [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "204" ]
    else
        false
    fi
'

# Summary
echo -e "\n${BLUE}=== TEST SUMMARY ===${NC}"
echo "Total tests: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$((TOTAL_TESTS - PASSED_TESTS))${NC}"
echo "Success rate: $(awk "BEGIN {printf \"%.2f\", ($PASSED_TESTS/$TOTAL_TESTS)*100}")%"

if [ $PASSED_TESTS -eq $TOTAL_TESTS ]; then
    echo -e "\n${GREEN}✓ ALL TESTS PASSED! The API integration is working correctly.${NC}"
else
    echo -e "\n${RED}✗ Some tests failed. Please review the output above.${NC}"
fi