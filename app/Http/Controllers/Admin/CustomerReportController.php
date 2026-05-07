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

        $customers = $query->with(['bookings.branch'])
            ->withCount(['bookings as appointments'])
            ->withCount(['bookings as no_shows' => function($q) {
                $q->where('status', 'no_show');
            }])
            ->withSum(['bookings as total_sales' => function($q) {
                $q->whereIn('status', ['completed', 'confirmed']);
            }], 'total_price')
            ->latest()
            ->get()
            ->map(function($user) {
                $lastBooking = $user->bookings->sortByDesc('start_time')->first();

                return [
                    'id'              => $user->id,
                    'customer'        => $user->name,
                    'phone'           => $user->phone ?? '-',   // ← tambah
                    'email'           => $user->email ?? '-',   // ← tambah
                    'blocked'         => !$user->is_active ? 1 : 0,
                    'appointments'    => $user->appointments,
                    'noShows'         => $user->no_shows,
                    'totalSales'      => (float) ($user->total_sales ?? 0),
                    'outstanding'     => 0,
                    'gender'          => $user->gender ?? '-',
                    'added'           => $user->created_at->format('d M Y'),
                    'lastAppointment' => $lastBooking
                        ? Carbon::parse($lastBooking->start_time)->format('d M Y H:i')
                        : '-',
                    'lastLocation'    => ($lastBooking && $lastBooking->branch)
                        ? $lastBooking->branch->name
                        : '-',
                ];
            });

        return response()->json(['data' => $customers]);
    }

    public function retensi(Request $request)
    {
        $branchId = $request->input('branch_id');
        $staffId  = $request->input('staff_id');
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $query = User::where('role', 'customer');

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
                if ($branchId && $branchId !== 'all') {
                    $q->where('branch_id', $branchId);
                }
            }, 'bookings.therapist'])
            ->withCount(['bookings as total_appointments'])                      // ← tambah
            ->withCount(['bookings as total_no_shows' => function($q) {         // ← tambah
                $q->where('status', 'no_show');
            }])
            ->get()
            ->map(function($user) {
                $bookings    = $user->bookings->sortByDesc('booking_date');
                $lastBooking = $bookings->first();
                $totalSale   = $bookings->where('status', 'completed')->sum('total_price');

                return [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'phone'       => $user->phone ?? '-',
                    'email'       => $user->email ?? '-',
                    'lastAgenda'  => $lastBooking
                        ? Carbon::parse($lastBooking->booking_date)->format('d M Y')
                        : '-',
                    'absentCount' => $lastBooking
                        ? Carbon::now()->diffInDays(Carbon::parse($lastBooking->booking_date))
                        : 0,
                    'staff'       => ($lastBooking && $lastBooking->therapist)
                        ? $lastBooking->therapist->name
                        : '-',
                    'lastSale'    => $lastBooking ? (float) $lastBooking->total_price : 0,
                    'totalSale'   => (float) $totalSale,
                    'appointments' => $user->total_appointments ?? 0,           // ← tambah
                    'noShows'      => $user->total_no_shows ?? 0,               // ← tambah
                ];
            })
            ->values();

        return response()->json(['data' => $data]);
    }
}