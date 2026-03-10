# Naqupos Backend API

Backend REST API untuk sistem manajemen spa dan salon Naqupos, dibangun menggunakan Laravel Lumen. API ini mendukung aplikasi customer mobile (`naqupos-salon-customer`) dan dashboard admin (`naqupos-admin`).

## 📋 Fitur Utama

### Untuk Customer (Mobile App)
- ✅ Registrasi & Login dengan OTP WhatsApp
- ✅ Booking layanan spa dengan custom calendar engine
- ✅ Pemilihan terapis berdasarkan ketersediaan
- ✅ Sistem payment (DP/Full) dengan Payment Gateway
- ✅ Riwayat transaksi & booking
- ✅ Feedback & rating
- ✅ Notifikasi WhatsApp otomatis (Cekat.ai)

### Untuk Kasir (POS System)
- ✅ Clock-in / Clock-out shift
- ✅ Transaksi walk-in & pelunasan booking
- ✅ Laporan harian (kas masuk/keluar, DP hangus, dll)
- ✅ Jadwal terapis & ruangan real-time
- ✅ Check-in booking customer

### Untuk Admin/Owner
- ✅ Dashboard analytics & charts
- ✅ Manajemen reservasi (reschedule, refund)
- ✅ Manajemen terapis & jadwal shift
- ✅ Manajemen layanan & ruangan
- ✅ Manajemen cabang (multi-branch)
- ✅ Customer CRM
- ✅ Laporan keuangan (laba rugi, revenue, dll)
- ✅ Manajemen banner promo

## 🛠 Tech Stack

- **Framework**: Laravel Lumen 10
- **Database**: MySQL 8.0+
- **Authentication**: JWT (php-open-source-saver/jwt-auth)
- **PHP**: 8.1+

## 📦 Installation

### 1. Clone & Install Dependencies

```bash
cd /path/to/naqupos
# Project sudah ada di: naqupos-be

cd naqupos-be
composer install
```

### 2. Environment Configuration

File `.env` sudah dikonfigurasi. Update konfigurasi database:

```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=naqupos_db
DB_USERNAME=root
DB_PASSWORD=
```

JWT Secret sudah di-generate otomatis. Untuk integrasi production, tambahkan:

```bash
# WhatsApp Notification (Cekat.ai)
CEKAT_API_KEY=your_cekat_api_key
CEKAT_API_URL=https://api.cekat.ai/v1

# Payment Gateway (Midtrans)
MIDTRANS_SERVER_KEY=your_server_key
MIDTRANS_CLIENT_KEY=your_client_key
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_MERCHANT_ID=your_merchant_id
```

### 3. Database Setup

```bash
# Create database
mysql -u root -p
CREATE DATABASE naqupos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# Run migrations
php artisan migrate

# Seed sample data
php artisan db:seed
```

### 4. Run Development Server

```bash
php -S localhost:8000 -t public
```

API akan berjalan di: `http://localhost:8000`

## 🔐 Default Credentials

Setelah seeding, gunakan credentials berikut:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@naqupos.com | admin123 |
| Owner | owner@naqupos.com | owner123 |
| Cashier | kasir.solo@naqupos.com | kasir123 |
| Customer | rina@customer.com | customer123 |

## 📚 API Documentation

Detailed API documentation can be found in the [docs/api/](docs/api/) directory.

### Base URL
```
http://localhost:8000/api/v1
```

### Authentication
Semua endpoint yang membutuhkan autentikasi harus menyertakan JWT token di header:
```
Authorization: Bearer {your_jwt_token}
```

### 🔓 Public Endpoints

#### Auth
- `POST /auth/register` - Register customer baru
- `POST /auth/login` - Login (support email/phone)
- `POST /auth/verify-otp` - Verifikasi OTP WhatsApp
- `POST /auth/resend-otp` - Kirim ulang OTP

#### Services
- `GET /services` - List semua layanan
- `GET /services/{id}` - Detail layanan

#### Banners
- `GET /banners` - List banner aktif (promo/news)

