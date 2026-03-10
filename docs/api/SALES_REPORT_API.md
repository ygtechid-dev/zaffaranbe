# Laporan Penjualan - Backend API Documentation

## Overview
Ini adalah dokumentasi untuk semua API endpoints Laporan Penjualan (Sales Reports) yang telah diimplementasikan di backend Naqupos.

## Base URL
```
/api/v1/admin/reports/sales
```

## Authentication
Semua endpoint memerlukan authentication dengan Bearer Token dan role `admin` atau `owner`.

## Common Query Parameters
Semua endpoint menerima parameter berikut:
- `branch_id` (optional): Filter berdasarkan branch ID
- `date_from` (optional): Tanggal mulai (format: YYYY-MM-DD), default: awal bulan ini
- `date_to` (optional): Tanggal akhir (format: YYYY-MM-DD), default: akhir bulan ini
- `staff_id` (optional): Filter berdasarkan staff/therapist ID

## Endpoints

### 1. Penjualan Item
**GET** `/sales/item`

Menampilkan daftar item penjualan individual.

**Response Fields:**
- `source`: Sumber penjualan (ONLINE/WALK-IN)
- `type`: Tipe item (Service/Product)
- `customer`: Nama pelanggan
- `name`: Nama item/layanan
- `location`: Lokasi cabang
- `staffReceiver`: Staff penerima
- `invoiceStatus`: Status faktur (PAID/PENDING)
- `qty`: Jumlah

---

### 2. Penjualan Berdasarkan Item
**GET** `/sales/by-item`

Menampilkan penjualan yang dikelompokkan berdasarkan item/layanan.

**Response Fields:**
- `type`: Tipe item
- `name`: Nama item
- `sold`: Jumlah terjual
- `gross`: Pendapatan kotor
- `discount`: Diskon item
- `salesDiscount`: Diskon penjualan
- `refund`: Pengembalian
- `nett`: Pendapatan bersih
- `tax`: Pajak
- `voucherUsage`: Penggunaan voucher
- `totalSales`: Total penjualan

---

### 3. Detail Penjualan Berdasarkan Item
**GET** `/sales/by-item-detail`

Menampilkan detail penjualan berdasarkan item dengan breakdown per staff.

**Response Fields:**
- `type`: Tipe item
- `name`: Nama item
- `staffName`: Nama staff
- `sold`: Jumlah terjual
- `gross`: Pendapatan kotor
- `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 4. Penjualan Berdasarkan Tipe
**GET** `/sales/by-type`

Menampilkan penjualan dikelompokkan berdasarkan tipe (Service/Product/Class).

**Response Fields:**
- `type`: Tipe (Service/Product/Class)
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 5. Penjualan Berdasarkan Service
**GET** `/sales/by-service`

Menampilkan penjualan dikelompokkan berdasarkan layanan.

**Response Fields:**
- `name`: Nama layanan
- `kotor`: Pendapatan kotor
- `discount`, `refund`, `nett`, `voucherUsage`, `salesDiscount`, `totalSales`, `tax`, `hargaModal`, `profit`

---

### 6. Penjualan Berdasarkan Produk
**GET** `/sales/by-product`

Menampilkan penjualan dikelompokkan berdasarkan produk.

**Response Fields:**
- `category`: Kategori produk
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 7. Penjualan Berdasarkan Lokasi
**GET** `/sales/by-location`

Menampilkan penjualan dikelompokkan berdasarkan lokasi/cabang.

**Response Fields:**
- `location`: Nama lokasi
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 8. Penjualan Berdasarkan Channel
**GET** `/sales/by-channel`

Menampilkan penjualan dikelompokkan berdasarkan channel (ONLINE/WALK-IN).

**Response Fields:**
- `channel`: Nama channel
- `sold`, `gross`, `discount`, `totalDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 9. Penjualan Berdasarkan Pelanggan
**GET** `/sales/by-customer`

Menampilkan penjualan dikelompokkan berdasarkan pelanggan.

**Response Fields:**
- `email`: Email/ID pelanggan
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 10. Penjualan Berdasarkan Staff Terperinci
**GET** `/sales/by-staff-detailed`

Menampilkan penjualan detail per staff dengan breakdown per layanan.

**Response Fields:**
- `staffName`: Nama staff
- `serviceName`: Nama layanan
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 11. Penjualan Berdasarkan Staff
**GET** `/sales/by-staff`

Menampilkan penjualan dikelompokkan berdasarkan staff.

**Response Fields:**
- `name`: Nama staff
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 12. Penjualan Berdasarkan Jam
**GET** `/sales/by-hour`

Menampilkan penjualan dikelompokkan berdasarkan jam (00:00 - 23:00).

**Response Fields:**
- `hour`: Jam (format: HH:00)
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 13. Penjualan Berdasarkan Jam Per Hari
**GET** `/sales/by-hour-per-day`

