#!/bin/bash

# Setup OpenAI API Key for Phase 3 Testing
# This script helps configure the OpenAI API key for the CRM

echo "=================================="
echo "OpenAI API Key Configuration"
echo "=================================="
echo ""

# Check if key is already in environment
if [ ! -z "$OPENAI_API_KEY" ]; then
    echo "✓ OPENAI_API_KEY is already set in environment"
    echo ""
    echo "To use it in the CRM, add it to your docker-compose.yml:"
    echo "  environment:"
    echo "    - OPENAI_API_KEY=\${OPENAI_API_KEY}"
    echo ""
else
    echo "⚠️  OPENAI_API_KEY is not set"
    echo ""
    echo "To configure OpenAI integration:"
    echo ""
    echo "1. Get your API key from: https://platform.openai.com/api-keys"
    echo ""
    echo "2. Add to your .env file:"
    echo "   OPENAI_API_KEY=sk-..."
    echo ""
    echo "3. Update docker-compose.yml to pass the environment variable:"
    echo "   services:"
    echo "     app:"
    echo "       environment:"
    echo "         - OPENAI_API_KEY=\${OPENAI_API_KEY}"
    echo ""
    echo "4. Restart containers:"
    echo "   docker-compose down"
    echo "   docker-compose up -d"
fi

echo ""
echo "Current configuration:"
echo "---------------------"

# Check if config file has the key
if [ -f "./custom/config/ai_config.php" ]; then
    if grep -q "OPENAI_API_KEY" "./custom/config/ai_config.php"; then
        echo "✓ AI config file exists and references OPENAI_API_KEY"
    else
        echo "✗ AI config file exists but doesn't reference OPENAI_API_KEY"
    fi
else
    echo "✗ AI config file not found"
fi

echo ""
echo "Without a valid OpenAI API key, these features won't work:"
echo "- Lead AI Scoring"
echo "- Knowledge Base semantic search"
echo "- AI Chatbot conversations"
echo "- Smart lead enrichment"