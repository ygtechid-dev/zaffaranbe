# DOKU Payment Gateway Setup

This project now supports DOKU Payment Gateway (Jokul - Direct API).

## 1. Environment Configuration

To enable DOKU, update your `.env` file with the following keys. If you haven't already, add them based on `.env.example`.

```env
PAYMENT_GATEWAY=doku

# DOKU Payment Gateway
DOKU_ENV=sandbox
DOKU_CLIENT_ID=your_client_id_here
DOKU_SECRET_KEY=your_secret_key_here
```

-   **PAYMENT_GATEWAY**: Set to `doku` to use DOKU. Remove or set to `midtrans` to revert to Midtrans.
-   **DOKU_ENV**: Set to `sandbox` for testing or `production` for live payments.
-   **DOKU_CLIENT_ID**: Your Client ID from DOKU Dashboard.
-   **DOKU_SECRET_KEY**: Your Secret Key from DOKU Dashboard.

## 2. Notification URL (Callback)

Configure your Notification URL (Webhook) in the DOKU Dashboard to point to:

*   **Terpadu:** `https://your-api-domain.com/api/v1/payments/callback`
*   **Khusus DOKU:** `https://your-api-domain.com/api/v1/payments/doku/callback`

Sistem akan otomatis mendeteksi format payload DOKU pada kedua endpoint tersebut. Menggunakan URL khusus dapat memudahkan filter log di sisi server jika diperlukan.

## 3. Supported Methods

The integration supports:
-   **QRIS**: Uses DOKU QRIS Generator (AirPay Shopee acquirer default).
-   **Virtual Account**: Uses DOKU VA Generator (BCA default).

## 4. Frontend Integration

No major frontend changes are required if it already handles `qr_code_url` and `va_number` in the payment response.
-   For **QRIS**, the API returns `qr_code_url` (raw QR string content).
-   For **VA**, the API returns `va_number`.

## 5. Troubleshooting

If you encounter "Class not found" errors for `GuzzleHttp\Client`, ensure you have run:
```bash
composer install
```
(Guzzle is usually included with Laravel/Lumen or JWT Auth, but verify it exists in `vendor/guzzlehttp`).
