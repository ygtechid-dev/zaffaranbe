<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Therapist;
use App\Models\Service;
use App\Models\CashierShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function revenue(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = Transaction::select(
            DB::raw('DATE(transaction_date) as date'),
            DB::raw('SUM(total) as total_revenue'),
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('SUM(CASE WHEN payment_method = "cash" THEN total ELSE 0 END) as cash_revenue'),
            DB::raw('SUM(CASE WHEN payment_method = "qris" THEN total ELSE 0 END) as qris_revenue'),
            DB::raw('SUM(CASE WHEN payment_method = "virtual_account" THEN total ELSE 0 END) as va_revenue')
        );

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $revenue = $query->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $summary = [
            'total_revenue' => $revenue->sum('total_revenue'),
            'total_transactions' => $revenue->sum('total_transactions'),
            'cash_revenue' => $revenue->sum('cash_revenue'),
            'qris_revenue' => $revenue->sum('qris_revenue'),
            'va_revenue' => $revenue->sum('va_revenue'),
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => $summary,
            'daily_revenue' => $revenue,
        ]);
    }

    public function profitLoss(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = Transaction::query();

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $totalRevenue = $query->clone()
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->sum('total');

        $totalDiscount = $query->clone()
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->sum('discount');

        // TODO: Calculate actual costs (COGS, operational, etc.)
        // For now, just return revenue
        $grossProfit = $totalRevenue - $totalDiscount;
        $netProfit = $grossProfit; // Placeholder

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_revenue' => $totalRevenue,
            'total_discount' => $totalDiscount,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
        ]);
    }

    public function bookings(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = Booking::query();

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $bookings = $query->clone()
            ->whereBetween('booking_date', [$dateFrom, $dateTo])
            ->select(
                DB::raw('status'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_price) as total_value')
            )
            ->groupBy('status')
            ->get();

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'by_status' => $bookings,
            'total_bookings' => $bookings->sum('count'),
            'total_value' => $bookings->sum('total_value'),
        ]);
    }

    public function therapistPerformance(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = Booking::with('therapist:id,name')
            ->select(
                'therapist_id',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('AVG(total_price) as avg_booking_value')
            );

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $performance = $query->whereBetween('booking_date', [$dateFrom, $dateTo])
            ->whereIn('status', ['completed', 'in_progress', 'confirmed'])
            ->groupBy('therapist_id')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'therapist_performance' => $performance,
        ]);
    }

    public function popularServices(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = Booking::with('service:id,name,category')
            ->select(
                'service_id',
                DB::raw('COUNT(*) as total_bookings'),
                DB::raw('SUM(service_price) as total_revenue')
            );

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $services = $query->whereBetween('booking_date', [$dateFrom, $dateTo])
            ->whereIn('status', ['completed', 'in_progress', 'confirmed'])
            ->groupBy('service_id')
            ->orderBy('total_bookings', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'popular_services' => $services,
        ]);
    }
    public function financialSummary(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $usePaymentDate = filter_var($request->input('use_payment_date', true), FILTER_VALIDATE_BOOLEAN);

        // 1. Sales Data
        $salesQuery = Transaction::query();
        if ($branchId && $branchId !== 'all') {
            $salesQuery->where('branch_id', $branchId);
        }
        $salesData = $salesQuery->where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo)
            ->select(
                DB::raw('SUM(total) as penjualan_kotor'),
                DB::raw('SUM(discount) as diskon'),
                DB::raw('SUM(CASE WHEN type = "refund" THEN total ELSE 0 END) as pengembalian'), // Assuming 'refund' type exists or adjust logic
                DB::raw('SUM(tax) as total_pajak'),
                DB::raw('SUM(total) as total_penjualan') // Net sales basically
            )->first();

        // 2. Payments Data
        $paymentsQuery = Transaction::query();
        if ($branchId && $branchId !== 'all') {
            $paymentsQuery->where('branch_id', $branchId);
        }
        $payments = $paymentsQuery->where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo)
            ->select(
                'payment_method as type',
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(change_amount) as kembalian'),
                DB::raw('SUM(cash_received - change_amount) as jumlah_bersih'), // Simplified logic
                DB::raw('0 as outstanding') // Placeholder
            )
            ->groupBy('payment_method')
            ->get();

        // 3. Tips Data
        $tipsQuery = \App\Models\StaffTip::with(['staff', 'transaction.booking.user'])->where(DB::raw('DATE(date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(date)'), '<=', $dateTo);
        if ($branchId && $branchId !== 'all') {
            $tipsQuery->where('branch_id', $branchId);
        }

        $tipsData = $tipsQuery->get();
        $tips = $tipsData->map(function ($t) {
            $customerName = 'Guest';
            if ($t->transaction && $t->transaction->booking && $t->transaction->booking->user) {
                $customerName = $t->transaction->booking->user->name;
            } elseif ($t->transaction && $t->transaction->booking && $t->transaction->booking->guest_name) {
                $customerName = $t->transaction->booking->guest_name;
            }

            return [
                'id' => $t->id,
                'staff' => $t->staff ? $t->staff->name : '-',
                'tanggal' => Carbon::parse($t->date)->format('d M Y'),
                'customer' => $customerName,
                'jumlah' => (float) $t->amount_collected
            ];
        })->values()->toArray();

        // 4. Vouchers Data
        $bookingIdsInPeriod = null;
        if ($usePaymentDate) {
            $trxFilter = Transaction::where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
                ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo);
            if ($branchId && $branchId !== 'all')
                $trxFilter->where('branch_id', $branchId);
            $bookingIdsInPeriod = $trxFilter->pluck('booking_id')->unique()->filter()->toArray();
        }

        $vouchersQuery = \App\Models\Booking::whereNotNull('promo_code')
            ->where('discount_amount', '>', 0)
            ->whereNotIn('status', ['cancelled']);

        if ($branchId && $branchId !== 'all') {
            $vouchersQuery->where('branch_id', $branchId);
        }

        if ($usePaymentDate) {
            if (!empty($bookingIdsInPeriod)) {
                $vouchersQuery->whereIn('id', $bookingIdsInPeriod);
            } else {
                $vouchersQuery->whereRaw('1 = 0');
            }
        } else {
            $vouchersQuery->where(DB::raw('DATE(booking_date)'), '>=', $dateFrom)
                ->where(DB::raw('DATE(booking_date)'), '<=', $dateTo);
        }

        $bookingsForVouchers = $vouchersQuery->get();
        $totalPenggunaanVoucher = $bookingsForVouchers->sum('discount_amount');

        $vouchers = $bookingsForVouchers->map(function ($booking) {
            $promo = \App\Models\Promo::where('code', $booking->promo_code)->first();
            return [
                'id' => $booking->id,
                'kode' => $booking->promo_code ?? '-',
                'nama' => $promo ? $promo->title : 'Voucher/Promo',
                'tanggalDigunakan' => Carbon::parse($booking->created_at)->format('Y-m-d H:i:s'),
                'nilai' => (float) $booking->discount_amount,
                'status' => 'Digunakan'
            ];
        })->values()->toArray();

        // Fix overall discount values to include voucher values
        $baseDiskon = $salesData->diskon ?? 0;
        $totalDiskonGabungan = $baseDiskon + $totalPenggunaanVoucher;

        // 5. Payment Invoice Other Period Data
        $otherPeriodQuery = Transaction::with('booking')->where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo);
        if ($branchId && $branchId !== 'all') {
            $otherPeriodQuery->where('branch_id', $branchId);
        }

        $otherPeriodTransactions = $otherPeriodQuery->get()->filter(function ($trx) use ($dateFrom, $dateTo) {
            if (!$trx->booking)
                return false;
            $bookingDate = Carbon::parse($trx->booking->booking_date)->format('Y-m-d');
            // Jika tanggal faktur (booking) berada di luar rentang tanggal waktu saat bayar
            return $bookingDate < $dateFrom || $bookingDate > $dateTo;
        });

        $otherPeriodPayments = $otherPeriodTransactions->map(function ($trx) {
            return [
                'id' => $trx->id,
                'booking_id' => $trx->booking_id,
                'tanggalFaktur' => Carbon::parse($trx->booking->booking_date)->format('d M Y'),
                'nomorFaktur' => $trx->booking->booking_ref ?? ('BK-' . $trx->booking->id),
                'tanggalPembayaran' => Carbon::parse($trx->transaction_date)->format('d M Y'),
                'jumlah' => (float) $trx->total
            ];
        })->values()->toArray();

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'sales' => [
                'penjualanKotor' => ($salesData->penjualan_kotor ?? 0) + $totalPenggunaanVoucher,
                'diskon' => $baseDiskon,
                'diskonTotalPenjualan' => $totalDiskonGabungan,
                'pengembalian' => $salesData->pengembalian ?? 0,
                'penjualanBersih' => ($salesData->penjualan_kotor ?? 0) - ($salesData->diskon ?? 0) - $totalPenggunaanVoucher,
                'totalPajak' => $salesData->total_pajak ?? 0,
                'totalPembulatan' => 0,
                'penggunaanVoucher' => $totalPenggunaanVoucher,
                'totalPenjualan' => $salesData->total_penjualan ?? 0
            ],
            'payments' => $payments,
            'other_period_payments' => $otherPeriodPayments,
            'tips' => $tips,
            'vouchers' => $vouchers
        ]);
    }


    public function taxSummary(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = Transaction::query()
            ->with([
                'branch:id,name',
                'booking.user:id,name',
                'booking.service:id,name'
            ]);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $transactions = $query->where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo)
            ->where('tax', '>', 0)
            ->orderBy('transaction_date', 'desc')
            ->get()
            ->map(function ($transaction) {
                // Calculate rate roughly if not stored
                $rate = '0%';
                if ($transaction->subtotal > 0 && $transaction->tax > 0) {
                    $percentage = ($transaction->tax / $transaction->subtotal) * 100;
                    $rate = round($percentage, 0) . '%';
                }

                $customerName = $transaction->booking ? ($transaction->booking->user->name ?? 'Guest') : 'Unknown';
                $serviceName = $transaction->booking ? ($transaction->booking->service->name ?? 'Service') : 'General Sales';

                return [
                    'id' => $transaction->id,
                    'nama' => $customerName,
                    'itemPenjualan' => $serviceName,
                    'rate' => $rate,
                    'lokasi' => $transaction->branch->name ?? '-',
                    'jumlah' => $transaction->tax
                ];
            });

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'taxes' => $transactions,
            'total_tax' => $transactions->sum('jumlah')
        ]);
    }

    public function paymentSummary(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = Transaction::with('booking')->where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $allTransactions = $query->get();

        $groupedByMethod = $allTransactions->groupBy('payment_method');

        $details = $groupedByMethod->map(function ($group, $method) {
            $totalTx = $group->count();

            // To prevent duplicated voucher discounts on the same booking split into multiple trxs,
            // we will simply accumulate total + discount individually or keep it simple
            // But let's assume each transaction's discount should be counted by fetching unique bookings or allocating.
            // As a simplified safe metric, we just sum up the booking's discount amounts uniquely represented in this group.
            // actually if they pay split payment, then the voucher is applied to the booking, so we can attribute it proportionally.
            // Let's attribute the discount entirely to this specific transaction directly since transaction itself doesn't duplicate the discount.

            $gross = 0;
            $refunds = 0;
            $voucher = 0;
            $kembalian = 0;

            foreach ($group as $trx) {
                // Determine refunds if it exists
                if ($trx->type === 'refund') {
                    $refunds += $trx->total; // assuming refund value is tracked here
                } else {
                    $voucherAmount = 0;
                    if ($trx->booking && $trx->booking->discount_amount) {
                        // Attribute voucher proportionally if needed, but realistically it's a 1-to-N. For now sum it.
                        // But if same booking has 2 transactions, it duplicates voucher. 
                        // To be perfectly safe, just fetch unique bookings in this group:
                        $voucherAmount = $trx->booking->discount_amount;
                    }

                    // A trick to prevent duplicating booking vouchers within the same method group
                    // Instead of simple loop, let's just do an array map for unique bookings.
                }

                $gross += $trx->total;
                $kembalian += $trx->change_amount ?? 0;
            }

            // Extract unique bookings in this payment method group to sum voucher specifically
            $uniqueBookings = $group->pluck('booking')->filter()->unique('id');
            $voucherTotal = $uniqueBookings->sum('discount_amount');

            // Re-adjust gross to include voucher to match frontend expectation where Gross - Refunds - Voucher - Kembalian = Net
            $gross += $voucherTotal;

            $net = $gross - $refunds - $voucherTotal - $kembalian;

            return [
                'name' => ucwords(str_replace('_', ' ', $method)),
                'totalTx' => $totalTx,
                'gross' => $gross,
                'refunds' => $refunds,
                'voucher' => $voucherTotal,
                'kembalian' => $kembalian,
                'net' => $net
            ];
        })->values()->map(function ($item, $index) {
            $item['id'] = $index + 1;
            return $item;
        });

        // Calculate summary
        $summary = [
            'totalTransaksi' => $details->sum('totalTx'),
            'pendapatanKotor' => $details->sum('gross'),
            'totalPengembalian' => $details->sum('refunds'),
            'penggunaanVoucher' => $details->sum('voucher'),
            'kembalian' => $details->sum('kembalian'),
            'totalPembayaranNet' => $details->sum('net')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'details' => $details,
            'summary' => $summary
        ]);
    }

    public function paymentLog(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());
        $staffName = $request->input('staff');
        $method = $request->input('method');

        $query = Transaction::query()
            ->with(['branch', 'booking.user', 'cashier']);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        if ($method && $method !== 'all') {
            // Note: DB values might be 'virtual_account', 'cash', so we might need formatting 
            // but if $method comes from frontend, we trust it matches or adjust it.
            // Wait, uniqueMethods returns formatted versions, so let's accommodate.
            // Let's filter post-query or format correctly. Usually 'filter' via Collection if exact string doesn't match
        }

        if ($request->input('use_payment_date') == 'true' || $request->input('use_payment_date') === true) {
            $query->where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
                ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo);
        } else {
            $query->where(DB::raw('DATE(created_at)'), '>=', $dateFrom)
                ->where(DB::raw('DATE(created_at)'), '<=', $dateTo);
        }

        $allTransactions = $query->orderBy('transaction_date', 'desc')
            ->get();

        $mappedLogs = $allTransactions->map(function ($t) {
            $staffName = $t->cashier ? $t->cashier->name : '-';
            $customerName = 'Guest';
            if ($t->booking && $t->booking->user) {
                $customerName = $t->booking->user->name;
            } elseif ($t->booking && $t->booking->guest_name) {
                $customerName = $t->booking->guest_name;
            }

            // Construct ID (Faktur) logic if not exist
            $fakturId = $t->transaction_ref ?? ('TRX-' . $t->id);
            $voucherAmount = $t->booking ? ($t->booking->discount_amount ?? 0) : 0;
            $kembalian = $t->change_amount ?? 0;
            $gross = $t->total + $voucherAmount;
            $net = $t->total; // assuming total is after discount
            $discount = 0; // standard product discount (not voucher) if we had that separation. Let's make it 0 for now.

            return [
                'id' => $fakturId,
                'staff' => $staffName,
                'type' => 'Sales', // Defaulting to Sales
                'amount' => $gross,
                'change' => $kembalian,
                'net' => $net,
                'voucher' => $voucherAmount,
                'discount' => $discount,
                'receivedBy' => $staffName,
                'method' => ucwords(str_replace('_', ' ', $t->payment_method)),
                'referenceNo' => $t->id, // or payment_ref if it existed
                'paymentDate' => Carbon::parse($t->transaction_date)->format('d M Y'),
                'hargaMarkup' => 0,
                'location' => $t->branch->name ?? '-',
                'customer' => $customerName
            ];
        });

        if ($method && $method !== 'all') {
            $mappedLogs = $mappedLogs->where('method', $method)->values();
        }

        if ($staffName && $staffName !== 'all') {
            $mappedLogs = $mappedLogs->where('staff', $staffName)->values();
        }

        // Calculate summary
        $summary = [
            'jumlahTotal' => $mappedLogs->sum('amount'), // Gross
            'totalDiskon' => $mappedLogs->sum('discount'),
            'penggunaanVoucher' => $mappedLogs->sum('voucher'),
            'jumlahPerubahan' => $mappedLogs->sum('change'),
            'jumlahTip' => 0,
            'jumlahBersih' => $mappedLogs->sum('net')
        ];

        // Get unique dropdown options (before filter post-query so options don't vanish)
        $uniqueStaff = $allTransactions->map(function ($t) {
            return $t->cashier ? $t->cashier->name : '-';
        })->unique()->filter(function ($s) {
            return $s !== '-';
        })->values();

        $uniqueMethods = $allTransactions->pluck('payment_method')->map(function ($m) {
            return ucwords(str_replace('_', ' ', $m));
        })->unique()->values();

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'logs' => $mappedLogs,
            'summary' => $summary,
            'filters' => [
                'staff' => $uniqueStaff,
                'methods' => $uniqueMethods
            ]
        ]);
    }


    public function tipsSummary(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());
        $staffName = $request->input('staff');

        $query = Transaction::query()
            ->with(['branch', 'booking.staff']);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Filter by staff if specific staff selected
        if ($staffName && $staffName !== 'all') {
            $query->whereHas('booking.staff', function ($q) use ($staffName) {
                $q->where('name', $staffName);
            });
        }

        $transactions = $query->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->orderBy('transaction_date', 'desc')
            ->get()
            ->map(function ($t) {
                // Ensure we have a staff name. If booking->staff is null, use 'Unassigned'
                $staffName = $t->booking && $t->booking->staff ? $t->booking->staff->name : 'Unassigned';

                return [
                    'id' => $t->id,
                    'namaStaff' => $staffName,
                    'lokasi' => $t->branch->name ?? '-',
                    'tanggalFaktur' => Carbon::parse($t->transaction_date)->format('d M Y'),
                    // Placeholder for actual tip column. 
                    'jumlah' => 0
                ];
            });

        // Calculate summary
        $totalTips = $transactions->sum('jumlah');

        // Unique staff for filter
        $uniqueStaff = $transactions->pluck('namaStaff')->unique()->values();

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'tips' => $transactions,
            'total_tips' => $totalTips,
            'filters' => [
                'staff' => $uniqueStaff
            ]
        ]);
    }


    public function discountSummary(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = Transaction::query()->with('booking');

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        $transactions = $query->where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo)
            ->get();

        $discountsData = [];

        // Track unique bookings to prevent duplicate voucher application if they split payment
        $processedBookings = [];

        foreach ($transactions as $t) {
            $promoName = 'General Discount';
            $discountAmount = $t->discount > 0 ? $t->discount : 0;

            if ($t->booking && $t->booking->discount_amount > 0) {
                // If we already counted this booking's discount in another transaction part, skip its voucher portion
                if (in_array($t->booking->id, $processedBookings)) {
                    continue; // Skip counting the voucher again
                }
                $processedBookings[] = $t->booking->id;

                $discountAmount = $t->booking->discount_amount;
                $promoCode = $t->booking->promo_code;
                if ($promoCode) {
                    $promo = \App\Models\Promo::where('code', $promoCode)->first();
                    $promoName = $promo ? $promo->title : "Promo ({$promoCode})";
                }
            }

            if ($discountAmount > 0) {
                if (!isset($discountsData[$promoName])) {
                    $discountsData[$promoName] = [
                        'itemDisc' => 0,
                        'itemValue' => 0,
                        'amount' => 0,
                        'refund' => 0,
                        'net' => 0
                    ];
                }

                $discountsData[$promoName]['itemDisc'] += 1;
                $discountsData[$promoName]['itemValue'] += $t->subtotal;
                $discountsData[$promoName]['amount'] += $discountAmount;
                $discountsData[$promoName]['net'] += $discountAmount;
            }
        }

        $discounts = collect();
        $id = 1;
        foreach ($discountsData as $name => $data) {
            $discounts->push([
                'id' => $id++,
                'name' => $name,
                'itemDisc' => $data['itemDisc'],
                'itemValue' => $data['itemValue'],
                'amount' => $data['amount'],
                'refund' => $data['refund'],
                'net' => $data['net']
            ]);
        }

        $totalNet = $discounts->sum('net');

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'discounts' => $discounts,
            'total_net' => $totalNet
        ]);
    }

    public function unsettledInvoices(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = Booking::query()
            ->with(['branch', 'user', 'payments'])
            ->where('total_price', '>', 0)
            ->whereNotIn('payment_status', ['paid', 'refunded', 'cancelled'])
            ->whereNotIn('status', ['cancelled']); // Ensure booking itself isn't cancelled

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Filter by date range (Invoice Date)
        $query->where(DB::raw('DATE(booking_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(booking_date)'), '<=', $dateTo);

        $invoices = $query->orderBy('booking_date', 'asc')
            ->get()
            ->map(function ($booking) {
                $paidAmount = $booking->payments->where('status', 'success')->sum('amount');
                $dueAmount = $booking->total_price - $paidAmount;
                $grossPrice = $booking->total_price + $booking->discount_amount;

                // Determine status
                $status = ($paidAmount > 0 && $dueAmount > 0) ? 'PART PAID' : 'UNPAID';

                // Calculate overdue days (Terlambat)
                // Assuming due date is same as booking date for now
                $dueDate = Carbon::parse($booking->booking_date);
                $daysOverdue = Carbon::now()->diffInDays($dueDate, false); // negative if past
    
                // If past due date (daysOverdue < 0), format it.
                // Example "5 Hari" (5 days late)
                $terlambatStr = '-';
                if ($daysOverdue < 0) {
                    $terlambatStr = abs(intval($daysOverdue)) . ' Hari';
                }

                return [
                    'id' => $booking->id,
                    'lokasi' => $booking->branch->name ?? '-',
                    'pelanggan' => $booking->user ? $booking->user->name : 'Guest',
                    'jatuhTempo' => $dueAmount, // Amount Due
                    'tglFaktur' => Carbon::parse($booking->booking_date)->format('d M Y'),
                    'tglJatuhTempo' => Carbon::parse($booking->booking_date)->format('d M Y'), // Assumption
                    'kotor' => $grossPrice,
                    'status' => $status,
                    'terlambat' => $terlambatStr
                ];
            })
            // Filter out fully paid if logic above missed them (e.g. calculation rounding)
            ->filter(function ($i) {
                return $i['jatuhTempo'] > 0;
            })->values();

        $summary = [
            'totalGros' => $invoices->sum('kotor'),
            'jumlahJatuhTempo' => $invoices->sum('jatuhTempo')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'invoices' => $invoices,
            'summary' => $summary
        ]);
    }


    public function cashRegisterMovement(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $query = CashierShift::query()
            ->with(['cashier']);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Filter by Open Date
        $query->where(DB::raw('DATE(clock_in)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(clock_in)'), '<=', $dateTo);

        $shifts = $query->orderBy('clock_in', 'desc')
            ->get()
            ->map(function ($shift) {
                return [
                    'id' => $shift->id,
                    'openDate' => Carbon::parse($shift->clock_in)->setTimezone('Asia/Jakarta')->format('d-M-Y, H:i'),
                    'closeDate' => $shift->clock_out ? Carbon::parse($shift->clock_out)->setTimezone('Asia/Jakarta')->format('d-M-Y, H:i') : '-',
                    'staff' => $shift->cashier ? $shift->cashier->name : 'Unknown',
                    'expected' => $shift->expected_cash ?? 0,
                    'counted' => $shift->ending_cash ?? 0,
                    'diff' => $shift->variance ?? 0
                ];
            });

        $summary = [
            'totalDiharapkan' => $shifts->sum('expected'),
            'totalTerhitung' => $shifts->sum('counted'),
            'totalPerbedaan' => $shifts->sum('diff')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'movements' => $shifts,
            'summary' => $summary
        ]);
    }


    public function salesProfitLoss(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());

        // 1. Transactions Data
        $trxQuery = Transaction::query()->with('booking');
        if ($branchId && $branchId !== 'all') {
            $trxQuery->where('branch_id', $branchId);
        }

        $transactions = $trxQuery->where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo)
            ->get();

        $onlineSales = $transactions->whereIn('payment_method', ['virtual_account', 'transfer'])->sum('subtotal');
        $posSales = $transactions->whereNotIn('payment_method', ['virtual_account', 'transfer'])->sum('subtotal');
        $totalTax = $transactions->sum('tax');
        $totalRefund = $transactions->where('type', 'refund')->sum('total');

        $totalPendapatan = $onlineSales + $posSales;

        // 2. COGS (HPP) - Placeholder
        $totalCOGSProduct = 0;
        $totalCOGSService = 0;
        $totalRefundCOGS = 0;

        // 3. Cashier Shifts (For Kas Masuk/Keluar unexpected stats)
        $shiftQuery = CashierShift::query();
        if ($branchId && $branchId !== 'all') {
            $shiftQuery->where('branch_id', $branchId);
        }
        $shifts = $shiftQuery->where(DB::raw('DATE(clock_in)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(clock_in)'), '<=', $dateTo)
            ->get();
        // Variance: Positive = Excess (Kas Masuk), Negative = Shortage (Kas Keluar)
        $variance = $shifts->sum('variance');
        $kasMasuk = $variance > 0 ? $variance : 0;

        $expenseQuery = \App\Models\Expense::query();
        if ($branchId) {
            $expenseQuery->where('branch_id', $branchId);
        }
        $pettyCashTotal = $expenseQuery->where(DB::raw('DATE(created_at)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(created_at)'), '<=', $dateTo)
            ->sum('amount');

        $kasKeluar = ($variance < 0 ? abs($variance) : 0) + $pettyCashTotal;
        $kasNet = $kasMasuk - $kasKeluar;

        // Actual Commission from StaffCommission table
        $commissionQuery = \App\Models\StaffCommission::query();
        if ($branchId && $branchId !== 'all') {
            $commissionQuery->where('branch_id', $branchId);
        }
        $komisi = $commissionQuery->where(DB::raw('DATE(payment_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(payment_date)'), '<=', $dateTo)
            ->sum('commission_amount');

        // 5. Tips - Can be extracted from change_amount / tips if table supported it.
        $tips = 0;

        // 6. Voucher
        $processedBookings = [];
        $voucherSales = 0;
        foreach ($transactions as $t) {
            if ($t->discount > 0) {
                $voucherSales += $t->discount;
            } elseif ($t->booking && $t->booking->discount_amount > 0 && !in_array($t->booking->id, $processedBookings)) {
                $voucherSales += $t->booking->discount_amount;
                $processedBookings[] = $t->booking->id;
            }
        }

        $pendapatanBersih = $totalPendapatan - $totalTax - abs($totalRefund) + $kasNet - $komisi;

        // Build Rows
        $rows = [
            ['name' => '1. Pendapatan', 'amount' => $totalPendapatan, 'bold' => true],
            ['name' => '- KIOSK', 'amount' => 0, 'indent' => 1],
            ['name' => '- MARKETPLACE', 'amount' => 0, 'indent' => 1],
            ['name' => '- ONLINE', 'amount' => $onlineSales, 'indent' => 1],
            ['name' => '- POINT OF SALE', 'amount' => $posSales, 'indent' => 1],
            ['name' => '- Tax', 'amount' => $totalTax, 'indent' => 1],
            ['name' => '- Refund', 'amount' => $totalRefund, 'indent' => 1], // Show as positive number, logic handles deduction

            ['name' => '2. Harga Pokok Penjualan', 'amount' => 0, 'bold' => true],
            ['name' => '- Total penjualan produk (Harga biaya)', 'amount' => 0, 'indent' => 1],
            ['name' => '- Total pengembalian produk (Harga biaya)', 'amount' => 0, 'indent' => 1, 'textAmount' => '(0,00)'],
            ['name' => '- Total penjualan Layanan (Harga modal)', 'amount' => 0, 'indent' => 1],
            ['name' => '- Total pengembalian Layanan (Harga modal)', 'amount' => 0, 'indent' => 1, 'textAmount' => '(0,00)'],

            ['name' => '3. Laba kotor -(pajak)', 'amount' => $totalPendapatan - $totalTax, 'bold' => true], // Gross - Tax

            ['name' => '4. Kas masuk/keluar', 'amount' => $kasNet, 'bold' => true, 'isNegative' => $kasNet < 0],
            ['name' => '- Kas Masuk', 'amount' => $kasMasuk, 'indent' => 1],
            ['name' => '- Kas Keluar', 'amount' => $kasKeluar, 'indent' => 1, 'isNegative' => true],

            ['name' => '5. Order Produk', 'amount' => 0, 'bold' => true],
            ['name' => '6. Total penjualan berdasarkan penggunaan voucher', 'amount' => $voucherSales, 'bold' => true],
            ['name' => '7. Tips', 'amount' => $tips, 'bold' => true],
            ['name' => '8. Komisi pegawai', 'amount' => $komisi, 'bold' => true, 'isNegative' => true],
            ['name' => '9. Pendapatan bersih', 'amount' => $pendapatanBersih, 'bold' => true]
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'rows' => $rows
        ]);
    }


    public function cashFlow(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());

        // 1. Transactions (Sales & Refunds)
        $trxQuery = Transaction::query();
        if ($branchId) {
            $trxQuery->where('branch_id', $branchId);
        }
        $transactions = $trxQuery->where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo)
            ->get();

        $flows = collect();

        foreach ($transactions as $t) {
            // Sales
            if ($t->total > 0 && $t->type !== 'refund') {
                $flows->push([
                    'id' => $t->id,
                    'raw_id' => $t->id,
                    'is_trx' => true,
                    'judul' => 'PENJUALAN',
                    'tanggal' => Carbon::parse($t->transaction_date)->setTimezone('Asia/Jakarta'),
                    'tipe' => 'PENJUALAN VIA ' . strtoupper(str_replace('_', ' ', $t->payment_method)),
                    'jumlah' => $t->total
                ]);
            }

            // If it's a refund
            if ($t->type === 'refund') {
                $flows->push([
                    'id' => $t->id,
                    'raw_id' => $t->id,
                    'is_trx' => true,
                    'judul' => 'PENGEMBALIAN DANA',
                    'tanggal' => Carbon::parse($t->transaction_date)->setTimezone('Asia/Jakarta'),
                    'tipe' => 'REFUND VIA ' . strtoupper(str_replace('_', ' ', $t->payment_method)),
                    'jumlah' => -abs((float) $t->total)
                ]);
            }
        }

        // 2. Cashier Shifts (Variance)
        $shiftQuery = CashierShift::query();
        if ($branchId && $branchId !== 'all') {
            $shiftQuery->where('branch_id', $branchId);
        }
        $shifts = $shiftQuery->where(DB::raw('DATE(clock_in)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(clock_in)'), '<=', $dateTo)
            ->where('variance', '!=', 0)->get();

        foreach ($shifts as $s) {
            $isPositive = $s->variance > 0;
            $flows->push([
                'id' => $s->id,
                'raw_id' => $s->id,
                'is_trx' => false,
                'judul' => $isPositive ? 'Kelebihan Kas (Over)' : 'Kekurangan Kas (Short)',
                'tanggal' => Carbon::parse($s->clock_out ?? $s->clock_in)->setTimezone('Asia/Jakarta'),
                'tipe' => $isPositive ? 'CASH IN (Kasir Manual)' : 'CASH OUT (Kasir Manual)',
                'jumlah' => $s->variance
            ]);
        }

        // 3. Petty Cash (Expenses)
        $expenseQuery = \App\Models\Expense::with('category');
        if ($branchId && $branchId !== 'all') {
            $expenseQuery->where('branch_id', $branchId);
        }
        $expenses = $expenseQuery->where(DB::raw('DATE(created_at)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(created_at)'), '<=', $dateTo)
            ->get();

        foreach ($expenses as $e) {
            $flows->push([
                'id' => 'EXP-' . $e->id,
                'raw_id' => $e->id,
                'is_trx' => false,
                'judul' => strtoupper($e->description ?? 'PETTY CASH'),
                'tanggal' => Carbon::parse($e->created_at)->setTimezone('Asia/Jakarta'),
                'tipe' => 'PETTY CASH' . ($e->category ? ' - ' . strtoupper($e->category->name) : ''),
                'jumlah' => -abs((float)$e->amount)
            ]);
        }

        // Sort by date DESC
        $sortedFlows = $flows->sortByDesc('tanggal')->values();

        // Format for response
        $formatted = $sortedFlows->map(function ($item, $index) {
            return [
                'id' => $index + 1,
                'judul' => $item['judul'],
                'tanggal' => $item['tanggal']->format('d M Y, H:i'),
                'tipe' => $item['tipe'],
                'jumlah' => $item['jumlah']
            ];
        });

        // Summary
        $summary = [
            'masuk' => $flows->where('jumlah', '>', 0)->sum('jumlah'),
            'keluar' => $flows->where('jumlah', '<', 0)->sum('jumlah'),
            'penjualan' => $flows->filter(function ($f) {
                return str_starts_with($f['tipe'], 'PENJUALAN');
            })->sum('jumlah'),
            'keseluruhan' => $flows->sum('jumlah')
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'flows' => $formatted,
            'summary' => $summary
        ]);
    }


    public function dpReport(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // We want bookings that have a DP payment or are in partial_paid state?
        // Or simply all payments of type 'down_payment' that were successful.
        // Let's filter payments where payment_type = 'down_payment'
        // And status = 'success'.

        $query = Payment::query()->with(['booking.user', 'booking.service', 'booking.therapist', 'booking.branch', 'booking.transactions']);

        if ($branchId && $branchId !== 'all') {
            $query->whereHas('booking', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        $query->where('payment_type', 'down_payment')
            ->where('status', 'success');

        if ($dateFrom && $dateTo) {
            $query->whereBetween('paid_at', [$dateFrom, $dateTo]);
        }

        $payments = $query->get();

        $rows = $payments->map(function ($p) {
            $booking = $p->booking;
            if (!$booking)
                return null;

            // remaining amount = total_price - sum of paid payments?
            // Or simpler: total_price - amount_paid_so_far
            // Let's assume booking has correct status and calculation logic.

            // Re-calculate remaining just in case
            // Re-calculate remaining
            // Calculate total paid (All successful payments are tracked in the payments table, both online and POS)
            $totalPaid = $booking->payments()->where('status', 'success')->sum('amount');

            // If checking payment_status from booking (which is updated by POS)
            $isPaid = $booking->payment_status === 'paid';

            // Calculate remaining
            $remaining = max(0, $booking->total_price - $totalPaid);

            if ($isPaid) {
                $remaining = 0;
            }

            $status = 'Menunggu Pelunasan';
            if ($booking->status == 'refunded') {
                $status = 'Refund';
            } elseif ($booking->status == 'cancelled') {
                $status = 'Void';
            } elseif ($isPaid || $remaining <= 0) {
                $status = 'Lunas';
                $remaining = 0; // Ensure consistency
            }

            return [
                'id' => $p->id,
                'invoiceNo' => $booking->booking_ref,
                'customer' => $booking->user->name ?? 'Guest',
                'date' => $p->paid_at->format('d M Y'),
                'dpAmount' => (float) $p->amount,
                'totalAmount' => (float) $booking->total_price,
                'remainingAmount' => (float) $remaining,
                'status' => $status,
                'paymentMethod' => ucfirst(str_replace('_', ' ', $p->payment_method)),
                'staff' => $booking->therapist->name ?? '-',
                'service' => $booking->service->name ?? '-'
            ];
        })->filter()->values();

        // Calculate summary based on rows, excluding Refunds from Revenue
        $validRows = $rows->where('status', '!=', 'Refund');

        $summary = [
            'totalDP' => $validRows->sum('dpAmount'),
            'totalTransactions' => $validRows->sum('totalAmount'),
            'totalRemaining' => $validRows->sum('remainingAmount'),
            'countPending' => $rows->where('status', 'Menunggu Pelunasan')->count(),
            'countLunas' => $rows->where('status', 'Lunas')->count(),
        ];

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'rows' => $rows,
            'summary' => $summary
        ]);
    }
    public function debugData(Request $request)
    {
        $branchId = $request->input('branch_id');
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());

        $trxCount = Transaction::where(DB::raw('DATE(transaction_date)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(transaction_date)'), '<=', $dateTo)
            ->count();

        $expCount = \App\Models\Expense::where(DB::raw('DATE(created_at)'), '>=', $dateFrom)
            ->where(DB::raw('DATE(created_at)'), '<=', $dateTo)
            ->count();

        return response()->json([
            'debug' => [
                'branch_id' => $branchId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'transaction_count' => $trxCount,
                'expense_count' => $expCount,
                'latest_trx' => Transaction::latest('id')->first(),
                'latest_exp' => \App\Models\Expense::latest('id')->first()
            ]
        ]);
    }
}

