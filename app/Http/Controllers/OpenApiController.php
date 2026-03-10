<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\WhatsAppService;
use App\Services\EmailService;
use App\Models\AuditLog;
use Carbon\Carbon;

class OpenApiController extends Controller
{
    protected $whatsappService;
    protected $emailService;

    public function __construct(WhatsAppService $whatsappService, EmailService $emailService)
    {
        $this->whatsappService = $whatsappService;
        $this->emailService = $emailService;
    }
    /**
     * Register a new customer
     * POST /openapi/register
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'customer',
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available schedule/therapists
     * GET /openapi/availability
     */
    public function availability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|integer|exists:branches,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'duration' => 'required|integer|min:15'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bookingController = app(BookingController::class);
            $availabilityRequest = new Request([
                'branch_id' => $request->branch_id,
                'booking_date' => $request->booking_date,
                'duration' => $request->duration
            ]);

            $availability = $bookingController->getTherapistAvailability($availabilityRequest);

            return response()->json([
                'success' => true,
                'data' => $availability->original
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new reservation/booking
     * POST /openapi/reservations
     */
    public function createReservation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'service_id' => 'required|integer|exists:services,id',
            'therapist_id' => 'required|integer|exists:therapists,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'guest_count' => 'integer|min:1',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bookingController = app(BookingController::class);
            $bookingRequest = new Request($request->all());

            $result = $bookingController->store($bookingRequest);

            return response()->json([
                'success' => true,
                'message' => 'Reservation created successfully',
                'data' => $result->original
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reschedule an existing booking
     * PUT /openapi/reservations/{id}/reschedule
     */
    public function reschedule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'booking_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'therapist_id' => 'nullable|integer|exists:therapists,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $booking = Booking::findOrFail($id);

            // Check if booking can be rescheduled
            if (!in_array($booking->status, ['pending', 'confirmed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking cannot be rescheduled. Current status: ' . $booking->status
                ], 400);
            }

            // Calculate new end time based on service duration
            $startTime = Carbon::parse($request->booking_date . ' ' . $request->start_time);
            $endTime = $startTime->copy()->addMinutes($booking->duration);

            // Update booking
            $booking->update([
                'booking_date' => $request->booking_date,
                'start_time' => $request->start_time,
                'end_time' => $endTime->format('H:i:s'),
                'therapist_id' => $request->therapist_id ?? $booking->therapist_id
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

            AuditLog::log('update', 'OpenAPI', "Rescheduled booking REF: {$booking->booking_ref} via OpenAPI");

            return response()->json([
                'success' => true,
                'message' => 'Booking rescheduled successfully',
                'data' => [
                    'booking_id' => $booking->id,
                    'booking_date' => $booking->booking_date,
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                    'status' => $booking->status
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reschedule booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create payment for a booking
     * POST /openapi/payments
     */
    public function createPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|integer|exists:bookings,id',
            'payment_method' => 'required|string',
            'amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $booking = Booking::findOrFail($request->booking_id);

            $paymentLog = PaymentLog::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'status' => 'pending',
                'booking_data' => json_encode($booking->toArray())
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully',
                'data' => [
                    'payment_id' => $paymentLog->id,
                    'booking_id' => $booking->id,
                    'amount' => $paymentLog->amount,
                    'status' => $paymentLog->status
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check payment status
     * GET /openapi/payments/{id}/status
     */
    public function checkPaymentStatus($id)
    {
        try {
            $payment = PaymentLog::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'booking_id' => $payment->booking_id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'payment_method' => $payment->payment_method,
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
