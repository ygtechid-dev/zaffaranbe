# OpenAPI Endpoints Documentation

Base URL: `http://localhost:8001/openapi`

## Authentication
All endpoints require Basic Authentication.

**Headers:**
```
Authorization: Basic base64(username:password)
```

**Credentials (from .env):**
- Username: `openapi_user`
- Password: `openapi_secret_2026`

---

## 1. Register Customer
**Endpoint:** `POST /openapi/register`

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "081234567890",
  "password": "password123"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user_id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "081234567890"
  }
}
```

---

## 2. Get Availability Schedule
**Endpoint:** `GET /openapi/availability`

**Query Parameters:**
- `branch_id` (required): Branch ID
- `booking_date` (required): Date in format Y-m-d (e.g., 2026-02-10)
- `duration` (required): Service duration in minutes

**Example:**
```
GET /openapi/availability?branch_id=1&booking_date=2026-02-10&duration=60
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Therapist Name",
      "photo": "url",
      "gender": "female",
      "specialization": "Massage",
      "shifts": [
        {
          "start": "09:00",
          "end": "21:00"
        }
      ],
      "slots": [
        {
          "time": "09:00",
          "available": true,
          "disabled": false,
          "reason": null
        }
      ]
    }
  ]
}
```

---

## 3. Create Reservation
**Endpoint:** `POST /openapi/reservations`

**Request Body:**
```json
{
  "user_id": 1,
  "branch_id": 1,
  "service_id": 1,
  "therapist_id": 1,
  "booking_date": "2026-02-10",
  "start_time": "10:00",
  "guest_count": 1,
  "room_id": 1,
  "notes": "Special request"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Reservation created successfully",
  "data": {
    "booking": {
      "id": 1,
      "booking_date": "2026-02-10",
      "start_time": "10:00:00",
      "end_time": "11:00:00",
      "status": "pending"
    },
    "payment_log_id": 1
  }
}
```

---

## 4. Reschedule Booking
**Endpoint:** `PUT /openapi/reservations/{id}/reschedule`

**Request Body:**
```json
{
  "booking_date": "2026-02-11",
  "start_time": "14:00",
  "therapist_id": 2
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Booking rescheduled successfully",
  "data": {
    "booking_id": 1,
    "booking_date": "2026-02-11",
    "start_time": "14:00:00",
    "end_time": "15:00:00",
    "status": "confirmed"
  }
}
```

---

## 5. Create Payment
**Endpoint:** `POST /openapi/payments`

**Request Body:**
```json
{
  "booking_id": 1,
  "payment_method": "cash",
  "amount": 150000
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Payment created successfully",
  "data": {
    "payment_id": 1,
    "booking_id": 1,
    "amount": 150000,
    "status": "pending"
  }
}
```

---

## 6. Check Payment Status
**Endpoint:** `GET /openapi/payments/{id}/status`

**Response (200):**
```json
{
  "success": true,
  "data": {
    "payment_id": 1,
    "booking_id": 1,
    "amount": 150000,
    "status": "pending",
    "payment_method": "cash",
    "created_at": "2026-02-10T10:00:00.000000Z",
    "updated_at": "2026-02-10T10:00:00.000000Z"
  }
}
```

---

## Error Responses

**401 Unauthorized:**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

**422 Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

**500 Server Error:**
```json
{
  "success": false,
  "message": "Error message",
  "error": "Detailed error"
}
```

---

## Testing with cURL

**Example - Register:**
```bash
curl -X POST http://localhost:8001/openapi/register \
  -u openapi_user:openapi_secret_2026 \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "phone": "081234567890",
    "password": "password123"
  }'
```

**Example - Get Availability:**
```bash
curl -X GET "http://localhost:8001/openapi/availability?branch_id=1&booking_date=2026-02-10&duration=60" \
  -u openapi_user:openapi_secret_2026
```

**Example - Create Reservation:**
```bash
curl -X POST http://localhost:8001/openapi/reservations \
  -u openapi_user:openapi_secret_2026 \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "branch_id": 1,
    "service_id": 1,
    "therapist_id": 1,
    "booking_date": "2026-02-10",
    "start_time": "10:00",
    "guest_count": 1
  }'
```
