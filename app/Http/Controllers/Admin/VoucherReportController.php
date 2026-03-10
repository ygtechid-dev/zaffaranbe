<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Models\Membership;
use App\Models\FreeProductRedemption;
use Carbon\Carbon;

class VoucherReportController extends Controller
{
    /**
     * 1. Saldo Voucher Tersisa (Remaining Voucher Balance)
     * GET /api/v1/admin/reports/voucher/remaining-balance
     */
    public function remainingBalance(Request $request)
    {
        $branchId = $request->input('branch_id');
        $search = $request->input('search');

        $query = Voucher::with('branch')
            ->where('type', 'balance')
            ->where('is_active', true);

        if ($branchId && $branchId !== 'all') {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        $vouchers = $query->orderBy('name')->get();

        $data = $vouchers->map(function ($voucher) {
            return [
                'id' => $voucher->id,
                'location' => $voucher->branch ? $voucher->branch->name : 'Semua Cabang',
                'type' => ucfirst($voucher->type),
                'code' => $voucher->code,
                'name' => $voucher->name,
                'expiryDate' => $voucher->expiry_date ? Carbon::parse($voucher->expiry_date)->format('d M Y') : '-',
                'status' => $voucher->status_label,
                'total' => (float) $voucher->discount_value,
                'used' => (float) ($voucher->discount_value * ($voucher->used_quantity / max(1, $voucher->total_quantity))),
                'remaining' => (float) ($voucher->discount_value * ($voucher->remaining / max(1, $voucher->total_quantity))),
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'totalVouchers' => $data->count(),
                'totalValue' => $data->sum('total'),
                'totalRemaining' => $data->sum('remaining'),
            ],
        ]);
    }

    /**
     * 2. Penjualan Voucher (Voucher Sales)
     * GET /api/v1/admin/reports/voucher/sales
     */
    public function voucherSales(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        $query = \App\Models\Booking::select(
            'promo_code',
            DB::raw('COUNT(*) as usage_count'),
            DB::raw('SUM(discount_amount) as total_discount')
        )
            ->whereNotNull('promo_code')
            ->where('discount_amount', '>', 0)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->whereNotIn('status', ['cancelled'])
            ->groupBy('promo_code');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $usages = $query->get();

        $data = $usages->map(function ($usage) {
            $promo = \App\Models\Promo::where('code', $usage->promo_code)->first();

            return [
                'id' => $promo ? $promo->id : 0,
                'voucherCode' => $usage->promo_code,
                'voucherName' => $promo ? $promo->title : 'Voucher/Promo',
                'usageCount' => (int) $usage->usage_count,
                'totalDiscount' => (float) $usage->total_discount,
            ];
        });

        $summary = [
            'totalVouchers' => $data->count(),
            'totalUsage' => $data->sum('usageCount'),
            'totalDiscount' => $data->sum('totalDiscount'),
        ];

        return response()->json([
            'data' => $data->values(),
            'summary' => $summary,
        ]);
    }

    /**
     * 3. Penggunaan Voucher (Voucher Usage)
     * GET /api/v1/admin/reports/voucher/usage
     */
    public function voucherUsage(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $search = $request->input('search');

        $query = \App\Models\Booking::with(['user', 'branch'])
            ->whereNotNull('promo_code')
            ->where('discount_amount', '>', 0)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('created_at', 'desc');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        $usages = $query->get();

        $data = $usages->map(function ($usage) {
            $promo = \App\Models\Promo::where('code', $usage->promo_code)->first();

            return [
                'id' => $usage->id,
                'customer' => $usage->user ? $usage->user->name : ($usage->guest_name ?? 'Guest'),
                'location' => $usage->branch ? $usage->branch->name : 'N/A',
                'type' => $promo ? ucfirst($promo->type) : 'Promo',
                'code' => $usage->promo_code,
                'name' => $promo ? $promo->title : 'Voucher/Promo',
                'expiryDate' => $promo && $promo->end_date
                    ? Carbon::parse($promo->end_date)->format('d M Y')
                    : '-',
                'status' => $promo ? ($promo->is_valid ? 'Active' : 'Expired') : 'N/A',
                'usageTime' => Carbon::parse($usage->created_at)->format('d M Y H:i'),
                'used' => (float) $usage->discount_amount,
                'remaining' => $promo ? (float) $promo->remaining_quota : 0,
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'totalUsages' => $data->count(),
                'totalDiscountGiven' => $data->sum('used'),
            ],
        ]);
    }

    /**
     * 4. Kode Voucher Promo (Promo Voucher Codes)
     * GET /api/v1/admin/reports/voucher/promo-codes
     */
    public function promoCodes(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = \App\Models\Promo::with('branch');

        if ($branchId && $branchId !== 'all') {
            $query->where(function($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                  ->orWhereNull('branch_id');
            });
        }

        if ($dateFrom && $dateTo) {
            $query->where('start_date', '<=', $dateTo)
                  ->where('end_date', '>=', $dateFrom);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        $promos = $query->orderBy('title')->get();

        $data = $promos->map(function ($promo) {
            $discountDisplay = $promo->type === 'percent'
                ? number_format((float) $promo->discount, 2) . '%'
                : 'Rp ' . number_format((float) $promo->discount, 0, ',', '.');

            return [
                'id' => $promo->id,
                'name' => $promo->title,
                'discountValue' => $discountDisplay,
                'code' => $promo->code,
                'startDate' => $promo->start_date ? Carbon::parse($promo->start_date)->format('d M Y') : '-',
                'expiryDate' => $promo->end_date ? Carbon::parse($promo->end_date)->format('d M Y') : '-',
                'status' => $promo->is_valid ? 'Active' : 'Expired',
                'totalVouchers' => $promo->quota,
                'available' => $promo->remaining_quota,
                'used' => $promo->used,
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'totalPromoCodes' => $data->count(),
                'totalAvailable' => $data->sum('available'),
                'totalUsed' => $data->sum('used'),
            ],
        ]);
    }

    /**
     * 5. Penukaran Voucher Promo (Promo Voucher Redemption)
     * GET /api/v1/admin/reports/voucher/promo-redemption
     */
    public function promoRedemption(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));

        $query = \App\Models\Booking::with(['user', 'branch'])
            ->whereNotNull('promo_code')
            ->where('discount_amount', '>', 0)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('created_at', 'desc');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $redemptions = $query->get();

        $data = $redemptions->map(function ($redemption) {
            $promo = \App\Models\Promo::where('code', $redemption->promo_code)->first();

            $discountDisplay = $promo && $promo->type === 'percent'
                ? number_format((float) $promo->discount, 2) . '%'
                : 'Rp ' . number_format($promo ? (float) $promo->discount : 0, 0, ',', '.');

            return [
                'id' => $redemption->id,
                'name' => $promo ? $promo->title : 'N/A',
                'discountValue' => $discountDisplay,
                'code' => $redemption->promo_code,
                'invoiceNo' => $redemption->booking_ref
                    ? $redemption->booking_ref
                    : 'INV-' . str_pad($redemption->id, 6, '0', STR_PAD_LEFT),
                'customer' => $redemption->user ? $redemption->user->name : ($redemption->guest_name ?? 'Guest'),
                'usedDate' => Carbon::parse($redemption->created_at)->format('d M Y'),
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'totalRedemptions' => $data->count(),
            ],
        ]);
    }

