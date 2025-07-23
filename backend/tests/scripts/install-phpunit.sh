#!/bin/bash

echo "=== Installing PHPUnit Directly in Container ==="

# Download PHPUnit PHAR directly
echo "Downloading PHPUnit 9.5..."
docker exec suitecrm-backend bash -c "
    cd /var/www/html && \
    curl -LO https://phar.phpunit.de/phpunit-9.5.28.phar && \
    chmod +x phpunit-9.5.28.phar && \
    mv phpunit-9.5.28.phar /usr/local/bin/phpunit
"

# Verify installation
echo ""
echo "Verifying installation..."
docker exec suitecrm-backend phpunit --version

echo ""
echo "PHPUnit installed successfully!"
echo "You can now run tests with: docker exec suitecrm-backend phpunit -c /var/www/html/phpunit.xml"