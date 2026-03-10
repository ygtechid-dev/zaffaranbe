<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = Feedback::with([
            'user:id,name,email',
            'booking:id,booking_ref,branch_id,service_id,therapist_id',
            'booking.branch:id,name',
            'booking.service:id,name',
            'booking.therapist:id,name'
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%$search%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%");
                    });
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('booking', function ($bq) use ($request) {
                $bq->where('branch_id', $request->branch_id);
            });
        }

        if ($request->filled('therapist_id')) {
            $query->whereHas('booking', function ($bq) use ($request) {
                $bq->where('therapist_id', $request->therapist_id);
            });
        }

        if ($request->filled('service_id')) {
            $query->whereHas('booking', function ($bq) use ($request) {
                $bq->where('service_id', $request->service_id);
            });
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->filled('replied')) {
            if ($request->replied == 'true') {
                $query->whereNotNull('admin_reply');
            } else {
                $query->whereNull('admin_reply');
            }
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator $feedbacks */
        $feedbacks = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('limit', 15));

        $feedbacks->through(function ($f) {
            return [
                'id' => $f->id,
                'user_id' => $f->user_id,
                'user_name' => $f->user->name ?? 'Anonymous',
                'booking_ref' => $f->booking->booking_ref ?? '-',
                'branch_name' => $f->booking->branch->name ?? '-',
                'service_name' => $f->booking->service->name ?? '-',
                'therapist_name' => $f->booking->therapist->name ?? '-',
                'rating' => (int) $f->rating,
                'comment' => $f->comment,
                'reply' => $f->admin_reply,
                'created_at' => $f->created_at->toIso8601String(),
            ];
        });

        return response()->json($feedbacks);
    }

    public function reply(Request $request, $id)
    {
        $request->validate([
            'admin_reply' => 'required|string',
        ]);

        $feedback = Feedback::findOrFail($id);

        $feedback->update([
            'admin_reply' => $request->admin_reply,
            'replied_at' => \Carbon\Carbon::now(),
        ]);

        // TODO: Send notification to customer

        return response()->json([
            'message' => 'Reply sent successfully',
            'feedback' => $feedback->fresh(['user', 'booking']),
        ]);
    }
}
