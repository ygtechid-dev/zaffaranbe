# API Dokumentasi - Laporan Staff

Dokumentasi untuk endpoint API Laporan Staff.

## Base URL
```
/api/v1/admin/reports/staff
```

## Autentikasi
Semua endpoint memerlukan autentikasi admin.

---

## 1. Jam Kerja Staff (Staff Attendance)
**Endpoint:** `GET /attendance`

Menampilkan log kehadiran staff dan total jam kerja.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai (YYYY-MM-DD)   |
| date_to    | string | Tidak | Tanggal akhir (YYYY-MM-DD)   |
| staff_id   | int    | Tidak | Filter spesifik staff        |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "name": "Ninda",
            "totalDurationHours": 10.5,
            "logs": [
                {
                    "id": 1,
                    "durasi": "10 Jam, 30 Menit",
                    "mulai": "08:00",
                    "berakhir": "18:30",
                    "lokasi": "Naqupos Spa Solo",
                    "date": "2026-01-01"
                }
            ]
        }
    ],
    "summary": {
        "totalStaff": 1
    }
}
```

---

## 2. Tip Berdasarkan Staff (Tips by Staff)
**Endpoint:** `GET /tips`

Menampilkan ringkasan tip yang diterima staff.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai (YYYY-MM-DD)   |
| date_to    | string | Tidak | Tanggal akhir (YYYY-MM-DD)   |
| staff_id   | int    | Tidak | Filter spesifik staff        |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "staffName": "Ninda",
            "terkumpul": 500000.00,
            "dikembalikan": 500000.00,
            "total": 0.00, // (Terkumpul - Dikembalikan)
            "rataRata": 50000.00
        }
    ],
    "summary": {
        "totalTips": 10,
        "totalCollected": 500000.00,
        "totalReturned": 500000.00
    }
}
```

---

## 3. Ringkasan Komisi Staff (Commission Summary)
**Endpoint:** `GET /commission-summary`

Menampilkan ringkasan komisi per staff dikelompokkan berdasarkan tipe item.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai                |
| date_to    | string | Tidak | Tanggal akhir                |
| staff_id   | int    | Tidak | Filter spesifik staff        |
| filter_by_payment_date | bool | Tidak | Default true (filter by payment date) |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "nama": "Ninda",
            "total": 1500000.00,
            "layanan": 1000000.00,
            "produk": 500000.00,
            "voucher": 0.00,
            "kelas": 0.00,
            "planKelas": 0.00
        }
    ],
    "summary": {
        "totalNilaiKomisi": 1500000.00
    }
}
```

---

## 4. Komisi Staff Terperinci (Detailed Commission)
**Endpoint:** `GET /commission-detailed`

Menampilkan detail setiap transaksi komisi.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai                |
| date_to    | string | Tidak | Tanggal akhir                |
| category   | string | Tidak | Filter tipe item (Service, Product) |
| staff_id   | int    | Tidak | Filter spesifik staff        |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "pelanggan": "Rina Susanti",
            "tanggalFaktur": "01 Jan 2026",
            "tanggalPembayaran": "01 Jan 2026",
            "nomorFaktur": "TRX-20260101-0001",
            "item": "Javanese Massage",
            "namaVariant": "60 Menit",
            "qty": 1,
            "totalPenjualan": 150000.00,
            "persenKomisi": 10.00,
            "besaranKomisi": 15000.00
        }
    ],
    "summary": {
        "totalItems": 1,
        "totalCommission": 15000.00
    }
}
```

---

## 5. Rincian Komisi Staff - Grup Item (Item Group Commission)
**Endpoint:** `GET /commission-item-group`

Menampilkan komisi yang dikelompokkan berdasarkan item dan varian.

### Query Parameters
Sama dengan Detailed Commission.

### Response
```json
{
    "data": [
        {
            "id": 1,
            "tipe": "Service",
            "item": "Javanese Massage",
            "namaVariant": "60 Menit",
            "qty": 10,
            "totalPenjualan": 1500000.00,
            "persenKomisi": "10%",
            "besaranKomisi": 150000.00
        }
    ],
    "summary": {
        "totalGroups": 1,
        "totalCommission": 150000.00
    }
}
```
