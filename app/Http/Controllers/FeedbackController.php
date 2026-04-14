<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    public function index()
    {
        $feedbacks = Feedback::with([
            'booking:id,booking_ref,booking_date,service_id',
            'booking.service:id,name',
        ])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($feedbacks);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'nullable|exists:bookings,id',
            'rating'     => 'required|integer|min:1|max:5',
            'comment'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Kalau ada booking_id, pastikan booking milik user ini & statusnya completed
        if ($request->booking_id) {
            $booking = \App\Models\Booking::where('id', $request->booking_id)
                ->where('user_id', auth()->id())
                ->first();

            if (!$booking) {
                return response()->json(['error' => 'Booking tidak ditemukan'], 404);
            }

            if ($booking->status !== 'completed') {
                return response()->json(['error' => 'Hanya booking yang sudah selesai yang bisa diberi rating'], 422);
            }

            // Cek apakah sudah ada feedback untuk booking ini
            $existing = Feedback::where('user_id', auth()->id())
                ->where('booking_id', $request->booking_id)
                ->first();

            if ($existing) {
                return response()->json(['error' => 'Anda sudah memberikan rating untuk kunjungan ini'], 409);
            }
        }

        $feedback = Feedback::create([
            'user_id'    => auth()->id(),
            'booking_id' => $request->booking_id,
            'rating'     => $request->rating,
            'comment'    => $request->comment,
        ]);

        return response()->json([
            'message'  => 'Feedback submitted successfully',
            'feedback' => $feedback->load(['booking:id,booking_ref,booking_date,service_id', 'booking.service:id,name']),
        ], 201);
    }

    /**
     * Cek apakah booking sudah punya feedback
     * GET /customer/feedbacks/booking/{bookingId}
     */
    public function getByBooking($bookingId)
    {
        $feedback = Feedback::where('user_id', auth()->id())
            ->where('booking_id', $bookingId)
            ->first();

        if (!$feedback) {
            return response()->json(null);
        }

        return response()->json($feedback);
    }
}