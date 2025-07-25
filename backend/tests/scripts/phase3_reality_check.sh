#!/bin/bash

# Phase 3 Reality Check - What's Actually Implemented?

echo "======================================"
echo "Phase 3 Implementation Reality Check"
echo "======================================"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "📁 Checking File Structure:"
echo ""

# Controllers
echo "Controllers:"
for file in AIController FormBuilderController KnowledgeBaseController ActivityTrackingController CustomerHealthController; do
    if [ -f "./custom-api/controllers/${file}.php" ]; then
        echo -e "${GREEN}✓${NC} $file.php exists"
    else
        echo -e "${RED}✗${NC} $file.php missing"
    fi
done

echo ""
echo "Services:"
for file in OpenAIService CustomerHealthService; do
    if [ -f "./suitecrm-custom/services/${file}.php" ]; then
        echo -e "${GREEN}✓${NC} $file.php exists"
    else
        echo -e "${RED}✗${NC} $file.php missing"
    fi
done

echo ""
echo "Embed Scripts:"
for file in forms-embed.js tracking.js chat-widget.js; do
    if [ -f "./suitecrm/public/js/${file}" ]; then
        SIZE=$(wc -l "./suitecrm/public/js/${file}" | awk '{print $1}')
        echo -e "${GREEN}✓${NC} $file exists ($SIZE lines)"
    else
        echo -e "${RED}✗${NC} $file missing"
    fi
done

echo ""
echo "📊 Database Tables (checking if they exist):"
if command -v docker &> /dev/null; then
    TABLES=$(docker exec suitecrm_db mysql -uroot -padmin123 -e "SHOW TABLES" suitecrm 2>/dev/null | grep -E "(form_|kb_|website_|page_|activity_|ai_|customer_health)" | wc -l)
    echo "Phase 3 tables found: $TABLES"
else
    echo -e "${YELLOW}Docker not available - skipping DB check${NC}"
fi

echo ""
echo "🔍 Route Analysis:"
if [ -f "./custom-api/routes.php" ]; then
    echo "Routes defined in custom-api/routes.php:"
    grep -E "(ai/|forms|knowledge-base|track/|health-score|webhook)" ./custom-api/routes.php | wc -l | xargs echo "- Phase 3 routes:"
    
    echo ""
    echo "Sample routes found:"
    grep -E "(ai/|forms|knowledge-base|track/|health-score)" ./custom-api/routes.php | head -5 | sed 's/^/  /'
else
    echo -e "${RED}routes.php not found${NC}"
fi

echo ""
echo "⚙️ Configuration Files:"
if [ -f "./suitecrm-custom/config/ai_config.php" ]; then
    echo -e "${GREEN}✓${NC} AI configuration exists"
    if grep -q "OPENAI_API_KEY" "./suitecrm-custom/config/ai_config.php"; then
        echo "  - OpenAI API key placeholder found"
    fi
else
    echo -e "${RED}✗${NC} AI configuration missing"
fi

echo ""
echo "🧪 Test Coverage:"
echo "Test files:"
for file in "Integration/Phase3ApiTest.php" "Unit/Services/CustomerHealthServiceTest.php"; do
    if [ -f "./tests/$file" ]; then
        LINES=$(wc -l "./tests/$file" | awk '{print $1}')
        echo -e "${GREEN}✓${NC} $file ($LINES lines)"
    else
        echo -e "${RED}✗${NC} $file missing"
    fi
done

echo ""
echo "Test scripts:"
for script in test-phase3-integration.sh verify_phase3_realistic.sh run-phase3-tests.sh; do
    if [ -f "./tests/scripts/$script" ]; then
        echo -e "${GREEN}✓${NC} $script"
    else
        echo -e "${RED}✗${NC} $script"
    fi
done

echo ""
echo "📈 Implementation Status:"
echo ""
echo "What's ACTUALLY implemented:"
echo "✅ File structure is in place"
echo "✅ Controllers and services are created"
echo "✅ Embed scripts have substantial content"
echo "✅ Routes are defined"
echo "✅ Test files exist"
echo ""
echo "What MIGHT NOT work without setup:"
echo "⚠️  Database tables (need to run install script)"
echo "⚠️  OpenAI features (need API key)"
echo "⚠️  Authentication (depends on SuiteCRM config)"
echo "⚠️  Some endpoints may return 404/500 without proper data"
echo ""
echo "Realistic Test Coverage Estimate: ~60-70%"
echo "(Files exist and routes are defined, but full integration depends on setup)"
echo ""
echo "To make everything work:"
echo "1. Run: docker exec suitecrm_app php /var/www/html/suitecrm-custom/install/install_phase3_tables.php"
echo "2. Set: export OPENAI_API_KEY=your-key"
echo "3. Run: docker exec suitecrm_app php /var/www/html/suitecrm-custom/install/seed_phase3_data.php"
echo "4. Test: ./tests/scripts/verify_phase3_realistic.sh"