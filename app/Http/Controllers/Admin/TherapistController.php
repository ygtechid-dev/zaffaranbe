<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Therapist;
use App\Models\TherapistSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TherapistController extends Controller
{
    public function index(Request $request)
    {
        $query = Therapist::with('branch');

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
                // ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($request->has('is_booking_online_enabled')) {
            $query->where('is_booking_online_enabled', $request->boolean('is_booking_online_enabled'));
        }

        $therapists = $query->withCount('bookings')
            ->orderBy('name', 'asc')
            ->paginate($request->input('per_page', 15));

        return response()->json($therapists);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|unique:therapists,phone',
            'gender' => 'required|in:male,female',
            'specialization' => 'nullable|string',
            'photo' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'social_media' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'start_work_date' => 'nullable|date',
            'end_work_date' => 'nullable|date|after_or_equal:start_work_date',
            'is_booking_online_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $therapist = Therapist::create($request->all());

        return response()->json([
            'message' => 'Therapist created successfully',
            'therapist' => $therapist->load('branch'),
        ], 201);
    }

    public function show($id)
    {
        $therapist = Therapist::with(['branch', 'schedules'])
            ->withCount('bookings')
            ->findOrFail($id);

        return response()->json($therapist);
    }

    public function update(Request $request, $id)
    {
        $therapist = Therapist::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'branch_id' => 'sometimes|required|exists:branches,id',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|required|string|unique:therapists,phone,' . $id,
            'gender' => 'sometimes|required|in:male,female',
            'specialization' => 'nullable|string',
            'photo' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'social_media' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'start_work_date' => 'nullable|date',
            'end_work_date' => 'nullable|date|after_or_equal:start_work_date',
            'is_active' => 'sometimes|boolean',
            'is_booking_online_enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $therapist->update($request->all());

        return response()->json([
            'message' => 'Therapist updated successfully',
            'therapist' => $therapist->fresh('branch'),
        ]);
    }

    public function destroy($id)
    {
        $therapist = Therapist::findOrFail($id);
        $therapist->delete();

        return response()->json([
            'message' => 'Therapist deleted successfully',
        ]);
    }

    public function uploadPhoto(Request $request, $id)
    {
        $therapist = Therapist::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = 'therapist_' . $id . '_' . time() . '.' . $file->getClientOriginalExtension();

            // Store in public/uploads/therapists directory
            $uploadPath = base_path('public/uploads/therapists');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            $path = $file->move($uploadPath, $filename);

            // Update therapist with photo path
            $photoUrl = '/uploads/therapists/' . $filename;
            $therapist->update(['photo' => $photoUrl]);

            return response()->json([
                'message' => 'Photo uploaded successfully',
                'photo_url' => $photoUrl,
                'therapist' => $therapist->fresh()
            ]);
        }

        return response()->json(['error' => 'No photo provided'], 400);
    }

    public function schedules($id)
    {
        $therapist = Therapist::findOrFail($id);
        $schedules = $therapist->schedules()->orderBy('day_of_week')->get();

        return response()->json($schedules);
    }

    public function storeSchedule(Request $request, $id)
    {
        $therapist = Therapist::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'date' => 'required_without:day_of_week|date|nullable',
            'day_of_week' => 'required_without:date|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday,daily|nullable',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'shift_type' => 'nullable|in:morning,afternoon,evening,full_day',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $date = $request->date;
        $dayOfWeek = $request->day_of_week;

        // Find potential overlaps to perform UPSERT
        $query = TherapistSchedule::where('therapist_id', $id);
        if ($date) {
            $query->where(function ($q) use ($date, $dayOfWeek) {
                $q->where('date', $date);
                if ($dayOfWeek)
                    $q->orWhere('day_of_week', $dayOfWeek);
            });
        } else {
            $query->where('day_of_week', $dayOfWeek)->whereNull('date');
        }

        $existingSchedules = $query->get();

        // Match by time overlap
        $overlapping = $existingSchedules->filter(function ($s) use ($request) {
            $newStart = $request->start_time;
            $newEnd = $request->end_time;
            return ($newStart < $s->end_time && $newEnd > $s->start_time);
        });

        if ($overlapping->isNotEmpty()) {
            $target = $overlapping->first();

            // Delete others to clear the way
            foreach ($overlapping as $other) {
                if ($other->id !== $target->id) {
                    $other->delete();
                }
            }

            $target->update([
                'date' => $date,
                'day_of_week' => $dayOfWeek,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'shift_type' => $request->shift_type ?? 'full_day',
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'Schedule updated successfully (upsert)',
                'schedule' => $target,
            ], 200);
        }

        $schedule = TherapistSchedule::create([
            'therapist_id' => $id,
            'date' => $date,
            'day_of_week' => $dayOfWeek,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'shift_type' => $request->shift_type ?? 'full_day',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Schedule created successfully',
            'schedule' => $schedule,
        ], 201);
    }

    public function updateSchedule(Request $request, $scheduleId)
    {
        $schedule = TherapistSchedule::findOrFail($scheduleId);

        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|nullable|date',
            'day_of_week' => 'sometimes|nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday,daily',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'shift_type' => 'sometimes|required|in:morning,afternoon,evening,full_day',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for overlaps with OTHER records after this update
        $newDate = $request->input('date', $schedule->date);
        $newDay = $request->input('day_of_week', $schedule->day_of_week);
        $newStart = $request->input('start_time', $schedule->start_time);
        $newEnd = $request->input('end_time', $schedule->end_time);

        $conflicts = TherapistSchedule::where('therapist_id', $schedule->therapist_id)
            ->where('id', '!=', $schedule->id)
            ->where(function ($q) use ($newDate, $newDay) {
                if ($newDate)
                    $q->where('date', $newDate);
                else
                    $q->where('day_of_week', $newDay)->whereNull('date');
            })
            ->get()
            ->filter(function ($s) use ($newStart, $newEnd) {
                return ($newStart < $s->end_time && $newEnd > $s->start_time);
            });

        // Cleanup conflicts
        foreach ($conflicts as $c) {
            $c->delete();
        }

        $schedule->update($request->all());

        return response()->json([
            'message' => 'Schedule updated successfully',
            'schedule' => $schedule,
        ]);
    }

    public function deleteSchedule($scheduleId)
    {
        $schedule = TherapistSchedule::findOrFail($scheduleId);
        $schedule->delete();

        return response()->json([
            'message' => 'Schedule deleted successfully',
        ]);
    }

    /**
     * Get commission settings for a therapist
     */
    public function getCommissions($id)
    {
        $therapist = Therapist::with('commissions.service')->findOrFail($id);

        return response()->json([
            'default_service_commission' => $therapist->default_service_commission ?? 0,
            'default_product_commission' => $therapist->default_product_commission ?? 0,
            'service_commission_type' => $therapist->service_commission_type ?? $therapist->commission_type ?? 'percent',
            'product_commission_type' => $therapist->product_commission_type ?? $therapist->commission_type ?? 'percent',
            'commission_type' => $therapist->commission_type ?? 'percent',
            'service_commissions' => $therapist->commissions->where('type', 'service')->map(function ($c) {
                return [
                    'service_id' => $c->service_id,
                    'service_name' => $c->service ? $c->service->name : null,
                    'commission_rate' => $c->commission_rate,
                    'commission_type' => $c->commission_type,
                ];
            })->values(),
            'product_commissions' => $therapist->commissions->where('type', 'product')->map(function ($c) {
                $name = null;
                if ($c->product_variant_id) {
                    $variant = \App\Models\ProductVariant::with('product')->find($c->product_variant_id);
                    if ($variant) {
                        $name = $variant->product ? $variant->product->name . ' - ' . $variant->name : $variant->name;
                    }
                } else {
                    $product = \App\Models\Product::find($c->product_id);
                    $name = $product ? $product->name : null;
                }

                return [
                    'product_id' => $c->product_id,
                    'product_variant_id' => $c->product_variant_id,
                    'product_name' => $name,
                    'commission_rate' => $c->commission_rate,
                    'commission_type' => $c->commission_type,
                ];
            })->values(),
        ]);
    }

    /**
     * Save commission settings for a therapist
     */
    public function saveCommissions(Request $request, $id)
    {
        $therapist = Therapist::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'default_service_commission' => 'nullable|numeric|min:0',
            'default_product_commission' => 'nullable|numeric|min:0',
            'service_commission_type' => 'nullable|in:percent,fixed',
            'product_commission_type' => 'nullable|in:percent,fixed',
            'commission_type' => 'nullable|in:percent,fixed',
            'service_commissions' => 'nullable|array',
            'service_commissions.*.service_id' => 'required|exists:services,id',
            'service_commissions.*.commission_rate' => 'required|numeric|min:0',
            'service_commissions.*.commission_type' => 'required|in:percent,fixed',
            'product_commissions' => 'nullable|array',
            'product_commissions.*.product_id' => 'nullable|exists:products,id',
            'product_commissions.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'product_commissions.*.commission_rate' => 'required|numeric|min:0',
            'product_commissions.*.commission_type' => 'required|in:percent,fixed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update default commissions
        $therapist->update([
            'default_service_commission' => $request->input('default_service_commission', 0),
            'default_product_commission' => $request->input('default_product_commission', 0),
            'service_commission_type' => $request->input('service_commission_type', $request->input('commission_type', 'percent')),
            'product_commission_type' => $request->input('product_commission_type', $request->input('commission_type', 'percent')),
            'commission_type' => $request->input('commission_type', 'percent'),
        ]);

        // Update service-specific commissions
        if ($request->has('service_commissions')) {
            // Clear existing service commissions
            $therapist->commissions()->where('type', 'service')->delete();

            foreach ($request->service_commissions as $commission) {
                \App\Models\TherapistCommission::create([
                    'therapist_id' => $id,
                    'service_id' => $commission['service_id'],
                    'type' => 'service',
                    'commission_rate' => $commission['commission_rate'],
                    'commission_type' => $commission['commission_type'],
                ]);
            }
        }

        // Update product-specific commissions
        if ($request->has('product_commissions')) {
            // Clear existing product commissions
            $therapist->commissions()->where('type', 'product')->delete();

            foreach ($request->product_commissions as $commission) {
                \App\Models\TherapistCommission::create([
                    'therapist_id' => $id,
                    'product_id' => $commission['product_id'] ?? null,
                    'product_variant_id' => $commission['product_variant_id'] ?? null,
                    'type' => 'product',
                    'commission_rate' => $commission['commission_rate'],
                    'commission_type' => $commission['commission_type'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Commission settings saved successfully',
            'therapist' => $therapist->fresh('commissions'),
        ]);
    }
}
