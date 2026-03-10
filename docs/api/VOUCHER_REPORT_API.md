# API Dokumentasi - Laporan Voucher

Dokumentasi untuk semua endpoint API Laporan Voucher yang telah diimplementasikan.

## Base URL
```
/api/v1/admin/reports/voucher
```

## Autentikasi
Semua endpoint memerlukan autentikasi admin.

---

## 1. Saldo Voucher Tersisa (Remaining Voucher Balance)
**Endpoint:** `GET /remaining-balance`

Menampilkan saldo voucher yang tersisa untuk voucher tipe "balance".

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| search     | string | Tidak | Cari berdasarkan nama/kode   |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "location": "Naqupos Spa Solo",
            "type": "Balance",
            "code": "SALDO100K",
            "name": "Voucher Saldo 100K",
            "expiryDate": "30 Jun 2026",
            "status": "Active",
            "total": 100000.00,
            "used": 40000.00,
            "remaining": 60000.00
        }
    ],
    "summary": {
        "totalVouchers": 3,
        "totalValue": 800000.00,
        "totalRemaining": 480000.00
    }
}
```

---

## 2. Penjualan Voucher (Voucher Sales)
**Endpoint:** `GET /sales`

Menampilkan data penjualan/penggunaan voucher per voucher.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai (YYYY-MM-DD)   |
| date_to    | string | Tidak | Tanggal akhir (YYYY-MM-DD)   |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "voucherCode": "NEWYEAR10",
            "voucherName": "Diskon Awal Tahun 10%",
            "usageCount": 25,
            "totalDiscount": 1250000.00
        }
    ],
    "summary": {
        "totalVouchers": 7,
        "totalUsage": 150,
        "totalDiscount": 7500000.00
    }
}
```

---

## 3. Penggunaan Voucher (Voucher Usage)
**Endpoint:** `GET /usage`

Menampilkan riwayat penggunaan voucher secara detail.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai (YYYY-MM-DD)   |
| date_to    | string | Tidak | Tanggal akhir (YYYY-MM-DD)   |
| search     | string | Tidak | Cari berdasarkan pelanggan   |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "customer": "Andi Wijaya",
            "location": "Naqupos Spa Solo",
            "type": "Promo",
            "code": "NEWYEAR10",
            "name": "Diskon Awal Tahun 10%",
            "expiryDate": "31 Jan 2026",
            "status": "Active",
            "usageTime": "13 Jan 2026 14:30",
            "used": 50000.00,
            "remaining": 0.00
        }
    ],
    "summary": {
        "totalUsages": 150,
        "totalDiscountGiven": 7500000.00
    }
}
```

---

## 4. Kode Voucher Promo (Promo Voucher Codes)
**Endpoint:** `GET /promo-codes`

Menampilkan daftar kode voucher promo yang tersedia.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai                |
| date_to    | string | Tidak | Tanggal akhir                |
| search     | string | Tidak | Cari berdasarkan nama/kode   |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "name": "Diskon Awal Tahun 10%",
            "discountValue": "10.00%",
            "code": "NEWYEAR10",
            "startDate": "01 Jan 2026",
            "expiryDate": "31 Jan 2026",
            "status": "Active",
            "totalVouchers": 100,
            "available": 75,
            "used": 25
        }
    ],
    "summary": {
        "totalPromoCodes": 7,
        "totalAvailable": 350,
        "totalUsed": 150
    }
}
```

---

## 5. Penukaran Voucher Promo (Promo Voucher Redemption)
**Endpoint:** `GET /promo-redemption`

Menampilkan riwayat penukaran voucher promo.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai (YYYY-MM-DD)   |
| date_to    | string | Tidak | Tanggal akhir (YYYY-MM-DD)   |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "name": "Diskon Weekend 20%",
            "discountValue": "20.00%",
            "code": "WEEKEND20",
            "invoiceNo": "INV-000001",
            "customer": "Andi Wijaya",
            "usedDate": "11 Jan 2026"
        }
    ],
    "summary": {
        "totalRedemptions": 50
    }
}
```

---

## 6. Penukaran Produk Gratis (Free Product Redemption)
**Endpoint:** `GET /free-product-redemption`

Menampilkan riwayat penukaran voucher produk gratis.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai (YYYY-MM-DD)   |
| date_to    | string | Tidak | Tanggal akhir (YYYY-MM-DD)   |
| search     | string | Tidak | Cari berdasarkan pelanggan   |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "name": "Gratis Body Wash",
            "productName": "Body Wash Premium",
            "code": "FREEBODYWASH",
            "invoiceNo": "INV-FREE-A1B2C3",
            "customer": "Budi Santoso",
            "usedDate": "12 Jan 2026"
        }
    ],
    "summary": {
        "totalRedemptions": 30
    }
}
```

