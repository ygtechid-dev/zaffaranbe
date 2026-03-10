# Documentation: DOKU Payment Webhook Integration

This document outlines the endpoint and security requirements for DOKU payment notifications (webhooks) integrated into the Naqupos Spa backend.

## 1. Endpoint Overview

*   **URL**: `https://[BASE_URL]/api/v1/payments/callback`
*   **Method**: `POST`
*   **Content-Type**: `application/json`
*   **Authentication**: Custom HMAC Signature (Doku Standard)

## 2. Request Headers

The following headers are mandatory for request validation:

| Header | Description | Required |
| :--- | :--- | :--- |
| `Client-Id` | Your DOKU Client ID. | Yes |
| `Request-Id` | Unique ID generated for the request. | Yes |
| `Request-Timestamp` | ISO 8601 formatted timestamp (UTC). | Yes |
| `Signature` | HMAC-SHA256 signature for verification. | Yes |

## 3. Webhook Payload (JSON)

DOKU sends the following payload structure to notify the system of transaction status changes:

```json
{
  "order": {
    "invoice_number": "TRX-20240214-1234",
    "amount": 150000
  },
  "transaction": {
    "status": "SUCCESS",
    "date": "2024-02-14T15:26:37Z"
  },
  "service": {
      "id": "SERVICE_ID"
  }
}
```

### Field Definitions:
- `order.invoice_number`: The internal reference used during payment initiation.
- `order.amount`: Total transaction amount (verified against internal records).
- `transaction.status`: The status of the payment. Possible values:
    - `SUCCESS`: Payment completed successfully.
    - `FAILED`: Payment failed or was rejected.

## 4. Signature Verification Logic

The system strictly validates every incoming request from DOKU using an HMAC-SHA256 signature.

### Signature Generation Pattern:
```text
HMACSHA256 = "HMACSHA256=" + Base64(
    HMAC-SHA256(
        "Client-Id:" + <ClientId> + "\n" +
        "Request-Id:" + <RequestId> + "\n" +
        "Request-Timestamp:" + <RequestTimestamp> + "\n" +
        "Request-Target:" + <RequestPath> + "\n" +
        "Digest:" + <BodyDigest>,
        <SecretKey>
    )
)
```

### Components:
1.  **Digest**: `Base64(SHA256(RawRequestBody))`
2.  **Request-Target**: The exact path (e.g., `/api/v1/payments/callback`).
3.  **Secret Key**: Provided by DOKU Dashboard.

## 5. Security Measures

1.  **Timestamp Validation**: Requests with a timestamp older than 5 minutes are rejected to prevent replay attacks.
2.  **Idempotency**: The system checks if a payment has already reached a final state (`success`, `failed`, or `expired`) before processing the notification.
3.  **Amount Matching**: Notified amount must exactly match the expected payment amount in the database.

## 6. System Response

The endpoint will respond with:

-   **200 OK**: If the notification was processed successfully (even if the transaction itself failed).
-   **500 Internal Server Error**: If there was a system error during processing.
-   **400/403**: If signature verification or data validation fails.

---
*Last Updated: February 2026*
