# Swagger/OpenAPI Documentation

## 📚 Dokumentasi API Telah Dibuat!

Saya telah membuat dokumentasi Swagger/OpenAPI 3.0 yang lengkap untuk semua endpoint OpenAPI.

### 📁 File yang Dibuat:

1. **`openapi.yaml`** - Spesifikasi OpenAPI 3.0 lengkap
2. **`public/openapi.yaml`** - Copy untuk akses publik
3. **`public/api-docs.html`** - Swagger UI interface

---

## 🌐 Cara Mengakses Dokumentasi:

### **Option 1: Swagger UI (Recommended)**

Buka browser dan akses:
```
http://localhost:8001/api-docs.html
```

Anda akan melihat dokumentasi interaktif dengan fitur:
- ✅ Daftar semua endpoint
- ✅ Request/Response examples
- ✅ Try it out (test langsung dari browser)
- ✅ Schema definitions
- ✅ Authentication helper

### **Option 2: Import ke Postman**

1. Buka Postman
2. Klik **Import**
3. Pilih file `openapi.yaml`
4. Semua endpoint akan otomatis ter-import dengan:
   - Request examples
   - Authentication setup
   - Environment variables

### **Option 3: Import ke Insomnia**

1. Buka Insomnia
2. Klik **Create** → **Import From** → **File**
3. Pilih file `openapi.yaml`
4. Collection siap digunakan

### **Option 4: Swagger Editor Online**

1. Buka https://editor.swagger.io
2. Copy isi file `openapi.yaml`
3. Paste di editor
4. Dokumentasi akan langsung ter-render

---

## 🔑 Authentication Setup

Semua endpoint memerlukan Basic Authentication:

**Username:** `openapi_user`  
**Password:** `openapi_secret_2026`

### Di Swagger UI:
1. Klik tombol **Authorize** 🔓
2. Masukkan username dan password
3. Klik **Authorize**
4. Sekarang bisa test semua endpoint

### Di Postman:
1. Pilih tab **Authorization**
2. Type: **Basic Auth**
3. Username: `openapi_user`
4. Password: `openapi_secret_2026`

### Di cURL:
```bash
curl -u openapi_user:openapi_secret_2026 \
  http://localhost:8001/openapi/availability?branch_id=1&booking_date=2026-02-10&duration=60
```

---

## 📋 Endpoint Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/openapi/register` | Register customer baru |
| GET | `/openapi/availability` | Cek jadwal tersedia |
| POST | `/openapi/reservations` | Buat reservasi |
| PUT | `/openapi/reservations/{id}/reschedule` | Reschedule booking |
| POST | `/openapi/payments` | Buat payment |
| GET | `/openapi/payments/{id}/status` | Cek status payment |

---

## 🎯 Features Dokumentasi Swagger:

✅ **Complete Schema Definitions**
- Request body schemas
- Response schemas
- Error schemas
- Validation schemas

✅ **Interactive Testing**
- Try it out langsung dari browser
- Auto-fill authentication
- Real-time response preview

✅ **Detailed Examples**
- Request examples untuk setiap endpoint
- Response examples (success & error)
- Query parameter examples

✅ **Type Safety**
- Data types untuk semua fields
- Required/optional indicators
- Validation rules (min, max, format)

✅ **Multi-Server Support**
- Local development server
- Production server (ready to configure)

---

## 🚀 Quick Start Testing

1. **Start server** (jika belum running):
   ```bash
   cd /Users/error/Documents/memofy/naqupos/naqupos-be
   php -S localhost:8001 -t public
   ```

2. **Buka Swagger UI**:
   ```
   http://localhost:8001/api-docs.html
   ```

3. **Authorize**:
   - Klik tombol **Authorize**
   - Username: `openapi_user`
   - Password: `openapi_secret_2026`

4. **Test Endpoint**:
   - Pilih endpoint (misal: POST /register)
   - Klik **Try it out**
   - Edit request body
   - Klik **Execute**
   - Lihat response

---

## 📝 Example Usage

### Test Register via Swagger UI:

1. Expand **POST /register**
2. Klik **Try it out**
3. Edit request body:
   ```json
   {
     "name": "Test User",
     "email": "test@example.com",
     "phone": "081234567890",
     "password": "password123"
   }
   ```
4. Klik **Execute**
5. Lihat response di bawah

### Test Availability:

1. Expand **GET /availability**
2. Klik **Try it out**
3. Isi parameters:
   - branch_id: `1`
   - booking_date: `2026-02-10`
   - duration: `60`
4. Klik **Execute**

---

## 🔧 Customization

Jika ingin mengubah dokumentasi:

1. Edit file `openapi.yaml`
2. Copy ke public folder:
   ```bash
   cp openapi.yaml public/openapi.yaml
   ```
3. Refresh browser (Ctrl+F5)

---

## 📤 Share Dokumentasi

Tim WhatsApp Bot bisa:

1. **Akses langsung** via URL (jika server public)
2. **Download file** `openapi.yaml` dan import ke tools mereka
3. **View online** via Swagger Editor

---

Dokumentasi Swagger sudah siap digunakan! 🎉