#### Branches
- `GET /branches` - List cabang
- `GET /branches/{id}` - Detail cabang

---

### 🔒 Customer Endpoints
Prefix: `/customer` (Requires: auth middleware)

#### Bookings
- `GET /customer/bookings` - List booking user
- `POST /customer/bookings` - Buat booking baru
- `GET /customer/bookings/{id}` - Detail booking
- `POST /customer/bookings/{id}/cancel` - Cancel booking
- `POST /customer/bookings/check-availability` - Cek ketersediaan slot

**Check Availability Payload:**
```json
{
  "branch_id": 1,
  "booking_date": "2026-01-15",
  "start_time": "10:00",
  "duration": 90
}
```

**Create Booking Payload:**
```json
{
  "branch_id": 1,
  "service_id": 3,
  "therapist_id": 1,
  "room_id": 5,
  "booking_date": "2026-01-15",
  "start_time": "10:00",
  "notes": "Prefer aromatherapy lavender"
}
```

#### Payments
- `POST /customer/payments/initiate` - Inisiasi pembayaran
- `GET /customer/payments/{id}` - Status pembayaran
- `POST /customer/payments/callback` - Webhook payment gateway

#### Feedback
- `GET /customer/feedbacks` - List feedback user
- `POST /customer/feedbacks` - Submit feedback

#### Profile
- `GET /customer/profile` - Profil user
- `PUT /customer/profile` - Update profil
- `PUT /customer/profile/password` - Ganti password

---

### 💰 Cashier Endpoints
Prefix: `/cashier` (Requires: auth + role:cashier,admin,owner)

#### Shift Management
- `POST /cashier/shift/clock-in` - Clock-in kasir
- `POST /cashier/shift/clock-out` - Clock-out kasir
- `GET /cashier/shift/current` - Shift aktif

#### Transactions
- `GET /cashier/transactions` - List transaksi
- `POST /cashier/transactions` - Buat transaksi baru
- `GET /cashier/transactions/{id}` - Detail transaksi
- `POST /cashier/transactions/{id}/print` - Print nota

#### Schedule & Bookings
- `GET /cashier/schedule/today` - Jadwal hari ini
- `POST /cashier/bookings/{id}/check-in` - Check-in customer
- `POST /cashier/bookings/{id}/complete` - Complete treatment

#### Reports
- `GET /cashier/reports/daily` - Laporan harian

---

### 👨‍💼 Admin Endpoints
Prefix: `/admin` (Requires: auth + role:admin,owner)

#### Dashboard
- `GET /admin/dashboard` - Dashboard overview
- `GET /admin/dashboard/stats` - Statistics
- `GET /admin/dashboard/charts` - Chart data

#### Booking Management
- `GET /admin/bookings` - List semua booking
- `GET /admin/bookings/{id}` - Detail booking
- `PUT /admin/bookings/{id}/reschedule` - Reschedule booking
- `PUT /admin/bookings/{id}/status` - Update status
- `POST /admin/bookings/{id}/refund` - Proses refund

#### Calendar
- `GET /admin/calendar` - Master calendar
- `GET /admin/calendar/therapists` - Jadwal terapis

#### Customer CRM
- `GET /admin/customers` - List customer
- `GET /admin/customers/{id}` - Detail customer
- `GET /admin/customers/{id}/history` - Riwayat customer
- `PUT /admin/customers/{id}` - Update customer
- `DELETE /admin/customers/{id}` - Hapus customer

#### Therapist Management
- `GET /admin/therapists` - List terapis
- `POST /admin/therapists` - Tambah terapis
- `PUT /admin/therapists/{id}` - Update terapis
- `DELETE /admin/therapists/{id}` - Hapus terapis
- `GET /admin/therapists/{id}/schedules` - Jadwal terapis
- `POST /admin/therapists/{id}/schedules` - Tambah jadwal
- `PUT /admin/therapists/schedules/{scheduleId}` - Update jadwal

