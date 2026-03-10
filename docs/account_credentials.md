# Dokumentasi Akun Naqupos Beauty & Spa

Dokumen ini berisi daftar akun default yang dapat digunakan untuk pengujian (testing) dan pengembangan (development) di lingkungan lokal.

## 1. Akun Administrator (Back-office / Web Admin)

Gunakan akun ini untuk mengakses dashboard manajemen, laporan, pengaturan cabang, dan manajemen staff.

| Role | Email | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin@naqupos.com` | `admin123` | Akses penuh ke seluruh fitur dan cabang. |
| **Cashier / POS** | `cashier@naqupos.com` | `password` | Khusus untuk operasional kasir dan POS. |

## 2. Akun Customer (Aplikasi Mobile / Frontend)

Gunakan akun ini untuk mencoba alur reservasi (booking), melihat riwayat, dan profil pelanggan.

| Nama | Email / Phone | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **John Doe** | `customer@naqupos.com` | `123456` | Akun tester utama (User ID: 2) |
| **Jane Smith** | `jane@naqupos.com` | `123456` | Akun tester pendukung |
| **Rina Susanti** | `rina.susanti@gmail.com` | `password` | Akun yang sering digunakan di laporan staff |

---

### Catatan Penting:
*   Jika login menggunakan **Nomor WhatsApp**, pastikan formatnya sesuai dengan yang terdaftar (contoh: `081234567891`).
*   Password default di lingkungan lokal biasanya adalah `123456`, `admin123`, atau `password`.
*   Jika Anda melakukan re-seed database (`php artisan db:seed`), akun-akun di atas akan otomatis dibuat kembali.

---
*Terakhir diperbarui: 17 Februari 2026*
