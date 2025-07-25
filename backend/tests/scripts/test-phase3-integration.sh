#!/bin/bash

# Phase 3 Integration Test Script
# Tests all Phase 3 features through API calls

set -e

echo "=========================================="
echo "Phase 3 Frontend-Backend Integration Tests"
echo "=========================================="
echo ""

# Configuration
API_BASE="http://localhost:8080/api/v8"
OPENAI_API_KEY="${OPENAI_API_KEY:-your-test-key}"

# Color codes for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper function to print test results
print_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}"
    else
        echo -e "${RED}✗ $2${NC}"
        return 1
    fi
}

# Helper function to extract JSON value
get_json_value() {
    echo "$1" | jq -r "$2" 2>/dev/null || echo ""
}

# Start tests
echo "Setting up test environment..."
export OPENAI_API_KEY

# Get auth token
echo "Authenticating..."
AUTH_RESPONSE=$(curl -s -X POST "$API_BASE/login" \
    -H "Content-Type: application/json" \
    -d '{
        "grant_type": "password",
        "client_id": "sugar",
        "username": "admin",
        "password": "admin123"
    }')

ACCESS_TOKEN=$(get_json_value "$AUTH_RESPONSE" '.access_token')

if [ -z "$ACCESS_TOKEN" ] || [ "$ACCESS_TOKEN" = "null" ]; then
    echo -e "${RED}✗ Authentication failed${NC}"
    echo "Response: $AUTH_RESPONSE"
    exit 1
fi

print_result 0 "Authentication successful"
echo ""

# Test 1: AI Lead Scoring
echo "1. Testing AI Lead Scoring..."
echo "   Creating test lead..."

LEAD_RESPONSE=$(curl -s -X POST "$API_BASE/module/Leads" \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "data": {
            "type": "Leads",
            "attributes": {
                "first_name": "Integration",
                "last_name": "Test Lead",
                "email": "test@techcorp.com",
                "account_name": "Tech Corp Enterprise",
                "title": "CTO",
                "industry": "Technology",
                "website": "https://techcorp.com",
                "lead_source": "Website"
            }
        }
    }')

LEAD_ID=$(get_json_value "$LEAD_RESPONSE" '.data.id')

if [ -z "$LEAD_ID" ] || [ "$LEAD_ID" = "null" ]; then
    print_result 1 "Lead creation failed"
else
    print_result 0 "Lead created: $LEAD_ID"
    
    # Score the lead
    echo "   Scoring lead..."
    SCORE_RESPONSE=$(curl -s -X POST "$API_BASE/leads/$LEAD_ID/ai-score" \
        -H "Authorization: Bearer $ACCESS_TOKEN" \
        -H "Content-Type: application/json")
    
    SCORE=$(get_json_value "$SCORE_RESPONSE" '.score')
    
    if [ -n "$SCORE" ] && [ "$SCORE" -ge 0 ] && [ "$SCORE" -le 100 ] 2>/dev/null; then
        print_result 0 "AI Lead Scoring working - Score: $SCORE"
        
        # Check for required fields
        FACTORS=$(get_json_value "$SCORE_RESPONSE" '.factors')
        INSIGHTS=$(get_json_value "$SCORE_RESPONSE" '.insights')
        
        if [ -n "$FACTORS" ] && [ "$FACTORS" != "null" ]; then
            print_result 0 "Score factors present"
        else
            print_result 1 "Score factors missing"
        fi
    else
        print_result 1 "AI Lead Scoring failed"
        echo "   Response: $SCORE_RESPONSE"
    fi
fi
echo ""

# Test 2: Form Builder
echo "2. Testing Form Builder..."
FORM_RESPONSE=$(curl -s -X POST "$API_BASE/forms" \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "name": "Integration Test Form",
        "fields": [
            {
                "name": "email",
                "type": "email",
                "label": "Email",
                "required": true
            },
            {
                "name": "company",
                "type": "text",
                "label": "Company",
                "required": true
            }
        ],
        "settings": {
            "submit_button_text": "Submit",
            "success_message": "Thank you!"
        }
    }')

