#!/bin/bash
# Test script for SuiteCRM custom API

API_URL="http://localhost:8080/custom/api/index.php"
USERNAME="admin"
PASSWORD="admin"

echo "Testing SuiteCRM Custom API..."
echo "=============================="

# Test login
echo -e "\n1. Testing Login..."
LOGIN_RESPONSE=$(curl -s -X POST $API_URL/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"$USERNAME\",\"password\":\"$PASSWORD\"}")

echo "Response: $LOGIN_RESPONSE"

# Extract token using grep and sed
ACCESS_TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"accessToken":"[^"]*' | sed 's/"accessToken":"//')

if [ -z "$ACCESS_TOKEN" ]; then
    echo "❌ Login failed!"
    exit 1
fi

echo "✅ Login successful!"
echo "Token: ${ACCESS_TOKEN:0:20}..."

# Test contacts endpoint
echo -e "\n2. Testing Contacts List..."
CONTACTS_RESPONSE=$(curl -s -X GET $API_URL/contacts \
  -H "Authorization: Bearer $ACCESS_TOKEN")

echo "Response: $CONTACTS_RESPONSE" | head -100

# Test create contact
echo -e "\n3. Testing Create Contact..."
CREATE_RESPONSE=$(curl -s -X POST $API_URL/contacts \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Test",
    "last_name": "User",
    "email1": "test@example.com",
    "phone_mobile": "+1234567890"
  }')

echo "Response: $CREATE_RESPONSE"

echo -e "\n✅ API tests completed!"