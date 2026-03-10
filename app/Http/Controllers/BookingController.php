<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\TherapistSchedule;
use App\Models\ServiceVariant;
use App\Models\PaymentLog;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * Get available slots grouped by therapist
     */
    public function getTherapistAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'duration' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $branchId = $request->branch_id;
        $date = $request->booking_date;
        $duration = $request->duration;
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

        // 1. Get Branch Opening Hours and Settings
        $branch = \App\Models\Branch::findOrFail($branchId);
        $settings = \App\Models\CompanySettings::where('branch_id', $branchId)->first();

        $openingTime = $branch->opening_time;
        $closingTime = $branch->closing_time;
        $isClosed = false;

        $dayOfWeekMap = [
            'monday' => 'Senin',
            'tuesday' => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday' => 'Kamis',
            'friday' => 'Jumat',
            'saturday' => 'Sabtu',
            'sunday' => 'Minggu'
        ];
        $targetDayName = $dayOfWeekMap[$dayOfWeek] ?? null;

        $useSpecific = $settings ? ($settings->use_specific_operating_hours ?? false) : false;

        $operatingDays = $settings ? $settings->operating_days : $branch->operating_days;
        if ($useSpecific && !empty($operatingDays)) {
            $isFound = false;
            foreach ($operatingDays as $dayConfig) {
                // Handle both new object format and old string format
                $dayName = is_array($dayConfig) ? ($dayConfig['day'] ?? '') : $dayConfig;

                if ($dayName === $targetDayName) {
                    $isFound = true;
                    if (is_array($dayConfig)) {
                        $openingTime = $dayConfig['open'] ?? ($settings->default_open_time ?? $openingTime);
                        $closingTime = $dayConfig['close'] ?? ($settings->default_close_time ?? $closingTime);
                        if (isset($dayConfig['active']) && !$dayConfig['active']) {
                            $isClosed = true;
                        }
                    }
                    break;
                }
            }
            if (!$isFound) {
                $isClosed = true;
            }
        } else {
            // Global Mode - use default times from settings or branch
            $openingTime = $settings->default_open_time ?? $branch->opening_time;
            $closingTime = $settings->default_close_time ?? $branch->closing_time;

            // Check if today is one of the operating days (even in global mode, we might want to know if it's open)
            if (!empty($operatingDays)) {
                $isFound = false;
                foreach ($operatingDays as $dayConfig) {
                    $dayName = is_array($dayConfig) ? ($dayConfig['day'] ?? '') : $dayConfig;
                    if ($dayName === $targetDayName) {
                        $isFound = true;
                        // In global mode, if day is in list but explicitly marked inactive in object format
                        if (is_array($dayConfig) && isset($dayConfig['active']) && !$dayConfig['active']) {
                            $isClosed = true;
                        }
                        break;
                    }
                }
                if (!$isFound)
                    $isClosed = true;
            }
        }

        if ($isClosed) {
            return response()->json(['error' => 'Salon tutup pada hari tersebut'], 400);
        }

        if (!$openingTime || !$closingTime) {
            return response()->json(['error' => 'Branch operating hours not set'], 500);
        }

        $startOfDay = Carbon::parse($date . ' ' . $openingTime);
        $endOfDay = Carbon::parse($date . ' ' . $closingTime);

        // Handle over-midnight closing time (e.g., 09:00 to 03:00)
        if ($endOfDay->lte($startOfDay)) {
            $endOfDay->addDay();
        }

        // Get slot duration and therapist buffer time from calendar settings
        $calendarSettings = \App\Models\CalendarSettings::where('branch_id', $branchId)->first()
            ?? \App\Models\CalendarSettings::whereNull('branch_id')->first();
        $slotInterval = $calendarSettings ? $calendarSettings->slot_duration : 15;
        $therapistBufferTime = $calendarSettings ? ($calendarSettings->therapist_buffer_time ?? 15) : 15;

        // 2. Get All Active Therapists & Rooms
        $therapists = Therapist::where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('is_booking_online_enabled', true)
            ->where(function ($q) use ($date) {
                // Check start/end work dates
                $q->where(function ($q2) use ($date) {
                    $q2->whereNull('start_work_date')
                        ->orWhere('start_work_date', '<=', $date);
                })->where(function ($q3) use ($date) {
                    $q3->whereNull('end_work_date')
                        ->orWhere('end_work_date', '>=', $date);
                });
            })
            ->with([
                'schedules' => function ($q) use ($date, $dayOfWeek) {
                    $q->where('is_active', true)
                        ->where(function ($q2) use ($date, $dayOfWeek) {
                            $q2->where('date', $date)
                                ->orWhere(function ($q3) use ($dayOfWeek) {
                                    $q3->whereNull('date')->where('day_of_week', $dayOfWeek);
                                })
                                ->orWhere(function ($q4) {
                                    $q4->whereNull('date')->where('day_of_week', 'daily');
                                });
                        });
                }
            ])
            ->get();

        $totalRooms = Room::where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhere('is_global', true);
        })->where('is_active', true)->get()->sum(function ($room) {
            return $room->capacity * max(1, $room->quantity ?? 1);
        });

        // 3. Get All Bookings for that day
        $bookings = Booking::where('branch_id', $branchId)
            ->where('booking_date', $date)
            ->where(function ($q) {
                $q->where('is_blocked', true)
                    ->orWhereIn('status', ['confirmed', 'in_progress', 'completed'])
                    ->orWhere(function ($q2) {
                        $q2->whereIn('status', ['pending_payment', 'awaiting_payment'])
                            ->where(function ($q3) {
                                $q3->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', Carbon::now());
                            });
                    });
            })
            ->with([
                'items' => function ($q) {
                    // Ignore cancelled items
                    $q->whereNotIn('status', ['cancelled']);
                }
            ])
            ->get();


        // 4. Build Room Usage Map (Minute by Minute)
        // Array of size 1440 (minutes in a day)
        $roomUsage = array_fill(0, 1440, 0);

        foreach ($bookings as $b) {
            $bookingDate = $b->booking_date instanceof \Carbon\Carbon
                ? $b->booking_date->toDateString()
                : $b->booking_date;

            $itemsToProcess = [];
            if ($b->items && $b->items->count() > 0) {
                foreach ($b->items as $item) {
                    $itemsToProcess[] = [
                        'start_time' => $item->start_time,
                        'end_time' => $item->end_time,
                    ];
                }
            } else {
                $itemsToProcess[] = [
                    'start_time' => $b->start_time,
                    'end_time' => $b->end_time,
                ];
            }

            foreach ($itemsToProcess as $item) {
                if (!$item['start_time'] || !$item['end_time'])
                    continue;

                $bStart = Carbon::parse($bookingDate . ' ' . $item['start_time']);
                $bEnd = Carbon::parse($bookingDate . ' ' . $item['end_time']);

                // Convert to minutes from midnight
                $startMin = $bStart->hour * 60 + $bStart->minute;
                // Include buffer time for room preparation/cleaning
                $endMin = ($bEnd->hour * 60 + $bEnd->minute) + $therapistBufferTime;

                for ($i = $startMin; $i < $endMin; $i++) {
                    if (isset($roomUsage[$i])) {
                        $roomUsage[$i]++;
                    }
                }
            }
        }

        // 5. Generate Slots and Check Availability for each Therapist
        $result = [];

        foreach ($therapists as $therapist) {
            // Determine Priority Schedule: Date > WeekDay > Daily
            $schedule = $therapist->schedules->sortByDesc(function ($s) {
                if ($s->date)
                    return 3;
                if ($s->day_of_week != 'daily')
                    return 2;
                return 1;
            })->first();

            // If no schedule, therapist is not working
            if (!$schedule) {
                continue;
            }

            $availableSlots = [];

            if ($schedule) {
                $shiftStart = Carbon::parse($date . ' ' . $schedule->start_time);
                $shiftEnd = Carbon::parse($date . ' ' . $schedule->end_time);

                // Generate slots every {slotInterval} mins within therapist shift hours (not branch hours)
                $current = $shiftStart->copy();
                while ($current->copy()->addMinutes($duration)->lte($shiftEnd)) {
                    $slotStart = $current->copy();
                    $slotEnd = $current->copy()->addMinutes($duration);
                    $slotTimeStr = $slotStart->format('H:i');

                    // Increment for next loop
                    $current->addMinutes($slotInterval);

                    // Check Room Availability
                    $startMin = $slotStart->hour * 60 + $slotStart->minute;
                    $endMin = $slotEnd->hour * 60 + $slotEnd->minute;
                    $maxUsage = 0;
                    for ($m = $startMin; $m < $endMin; $m++) {
                        if (isset($roomUsage[$m]) && $roomUsage[$m] > $maxUsage) {
                            $maxUsage = $roomUsage[$m];
                        }
                    }

                    $roomAvailable = $maxUsage < $totalRooms;

                    // Check if time is in the past (for today)
                    $isPast = false;
                    $now = Carbon::now();
                    if ($slotStart->lt($now)) {
                        $isPast = true;
                    }

                    // Check Therapist Booking Conflict (including buffer time for rest)
                    $therapistBusy = false;
                    foreach ($bookings as $b) {
                        $bookingDate = $b->booking_date instanceof \Carbon\Carbon
                            ? $b->booking_date->toDateString()
                            : $b->booking_date;

                        $itemsToProcess = [];
                        if ($b->items && $b->items->count() > 0) {
                            foreach ($b->items as $item) {
                                if ($item->therapist_id == $therapist->id) {
                                    $itemsToProcess[] = [
                                        'start_time' => $item->start_time,
                                        'end_time' => $item->end_time,
                                    ];
                                }
                            }
                        } else {
                            if ($b->therapist_id == $therapist->id) {
                                $itemsToProcess[] = [
                                    'start_time' => $b->start_time,
                                    'end_time' => $b->end_time,
                                ];
                            }
                        }

                        foreach ($itemsToProcess as $item) {
                            if (!$item['start_time'] || !$item['end_time'])
                                continue;

                            $bStart = Carbon::parse($bookingDate . ' ' . $item['start_time']);
                            // Add buffer time to end time for therapist rest period
                            $bEnd = Carbon::parse($bookingDate . ' ' . $item['end_time'])->addMinutes($therapistBufferTime);

                            // Check overlap (including buffer period)
                            // A conflict exists if there is less than {therapistBufferTime} minutes between any two bookings
                            // Formula: N_start < E_end + Buffer AND N_end + Buffer > E_start
                            // if ($slotStart->lt($bEnd) && $slotEnd->copy()->addMinutes($therapistBufferTime)->gt($bStart)) {
                           if ($slotStart->lt($bEnd) && $slotEnd->gt($bStart)) {
                            $therapistBusy = true;
                                break 2;
                            }
                        }
                    }

                    // Determine availability and reason if not available
                    $isAvailable = !$isPast && !$therapistBusy && $roomAvailable;
                    $disabledReason = null;

                    if ($isPast) {
                        $disabledReason = 'Waktu sudah lewat';
                    } elseif ($therapistBusy) {
                        $disabledReason = 'Terapis sudah terbooking';
                    } elseif (!$roomAvailable) {
                        $disabledReason = 'Ruangan penuh';
                    }

                    // Add all slots with their availability status
                    $availableSlots[] = [
                        'time' => $slotTimeStr,
                        'available' => $isAvailable,
                        'disabled' => !$isAvailable,
                        'reason' => $disabledReason
                    ];
                }
            }

            // Build shifts array from schedule
            $shifts = [];
            if ($schedule) {
                $shifts[] = [
                    'start' => substr($schedule->start_time, 0, 5),
                    'end' => substr($schedule->end_time, 0, 5)
                ];
            }

            $result[] = [
                'id' => $therapist->id,
                'name' => $therapist->name,
                'photo' => $therapist->photo,
                'gender' => $therapist->gender,
                'specialization' => $therapist->specialization,
                'shifts' => $shifts,
                'slots' => $availableSlots
            ];
        }

        return response()->json($result);
    }

    /**
     * Get available therapists for a specific date, time, and duration
     */
  public function checkAvailability(Request $request)
{
    $validator = Validator::make($request->all(), [
        'branch_id' => 'required|exists:branches,id',
        'booking_date' => 'required|date|after_or_equal:today',
        'start_time' => 'required|date_format:H:i',
        'duration' => 'required|integer|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $bookingDateTime = Carbon::parse($request->booking_date . ' ' . $request->start_time);
    if ($bookingDateTime->lt(Carbon::now())) {
        return response()->json([
            'available_therapists' => [],
            'available_rooms' => [],
            'slot_available' => false,
            'message' => 'Waktu sudah lewat'
        ]);
    }

    $bookingDate = Carbon::parse($request->booking_date);
    $dayOfWeek = strtolower($bookingDate->format('l'));
    $startTime = Carbon::parse($request->start_time);
    $endTime = $startTime->copy()->addMinutes($request->duration);

    $calendarSettings = \App\Models\CalendarSettings::where('branch_id', $request->branch_id)->first()
        ?? \App\Models\CalendarSettings::whereNull('branch_id')->first();
    $therapistBufferTime = $calendarSettings ? ($calendarSettings->therapist_buffer_time ?? 15) : 15;

    $therapists = Therapist::where('branch_id', $request->branch_id)
        ->where('is_active', true)
        ->where('is_booking_online_enabled', true)
        ->where(function ($q) use ($bookingDate) {
            $dateStr = $bookingDate->toDateString();
            $q->where(function ($q2) use ($dateStr) {
                $q2->whereNull('start_work_date')
                    ->orWhere('start_work_date', '<=', $dateStr);
            })->where(function ($q3) use ($dateStr) {
                $q3->whereNull('end_work_date')
                    ->orWhere('end_work_date', '>=', $dateStr);
            });
        })
        ->with([
            'schedules' => function ($query) use ($dayOfWeek, $bookingDate) {
                $dateStr = $bookingDate->toDateString();
                $query->where('is_active', true)
                    ->where(function ($q2) use ($dateStr, $dayOfWeek) {
                        $q2->where('date', $dateStr)
                            ->orWhere(function ($q3) use ($dayOfWeek) {
                                $q3->whereNull('date')->where('day_of_week', $dayOfWeek);
                            })
                            ->orWhere(function ($q4) {
                                $q4->whereNull('date')->where('day_of_week', 'daily');
                            });
                    });
            }
        ])
        ->get();

    $allDayBookings = Booking::where('branch_id', $request->branch_id)
        ->where('booking_date', $request->booking_date)
        ->where(function ($q) {
            $q->where('is_blocked', true)
                ->orWhereIn('status', ['confirmed', 'in_progress', 'completed'])
                ->orWhere(function ($q2) {
                    $q2->whereIn('status', ['pending_payment', 'awaiting_payment'])
                        ->where(function ($q3) {
                            $q3->whereNull('expires_at')
                                ->orWhere('expires_at', '>', Carbon::now());
                        });
                });
        })
        ->with([
            'items' => function ($q) {
                $q->whereNotIn('status', ['cancelled']);
            }
        ])
        ->get();

    $reqStart = Carbon::parse($startTime->format('H:i:s'));
    $reqEnd = Carbon::parse($endTime->format('H:i:s'));

    $availableTherapists = $therapists->filter(function ($therapist) use ($startTime, $endTime, $therapistBufferTime, $reqStart, $reqEnd, $allDayBookings) {
        $schedule = $therapist->schedules->sortByDesc(function ($s) {
            if ($s->date) return 3;
            if ($s->day_of_week != 'daily') return 2;
            return 1;
        })->first();

         if ($therapist->id == 6) {
        \Log::info('DEBUG therapist 6', [
            'has_schedule' => !!$schedule,
            'schedule' => $schedule ? $schedule->toArray() : null,
            'reqStart' => $reqStart->format('H:i:s'),
            'reqEnd' => $reqEnd->format('H:i:s'),
            'shiftStart' => $schedule ? Carbon::parse($schedule->start_time)->format('H:i:s') : null,
            'shiftEnd' => $schedule ? Carbon::parse($schedule->end_time)->format('H:i:s') : null,
        ]);
    }
    
        if (!$schedule) return false;

        $shiftStart = Carbon::parse($schedule->start_time);
        $shiftEnd = Carbon::parse($schedule->end_time);

        if ($reqStart->lt($shiftStart) || $reqEnd->gt($shiftEnd)) return false;

        foreach ($allDayBookings as $b) {
            $itemsToProcess = [];
            if ($b->items && $b->items->count() > 0) {
                foreach ($b->items as $item) {
                    if ($item->therapist_id == $therapist->id) {
                        $itemsToProcess[] = $item;
                    }
                }
            } else {
                if ($b->therapist_id == $therapist->id) {
                    $itemsToProcess[] = $b;
                }
            }

            foreach ($itemsToProcess as $item) {
                if (!$item->start_time || !$item->end_time) continue;

                $bStart = Carbon::parse($item->start_time);
                // $bEnd = Carbon::parse($item->end_time)->addMinutes($therapistBufferTime);
$bEnd = Carbon::parse($item->end_time);

                 if ($therapist->id == 6) {
            \Log::info('DEBUG therapist 6 conflict check', [
                'booking_id' => $b->id,
                'item_start' => $item->start_time,
                'item_end' => $item->end_time,
                'bStart' => $bStart->format('H:i:s'),
                'bEnd' => $bEnd->format('H:i:s'),
                'reqStart' => $reqStart->format('H:i:s'),
                'reqEnd' => $reqEnd->format('H:i:s'),
                'conflict' => $reqStart->lt($bEnd) && $reqEnd->gt($bStart),
            ]);
        }
                
                // FIX: hapus buffer dari sisi slot baru, cukup cek apakah slot mulai sebelum bEnd
                if ($reqStart->lt($bEnd) && $reqEnd->gt($bStart)) {
                    return false;
                }
            }
        }

        return true;
    });

    $formattedTherapists = $availableTherapists->map(function ($therapist) {
        $shifts = [];
        foreach ($therapist->schedules as $schedule) {
            $shifts[] = [
                'start' => substr($schedule->start_time, 0, 5),
                'end' => substr($schedule->end_time, 0, 5)
            ];
        }

        return [
            'id' => $therapist->id,
            'name' => $therapist->name,
            'photo' => $therapist->photo,
            'gender' => $therapist->gender,
            'specialization' => $therapist->specialization,
            'is_active' => $therapist->is_active,
            'shifts' => $shifts
        ];
    })->values();

    $availableRooms = Room::where(function ($q) use ($request) {
        $q->where('branch_id', $request->branch_id)->orWhere('is_global', true);
    })
        ->where('is_active', true)
        ->get();

    foreach ($availableRooms as $room) {
        $overlappingCount = 0;
        foreach ($allDayBookings as $b) {
            $itemsToProcess = [];
            if ($b->items && $b->items->count() > 0) {
                foreach ($b->items as $item) {
                    if ($item->room_id == $room->id) {
                        $itemsToProcess[] = $item;
                    }
                }
            } else {
                if ($b->room_id == $room->id) {
                    $itemsToProcess[] = $b;
                }
            }

            foreach ($itemsToProcess as $item) {
                if (!$item->start_time || !$item->end_time) continue;

                $bStart = Carbon::parse($item->start_time);
                $bEnd = Carbon::parse($item->end_time)->addMinutes($therapistBufferTime);

                if ($reqStart->lt($bEnd) && $reqEnd->copy()->addMinutes($therapistBufferTime)->gt($bStart)) {
                    $overlappingCount++;
                }
            }
        }
        $room->bookings_count = $overlappingCount;
    }

    $availableRooms = $availableRooms
        ->filter(function ($room) {
            $qty = (int) ($room->quantity ?? 1);
            if ($qty < 1) $qty = 1;
            $totalCapacity = $room->capacity * $qty;
            return $room->bookings_count < $totalCapacity;
        })
        ->map(function ($room) {
            $qty = (int) ($room->quantity ?? 1);
            if ($qty < 1) $qty = 1;
            $totalCapacity = $room->capacity * $qty;
            $room->available_slots = $totalCapacity - $room->bookings_count;
            $room->quantity = $qty;
            return $room;
        })
        ->values();

    return response()->json([
        'available_therapists' => $formattedTherapists,
        'available_rooms' => $availableRooms,
        'slot_available' => $formattedTherapists->isNotEmpty() && $availableRooms->isNotEmpty(),
    ]);
}

    /**
     * Create a new booking
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'items' => 'nullable|array',
            'items.*.service_id' => 'required_with:items|exists:services,id',
            'items.*.therapist_id' => 'required_with:items|exists:therapists,id',
            'items.*.room_id' => 'nullable|exists:rooms,id',
            'items.*.start_time' => 'required_with:items|date_format:H:i',
            'items.*.variant_id' => 'nullable|exists:service_variants,id',
            'items.*.guest_age' => 'nullable|string',

            // Legacy/Single Item fields - only required if no items array AND no product_total
            'service_id' => 'required_without_all:items,product_total|exists:services,id',
            'therapist_id' => 'required_without_all:items,product_total|exists:therapists,id',
            'room_id' => 'nullable|exists:rooms,id',
            'start_time' => 'required_without_all:items,product_total|date_format:H:i',
            'guest_age' => 'nullable|string',

            'booking_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string',
            'payment_type' => 'nullable|in:full_payment,down_payment',
            'promo_code' => 'nullable|string',
            'product_total' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $items = $request->input('items', []);
        $productTotal = (float) $request->input('product_total', 0);

        if (empty($items) && $request->service_id) {
            // Convert single item to items array format for unified processing
            $items = [
                [
                    'service_id' => $request->service_id,
                    'therapist_id' => $request->therapist_id,
                    'room_id' => $request->room_id,
                    'start_time' => $request->start_time,
                    'variant_id' => $request->variant_id,
                    'guest_name' => $request->guest_name,
                    'guest_phone' => $request->guest_phone,
                    'guest_type' => $request->guest_type ?? 'dewasa',
                    'guest_age' => $request->guest_age,
                    'notes' => $request->notes,
                ]
            ];
        }

        if (empty($items) && $productTotal <= 0) {
            return response()->json(['error' => 'Harus memilih setidaknya satu layanan atau produk'], 422);
        }

        $processedItems = [];
        $totalPrice = 0;
        $totalServicePrice = 0;
        $totalRoomCharge = 0;
        $totalDuration = 0;

        foreach ($items as $index => $itemData) {
            $service = Service::findOrFail($itemData['service_id']);
            if (!$service->is_active || !$service->is_booking_online_enabled) {
                return response()->json(['error' => "Layanan '{$service->name}' (Item #" . ($index + 1) . ") tidak tersedia"], 400);
            }

            $duration = $service->duration;
            $price = $service->price;

            if (isset($itemData['variant_id'])) {
                $variant = ServiceVariant::find($itemData['variant_id']);
                if ($variant && $variant->service_id == $service->id) {
                    $duration = $variant->duration;
                    $price = $variant->special_price ?: $variant->price;
                }
            }

            $startTime = Carbon::parse($itemData['start_time']);
            $endTime = $startTime->copy()->addMinutes($duration);
            $newStart = $startTime->format('H:i:s');
            $newEnd = $endTime->format('H:i:s');

            // Conflict Check
            $calendarSettings = \App\Models\CalendarSettings::where('branch_id', $request->branch_id)->first()
                ?? \App\Models\CalendarSettings::whereNull('branch_id')->first();
            $buffer = $calendarSettings ? ($calendarSettings->therapist_buffer_time ?? 15) : 15;

         $conflict = Booking::where('booking_date', $request->booking_date)
    ->where('therapist_id', $itemData['therapist_id'])
    ->where(function ($query) use ($newStart, $newEnd) {
        $query->whereRaw("? < end_time", [$newStart])
            ->whereRaw("? > start_time", [$newEnd]);
    })
    ->whereIn('status', ['confirmed', 'in_progress'])
    ->exists();

            if ($conflict) {
                return response()->json(['error' => "Terapis untuk Item #" . ($index + 1) . " sudah memiliki booking lain pada jam tersebut."], 409);
            }

            $roomCharge = 0;
            if (isset($itemData['room_id'])) {
                $room = Room::find($itemData['room_id']);
                $roomCharge = $room ? $room->extra_charge : 0;
            }

            $processedItems[] = [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'therapist_id' => $itemData['therapist_id'],
                'room_id' => $itemData['room_id'] ?? null,
                'booking_date' => $request->booking_date,
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'duration' => $duration,
                'service_price' => $price,
                'room_charge' => $roomCharge,
                'total_price' => $price + $roomCharge,
                'variant_id' => $itemData['variant_id'] ?? null,
                'guest_name' => $itemData['guest_name'] ?? $request->guest_name,
                'guest_phone' => $itemData['guest_phone'] ?? $request->guest_phone,
                'guest_type' => $itemData['guest_type'] ?? 'dewasa',
                'guest_age' => $itemData['guest_age'] ?? $request->guest_age,
                'notes' => $itemData['notes'] ?? $request->notes,
            ];

            $totalServicePrice += $price;
            $totalRoomCharge += $roomCharge;
            $totalDuration += $duration;
        }

        $originalTotalPrice = $totalServicePrice + $totalRoomCharge + $productTotal;
        $discountAmount = 0;
        $promo = null;

        if ($request->promo_code) {
            $promo = \App\Models\Promo::active()->where('code', $request->promo_code)->first();
            if (!$promo) {
                return response()->json(['error' => 'Kode promo tidak valid atau sudah habis'], 400);
            }

            if ($promo->min_purchase > 0 && $originalTotalPrice < $promo->min_purchase) {
                return response()->json(['error' => 'Total transaksi belum memenuhi minimum pembelian promo'], 400);
            }

            if ($promo->type == 'percent') {
                $discountAmount = ($originalTotalPrice * $promo->discount) / 100;
                if ($promo->max_discount > 0) {
                    $discountAmount = min($discountAmount, $promo->max_discount);
                }
            } else {
                $discountAmount = $promo->discount;
            }
        }

        $totalPriceBeforeTax = max(0, $originalTotalPrice - $discountAmount);

        // Get Tax & Service Charge from Settings
        $companySettings = \App\Models\CompanySettings::where('branch_id', $request->branch_id)->first()
            ?? \App\Models\CompanySettings::first();

        $taxPercent = $companySettings ? ($companySettings->tax_percentage ?? 0) : 0;
        $serviceChargePercent = $companySettings ? ($companySettings->service_charge_percentage ?? 0) : 0;

        $serviceChargeAmount = round(($totalPriceBeforeTax * $serviceChargePercent) / 100);
        $taxAmount = round((($totalPriceBeforeTax + $serviceChargeAmount) * $taxPercent) / 100);

        $totalPrice = $totalPriceBeforeTax + $serviceChargeAmount + $taxAmount;

        // Ensure we uninitialized some fields if no items
        $finalServiceId = count($processedItems) > 0 ? $processedItems[0]['service_id'] : null;
        $finalTherapistId = count($processedItems) > 0 ? $processedItems[0]['therapist_id'] : null;
        $finalStartTime = count($processedItems) > 0 ? $processedItems[0]['start_time'] : null;
        $finalEndTime = count($processedItems) > 0 ? $processedItems[0]['end_time'] : null;
        $finalDuration = count($processedItems) > 0 ? $processedItems[0]['duration'] : null;
        $finalServicePrice = count($processedItems) > 0 ? $processedItems[0]['service_price'] : 0;

        $timeoutMinutes = $companySettings ? ($companySettings->payment_timeout ?? 15) : 15;

        // Calculate unique guest count
        $uniqueGuests = [];
        foreach ($processedItems as $item) {
            $guestKey = ($item['guest_name'] ?? 'Guest') . ($item['guest_phone'] ?? '');
            if (!in_array($guestKey, $uniqueGuests)) {
                $uniqueGuests[] = $guestKey;
            }
        }
        $guestCount = max(1, count($uniqueGuests));

        // Prepare consistent booking_data
        $bookingData = [
            'user_id' => auth()->id(),
            'branch_id' => $request->branch_id,
            'booking_date' => $request->booking_date,
            'total_price' => $totalPrice,
            'service_price' => $totalServicePrice,
            'room_charge' => $totalRoomCharge,
            'product_total' => $productTotal,
            'duration' => $totalDuration,
            'payment_type' => $request->payment_type ?? 'full_payment',
            'promo_code' => $promo ? $promo->code : null,
            'discount_amount' => $discountAmount,
            'service_charge_amount' => $serviceChargeAmount,
            'tax_amount' => $taxAmount,
            'items' => $processedItems, // Store all items
            'guest_count' => $guestCount,
        ];

        // Always include first item data at root for backward compatibility
        if (count($processedItems) > 0) {
            $bookingData = array_merge($processedItems[0], $bookingData);
        }

        $paymentLog = PaymentLog::create([
            'booking_data' => $bookingData,
            'status' => 'pending',
            'expired_at' => \Carbon\Carbon::now()->addMinutes($timeoutMinutes),
        ]);

        return response()->json([
            'message' => 'Booking initiated, awaiting payment',
            'payment_log_id' => $paymentLog->id,
            'expired_at' => $paymentLog->expired_at,
            // Mock a booking object for frontend compatibility if needed
            'booking' => [
                'id' => 'PL-' . $paymentLog->id,
                'status' => 'awaiting_payment',
                'payment_status' => 'unpaid',
            ]
        ], 201);
    }

    /**
     * Get all bookings for authenticated user
     */
    public function index(Request $request)
    {
        // 1. Get Pending Logs (Temporary Bookings) that belong to this user
        // We can't query JSON column efficiently in all DBs, but for filtered user_id we can simply check in loop or assuming logged in user created them.
        // Actually PaymentLog doesn't have user_id column, it's inside JSON. 
        // But usually we filter by session or we need to rely on the fact that only user's logs are relevant.
        // Limitation: PaymentLog table might need user_id column for efficient querying.
        // For now, let's assume we can fetch recent pending logs and filter in PHP (acceptable for small scale).

        $pendingLogs = PaymentLog::where('status', 'pending')
            ->orderBy('id', 'desc')
            ->limit(20) // Limit to avoid scanning too many
            ->get()
            ->filter(function ($log) {
                return isset($log->booking_data['user_id']) && $log->booking_data['user_id'] == auth()->id();
            });

        // Map logs to Booking structure
        $pendingBookings = $pendingLogs->map(function ($log) {
            $data = $log->booking_data;

            // Check formatted status based on expiration
            $isExpired = \Carbon\Carbon::parse($log->expired_at)->isPast();
            $status = $isExpired ? 'cancelled' : 'awaiting_payment';
            $cancellationReason = $isExpired ? 'Payment Time Expired' : null;

            $booking = new Booking([
                'id' => $log->id,
            ]);

            $recentPayment = \App\Models\Payment::where('payment_log_id', $log->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $booking->forceFill([
                'id' => 99900000 + $log->id,
                'status' => $status,
                'payment_status' => $recentPayment && $recentPayment->status === 'success' ? 'paid' : ($isExpired ? 'failed' : 'unpaid'),
                'booking_date' => $data['booking_date'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'total_price' => $data['total_price'] ?? 0,
                'service_price' => $data['service_price'] ?? 0,
                'room_charge' => $data['room_charge'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'cancellation_reason' => $cancellationReason,
                'cancelled_at' => $isExpired ? $log->expired_at : null,
                'branch' => \App\Models\Branch::find($data['branch_id'] ?? 0),
                'service' => \App\Models\Service::find($data['service_id'] ?? 0),
                'therapist' => \App\Models\Therapist::find($data['therapist_id'] ?? 0),
                'room' => \App\Models\Room::find($data['room_id'] ?? 0),
                'therapist_id' => $data['therapist_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'room_id' => $data['room_id'] ?? null,
                'created_at' => $log->created_at,
                'expires_at' => $log->expired_at,
                'is_pending_log' => true,
                'payment_log_id' => $log->id,
                'recent_payment_id' => $recentPayment ? $recentPayment->id : null
            ]);

            return $booking;
        });

        // Filter pending bookings based on requested status (if any)
        if ($request->has('status')) {
            $allowedStatuses = explode(',', $request->status);
            $pendingBookings = $pendingBookings->filter(function ($booking) use ($allowedStatuses) {
                return in_array($booking->status, $allowedStatuses);
            });
        }

        if ($request->has('from_date')) {
            $fromDate = $request->from_date;
            $pendingBookings = $pendingBookings->filter(function ($booking) use ($fromDate) {
                return $booking->booking_date >= $fromDate;
            });
        }

        // 2. Get Real Bookings
        $query = Booking::with(['branch', 'service', 'therapist', 'room', 'payments', 'transaction', 'feedback', 'items.service', 'items.therapist', 'items.room'])
            ->where('user_id', auth()->id());

        if ($request->has('status')) {
            $statuses = explode(',', $request->status);
            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('from_date')) {
            $query->where('booking_date', '>=', $request->from_date);
        }

        $sortDir = $request->has('from_date') ? 'asc' : 'desc';

        $bookings = $query->orderBy('booking_date', $sortDir)
            ->orderBy('start_time', $sortDir)
            ->paginate(10);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $bookings */
        if ($bookings->currentPage() === 1) {
            $realCollection = $bookings->getCollection();

            // Filter pending bookings that duplicate real bookings (same date & time)
            // This prevents showing "Awaiting Payment" drafts when a confirmed booking already exists
            $pendingBookings = $pendingBookings->filter(function ($pending) use ($realCollection) {
                return !$realCollection->contains(function ($real) use ($pending) {
                    $realDate = $real->booking_date instanceof \Carbon\Carbon ? $real->booking_date->format('Y-m-d') : $real->booking_date;
                    $pendingDate = $pending->booking_date instanceof \Carbon\Carbon ? $pending->booking_date->format('Y-m-d') : $pending->booking_date;

                    return $realDate === $pendingDate &&
                        substr($real->start_time, 0, 5) === substr($pending->start_time, 0, 5);
                });
            });

            $merged = $pendingBookings->merge($realCollection);

            // Sort merged collection by booking_date and start_time
            $sortedMerged = $merged->sort(function ($a, $b) use ($sortDir) {
                $dateA = $a->booking_date instanceof \Carbon\Carbon ? $a->booking_date->toDateString() : $a->booking_date;
                $dateB = $b->booking_date instanceof \Carbon\Carbon ? $b->booking_date->toDateString() : $b->booking_date;

                if ($dateA != $dateB) {
                    return $sortDir === 'asc' ? strcmp($dateA, $dateB) : strcmp($dateB, $dateA);
                }

                return $sortDir === 'asc' ? strcmp($a->start_time, $b->start_time) : strcmp($b->start_time, $a->start_time);
            })->values();

            $bookings->setCollection($sortedMerged);
        }

        return response()->json($bookings);
    }

    /**
     * Get specific booking
     */
    public function show($id)
    {
        // Handle PaymentLog synthetic ID (Drafts)
        if ($id >= 99900000) {
            $logId = $id - 99900000;
            $log = \App\Models\PaymentLog::find($logId);

            if (!$log) {
                return response()->json(['error' => 'Booking not found'], 404);
            }

            // Allow user to view their own log
            $bookingData = $log->booking_data;
            if (($bookingData['user_id'] ?? null) != auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $booking = $this->mapLogToBooking($log);
            return response()->json($booking);
        }

        $booking = Booking::with(['branch', 'service', 'therapist', 'room', 'payments', 'items.service', 'items.therapist', 'items.room'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json($booking);
    }

    /**
     * Request refund for cancelled booking or cancelled items
     */
    public function requestRefund(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_holder' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::with(['items', 'payments'])->where('user_id', auth()->id())->findOrFail($id);

        // Check if the booking itself or any of its items are cancelled
        $hasCancelledItems = $booking->items()->where('status', 'cancelled')->exists();

        if ($booking->status !== 'cancelled' && !$hasCancelledItems) {
            return response()->json(['error' => 'Refund can only be requested if the booking or an item is cancelled'], 400);
        }

        // Check if there are successful payments to refund
        $totalPaid = $booking->payments()->whereIn('status', ['paid', 'success', 'settlement'])->sum('amount');
        if ($totalPaid <= 0) {
            return response()->json(['error' => 'No successful payments found for this booking to refund'], 400);
        }

        $typeLabel = $booking->status === 'cancelled' ? 'TOTAL' : 'PARSIAL';

        $refundInfo = "\n\n--- PENGAJUAN REFUND ({$typeLabel}) ---\n" .
            "Bank: {$request->bank_name}\n" .
            "No. Rekening: {$request->account_number}\n" .
            "Nama Pemegang: {$request->account_holder}\n" .
            "Alasan: " . ($request->reason ?? 'Tidak ada alasan spesifik') . "\n" .
            "Total Pembayaran Terdeteksi: Rp " . number_format($totalPaid) . "\n" .
            "Diajukan pada: " . \Carbon\Carbon::now()->toDateTimeString();

        // Avoid duplicated notes if user double submits
        if (strpos($booking->notes ?? '', $request->account_number) !== false && strpos($booking->notes ?? '', '--- PENGAJUAN REFUND') !== false) {
            // Already requested with this account, maybe just update or ignore
        } else {
            $booking->update([
                'notes' => ($booking->notes ?? '') . $refundInfo
            ]);
        }

        // Notify Customer about successful refund request
        $user = auth()->user();
        if ($user) {
            if ($user->phone) {
                $this->whatsappService->sendCustomerRefundNotification($user->phone, $booking, $totalPaid, 'requested');
            }
            if ($user->email) {
                $this->emailService->sendCustomerRefundNotification($user->email, $booking, $totalPaid, 'requested');
            }
        }

        AuditLog::log('update', 'Booking', "Customer requested {$typeLabel} refund for booking REF: {$booking->booking_ref}");

        return response()->json([
            'message' => 'Refund request submitted successfully',
            'booking' => $booking,
        ]);
    }

    /**
     * Cancel booking
     */
    public function cancel(Request $request, $id)
    {
        // Handle PaymentLog synthetic ID (Drafts)
        if ($id >= 99900000) {
            $logId = $id - 99900000;
            $log = \App\Models\PaymentLog::find($logId);

            if (!$log) {
                return response()->json(['error' => 'Booking not found'], 404);
            }

            // Verify Ownership
            $bookingData = $log->booking_data;
            if (($bookingData['user_id'] ?? null) != auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Check if already paid/settled
            if ($log->status === 'settlement') {
                return response()->json(['error' => 'Cannot cancel paid booking via draft ID. Use real booking ID.'], 400);
            }

            $log->update(['status' => 'cancelled']);
            return response()->json(['message' => 'Booking draft cancelled']);
        }

        $booking = Booking::where('user_id', auth()->id())->findOrFail($id);

        if (!in_array($booking->status, ['pending_payment', 'confirmed'])) {
            return response()->json(['error' => 'Booking cannot be cancelled'], 400);
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => \Carbon\Carbon::now(),
        ]);

        // Send Notification
        NotificationController::createNotification(
            auth()->id(),
            'booking',
            'Booking Dibatalkan',
            "Booking Anda untuk layanan {$booking->service->name} telah dibatalkan.",
            ['booking_id' => $booking->id]
        );

        // Send Staff Notification
        if ($booking->therapist) {
            if ($booking->therapist->phone) {
                $this->whatsappService->sendStaffCancellationNotification($booking->therapist->phone, $booking);
            }
            if ($booking->therapist->email) {
                $this->emailService->sendStaffCancellationNotification($booking->therapist->email, $booking);
            }
        }

        // NEW: Notify Customer directly using new methods
        $user = auth()->user();
        if ($user) {
            if ($user->phone) {
                $this->whatsappService->sendCustomerCancellationNotification($user->phone, $booking, $request->input('reason'));
            }
            if ($user->email) {
                $this->emailService->sendCustomerCancellationNotification($user->email, $booking);
            }
        }

        // TODO: Process refund if applicable

        AuditLog::log('cancel', 'Booking', "Customer cancelled booking REF: {$booking->booking_ref}");

        return response()->json([
            'message' => 'Booking cancelled successfully',
            'booking' => $booking,
        ]);
    }

    /**
     * Reschedule booking (Admin)
     */
    public function reschedule(Request $request, $id)
    {
        // Handle PaymentLog synthetic ID (Drafts)
        if ($id >= 99900000) {
            return response()->json(['error' => 'Draft booking cannot be rescheduled. Please finalize payment or create a new booking.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'therapist_id' => 'required|exists:therapists,id',
            'room_id' => 'required|exists:rooms,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::findOrFail($id);

        // Check ownership if not admin/staff/owner
        $user = auth()->user();
        $isStaff = $user->role && in_array($user->role, ['admin', 'cashier', 'owner', 'super_admin']);

        if (!$isStaff && $booking->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $service = $booking->service;
        $startTime = Carbon::parse($request->start_time);
        $endTime = $startTime->copy()->addMinutes($service->duration);

        // Check availability
        $newStart = $startTime->format('H:i:s');
        $newEnd = $endTime->format('H:i:s');

        // Check therapist conflict - STRICT validation to prevent ANY overlap
        $conflictingBooking = Booking::where('id', '!=', $id)
            ->where('booking_date', $request->booking_date)
            ->where('therapist_id', $request->therapist_id)
            ->where(function ($query) use ($newStart, $newEnd) {
                // Overlap occurs when: new_start < existing_end AND new_end > existing_start
                $query->where('start_time', '<', $newEnd)
                    ->where('end_time', '>', $newStart);
            })
            ->whereIn('status', ['confirmed', 'in_progress', 'pending_payment'])
            ->first();

        if ($conflictingBooking) {
            return response()->json([
                'error' => 'Terapis tidak tersedia pada waktu yang dipilih',
                'message' => sprintf(
                    'Terapis sudah memiliki booking dari %s sampai %s. Silakan pilih waktu lain.',
                    substr($conflictingBooking->start_time, 0, 5),
                    substr($conflictingBooking->end_time, 0, 5)
                ),
                'conflicting_time' => [
                    'start' => substr($conflictingBooking->start_time, 0, 5),
                    'end' => substr($conflictingBooking->end_time, 0, 5),
                ]
            ], 409);
        }

        // Check room conflict
        $roomConflict = Booking::where('id', '!=', $id)
            ->where('booking_date', $request->booking_date)
            ->where('room_id', $request->room_id)
            ->where('start_time', '<', $newEnd)
            ->where('end_time', '>', $newStart)
            ->whereIn('status', ['confirmed', 'in_progress', 'pending_payment'])
            ->exists();

        if ($roomConflict) {
            return response()->json(['error' => 'Room is not available for the new time slot'], 409);
        }

        $booking->update([
            'booking_date' => $request->booking_date,
            'start_time' => $startTime->format('H:i:s'),
            'end_time' => $endTime->format('H:i:s'),
            'therapist_id' => $request->therapist_id,
            'room_id' => $request->room_id,
        ]);

        // Notify Staff of Reschedule
        if ($booking->therapist) {
            if ($booking->therapist->phone) {
                $this->whatsappService->sendStaffRescheduleNotification($booking->therapist->phone, $booking);
            }
            if ($booking->therapist->email) {
                $this->emailService->sendStaffRescheduleNotification($booking->therapist->email, $booking);
            }
        }

        // Notify Customer of Reschedule
        if ($booking->user && $booking->user->phone) {
            $this->whatsappService->sendCustomerNotification($booking->user->phone, 'reschedule', [
                'customer' => $booking->user,
                'booking' => $booking,
                'branch' => $booking->branch
            ], $booking->branch_id);
        }

        AuditLog::log('update', 'Booking', "Customer rescheduled booking REF: {$booking->booking_ref} to {$request->booking_date} {$startTime->format('H:i')}");

        return response()->json([
            'message' => 'Booking rescheduled successfully',
            'booking' => $booking->fresh(['branch', 'service', 'therapist', 'room']),
        ]);
    }

    /**
     * Helper to map PaymentLog to Booking model structure
     */
    private function mapLogToBooking($log)
    {
        $data = $log->booking_data;
        $isExpired = \Carbon\Carbon::parse($log->expired_at)->isPast();
        $status = $isExpired ? 'cancelled' : 'awaiting_payment';

        $booking = new Booking([
            'id' => $log->id,
        ]);

        $recentPayment = \App\Models\Payment::where('payment_log_id', $log->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $booking->forceFill([
            'id' => 99900000 + $log->id, // Maintain synthetic ID
            'status' => $status,
            'payment_status' => $recentPayment && $recentPayment->status === 'success' ? 'paid' : ($isExpired ? 'failed' : 'unpaid'),
            'booking_date' => $data['booking_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'total_price' => $data['total_price'] ?? 0,
            'service_price' => $data['service_price'] ?? 0,
            'room_charge' => $data['room_charge'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'cancellation_reason' => $isExpired ? 'Payment Time Expired' : null,
            'cancelled_at' => $isExpired ? $log->expired_at : null,
            'branch' => \App\Models\Branch::find($data['branch_id'] ?? 0),
            'service' => \App\Models\Service::find($data['service_id'] ?? 0),
            'therapist' => \App\Models\Therapist::find($data['therapist_id'] ?? 0),
            'room' => \App\Models\Room::find($data['room_id'] ?? 0),
            'therapist_id' => $data['therapist_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'room_id' => $data['room_id'] ?? null,
            'created_at' => $log->created_at,
            'expires_at' => $log->expired_at,
            'is_pending_log' => true,
            'payment_log_id' => $log->id,
            'recent_payment_id' => $recentPayment ? $recentPayment->id : null
        ]);

        // Map items if they exist in booking data
        if (isset($data['items']) && is_array($data['items'])) {
            $items = collect($data['items'])->map(function ($itemData) {
                return (object) [
                    'service_id' => $itemData['service_id'] ?? null,
                    'therapist_id' => $itemData['therapist_id'] ?? null,
                    'room_id' => $itemData['room_id'] ?? null,
                    'price' => $itemData['service_price'] ?? ($itemData['price'] ?? 0),
                    'room_charge' => $itemData['room_charge'] ?? 0,
                    'duration' => $itemData['duration'] ?? 0,
                    'start_time' => $itemData['start_time'] ?? null,
                    'guest_name' => $itemData['guest_name'] ?? null,
                    'guest_type' => $itemData['guest_type'] ?? 'dewasa',
                    'service' => \App\Models\Service::find($itemData['service_id'] ?? 0),
                    'therapist' => \App\Models\Therapist::find($itemData['therapist_id'] ?? 0),
                    'room' => \App\Models\Room::find($itemData['room_id'] ?? 0),
                ];
            });
            $booking->setRelation('items', $items);
        }

        return $booking;
    }
    public function rescheduleItem(Request $request, $id, $itemId)
    {
        $userId = $request->user()->id;
        $validator = Validator::make($request->all(), [
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $item = \App\Models\BookingItem::where('booking_id', $id)
            ->where('id', $itemId)
            ->whereHas('booking', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->firstOrFail();

        $sourceBooking = $item->booking;

        // Calculate new times
        $newDate = $request->booking_date;
        $newStart = $request->start_time;
        $duration = $item->duration;
        $newEnd = date('H:i', strtotime($newStart) + ($duration * 60));

        // Check Availability for this specific item (therapist)
        $therapistId = $item->therapist_id;
        if ($therapistId) {
            $conflict = Booking::where('therapist_id', $therapistId)
                ->where('booking_date', $newDate)
                ->whereNotIn('status', ['cancelled', 'awaiting_payment'])
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

        // CREATE NEW BOOKING
        $newRef = 'BK-' . date('Ymd') . '-' . rand(1000, 9999);
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
            'therapist_id' => $item->therapist_id,
            'room_id' => $item->room_id,
            'service_price' => $item->price,
            'room_charge' => $item->room_charge,
            'total_price' => $item->price + $item->room_charge,
            'guest_count' => 1,
            'status' => 'confirmed',
            'payment_status' => $sourceBooking->payment_status, // Inherit payment status correctly (including partial/DP)
            'notes' => "Rescheduled from {$sourceBooking->booking_ref}. " . ($request->reason ?? ''),
            'created_by' => $userId
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

        // Move Item to New Booking
        $item->update([
            'booking_id' => $newBooking->id,
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
            'notes' => 'Pemindahan item ke booking baru: ' . $newBooking->booking_ref
        ]);

        AuditLog::log('update', 'Booking', "Customer rescheduled item to new booking REF: {$newBooking->booking_ref} from original REF: {$sourceBooking->booking_ref}");

        return response()->json([
            'message' => 'Item rescheduled to new booking successfully',
            'new_booking' => $newBooking,
            'old_booking' => $sourceBooking->fresh()
        ]);
    }

    public function cancelItem(Request $request, $id, $itemId)
    {
        $userId = $request->user()->id;
        $item = \App\Models\BookingItem::where('booking_id', $id)
            ->where('id', $itemId)
            ->whereHas('booking', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->firstOrFail();

        $booking = $item->booking;

        $oldTotalPrice = $booking->total_price;
        $oldItems = $booking->items->toArray();

        $item->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->reason ?? 'Dibatalkan oleh pelanggan'
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
            'notes' => 'Pembatalan item layanan oleh pelanggan'
        ]);

        // Notifications for Item Cancel
        $customer = $booking->user;
        $therapist = $item->therapist;

        // To Customer
        if ($customer && $customer->phone) {
            $this->whatsappService->sendCustomerCancellationNotification($customer->phone, $booking, "Pembatalan item: {$item->service->name}");
        }
        if ($customer && $customer->email) {
            $this->emailService->sendCustomerCancellationNotification($customer->email, $booking);
        }

        // To Therapist
        if ($therapist) {
            if ($therapist->phone) {
                $this->whatsappService->sendStaffCancellationNotification($therapist->phone, $booking);
            }
            if ($therapist->email) {
                $this->emailService->sendStaffCancellationNotification($therapist->email, $booking);
            }
        }

        AuditLog::log('cancel', 'Booking', "Customer cancelled item {$itemId} from booking REF: {$booking->booking_ref}");

        return response()->json([
            'message' => 'Item cancelled successfully',
            'booking' => $booking->fresh(['items'])
        ]);
    }

    private function recalculateBooking(Booking $booking)
    {
        $remainingItems = $booking->items()->where('status', '!=', 'cancelled')->get();

        if ($remainingItems->isEmpty()) {
            $booking->update(['status' => 'cancelled']);
        } else {
            $minStart = $remainingItems->min('start_time');
            $maxEnd = $remainingItems->max('end_time');
            $totalServicePrice = $remainingItems->sum('price');
            $totalRoomCharge = $remainingItems->sum('room_charge');
            $guestCount = $remainingItems->count();

            $totalPrice = max(0, ($totalServicePrice + $totalRoomCharge) - ($booking->discount_amount ?? 0));

            $booking->update([
                'start_time' => $minStart,
                'end_time' => $maxEnd,
                'service_price' => $totalServicePrice,
                'room_charge' => $totalRoomCharge,
                'total_price' => $totalPrice,
                'guest_count' => $guestCount
            ]);
        }
    }
}