FORM_ID=$(get_json_value "$FORM_RESPONSE" '.id')

if [ -z "$FORM_ID" ] || [ "$FORM_ID" = "null" ]; then
    print_result 1 "Form creation failed"
else
    print_result 0 "Form created: $FORM_ID"
    
    # Test form submission
    echo "   Testing form submission..."
    SUBMISSION_RESPONSE=$(curl -s -X POST "$API_BASE/forms/$FORM_ID/submit" \
        -H "Content-Type: application/json" \
        -d '{
            "email": "demo@example.com",
            "company": "Example Corp"
        }')
    
    SUCCESS=$(get_json_value "$SUBMISSION_RESPONSE" '.success')
    
    if [ "$SUCCESS" = "true" ]; then
        print_result 0 "Form submission successful"
        
        SUBMISSION_ID=$(get_json_value "$SUBMISSION_RESPONSE" '.submission_id')
        if [ -n "$SUBMISSION_ID" ] && [ "$SUBMISSION_ID" != "null" ]; then
            print_result 0 "Submission tracked: $SUBMISSION_ID"
        fi
    else
        print_result 1 "Form submission failed"
    fi
fi
echo ""

# Test 3: Knowledge Base
echo "3. Testing Knowledge Base..."

# Create category
CATEGORY_RESPONSE=$(curl -s -X POST "$API_BASE/knowledge-base/categories" \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "name": "Product Features",
        "description": "Learn about our features"
    }')

CATEGORY_ID=$(get_json_value "$CATEGORY_RESPONSE" '.id')

if [ -z "$CATEGORY_ID" ] || [ "$CATEGORY_ID" = "null" ]; then
    print_result 1 "Category creation failed"
else
    print_result 0 "KB category created"
    
    # Create article
    ARTICLE_RESPONSE=$(curl -s -X POST "$API_BASE/knowledge-base/articles" \
        -H "Authorization: Bearer $ACCESS_TOKEN" \
        -H "Content-Type: application/json" \
        -d "{
            \"title\": \"Getting Started with AI Lead Scoring\",
            \"content\": \"<p>Our AI lead scoring helps identify hot prospects.</p>\",
            \"category_id\": \"$CATEGORY_ID\",
            \"tags\": [\"ai\", \"lead-scoring\"],
            \"is_public\": true
        }")
    
    ARTICLE_ID=$(get_json_value "$ARTICLE_RESPONSE" '.id')
    
    if [ -n "$ARTICLE_ID" ] && [ "$ARTICLE_ID" != "null" ]; then
        print_result 0 "KB article created"
        
        # Test search
        echo "   Testing semantic search..."
        SEARCH_RESPONSE=$(curl -s -G "$API_BASE/knowledge-base/search" \
            -H "Authorization: Bearer $ACCESS_TOKEN" \
            --data-urlencode "q=AI lead scoring tutorial" \
            --data-urlencode "limit=5")
        
        # Check if response is an array
        if echo "$SEARCH_RESPONSE" | jq -e 'type == "array"' >/dev/null 2>&1; then
            print_result 0 "KB semantic search working"
        else
            print_result 1 "KB search failed"
        fi
    else
        print_result 1 "Article creation failed"
    fi
fi
echo ""

# Test 4: Activity Tracking
echo "4. Testing Activity Tracking..."
VISITOR_ID="test_visitor_$(date +%s)"

# Track page view
TRACKING_RESPONSE=$(curl -s -X POST "$API_BASE/track/pageview" \
    -H "Content-Type: application/json" \
    -d "{
        \"visitor_id\": \"$VISITOR_ID\",
        \"url\": \"https://example.com/pricing\",
        \"title\": \"Pricing - Example CRM\",
        \"referrer\": \"https://google.com\"
    }")

SESSION_ID=$(get_json_value "$TRACKING_RESPONSE" '.session_id')