    /**
     * 6. Penukaran Produk Gratis (Free Product Redemption)
     * GET /api/v1/admin/reports/voucher/free-product-redemption
     */
    public function freeProductRedemption(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $search = $request->input('search');

        $query = FreeProductRedemption::with(['voucher', 'product', 'user'])
            ->whereDate('redeemed_at', '>=', $dateFrom)
            ->whereDate('redeemed_at', '<=', $dateTo)
            ->orderBy('redeemed_at', 'desc');

        if ($branchId && $branchId !== 'all') {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        $redemptions = $query->get();

        $data = $redemptions->map(function ($redemption) {
            return [
                'id' => $redemption->id,
                'name' => $redemption->voucher ? $redemption->voucher->name : 'N/A',
                'productName' => $redemption->product ? $redemption->product->name : 'N/A',
                'code' => $redemption->voucher ? $redemption->voucher->code : 'N/A',
                'invoiceNo' => $redemption->invoice_no ?? 'N/A',
                'customer' => $redemption->user ? $redemption->user->name : ($redemption->guest_name ?? 'Guest'),
                'usedDate' => Carbon::parse($redemption->redeemed_at)->format('d M Y'),
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'totalRedemptions' => $data->count(),
            ],
        ]);
    }

    /**
     * 7. Kadaluwarsa Keanggotaan (Membership Expiration)
     * GET /api/v1/admin/reports/voucher/membership-expiry
     */
    public function membershipExpiry(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->addMonths(3)->format('Y-m-d'));
        $status = $request->input('status');

        $query = Membership::with(['user', 'branch']);

        if ($dateFrom) {
            $query->whereDate('expiry_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('expiry_date', '<=', $dateTo);
        }

        $query->orderBy('expiry_date', 'asc');

        if ($branchId && $branchId !== 'all') {
            $query->where(function($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                  ->orWhereNull('branch_id');
            });
        }

        if ($status) {
            if ($status === 'expired') {
                $query->where('expiry_date', '<', Carbon::now());
            } elseif ($status === 'active') {
                $query->where('expiry_date', '>=', Carbon::now());
            }
        }

        $memberships = $query->get();

        $data = $memberships->map(function ($membership) {
            $isExpired = $membership->expiry_date && Carbon::parse($membership->expiry_date)->isPast();

            return [
                'id' => $membership->id,
                'customer' => $membership->user ? $membership->user->name : 'N/A',
                'phone' => $membership->user ? ($membership->user->phone ?? '-') : '-',
                'membership' => $membership->membership_type,
                'invoiceNo' => $membership->invoice_no ?? '-',
                'invoiceDate' => $membership->invoice_date
                    ? Carbon::parse($membership->invoice_date)->format('d M Y')
                    : '-',
                'expiryDate' => $membership->expiry_date
                    ? Carbon::parse($membership->expiry_date)->format('d M Y')
                    : '-',
                'status' => $isExpired ? 'Expired' : 'Active',
            ];
        });

        $expiredCount = $data->filter(fn($d) => $d['status'] === 'Expired')->count();
        $activeCount = $data->filter(fn($d) => $d['status'] === 'Active')->count();

        return response()->json([
            'data' => $data->values(),
            'summary' => [
                'totalMemberships' => $data->count(),
                'expiredCount' => $expiredCount,
                'activeCount' => $activeCount,
            ],
        ]);
    }
}
