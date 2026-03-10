# DOKU Payment Integration - Troubleshooting Guide

## 🔴 Error yang Terjadi

**Error Message:**
```json
{
    "error": "Payment initiation failed",
    "message": "Client error: `POST https://api-sandbox.doku.com/checkout/v1/payment` resulted in a `400 Bad Request` response:\n{\"message\":[\"PAYMENT CHANNEL IS INACTIVE\"]}\n"
}
```

## 🔍 Penyebab Masalah

### 1. **Kode Payment Channel yang Salah**
Kode sebelumnya mengirim `"payment_method_types": ["doku"]` ke DOKU API.

**Masalah:** `"doku"` BUKAN kode channel yang valid di DOKU API!

### 2. **Kode Channel DOKU yang Benar**
DOKU API menggunakan kode channel spesifik seperti:
- `VIRTUAL_ACCOUNT_BCA`
- `VIRTUAL_ACCOUNT_MANDIRI`
- `VIRTUAL_ACCOUNT_BNI`
- `VIRTUAL_ACCOUNT_BRI`
- `VIRTUAL_ACCOUNT_PERMATA`
- `QRIS`
- `PEER_TO_PEER_AKULAKU`
- `PEER_TO_PEER_KREDIVO`
- dll.

## ✅ Solusi yang Diterapkan

### 1. **Mapping Payment Channel**
Menambahkan mapping antara kode internal dengan kode DOKU API:

```php
$dokuChannelMap = [
    'VIRTUAL_ACCOUNT_BCA' => 'VIRTUAL_ACCOUNT_BCA',
    'VIRTUAL_ACCOUNT_MANDIRI' => 'VIRTUAL_ACCOUNT_MANDIRI',
    'QRIS' => 'QRIS',
    // ... dll
];
```

### 2. **Default Active Channels**
Menggunakan default channels yang umumnya aktif di sandbox:

```php
$defaultActiveChannels = [
    'VIRTUAL_ACCOUNT_BCA',
    'VIRTUAL_ACCOUNT_MANDIRI',
    'VIRTUAL_ACCOUNT_BNI',
    'VIRTUAL_ACCOUNT_BRI',
    'VIRTUAL_ACCOUNT_PERMATA',
    'QRIS',
];
```

### 3. **Logika Pemilihan Channel**
- Jika `payment_method` adalah kode spesifik (misal: `VIRTUAL_ACCOUNT_BCA`), gunakan channel tersebut
- Jika `payment_method` adalah `"doku"` (generic), gunakan default active channels
- Tambahkan logging lengkap untuk debugging

## 🧪 Cara Testing

### Method 1: Via API (Butuh Authentication)

```bash
# 1. Login dulu untuk mendapatkan token
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "your_email@example.com",
    "password": "your_password"
  }'

# 2. Copy token dari response

# 3. Test payment initiation
curl -X POST http://localhost:8000/api/v1/customer/payments/initiate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "items": [{"name": "Creambat", "price": 100000, "quantity": 1}],
    "customer_name": "Insan",
    "customer_phone": "0877171717",
    "customer_email": "test@example.com",
    "amount": 200000,
    "payment_method": "doku",
    "payment_type": "full_payment"
  }'
```

### Method 2: Via Postman/Insomnia

1. **Login** ke aplikasi untuk mendapatkan JWT token
2. **Set Authorization Header:** `Bearer YOUR_TOKEN`
3. **POST** ke `http://localhost:8000/api/v1/customer/payments/initiate`
4. **Body:**
```json
{
    "items": [
        {
            "name": "Creambat",
            "price": 100000,
            "quantity": 1
        }
    ],
    "customer_name": "Insan",
    "customer_phone": "0877171717",
    "customer_email": "test@example.com",
    "amount": 200000,
    "payment_method": "doku",
    "payment_type": "full_payment"
}
```

## 📊 Expected Response (Success)

```json
{
    "success": true,
    "payment_id": 123,
    "payment_ref": "PAY-20260127-000123-1769518820",
    "method": "doku_checkout",
    "amount": 200000,
    "data": {
        "transaction_id": "PAY-20260127-000123-1769518820",
        "transaction_status": "pending",
        "payment_url": "https://sandbox.doku.com/checkout/...",
        "raw_response": { ... }
    }
}
```

## 🔧 Debugging

### Check Logs
```bash
tail -f storage/logs/lumen-$(date +%Y-%m-%d).log
```

### Look for:
1. `createDokuPayment: Allowed Payment Codes from DB` - Kode dari database
2. `createDokuPayment: Using default active channels` - Channel yang dipilih
3. `createDokuPayment: Final payment_method_types` - Channel yang dikirim ke DOKU
4. `createDokuPayment: Request Body` - Full request body

## 📝 Notes

### Jika Masih Error "PAYMENT CHANNEL IS INACTIVE"

Kemungkinan penyebab:
1. **Channel belum diaktifkan di DOKU Dashboard** - Cek di https://sandbox.doku.com
2. **Akun sandbox belum fully activated** - Hubungi support DOKU
3. **Kode channel salah** - Pastikan menggunakan kode yang benar sesuai dokumentasi DOKU

### Channels yang Mungkin Tidak Aktif di Sandbox
Beberapa channel mungkin tidak tersedia di sandbox:
- `EMONEY_SHOPEEPAY`
- `EMONEY_OVO`
- `EMONEY_DANA`
- `CREDIT_CARD` (butuh aktivasi khusus)

### Rekomendasi
Untuk testing awal, gunakan:
- `VIRTUAL_ACCOUNT_BCA` - Paling stabil
- `QRIS` - Biasanya aktif di sandbox
- `VIRTUAL_ACCOUNT_MANDIRI` - Reliable

## 🎯 Next Steps

1. **Test dengan channel spesifik:**
   ```json
   {
       "payment_method": "VIRTUAL_ACCOUNT_BCA",
       ...
   }
   ```

2. **Verifikasi di DOKU Dashboard** bahwa channel yang Anda gunakan benar-benar aktif

3. **Cek dokumentasi DOKU** untuk list lengkap payment channels yang tersedia

## 📞 Support

Jika masih mengalami masalah:
1. Cek log file di `storage/logs/`
2. Verifikasi DOKU credentials di `.env`
3. Pastikan DOKU_ENV=sandbox
4. Hubungi DOKU support untuk aktivasi channel