if [ -n "$SESSION_ID" ] && [ "$SESSION_ID" != "null" ]; then
    print_result 0 "Activity tracking working - Session: ${SESSION_ID:0:8}..."
    
    # Track engagement
    ENGAGEMENT_RESPONSE=$(curl -s -X POST "$API_BASE/track/engagement" \
        -H "Content-Type: application/json" \
        -d "{
            \"visitor_id\": \"$VISITOR_ID\",
            \"session_id\": \"$SESSION_ID\",
            \"type\": \"click\",
            \"data\": {
                \"element\": \"button\",
                \"text\": \"Get Started\"
            }
        }")
    
    SUCCESS=$(get_json_value "$ENGAGEMENT_RESPONSE" '.success')
    if [ "$SUCCESS" = "true" ]; then
        print_result 0 "Engagement tracking working"
    else
        print_result 1 "Engagement tracking failed"
    fi
else
    print_result 1 "Activity tracking failed"
fi
echo ""

# Test 5: AI Chatbot
echo "5. Testing AI Chatbot..."
CHAT_RESPONSE=$(curl -s -X POST "$API_BASE/ai/chat" \
    -H "Content-Type: application/json" \
    -d '{
        "messages": [
            {"role": "user", "content": "What are your pricing plans?"}
        ],
        "context": {
            "visitor_id": "test_chat_visitor",
            "page_url": "https://example.com"
        }
    }')

CHAT_CONTENT=$(get_json_value "$CHAT_RESPONSE" '.response')
CONVERSATION_ID=$(get_json_value "$CHAT_RESPONSE" '.conversation_id')

if [ -n "$CHAT_CONTENT" ] && [ "$CHAT_CONTENT" != "null" ]; then
    print_result 0 "AI Chatbot responding"
    
    if [ -n "$CONVERSATION_ID" ] && [ "$CONVERSATION_ID" != "null" ]; then
        print_result 0 "Conversation tracked: ${CONVERSATION_ID:0:8}..."
    fi
else
    print_result 1 "AI Chatbot failed"
    echo "   Response: $CHAT_RESPONSE"
fi
echo ""

# Test 6: Batch Lead Scoring
echo "6. Testing Batch Lead Scoring..."
LEAD_IDS=()

# Create multiple leads
for i in {1..3}; do
    LEAD=$(curl -s -X POST "$API_BASE/module/Leads" \
        -H "Authorization: Bearer $ACCESS_TOKEN" \
        -H "Content-Type: application/json" \
        -d "{
            \"data\": {
                \"type\": \"Leads\",
                \"attributes\": {
                    \"first_name\": \"Batch\",
                    \"last_name\": \"Test $i\",
                    \"email\": \"batch$i@example.com\",
                    \"account_name\": \"Company $i\"
                }
            }
        }")
    
    LEAD_ID=$(get_json_value "$LEAD" '.data.id')
    if [ -n "$LEAD_ID" ] && [ "$LEAD_ID" != "null" ]; then
        LEAD_IDS+=("\"$LEAD_ID\"")
    fi
done

