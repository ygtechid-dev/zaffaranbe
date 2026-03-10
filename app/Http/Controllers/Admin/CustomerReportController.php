<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerReportController extends Controller
{
    private function applyFilters($query, Request $request, $dateField = 'created_at') {
        if ($request->filled('branch_id') && $request->branch_id !== 'all') {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate($dateField, '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate($dateField, '<=', $request->date_to);
        }
        return $query;
    }

    // Laporan Daftar Pelanggan
    public function daftar(Request $request)
    {
        $query = User::where('role', 'customer');
        $this->applyFilters($query, $request, 'created_at');

        // Optimasi: Eager load bookings untuk perhitungan manual jika diperlukan, atau andalkan withCount/Sum
        // Untuk last appointment, kita perlu data booking.
        $customers = $query->with(['bookings.branch'])
            ->withCount(['bookings as appointments'])
            ->withCount(['bookings as no_shows' => function($q) {
                $q->where('status', 'no_show');
            }])
            ->withSum(['bookings as total_sales' => function($q) {
                $q->whereIn('status', ['completed', 'confirmed']); // Asumsi confirmed juga dihitung atau hanya completed
            }], 'total_price')
            ->latest()
            ->get()
            ->map(function($user) {
                // Determine last booking manually from collection to ensure accuracy without complex subqueries
                $lastBooking = $user->bookings->sortByDesc('start_time')->first();

                return [
                    'id' => $user->id,
                    'customer' => $user->name,
                    'blocked' => !$user->is_active ? 1 : 0,
                    'appointments' => $user->appointments,
                    'noShows' => $user->no_shows,
                    'totalSales' => (float) ($user->total_sales ?? 0),
                    'outstanding' => 0, // Belum ada logic invoice outstanding
                    'gender' => $user->gender ?? '-',
                    'added' => $user->created_at->format('d M Y'),
                    'lastAppointment' => $lastBooking ? Carbon::parse($lastBooking->start_time)->format('d M Y H:i') : '-',
                    'lastLocation' => ($lastBooking && $lastBooking->branch) ? $lastBooking->branch->name : '-',
                ];
            });

        return response()->json(['data' => $customers]);
    }

    public function retensi(Request $request)
    {
        $branchId = $request->input('branch_id');
        $staffId = $request->input('staff_id'); // Usually 'staff_id' from frontend
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = User::where('role', 'customer');

        // Filter based on bookings matching criteria
        $query->whereHas('bookings', function($q) use ($branchId, $staffId, $dateFrom, $dateTo) {
            if ($branchId && $branchId !== 'all') {
                $q->where('branch_id', $branchId);
            }
            if ($staffId && $staffId !== 'all') {
                $q->where('therapist_id', $staffId);
            }
            if ($dateFrom) {
                $q->whereDate('booking_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->whereDate('booking_date', '<=', $dateTo);
            }
        });

        $data = $query->with(['bookings' => function($q) use ($branchId, $staffId) {
                // Ensure we only get relevant bookings for calculations if needed
                // But for 'last agenda', we might want their absolute last or last in this branch
                if ($branchId && $branchId !== 'all') {
                    $q->where('branch_id', $branchId);
                }
            }, 'bookings.therapist'])
            ->get()
            ->map(function($user) {
                 $bookings = $user->bookings->sortByDesc('booking_date');
                 $lastBooking = $bookings->first();
                 
                 // Kalkulasi Total Sale (Lifetime or Branch specific depends on BR, let's keep it lifetime for total)
                 $totalSale = $bookings->where('status', 'completed')->sum('total_price');
                 
                 return [
                     'id' => $user->id,
                     'name' => $user->name,
                     'phone' => $user->phone,
                     'email' => $user->email,
                     'lastAgenda' => $lastBooking ? Carbon::parse($lastBooking->booking_date)->format('d M Y') : '-',
                     'absentCount' => $lastBooking ? Carbon::now()->diffInDays(Carbon::parse($lastBooking->booking_date)) : 0,
                     'staff' => ($lastBooking && $lastBooking->therapist) ? $lastBooking->therapist->name : '-',
                     'lastSale' => $lastBooking ? (float) $lastBooking->total_price : 0,
                     'totalSale' => (float) $totalSale
                 ];
            })
            ->values();

        return response()->json(['data' => $data]);
    }
}
