#!/bin/bash

# Run tests in Docker container

echo "Setting up test environment..."

# Install composer in the container if not present
docker-compose exec suitecrm which composer > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "Installing Composer..."
    docker-compose exec suitecrm bash -c "
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    "
fi

# Copy backend composer.json to a temporary location and install dependencies
echo "Installing test dependencies..."
docker-compose exec suitecrm bash -c "
    cp /var/www/html/../backend/composer.json /tmp/composer.json &&
    cd /tmp && composer install --no-interaction
"

# Copy test files and vendor to the container
echo "Setting up test files..."
docker-compose exec suitecrm bash -c "
    mkdir -p /var/www/html/backend-tests &&
    cp -r /tmp/vendor /var/www/html/backend-tests/ &&
    cp -r /var/www/html/../backend/tests /var/www/html/backend-tests/ &&
    cp /var/www/html/../backend/phpunit.xml /var/www/html/backend-tests/
"

# Run the tests
echo "Running tests..."
docker-compose exec -w /var/www/html/backend-tests suitecrm ./vendor/bin/phpunit

# Cleanup
echo "Cleaning up..."
docker-compose exec suitecrm rm -rf /var/www/html/backend-tests /tmp/vendor /tmp/composer.*