if [ ${#LEAD_IDS[@]} -eq 3 ]; then
    # Join array elements with comma
    LEAD_IDS_JSON=$(IFS=,; echo "[${LEAD_IDS[*]}]")
    
    BATCH_RESPONSE=$(curl -s -X POST "$API_BASE/leads/ai-score-batch" \
        -H "Authorization: Bearer $ACCESS_TOKEN" \
        -H "Content-Type: application/json" \
        -d "{\"lead_ids\": $LEAD_IDS_JSON}")
    
    # Count scored leads
    SCORED_COUNT=$(echo "$BATCH_RESPONSE" | jq '[to_entries[] | select(.value.score != null)] | length' 2>/dev/null || echo "0")
    
    if [ "$SCORED_COUNT" -eq 3 ]; then
        print_result 0 "Batch lead scoring successful - Scored $SCORED_COUNT leads"
    else
        print_result 1 "Batch lead scoring incomplete - Only $SCORED_COUNT/3 scored"
    fi
else
    print_result 1 "Failed to create test leads for batch scoring"
fi
echo ""

# Test 7: Customer Health Scoring
echo "7. Testing Customer Health Scoring..."

# Create a customer account
ACCOUNT_RESPONSE=$(curl -s -X POST "$API_BASE/module/Accounts" \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "data": {
            "type": "Accounts",
            "attributes": {
                "name": "Test Customer Inc",
                "account_type": "Customer",
                "industry": "Technology",
                "annual_revenue": "5000000"
            }
        }
    }')

ACCOUNT_ID=$(get_json_value "$ACCOUNT_RESPONSE" '.data.id')

if [ -n "$ACCOUNT_ID" ] && [ "$ACCOUNT_ID" != "null" ]; then
    print_result 0 "Customer account created"
    
    # Calculate health score
    HEALTH_RESPONSE=$(curl -s -X POST "$API_BASE/accounts/$ACCOUNT_ID/health-score" \
        -H "Authorization: Bearer $ACCESS_TOKEN")
    
    HEALTH_SCORE=$(get_json_value "$HEALTH_RESPONSE" '.score')
    RISK_LEVEL=$(get_json_value "$HEALTH_RESPONSE" '.risk_level')
    
    if [ -n "$HEALTH_SCORE" ] && [ "$HEALTH_SCORE" -ge 0 ] && [ "$HEALTH_SCORE" -le 100 ] 2>/dev/null; then
        print_result 0 "Health score calculated: $HEALTH_SCORE (Risk: $RISK_LEVEL)"
        
        # Check recommendations
        RECOMMENDATIONS=$(get_json_value "$HEALTH_RESPONSE" '.recommendations')
        if [ -n "$RECOMMENDATIONS" ] && [ "$RECOMMENDATIONS" != "null" ] && [ "$RECOMMENDATIONS" != "[]" ]; then
            print_result 0 "Health recommendations generated"
        fi
    else
        print_result 1 "Health score calculation failed"
    fi
else
    print_result 1 "Customer account creation failed"
fi
echo ""

# Test 8: Webhook Health Check
echo "8. Testing Webhook Health Check..."
WEBHOOK_RESPONSE=$(curl -s -X POST "$API_BASE/webhooks/health-check" \
    -H "X-Webhook-Token: your-secret-token-here")

WEBHOOK_MESSAGE=$(get_json_value "$WEBHOOK_RESPONSE" '.message')

if [ "$WEBHOOK_MESSAGE" = "Health check completed" ]; then
    print_result 0 "Webhook health check working"
    
    ACCOUNTS_PROCESSED=$(get_json_value "$WEBHOOK_RESPONSE" '.at_risk_accounts_processed')
    echo "   At-risk accounts processed: $ACCOUNTS_PROCESSED"
else
    print_result 1 "Webhook health check failed"
fi
echo ""

# Summary
echo "=========================================="
echo "Phase 3 Integration Test Summary"
echo "=========================================="
echo ""
echo "Critical Features Tested:"
echo "- AI Lead Scoring: ✓"
echo "- Form Builder: ✓"
echo "- Knowledge Base: ✓"
echo "- Activity Tracking: ✓"
echo "- AI Chatbot: ✓"
echo "- Customer Health Scoring: ✓"
echo "- Batch Processing: ✓"
echo "- Webhook Integration: ✓"
echo ""
echo "All Phase 3 features are operational!"
echo ""

# Performance check
echo "Running basic performance check..."
START_TIME=$(date +%s%N)
curl -s -X GET "$API_BASE/health" > /dev/null
END_TIME=$(date +%s%N)
RESPONSE_TIME=$(((END_TIME - START_TIME) / 1000000))

echo "API Response Time: ${RESPONSE_TIME}ms"

if [ $RESPONSE_TIME -lt 500 ]; then
    print_result 0 "Performance is good"
else
    print_result 1 "Performance may need optimization (>500ms)"
fi

echo ""
echo "Phase 3 integration tests completed successfully!"