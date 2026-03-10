<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\WhatsAppService;
use App\Services\EmailService;
use App\Models\AuditLog;
use App\Models\BookingAgendaLog;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BookingController extends Controller
{
    protected $whatsappService;
    protected $emailService;

    public function __construct(WhatsAppService $whatsappService, EmailService $emailService)
    {
        $this->whatsappService = $whatsappService;
        $this->emailService = $emailService;
    }

    public function index(Request $request)
    {
        $query = Booking::with([
            'user',
            'branch',
            'service',
            'therapist',
            'room',
            'payments',
            'items',
            'items.service',
            'items.therapist',
            'items.room',
            'transaction.items.product',
            'transaction.items.variant'
        ]);

        // Filters
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('booking_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('booking_date', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_ref', 'like', "%$search%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%");
                    });
            });
        }

        $bookings = $query->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($bookings);
    }

    public function show($id)
    {
        $booking = Booking::with([
            'user',
            'branch',
            'service',
            'therapist',
            'room',
            'items.service',
            'items.therapist',
            'items.room',
            'payments',
            'transaction.items.product',
            'transaction.items.variant',
            'transaction.loyaltyPoints',
            'transaction.pointRedemptions',
            'transaction.cashier',
            'feedback'
        ])->findOrFail($id);

        return response()->json($booking);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:awaiting_payment,confirmed,in_progress,completed,cancelled,no_show',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::findOrFail($id);

        $updateData = ['status' => $request->status];

        if ($request->status === 'completed') {
            $updateData['completed_at'] = Carbon::now();
            // Record Commission
            $this->recordCommission($booking);
        } elseif ($request->status === 'cancelled') {
            $updateData['cancelled_at'] = Carbon::now();
            // Notify Staff of Cancellation
            if ($booking->therapist) {
                if ($booking->therapist->phone) {
                    $this->whatsappService->sendStaffCancellationNotification($booking->therapist->phone, $booking);
                }
                if ($booking->therapist->email) {
                    $this->emailService->sendStaffCancellationNotification($booking->therapist->email, $booking);
                }
            }
        } elseif ($request->status === 'confirmed') {
            $updateData['confirmed_at'] = Carbon::now();
        }

        if ($request->has('notes')) {
            $updateData['notes'] = $request->notes;
        }

        $booking->update($updateData);

        AuditLog::log('update', 'Reservasi', "Updated booking status to {$request->status} for REF: {$booking->booking_ref}");

        // TODO: Send notification to customer

        return response()->json([
            'message' => 'Booking status updated successfully',
            'booking' => $booking->fresh(['user', 'service', 'therapist', 'room']),
        ]);
    }

    private function recordCommission(Booking $booking)
    {
        // Clear existing commissions to prevent duplicates/ensure correct recalculation
        \App\Models\StaffCommission::where('booking_id', $booking->id)->delete();

        $items = $booking->items;

        // Fallback for bookings without items (legacy or migrated)
        if ($items->isEmpty()) {
            // Treat the booking itself as a single item transaction
            if ($booking->therapist) {
                $this->createCommissionForSingleItem(
                    $booking,
                    $booking->therapist,
                    $booking->service,
                    $booking->service_price
                );
            }
            return;
        }

        // Process each item
        foreach ($items as $item) {
            $therapist = $item->therapist ?: ($item->therapist_id ? \App\Models\Therapist::find($item->therapist_id) : null);
            $service = $item->service ?: ($item->service_id ? \App\Models\Service::find($item->service_id) : null);

            if ($therapist && $service) {
                $this->createCommissionForSingleItem(
                    $booking,
                    $therapist,
                    $service,
                    $item->price
                );
            }
        }
    }

    private function createCommissionForSingleItem($booking, $therapist, $service, $itemPrice)
    {
        if (!$therapist || !$service)
            return;

        $commRule = $therapist->getCommissionForService($service->id);
        $basePrice = $itemPrice;

        // Fetch company settings
        $settings = \App\Models\CompanySettings::where('branch_id', $booking->branch_id)->first()
            ?? \App\Models\CompanySettings::whereNull('branch_id')->first();

        $calculateBeforeDiscount = $settings && $settings->commission_before_discount;
        $calculateAfterDiscount = $settings && $settings->commission_after_discount;

        // Apply Discount Logic
        $transaction = $booking->transaction;
        if (!$calculateBeforeDiscount && ($calculateAfterDiscount || !$settings)) {
            if ($transaction && $transaction->discount > 0 && $transaction->subtotal > 0) {
                // Calculate proportional discount for this item
                $ratio = $basePrice / ($transaction->subtotal ?: 1); // Avoid division by zero
                $discountShare = $transaction->discount * $ratio;

                $basePrice -= $discountShare;
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
            \App\Models\StaffCommission::create([
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
                'payment_date' => \Carbon\Carbon::now(),
                'status' => 'pending',
            ]);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'service_id' => 'required_without_all:is_blocked,service_ids|exists:services,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'nullable|exists:rooms,id',
            'therapist_id' => 'required|exists:therapists,id',
            'room_id' => 'nullable|exists:rooms,id',
            'user_id' => 'nullable|exists:users,id',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'is_blocked' => 'boolean',
            'block_reason' => 'nullable|string',
            'guest_names' => 'nullable|array',
            'guest_phones' => 'nullable|array',
            'guest_types' => 'nullable|array',
            'guest_ages' => 'nullable|array',
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for conflicts per item (therapist + start_time + duration of each item)
        $serviceIdsCheck = $request->input('service_ids', []);
        if (empty($serviceIdsCheck) && $request->service_id) {
            $serviceIdsCheck = [$request->service_id];
        }
        $startTimesCheck = $request->input('start_times', []);
        $therapistIdsCheck = $request->input('therapist_ids', []);
        $variantIdsCheck = $request->input('variant_ids', []);

        foreach ($serviceIdsCheck as $idx => $sId) {
            $s = \App\Models\Service::find($sId);
            $vId = $variantIdsCheck[$idx] ?? null;
            $variant = $vId ? \App\Models\ServiceVariant::find($vId) : null;
            $itemDuration = $variant ? $variant->duration : ($s?->duration ?? 60);
            $tId = $therapistIdsCheck[$idx] ?? $request->therapist_id;
            $itemStart = $startTimesCheck[$idx] ?? $request->start_time;
            $itemEnd = date('H:i', strtotime($itemStart) + ($itemDuration * 60));

            $conflictBooking = Booking::where('therapist_id', $tId)
                ->where('booking_date', $request->booking_date)
                ->whereNotIn('status', ['cancelled', 'awaiting_payment'])
                ->where(function ($q) use ($itemStart, $itemEnd) {
                    $q->where(function ($sub) use ($itemStart, $itemEnd) {
                        $sub->where('start_time', '>=', $itemStart)
                            ->where('start_time', '<', $itemEnd);
                    })->orWhere(function ($sub) use ($itemStart, $itemEnd) {
                        $sub->where('end_time', '>', $itemStart)
                            ->where('end_time', '<=', $itemEnd);
                    })->orWhere(function ($sub) use ($itemStart, $itemEnd) {
                        $sub->where('start_time', '<', $itemStart)
                            ->where('end_time', '>', $itemEnd);
                    });
                })
                ->with('therapist')
                ->first();

            if ($conflictBooking) {
                $therapistName = $conflictBooking->therapist->name ?? ('Terapis #' . $tId);
                $serviceName = $s ? ($variant ? $s->name . ' - ' . $variant->name : $s->name) : ('Layanan #' . $sId);
                return response()->json([
                    'errors' => [
                        'time' => [
                            "Konflik jadwal: {$therapistName} sudah memiliki reservasi pada pukul {$itemStart}–{$itemEnd} (Layanan: {$serviceName}). Silakan pilih waktu lain."
                        ]
                    ]
                ], 422);
            }
        }

        /*
        // Check therapist conflict in payment_logs (Removed to prevent blocking during payment)
        $pendingLogs = PaymentLog::where('status', 'pending')
            ->where('expired_at', '>', Carbon::now())
            ->get();

        foreach ($pendingLogs as $log) {
            $data = $log->booking_data;
            if (isset($data['booking_date']) && $data['booking_date'] === $request->booking_date && isset($data['therapist_id']) && $data['therapist_id'] == $request->therapist_id) {
                if (isset($data['start_time']) && isset($data['end_time']) && $startTime < $data['end_time'] && $endTime > $data['start_time']) {
                    return response()->json(['errors' => ['time' => ['Therapist has a pending booking for this time slot.']]], 422);
                }
            }
        }
        */

        if ($request->is_blocked) {
            $booking = Booking::create([
                'branch_id' => $request->branch_id,
                'therapist_id' => $request->therapist_id,
                'booking_date' => $request->booking_date,
                'start_time' => $request->start_time,
                'end_time' => date('H:i', strtotime($request->start_time) + ($request->duration ? $request->duration * 60 : 3600)),
                'duration' => $request->input('duration', 60),
                'is_blocked' => true,
                'block_reason' => $request->block_reason,
                'service_id' => null, // Assuming nullable
                'room_id' => null,
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'service_price' => 0,
                'room_charge' => 0,
                'total_price' => 0,
            ]);
            AuditLog::log('create', 'Reservasi', "Blocked time slot for therapist on {$request->booking_date}");
            return response()->json(['message' => 'Time blocked successfully', 'booking' => $booking], 201);
        }

        // Regular Booking - Create directly with expires_at
        $serviceIds = $request->input('service_ids', []);
        if (empty($serviceIds) && $request->service_id) {
            $serviceIds = [$request->service_id];
        }

        $totalDuration = 0;
        $totalServicePrice = 0;
        $totalRoomCharge = 0;
        $serviceModels = [];
        $roomIds = $request->input('room_ids', []);
        $startTimes = $request->input('start_times', []);
        $variantIds = $request->input('variant_ids', []);
        $guestNames = $request->input('guest_names', []);
        $guestPhones = $request->input('guest_phones', []);
        $guestTypes = $request->input('guest_types', []);
        $guestAges = $request->input('guest_ages', []);
        $therapistIds = $request->input('therapist_ids', []);

        // Initial start time for the first service or sequential calc
        $runningTime = $request->start_time;

        // Use these to track the overall booking range
        $overallStartTime = $request->start_time;
        $overallEndTime = $request->start_time;

        // First pass: Calculate items and determine overall times
        foreach ($serviceIds as $index => $sId) {
            $s = \App\Models\Service::find($sId);
            $vId = $variantIds[$index] ?? null;
            $variant = $vId ? \App\Models\ServiceVariant::find($vId) : null;
            $tId = $therapistIds[$index] ?? $request->therapist_id;
            $tId = $therapistIds[$index] ?? $request->therapist_id;

            if ($s) {
                // Determine Start Time for this item
                // If provided in array, use it. Otherwise use running sequential time.
                $itemStartTime = isset($startTimes[$index]) ? $startTimes[$index] : $runningTime;

                // Calculate item end time & price
                $itemDuration = $variant ? $variant->duration : $s->duration;
                $itemPrice = $variant ? $variant->price : $s->price;

                $itemEndTime = date('H:i', strtotime($itemStartTime) + ($itemDuration * 60));

                // Update running time for next sequential item
                $runningTime = $itemEndTime;

                // Update Overall Start/End
                if ($index === 0) {
                    $overallStartTime = $itemStartTime;
                } else {
                    if ($itemStartTime < $overallStartTime)
                        $overallStartTime = $itemStartTime;
                }

                // Assuming services typically go forward, but we check max end time
                if ($itemEndTime > $overallEndTime)
                    $overallEndTime = $itemEndTime;

                $totalDuration += $itemDuration;
                $totalServicePrice += $itemPrice;

                $rId = $roomIds[$index] ?? $request->room_id;
                $rCharge = 0;
                if ($rId) {
                    $r = \App\Models\Room::find($rId);
                    $rCharge = $r ? ($r->extra_charge ?? $r->price ?? 0) : 0;
                }

                $totalRoomCharge += $rCharge;
                $serviceModels[] = [
                    'service' => $s,
                    'variant' => $variant,
                    'room_id' => $rId,
                    'room_charge' => $rCharge,
                    'start_time' => $itemStartTime,
                    'end_time' => $itemEndTime,
                    'price' => $itemPrice,
                    'guest_name' => $guestNames[$index] ?? null,
                    'guest_phone' => $guestPhones[$index] ?? null,
                    'guest_type' => $guestTypes[$index] ?? 'dewasa',
                    'guest_age' => $guestAges[$index] ?? null,
                    'therapist_id' => $tId,
                ];
            }
        }

        // If explicitly set start_time in request differs from first item (unlikely but safe keeping)
        // actually overallStartTime is better derived from items now.

        // Calculate overall duration as difference between First Start and Last End?
        // Or just sum of service durations? 
        // "duration" field usually implies billable time or block time. 
        // If there is a gap, the "effective" duration might be longer.
        // Let's keep 'duration' as sum of service durations for now (active time).

        $booking = Booking::create([
            'branch_id' => $request->branch_id,
            'user_id' => $request->user_id,
            'guest_name' => $guestNames[0] ?? $request->guest_name,
            'guest_phone' => $guestPhones[0] ?? $request->guest_phone,
            'guest_type' => $guestTypes[0] ?? ($request->guest_type ?? 'dewasa'),
            'guest_age' => $guestAges[0] ?? ($request->guest_age ?? null),
            'service_id' => $serviceIds[0] ?? null,
            'therapist_id' => $therapistIds[0] ?? $request->therapist_id,
            'room_id' => $roomIds[0] ?? $request->room_id,
            'booking_date' => $request->booking_date,
            'start_time' => $overallStartTime, // Earliest start
            'end_time' => $overallEndTime,     // Latest end
            'duration' => $totalDuration,
            'service_price' => $totalServicePrice,
            'room_charge' => $totalRoomCharge,
            'total_price' => $totalServicePrice + $totalRoomCharge,
            'guest_count' => count($serviceIds),
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'notes' => (function () use ($serviceModels, $request) {
                $parts = [];
                foreach ($serviceModels as $i => $item) {
                    $name = $item['guest_name'] ?? ('Guest ' . ($i + 1));
                    $svc = $item['service']->name ?? 'Treatment';
                    $parts[] = "$name ($svc)";
                }
                $consolidated = implode(", ", $parts);
                return $request->notes ? "$consolidated | " . $request->notes : $consolidated;
            })(),
            // Legacy/Summary Guest Info for consistency
            'guest2_name' => $guestNames[1] ?? null,
            'guest2_whatsapp' => $guestPhones[1] ?? null,
            'guest2_age_type' => $guestTypes[1] ?? null,
            'guest2_age' => $guestAges[1] ?? null,
            'guest3_name' => $guestNames[2] ?? null,
            'guest3_whatsapp' => $guestPhones[2] ?? null,
            'guest3_age_type' => $guestTypes[2] ?? null,
            'guest3_age' => $guestAges[2] ?? null,
            'guest4_name' => $guestNames[3] ?? null,
            'guest4_whatsapp' => $guestPhones[3] ?? null,
            'guest4_age_type' => $guestTypes[3] ?? null,
            'guest4_age' => $guestAges[3] ?? null,
            'guest5_name' => $guestNames[4] ?? null,
            'guest5_whatsapp' => $guestPhones[4] ?? null,
            'guest5_age_type' => $guestTypes[4] ?? null,
            'guest5_age' => $guestAges[4] ?? null,
        ]);

        // Create Booking Items
        foreach ($serviceModels as $item) {
            \App\Models\BookingItem::create([
                'booking_id' => $booking->id,
                'service_id' => $item['service']->id,
                'service_variant_id' => $item['variant'] ? $item['variant']->id : null,
                'room_id' => $item['room_id'],
                'price' => $item['price'],
                'room_charge' => $item['room_charge'],
                'duration' => $item['variant'] ? $item['variant']->duration : $item['service']->duration,
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time'],
                'guest_name' => $item['guest_name'],
                'guest_phone' => $item['guest_phone'],
                'guest_type' => $item['guest_type'],
                'guest_age' => $item['guest_age'],
                'therapist_id' => $item['therapist_id'],
            ]);
        }


        AuditLog::log('create', 'Reservasi', "Created new booking REF: {$booking->booking_ref} for customer: " . ($booking->user->name ?? 'Guest'));

        // Notify Staff of New Booking
        if ($booking->therapist) {
            if ($booking->therapist->phone) {
                $this->whatsappService->sendStaffBookingNotification($booking->therapist->phone, $booking);
            }
            if ($booking->therapist->email) {
                $this->emailService->sendStaffBookingNotification($booking->therapist->email, $booking);
            }
        }

        return response()->json([
            'message' => 'Booking created successfully (expires in 60 seconds if not paid)',
            'booking' => $booking
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'service_id' => 'exists:services,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'nullable|exists:rooms,id',
            'therapist_id' => 'exists:therapists,id',
            'room_id' => 'nullable|exists:rooms,id',
            'booking_date' => 'date',
            'start_time' => 'date_format:H:i',
            'guest_names' => 'nullable|array',
            'guest_phones' => 'nullable|array',
            'guest_types' => 'nullable|array',
            'guest_ages' => 'nullable|array',
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => ['validation' => $validator->errors()]], 422);
        }

        // Check for conflicts on update
        $checkDate = $request->booking_date ?? $booking->booking_date;
        $checkTherapist = $request->therapist_id ?? $booking->therapist_id;

        $duration = 0;
        $totalPrice = 0;
        $serviceIds = $request->input('service_ids');
        $variantIds = $request->input('variant_ids', []);

        if ($serviceIds !== null) {
            foreach ($serviceIds as $index => $sId) {
                $s = \App\Models\Service::find($sId);
                $vId = $variantIds[$index] ?? null;
                $variant = $vId ? \App\Models\ServiceVariant::find($vId) : null;

                if ($s) {
                    $duration += $variant ? $variant->duration : $s->duration;
                    $totalPrice += $variant ? $variant->price : $s->price;
                }
            }
        } elseif ($request->has('service_id')) {
            $s = \App\Models\Service::find($request->service_id);
            // Single service update doesn't support variant in this legacy block? 
            // Better to assume service_ids array is always used now, but for backward compat:
            if ($s) {
                $duration = $s->duration;
                $totalPrice = $s->price;
            }
        } else {
            // Keep existing if not changing service
            $duration = $booking->duration;
            $totalPrice = $booking->service_price;
        }


        $startTime = $request->start_time ?? $booking->start_time;
        // recalculate End Time based on duration (if simple seq) - but we use overall logic later if service_ids set
        $endTime = date('H:i', strtotime($startTime) + ($duration * 60));

        // Per-item conflict check with detailed error messages
        $serviceIdsCheck = $request->input('service_ids', []);
        $startTimesCheck = $request->input('start_times', []);
        $therapistIdsCheck = $request->input('therapist_ids', []);
        $variantIdsCheck = $request->input('variant_ids', []);

        if (!empty($serviceIdsCheck)) {
            foreach ($serviceIdsCheck as $idx => $sId) {
                $s = \App\Models\Service::find($sId);
                $vId = $variantIdsCheck[$idx] ?? null;
                $variant = $vId ? \App\Models\ServiceVariant::find($vId) : null;
                $itemDuration = $variant ? $variant->duration : ($s?->duration ?? 60);
                $tId = $therapistIdsCheck[$idx] ?? $checkTherapist;
                $itemStart = $startTimesCheck[$idx] ?? $startTime;
                $itemEnd = date('H:i', strtotime($itemStart) + ($itemDuration * 60));

                $conflictBooking = Booking::where('therapist_id', $tId)
                    ->where('booking_date', $checkDate)
                    ->where('id', '!=', $id)
                    ->whereNotIn('status', ['cancelled', 'awaiting_payment'])
                    ->where(function ($q) use ($itemStart, $itemEnd) {
                        $q->where(function ($sub) use ($itemStart, $itemEnd) {
                            $sub->where('start_time', '>=', $itemStart)
                                ->where('start_time', '<', $itemEnd);
                        })->orWhere(function ($sub) use ($itemStart, $itemEnd) {
                            $sub->where('end_time', '>', $itemStart)
                                ->where('end_time', '<=', $itemEnd);
                        })->orWhere(function ($sub) use ($itemStart, $itemEnd) {
                            $sub->where('start_time', '<', $itemStart)
                                ->where('end_time', '>', $itemEnd);
                        });
                    })
                    ->with('therapist')
                    ->first();

                if ($conflictBooking) {
                    $therapistName = $conflictBooking->therapist->name ?? ('Terapis #' . $tId);
                    $serviceName = $s ? ($variant ? $s->name . ' - ' . $variant->name : $s->name) : ('Layanan #' . $sId);
                    return response()->json([
                        'errors' => [
                            'time' => [
                                "Konflik jadwal: {$therapistName} sudah memiliki reservasi pada pukul {$itemStart}–{$itemEnd} (Layanan: {$serviceName}). Silakan pilih waktu lain."
                            ]
                        ]
                    ], 422);
                }
            }
        } else {
            // Fallback single-service legacy check
            $singleConflict = Booking::where('therapist_id', $checkTherapist)
                ->where('booking_date', $checkDate)
                ->where('id', '!=', $id)
                ->whereNotIn('status', ['cancelled', 'awaiting_payment'])
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where(function ($sub) use ($startTime, $endTime) {
                        $sub->where('start_time', '>=', $startTime)->where('start_time', '<', $endTime);
                    })->orWhere(function ($sub) use ($startTime, $endTime) {
                        $sub->where('end_time', '>', $startTime)->where('end_time', '<=', $endTime);
                    })->orWhere(function ($sub) use ($startTime, $endTime) {
                        $sub->where('start_time', '<', $startTime)->where('end_time', '>', $endTime);
                    });
                })
                ->with('therapist')
                ->first();

            if ($singleConflict) {
                $therapistName = $singleConflict->therapist->name ?? ('Terapis #' . $checkTherapist);
                return response()->json([
                    'errors' => [
                        'time' => [
                            "Konflik jadwal: {$therapistName} sudah ada reservasi pada pukul {$startTime}–{$endTime}. Silakan pilih waktu lain."
                        ]
                    ]
                ], 422);
            }
        }

        /*
        // Check conflicts in payment_logs on update (Removed to prevent blocking during payment)
        $pendingLogs = PaymentLog::where('status', 'pending')
            ->where('expired_at', '>', Carbon::now())
            ->get();

        foreach ($pendingLogs as $log) {
            $data = $log->booking_data;
            if (isset($data['booking_date']) && $data['booking_date'] === $checkDate && isset($data['therapist_id']) && $data['therapist_id'] == $checkTherapist) {
                if (isset($data['start_time']) && isset($data['end_time']) && $startTime < $data['end_time'] && $endTime > $data['start_time']) {
                    return response()->json(['errors' => ['time' => ['Therapist has a pending booking for this time slot.']]], 422);
                }
            }
        }
        */

        $data = $request->only([
            'branch_id',
            'user_id',
            'guest_name',
            'guest_phone',
            'service_id',
            'therapist_id',
            'room_id',
            'booking_date',
            'start_time',
            'notes',
            'is_blocked',
            'block_reason',
            'guest_count',
            'guest_age'
        ]);



        $guestNames = $request->input('guest_names', []);
        $guestPhones = $request->input('guest_phones', []);
        $guestTypes = $request->input('guest_types', []);
        $guestAges = $request->input('guest_ages', []);
        $therapistIds = $request->input('therapist_ids', []);

        // Update guest info from the first guest in multi-list
        if ($guestNames && isset($guestNames[0])) {
            $data['guest_name'] = $guestNames[0];
        }
        if ($guestPhones && isset($guestPhones[0])) {
            $data['guest_phone'] = $guestPhones[0];
        }

        // Legacy/Summary Guest Info for consistency
        $data['guest2_name'] = $guestNames[1] ?? null;
        $data['guest2_whatsapp'] = $guestPhones[1] ?? null;
        $data['guest2_age_type'] = $guestTypes[1] ?? null;
        $data['guest2_age'] = $guestAges[1] ?? null;
        $data['guest3_name'] = $guestNames[2] ?? null;
        $data['guest3_whatsapp'] = $guestPhones[2] ?? null;
        $data['guest3_age_type'] = $guestTypes[2] ?? null;
        $data['guest3_age'] = $guestAges[2] ?? null;
        $data['guest4_name'] = $guestNames[3] ?? null;
        $data['guest4_whatsapp'] = $guestPhones[3] ?? null;
        $data['guest4_age_type'] = $guestTypes[3] ?? null;
        $data['guest4_age'] = $guestAges[3] ?? null;
        $data['guest5_name'] = $guestNames[4] ?? null;
        $data['guest5_whatsapp'] = $guestPhones[4] ?? null;
        $data['guest5_age_type'] = $guestTypes[4] ?? null;
        $data['guest5_age'] = $guestAges[4] ?? null;

        // Update main therapist to first therapist in list if available
        if ($therapistIds && isset($therapistIds[0])) {
            $data['therapist_id'] = $therapistIds[0];
        }

        if ($guestAges && isset($guestAges[0])) {
            $data['guest_age'] = $guestAges[0];
        }

        if ($guestTypes && isset($guestTypes[0])) {
            $data['guest_type'] = $guestTypes[0];
        }

        if ($serviceIds !== null) {
            $data['service_id'] = $serviceIds[0] ?? null;
            $data['service_price'] = $totalPrice;
            $data['duration'] = $duration;
            // $start = $request->start_time ?? $booking->start_time;
            // $data['end_time'] = date('H:i', strtotime($start) + ($duration * 60));

            // Sync Booking Items
            $booking->items()->delete();
            $roomIds = $request->input('room_ids', []);
            $startTimes = $request->input('start_times', []);
            $totalRC = 0;

            // Logic for calculating times similar to Store method
            $runningTime = $request->start_time ?? $booking->start_time;
            $overallStartTime = $runningTime;
            $overallEndTime = $runningTime;

            foreach ($serviceIds as $index => $sId) {
                $s = \App\Models\Service::find($sId);
                $vId = $variantIds[$index] ?? null;
                $variant = $vId ? \App\Models\ServiceVariant::find($vId) : null;
                $tId = $therapistIds[$index] ?? $request->input('therapist_id') ?? $booking->therapist_id;
                $tId = $therapistIds[$index] ?? $request->input('therapist_id') ?? $booking->therapist_id;

                if ($s) {
                    $rId = $roomIds[$index] ?? $request->room_id;
                    $rCharge = 0;
                    if ($rId) {
                        $r = \App\Models\Room::find($rId);
                        $rCharge = $r ? ($r->extra_charge ?? $r->price ?? 0) : 0;
                    }
                    $totalRC += $rCharge;

                    // Params for this item
                    $itemDuration = $variant ? $variant->duration : $s->duration;
                    $itemPrice = $variant ? $variant->price : $s->price;

                    // Time calc
                    $itemStartTime = isset($startTimes[$index]) ? $startTimes[$index] : $runningTime;
                    // End time based on item duration
                    $itemEndTime = date('H:i', strtotime($itemStartTime) + ($itemDuration * 60));

                    $runningTime = $itemEndTime; // for next sequential

                    if ($index === 0) {
                        $overallStartTime = $itemStartTime;
                    } else {
                        if ($itemStartTime < $overallStartTime)
                            $overallStartTime = $itemStartTime;
                    }
                    if ($itemEndTime > $overallEndTime)
                        $overallEndTime = $itemEndTime;

                    \App\Models\BookingItem::create([
                        'booking_id' => $booking->id,
                        'service_id' => $s->id,
                        'service_variant_id' => $variant ? $variant->id : null,
                        'room_id' => $rId,
                        'price' => $itemPrice,
                        'room_charge' => $rCharge,
                        'duration' => $itemDuration,
                        'start_time' => $itemStartTime,
                        'end_time' => $itemEndTime,
                        'guest_name' => $guestNames[$index] ?? null,
                        'guest_phone' => $guestPhones[$index] ?? null,
                        'guest_type' => $guestTypes[$index] ?? 'dewasa',
                        'guest_age' => $guestAges[$index] ?? null,
                        'therapist_id' => $tId,
                    ]);
                }
            }
            $data['room_charge'] = $totalRC;
            $data['room_id'] = $roomIds[0] ?? $request->room_id;
            $data['total_price'] = $totalPrice + $totalRC;
            $data['start_time'] = $overallStartTime;
            $data['end_time'] = $overallEndTime;

        } elseif ($request->has('service_id')) {
            $service = \App\Models\Service::find($request->service_id);
            // Single service update legacy support if needed, assuming no variant
            if ($service) {
                $data['service_price'] = $service->price;
                $data['duration'] = $service->duration;
                $start = $request->start_time ?? $booking->start_time;
                $data['end_time'] = date('H:i', strtotime($start) + ($service->duration * 60));
                $data['start_time'] = $start;

                // Sync Booking Items (single)
                $booking->items()->delete();

                $rId = $request->room_id ?? $booking->room_id;
                $rCharge = 0;
                if ($rId) {
                    $r = \App\Models\Room::find($rId);
                    $rCharge = $r ? ($r->extra_charge ?? $r->price ?? 0) : 0;
                }

                \App\Models\BookingItem::create([
                    'booking_id' => $booking->id,
                    'service_id' => $service->id,
                    'room_id' => $rId,
                    'price' => $service->price,
                    'room_charge' => $rCharge,
                    'duration' => $service->duration,
                    'start_time' => $start,
                    'end_time' => $data['end_time']
                ]);
            }
            $data['room_charge'] = $rCharge;
            $data['total_price'] = $service->price + $rCharge;

        } elseif ($request->has('start_time')) {
            // If only time changed, update end time based on existing duration
            $data['end_time'] = date('H:i', strtotime($request->start_time) + ($booking->duration * 60));
        }

        if ($request->has('room_id') && $serviceIds === null && !$request->has('service_id')) {
            $room = \App\Models\Room::find($request->room_id);
            $data['room_charge'] = $room ? $room->extra_charge ?? $room->price ?? 0 : 0;
            $data['total_price'] = $booking->service_price + $data['room_charge'];
        }

        $oldItems = $booking->items->toArray();
        $oldTotalPrice = $booking->total_price;

        $booking->update($data);

        // Log Agenda Change
        $newItems = $booking->fresh()->items->toArray();
        $newTotalPrice = $booking->total_price;
        $priceDiff = $newTotalPrice - $oldTotalPrice;

        if ($serviceIds !== null || $request->has('service_id') || $request->has('start_time') || $request->has('room_id')) {
            BookingAgendaLog::create([
                'booking_id' => $booking->id,
                'action' => 'update_agenda',
                'old_data' => [
                    'items' => $oldItems,
                    'total_price' => $oldTotalPrice
                ],
                'new_data' => [
                    'items' => $newItems,
                    'total_price' => $newTotalPrice
                ],
                'price_difference' => $priceDiff,
                'changed_by' => Auth::id(),
                'notes' => 'Update agenda reservasi oleh admin'
            ]);
        }


        AuditLog::log('update', 'Reservasi', "Updated booking details for REF: {$booking->booking_ref}");

        // Notify Staff of Reschedule/Update
        if ($booking->therapist) {
            if ($booking->therapist->phone) {
                $this->whatsappService->sendStaffRescheduleNotification($booking->therapist->phone, $booking);
            }
            if ($booking->therapist->email) {
                $this->emailService->sendStaffRescheduleNotification($booking->therapist->email, $booking);
            }
        }
        
        $this->recalculateBooking($booking);

        return response()->json(['message' => 'Booking updated successfully', 'booking' => $booking->fresh(['items'])]);
    }

    public function processRefund(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'refund_amount' => 'required|numeric|min:0',
            'refund_method' => 'required|in:cash,bank_transfer,wallet',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::with('payments')->findOrFail($id);

        if (!in_array($booking->status, ['cancelled', 'no_show'])) {
            return response()->json([
                'error' => 'Refund can only be processed for cancelled or no-show bookings'
            ], 400);
        }

        $totalPaid = $booking->payments()->where('status', 'success')->sum('amount');

        if ($request->refund_amount > $totalPaid) {
            return response()->json([
                'error' => 'Refund amount cannot exceed total paid amount'
            ], 400);
        }

        // TODO: Process actual refund via payment gateway
        // For now, just log the refund request

        $booking->update([
            'status' => 'refunded', // Update status to refunded
            'payment_status' => 'refunded',
            'refund_amount' => $request->refund_amount,
            'notes' => ($booking->notes ?? '') . "\n\nRefund: Rp " . number_format($request->refund_amount) .
                " via " . $request->refund_method . ". Reason: " . $request->reason
        ]);

        // Create Refund Transaction
        \App\Models\Transaction::create([
            'booking_id' => $booking->id,
            'branch_id' => $booking->branch_id,
            'cashier_id' => auth()->id() ?? null, // Assuming admin/staff is logged in
            'type' => 'refund',
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => $request->refund_amount,
            'payment_method' => $request->refund_method,
            'cash_received' => 0,
            'change_amount' => 0,
            'transaction_date' => Carbon::now(),
            'notes' => "Refund for Booking " . $booking->booking_ref . ". Reason: " . $request->reason
        ]);

        // Notify Customer
        $customer = $booking->user;
        $phone = $customer ? $customer->phone : ($booking->guest_phone ?? null);
        $email = $customer ? $customer->email : null;

        if ($phone) {
            $this->whatsappService->sendCustomerRefundNotification($phone, $booking, $request->refund_amount, 'processed');
        }
        if ($email) {
            $this->emailService->sendCustomerRefundNotification($email, $booking, $request->refund_amount, 'processed');
        }

        AuditLog::log('update', 'Reservasi', "Processed refund Rp " . number_format($request->refund_amount) . " for booking REF: {$booking->booking_ref}");

        return response()->json([
            'message' => 'Refund processed successfully',
            'refund_amount' => $request->refund_amount,
            'method' => $request->refund_method,
            'booking' => $booking->fresh(),
        ]);
    }

    public function reschedule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::with('items')->findOrFail($id);

        // Check availability
        $duration = $booking->duration;
        $startTime = $request->start_time;
        $endTime = date('H:i', strtotime($startTime) + ($duration * 60));

        $conflict = Booking::where('therapist_id', $booking->therapist_id)
            ->where('booking_date', $request->booking_date)
            ->where('id', '!=', $id)
            ->whereNotIn('status', ['cancelled', 'awaiting_payment'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($sub) use ($startTime, $endTime) {
                    $sub->where('start_time', '>=', $startTime)
                        ->where('start_time', '<', $endTime);
                })
                    ->orWhere(function ($sub) use ($startTime, $endTime) {
                        $sub->where('end_time', '>', $startTime)
                            ->where('end_time', '<=', $endTime);
                    })
                    ->orWhere(function ($sub) use ($startTime, $endTime) {
                        $sub->where('start_time', '<', $startTime)
                            ->where('end_time', '>', $endTime);
                    });
            })
            ->exists();

        if ($conflict) {
            return response()->json(['errors' => ['time' => ['Therapist is already booked for this time slot.']]], 422);
        }

        // Update Booking
        $booking->update([
            'booking_date' => $request->booking_date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'notes' => $booking->notes . ($request->reason ? "\nReschedule: " . $request->reason : ""),
        ]);

        // Update Items sequantially
        $runningTime = $startTime;
        foreach ($booking->items as $item) {
            $itemDuration = $item->duration;
            $itemEndTime = date('H:i', strtotime($runningTime) + ($itemDuration * 60));

            $item->update([
                'start_time' => $runningTime,
                'end_time' => $itemEndTime,
            ]);

            $runningTime = $itemEndTime;
        }

        AuditLog::log('update', 'Reservasi', "Rescheduled booking REF: {$booking->booking_ref} to {$request->booking_date} {$startTime}");

        // Notify Staff of Reschedule
        if ($booking->therapist) {
            if ($booking->therapist->phone) {
                $this->whatsappService->sendStaffRescheduleNotification($booking->therapist->phone, $booking);
            }
            if ($booking->therapist->email) {
                $this->emailService->sendStaffRescheduleNotification($booking->therapist->email, $booking);
            }
        }

        return response()->json([
            'message' => 'Booking rescheduled successfully',
            'booking' => $booking->fresh(['items'])
        ]);
    }

    public function cancelItem(Request $request, $id, $itemId)
    {
        $item = \App\Models\BookingItem::where('booking_id', $id)->where('id', $itemId)->first();

        if (!$item) {
            // Check for legacy/single-item fallback where ID might match
            $booking = Booking::find($id);
            if ($booking && $id == $itemId) {
                $booking->update([
                    'status' => 'cancelled',
                    'cancelled_at' => Carbon::now(),
                    'cancellation_reason' => $request->reason ?? 'Cancelled by admin'
                ]);
                AuditLog::log('update', 'Reservasi', "Cancelled legacy booking REF: {$booking->booking_ref}");
                return response()->json(['message' => 'Booking cancelled successfully', 'booking' => $booking->fresh(['items'])]);
            }
            // Throw 404 if truly not found
            abort(404, "Booking item not found (ID: {$itemId} for Booking: {$id})");
        }

        $booking = $item->booking;

        $oldTotalPrice = $booking->total_price;
        $oldItems = $booking->items->toArray();

        // Update Item Status instead of delete
        $item->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->reason ?? 'Cancelled by admin'
        ]);

        $this->recalculateBooking($booking);

        // Log Agenda Change (Cancellation)
        $newTotalPrice = $booking->total_price;
        $priceDiff = $newTotalPrice - $oldTotalPrice;

        BookingAgendaLog::create([
            'booking_id' => $booking->id,
            'booking_item_id' => $item->id,
            'action' => 'cancel_item',
            'old_data' => [
                'items' => $oldItems,
                'total_price' => $oldTotalPrice
            ],
            'new_data' => [
                'cancelled_item_id' => $item->id,
                'total_price' => $newTotalPrice
            ],
            'price_difference' => $priceDiff,
            'changed_by' => Auth::id(),
            'notes' => 'Pembatalan item layanan oleh admin'
        ]);

        AuditLog::log('update', 'Reservasi Item', "Cancelled item {$itemId} from booking REF: {$booking->booking_ref}");

        return response()->json([
            'message' => 'Item cancelled successfully',
            'booking' => $booking->fresh(['items'])
        ]);
    }

    public function rescheduleItem(Request $request, $id, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'therapist_id' => 'nullable|exists:therapists,id',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $item = \App\Models\BookingItem::where('booking_id', $id)->where('id', $itemId)->first();

        if (!$item) {
            // Check legacy fallback
            if ($id == $itemId) {
                return $this->update($request, $id); // Re-use update logic for single booking
            }
            abort(404, "Booking item not found to reschedule");
        }

        $sourceBooking = $item->booking;

        // Calculate new times
        $newDate = $request->booking_date;
        $newStart = $request->start_time;
        $duration = $item->duration;
        $newEnd = date('H:i', strtotime($newStart) + ($duration * 60));

        // Use new therapist if provided, otherwise keep existing
        $therapistId = $request->input('therapist_id', $item->therapist_id);

        // Check Availability for this specific item (therapist)
        if ($therapistId) {
            $conflict = Booking::where('therapist_id', $therapistId)
                ->where('booking_date', $newDate)
                ->whereNotIn('status', ['cancelled', 'awaiting_payment']) // Exclude cancelled
                ->where('id', '!=', $sourceBooking->id) // Don't conflict with its own original booking just in case
                ->where(function ($q) use ($newStart, $newEnd) {
                    $q->where(function ($sub) use ($newStart, $newEnd) {
                        $sub->where('start_time', '>=', $newStart)
                            ->where('start_time', '<', $newEnd);
                    })
                        ->orWhere(function ($sub) use ($newStart, $newEnd) {
                            $sub->where('end_time', '>', $newStart)
                                ->where('end_time', '<=', $newEnd);
                        })
                        ->orWhere(function ($sub) use ($newStart, $newEnd) {
                            $sub->where('start_time', '<', $newStart)
                                ->where('end_time', '>', $newEnd);
                        });
                })
                ->exists();

            if ($conflict) {
                return response()->json(['errors' => ['time' => ['Therapist is not available at this time.']]], 422);
            }
        }

        // CHECK IF IT'S THE ONLY ACTIVE ITEM IN THE BOOKING
        $remainingItemsCount = $sourceBooking->items()->where('status', 'active')->count();

        if ($remainingItemsCount === 1) {
            // ONLY ONE ITEM - Just update the existing booking
            $sourceBooking->update([
                'booking_date' => $newDate,
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'duration' => $duration,
                'therapist_id' => $therapistId,
                'notes' => $sourceBooking->notes . "\nRescheduled item: " . ($request->reason ?? 'Moved via Calendar'),
            ]);

            $item->update([
                'therapist_id' => $therapistId,
                'start_time' => $newStart,
                'end_time' => $newEnd
            ]);

            // Notify Staff of Reschedule
            if ($sourceBooking->therapist) {
                if ($sourceBooking->therapist->phone) {
                    $this->whatsappService->sendStaffRescheduleNotification($sourceBooking->therapist->phone, $sourceBooking);
                }
                if ($sourceBooking->therapist->email) {
                    $this->emailService->sendStaffRescheduleNotification($sourceBooking->therapist->email, $sourceBooking);
                }
            }

            return response()->json([
                'message' => 'Booking rescheduled successfully',
                'booking' => $sourceBooking->fresh(['items'])
            ]);
        }

        // MULTIPLE ITEMS - Split into new booking (Existing logic)
        // Use a derivative of the original ref to allow grouping in frontend
        $baseRef = $sourceBooking->booking_ref;
        // If it already has a suffix like -1, strip it or just add another
        $newRef = $baseRef . '-' . rand(1, 99);

        // Ensure uniqueness for newRef (rare conflict)
        while (\App\Models\Booking::where('booking_ref', $newRef)->exists()) {
            $newRef = $baseRef . '-' . rand(1, 99);
        }

        $newBooking = Booking::create([
            'branch_id' => $sourceBooking->branch_id,
            'booking_ref' => $newRef,
            'user_id' => $sourceBooking->user_id,
            'guest_name' => $item->guest_name ?: $sourceBooking->guest_name,
            'guest_phone' => $item->guest_phone ?: $sourceBooking->guest_phone,
            'booking_date' => $newDate,
            'start_time' => $newStart,
            'end_time' => $newEnd,
            'duration' => $duration,
            'service_id' => $item->service_id,
            'therapist_id' => $therapistId, // Updated therapist
            'room_id' => $item->room_id,
            'service_price' => $item->price,
            'room_charge' => $item->room_charge,
            'total_price' => $item->price + $item->room_charge,
            'guest_count' => 1,
            'status' => $sourceBooking->status, // Inherit status
            'payment_status' => $sourceBooking->payment_status, // Inherit payment status
            'notes' => "Rescheduled from {$sourceBooking->booking_ref}. " . ($request->reason ?? ''),
        ]);

        // Move Item to New Booking
        $item->update([
            'booking_id' => $newBooking->id,
            'therapist_id' => $therapistId, // Updated therapist
            'start_time' => $newStart,
            'end_time' => $newEnd
        ]);

        $oldTotalPrice = $sourceBooking->total_price;
        $oldItems = $sourceBooking->items->toArray();

        // Recalculate OLD Booking
        $this->recalculateBooking($sourceBooking);

        // Log Agenda Change (Move Item to New Booking)
        $newTotalPrice = $sourceBooking->total_price;
        $priceDiff = $newTotalPrice - $oldTotalPrice;

        BookingAgendaLog::create([
            'booking_id' => $sourceBooking->id,
            'booking_item_id' => $item->id,
            'action' => 'reschedule_item_move',
            'old_data' => [
                'items' => $oldItems,
                'total_price' => $oldTotalPrice
            ],
            'new_data' => [
                'moved_item_id' => $item->id,
                'new_booking_id' => $newBooking->id,
                'total_price' => $newTotalPrice
            ],
            'price_difference' => $priceDiff,
            'changed_by' => Auth::id(),
            'notes' => 'Pemindahan item ke booking baru oleh admin: ' . $newBooking->booking_ref
        ]);

        // Notify Staff of New Rescheduled Booking
        if ($newBooking->therapist) {
            if ($newBooking->therapist->phone) {
                $this->whatsappService->sendStaffBookingNotification($newBooking->therapist->phone, $newBooking);
            }
            if ($newBooking->therapist->email) {
                $this->emailService->sendStaffBookingNotification($newBooking->therapist->email, $newBooking);
            }
        }

        AuditLog::log('update', 'Reservasi', "Split item from REF: {$sourceBooking->booking_ref} to new Booking REF: {$newBooking->booking_ref} (Reschedule)");

        return response()->json([
            'message' => 'Item rescheduled to new booking successfully',
            'new_booking' => $newBooking,
            'old_booking' => $sourceBooking->fresh()
        ]);
    }

    public function completeItem(Request $request, $id, $itemId)
    {
        $item = \App\Models\BookingItem::where('booking_id', $id)->where('id', $itemId)->first();

        if (!$item) {
            $booking = Booking::find($id);
            if ($booking && $id == $itemId) {
                $booking->update([
                    'status' => 'completed',
                    'completed_at' => Carbon::now(),
                ]);
                $this->recordCommission($booking);
                AuditLog::log('update', 'Reservasi', "Completed legacy booking REF: {$booking->booking_ref}");
                return response()->json(['message' => 'Booking completed successfully', 'booking' => $booking->fresh(['items'])]);
            }
            abort(404, "Booking item not found to complete");
        }

        $sourceBooking = $item->booking;

        // CHECK IF IT'S THE ONLY ACTIVE ITEM IN THE BOOKING
        $remainingItemsCount = $sourceBooking->items()->where('status', 'active')->count();

        if ($remainingItemsCount === 1) {
            // ONLY ONE ITEM - Just update the existing booking to completed
            $sourceBooking->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
                'notes' => $sourceBooking->notes . "\nCompleted item: " . $item->id,
            ]);

            // Item is already at the right booking, nothing to change except maybe status if we want to track it
            // But currently items are moved to completed bookings and kept as 'active'?
            // Actually, keep status as active but the booking status is completed.

            // Record Commission
            $this->recordCommission($sourceBooking);

            AuditLog::log('update', 'Reservasi Item', "Completed only item {$itemId} in booking REF: {$sourceBooking->booking_ref}");

            return response()->json([
                'message' => 'Booking completed successfully',
                'booking' => $sourceBooking->fresh(['items'])
            ]);
        }

        // MULTIPLE ITEMS - Split into new booking (Existing logic)
        $baseRef = $sourceBooking->booking_ref;
        $newRef = $baseRef . '-' . rand(1, 99);

        // Ensure uniqueness for newRef
        while (\App\Models\Booking::where('booking_ref', $newRef)->exists()) {
            $newRef = $baseRef . '-' . rand(1, 99);
        }

        $newBooking = Booking::create([
            'branch_id' => $sourceBooking->branch_id,
            'booking_ref' => $newRef,
            'user_id' => $sourceBooking->user_id,
            'guest_name' => $item->guest_name ?: $sourceBooking->guest_name,
            'guest_phone' => $item->guest_phone ?: $sourceBooking->guest_phone,
            'booking_date' => $sourceBooking->booking_date, // Keep same date
            'start_time' => $item->start_time,
            'end_time' => $item->end_time,
            'duration' => $item->duration,
            'service_id' => $item->service_id,
            'therapist_id' => $item->therapist_id,
            'room_id' => $item->room_id,
            'service_price' => $item->price,
            'room_charge' => $item->room_charge,
            'total_price' => $item->price + $item->room_charge, // Added total_price
            'guest_count' => 1,
            'status' => 'completed',
            'payment_status' => $sourceBooking->payment_status, // Inherit payment status
            'notes' => "Completed individually from {$sourceBooking->booking_ref}.",
            'created_by' => $request->user() ? $request->user()->id : null,
            'completed_at' => Carbon::now()
        ]);

        // Move Item to New Booking
        $item->update([
            'booking_id' => $newBooking->id
        ]);

        // Recalculate OLD Booking
        $this->recalculateBooking($sourceBooking);

        // Record Commission
        $this->recordCommission($newBooking);

        AuditLog::log('update', 'Reservasi Item', "Completed item {$itemId} from booking REF: {$sourceBooking->booking_ref}");

        return response()->json([
            'message' => 'Item completed successfully',
            'new_booking' => $newBooking,
            'old_booking' => $sourceBooking->fresh()
        ]);
    }

    public function refundItem(Request $request, $id, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'refund_amount' => 'required|numeric|min:0',
            'refund_method' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $item = \App\Models\BookingItem::where('booking_id', $id)->where('id', $itemId)->first();

        if (!$item) {
            if ($id == $itemId) {
                return $this->processRefund($request, $id);
            }
            abort(404, "Booking item not found to refund");
        }

        $booking = $item->booking;

        $oldTotalPrice = $booking->total_price;
        $oldItems = $booking->items->toArray();

        // Update Item Status
        $item->update([
            'status' => 'refunded',
            'refund_amount' => $request->refund_amount,
            'cancellation_reason' => $request->reason ?? 'Refunded by admin'
        ]);

        // Update main booking refund total
        $booking->update([
            'refund_amount' => ($booking->refund_amount ?? 0) + $request->refund_amount,
        ]);

        $this->recalculateBooking($booking);

        // Log Agenda Change (Refund)
        $newTotalPrice = $booking->total_price;
        $priceDiff = $newTotalPrice - $oldTotalPrice;

        BookingAgendaLog::create([
            'booking_id' => $booking->id,
            'booking_item_id' => $item->id,
            'action' => 'refund_item',
            'old_data' => [
                'items' => $oldItems,
                'total_price' => $oldTotalPrice
            ],
            'new_data' => [
                'refunded_item_id' => $item->id,
                'refund_amount' => $request->refund_amount,
                'total_price' => $newTotalPrice
            ],
            'price_difference' => $priceDiff,
            'changed_by' => Auth::id(),
            'notes' => 'Refund item layanan oleh admin'
        ]);

        // Log transaction (Refund)
        \App\Models\Transaction::create([
            'booking_id' => $booking->id,
            'branch_id' => $booking->branch_id,
            'type' => 'refund',
            'total' => $request->refund_amount,
            'payment_method' => $request->refund_method,
            'transaction_date' => Carbon::now(),
            'notes' => "Item Refund (Item #{$itemId}) for Booking " . $booking->booking_ref . ". Reason: " . $request->reason
        ]);

        // Notify Customer
        $customer = $booking->user;
        $phone = $customer ? $customer->phone : ($booking->guest_phone ?? null);
        $email = $customer ? $customer->email : null;

        if ($phone) {
            $this->whatsappService->sendCustomerRefundNotification($phone, $booking, $request->refund_amount, 'processed');
        }
        if ($email) {
            $this->emailService->sendCustomerRefundNotification($email, $booking, $request->refund_amount, 'processed');
        }

        AuditLog::log('update', 'Reservasi Item', "Refunded item {$itemId} from booking REF: {$booking->booking_ref} amount Rp " . number_format($request->refund_amount));

        return response()->json([
            'message' => 'Item refunded successfully',
            'booking' => $booking->fresh(['items'])
        ]);
    }


    private function recalculateBooking(Booking $booking)
    {
        $remainingItems = $booking->items()->where('status', 'active')->get();

        if ($remainingItems->isEmpty()) {
            $booking->update(['status' => 'cancelled']);
        } else {
            $minStart = $remainingItems->min('start_time');
            $maxEnd = $remainingItems->max('end_time');
            $totalServicePrice = $remainingItems->sum('price');
            $totalRoomCharge = $remainingItems->sum('room_charge'); 
            $guestCount = $remainingItems->count();
            $newTotalPrice = $totalServicePrice + $totalRoomCharge;

            // Calculate total paid from successful payments
            $totalPaid = $booking->payments()->where('status', 'success')->sum('amount');

            // Determine new payment status based on price change
            $paymentStatus = 'unpaid';
            if ($totalPaid >= $newTotalPrice && $newTotalPrice > 0) {
                $paymentStatus = 'paid';
            } elseif ($totalPaid > 0) {
                $paymentStatus = 'partial';
            }

            $booking->update([
                'start_time' => $minStart,
                'end_time' => $maxEnd,
                'service_price' => $totalServicePrice,
                'room_charge' => $totalRoomCharge,
                'total_price' => $newTotalPrice,
                'guest_count' => $guestCount,
                'payment_status' => $paymentStatus
            ]);

            // Detect overpayment (case where service changed to cheaper price after full payment)
            if ($totalPaid > $newTotalPrice) {
                // Optional: We could log this or update notes
                $excess = $totalPaid - $newTotalPrice;
                $booking->update([
                    'notes' => $booking->notes . "\n[SISTEM] Terdeteksi kelebihan bayar: Rp " . number_format($excess) . " akibat penyesuaian harga layanan."
                ]);
            }
        }
    }

    public function agendaLogs($id)
    {
        $logs = BookingAgendaLog::with(['user', 'bookingItem.service'])
            ->where('booking_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }
}
