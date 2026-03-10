<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Branch;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $roleName = strtolower($user->role);

        // Find role to get permissions
        $role = Role::where('name', $roleName)->first();
        $permissions = $role ? ($role->permissions ?? []) : [];

        // ONLY super_admin and owner bypass subscription filtering.
        // Regular 'admin' (branch admin) is subject to subscription plan limits.
        $isSuperAdmin = in_array($roleName, ['super_admin', 'owner']);

        // Grant full role permissions to super admins
        $hasFullRoleAccess = in_array($roleName, ['super_admin', 'admin', 'owner']);

        // --- Get subscription status for the current branch ---
        $subscriptionMenuPermissions = null; // null = no subscription filter (super admin/owner only)

        if (!$isSuperAdmin) {
            // For branch admin and other roles: filter by their branch subscription
            $branchId = $user->branch_id ?? null;
            if (!$branchId && $user->branch) {
                $branch = Branch::where('name', $user->branch)->first();
                $branchId = $branch?->id;
            }

            if ($branchId) {
                $subscription = Subscription::where('branch_id', $branchId)
                    ->where('status', 'active')
                    ->orderByDesc('expires_at')
                    ->first();

                $planKey = $subscription ? $subscription->plan_key : 'starter';

                // Check if expired for pro
                $isPro = false;
                if ($subscription && $subscription->plan_key === 'pro') {
                    $isPro = !$subscription->expires_at || $subscription->expires_at->isFuture();
                }
                $planKey = $isPro ? 'pro' : 'starter';

                // Get menu permissions from plan
                $plan = SubscriptionPlan::where('plan_key', $planKey)->first();
                if ($plan && $plan->menu_permissions) {
                    $subscriptionMenuPermissions = $plan->menu_permissions;
                } else {
                    // Default menu permissions per plan
                    $subscriptionMenuPermissions = $planKey === 'pro'
                        ? ['dashboard', 'pos', 'calendar', 'bookings', 'services', 'staff', 'inventory', 'customers', 'marketing', 'reports', 'sales', 'payment', 'settings', 'subscription']
                        : ['dashboard', 'pos', 'calendar', 'bookings', 'services', 'staff', 'reports', 'subscription'];
                }
            } else {
                // No branch found: default to starter permissions
                $subscriptionMenuPermissions = ['dashboard', 'pos', 'calendar', 'bookings', 'services', 'staff', 'reports', 'subscription'];
            }
        }

        $navCategories = [
            [
                'id' => 'main',
                'label' => 'Menu Utama',
                'icon' => 'LayoutDashboard',
                'permission' => 'dashboard',
                'items' => [
                    ['path' => '/dashboard', 'label' => 'Dashboard', 'icon' => 'LayoutDashboard', 'permission' => 'dashboard'],
                    ['path' => '/pos', 'label' => 'Kasir (POS)', 'icon' => 'ShoppingCart', 'permission' => 'pos'],
                ]
            ],
            [
                'id' => 'operations',
                'label' => 'Operasional',
                'icon' => 'Briefcase',
                'permission' => 'calendar',
                'items' => [
                    ['path' => '/calendar', 'label' => 'Kalender', 'icon' => 'Calendar', 'permission' => 'calendar'],
                    ['path' => '/bookings', 'label' => 'Reservasi', 'icon' => 'BookOpen', 'permission' => 'bookings'],
                    ['path' => '/services', 'label' => 'Layanan', 'icon' => 'Sparkles', 'permission' => 'services'],
                    ['path' => '/staff', 'label' => 'Staff', 'icon' => 'UserCog', 'permission' => 'staff'],
                ]
            ],
            [
                'id' => 'inventory',
                'label' => 'Inventori',
                'icon' => 'Package',
                'permission' => 'inventory',
                'items' => [
                    ['path' => '/inventory', 'label' => 'Produk', 'icon' => 'Package', 'permission' => 'inventory'],
                    ['path' => '/inventory-orders', 'label' => 'Pesanan', 'icon' => 'ClipboardList', 'permission' => 'inventory'],
                    ['path' => '/stock-opname', 'label' => 'Stok Opname', 'icon' => 'ClipboardList', 'permission' => 'inventory'],
                ]
            ],
            [
                'id' => 'crm',
                'label' => 'Pelanggan',
                'icon' => 'Heart',
                'permission' => 'customers',
                'items' => [
                    ['path' => '/customers', 'label' => 'Data Pelanggan', 'icon' => 'Users', 'permission' => 'customers'],
                    ['path' => '/feedback', 'label' => 'Feedback & Ulasan', 'icon' => 'MessageSquare', 'permission' => 'customers'],
                    ['path' => '/loyalty', 'label' => 'Loyalty Point', 'icon' => 'Gift', 'permission' => 'customers'],
                ]
            ],
            [
                'id' => 'marketing',
                'label' => 'Marketing',
                'icon' => 'Megaphone',
                'permission' => 'marketing',
                'items' => [
                    ['path' => '/marketing/vouchers', 'label' => 'Voucher & Promo', 'icon' => 'Gift', 'permission' => 'marketing'],
                    ['path' => '/marketing/news', 'label' => 'Berita & Artikel', 'icon' => 'FileText', 'permission' => 'marketing'],
                    ['path' => '/marketing/campaigns', 'label' => 'Kampanye', 'icon' => 'Megaphone', 'permission' => 'marketing'],
                    ['path' => '/marketing/automation', 'label' => 'Automation', 'icon' => 'Zap', 'permission' => 'marketing'],
                    ['path' => '/banners', 'label' => 'Kelola Banner', 'icon' => 'Image', 'permission' => 'marketing'],
                ]
            ],
            [
                'id' => 'analytics',
                'label' => 'Laporan & Analitik',
                'icon' => 'TrendingUp',
                'permission' => 'reports',
                'items' => [
                    ['path' => '/reports', 'label' => 'Laporan', 'icon' => 'BarChart3', 'permission' => 'reports'],
                    ['path' => '/sales', 'label' => 'Penjualan', 'icon' => 'ShoppingCart', 'permission' => 'sales'], // Pro only
                ]
            ],
            [
                'id' => 'master',
                'label' => 'Master Data',
                'icon' => 'Building2',
                'permission' => 'settings',
                'items' => [
                    ['path' => '/branches', 'label' => 'Cabang', 'icon' => 'Building2', 'permission' => 'settings'],
                    ['path' => '/facilities', 'label' => 'Fasilitas', 'icon' => 'LayoutDashboard', 'permission' => 'settings'],
                    ['path' => '/assets', 'label' => 'Aset', 'icon' => 'Package', 'permission' => 'settings'],
                    ['path' => '/rooms', 'label' => 'Ruangan', 'icon' => 'DoorOpen', 'permission' => 'settings'],
                    ['path' => '/expense-categories', 'label' => 'Kategori Biaya', 'icon' => 'Wallet', 'permission' => 'settings'],
                ]
            ],
            [
                'id' => 'payment',
                'label' => 'Pembayaran',
                'icon' => 'CreditCard',
                'permission' => 'payment',
                'items' => [
                    ['path' => '/payment-methods', 'label' => 'Metode Bayar', 'icon' => 'CreditCard', 'permission' => 'payment'],
                ]
            ],
            [
                'id' => 'billing',
                'label' => 'Berlangganan',
                'icon' => 'Crown',
                'permission' => 'subscription',
                'items' => [
                    ['path' => '/subscriptions', 'label' => 'Subscription', 'icon' => 'Crown', 'permission' => 'subscription'],
                ]
            ],
            [
                'id' => 'system',
                'label' => 'Sistem',
                'icon' => 'Wrench',
                'permission' => 'settings',
                'items' => [
                    ['path' => '/users', 'label' => 'Manajemen Pengguna', 'icon' => 'Shield', 'permission' => 'settings'],
                    ['path' => '/audit-log', 'label' => 'Riwayat Aktivitas', 'icon' => 'FileText', 'permission' => 'settings'],
                    ['path' => '/settings', 'label' => 'Pengaturan', 'icon' => 'Settings', 'permission' => 'settings'],
                ]
            ],
        ];

        // Filter by permissions
        $filtered = [];
        foreach ($navCategories as $category) {
            // 1. Check role permission (super admins and 'admin' role have full role access)
            $hasRolePermission = $hasFullRoleAccess || in_array($category['permission'], $permissions);
            if (!$hasRolePermission) continue;

            // 2. Check subscription permission
            //    - super_admin / owner: $subscriptionMenuPermissions is null → always allowed
            //    - admin / cashier: must match their branch subscription plan
            $hasSubPermission = $subscriptionMenuPermissions === null
                || in_array($category['permission'], $subscriptionMenuPermissions);
            if (!$hasSubPermission) continue;

            $items = [];
            foreach ($category['items'] as $item) {
                $hasItemRolePerm = $hasFullRoleAccess || in_array($item['permission'], $permissions);
                $hasItemSubPerm = $subscriptionMenuPermissions === null
                    || in_array($item['permission'], $subscriptionMenuPermissions);

                if ($hasItemRolePerm && $hasItemSubPerm) {
                    $items[] = $item;
                }
            }

            if (count($items) > 0) {
                $category['items'] = $items;
                $filtered[] = $category;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $filtered
        ]);
    }
}
