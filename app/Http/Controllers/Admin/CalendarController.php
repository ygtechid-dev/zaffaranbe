<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Therapist;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->input('branch_id');
        $date = $request->input('date', Carbon::today()->toDateString());
        $view = $request->input('view', 'day'); // day, week, month

        $bookings = Booking::with(['user', 'service', 'therapist', 'room', 'payments', 'items.service', 'items.therapist', 'items.room', 'transaction.items.product', 'transaction.items.variant'])
            ->when($branchId && $branchId !== 'all', function ($q) use ($branchId) {
                return $q->where('branch_id', $branchId);
            })
            ->where(function ($q) {
                $q->where('is_blocked', true)
                    ->orWhere(function ($q2) {
                        // Show all non-cancelled bookings (including unpaid)
                        $q2->whereIn('status', ['confirmed', 'in_progress', 'awaiting_payment', 'completed', 'pending']);
                    });
            });

        switch ($view) {
            case 'day':
                $bookings->whereDate('booking_date', $date);
                break;
            case 'week':
                $startOfWeek = Carbon::parse($date)->startOfWeek();
                $endOfWeek = Carbon::parse($date)->endOfWeek();
                $bookings->whereBetween('booking_date', [$startOfWeek, $endOfWeek]);
                break;
            case 'month':
                $bookings->whereYear('booking_date', Carbon::parse($date)->year)
                    ->whereMonth('booking_date', Carbon::parse($date)->month);
                break;
        }

        $bookings = $bookings->orderBy('booking_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        // Format for frontend
        $formattedBookings = $bookings->flatMap(function ($booking) {
            $entries = [];

            // Base data shared by all entries for this booking
            $baseData = [
                'id' => $booking->id,
                'date' => $booking->booking_date instanceof \Carbon\Carbon ? $booking->booking_date->toDateString() : $booking->booking_date,
                'customer' => $booking->user ? $booking->user->name : ($booking->guest_name ?? ($booking->is_blocked ? 'Blocked Time' : 'Walk-in')),
                'customerPhone' => $booking->user ? $booking->user->phone : ($booking->guest_phone ?? null),
                'status' => $booking->status,
                'paymentStatus' => $booking->payment_status,
                'totalPaid' => $booking->payments->where('status', 'success')->sum('amount'),
                'invoiceNumber' => $booking->booking_ref,
                'notes' => $booking->notes,
                'isBlocked' => $booking->is_blocked,
                'blockReason' => $booking->block_reason,
                'user_id' => $booking->user_id,
                'promoCode' => $booking->promo_code,
                'discountAmount' => (float) $booking->discount_amount,
                'guestCount' => $booking->items->unique(function ($item) use ($booking) {
                    $name = $item->guest_name ?? $booking->guest_name ?? 'Guest';
                    $phone = $item->guest_phone ?? $booking->guest_phone ?? '';
                    return strtolower(trim($name . '-' . $phone));
                })->count(),
                'products' => $booking->transaction && $booking->transaction->items
                    ? $booking->transaction->items->filter(function ($item) {
                        return $item->type === 'product' || $item->product_id;
                    })->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->product->name ?? $item->name ?? 'Produk',
                            'quantity' => $item->quantity,
                            'price' => (float) $item->price,
                            'subtotal' => (float) $item->subtotal,
                            'variant' => $item->variant->name ?? null,
                        ];
                    })->values()
                    : [],
            ];

            // Map guests for the detail modal
            $guests = $booking->items->map(function ($item) use ($booking) {
                return [
                    'id' => $item->id,
                    'name' => $item->guest_name ?? $booking->guest_name,
                    'phone' => $item->guest_phone ?? $booking->guest_phone,
                    'type' => $item->guest_type,
                    'age' => $item->guest_age,
                    'serviceId' => $item->service_id,
                    'serviceName' => $item->service->name ?? '',
                    'servicePrice' => $item->price,
                    'serviceDuration' => $item->duration,
                    'roomId' => $item->room_id,
                    'roomName' => $item->room->name ?? 'Regular',
                    'roomPrice' => $item->room_charge,
                    'staffId' => $item->therapist_id ?? $booking->therapist_id,
                    'staffName' => ($item->therapist ?? $booking->therapist)->name ?? 'Staff',
                    'startTime' => substr($item->start_time, 0, 5),
                ];
            })->values();
            $baseData['guests'] = $guests;

            if ($booking->items->count() > 0 && !$booking->is_blocked) {
                // Group items by therapist to create separate calendar blocks
                $therapistsInItems = $booking->items->groupBy(function ($item) use ($booking) {
                    return $item->therapist_id ?? $booking->therapist_id;
                });

                foreach ($therapistsInItems as $therapistId => $items) {
                    $firstItem = $items->first();
                    $entries[] = array_merge($baseData, [
                        'staffId' => $therapistId,
                        'start' => substr($firstItem->start_time, 0, 5),
                        'duration' => $firstItem->duration, // Calendar block reflects the item's duration
                        'customer' => $baseData['customer'],
                        'service' => $items->count() > 1 ? $items->count() . ' Layanan' : ($firstItem->service->name ?? ''),
                        'servicePrice' => $items->sum('price'),
                        'roomId' => $firstItem->room_id ?? $booking->room_id,
                        'roomName' => $firstItem->room ? $firstItem->room->name : ($booking->room ? $booking->room->name : 'Regular'),
                        'roomPrice' => $items->sum('room_charge'),
                    ]);
                }
            } else {
                // Fallback for blocked time or legacy bookings without items
                $entries[] = array_merge($baseData, [
                    'staffId' => $booking->therapist_id,
                    'start' => substr($booking->start_time, 0, 5),
                    'duration' => $booking->duration,
                    'service' => $booking->service ? $booking->service->name : '',
                    'servicePrice' => $booking->service_price,
                    'roomId' => $booking->room_id,
                    'roomName' => $booking->room ? $booking->room->name : 'Regular',
                    'roomPrice' => $booking->room_charge,
                ]);
            }

            return $entries;
        });

        return response()->json([
            'view' => $view,
            'date' => $date,
            'bookings' => $formattedBookings,
        ]);
    }

    public function therapistSchedule(Request $request)
    {
        $branchId = $request->input('branch_id');
        $startDate = $request->input('start_date', Carbon::today()->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());

        $therapists = Therapist::with([
            'schedules' => function ($q) use ($startDate, $endDate) {
                // Fetch recurring AND specific schedules in range
                $q->whereNull('date') // Recurring
                    ->orWhereBetween('date', [$startDate, $endDate]); // Specific
            }
        ])
            ->when($branchId && $branchId !== 'all', function ($q) use ($branchId) {
                return $q->where('branch_id', $branchId);
            })
            ->where('is_active', true)
            ->get();

        $shifts = [];
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);

        foreach ($therapists as $therapist) {
            foreach ($period as $date) {
                $dateString = $date->toDateString();
                $dayOfWeek = strtolower($date->format('l'));

                // Prioritize specific date
                $schedule = $therapist->schedules->where('date', $dateString)->first();

                // Fallback to recurring if not specific
                if (!$schedule) {
                    $schedule = $therapist->schedules
                        ->where('day_of_week', $dayOfWeek)
                        ->whereNull('date')
                        ->filter(function ($s) use ($dateString) {
                            $startOk = !$s->start_date || $dateString >= $s->start_date;
                            $endOk = !$s->end_date || $dateString <= $s->end_date;
                            return $startOk && $endOk;
                        })
                        ->first();
                }

                if (!$schedule) {
                    $schedule = $therapist->schedules
                        ->where('day_of_week', 'daily')
                        ->whereNull('date')
                        ->filter(function ($s) use ($dateString) {
                            $startOk = !$s->start_date || $dateString >= $s->start_date;
                            $endOk = !$s->end_date || $dateString <= $s->end_date;
                            return $startOk && $endOk;
                        })
                        ->first();
                }

                if ($schedule && $schedule->is_active) {
                    $shifts[] = [
                        'id' => $schedule->id,
                        'staffId' => $therapist->id,
                        'date' => $dateString,
                        'startTime' => substr($schedule->start_time, 0, 5),
                        'endTime' => substr($schedule->end_time, 0, 5),
                        'type' => $schedule->date ? 'specific' : 'recurring'
                    ];
                }
            }
        }

        return response()->json(['data' => $shifts]);
    }

    private function getStatusColor($status)
    {
        $colors = [
            'confirmed' => '#10b981',
            'in_progress' => '#3b82f6',
            'awaiting_payment' => '#f59e0b',
            'completed' => '#6b7280',
            'cancelled' => '#ef4444',
            'no_show' => '#9ca3af',
        ];

        return $colors[$status] ?? '#6b7280';
    }

    public function getSettings(Request $request)
    {
        $branchId = $request->input('branch_id');
        $settings = \App\Models\CalendarSettings::where('branch_id', $branchId)->first();

        if (!$settings) {
            // Default settings
            return response()->json([
                'start_hour' => 9,
                'end_hour' => 21,
                'slot_duration' => 15,
                'default_view' => 'day',
                'agenda_color' => 'staff',
                'week_start' => 'sunday',
                'staff_order' => 'default'
            ]);
        }

        return response()->json($settings);
    }

    public function updateSettings(Request $request)
    {
        $this->validate($request, [
            'branch_id' => 'nullable|exists:branches,id',
            'start_hour' => 'required|integer|min:0|max:23',
            'end_hour' => 'required|integer|min:0|max:23|gt:start_hour',
            'slot_duration' => 'required|integer|in:5,10,15,30,60', // Updated to match frontend options
            'default_view' => 'required|string|in:day,week,month',
            'agenda_color' => 'nullable|string',
            'week_start' => 'nullable|string',
            'staff_order' => 'nullable|string',
            'allow_reschedule' => 'nullable|boolean',
            'reschedule_deadline' => 'nullable|integer'
        ]);

        $branchId = $request->input('branch_id');

        $settings = \App\Models\CalendarSettings::updateOrCreate(
            ['branch_id' => $branchId],
            [
                'start_hour' => $request->start_hour,
                'end_hour' => $request->end_hour,
                'slot_duration' => $request->slot_duration,
                'default_view' => $request->default_view,
                'agenda_color' => $request->agenda_color ?? 'staff',
                'week_start' => $request->week_start ?? 'sunday',
                'staff_order' => $request->staff_order ?? 'default',
                'allow_reschedule' => $request->input('allow_reschedule', true),
                'reschedule_deadline' => $request->input('reschedule_deadline', 24)
            ]
        );

        return response()->json($settings);
    }
}
