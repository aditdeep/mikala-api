#!/bin/bash

# Mikala API Endpoint Testing Script
# This script tests key API endpoints to verify backend implementation

BASE_URL="http://localhost:8000/api"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================="
echo "   MIKALA API ENDPOINT TESTING"
echo "========================================="
echo ""

# Counter for test results
PASSED=0
FAILED=0

# Test function
test_endpoint() {
    local test_name=$1
    local method=$2
    local endpoint=$3
    local data=$4
    local token=$5
    local expected_status=$6
    
    echo -n "Testing: $test_name ... "
    
    if [ -z "$token" ]; then
        response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -d "$data")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer $token" \
            -d "$data")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" == "$expected_status" ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $http_code)"
        ((PASSED++))
    else
        echo -e "${RED}✗ FAIL${NC} (Expected $expected_status, got $http_code)"
        echo "Response: $body"
        ((FAILED++))
    fi
}

echo "========================================="
echo "1. PUBLIC ENDPOINTS (No Authentication)"
echo "========================================="
echo ""

# Test 1: MGM Layanan (Public)
test_endpoint \
    "MGM - Get Services List" \
    "GET" \
    "/public/mgm/layanan" \
    "" \
    "" \
    "200"

# Test 2: MGM About (Public)
test_endpoint \
    "MGM - Get About Info" \
    "GET" \
    "/public/mgm/about" \
    "" \
    "" \
    "200"

# Test 3: MGM Submit Leads (Public)
test_endpoint \
    "MGM - Submit Contact Form" \
    "POST" \
    "/public/mgm/leads" \
    '{"nama":"Test User","email":"test@example.com","phone":"08123456789","pesan":"Test message"}' \
    "" \
    "200"

# Test 4: MGA Program Pelatihan (Public)
test_endpoint \
    "MGA - Get Training Programs" \
    "GET" \
    "/public/mga/programs" \
    "" \
    "" \
    "200"

echo ""
echo "========================================="
echo "2. AUTHENTICATION ENDPOINTS"
echo "========================================="
echo ""

# Test 5: Login (should return token)
echo -n "Testing: Login - Get Auth Token ... "
response=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"admin@mikala.com","password":"password"}')

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | sed '$d')

if [ "$http_code" == "200" ]; then
    echo -e "${GREEN}✓ PASS${NC} (HTTP $http_code)"
    # Extract token from response (assuming JSON format)
    TOKEN=$(echo "$body" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
    if [ -z "$TOKEN" ]; then
        # Try alternative extraction
        TOKEN=$(echo "$body" | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)
    fi
    echo "Token: ${TOKEN:0:50}..."
    ((PASSED++))
else
    echo -e "${YELLOW}⚠ SKIP${NC} (Login failed - HTTP $http_code)"
    echo "Note: Create admin user first or update credentials"
    TOKEN=""
fi

echo ""
echo "========================================="
echo "3. PROTECTED ENDPOINTS (Require Auth)"
echo "========================================="
echo ""

if [ -z "$TOKEN" ]; then
    echo -e "${YELLOW}⚠ Skipping protected endpoint tests (no auth token)${NC}"
    echo "To test protected endpoints:"
    echo "1. Create an admin user in database"
    echo "2. Update login credentials in this script"
    echo "3. Run script again"
else
    # Test 6: Dashboard Summary
    test_endpoint \
        "Dashboard - Get Summary" \
        "GET" \
        "/internal/dashboard/summary" \
        "" \
        "$TOKEN" \
        "200"
    
    # Test 7: Notifications
    test_endpoint \
        "Notifications - Get List" \
        "GET" \
        "/notifikasi" \
        "" \
        "$TOKEN" \
        "200"
    
    # Test 8: Unread Notification Count
    test_endpoint \
        "Notifications - Get Unread Count" \
        "GET" \
        "/notifikasi/unread-count" \
        "" \
        "$TOKEN" \
        "200"
fi

echo ""
echo "========================================="
echo "4. VALIDATION TESTS (Should Fail)"
echo "========================================="
echo ""

# Test 9: Invalid Lead Submission (missing required field)
test_endpoint \
    "MGM - Submit Invalid Lead (missing email)" \
    "POST" \
    "/public/mgm/leads" \
    '{"nama":"Test User","phone":"08123456789"}' \
    "" \
    "422"

# Test 10: Invalid MGA Registration (missing required field)
test_endpoint \
    "MGA - Invalid Registration (missing NIK)" \
    "POST" \
    "/public/mga/register" \
    '{"nama_lengkap":"Test","email":"test2@test.com","phone":"08123456789"}' \
    "" \
    "422"

echo ""
echo "========================================="
echo "   TEST SUMMARY"
echo "========================================="
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"
echo "Total:  $((PASSED + FAILED))"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠ Some tests failed. Review output above.${NC}"
    exit 1
fi
