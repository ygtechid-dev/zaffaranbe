#!/bin/bash

# Test Payment Status Endpoint
# Usage: ./test-payment-status.sh <payment_id> <auth_token>

PAYMENT_ID=$1
AUTH_TOKEN=$2

if [ -z "$PAYMENT_ID" ] || [ -z "$AUTH_TOKEN" ]; then
    echo "Usage: ./test-payment-status.sh <payment_id> <auth_token>"
    echo "Example: ./test-payment-status.sh 123 your_bearer_token"
    exit 1
fi

echo "Testing payment status for payment_id: $PAYMENT_ID"
echo "=========================================="

curl -X GET "http://localhost:8000/api/v1/customer/payments/status/$PAYMENT_ID" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  -H "Content-Type: application/json" \
  -v

echo ""
echo "=========================================="
