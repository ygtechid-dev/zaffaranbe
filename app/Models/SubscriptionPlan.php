<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'plan_key',
        'name',
        'description',
        'price_monthly',
        'price_yearly',
        'features',
        'menu_permissions',
        'is_popular',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'menu_permissions' => 'array',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function getDefaultPlans()
    {
        return [
            [
                'plan_key' => 'starter',
                'name' => 'Starter',
                'description' => 'Untuk bisnis kecil yang baru memulai.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'is_popular' => false,
                'is_active' => true,
                'features' => [
                    'Dashboard',
                    'Kasir (POS)',
                    'Kalender',
                    'Reservasi',
                    'Layanan',
                    'Staff',
                    'Laporan',
                ],
                'menu_permissions' => [
                    'dashboard', 'pos', 'calendar', 'bookings', 'services', 'staff', 'reports', 'subscription'
                ],
            ],
            [
                'plan_key' => 'pro',
                'name' => 'Pro Business',
                'description' => 'Untuk bisnis yang sedang berkembang.',
                'price_monthly' => 215000,
                'price_yearly' => 2580000,
                'is_popular' => true,
                'is_active' => true,
                'features' => [
                    'Dashboard',
                    'Kasir (POS)',
                    'Kalender',
                    'Reservasi',
                    'Layanan',
                    'Staff',
                    'Produk',
                    'Stok Opname',
                    'Data Pelanggan',
                    'Voucher & Promo',
                    'Laporan',
                    'Penjualan',
                    'Cabang',
                    'Manajemen Pengguna',
                    'Pengaturan',
                ],
                'menu_permissions' => [
                    'dashboard', 'pos', 'calendar', 'bookings', 'services', 'staff',
                    'inventory', 'customers', 'marketing', 'reports', 'payment',
                    'settings', 'subscription'
                ],
            ],
        ];
    }
}
