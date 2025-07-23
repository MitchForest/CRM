#!/bin/bash

echo "=== Fixing and Running Backend Tests ==="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Change to project root
cd "$(dirname "$0")/../../.."

# Step 1: Rebuild the Docker container with Composer
echo -e "${YELLOW}Step 1: Rebuilding Docker container with Composer...${NC}"
docker-compose build suitecrm
if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to rebuild Docker container${NC}"
    exit 1
fi

# Step 2: Start containers
echo -e "${YELLOW}Step 2: Starting containers...${NC}"
docker-compose up -d suitecrm mysql
sleep 10  # Wait for containers to be ready

# Step 3: Fix Composer dependencies inside container
echo -e "${YELLOW}Step 3: Installing Composer dependencies...${NC}"
docker-compose exec suitecrm bash -c "cd /var/www/html && composer install --no-interaction"

# Step 4: Set up test database
echo -e "${YELLOW}Step 4: Setting up test database...${NC}"
docker-compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS suitecrm_test;"
docker-compose exec mysql mysql -uroot -proot -e "GRANT ALL PRIVILEGES ON suitecrm_test.* TO 'suitecrm'@'%';"
docker-compose exec mysql mysql -uroot -proot -e "FLUSH PRIVILEGES;"

# Step 5: Run PHPUnit tests
echo -e "${YELLOW}Step 5: Running PHPUnit tests...${NC}"
docker-compose exec -e SUITECRM_PATH=/var/www/html suitecrm bash -c "cd /var/www/html && vendor/bin/phpunit -c phpunit.xml"

# Check test results
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests passed successfully!${NC}"
else
    echo -e "${RED}✗ Tests failed${NC}"
    exit 1
fi

echo ""
echo "=== Test execution complete ==="