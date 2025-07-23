#!/bin/bash

echo "Running API tests..."

# Run tests using the test API endpoints directly
echo "Testing Authentication..."
docker-compose exec suitecrm bash -c "cd /var/www/html/custom/api && php test-login.php"

echo -e "\nTesting all endpoints..."
docker-compose exec suitecrm bash -c "cd /var/www/html/custom/api && bash test-all-endpoints.sh"

echo -e "\nTests complete!"