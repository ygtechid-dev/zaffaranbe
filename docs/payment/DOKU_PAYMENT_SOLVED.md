# DOKU Payment Integration - SOLVED ✅

## 🎉 MASALAH BERHASIL DIPERBAIKI

### Error 1: ❌ "PAYMENT CHANNEL IS INACTIVE"
**Status:** ✅ SOLVED

**Penyebab:** Mengirim kode channel yang salah (`"doku"` bukan kode valid)

**Solusi:** Menggunakan kode channel DOKU yang benar:
- `VIRTUAL_ACCOUNT_BCA`
- `VIRTUAL_ACCOUNT_MANDIRI`
- `VIRTUAL_ACCOUNT_BNI`
- `VIRTUAL_ACCOUNT_BRI`
- `VIRTUAL_ACCOUNT_PERMATA`
- `QRIS`

---

### Error 2: ❌ "AMOUNT NOT MATCH"
**Status:** ✅ SOLVED

**Penyebab:** 
Total `line_items` tidak sama dengan `order.amount`

**Contoh Kasus:**
```json
{
    "items": [{"name": "Creambat", "price": 100000, "quantity": 1}],
    "amount": 200000
}
```

**Masalah:**
- `order.amount` = 200,000
- `line_items` total = 100,000 × 1 = 100,000
- **200,000 ≠ 100,000** → ERROR!

**Solusi:**
Sistem sekarang **otomatis menghitung** total dari `line_items` dan menggunakan nilai tersebut sebagai `order.amount`:

```php
// Calculate total from line_items
$calculatedTotal = 0;
foreach ($items as $item) {
    $calculatedTotal += ($item['price'] * $item['quantity']);
}

// Use calculated total for DOKU
$body['order']['amount'] = $calculatedTotal;
```

---

## 📊 DOKU Checkout API Requirements

### 1. **Amount Validation**
```
order.amount = SUM(line_items[].price × line_items[].quantity)
```

### 2. **Payment Method Types**
Harus menggunakan kode channel yang valid dan aktif di akun DOKU Anda.

### 3. **Customer Data**
- `customer.name` - Required
- `customer.email` - Required
- `customer.phone` - Required

---

## 🧪 Testing Guide

### Request Payload
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
    "amount": 100000,
    "payment_method": "doku",
    "payment_type": "full_payment"
}
```

**PENTING:** `amount` harus sama dengan total items (100,000 × 1 = 100,000)

### Expected Response
```json
{
    "success": true,
    "payment_id": 123,
    "payment_ref": "PAY-20260127-000123-1769518820",
    "method": "doku_checkout",
    "amount": 100000,
    "data": {
        "transaction_id": "PAY-20260127-000123-1769518820",
        "transaction_status": "pending",
        "payment_url": "https://sandbox.doku.com/checkout/...",
        "raw_response": { ... }
    }
}
```

---

## 🔧 What Was Fixed

### File: `app/Services/PaymentService.php`

#### 1. **Payment Channel Mapping**
```php
// Added proper DOKU channel codes
$dokuChannelMap = [
    'VIRTUAL_ACCOUNT_BCA' => 'VIRTUAL_ACCOUNT_BCA',
    'VIRTUAL_ACCOUNT_MANDIRI' => 'VIRTUAL_ACCOUNT_MANDIRI',
    // ... etc
];

// Default active channels for sandbox
$defaultActiveChannels = [
    'VIRTUAL_ACCOUNT_BCA',
    'VIRTUAL_ACCOUNT_MANDIRI',
    'VIRTUAL_ACCOUNT_BNI',
    'VIRTUAL_ACCOUNT_BRI',
    'VIRTUAL_ACCOUNT_PERMATA',
    'QRIS',
];
```

#### 2. **Amount Calculation**
```php
// Calculate total from line_items
$calculatedTotal = 0;
foreach ($model->booking_data['items'] as $item) {
    $itemPrice = (int) ($item['price'] ?? 0);
    $itemQuantity = (int) ($item['quantity'] ?? 1);
    $calculatedTotal += ($itemPrice * $itemQuantity);
}

// Use calculated total for order.amount
$body['order']['amount'] = $calculatedTotal;
```

#### 3. **Payment Record Update**
```php
// Update payment with correct amount
$payment->update([
    'amount' => $finalAmount,
    'payment_data' => json_encode($paymentData)
]);
```

---

## ✅ Verification Checklist

- [x] Payment channel codes are correct
- [x] Amount calculation matches line_items total
- [x] Payment record stores correct amount
- [x] Response returns actual charged amount
- [x] Logging added for debugging
- [x] DOKU Checkout API is used (not Direct API)

---

## 📝 Important Notes

### 1. **Amount Mismatch Warning**
Jika `amount` yang dikirim berbeda dengan total `line_items`, sistem akan:
- ✅ Menggunakan total dari `line_items`
- ⚠️ Log warning untuk debugging
- ✅ Update payment record dengan amount yang benar

### 2. **DOKU Checkout vs Direct API**
Kode ini menggunakan **DOKU Checkout API** (`/checkout/v1/payment`), bukan Direct API.

**Checkout API Features:**
- ✅ Hosted payment page
- ✅ Multiple payment channels in one URL
- ✅ Customer chooses payment method
- ✅ Easier integration

### 3. **Sandbox Testing**
Pastikan menggunakan:
- `DOKU_ENV=sandbox` di `.env`
- Sandbox credentials dari DOKU dashboard
- Test payment dengan nominal kecil dulu

---

## 🚀 Next Steps

1. **Test dengan payload yang benar:**
   ```json
   {
       "items": [{"name": "Test Item", "price": 100000, "quantity": 1}],
       "amount": 100000,
       "payment_method": "doku",
       ...
   }
   ```

2. **Verifikasi response** mendapat `payment_url`

3. **Buka payment_url** di browser untuk test checkout flow

4. **Test callback** setelah payment berhasil

---

## 📞 Support

Jika masih ada masalah:
1. Cek log: `tail -f storage/logs/lumen-$(date +%Y-%m-%d).log`
2. Pastikan amount = total line_items
3. Verifikasi DOKU credentials
4. Cek DOKU dashboard untuk channel yang aktif
