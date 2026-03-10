<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CashierShift;
use App\Models\Therapist;
use App\Models\TherapistSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\StaffCommission;
use App\Models\PaymentLog;
use App\Models\AuditLog;
use App\Services\WhatsAppService;

class CashierController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }
    public function todaySchedule(Request $request)
    {
        // Get cashier's branch from active shift
        $activeShift = CashierShift::where('cashier_id', auth()->id())
            ->whereNull('clock_out')
            ->first();

        if (!$activeShift) {
            return response()->json(['error' => 'You must check in (start shift) first.'], 403);
        }

        $bookings = Booking::with(['user', 'service', 'therapist', 'room'])
            ->where('branch_id', $activeShift->branch_id)
            ->whereDate('booking_date', Carbon::today())
            ->whereIn('status', ['confirmed', 'in_progress', 'awaiting_payment'])
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($bookings);
    }

    /**
     * Get available therapists for a specific time slot
     */
    public function getAvailableTherapists(Request $request)
    {
        $branchId = $request->input('branch_id');
        $date = $request->input('date', Carbon::today()->toDateString());
        $startTime = $request->input('start_time');
        $duration = $request->input('duration', 60); // in minutes

        if (!$branchId || $branchId == '0') {
            // Try to look up active shift
            $activeShift = CashierShift::where('cashier_id', auth()->id())
                ->whereNull('clock_out')
                ->first();

            if ($activeShift) {
                $branchId = $activeShift->branch_id;
            } else {
                return response()->json(['error' => 'Branch ID is required (or must be in an active shift)'], 400);
            }
        }

        // Calculate end time
        $endTime = $startTime
            ? Carbon::parse($date . ' ' . $startTime)->addMinutes($duration)->format('H:i:s')
            : null;

        // Get all therapists in the branch
        $therapists = Therapist::where('branch_id', $branchId)
            ->where('is_active', true)
            ->get();

        $availableTherapists = [];
        $dayOfWeek = Carbon::parse($date)->format('l');
        $dayOfWeekLower = strtolower($dayOfWeek);

        // Get branch room capacity
        $totalRooms = \App\Models\Room::where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhere('is_global', true);
        })->where('is_active', true)->get()->sum(function ($room) {
            return $room->capacity * max(1, $room->quantity ?? 1);
        });

        // Get all bookings for that day to check room usage
        $bookingsForDay = Booking::where('branch_id', $branchId)
            ->where('booking_date', $date)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->get();

        // Get pending logs to check blocks
        $pendingLogs = PaymentLog::where('status', 'pending')
            ->where('expired_at', '>', Carbon::now())
            ->get();

        foreach ($therapists as $therapist) {
            // Check if therapist has schedule for this day
            $schedule = TherapistSchedule::where('therapist_id', $therapist->id)
                ->where('is_active', true)
                ->where(function ($q) use ($date, $dayOfWeekLower) {
                    $q->where('date', $date)
                        ->orWhere(function ($q2) use ($dayOfWeekLower) {
                            $q2->where('day_of_week', $dayOfWeekLower)
                                ->whereNull('date');
                        });
                })
                ->first();

            if (!$schedule) {
                continue;
            }

            // If no specific time requested, therapist is available if they have a schedule
            if (!$startTime) {
                $availableTherapists[] = [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'phone' => $therapist->phone,
                    'gender' => $therapist->gender,
                    'specialization' => $therapist->specialization,
                    'photo' => $therapist->photo,
                    'available' => true,
                    'schedule' => [
                        'start' => substr($schedule->start_time, 0, 5),
                        'end' => substr($schedule->end_time, 0, 5),
                    ]
                ];
                continue;
            }

            // Check if requested time is within therapist's working hours
            if ($startTime < $schedule->start_time || $endTime > $schedule->end_time) {
                continue;
            }

            // Check for conflicting bookings
            $hasConflict = $bookingsForDay->where('therapist_id', $therapist->id)
                ->filter(function ($b) use ($startTime, $endTime) {
                    return ($startTime < $b->end_time && $endTime > $b->start_time);
                })
                ->isNotEmpty();

            if ($hasConflict) {
                continue;
            }

            /* Removed pending logs conflict check */

            // Check Room Availability
            $roomUsage = 0;
            foreach ($bookingsForDay as $b) {
                if ($startTime < $b->end_time && $endTime > $b->start_time) {
                    $roomUsage++;
                }
            }

            /* Removed pending logs room usage check */

            if ($roomUsage >= $totalRooms) {
                continue; // All rooms are occupied
            }

            $availableTherapists[] = [
                'id' => $therapist->id,
                'name' => $therapist->name,
                'phone' => $therapist->phone,
                'gender' => $therapist->gender,
                'specialization' => $therapist->specialization,
                'photo' => $therapist->photo,
                'available' => true,
                'schedule' => [
                    'start' => substr($schedule->start_time, 0, 5),
                    'end' => substr($schedule->end_time, 0, 5),
                ]
            ];
        }

        return response()->json([
            'date' => $date,
            'start_time' => $startTime,
            'duration' => $duration,
            'therapists' => $availableTherapists
        ]);
    }

    public function checkIn(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        // Validation: Can only check in if status is confirmed (paid) or pending_payment (if allowed)
        if (!in_array($booking->status, ['confirmed', 'awaiting_payment'])) {
            return response()->json(['error' => 'Booking not ready for check-in'], 400);
        }

        // Check if it's the right day
        if ($booking->booking_date !== Carbon::today()->toDateString()) {
            return response()->json(['error' => 'Booking is not for today'], 400);
        }

        $booking->update([
            'status' => 'in_progress',
            'check_in_time' => Carbon::now()
        ]);

        AuditLog::log('update', 'POS', "Customer checked in for booking REF: {$booking->booking_ref}");

        return response()->json([
            'message' => 'Customer checked in successfully',
            'booking' => $booking
        ]);
    }

    public function completeBooking(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        if ($booking->status !== 'in_progress') {
            return response()->json(['error' => 'Booking is not in progress'], 400);
        }

        $booking->update([
            'status' => 'completed',
            'completed_at' => Carbon::now()
        ]);

        AuditLog::log('update', 'POS', "Booking REF: {$booking->booking_ref} marked as COMPLETED");

        // Record Commission
        $this->recordCommission($booking);

        // Send WhatsApp Notification (Thank You)
        $user = $booking->user;
        if ($user && $user->phone) {
            $this->whatsappService->sendCustomerNotification($user->phone, 'thankYou', [
                'customer' => $user,
                'booking' => $booking,
                'branch' => $booking->branch
            ], $booking->branch_id);
        }

        return response()->json([
            'message' => 'Treatment completed',
            'booking' => $booking
        ]);
    }

    public function dailyReport(Request $request)
    {
        $activeShift = CashierShift::where('cashier_id', auth()->id())
            ->whereNull('clock_out')
            ->first();

        if (!$activeShift) {
            return response()->json(['error' => 'No active shift'], 404);
        }

        $transactions = DB::table('transactions')
            ->where('cashier_id', auth()->id())
            ->where('branch_id', $activeShift->branch_id)
            ->whereBetween('transaction_date', [$activeShift->clock_in, Carbon::now()])
            ->select(
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(total) as total_amount'),
                DB::raw('SUM(CASE WHEN payment_method = "cash" THEN total ELSE 0 END) as total_cash'),
                DB::raw('SUM(CASE WHEN payment_method = "qris" THEN total ELSE 0 END) as total_qris')
            )
            ->first();

        return response()->json([
            'shift_info' => $activeShift,
            'report' => $transactions
        ]);
    }

    private function recordCommission(Booking $booking)
    {
        $therapist = $booking->therapist;
        if (!$therapist)
            return;

        // Prevent duplicates
        if (StaffCommission::where('booking_id', $booking->id)->exists()) {
            return;
        }

        $service = $booking->service;
        if (!$service)
            return;

        $commRule = $therapist->getCommissionForService($service->id);

        $basePrice = $booking->service_price;

        // Fetch company settings to determine commission calculation logic
        $settings = \App\Models\CompanySettings::where('branch_id', $booking->branch_id)->first()
            ?? \App\Models\CompanySettings::whereNull('branch_id')->first();

        $calculateBeforeDiscount = $settings && $settings->commission_before_discount;
        $calculateAfterDiscount = $settings && $settings->commission_after_discount;

        // Apply Discount if Transaction exists AND we are NOT specifically told to calculate BEFORE discount
        // OR we are explicitly told to calculate AFTER discount
        $transaction = $booking->transaction;
        if (!$calculateBeforeDiscount && ($calculateAfterDiscount || !$settings)) {
            if ($transaction && $transaction->discount > 0 && $transaction->subtotal > 0) {
                // Calculate proportional discount for this service
                $ratio = $basePrice / $transaction->subtotal;
                $discountShare = $transaction->discount * $ratio;

                $basePrice -= $discountShare;

                // Ensure base price is not negative
                if ($basePrice < 0)
                    $basePrice = 0;
            }
        }

        $amount = 0;

        if ($commRule['type'] === 'percent') {
            $amount = $basePrice * ($commRule['rate'] / 100);
        } else {
            $amount = $commRule['rate'];
        }

        if ($amount > 0) {
            StaffCommission::create([
                'staff_id' => $therapist->id,
                'branch_id' => $booking->branch_id,
                'booking_id' => $booking->id,
                'item_id' => $service->id,
                'item_type' => 'service',
                'item_name' => $service->name,
                'sales_amount' => $basePrice,
                'qty' => 1,
                'commission_percentage' => ($commRule['type'] === 'percent') ? $commRule['rate'] : 0,
                'commission_amount' => $amount,
                'payment_date' => Carbon::now(),
                'status' => 'pending',
            ]);
        }
    }
}