Menampilkan penjualan berdasarkan jam per hari.

**Response Fields:**
- `date`: Tanggal
- `hour`: Jam
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 14. Penjualan Per Hari
**GET** `/sales/per-day`

Menampilkan penjualan harian.

**Response Fields:**
- `date`: Tanggal (format: YYYY-MM-DD)
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 15. Penjualan Per Bulan
**GET** `/sales/per-month`

Menampilkan penjualan bulanan.

**Response Fields:**
- `month`: Bulan (format: Month YYYY)
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 16. Penjualan Per Kuartal
**GET** `/sales/per-quarter`

Menampilkan penjualan per kuartal.

**Response Fields:**
- `quarter`: Kuartal (format: Q1 YYYY)
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 17. Pendapatan Per Tahun
**GET** `/sales/per-year`

Menampilkan pendapatan tahunan.

**Response Fields:**
- `year`: Tahun
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 18. Log Penjualan
**GET** `/sales/log`

Menampilkan log penjualan.

**Response Fields:**
- `name`: Nama item
- `qty`: Jumlah
- `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 19. Item Penjualan Berdasarkan Tanggal
**GET** `/sales/item-by-date`

Menampilkan item penjualan berdasarkan tanggal.

**Response Fields:**
- `date`: Tanggal
- `serviceName`: Nama layanan
- `customer`: Nama pelanggan
- `qty`: Jumlah
- `gross`, `discount`, `nett`

---

### 20. Penjualan Berdasarkan Paket Layanan
**GET** `/sales/by-service-package`

Menampilkan penjualan berdasarkan paket layanan.

---

### 21. Penjualan Berdasarkan Varian Layanan
**GET** `/sales/by-service-variant`

Menampilkan penjualan berdasarkan varian layanan (dengan durasi).

**Response Fields:**
- `variant`: Nama layanan - Durasi
- `sold`, `gross`, `discount`, `salesDiscount`, `refund`, `nett`, `tax`, `voucherUsage`, `totalSales`

---

### 22. Penjualan Berdasarkan Varian Produk
**GET** `/sales/by-product-variant`

Menampilkan penjualan berdasarkan varian produk.

---

### 23. Penjualan Refund
**GET** `/sales/refund`

Menampilkan daftar penjualan yang di-refund.

**Response Fields:**
- `invoiceNo`: Nomor faktur
- `date`: Tanggal
- `amount`: Jumlah refund
- `costPrice`: Harga modal
- `customer`: Pelanggan
- `location`: Lokasi
- `source`: Sumber
- `changedOn`: Diubah pada
- `changedBy`: Diubah oleh

---

### 24. Penjualan Dibatalkan
**GET** `/sales/cancelled`

Menampilkan daftar penjualan yang dibatalkan.

**Response Fields:**
- `invoiceNo`: Nomor faktur
- `date`: Tanggal
- `serviceName`: Nama layanan
- `amount`: Jumlah
- `customer`: Pelanggan
- `location`: Lokasi
- `reason`: Alasan pembatalan
- `cancelledAt`: Dibatalkan pada

---

### 25. Penjualan Berdasarkan Item Material
**GET** `/sales/by-material-item`

Menampilkan penjualan berdasarkan item material (bahan).

---

## Response Format

Semua endpoint mengembalikan response dengan format berikut:

```json
{
    "period": {
        "from": "2026-01-01",
        "to": "2026-01-31"
    },
    "data": [...],
    "summary": {
        "totalSold": 0,
        "gross": 0,
        "discount": 0,
        "salesDiscount": 0,
        "refund": 0,
        "nett": 0,
        "tax": 0,
        "voucherUsage": 0,
        "totalSales": 0
    }
}
```

## Frontend Integration

### Contoh Penggunaan di Frontend (React):

```typescript
import { api } from '@/services/api';
import { useEffect, useState } from 'react';

// Contoh untuk Penjualan Berdasarkan Item
const [data, setData] = useState([]);
const [summary, setSummary] = useState({});

useEffect(() => {
    const fetchData = async () => {
        try {
            const result = await api.getSalesByItem({
                branch_id: selectedBranchId,
                date_from: startDate.toISOString().split('T')[0],
                date_to: endDate.toISOString().split('T')[0]
            });
            setData(result.data);
            setSummary(result.summary);
        } catch (error) {
            console.error('Error fetching sales data:', error);
        }
    };
    fetchData();
}, [selectedBranchId, startDate, endDate]);
```

## Files Changed

### Backend
- `/be/app/Http/Controllers/Admin/SalesReportController.php` - Controller baru dengan 25 method
- `/be/routes/web.php` - Routes baru untuk 25 endpoint

### Frontend
- `/web-admin/src/services/api.ts` - 25 API function baru untuk memanggil endpoint
