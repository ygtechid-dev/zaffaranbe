<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\Branch;
use App\Models\User;
use App\Models\TransactionItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    /**
     * Helper function to get base query with filters
     */
    private function getBaseTransactionQuery(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();
        $staffId = $request->input('staff_id');

        $query = Transaction::with(['booking.user', 'booking.service', 'booking.therapist', 'branch']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($staffId) {
            $query->whereHas('booking', function ($q) use ($staffId) {
                $q->where('therapist_id', $staffId);
            });
        }

        $query->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        return $query;
    }

    /**
     * Helper function to calculate common summary
     */
    private function calculateSummary($data)
    {
        return [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => $data->sum('discount'),
            'salesDiscount' => $data->sum('salesDiscount'),
            'refund' => $data->sum('refund'),
            'nett' => $data->sum('nett'),
            'tax' => $data->sum('tax'),
            'voucherUsage' => $data->sum('voucherUsage'),
            'totalSales' => $data->sum('totalSales'),
        ];
    }

    /**
     * 1. Penjualan Item - Sales by individual items
     */
    public function salesItem(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = TransactionItem::with(['transaction.branch', 'transaction.cashier', 'transaction.booking.user', 'service', 'product', 'therapist'])
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->select('transaction_items.*')
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $items = $query->orderBy('transactions.transaction_date', 'desc')->get();

        $data = $items->map(function ($item) {
            $customerName = 'Guest';
            if ($item->transaction->booking) {
                $customerName = $item->transaction->booking->user->name ?? $item->transaction->booking->customer_name ?? $item->transaction->booking->guest_name ?? 'Guest';
            }

            $itemName = $item->type === 'service' ? ($item->service->name ?? '-') : ($item->product->name ?? '-');
            $duration = $item->type === 'service' ? ($item->service->duration ?? 0) : 0;
            $txDate = \Carbon\Carbon::parse($item->transaction->transaction_date);

            return [
                'id' => $item->id,
                'receiptNo' => strtoupper(substr(md5($item->transaction->id . 'rcp'), 0, 12)),
                'invoiceNo' => $item->transaction->transaction_ref ?? ('INV-' . $item->transaction->id),
                'source' => $item->transaction->booking ? (in_array($item->transaction->payment_method, ['virtual_account', 'transfer']) ? 'ONLINE' : 'WALK-IN') : 'POS',
                'type' => ucfirst($item->type),
                'customer' => $customerName,
                'name' => $itemName,
                'location' => $item->transaction->branch->name ?? '-',
                'soldBy' => $item->transaction->cashier->name ?? '-',
                'staffReceiver' => $item->therapist->name ?? '-',
                'servedBy' => $item->therapist->name ?? '-',
                'invoiceStatus' => strtoupper($item->transaction->status ?? 'COMPLETED'),
                'qty' => $item->quantity,
                'price' => floatval($item->price),
                'subtotal' => floatval($item->subtotal),
                'date' => $txDate->format('d M Y'),
                'time' => $item->start_time ? \Carbon\Carbon::parse($item->start_time)->format('H:i') : $txDate->format('H:i'),
                'duration' => $duration > 0 ? $duration . ' Menit' : '-',
            ];
        });

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'total' => $data->count()
        ]);
    }

    /**
     * 2. Penjualan Berdasarkan Item - Sales grouped by item
     */
    public function salesByItem(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->select(
                'transaction_items.type',
                'transaction_items.service_id',
                'transaction_items.product_id',
                DB::raw('SUM(transaction_items.quantity) as qty'),
                DB::raw('SUM(transaction_items.subtotal) as gross')
            )
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $salesByItem = $query->groupBy('transaction_items.type', 'transaction_items.service_id', 'transaction_items.product_id')->get();

        $data = $salesByItem->map(function ($item) {
            $name = '-';
            if ($item->type === 'service') {
                $service = Service::find($item->service_id);
                $name = $service->name ?? 'Unknown Service';
            } else {
                $product = Product::find($item->product_id);
                $name = $product->name ?? 'Unknown Product';
            }

            return [
                'id' => $item->type === 'service' ? $item->service_id : $item->product_id,
                'type' => ucfirst($item->type),
                'name' => $name,
                'sold' => intval($item->qty),
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 3. Detail Penjualan Berdasarkan Item - Detailed sales by item with staff breakdown
     * Columns: Tipe, Nama, Staff, Sold, Kotor, Diskon, Pengembalian, Nett, Tax, Diskon Penjualan, Penggunaan Voucher, Total Penjualan
     */
    public function salesByItemDetail(Request $request)
    {
        $branchId = $request->input('branch_id');
        $staffId = $request->input('staff_id');
        $dateFrom = $request->filled('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::now()->endOfMonth();

        // Main query: join items → transactions, aggregate by (type, service/product, therapist)
        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->select(
                'transaction_items.type',
                'transaction_items.service_id',
                'transaction_items.product_id',
                'transaction_items.therapist_id',
                DB::raw('SUM(transaction_items.quantity)                          AS qty'),
                DB::raw('SUM(transaction_items.subtotal)                          AS gross_sum'),
                // Proportional discount: item_subtotal / trx_subtotal * trx_discount (per row before GROUP)
                // We use a correlated approach: sum items, join transaction totals proportionally
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.discount
                    ELSE 0 END
                ) AS discount_sum'),
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.tax
                    ELSE 0 END
                ) AS tax_sum'),
                // Refund: credit_note amounts linked to this grouping (approximated via transactions refund field if exists)
                DB::raw('0 AS refund_sum'),
                DB::raw('0 AS voucher_sum')
            )
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        if ($staffId) {
            $query->where('transaction_items.therapist_id', $staffId);
        }

        $salesData = $query
            ->groupBy(
                'transaction_items.type',
                'transaction_items.service_id',
                'transaction_items.product_id',
                'transaction_items.therapist_id'
            )
            ->orderByDesc('gross_sum')
            ->get();

        // Pre-load related models to avoid N+1
        $serviceIds = $salesData->pluck('service_id')->filter()->unique();
        $productIds = $salesData->pluck('product_id')->filter()->unique();
        $therapistIds = $salesData->pluck('therapist_id')->filter()->unique();

        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $therapists = Therapist::whereIn('id', $therapistIds)->get()->keyBy('id');

        $data = $salesData->map(function ($item) use ($services, $products, $therapists) {
            $name = '-';
            if ($item->type === 'service') {
                $name = $services->get($item->service_id)?->name ?? 'Unknown Service';
            } else {
                $name = $products->get($item->product_id)?->name ?? 'Unknown Product';
            }

            $therapist = $therapists->get($item->therapist_id);

            $gross = floatval($item->gross_sum);
            $discount = floatval($item->discount_sum);
            $tax = floatval($item->tax_sum);
            $refund = floatval($item->refund_sum);
            $voucher = floatval($item->voucher_sum);
            // Nett = gross - item-level discount (before tax)
            $nett = $gross - $discount - $refund;
            $totalSales = $nett + $tax - $voucher;

            return [
                'id' => $item->type . '-' . ($item->service_id ?: $item->product_id) . '-' . $item->therapist_id,
                'type' => ucfirst($item->type),
                'name' => $name,
                'staffName' => $therapist?->name ?? 'Unassigned',
                'sold' => intval($item->qty),
                'gross' => round($gross, 2),
                'discount' => round($discount, 2),
                'refund' => round($refund, 2),
                'nett' => round($nett, 2),
                'tax' => round($tax, 2),
                'salesDiscount' => round($discount, 2),   // same as item-level discount here
                'voucherUsage' => round($voucher, 2),
                'totalSales' => round($totalSales, 2),
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => round($data->sum('gross'), 2),
            'discount' => round($data->sum('discount'), 2),
            'salesDiscount' => round($data->sum('salesDiscount'), 2),
            'refund' => round($data->sum('refund'), 2),
            'nett' => round($data->sum('nett'), 2),
            'tax' => round($data->sum('tax'), 2),
            'voucherUsage' => round($data->sum('voucherUsage'), 2),
            'totalSales' => round($data->sum('totalSales'), 2),
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary,
        ]);
    }

    /**
     * 4. Penjualan Berdasarkan Tipe - Sales by type (Service/Product/Class)
     */
    public function salesByType(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->select(
                'transaction_items.type',
                DB::raw('COUNT(*) as sold'),
                DB::raw('SUM(transaction_items.subtotal) as gross')
            )
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $salesData = $query->groupBy('transaction_items.type')->get();

        $types = ['service' => 'Service', 'product' => 'Product'];
        $data = collect($types)->map(function ($label, $type) use ($salesData) {
            $row = $salesData->firstWhere('type', $type);
            return [
                'id' => $type === 'service' ? 1 : 2,
                'type' => $label,
                'sold' => $row->sold ?? 0,
                'gross' => floatval($row->gross ?? 0),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($row->gross ?? 0),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($row->gross ?? 0)
            ];
        })->values();

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => $data->sum('discount'),
            'salesDiscount' => $data->sum('salesDiscount'),
            'refund' => $data->sum('refund'),
            'nett' => $data->sum('nett'),
            'tax' => $data->sum('tax'),
            'voucherUsage' => $data->sum('voucherUsage'),
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 5. Penjualan Berdasarkan Service - Sales by service category
     */
    /**
     * 5. Penjualan Berdasarkan Service - Sales by service (with group, variant, sold, and financial columns)
     * Columns: Tipe, Group, Nama, Nama Variant, Sold, Kotor, Diskon, Pengembalian, Nett, Tax, Diskon Penjualan, Penggunaan Voucher, Total Penjualan, Harga Modal, Profit
     */
    public function salesByService(Request $request)
    {
        $branchId = $request->input('branch_id');
        $staffId = $request->input('staff_id');
        $dateFrom = $request->filled('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereNotNull('transaction_items.service_id')
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo])
            ->select(
                'transaction_items.service_id',
                'transaction_items.variant_id',
                DB::raw('SUM(transaction_items.quantity)  AS sold'),
                DB::raw('SUM(transaction_items.subtotal)  AS gross_sum'),
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.discount
                    ELSE 0 END
                ) AS discount_sum'),
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.tax
                    ELSE 0 END
                ) AS tax_sum')
            );

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        if ($staffId) {
            $query->where('transaction_items.therapist_id', $staffId);
        }

        $salesData = $query
            ->groupBy('transaction_items.service_id', 'transaction_items.variant_id')
            ->orderByDesc('gross_sum')
            ->get();

        // Pre-load related models (avoid N+1)
        $serviceIds = $salesData->pluck('service_id')->filter()->unique();
        $variantIds = $salesData->pluck('variant_id')->filter()->unique();

        $services = Service::with('serviceCategory')->whereIn('id', $serviceIds)->get()->keyBy('id');
        $variants = \App\Models\ServiceVariant::whereIn('id', $variantIds)->get()->keyBy('id');

        $data = $salesData->values()->map(function ($item, $index) use ($services, $variants) {
            $service = $services->get($item->service_id);
            $variant = $variants->get($item->variant_id);
            $category = $service?->serviceCategory;

            $gross = floatval($item->gross_sum);
            $discount = floatval($item->discount_sum);
            $tax = floatval($item->tax_sum);
            $refund = 0.0;
            $voucher = 0.0;
            $hargaModal = floatval($variant?->capital_price ?? $service?->commission ?? 0);
            $nett = $gross - $discount - $refund;
            $totalSales = $nett + $tax - $voucher;
            $profit = $nett - $hargaModal;

            return [
                'id' => $index + 1,
                'tipe' => 'Service',
                'group' => $category?->name ?? '-',
                'name' => $service?->name ?? 'Unknown Service',
                'variantName' => $variant?->name ?? '-',
                'sold' => intval($item->sold),
                'kotor' => round($gross, 2),
                'discount' => round($discount, 2),
                'refund' => round($refund, 2),
                'nett' => round($nett, 2),
                'tax' => round($tax, 2),
                'salesDiscount' => round($discount, 2),
                'voucherUsage' => round($voucher, 2),
                'totalSales' => round($totalSales, 2),
                'hargaModal' => round($hargaModal, 2),
                'profit' => round($profit, 2),
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => round($data->sum('kotor'), 2),
            'discount' => round($data->sum('discount'), 2),
            'salesDiscount' => round($data->sum('salesDiscount'), 2),
            'refund' => round($data->sum('refund'), 2),
            'nett' => round($data->sum('nett'), 2),
            'tax' => round($data->sum('tax'), 2),
            'totalSales' => round($data->sum('totalSales'), 2),
            'hargaModal' => round($data->sum('hargaModal'), 2),
            'profit' => round($data->sum('profit'), 2),
            'voucherUsage' => round($data->sum('voucherUsage'), 2),
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary,
        ]);
    }


    /**
     * 6. Penjualan Berdasarkan Produk - Sales by product
     */
    public function salesByProduct(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->select(
                'transaction_items.product_id',
                'products.name',
                DB::raw('SUM(transaction_items.quantity) as qty'),
                DB::raw('SUM(transaction_items.subtotal) as gross')
            )
            ->where('transaction_items.type', 'product')
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $salesData = $query->groupBy('transaction_items.product_id', 'products.name')->get();

        $data = $salesData->map(function ($item, $index) {
            return [
                'id' => $index + 1,
                'name' => $item->name,
                'sold' => intval($item->qty),
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('nett')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 7. Penjualan Berdasarkan Lokasi - Sales by location/branch
     */
    public function salesByLocation(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = Transaction::with(['branch'])
            ->select(
                'branch_id',
                DB::raw('COUNT(*) as sold'),
                DB::raw('SUM(subtotal) as gross'),
                DB::raw('SUM(discount) as discount'),
                DB::raw('SUM(tax) as tax'),
                DB::raw('SUM(total) as total')
            )
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $salesData = $query->groupBy('branch_id')->get();

        $data = $salesData->map(function ($item, $index) {
            $branch = Branch::find($item->branch_id);

            return [
                'id' => $index + 1,
                'location' => $branch->name ?? 'Unknown',
                'sold' => intval($item->sold),
                'gross' => floatval($item->gross),
                'discount' => floatval($item->discount),
                'salesDiscount' => floatval($item->discount),
                'refund' => 0,
                'nett' => floatval($item->gross) - floatval($item->discount),
                'tax' => floatval($item->tax),
                'voucherUsage' => 0,
                'totalSales' => floatval($item->total)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => $data->sum('discount'),
            'salesDiscount' => $data->sum('salesDiscount'),
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => $data->sum('tax'),
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 8. Penjualan Berdasarkan Channel - Sales by channel (Online/Walk-in)
     */
    public function salesByChannel(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = Transaction::with(['booking'])
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $transactions = $query->get();

        $onlineStats = [
            'sold' => 0,
            'gross' => 0,
            'discount' => 0,
            'tax' => 0,
            'nett' => 0,
            'totalSales' => 0
        ];
        $walkInStats = [
            'sold' => 0,
            'gross' => 0,
            'discount' => 0,
            'tax' => 0,
            'nett' => 0,
            'totalSales' => 0
        ];

        foreach ($transactions as $trx) {
            // Determine channel
            $isOnline = false;
            // Check booking first
            if (in_array($trx->payment_method, ['virtual_account', 'transfer'])) {
                $isOnline = true;
            }

            if ($isOnline) {
                $stats =& $onlineStats;
            } else {
                $stats =& $walkInStats;
            }

            $stats['sold']++;
            $stats['gross'] += $trx->subtotal;
            $stats['discount'] += $trx->discount;
            $stats['tax'] += $trx->tax;
            $stats['nett'] += ($trx->subtotal - $trx->discount); // Nett before tax
            $stats['totalSales'] += $trx->total;
        }

        $data = collect([
            [
                'id' => 1,
                'channel' => 'ONLINE',
                'sold' => $onlineStats['sold'],
                'gross' => floatval($onlineStats['gross']),
                'discount' => floatval($onlineStats['discount']),
                'totalDiscount' => floatval($onlineStats['discount']),
                'refund' => 0,
                'nett' => floatval($onlineStats['nett']),
                'tax' => floatval($onlineStats['tax']),
                'voucherUsage' => 0,
                'totalSales' => floatval($onlineStats['totalSales'])
            ],
            [
                'id' => 2,
                'channel' => 'WALK-IN',
                'sold' => $walkInStats['sold'],
                'gross' => floatval($walkInStats['gross']),
                'discount' => floatval($walkInStats['discount']),
                'totalDiscount' => floatval($walkInStats['discount']),
                'refund' => 0,
                'nett' => floatval($walkInStats['nett']),
                'tax' => floatval($walkInStats['tax']),
                'voucherUsage' => 0,
                'totalSales' => floatval($walkInStats['totalSales'])
            ]
        ]);

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => $data->sum('discount'),
            'salesDiscount' => $data->sum('totalDiscount'), // Consistent naming
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => $data->sum('tax'),
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 9. Penjualan Berdasarkan Pelanggan - Sales by customer
     */
    public function salesByCustomer(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        // Join transactions with bookings to get user_id
        $query = Transaction::query()
            ->join('bookings', 'transactions.booking_id', '=', 'bookings.id')
            ->select(
                'bookings.user_id',
                DB::raw('COUNT(transactions.id) as sold'),
                DB::raw('SUM(transactions.total) as gross')
            )
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $salesData = $query->groupBy('bookings.user_id')->get();

        $data = $salesData->map(function ($item, $index) {
            $user = User::find($item->user_id);

            return [
                'id' => $index + 1,
                'email' => $user->email ?? $user->phone ?? 'Guest',
                'sold' => intval($item->sold),
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 10. Penjualan Berdasarkan Staff Terperinci - Detailed sales by staff
     */
    public function salesByStaffDetailed(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->select(
                'transaction_items.therapist_id',
                'transaction_items.service_id',
                DB::raw('SUM(transaction_items.quantity) as sold'),
                DB::raw('SUM(transaction_items.subtotal) as gross')
            )
            ->whereNotNull('transaction_items.therapist_id')
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $salesData = $query->groupBy('transaction_items.therapist_id', 'transaction_items.service_id')->get();

        $data = $salesData->map(function ($item, $index) {
            $therapist = Therapist::find($item->therapist_id);
            $service = Service::find($item->service_id);

            return [
                'id' => $index + 1,
                'staffName' => $therapist->name ?? 'Unassigned',
                'serviceName' => $service->name ?? ($item->service_id ? 'Unknown Service' : 'Product/Other'),
                'sold' => intval($item->sold),
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 11. Penjualan Berdasarkan Staff - Sales by staff (aggregated)
     */
    public function salesByStaff(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->select(
                'transaction_items.therapist_id',
                DB::raw('SUM(transaction_items.quantity) as sold'),
                DB::raw('SUM(transaction_items.subtotal) as gross')
            )
            ->whereNotNull('transaction_items.therapist_id')
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $salesData = $query->groupBy('transaction_items.therapist_id')->orderBy('gross', 'desc')->get();

        $data = $salesData->map(function ($item, $index) {
            $therapist = Therapist::find($item->therapist_id);

            return [
                'id' => $index + 1,
                'name' => $therapist->name ?? 'Unassigned',
                'sold' => intval($item->sold),
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 12. Penjualan Berdasarkan Jam - Sales by hour
     */
    public function salesByHour(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = Transaction::query()
            ->select(
                DB::raw('HOUR(transaction_date) as hour'),
                DB::raw('COUNT(*) as sold'),
                DB::raw('SUM(subtotal) as gross')
            )
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $salesData = $query->groupBy(DB::raw('HOUR(transaction_date)'))->orderBy('hour')->get();

        // Create 24 hour slots
        $hourlyData = collect(range(0, 23))->map(function ($hour) use ($salesData) {
            $data = $salesData->firstWhere('hour', $hour);
            return [
                'id' => $hour + 1,
                'hour' => sprintf('%02d:00', $hour),
                'sold' => $data ? $data->sold : 0,
                'gross' => $data ? floatval($data->gross) : 0,
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => $data ? floatval($data->gross) : 0,
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => $data ? floatval($data->gross) : 0
            ];
        });

        $summary = [
            'totalSold' => $hourlyData->sum('sold'),
            'gross' => $hourlyData->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $hourlyData->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $hourlyData->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $hourlyData,
            'summary' => $summary
        ]);
    }

    /**
     * 13. Penjualan Berdasarkan Jam Per Hari - Sales by hour per day
     */
    public function salesByHourPerDay(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = Transaction::query()
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('HOUR(transaction_date) as hour'),
                DB::raw('COUNT(*) as sold'),
                DB::raw('SUM(subtotal) as gross')
            )
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $salesData = $query->groupBy(DB::raw('DATE(transaction_date)'), DB::raw('HOUR(transaction_date)'))
            ->orderBy('date')
            ->orderBy('hour')
            ->get();

        $data = $salesData->map(function ($item, $index) {
            return [
                'id' => $index + 1,
                'date' => $item->date,
                'hour' => sprintf('%02d:00', $item->hour),
                'sold' => $item->sold,
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 14. Penjualan Per Hari - Daily sales
     */
    public function salesPerDay(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->subDays(7);
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now();

        $query = Transaction::query()
            ->select(
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('COUNT(*) as sold'),
                DB::raw('SUM(subtotal) as gross')
            )
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $salesData = $query->groupBy(DB::raw('DATE(transaction_date)'))->orderBy('date')->get();

        $data = $salesData->map(function ($item, $index) {
            return [
                'id' => $index + 1,
                'date' => $item->date,
                'sold' => $item->sold,
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 15. Penjualan Per Bulan - Monthly sales
     */
    public function salesPerMonth(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfYear();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfYear();

        $query = Transaction::query()
            ->select(
                DB::raw('YEAR(transaction_date) as year'),
                DB::raw('MONTH(transaction_date) as month'),
                DB::raw('COUNT(*) as sold'),
                DB::raw('SUM(subtotal) as gross')
            )
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $salesData = $query->groupBy(DB::raw('YEAR(transaction_date)'), DB::raw('MONTH(transaction_date)'))
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $data = $salesData->map(function ($item, $index) {
            $monthName = Carbon::createFromDate($item->year, $item->month, 1)->format('F Y');
            return [
                'id' => $index + 1,
                'month' => $monthName,
                'sold' => $item->sold,
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 16. Penjualan Per Kuartal - Quarterly sales
     */
    public function salesPerQuarter(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfYear();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfYear();

        $query = Transaction::query()
            ->select(
                DB::raw('YEAR(transaction_date) as year'),
                DB::raw('QUARTER(transaction_date) as quarter'),
                DB::raw('COUNT(*) as sold'),
                DB::raw('SUM(subtotal) as gross')
            )
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $salesData = $query->groupBy(DB::raw('YEAR(transaction_date)'), DB::raw('QUARTER(transaction_date)'))
            ->orderBy('year')
            ->orderBy('quarter')
            ->get();

        $data = $salesData->map(function ($item, $index) {
            return [
                'id' => $index + 1,
                'quarter' => 'Q' . $item->quarter . ' ' . $item->year,
                'sold' => $item->sold,
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 17. Pendapatan Per Tahun - Yearly revenue
     */
    public function revenuePerYear(Request $request)
    {
        $branchId = $request->input('branch_id');

        $query = Transaction::query()
            ->select(
                DB::raw('YEAR(transaction_date) as year'),
                DB::raw('COUNT(*) as sold'),
                DB::raw('SUM(subtotal) as gross')
            );

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $salesData = $query->groupBy(DB::raw('YEAR(transaction_date)'))
            ->orderBy('year', 'desc')
            ->get();

        $data = $salesData->map(function ($item, $index) {
            return [
                'id' => $index + 1,
                'year' => $item->year,
                'sold' => $item->sold,
                'gross' => floatval($item->gross),
                'discount' => 0,
                'salesDiscount' => 0,
                'refund' => 0,
                'nett' => floatval($item->gross),
                'tax' => 0,
                'voucherUsage' => 0,
                'totalSales' => floatval($item->gross)
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 18. Log Penjualan - Sales log
     */
    public function salesLog(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = Transaction::query()
        ->select(
            'transaction_ref',
            'subtotal as gross',
            'transaction_date'
        )
        ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

    if ($branchId) {
        $query->where('branch_id', $branchId);
    }

    $transactions = $query->orderBy('transaction_date', 'desc')->get();

    $data = $transactions->map(function ($tx, $index) {
        return [
            'id' => $index + 1,
            'name' => 'Order ' . $tx->transaction_ref,
            'qty' => 1,
            'gross' => floatval($tx->gross),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => floatval($tx->gross),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => floatval($tx->gross)
        ];
    });

        $summary = [
            'totalSold' => $data->count(),
            'gross' => $data->sum('gross'),
            'discount' => 0,
            'salesDiscount' => 0,
            'refund' => 0,
            'nett' => $data->sum('nett'),
            'tax' => 0,
            'voucherUsage' => 0,
            'totalSales' => $data->sum('totalSales')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * 19. Item Penjualan Berdasarkan Tanggal - Sales items by date
     */
    public function salesItemByDate(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = Booking::with(['service', 'user'])
            ->whereIn('status', ['completed', 'in_progress', 'confirmed'])
            ->whereBetween('booking_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $bookings = $query->orderBy('booking_date', 'desc')->get();

        $data = $bookings->map(function ($booking, $index) {
            return [
                'id' => $index + 1,
                'date' => Carbon::parse($booking->booking_date)->format('Y-m-d'),
                'serviceName' => $booking->service->name ?? 'Unknown',
                'customer' => $booking->user->name ?? 'Guest',
                'qty' => 1,
                'gross' => floatval($booking->service_price),
                'discount' => 0,
                'nett' => floatval($booking->service_price)
            ];
        });

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'total' => $data->count()
        ]);
    }

    /**
     * 20. Penjualan Berdasarkan Paket Layanan - Sales by service package
     */
    public function salesByServicePackage(Request $request)
    {
        // Similar to salesByService but for packages
        return $this->salesByService($request);
    }

    /**
     * 20b. Penjualan Berdasarkan Kategori Layanan - Sales by service category
     */
    public function salesByServiceCategory(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') 
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() 
            : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') 
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() 
            : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('services', 'transaction_items.service_id', '=', 'services.id')
            ->join('service_categories', 'services.service_category_id', '=', 'service_categories.id')
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo])
            ->select(
                'service_categories.id as category_id',
                'service_categories.name as category_name',
                DB::raw('SUM(transaction_items.quantity) AS sold'),
                DB::raw('SUM(transaction_items.subtotal) AS gross_sum'),
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.discount
                    ELSE 0 END
                ) AS discount_sum'),
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.tax
                    ELSE 0 END
                ) AS tax_sum')
            );

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $salesData = $query->groupBy('service_categories.id', 'service_categories.name')
            ->orderByDesc('gross_sum')
            ->get();

        $data = $salesData->map(function ($item, $index) {
            $gross = floatval($item->gross_sum);
            $discount = floatval($item->discount_sum);
            $tax = floatval($item->tax_sum);
            $refund = 0.0;
            $voucher = 0.0;
            $nett = $gross - $discount - $refund;
            $totalSales = $nett + $tax - $voucher;

            return [
                'id' => $item->category_id,
                'categoryName' => $item->category_name,
                'sold' => intval($item->sold),
                'gross' => round($gross, 2),
                'discount' => round($discount, 2),
                'refund' => round($refund, 2),
                'nett' => round($nett, 2),
                'tax' => round($tax, 2),
                'salesDiscount' => round($discount, 2),
                'voucherUsage' => round($voucher, 2),
                'totalSales' => round($totalSales, 2),
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => round($data->sum('gross'), 2),
            'discount' => round($data->sum('discount'), 2),
            'salesDiscount' => round($data->sum('salesDiscount'), 2),
            'refund' => round($data->sum('refund'), 2),
            'nett' => round($data->sum('nett'), 2),
            'tax' => round($data->sum('tax'), 2),
            'voucherUsage' => round($data->sum('voucherUsage'), 2),
            'totalSales' => round($data->sum('totalSales'), 2),
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary,
        ]);
    }

    /**
     * 21. Penjualan Berdasarkan Varian Layanan - Sales by service variant
     */
    public function salesByServiceVariant(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') 
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() 
            : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') 
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() 
            : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('services', 'transaction_items.service_id', '=', 'services.id')
            ->leftJoin('service_variants', 'transaction_items.variant_id', '=', 'service_variants.id')
            ->where('transaction_items.type', 'service')
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo])
            ->select(
                'transaction_items.service_id',
                'transaction_items.variant_id',
                'services.name as service_name',
                'service_variants.name as variant_name',
                'service_variants.duration as variant_duration',
                DB::raw('SUM(transaction_items.quantity) AS sold'),
                DB::raw('SUM(transaction_items.subtotal) AS gross_sum'),
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.discount
                    ELSE 0 END
                ) AS discount_sum'),
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.tax
                    ELSE 0 END
                ) AS tax_sum')
            );

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $salesData = $query->groupBy(
                'transaction_items.service_id', 
                'transaction_items.variant_id',
                'services.name',
                'service_variants.name',
                'service_variants.duration'
            )
            ->orderByDesc('gross_sum')
            ->get();

        $data = $salesData->map(function ($item, $index) {
            $gross = floatval($item->gross_sum);
            $discount = floatval($item->discount_sum);
            $tax = floatval($item->tax_sum);
            $refund = 0.0;
            $voucher = 0.0;
            $nett = $gross - $discount - $refund;
            $totalSales = $nett + $tax - $voucher;

            $variantName = $item->variant_name 
                ? $item->variant_name 
                : ($item->variant_duration ? "{$item->variant_duration} Menit" : "-");

            return [
                'id' => $index + 1,
                'name' => $item->service_name,
                'variant' => $variantName,
                'sold' => intval($item->sold),
                'gross' => round($gross, 2),
                'discount' => round($discount, 2),
                'refund' => round($refund, 2),
                'nett' => round($nett, 2),
                'tax' => round($tax, 2),
                'salesDiscount' => round($discount, 2),
                'voucherUsage' => round($voucher, 2),
                'totalSales' => round($totalSales, 2),
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => round($data->sum('gross'), 2),
            'discount' => round($data->sum('discount'), 2),
            'salesDiscount' => round($data->sum('salesDiscount'), 2),
            'refund' => round($data->sum('refund'), 2),
            'nett' => round($data->sum('nett'), 2),
            'tax' => round($data->sum('tax'), 2),
            'voucherUsage' => round($data->sum('voucherUsage'), 2),
            'totalSales' => round($data->sum('totalSales'), 2),
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary,
        ]);
    }

    /**
     * 22. Penjualan Berdasarkan Varian Produk - Sales by product variant
     */
    public function salesByProductVariant(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') 
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay() 
            : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') 
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay() 
            : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('product_variants', 'transaction_items.variant_id', '=', 'product_variants.id')
            ->where('transaction_items.type', 'product')
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo])
            ->select(
                'transaction_items.product_id',
                'transaction_items.variant_id',
                'products.name as product_name',
                'product_variants.name as variant_name',
                DB::raw('SUM(transaction_items.quantity) AS sold'),
                DB::raw('SUM(transaction_items.subtotal) AS gross_sum'),
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.discount
                    ELSE 0 END
                ) AS discount_sum'),
                DB::raw('SUM(
                    CASE WHEN transactions.subtotal > 0
                    THEN (transaction_items.subtotal / transactions.subtotal) * transactions.tax
                    ELSE 0 END
                ) AS tax_sum')
            );

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $salesData = $query->groupBy(
                'transaction_items.product_id', 
                'transaction_items.variant_id',
                'products.name',
                'product_variants.name'
            )
            ->orderByDesc('gross_sum')
            ->get();

        $data = $salesData->map(function ($item, $index) {
            $gross = floatval($item->gross_sum);
            $discount = floatval($item->discount_sum);
            $tax = floatval($item->tax_sum);
            $refund = 0.0;
            $voucher = 0.0;
            $nett = $gross - $discount - $refund;
            $totalSales = $nett + $tax - $voucher;

            return [
                'id' => $index + 1,
                'productName' => $item->product_name,
                'variant' => $item->variant_name ?: "-",
                'sold' => intval($item->sold),
                'gross' => round($gross, 2),
                'discount' => round($discount, 2),
                'refund' => round($refund, 2),
                'nett' => round($nett, 2),
                'tax' => round($tax, 2),
                'salesDiscount' => round($discount, 2),
                'voucherUsage' => round($voucher, 2),
                'totalSales' => round($totalSales, 2),
            ];
        });

        $summary = [
            'totalSold' => $data->sum('sold'),
            'gross' => round($data->sum('gross'), 2),
            'discount' => round($data->sum('discount'), 2),
            'salesDiscount' => round($data->sum('salesDiscount'), 2),
            'refund' => round($data->sum('refund'), 2),
            'nett' => round($data->sum('nett'), 2),
            'tax' => round($data->sum('tax'), 2),
            'voucherUsage' => round($data->sum('voucherUsage'), 2),
            'totalSales' => round($data->sum('totalSales'), 2),
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary,
        ]);
    }

    /**
     * 23. Penjualan Refund - Refunded sales
     */
    public function salesRefund(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = Booking::with(['user', 'branch', 'transaction', 'service', 'canceller'])
            ->where('status', 'cancelled')
            ->where('refund_amount', '>', 0)
            ->whereBetween('cancelled_at', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $refunds = $query->orderBy('cancelled_at', 'desc')->get();

        $data = $refunds->map(function ($booking, $index) {
            return [
                'id' => $index + 1,
                'invoiceNo' => $booking->transaction->transaction_ref ?? $booking->booking_ref,
                'date' => Carbon::parse($booking->cancelled_at)->format('d M Y'),
                'amount' => floatval($booking->refund_amount),
                'costPrice' => floatval($booking->service->capital_price ?? 0),
                'customer' => $booking->customer_name,
                'location' => $booking->branch->name ?? '-',
                'source' => $booking->transaction ? ($booking->transaction->type === 'booking' ? 'ONLINE' : 'POS') : 'ONLINE',
                'changedOn' => Carbon::parse($booking->cancelled_at)->format('d M Y H:i'),
                'changedBy' => $booking->canceller->name ?? 'Admin'
            ];
        });

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'total' => $data->count(),
            'totalAmount' => round($data->sum('amount'), 2)
        ]);
    }

    /**
     * 24. Penjualan Dibatalkan - Cancelled sales
     */
    public function salesCancelled(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfMonth();

        $query = Booking::with(['user', 'branch', 'service', 'transaction'])
            ->where('status', 'cancelled')
            ->whereBetween('cancelled_at', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $cancelled = $query->orderBy('cancelled_at', 'desc')->get();

        $data = $cancelled->map(function ($booking, $index) {
            return [
                'id' => $index + 1,
                'invoiceNo' => $booking->transaction->transaction_ref ?? $booking->booking_ref,
                'date' => Carbon::parse($booking->booking_date)->format('d M Y'),
                'serviceName' => $booking->service->name ?? ($booking->is_blocked ? 'Time Block' : 'Unknown'),
                'amount' => floatval($booking->total_price),
                'customer' => $booking->customer_name,
                'location' => $booking->branch->name ?? '-',
                'reason' => $booking->cancellation_reason ?? ($booking->is_blocked ? ($booking->block_reason ?? 'Time Block') : 'Dibatalkan'),
                'cancelledAt' => Carbon::parse($booking->cancelled_at)->format('d M Y H:i'),
            ];
        });

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'total' => $data->count(),
            'totalAmount' => round($data->sum('amount'), 2)
        ]);
    }


    /**
     * 25. Penjualan Berdasarkan Layanan - Sales by service detailed
     */
    public function salesByServiceDetailed(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->filled('date_from') 
            ? Carbon::parse($request->input('date_from'))->startOfDay() 
            : Carbon::now()->startOfMonth();
        $dateTo = $request->filled('date_to') 
            ? Carbon::parse($request->input('date_to'))->endOfDay() 
            : Carbon::now()->endOfMonth();

        $query = TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->leftJoin('bookings', 'transactions.booking_id', '=', 'bookings.id')
            ->leftJoin('services', 'transaction_items.service_id', '=', 'services.id')
            ->leftJoin('service_variants', 'transaction_items.variant_id', '=', 'service_variants.id')
            ->leftJoin('users as cashier', 'transactions.cashier_id', '=', 'cashier.id')
            ->leftJoin('therapists', 'transaction_items.therapist_id', '=', 'therapists.id')
            ->leftJoin('users as customer', 'bookings.user_id', '=', 'customer.id')
            ->where('transaction_items.type', 'service')
            ->whereBetween('transactions.transaction_date', [$dateFrom, $dateTo]);

        if ($branchId) {
            $query->where('transactions.branch_id', $branchId);
        }

        $items = $query->select(
            'transaction_items.*',
            'transactions.id as trx_id',
            'transactions.transaction_ref',
            'transactions.type as trx_type',
            'transactions.transaction_date',
            'transactions.discount as total_discount',
            'transactions.tax as total_tax',
            'transactions.subtotal as total_subtotal',
            'bookings.id as booking_id',
            'bookings.booking_ref',
            'bookings.promo_code',
            'bookings.discount_amount as voucher_amount',
            'bookings.guest_name',
            'services.name as service_name',
            'service_variants.name as variant_name',
            'cashier.name as cashier_name',
            'therapists.name as therapist_name',
            'customer.name as user_customer_name'
        )->orderBy('transactions.transaction_date', 'desc')->get();

        $data = $items->map(function ($item, $index) {
            $subtotalFactor = $item->total_subtotal > 0 ? ($item->subtotal / $item->total_subtotal) : 0;
            $itemDiscount = (float)$item->total_discount * $subtotalFactor;
            $itemTax = (float)$item->total_tax * $subtotalFactor;
            $itemVoucher = (float)($item->voucher_amount ?? 0) * $subtotalFactor;

            $resourceName = $item->service_name;
            if ($item->variant_name) {
                $resourceName .= ' (' . $item->variant_name . ')';
            }

            return [
                'id' => $index + 1,
                'noReceipt' => $item->transaction_ref,
                'noFaktur' => $item->booking_ref ?? $item->transaction_ref,
                'date' => Carbon::parse($item->transaction_date)->format('d M Y'),
                'resource' => $resourceName ?? 'Unknown',
                'soldBy' => $item->cashier_name ?? '-',
                'servedBy' => $item->therapist_name ?? '-',
                'source' => $item->trx_type === 'booking' ? 'ONLINE' : 'POS',
                'type' => $item->trx_type,
                'customer' => $item->user_customer_name ?: ($item->guest_name ?: 'Guest'),
                'qty' => $item->quantity,
                'price' => (float)$item->price,
                'subtotal' => (float)$item->subtotal,
                'discount' => round($itemDiscount, 2),
                'tax' => round($itemTax, 2),
                'voucher' => round($itemVoucher, 2),
                'total' => round((float)$item->subtotal - $itemDiscount + $itemTax - $itemVoucher, 2)
            ];
        });

        $summary = [
            'totalCount' => $data->count(),
            'totalQty' => $data->sum('qty'),
            'totalSubtotal' => round($data->sum('subtotal'), 2),
            'totalDiscount' => round($data->sum('discount'), 2),
            'totalTax' => round($data->sum('tax'), 2),
            'totalVoucher' => round($data->sum('voucher'), 2),
            'totalOrder' => $items->unique('trx_id')->count(),
            'totalSales' => round($data->sum('total'), 2)
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'data' => $data,
            'summary' => $summary
        ]);
    }
}
