# API Dokumentasi - Laporan Inventori

Dokumentasi untuk semua endpoint API Laporan Inventori yang telah diimplementasikan.

## Base URL
```
/api/v1/admin/reports/inventory
```

## Autentikasi
Semua endpoint memerlukan autentikasi admin.

---

## 1. Stok Yang Ada (Current Stock)
**Endpoint:** `GET /current-stock`

Menampilkan stok saat ini untuk semua produk.

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
            "nama": "Minyak Pijat Aromaterapi",
            "lokasi": "Gudang Utama",
            "stok": 85,
            "stockCost": 25000.00,
            "average": 25000.00,
            "totalRetail": 3825000.00,
            "hargaRetail": 45000.00,
            "reorderPoint": 20,
            "reorderAmount": 50
        }
    ],
    "summary": {
        "totalAssetValue": 5250000.00,
        "totalRetailValue": 9450000.00,
        "totalProducts": 20,
        "totalStock": 1500
    }
}
```

---

## 2. Performa Penjualan Produk (Product Performance)
**Endpoint:** `GET /product-performance`

Menampilkan performa penjualan produk dalam periode tertentu.

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
            "nama": "Minyak Pijat Aromaterapi",
            "stok": 85,
            "qtyTerjual": 25,
            "hpp": 625000.00,
            "penjualanBersih": 1125000.00,
            "rataPenjualan": 45000.00
        }
    ],
    "summary": {
        "totalPenjualanBersih": 15750000.00,
        "totalHpp": 8750000.00,
        "totalQtyTerjual": 350
    }
}
```

---

## 3. Log Pergerakan Stok (Stock Movement Log)
**Endpoint:** `GET /stock-movement-log`

Menampilkan log semua pergerakan stok.

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
            "productName": "Minyak Pijat Aromaterapi",
            "lokasi": "Naqupos Spa Solo",
            "kodeBarang": "BRG-1001",
            "penyesuaian": 10,
            "hargaModal": 25000.00,
            "namaStaff": "Amandha Devlinsky",
            "deskripsi": "Stok Masuk",
            "diTangan": 95,
            "tanggal": "13 Jan 2026"
        }
    ],
    "summary": {
        "totalMovements": 150,
        "totalIn": 500,
        "totalOut": 350
    }
}
```

---

## 4. Kalkulasi Pergerakan Stok (Stock Calculation)
**Endpoint:** `GET /stock-calculation`

Menampilkan kalkulasi pergerakan stok dengan stok awal dan penerimaan.

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
            "productName": "Minyak Pijat Aromaterapi",
            "sku": "SKU-1001",
            "kodeBarang": "BRG-1001",
            "brandName": "Naqupos Originals",
            "category": "Perawatan Tubuh",
            "pemasok": "PT Herbal Alami Nusantara",
            "startStock": 50,
            "diterima": 45
        }
    ],
    "summary": {
        "totalProducts": 20,
        "totalStartStock": 1000,
        "totalReceived": 500
    }
}
```

---

## 5. Konsumsi Produk (Product Consumption)
**Endpoint:** `GET /product-consumption`

Menampilkan konsumsi produk untuk layanan spa.

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
            "nama": "Minyak Pijat Aromaterapi",
            "qty": 45,
            "sku": "SKU-1001",
            "kodeBarang": "BRG-1001",
            "avgCostPrice": 25000.00,
            "totalBiaya": 1125000.00
        }
    ],
    "summary": {
        "totalItems": 15,
        "totalQty": 350,
        "totalBiaya": 8750000.00
    }
}
```

---

## 6. Detail Konsumsi Produk (Product Consumption Detail)
**Endpoint:** `GET /product-consumption/{productId}`

Menampilkan detail konsumsi untuk produk tertentu.

### Path Parameters
| Parameter  | Tipe | Wajib | Deskripsi    |
|------------|------|-------|--------------|
| productId  | int  | Ya    | ID Produk    |

### Query Parameters
| Parameter  | Tipe   | Wajib | Deskripsi                    |
|------------|--------|-------|------------------------------|
| branch_id  | int    | Tidak | Filter berdasarkan cabang    |
| date_from  | string | Tidak | Tanggal mulai (YYYY-MM-DD)   |
| date_to    | string | Tidak | Tanggal akhir (YYYY-MM-DD)   |

### Response
```json
{
    "product": {
        "id": 1,
        "name": "Minyak Pijat Aromaterapi",
        "sku": "SKU-1001",
        "code": "BRG-1001"
    },
    "data": [
        {
            "id": 1,
            "tanggal": "13 Jan 2026 14:30",
            "qty": 2,
            "costPrice": 25000.00,
            "totalBiaya": 50000.00,
            "staff": "Amandha Devlinsky",
            "lokasi": "Naqupos Spa Solo",
            "booking": "BK-2026-001",
            "notes": "Digunakan untuk layanan spa"
        }
    ],
    "summary": {
        "totalQty": 45,
        "totalBiaya": 1125000.00
    }
}
```

---

## Model Database

### Products
```sql
CREATE TABLE products (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    code VARCHAR(100) UNIQUE,
    brand_name VARCHAR(255),
    category VARCHAR(100),
    description TEXT,
    cost_price DECIMAL(15,2) DEFAULT 0,
    retail_price DECIMAL(15,2) DEFAULT 0,
    reorder_point INT DEFAULT 10,
    reorder_amount INT DEFAULT 50,
    supplier_id BIGINT,
    branch_id BIGINT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Product Stocks
```sql
CREATE TABLE product_stocks (
    id BIGINT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    branch_id BIGINT NOT NULL,
    location VARCHAR(255) DEFAULT 'Gudang Utama',
    quantity INT DEFAULT 0,
    average_cost DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Stock Movements
```sql
CREATE TABLE stock_movements (
    id BIGINT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    branch_id BIGINT NOT NULL,
    user_id BIGINT,
    movement_type ENUM('in', 'out', 'adjustment', 'transfer', 'return', 'opname'),
    quantity INT NOT NULL,
    quantity_before INT DEFAULT 0,
    quantity_after INT DEFAULT 0,
    cost_price DECIMAL(15,2) DEFAULT 0,
    description VARCHAR(255),
    reference VARCHAR(100),
    movement_date TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Product Consumptions
```sql
CREATE TABLE product_consumptions (
    id BIGINT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    branch_id BIGINT NOT NULL,
    booking_id BIGINT,
    user_id BIGINT,
    quantity INT NOT NULL,
    cost_price DECIMAL(15,2) DEFAULT 0,
    consumption_date TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Suppliers
```sql
CREATE TABLE suppliers (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) UNIQUE,
    contact_person VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
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
php artisan db:seed --class=InventorySeeder
```
