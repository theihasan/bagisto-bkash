#!/bin/bash

echo "🧪 Running bKash Package Tests"
echo "=============================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}⚠️  vendor directory not found. Please run 'composer install' first.${NC}"
    exit 1
fi

# Run different test suites
echo -e "${BLUE}📋 Running Unit Tests...${NC}"
./vendor/bin/phpunit --testsuite="Unit Tests" --colors=always

echo ""
echo -e "${BLUE}🔗 Running Integration Tests...${NC}"
./vendor/bin/phpunit --testsuite="Integration Tests" --colors=always

echo ""
echo -e "${BLUE}🎭 Running Feature Tests...${NC}"
./vendor/bin/phpunit --testsuite="Feature Tests" --colors=always

echo ""
echo -e "${BLUE}🏃 Running All Tests with Coverage...${NC}"
./vendor/bin/phpunit --coverage-text --colors=always

echo ""
echo -e "${GREEN}✅ Test execution completed!${NC}"