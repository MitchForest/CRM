#!/bin/bash

echo "=== Running Backend Tests in Docker ==="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Change to project root
cd "$(dirname "$0")/../../.."

# Step 1: Check if containers are running
echo -e "${YELLOW}Checking Docker containers...${NC}"
if ! docker ps | grep -q suitecrm-backend; then
    echo -e "${RED}Error: suitecrm-backend container is not running${NC}"
    echo "Please run: docker-compose up -d"
    exit 1
fi

# Step 2: Install test dependencies using the backend composer.json
echo -e "${YELLOW}Installing test dependencies...${NC}"
docker exec suitecrm-backend bash -c "cd /var/www/html && \
    if [ -f composer-backend.json ]; then \
        composer install --no-interaction --working-dir=/var/www/html \
            --no-scripts --no-plugins \
            -d /tmp/backend-deps \
            --no-dev; \
        composer require --dev phpunit/phpunit:^9.5 mockery/mockery:^1.5 fakerphp/faker:^1.20 \
            --working-dir=/var/www/html; \
    fi"

# Step 3: Create a temporary PHPUnit runner
echo -e "${YELLOW}Creating PHPUnit runner...${NC}"
docker exec suitecrm-backend bash -c 'cat > /var/www/html/run-phpunit.php << '\''EOF'\''
<?php
// Simple PHPUnit runner that bypasses Composer autoloader issues

// Set up basic autoloader for our test classes
spl_autoload_register(function ($class) {
    $prefixes = [
        '\''Api\\'\'' => __DIR__ . '\''/custom/api/'\'',
        '\''Tests\\'\'' => __DIR__ . '\''/tests/'\''
    ];
    
    foreach ($prefixes as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = $dir . str_replace('\''\\'\'' , '\''/'\'' , substr($class, strlen($prefix))) . '\''.php'\'';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
});

// Load PHPUnit directly
$phpunitPath = __DIR__ . '\''/vendor/phpunit/phpunit/src/TextUI/Command.php'\'';
if (!file_exists($phpunitPath)) {
    die("PHPUnit not found. Please install it first.\n");
}

require_once __DIR__ . '\''/vendor/autoload.php'\'';

// Run PHPUnit
$command = new PHPUnit\TextUI\Command();
exit($command->run($argv));
EOF'

# Step 4: Run tests using our custom runner
echo -e "${YELLOW}Running tests...${NC}"
docker exec -w /var/www/html suitecrm-backend php run-phpunit.php \
    -c phpunit.xml \
    --colors=always \
    --verbose

# Check test results
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tests completed successfully!${NC}"
else
    echo -e "${RED}✗ Tests failed${NC}"
    
    # Show additional debugging info
    echo ""
    echo -e "${YELLOW}Debug Information:${NC}"
    echo "Container PHP version:"
    docker exec suitecrm-backend php -v | head -1
    echo ""
    echo "PHPUnit installation:"
    docker exec suitecrm-backend ls -la /var/www/html/vendor/bin/phpunit 2>/dev/null || echo "PHPUnit binary not found"
fi

echo ""
echo "=== Test execution complete ==="