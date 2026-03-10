<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AgendaReportController extends Controller
{
    // Helper filter umum (based on booking_date)
    private function applyFilters($query, Request $request, $dateField = 'booking_date')
    {
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

    public function kalkulasi(Request $request)
    {
        // Base Query untuk perhitungan (Completed/Paid usually)
        // Atau semua agenda valid? "Kalkulasi Agenda" usually means volume & revenue.
        // Kita ambil semua yang tidak dicancel untuk volume, dan paid untuk revenue? 
        // Simplifikasi: Semua yang status != cancelled.
        $baseQuery = Booking::query()->where('status', '!=', 'cancelled');
        $this->applyFilters($baseQuery, $request);

        $totalAgenda = (clone $baseQuery)->count();
        $totalRevenue = (clone $baseQuery)->sum('total_price');

        // By Service
        $byService = Booking::query()
            ->where('status', '!=', 'cancelled')
            ->join('services', 'bookings.service_id', '=', 'services.id')
            ->select(
                'services.name as service',
                DB::raw('count(bookings.id) as agenda'),
                DB::raw('sum(bookings.total_price) as total')
            );
        // Apply filters mannually to join query or reuse scope if careful
        if ($request->filled('branch_id') && $request->branch_id !== 'all') $byService->where('bookings.branch_id', $request->branch_id);
        if ($request->filled('date_from')) $byService->whereDate('bookings.booking_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $byService->whereDate('bookings.booking_date', '<=', $request->date_to);
        
        $byServiceResults = $byService->groupBy('services.id', 'services.name')->get();

        // By Staff
        $byStaff = Booking::query()
            ->where('status', '!=', 'cancelled')
            ->join('therapists', 'bookings.therapist_id', '=', 'therapists.id')
            ->select(
                'therapists.name as staff',
                DB::raw('count(bookings.id) as agendaCount'),
                DB::raw('sum(bookings.total_price) as totalAmount')
            );
        if ($request->filled('branch_id') && $request->branch_id !== 'all') $byStaff->where('bookings.branch_id', $request->branch_id);
        if ($request->filled('date_from')) $byStaff->whereDate('bookings.booking_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $byStaff->whereDate('bookings.booking_date', '<=', $request->date_to);
        
        $byStaffResults = $byStaff->groupBy('therapists.id', 'therapists.name')->get();

        return response()->json([
            'summary' => [
                'totalAgenda' => $totalAgenda,
                'totalRevenue' => $totalRevenue
            ],
            'byService' => $byServiceResults,
            'byStaff' => $byStaffResults
        ]);
    }

    public function pembatalan(Request $request)
    {
        // Filter by cancelled_at
        $query = Booking::query()->where('status', 'cancelled');
        $this->applyFilters($query, $request, 'cancelled_at');

        // Summary Reasons
        $summaryReasons = (clone $query)
            ->select('cancellation_reason as reason', DB::raw('count(*) as count'))
            ->groupBy('cancellation_reason')
            ->get()
            ->map(function($item) {
                return [
                    'reason' => $item->reason ?: 'Other',
                    'count' => $item->count
                ];
            });

        // Details
        $details = $query->with(['user', 'service', 'canceller'])
            ->latest('cancelled_at')
            ->get()
            ->map(function($booking) {
                return [
                    'id' => $booking->id,
                    'ref' => $booking->booking_ref,
                    'customer' => $booking->user ? $booking->user->name : 'Guest',
                    'service' => $booking->service ? $booking->service->name : '-',
                    'canceledDate' => $booking->cancelled_at ? $booking->cancelled_at->format('d M Y') : '-',
                    'canceledBy' => $booking->canceller ? $booking->canceller->name : 'System',
                    'reason' => $booking->cancellation_reason ?: 'Other',
                    'price' => $booking->total_price
                ];
            });

        return response()->json([
            'reasons' => $summaryReasons,
            'details' => $details
        ]);
    }

    public function detail(Request $request)
    {
        $query = Booking::query();
        $this->applyFilters($query, $request);

        // Frontend detail includes: Invoice Ref, inv date, last payment date.
        // Need relations.
        $data = $query->with(['service', 'transaction', 'payments'])
            ->latest('booking_date')
            ->get()
            ->map(function($booking) {
                $lastPayment = null;
                if ($booking->payments && $booking->payments->count() > 0) {
                    $lastPayment = $booking->payments->sortByDesc('paid_at')->first();
                }

                return [
                    'id' => $booking->id,
                    'service' => $booking->service ? $booking->service->name : '-',
                    'date' => $booking->booking_date ? $booking->booking_date->format('d M Y') : '-',
                    'startTime' => $booking->start_time ? Carbon::parse($booking->start_time)->format('H:i') : '-',
                    'endTime' => $booking->end_time ? Carbon::parse($booking->end_time)->format('H:i') : '-',
                    'price' => $booking->service_price, // or total_price
                    'invoiceRef' => $booking->transaction ? $booking->transaction->transaction_ref : '-',
                    'invoiceDate' => $booking->transaction ? $booking->transaction->created_at->format('d M Y') : '-',
                    'lastPaymentDate' => $lastPayment ? $lastPayment->paid_at->format('d M Y, H:i') : '-',
                    'amount' => $booking->total_price,
                    'status' => strtoupper($booking->status),
                    'createdAt' => $booking->created_at->format('d M Y, H:i')
                ];
            });

        return response()->json([
            'data' => $data
        ]);
    }
}
