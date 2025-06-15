#!/bin/bash

# Update dependencies to ensure dev dependencies are installed
echo "Updating dependencies..."
composer update

# Run the PHPUnit tests
echo "Running tests..."
vendor/bin/phpunit
