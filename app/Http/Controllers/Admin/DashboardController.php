<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview
     */
    public function index(Request $request)
    {
        $branchId = $request->input('branch_id');
        $today = Carbon::today();

        // Basic stats
        $stats = [
            'total_revenue_today' => $this->getRevenueToday($branchId),
            'bookings_today' => $this->getBookingsToday($branchId),
            'new_customers_today' => $this->getNewCustomersToday(),
            'active_therapists' => $this->getActiveTherapists($branchId),
            'total_revenue_month' => $this->getRevenueMonth($branchId),
            'total_bookings_month' => $this->getBookingsMonth($branchId),
            'pending_payments' => $this->getPendingPayments($branchId),
        ];

        // Today's agenda
        $todayAgenda = $this->getTodayAgenda($branchId);

        // Room status
        $roomStatus = $this->getRoomStatus($branchId);

        // Recent notifications/alerts
        $alerts = $this->getAlerts($branchId);

        return response()->json([
            'stats' => $stats,
            'today_agenda' => $todayAgenda,
            'room_status' => $roomStatus,
            'alerts' => $alerts,
        ]);
    }

    /**
     * Get statistics only
     */
    public function stats(Request $request)
    {
        $branchId = $request->input('branch_id');

        return response()->json([
            'total_revenue_today' => $this->getRevenueToday($branchId),
            'bookings_today' => $this->getBookingsToday($branchId),
            'new_customers_today' => $this->getNewCustomersToday(),
            'active_therapists' => $this->getActiveTherapists($branchId),
            'total_revenue_month' => $this->getRevenueMonth($branchId),
            'total_bookings_month' => $this->getBookingsMonth($branchId),
            'pending_payments' => $this->getPendingPayments($branchId),
        ]);
    }

    /**
     * Get chart data
     */
    public function charts(Request $request)
    {
        $branchId = $request->input('branch_id');
        $period = $request->input('period', 'week'); // week, month, year

        // Revenue trend
        $revenueTrend = $this->getRevenueTrend($branchId, $period);

        // Bookings trend
        $bookingsTrend = $this->getBookingsTrend($branchId, $period);

        // Popular services
        $popularServices = $this->getPopularServices($branchId, $period);

        // Top therapists
        $topTherapists = $this->getTopTherapists($branchId, $period);

        return response()->json([
            'revenue_trend' => $revenueTrend,
            'bookings_trend' => $bookingsTrend,
            'popular_services' => $popularServices,
            'top_therapists' => $topTherapists,
        ]);
    }

    // Helper methods
    private function getRevenueToday($branchId = null)
    {
        $query = Transaction::whereDate('transaction_date', Carbon::today());

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->sum('total');
    }

    private function getBookingsToday($branchId = null)
    {
        $query = Booking::whereDate('booking_date', Carbon::today())
            ->whereIn('status', ['confirmed', 'in_progress', 'awaiting_payment']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->count();
    }

    private function getNewCustomersToday()
    {
        return User::where('role', 'customer')
            ->whereDate('created_at', Carbon::today())
            ->count();
    }

    private function getActiveTherapists($branchId = null)
    {
        $query = DB::table('therapists')->where('is_active', true);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->count();
    }

    private function getRevenueMonth($branchId = null)
    {
        $query = Transaction::whereYear('transaction_date', Carbon::now()->year)
            ->whereMonth('transaction_date', Carbon::now()->month);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->sum('total');
    }

    private function getBookingsMonth($branchId = null)
    {
        $query = Booking::whereYear('booking_date', Carbon::now()->year)
            ->whereMonth('booking_date', Carbon::now()->month);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->count();
    }

    private function getPendingPayments($branchId = null)
    {
        $query = Booking::where('payment_status', '!=', 'paid')
            ->whereIn('status', ['confirmed', 'awaiting_payment']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->sum('total_price');
    }

    private function getTodayAgenda($branchId = null)
    {
        $query = Booking::with(['user', 'service', 'therapist', 'room'])
            ->whereDate('booking_date', Carbon::today())
            ->whereIn('status', ['pending_payment', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])
            ->orderBy('start_time', 'asc');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->limit(10)->get();
    }

    private function getRoomStatus($branchId = null)
    {
        $now = Carbon::now();
        $rooms = DB::table('rooms')
            ->where('is_active', true)
            ->when($branchId, function ($q) use ($branchId) {
                return $q->where('branch_id', $branchId);
            })
            ->limit(4)
            ->get();

        $roomStatus = [];
        foreach ($rooms as $room) {
            $occupied = Booking::where('room_id', $room->id)
                ->whereDate('booking_date', $now->toDateString())
                ->where('start_time', '<=', $now->format('H:i:s'))
                ->where('end_time', '>=', $now->format('H:i:s'))
                ->whereIn('status', ['confirmed', 'in_progress'])
                ->first();

            $roomStatus[] = [
                'name' => $room->name,
                'status' => $occupied ? 'occupied' : 'available',
                'until' => $occupied ? $occupied->end_time : null,
            ];
        }

        return $roomStatus;
    }

    private function getAlerts($branchId = null)
    {
        $alerts = [];

        // Fetch recent unread notifications for the admin
        $userId = auth()->id() ?? 1; // Fallback for dev
        $notifications = DB::table('notifications')
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get();

        foreach ($notifications as $notification) {
            $type = 'info';
            if (in_array($notification->type, ['warning', 'stock_investory', 'staff_attendance'])) {
                $type = 'warning';
            } elseif ($notification->type === 'success' || $notification->type === 'payment_received') {
                $type = 'success';
            }

            $alerts[] = [
                'type' => $type,
                'message' => $notification->message,
            ];
        }

        // Check pending feedbacks
        $pendingFeedbacks = DB::table('feedbacks')
            ->whereNull('admin_reply')
            ->count();

        if ($pendingFeedbacks > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "$pendingFeedbacks feedback baru belum dibalas",
            ];
        }

        // Check pending payments
        $pendingPayments = Booking::where('payment_status', 'unpaid')
            ->where('status', 'pending_payment')
            ->whereDate('booking_date', '<=', Carbon::today())
            ->count();

        if ($pendingPayments > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "$pendingPayments booking menunggu pembayaran",
            ];
        }

        return $alerts;
    }

    private function getRevenueTrend($branchId, $period)
    {
        $query = Transaction::select(
            DB::raw('DATE(transaction_date) as date'),
            DB::raw('SUM(total) as revenue'),
            DB::raw('COUNT(*) as count')
        );

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        switch ($period) {
            case 'week':
                $query->where('transaction_date', '>=', Carbon::now()->subDays(7));
                break;
            case 'month':
                $query->whereYear('transaction_date', Carbon::now()->year)
                    ->whereMonth('transaction_date', Carbon::now()->month);
                break;
            case 'year':
                $query->whereYear('transaction_date', Carbon::now()->year);
                break;
        }

        return $query->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
    }

    private function getBookingsTrend($branchId, $period)
    {
        $query = Booking::select(
            DB::raw('DATE(booking_date) as date'),
            DB::raw('COUNT(*) as count')
        );

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        switch ($period) {
            case 'week':
                $query->where('booking_date', '>=', Carbon::now()->subDays(7));
                break;
            case 'month':
                $query->whereYear('booking_date', Carbon::now()->year)
                    ->whereMonth('booking_date', Carbon::now()->month);
                break;
            case 'year':
                $query->whereYear('booking_date', Carbon::now()->year);
                break;
        }

        return $query->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();
    }

    private function getPopularServices($branchId, $period)
    {
        $query = Booking::select('service_id', DB::raw('COUNT(*) as total'))
            ->with('service:id,name');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        switch ($period) {
            case 'week':
                $query->where('booking_date', '>=', Carbon::now()->subDays(7));
                break;
            case 'month':
                $query->whereYear('booking_date', Carbon::now()->year)
                    ->whereMonth('booking_date', Carbon::now()->month);
                break;
            case 'year':
                $query->whereYear('booking_date', Carbon::now()->year);
                break;
        }

        return $query->groupBy('service_id')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
    }

    private function getTopTherapists($branchId, $period)
    {
        $query = Booking::select('therapist_id', DB::raw('COUNT(*) as total'))
            ->with('therapist:id,name');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        switch ($period) {
            case 'week':
                $query->where('booking_date', '>=', Carbon::now()->subDays(7));
                break;
            case 'month':
                $query->whereYear('booking_date', Carbon::now()->year)
                    ->whereMonth('booking_date', Carbon::now()->month);
                break;
            case 'year':
                $query->whereYear('booking_date', Carbon::now()->year);
                break;
        }

        return $query->groupBy('therapist_id')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
    }
}
