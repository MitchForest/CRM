#!/bin/bash

# Reset and seed database script
# Usage: ./reset_and_seed.sh

echo "=== Database Reset and Seed Script ==="
echo ""
echo "WARNING: This will DELETE ALL DATA in the database!"
echo "Are you sure you want to continue? (yes/no)"
read -r confirmation

if [ "$confirmation" != "yes" ]; then
    echo "Operation cancelled."
    exit 0
fi

echo ""
echo "Step 1: Resetting database..."
php ../install/reset_database.php

if [ $? -ne 0 ]; then
    echo "Error: Database reset failed!"
    exit 1
fi

echo ""
echo "Step 2: Seeding database with test data..."
php ../install/seed_phase5_data.php

if [ $? -ne 0 ]; then
    echo "Error: Database seeding failed!"
    exit 1
fi

echo ""
echo "=== Database Reset and Seed Complete ==="
echo ""
echo "Test credentials:"
echo "Email: john.doe@example.com"
echo "Password: admin123"
echo ""
echo "You can now run the API verification script:"
echo "php verify_apis.php"