# WhatsApp Business API - Zafaran Spa

**Version:** 1.0.0  
**Description:** API untuk mengirim WhatsApp template messages ke pelanggan Zafaran Spa Cianjur  
**Contact:** [Zafaran Group Indonesia](https://zafarangroupindonesia.com)

## Servers
- **Production Server:** `https://apinaqu.zafarangroupindonesia.com`
- **Development Server:** `http://localhost:3006`

## Endpoints

### 1. Kirim template promo diskon reservasi
**Endpoint:** `POST /api/messages/discount-reservation`  
**Description:** Mengirim pesan WhatsApp dengan template discreservasi (Self-care day promo)

**Request Body (JSON):**
| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `phone` | string | Yes | Nomor WhatsApp tujuan (format 62xxx tanpa +) | `6285121158088` |
| `nama_pelanggan` | string | Yes | Nama pelanggan | `Yogi` |
| `persentase` | string | Yes | Persentase diskon | `50` |
| `nama_layanan` | string | Yes | Nama layanan spa | `Massage Aromaterapi` |

---

### 2. Kirim konfirmasi reservasi berhasil
**Endpoint:** `POST /api/messages/reservation-success`  
**Description:** Mengirim pesan WhatsApp dengan template berhasilreservasi

**Request Body (JSON):**
| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `phone` | string | Yes | Nomor WhatsApp tujuan (format 62xxx tanpa +) | `6285121158088` |
| `nama_pelanggan` | string | Yes | Nama pelanggan | `Yogi` |
| `nama_spa` | string | Yes | Nama Spa | `Zafaran Spa Cianjur` |
| `tanggal` | string | Yes | Tanggal reservasi | `15 Februari 2026` |
| `waktu` | string | Yes | Waktu reservasi | `14:00 WIB` |
| `layanan` | string | Yes | Nama layanan spa | `Massage Aromaterapi` |
| `lokasi` | string | Yes | Lokasi spa | `Jl. Raya Cianjur No. 123` |

---

### 3. Kirim ucapan selamat ulang tahun dengan voucher
**Endpoint:** `POST /api/messages/birthday`  
**Description:** Mengirim pesan WhatsApp dengan template ulangtahunuser

**Request Body (JSON):**
| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `phone` | string | Yes | Nomor WhatsApp tujuan (format 62xxx tanpa +) | `6285121158088` |
| `nama_pelanggan` | string | Yes | Nama pelanggan | `Yogi` |
| `nama_spa` | string | Yes | Nama Spa | `Zafaran Spa Cianjur` |
| `persentase` | string | Yes | Persentase diskon | `30%` |
| `expired_date` | string | Yes | Tanggal kedaluwarsa | `31 Maret 2026` |
| `login_param` | string | No | Parameter untuk dynamic button URL | `login` |

---

### 4. Kirim sambutan member baru
**Endpoint:** `POST /api/messages/welcome`  
**Description:** Mengirim pesan WhatsApp dengan template berhasilbergabung

**Request Body (JSON):**
| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `phone` | string | Yes | Nomor WhatsApp tujuan (format 62xxx tanpa +) | `6285121158088` |
| `nama_spa` | string | Yes | Nama Spa | `Zafaran Spa Cianjur` |
| `nama_pelanggan` | string | Yes | Nama pelanggan | `Yogi` |
| `app_url` | string | Yes | URL Aplikasi | `https://zafara-spa-salon.vercel.app` |

---

### 5. Kirim kode OTP verifikasi
**Endpoint:** `POST /api/messages/otp`  
**Description:** Mengirim pesan WhatsApp dengan template otpzafaran

**Request Body (JSON):**
| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `phone` | string | Yes | Nomor WhatsApp tujuan (format 62xxx tanpa +) | `6285121158088` |
| `otp_code` | string | Yes | Kode OTP 6 digit | `123456` |
| `button_param`| string | No | Parameter untuk button URL | `123456` |

---

### 6. Kirim pesan teks biasa (free-form)
**Endpoint:** `POST /api/messages/send-text`  
**Description:** Mengirim pesan WhatsApp teks biasa (hanya bisa dalam 24 jam window)

**Request Body (JSON):**
| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `phone` | string | Yes | Nomor WhatsApp tujuan (format 62xxx tanpa +) | `6285121158088` |
| `message` | string | Yes | Pesan yang dikirimkan | `Terima kasih sudah berkunjung ke Zafaran Spa!` |

---

## Contoh Responses

### Success Response (200 OK)
Pesan berhasil dikirim.
```json
{
  "success": true,
  "messageId": "wamid.HBgNNjI4MTIzNDU2Nzg5FQIAERgSMEE3RDJDMTU0...",
  "data": {
    "messaging_product": "whatsapp",
    "contacts": [
      {
        "input": "6285121158088",
        "wa_id": "6285121158088"
      }
    ],
    "messages": [
      {
        "id": "wamid.HBgNNjI4MTIzNDU2Nzg5FQIAERgSMEE3RDJDMTU0..."
      }
    ]
  }
}
```

### Error Response (400 Bad Request)
Parameter tidak lengkap.
```json
{
  "success": false,
  "error": "Missing required fields"
}
```

### Error Response (500 Server Error)
Server error atau di luar 24h window (untuk text free-form).
```json
{
  "success": false,
  "error": "Server error"
}
```