---

## 7. Kadaluwarsa Keanggotaan (Membership Expiration)
**Endpoint:** `GET /membership-expiry`

Menampilkan daftar keanggotaan yang akan/sudah kadaluwarsa.

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                        |
|------------|--------|-------|----------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang        |
| date_from  | string | Tidak | Tanggal mulai periode kadaluwarsa |
| date_to    | string | Tidak | Tanggal akhir periode kadaluwarsa |
| status     | string | Tidak | Filter status (expired/active)   |

### Response
```json
{
    "data": [
        {
            "id": 1,
            "customer": "Member 1",
            "phone": "08123456781",
            "membership": "Gold",
            "invoiceNo": "INV-MEM-A1B2C3",
            "invoiceDate": "01 Jan 2025",
            "expiryDate": "01 Jan 2026",
            "status": "Active"
        }
    ],
    "summary": {
        "totalMemberships": 25,
        "expiredCount": 5,
        "activeCount": 20
    }
}
```

---

## Model Database

### Vouchers
```sql
CREATE TABLE vouchers (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) UNIQUE NOT NULL,
    type ENUM('balance', 'promo', 'free_product', 'discount') DEFAULT 'promo',
    discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
    discount_value DECIMAL(15,2) DEFAULT 0,
    min_purchase DECIMAL(15,2) DEFAULT 0,
    max_discount DECIMAL(15,2),
    total_quantity INT DEFAULT 100,
    used_quantity INT DEFAULT 0,
    start_date DATE,
    expiry_date DATE,
    status VARCHAR(50) DEFAULT 'active',
    branch_id BIGINT,
    is_active BOOLEAN DEFAULT TRUE,
    description TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Voucher Usages
```sql
CREATE TABLE voucher_usages (
    id BIGINT PRIMARY KEY,
    voucher_id BIGINT NOT NULL,
    user_id BIGINT,
    booking_id BIGINT,
    branch_id BIGINT,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    used_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Memberships
```sql
CREATE TABLE memberships (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    branch_id BIGINT,
    membership_type VARCHAR(100) DEFAULT 'Silver',
    invoice_no VARCHAR(100),
    invoice_date DATE,
    start_date DATE,
    expiry_date DATE,
    status VARCHAR(50) DEFAULT 'Active',
    price DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Free Product Redemptions
```sql
CREATE TABLE free_product_redemptions (
    id BIGINT PRIMARY KEY,
    voucher_id BIGINT NOT NULL,
    product_id BIGINT,
    user_id BIGINT,
    booking_id BIGINT,
    branch_id BIGINT,
    invoice_no VARCHAR(100),
    redeemed_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## Setup

### Jalankan Migrasi
```bash
php artisan migrate
```

### Jalankan Seeder (Data Dummy)
```bash
php artisan db:seed --class=VoucherSeeder
```

### Frontend API Functions
```typescript
// api.ts
getVoucherRemainingBalance: (params?: any) => fetchJson<any>(`/admin/reports/voucher/remaining-balance${...}`),
getVoucherSales: (params?: any) => fetchJson<any>(`/admin/reports/voucher/sales${...}`),
getVoucherUsage: (params?: any) => fetchJson<any>(`/admin/reports/voucher/usage${...}`),
getVoucherPromoCodes: (params?: any) => fetchJson<any>(`/admin/reports/voucher/promo-codes${...}`),
getVoucherPromoRedemption: (params?: any) => fetchJson<any>(`/admin/reports/voucher/promo-redemption${...}`),
getVoucherFreeProductRedemption: (params?: any) => fetchJson<any>(`/admin/reports/voucher/free-product-redemption${...}`),
getVoucherMembershipExpiry: (params?: any) => fetchJson<any>(`/admin/reports/voucher/membership-expiry${...}`),
```
