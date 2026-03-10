<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'customer');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('registration_source')) {
            $query->where('registration_source', $request->registration_source);
        }

        if ($request->filled('app_account_status')) {
            $query->where('has_app_account', $request->app_account_status === 'active');
        }

        if ($request->filled('status')) {
            $query->where('membership_status', $request->status);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $customers = $query->withCount('bookings')
            ->withSum('completedBookings as total_spend', 'total_price')
            ->withSum('loyaltyPoints as total_loyalty_points', 'remaining_points')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('limit', 15));

        $customers->getCollection()->transform(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'email' => $u->email,
                'dob' => $u->birth_date?->format('Y-m-d') ?? '', // Change to Y-m-d for date input compatibility
                'dob_formatted' => $u->birth_date?->format('d/m/Y') ?? '-',
                'visits_count' => $u->bookings_count,
                'total_spend' => (float) ($u->total_spend ?? 0),
                'loyalty_points' => (int) ($u->total_loyalty_points ?? 0),
                'last_visit' => $u->bookings()->where('status', 'completed')->latest('booking_date')->first()?->booking_date ?? '-',
                'status' => $u->membership_status ?? 'new',
                'notes' => $u->notes,
                'registration_source' => $u->registration_source ?? 'walk-in',
                'has_app_account' => (bool) $u->has_app_account,
                'user_id' => $u->id, // For compatibility with frontend check
                'branch_id' => $u->branch_id,
                'address' => $u->address,
            ];
        });

        return response()->json($customers);
    }

    public function show($id)
    {
        $customer = User::where('role', 'customer')
            ->withCount(['bookings', 'feedbacks'])
            ->findOrFail($id);

        // Get customer stats
        $stats = [
            'total_bookings' => $customer->bookings()->count(),
            'completed_bookings' => $customer->bookings()->where('status', 'completed')->count(),
            'cancelled_bookings' => $customer->bookings()->where('status', 'cancelled')->count(),
            'total_spent' => $customer->bookings()
                ->where('payment_status', 'paid')
                ->sum('total_price'),
            'last_visit' => $customer->bookings()
                ->where('status', 'completed')
                ->orderBy('booking_date', 'desc')
                ->first()?->booking_date,
        ];

        return response()->json([
            'customer' => $customer,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'birth_date' => 'nullable|date',
            'dob' => 'nullable|date', // Accept both
            'address' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'registration_source' => 'sometimes|string',
            'membership_status' => 'sometimes|string',
            'has_app_account' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $birthDate = $request->birth_date ?? $request->dob;

        $customer = User::create([
            'name' => $request->name,
            'email' => $request->email ?? strtolower(str_replace(' ', '.', $request->name)) . rand(100, 999) . '@generated.com',
            'phone' => $request->phone,
            'birth_date' => $birthDate,
            'address' => $request->address,
            'password' => Hash::make(rand(10000000, 99999999)),
            'role' => 'customer',
            'branch_id' => $request->branch_id === 'all' ? null : $request->branch_id,
            'registration_source' => $request->registration_source ?? 'walk-in',
            'membership_status' => $request->membership_status ?? 'new',
            'has_app_account' => $request->has_app_account ?? false,
            'notes' => $request->notes,
            'is_verified' => true,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Customer created successfully',
            'customer' => $customer,
        ], 201);
    }

    public function bookingHistory($id, Request $request)
    {
        $customer = User::where('role', 'customer')->findOrFail($id);

        $bookings = $customer->bookings()
            ->with(['service', 'therapist', 'room', 'branch'])
            ->orderBy('booking_date', 'desc')
            ->paginate($request->input('per_page', 10));

        return response()->json($bookings);
    }

    public function update(Request $request, $id)
    {
        $customer = User::where('role', 'customer')->findOrFail($id);

        $this->validate($request, [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'phone' => 'sometimes|required|string|unique:users,phone,' . $id,
            'birth_date' => 'nullable|date',
            'dob' => 'nullable|date',
            'address' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
            'registration_source' => 'sometimes|string',
            'membership_status' => 'sometimes|string',
            'has_app_account' => 'sometimes|boolean',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $birthDate = $request->input('birth_date') ?? $request->input('dob');

        $updateData = $request->only([
            'name',
            'email',
            'phone',
            'address',
            'branch_id',
            'registration_source',
            'membership_status',
            'has_app_account',
            'notes',
            'is_active',
        ]);

        if ($birthDate) {
            $updateData['birth_date'] = $birthDate;
        }

        $customer->update($updateData);

        return response()->json([
            'message' => 'Customer updated successfully',
            'customer' => $customer,
        ]);
    }

    public function destroy($id)
    {
        $customer = User::where('role', 'customer')->findOrFail($id);

        // Soft delete
        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }
}
