# 🌱 Seeders Documentation

## Overview

Dokumentasi ini menjelaskan semua seeder yang tersedia dalam aplikasi Naqupos Spa backend.

---

## 📁 Seeder Files

| File | Description |
|------|-------------|
| `DatabaseSeeder.php` | Main seeder - seeds all core tables |
| `CitySeeder.php` | Seeds Indonesian cities |
| `NotificationSeeder.php` | Seeds sample customer notifications |
| `CustomerPaymentMethodSeeder.php` | Seeds sample saved payment methods |

---

## 🚀 Running Seeders

### Run All Seeders
```bash
php artisan db:seed
```

### Run Specific Seeder
```bash
php artisan db:seed --class=NotificationSeeder
php artisan db:seed --class=CustomerPaymentMethodSeeder
```

### Fresh Migration + Seed
```bash
php artisan migrate:fresh --seed
```

---

## 📊 Data Seeded

### DatabaseSeeder (Main)

**Users Created:**
| Name | Email | Password | Role |
|------|-------|----------|------|
| Admin Naqupos | admin@naqupos.com | admin123 | admin |
| Owner Naqupos | owner@naqupos.com | owner123 | owner |
| Kasir Solo | kasir.solo@naqupos.com | kasir123 | cashier |
| Rina Susanti | rina@customer.com | customer123 | customer |

**Branches Created:**
- Naqupos Spa Solo (ZSP-SOLO)
- Naqupos Spa Jogja (ZSP-JOGJA)
- Naqupos Spa Semarang (ZSP-SMG)

**Services Created:** 10 services (Massage, Body Treatment, Face Treatment, Hair Treatment, Packages)

**Therapists Created:** 7 therapists across 3 branches

**Rooms Created:** 10 rooms (Standard, VIP, VVIP)

**Bookings Created:** 30 dummy bookings

**Banners Created:** 3 promotional banners

---

### NotificationSeeder

Seeds 10 sample notifications per customer user with various types:

| Type | Description | Example |
|------|-------------|---------|
| `booking_confirmation` | Booking confirmed | "Booking Anda telah dikonfirmasi" |
| `promo` | Promotional offers | "Flash Sale Weekend!" |
| `payment_success` | Payment successful | "Pembayaran berhasil" |
| `booking_reminder` | Upcoming booking reminder | "Jangan lupa! Booking besok" |
| `booking_completed` | Treatment completed | "Treatment selesai, berikan review" |
| `booking_cancelled` | Booking cancelled | "Booking dibatalkan, refund diproses" |
| `news` | News/announcements | "Cabang baru di Semarang!" |
| `loyalty` | Loyalty points update | "Poin loyalitas bertambah!" |
| `system` | System notifications | "Update aplikasi tersedia" |

**Sample Data for Each Notification:**
```json
{
  "user_id": 4,
  "type": "booking_confirmation",
  "title": "Booking Dikonfirmasi",
  "message": "Booking Anda untuk Javanese Traditional Massage...",
  "data": {
    "booking_id": 1,
    "booking_ref": "BK-20260107-0001",
    "service": "Javanese Traditional Massage",
    "date": "2026-01-09",
    "time": "10:00"
  },
  "is_read": false
}
```

---

### CustomerPaymentMethodSeeder

Seeds 6 sample payment methods per customer user:

| Provider | Type | Account Number | Is Default | Is Verified |
|----------|------|----------------|------------|-------------|
| BCA | bank_transfer | 1234567890 | ✅ Yes | ✅ Yes |
| Mandiri | bank_transfer | 0987654321 | No | ✅ Yes |
| GoPay | e_wallet | 081234567893 | No | ✅ Yes |
| OVO | e_wallet | 081234567893 | No | ✅ Yes |
| DANA | e_wallet | 081234567893 | No | ❌ No |
| BNI | bank_transfer | 5678901234 | No | ❌ No |

**Sample Data:**
```json
{
  "user_id": 4,
  "type": "bank_transfer",
  "provider": "BCA",
  "account_number": "1234567890",
  "account_name": "RINA SUSANTI",
  "is_default": true,
  "is_verified": true
}
```

---

## 🔄 Tables Truncated on Seed

When running `DatabaseSeeder`, the following tables are truncated first:

**Core Tables:**
- users
- branches
- services
- therapists
- therapist_schedules
- rooms
- bookings
- transactions
- banners

**Customer Feature Tables (NEW):**
- notifications
- customer_payment_methods
- password_resets

---

## ⚠️ Notes

1. **Auto-increment Reset**: Tables are truncated with `FOREIGN_KEY_CHECKS=0`, so auto-increment IDs are reset to 1.

2. **Customer User Required**: `NotificationSeeder` and `CustomerPaymentMethodSeeder` require at least one user with `role='customer'` to exist.

3. **Production Warning**: Never run seeders on production database unless you intentionally want to reset all data.

4. **Password Resets**: The `password_resets` table is truncated but not seeded (it's for transient OTP tokens).

---

## 📅 Last Updated

- **Date**: 2026-01-07
- **Version**: 1.0.0
