<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\StaffAttendance;
use App\Models\StaffTip;
use App\Models\StaffCommission;
use App\Models\Therapist;

class StaffReportController extends Controller
{
    /**
     * Helper to apply filters
     */
    private function applyFilters($query, Request $request, $dateColumn = 'created_at')
    {
        if ($request->has('branch_id') && $request->branch_id && $request->branch_id !== 'all') {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate($dateColumn, '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate($dateColumn, '<=', $request->date_to);
        }
        
        // Handle filter by staff_id if passed (usually 'staff_id' or 'therapist_id')
        if ($request->has('staff_id') && $request->staff_id) {
             $query->where('staff_id', $request->staff_id);
        }

        return $query;
    }

    /**
     * 1. Jam Kerja Staff (Staff Work Hours)
     */
    public function attendance(Request $request)
    {
        // Get all attendances first
        $query = StaffAttendance::with(['staff', 'branch']);
        $query = $this->applyFilters($query, $request, 'check_in');

        // Retrieve raw logs
        $logs = $query->orderBy('check_in', 'desc')->get();

        // Group by staff_id to calculate total hours
        $grouped = $logs->groupBy('staff_id');

        $staffData = [];
        foreach ($grouped as $staffId => $staffLogs) {
            $staff = $staffLogs->first()->staff;
            if (!$staff) continue;

            $totalSeconds = 0;
            $formattedLogs = $staffLogs->map(function ($log) use (&$totalSeconds) {
                $durationStr = '-';
                if ($log->check_out) {
                    $diffInSeconds = $log->check_in->diffInSeconds($log->check_out);
                    $totalSeconds += $diffInSeconds;
                    
                    // Format duration string for frontend "10 Jam, 30 Menit"
                    $hours = floor($diffInSeconds / 3600);
                    $minutes = floor(($diffInSeconds % 3600) / 60);
                    $parts = [];
                    if ($hours > 0) $parts[] = "$hours Jam";
                    if ($minutes > 0) $parts[] = "$minutes Menit";
                    $durationStr = implode(', ', $parts) ?: '0 Menit';
                }

                return [
                    'id' => $log->id,
                    'durasi' => $durationStr,
                    'mulai' => $log->check_in->format('H:i'),
                    'berakhir' => $log->check_out ? $log->check_out->format('H:i') : '-',
                    'lokasi' => $log->branch->name ?? '-',
                    'date' => $log->check_in->format('Y-m-d')
                ];
            });

            // Convert total seconds to hours (for summary total)
            $totalHours = round($totalSeconds / 3600, 1);

            $staffData[] = [
                'id' => $staff->id,
                'name' => $staff->name,
                'totalDurationHours' => $totalHours,
                'logs' => $formattedLogs
            ];
        }

        return response()->json([
            'data' => array_values($staffData),
            'summary' => [
                'totalStaff' => count($staffData)
            ]
        ]);
    }

    /**
     * 2. Tip Berdasarkan Staff
     */
    public function tips(Request $request)
    {
        $query = StaffTip::with('staff');
        $query = $this->applyFilters($query, $request, 'date');

        $tips = $query->get();
        $grouped = $tips->groupBy('staff_id');

        $data = [];
        $totalCollectedAll = 0;
        $totalReturnedAll = 0;

        foreach ($grouped as $staffId => $staffTips) {
            $staff = $staffTips->first()->staff;
            if (!$staff) continue;

            $collected = $staffTips->sum('amount_collected');
            $returned = $staffTips->sum('amount_returned'); // Usually same as collected if full tip given
            $total = $collected - $returned; // Wait, logic in frontend dummy: total = terkumpul - dikembalikan. 
            // Usually "Net Tip" = Collected. If "Returned" means "Returned to Company" or "Potongan", then ok.
            // But if "Dikembalikan" means "Given to Staff", then "Sisa di Kasir" = Collected - Given.
            // Let's assume "Total" means "Total received by staff" ??
            // Frontend Dummy: terkumpul 500k, dikembalikan 50k, total 450k.
            // Possibly "Dikembalikan" = refunded to customer? Or admin fee?
            // Let's stick to simple math: Total = Collected - Returned.

            $count = $staffTips->count();
            $average = $count > 0 ? ($total / $count) : 0; // Or average per transaction?

            $data[] = [
                'id' => $staff->id,
                'staffName' => $staff->name,
                'terkumpul' => $collected,
                'dikembalikan' => $returned,
                'total' => $total,
                'rataRata' => $average
            ];

            $totalCollectedAll += $collected;
            $totalReturnedAll += $returned;
        }

        return response()->json([
            'data' => $data,
            'summary' => [
                'totalTips' => count($tips),
                'totalCollected' => $totalCollectedAll,
                'totalReturned' => $totalReturnedAll
            ]
        ]);
    }

    /**
     * 3. Ringkasan Komisi Staff
     */
    public function commissionSummary(Request $request)
    {
        $query = StaffCommission::with('staff');
        
        // Filter date based on parameter or default
        // Frontend has "Filter by payment date" toggle.
        // If filter_by_payment_date is true, use payment_date. Else created_at (invoice date).
        $dateCol = $request->input('filter_by_payment_date', 'true') === 'true' ? 'payment_date' : 'created_at';
        $query = $this->applyFilters($query, $request, $dateCol);

        $commissions = $query->get();
        $grouped = $commissions->groupBy('staff_id');

        $data = [];
        foreach ($grouped as $staffId => $items) {
            $staff = $items->first()->staff;
            if (!$staff) continue;

            $total = $items->sum('commission_amount');
            
            // Breakdown by type (using case-insensitive check)
            $layanan = $items->filter(fn($i) => strtolower($i->item_type) === 'service')->sum('commission_amount');
            $produk = $items->filter(fn($i) => strtolower($i->item_type) === 'product')->sum('commission_amount');
            $voucher = $items->filter(fn($i) => strtolower($i->item_type) === 'voucher')->sum('commission_amount');
            $kelas = $items->filter(fn($i) => strtolower($i->item_type) === 'class')->sum('commission_amount');
            $planKelas = $items->filter(fn($i) => strtolower($i->item_type) === 'planclass')->sum('commission_amount');

            $data[] = [
                'id' => $staff->id,
                'nama' => $staff->name,
                'total' => $total,
                'layanan' => $layanan,
                'produk' => $produk,
                'voucher' => $voucher,
                'kelas' => $kelas,
                'planKelas' => $planKelas
            ];
        }

        // Summary total
        $summaryTotal = collect($data)->sum('total');

        return response()->json([
            'data' => $data,
            'summary' => [
                'totalNilaiKomisi' => $summaryTotal,
                // Add breakdown if needed
            ]
        ]);
    }

    /**
     * 4. Komisi Staff Terperinci
     */
    public function commissionDetailed(Request $request)
    {
        $query = StaffCommission::with(['staff', 'transaction', 'booking.user']); // user here is customer
        $dateCol = $request->input('filter_by_payment_date', 'true') === 'true' ? 'payment_date' : 'created_at';
        $query = $this->applyFilters($query, $request, $dateCol);

        // Filter category/type
        if ($request->has('category') && $request->category) {
            $query->where('item_type', $request->category); // e.g., 'Service', 'Product'
        }

        $items = $query->orderBy($dateCol, 'desc')->get();

        $data = $items->map(function ($item) {
            // Get customer name from booking
            $customerName = '-';
            if ($item->booking) {
                $customerName = $item->booking->customer_name;
            }

            return [
                'id' => $item->id,
                'staffName' => $item->staff ? $item->staff->name : '-',
                'pelanggan' => $customerName,
                'tanggalFaktur' => $item->created_at->format('d M Y'),
                'tanggalPembayaran' => $item->payment_date ? $item->payment_date->format('d M Y') : '-',
                'nomorFaktur' => $item->transaction ? $item->transaction->transaction_ref : '-',
                'item' => $item->item_name,
                'namaVariant' => $item->item_variant_name ?: '-',
                'qty' => $item->qty,
                'totalPenjualan' => $item->sales_amount,
                'persenKomisi' => $item->commission_percentage,
                'besaranKomisi' => $item->commission_amount
            ];
        });

        return response()->json([
            'data' => $data,
            'summary' => [
                'totalItems' => count($data),
                'totalCommission' => $data->sum('besaranKomisi')
            ]
        ]);
    }

    /**
     * 5. Rincian Komisi Staff (Grup Item)
     */
    public function commissionItemGroup(Request $request)
    {
        $query = StaffCommission::with(['staff']);
        $dateCol = $request->input('filter_by_payment_date', 'true') === 'true' ? 'payment_date' : 'created_at';
        $query = $this->applyFilters($query, $request, $dateCol);
        
         if ($request->has('category') && $request->category) {
            $query->where('item_type', $request->category);
        }

        // Group by Item Name + Variant + Commission %
        // We can do this via collection grouping for flexibility
        $items = $query->get();
        
        $grouped = $items->groupBy(function ($item) {
            return $item->item_name . '|' . $item->item_variant_name . '|' . $item->item_type . '|' . $item->commission_percentage;
        });

        $data = [];
        $idCounter = 1;

        foreach ($grouped as $key => $groupItems) {
            list($itemName, $variantName, $type, $percent) = explode('|', $key);

            $totalQty = $groupItems->sum('qty');
            $totalSales = $groupItems->sum('sales_amount');
            $totalCommission = $groupItems->sum('commission_amount');

            $data[] = [
                'id' => $idCounter++,
                'tipe' => $type,
                'item' => $itemName,
                'namaVariant' => $variantName ?: '-',
                'qty' => $totalQty,
                'totalPenjualan' => $totalSales,
                'persenKomisi' => $percent . '%',
                'besaranKomisi' => $totalCommission
            ];
        }

        return response()->json([
            'data' => $data,
            'summary' => [
                'totalGroups' => count($data),
                'totalCommission' => collect($data)->sum('besaranKomisi')
            ]
        ]);
    }
}
