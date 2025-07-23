#!/bin/bash

echo "=== Backend Status Check ==="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if container is running
echo -e "${YELLOW}1. Docker Container Status:${NC}"
if docker ps | grep -q suitecrm-backend; then
    echo -e "${GREEN}✓ suitecrm-backend is running${NC}"
else
    echo -e "${RED}✗ suitecrm-backend is not running${NC}"
    exit 1
fi

# Check PHP version
echo ""
echo -e "${YELLOW}2. PHP Version:${NC}"
docker exec suitecrm-backend php -v | head -1

# Check if SuiteCRM responds
echo ""
echo -e "${YELLOW}3. SuiteCRM Status:${NC}"
HTTP_CODE=$(docker exec suitecrm-backend curl -s -o /dev/null -w "%{http_code}" http://localhost/index.php)
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ SuiteCRM is responding (HTTP $HTTP_CODE)${NC}"
else
    echo -e "${RED}✗ SuiteCRM returned HTTP $HTTP_CODE${NC}"
fi

# Check if custom API responds
echo ""
echo -e "${YELLOW}4. Custom API Status:${NC}"
API_RESPONSE=$(docker exec suitecrm-backend curl -s http://localhost/custom/api/index.php/health 2>&1)
if echo "$API_RESPONSE" | grep -q "Fatal error"; then
    echo -e "${RED}✗ Custom API has fatal errors:${NC}"
    echo "$API_RESPONSE" | head -5
else
    echo -e "${GREEN}✓ Custom API is accessible${NC}"
fi

# Check database connection
echo ""
echo -e "${YELLOW}5. Database Connection:${NC}"
DB_CHECK=$(docker exec suitecrm-backend php -r "
    require_once('/var/www/html/config.php');
    \$db = mysqli_connect(
        \$sugar_config['dbconfig']['db_host_name'],
        \$sugar_config['dbconfig']['db_user_name'],
        \$sugar_config['dbconfig']['db_password'],
        \$sugar_config['dbconfig']['db_name']
    );
    echo mysqli_connect_error() ?: 'Connected';
" 2>&1)

if [ "$DB_CHECK" = "Connected" ]; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Database connection failed: $DB_CHECK${NC}"
fi

# Check Composer status
echo ""
echo -e "${YELLOW}6. Composer Status:${NC}"
if docker exec suitecrm-backend which composer > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Composer is installed${NC}"
    docker exec suitecrm-backend composer --version 2>/dev/null | head -1
else
    echo -e "${RED}✗ Composer is not installed${NC}"
fi

# Check PHPUnit status
echo ""
echo -e "${YELLOW}7. PHPUnit Status:${NC}"
if docker exec suitecrm-backend test -f /var/www/html/vendor/bin/phpunit; then
    echo -e "${GREEN}✓ PHPUnit binary exists${NC}"
    docker exec suitecrm-backend /var/www/html/vendor/bin/phpunit --version 2>&1 | head -1
else
    echo -e "${RED}✗ PHPUnit binary not found${NC}"
fi

# Check test files
echo ""
echo -e "${YELLOW}8. Test Files:${NC}"
TEST_COUNT=$(find /Users/mitchellwhite/Code/crm/backend/tests -name "*Test.php" | wc -l | tr -d ' ')
echo "Found $TEST_COUNT test files"

echo ""
echo "=== Status check complete ==="