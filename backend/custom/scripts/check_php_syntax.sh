#!/bin/bash

echo "Checking PHP syntax in custom API directory..."
echo "=========================================="

ERROR_COUNT=0
FILE_COUNT=0

# Find all PHP files in the custom/api directory
find /var/www/html/custom/api -name "*.php" -type f | while read file; do
    FILE_COUNT=$((FILE_COUNT + 1))
    
    # Check syntax
    OUTPUT=$(php -l "$file" 2>&1)
    
    if [[ $OUTPUT != *"No syntax errors detected"* ]]; then
        echo "❌ Error in $file:"
        echo "$OUTPUT"
        echo ""
        ERROR_COUNT=$((ERROR_COUNT + 1))
    fi
done

# Also check controller files
echo -e "\nChecking controller files..."
find /var/www/html/custom/api/controllers -name "*.php" -type f | while read file; do
    OUTPUT=$(php -l "$file" 2>&1)
    
    if [[ $OUTPUT != *"No syntax errors detected"* ]]; then
        echo "❌ Error in $file:"
        echo "$OUTPUT"
        echo ""
        ERROR_COUNT=$((ERROR_COUNT + 1))
    else
        echo "✓ $file"
    fi
done

if [ $ERROR_COUNT -eq 0 ]; then
    echo -e "\n✅ All PHP files have valid syntax!"
else
    echo -e "\n❌ Found $ERROR_COUNT files with syntax errors"
fi

# Check for common issues
echo -e "\n\nChecking for common issues..."
echo "=============================="

# Check for undefined variables
echo -e "\nChecking for potential undefined variables..."
grep -n "\$[a-zA-Z_][a-zA-Z0-9_]*" /var/www/html/custom/api/controllers/*.php | grep -v "function\|global\|isset\|empty\|=\|->|\$this" | head -20

# Check for missing semicolons
echo -e "\nChecking for missing semicolons..."
grep -n "^[[:space:]]*[^{};/].*[^{};]$" /var/www/html/custom/api/controllers/*.php | grep -v "^\s*//" | head -10

echo -e "\nSyntax check complete!"