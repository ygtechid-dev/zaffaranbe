# 🔧 Troubleshooting: Error 500 pada Production Server

## 🔍 **Diagnosis**

Server production (`https://naquposbe.memofy-dev.my.id`) mengembalikan **HTTP 500 Internal Server Error** pada semua endpoint API.

### Test Results:
```bash
curl -I https://naquposbe.memofy-dev.my.id/api/v1/branches
# Response: HTTP/2 500
```

### Local Server (Working):
```bash
curl http://localhost:8000/api/v1/auth/login
# Response: HTTP/1.1 200 OK ✅
```

---

## 🛠️ **Kemungkinan Penyebab & Solusi**

### 1. **Database Connection Error** (Paling Umum)

**Penyebab:**
- File `.env` di production belum dikonfigurasi dengan benar
- Database credentials salah
- Database belum dibuat

**Solusi:**

#### A. Cek/Edit file `.env` di server production:
```bash
# Login ke server via SSH atau File Manager
# Edit file: /path/to/naqupos-be/.env

DB_CONNECTION=mysql
DB_HOST=127.0.0.1  # atau hostname database Anda
DB_PORT=3306
DB_DATABASE=naqupos_db  # Nama database yang sudah dibuat
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

#### B. Buat database jika belum ada:
```sql
CREATE DATABASE naqupos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### C. Jalankan migrasi:
```bash
cd /path/to/naqupos-be
php artisan migrate --force
php artisan db:seed --force
```

---

### 2. **Missing Dependencies**

**Penyebab:**
- Folder `vendor/` tidak ada atau tidak lengkap

**Solusi:**
```bash
cd /path/to/naqupos-be
composer install --no-dev --optimize-autoloader
```

---

### 3. **File Permissions**

**Penyebab:**
- Folder `storage/` dan `bootstrap/cache/` tidak writable

**Solusi:**
```bash
cd /path/to/naqupos-be
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache  # atau user web server Anda
```

---

### 4. **Missing .env File**

**Penyebab:**
- File `.env` tidak ada di server production

**Solusi:**
```bash
# Copy dari .env.example
cp .env.example .env

# Generate APP_KEY
php artisan key:generate

# Set JWT_SECRET (gunakan yang sama dari local atau generate baru)
# Bisa copy dari .env local Anda:
JWT_SECRET=NsRDBhYwvVs9adnoM3iwrx43AzpAJP8Wl3TJex8mHvHQZAcBVZ0BfYTUDFO9ZCcE
```

---

### 5. **PHP Version Mismatch**

**Penyebab:**
- Server production menggunakan PHP versi berbeda

**Cek versi PHP:**
```bash
php -v
# Pastikan >= PHP 8.1
```

---

## 📋 **Checklist Deployment**

Pastikan langkah-langkah berikut sudah dilakukan di server production:

- [ ] File `.env` sudah ada dan dikonfigurasi dengan benar
- [ ] Database sudah dibuat
- [ ] `composer install` sudah dijalankan
- [ ] `php artisan migrate` sudah dijalankan
- [ ] `php artisan db:seed` sudah dijalankan (opsional, untuk data dummy)
- [ ] Permissions `storage/` dan `bootstrap/cache/` sudah benar (775)
- [ ] Web server (Apache/Nginx) sudah dikonfigurasi dengan benar
- [ ] Document root mengarah ke folder `public/`

---

## 🔍 **Cara Melihat Error Detail**

### Method 1: Via Laravel Log
```bash
# SSH ke server, lalu:
tail -f storage/logs/lumen.log
```

### Method 2: Enable Debug Mode (HANYA UNTUK TESTING)
```bash
# Edit .env
APP_DEBUG=true

# PENTING: Set kembali ke false setelah selesai debugging!
```

### Method 3: Via Browser
Akses langsung: `https://naquposbe.memofy-dev.my.id/api/v1/branches`

Dengan `APP_DEBUG=true`, Anda akan melihat stack trace error lengkap.

---

## 🚀 **Quick Fix (Jika Akses SSH Tersedia)**

```bash
# 1. Masuk ke direktori project
cd /path/to/naqupos-be

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Set permissions
chmod -R 775 storage bootstrap/cache

# 4. Copy .env jika belum ada
cp .env.example .env

# 5. Edit .env dengan database credentials yang benar
nano .env  # atau vi .env

# 6. Generate key
php artisan key:generate

# 7. Jalankan migrasi
php artisan migrate --force

# 8. Seed database (opsional)
php artisan db:seed --force

# 9. Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# 10. Test
curl http://localhost/api/v1/branches
```

---

## 📞 **Jika Masih Error**

Kirimkan informasi berikut:

1. **Output dari:**
   ```bash
   tail -50 storage/logs/lumen.log
   ```

2. **Konfigurasi database** (tanpa password):
   ```bash
   grep DB_ .env
   ```

3. **PHP Version:**
   ```bash
   php -v
   ```

4. **Composer packages:**
   ```bash
   composer show
   ```

---

## ✅ **Verifikasi Setelah Fix**

Test semua endpoint penting:

```bash
# 1. Health check
curl https://naquposbe.memofy-dev.my.id/

# 2. Public endpoint
curl https://naquposbe.memofy-dev.my.id/api/v1/branches

# 3. Login
curl -X POST https://naquposbe.memofy-dev.my.id/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"rina@customer.com","password":"customer123"}'
```

Semua harus return **HTTP 200** dengan data JSON yang valid.

---

**Last Updated:** 2026-01-07 07:15 WIB
