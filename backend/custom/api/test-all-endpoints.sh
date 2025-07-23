#!/bin/bash
# Comprehensive test script for all SuiteCRM API endpoints

API_URL="http://localhost:8080/custom/api/index.php"
USERNAME="admin"
PASSWORD="admin"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "Testing All SuiteCRM API Endpoints"
echo "=================================="

# Login first
echo -e "\n${GREEN}1. Testing Authentication${NC}"
LOGIN_RESPONSE=$(curl -s -X POST $API_URL/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"$USERNAME\",\"password\":\"$PASSWORD\"}")

ACCESS_TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"accessToken":"[^"]*' | sed 's/"accessToken":"//')

if [ -z "$ACCESS_TOKEN" ]; then
    echo -e "${RED}❌ Login failed!${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Login successful!${NC}"

# Test Contacts
echo -e "\n${GREEN}2. Testing Contacts${NC}"
echo "- List contacts"
curl -s -X GET $API_URL/contacts -H "Authorization: Bearer $ACCESS_TOKEN" | jq '.pagination' 2>/dev/null || echo "Failed"

echo "- Create contact"
CONTACT_RESPONSE=$(curl -s -X POST $API_URL/contacts \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email1": "john.doe@example.com",
    "phone_mobile": "+1234567890"
  }')
CONTACT_ID=$(echo $CONTACT_RESPONSE | grep -o '"id":"[^"]*' | sed 's/"id":"//')
echo "Created contact ID: $CONTACT_ID"

# Test Leads
echo -e "\n${GREEN}3. Testing Leads${NC}"
echo "- Create lead"
LEAD_RESPONSE=$(curl -s -X POST $API_URL/leads \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Jane",
    "last_name": "Smith",
    "email1": "jane.smith@example.com",
    "status": "New"
  }')
LEAD_ID=$(echo $LEAD_RESPONSE | grep -o '"id":"[^"]*' | sed 's/"id":"//')
echo "Created lead ID: $LEAD_ID"

# Test Opportunities
echo -e "\n${GREEN}4. Testing Opportunities${NC}"
echo "- Create opportunity"
OPP_RESPONSE=$(curl -s -X POST $API_URL/opportunities \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Software Deal\",
    \"amount\": \"5000\",
    \"sales_stage\": \"Prospecting\",
    \"contact_id\": \"$CONTACT_ID\"
  }")
OPP_ID=$(echo $OPP_RESPONSE | grep -o '"id":"[^"]*' | sed 's/"id":"//')
echo "Created opportunity ID: $OPP_ID"

echo "- Analyze opportunity"
curl -s -X POST $API_URL/opportunities/$OPP_ID/analyze \
  -H "Authorization: Bearer $ACCESS_TOKEN" | jq '.ai_insights' 2>/dev/null || echo "Analysis complete"

# Test Tasks
echo -e "\n${GREEN}5. Testing Tasks${NC}"
echo "- Create task"
TASK_RESPONSE=$(curl -s -X POST $API_URL/tasks \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Follow up with customer\",
    \"contact_id\": \"$CONTACT_ID\",
    \"status\": \"Not Started\",
    \"priority\": \"High\",
    \"date_due\": \"2025-08-01\"
  }")
TASK_ID=$(echo $TASK_RESPONSE | grep -o '"id":"[^"]*' | sed 's/"id":"//')
echo "Created task ID: $TASK_ID"

echo "- Get upcoming tasks"
curl -s -X GET $API_URL/tasks/upcoming -H "Authorization: Bearer $ACCESS_TOKEN" | jq '.total' 2>/dev/null || echo "0"

# Test Cases
echo -e "\n${GREEN}6. Testing Cases${NC}"
echo "- Create case"
CASE_RESPONSE=$(curl -s -X POST $API_URL/cases \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Software issue\",
    \"description\": \"Customer having login problems\",
    \"contact_id\": \"$CONTACT_ID\"
  }")
CASE_ID=$(echo $CASE_RESPONSE | grep -o '"id":"[^"]*' | sed 's/"id":"//')
echo "Created case ID: $CASE_ID"

# Test Activities
echo -e "\n${GREEN}7. Testing Activities${NC}"
echo "- Get all activities"
curl -s -X GET $API_URL/activities -H "Authorization: Bearer $ACCESS_TOKEN" | jq '.pagination' 2>/dev/null || echo "Failed"

echo "- Get recent activities"
curl -s -X GET $API_URL/activities/recent?limit=10 -H "Authorization: Bearer $ACCESS_TOKEN" | jq '.total' 2>/dev/null || echo "0"

# Test contact activities
echo -e "\n${GREEN}8. Testing Contact Activities${NC}"
curl -s -X GET $API_URL/contacts/$CONTACT_ID/activities -H "Authorization: Bearer $ACCESS_TOKEN" | jq '.pagination' 2>/dev/null || echo "Failed"

echo -e "\n${GREEN}✅ All API endpoint tests completed!${NC}"