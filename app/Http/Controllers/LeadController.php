<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * PUBLIC: Submit a new lead from landing page.
     * No auth required.
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'salon_name' => 'required|string|max:255',
            'pic_name'   => 'required|string|max:255',
            'phone'      => 'required|string|max:20',
            'email'      => 'nullable|email|max:255',
            'city'       => 'nullable|string|max:100',
            'source'     => 'nullable|string|max:50',
        ]);

        // Check for duplicate lead by phone (avoid spam)
        $existing = Lead::where('phone', $request->phone)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'success',
                'message' => 'Terima kasih! Data Anda sudah kami terima sebelumnya. Tim kami akan segera menghubungi.',
                'data' => ['id' => $existing->id],
            ]);
        }

        $lead = Lead::create([
            'salon_name' => $request->salon_name,
            'pic_name'   => $request->pic_name,
            'phone'      => $request->phone,
            'email'      => $request->email,
            'city'       => $request->city,
            'source'     => $request->source ?? 'landing_page',
            'status'     => 'new',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Terima kasih! Tim kami akan segera menghubungi Anda via WhatsApp.',
            'data' => ['id' => $lead->id],
        ], 201);
    }

    /**
     * ADMIN: List all leads with filtering (for CRM pipeline).
     * Requires auth + admin/super_admin role.
     */
    public function index(Request $request)
    {
        $query = Lead::query()->orderByDesc('created_at');

        if ($request->has('status') && $request->status !== 'all') {
            $query->byStatus($request->status);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        $perPage = $request->input('per_page', 20);
        if ($perPage === 'none' || $request->input('all') === 'true') {
            $leads = $query->get();
            return response()->json([
                'status' => 'success',
                'data' => $leads,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => $leads->count(),
                    'per_page' => $leads->count(),
                ],
            ]);
        }
        $leads = $query->paginate(is_numeric($perPage) ? (int)$perPage : 20);

        return response()->json([
            'status' => 'success',
            'data' => $leads->items(),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'total' => $leads->total(),
                'per_page' => $leads->perPage(),
            ],
        ]);
    }

    /**
     * ADMIN: Show a single lead.
     */
    public function show($id)
    {
        $lead = Lead::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $lead,
        ]);
    }

    /**
     * ADMIN: Update lead status / notes (pipeline progression).
     */
    public function update(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $this->validate($request, [
            'status' => 'nullable|string|in:' . implode(',', Lead::validStatuses()),
            'notes'  => 'nullable|string|max:2000',
        ]);

        if ($request->has('status')) {
            $oldStatus = $lead->status;
            $lead->status = $request->status;
            AuditLog::log('update', 'Lead', "Lead #{$lead->id} ({$lead->salon_name}) status: {$oldStatus} → {$request->status}");
        }

        if ($request->has('notes')) {
            $lead->notes = $request->notes;
        }

        $lead->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Lead berhasil diperbarui',
            'data' => $lead,
        ]);
    }

    /**
     * ADMIN: Delete a lead.
     */
    public function destroy($id)
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Lead berhasil dihapus',
        ]);
    }

    /**
     * ADMIN: Get pipeline stats (count per status).
     */
    public function stats()
    {
        $stats = [];
        foreach (Lead::validStatuses() as $status) {
            $stats[$status] = Lead::where('status', $status)->count();
        }
        $stats['total'] = Lead::count();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }
}