#### Service, Room, Branch Management
- `GET|POST|PUT|DELETE /admin/services`
- `GET|POST|PUT|DELETE /admin/rooms`
- `GET|POST|PUT|DELETE /admin/branches`
- `GET|POST|PUT|DELETE /admin/banners`

#### Reports
- `GET /admin/reports/revenue` - Laporan revenue
- `GET /admin/reports/profit-loss` - Laporan laba rugi
- `GET /admin/reports/bookings` - Laporan booking
- `GET /admin/reports/therapist-performance` - Performa terapis
- `GET /admin/reports/popular-services` - Layanan populer

#### Feedback Management
- `GET /admin/feedbacks` - List feedback
- `POST /admin/feedbacks/{id}/reply` - Reply feedback

#### User Management
- `GET|POST|PUT|DELETE /admin/users` - Manage admin/kasir

---

### 👑 Owner Endpoints
Prefix: `/owner` (Requires: auth + role:owner)

#### Advanced Reports
- `GET /owner/reports/financial-summary` - Ringkasan keuangan
- `GET /owner/reports/branch-comparison` - Perbandingan cabang

#### Audit
- `GET /owner/audit-logs` - Audit logs sistem

---

## 🗂 Database Structure

### Core Tables
- `users` - User accounts (customer, cashier, admin, owner)
- `branches` - Cabang spa
- `services` - Layanan/treatment
- `therapists` - Terapis/staff
- `therapist_schedules` - Jadwal kerja terapis
- `rooms` - Ruangan (Standard/VIP/VVIP)
- `bookings` - Reservasi customer
- `payments` - Pembayaran (DP/Full)
- `transactions` - Transaksi POS
- `cashier_shifts` - Shift kasir
- `banners` - Banner promo/news
- `feedbacks` - Kritik & saran

## 🎯 Custom Calendar Engine

Backend ini menggunakan **custom calendar logic** untuk:

✅ **Validasi slot ketersediaan** berdasarkan:
- Jadwal terapis per hari (day_of_week)
- Durasi layanan
- Booking yang sudah ada
- Ketersediaan ruangan

✅ **Concurrency handling** dengan database locking untuk mencegah double booking

✅ **Time slot calculation** otomatis berdasarkan start_time + duration

## 🔔 Notifikasi WhatsApp (Cekat.ai)

Sistem akan mengirim notifikasi otomatis untuk:
- OTP verifikasi
- Konfirmasi booking
- Reminder H-1
- Nota pembayaran

## 💳 Payment Gateway Integration

See [docs/payment/](docs/payment/) for detailed integration guides.

Support payment method:
- Down Payment (DP minimum Rp 50.000)
- Full Payment
- Payment via: QRIS, Virtual Account, Bank Transfer

## 🚀 Deployment Checklist

1. ✅ Set `APP_ENV=production` di `.env`
2. ✅ Set `APP_DEBUG=false`
3. ✅ Configure database credentials production
4. ✅ Setup Cekat.ai API key
5. ✅ Setup Midtrans credentials
6. ✅ Run migrations: `php artisan migrate --force`
7. ✅ Seed initial data (atau manual via admin dashboard)
8. ✅ Configure web server (Nginx/Apache)
9. ✅ Setup SSL certificate
10. ✅ Setup cron jobs untuk notifications & reminders

## 📝 License

Proprietary - © 2026 Naqupos Beauty & Spa

## 📚 Documentation

The full documentation is now in the `docs` directory.

- **[Documentation Home](docs/README.md)** - Index for all documentation
- **[Installation Guide](docs/README.md)** - Installation steps
- **[API Documentation](docs/api/)** - Detailed API specs
- **[Payment Integration](docs/payment/)** - Payment gateway setup
- **[Troubleshooting](docs/guides/troubleshooting_500.md)** - Common issue resolution
- **[Postman Collection](Naqupos_API.postman_collection.json)** - Ready-to-use API testing

## 🤝 Support

Untuk bantuan teknis, hubungi tim development.

---

**Developed with ❤️ for Naqupos Beauty & Spa**
