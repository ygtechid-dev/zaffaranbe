<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = Campaign::with('branches');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('branch_id') && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                    ->orWhereHas('branches', function ($bq) use ($branchId) {
                        $bq->where('branches.id', $branchId);
                    });
            });
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('search')) {
            $search = strtolower($request->search);
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . $search . '%']);
        }

        $perPage = $request->input('limit', 10);
        $campaigns = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($campaigns);
    }

    public function store(Request $request)
    {
        $isGlobal = $request->input('is_global', false);
        $branchIds = $request->input('branch_ids', []);

        if ($request->has('branch_id')) {
            if ($request->branch_id == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$request->branch_id];
            }
        }

        $this->validate($request, [
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'message' => 'nullable|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $campaignData = array_merge($request->all(), [
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        $campaign = Campaign::create($campaignData);

        if (!$isGlobal && !empty($branchIds)) {
            $campaign->branches()->sync($branchIds);
        }

        AuditLog::log('create', 'campaign', "Created campaign: {$campaign->name}");

        return response()->json($campaign->load('branches'), 201);
    }

    public function show($id)
    {
        $campaign = Campaign::with('branches')->findOrFail($id);
        return response()->json($campaign);
    }

    public function update(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);

        $isGlobal = $request->input('is_global', $campaign->is_global);
        $branchIds = $request->input('branch_ids', []);

        if ($request->has('branch_id')) {
            if ($request->branch_id == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$request->branch_id];
            }
        }

        $this->validate($request, [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string',
            'status' => 'sometimes|in:active,paused,completed',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $campaignData = array_merge($request->all(), [
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        $campaign->update($campaignData);

        if ($isGlobal) {
            $campaign->branches()->detach();
        } else if (!empty($branchIds)) {
            $campaign->branches()->sync($branchIds);
        }

        AuditLog::log('update', 'campaign', "Updated campaign: {$campaign->name}");

        return response()->json($campaign->load('branches'));
    }

    public function destroy($id)
    {
        $campaign = Campaign::findOrFail($id);
        $name = $campaign->name;
        $campaign->branches()->detach();
        $campaign->delete();

        AuditLog::log('delete', 'campaign', "Deleted campaign: {$name}");

        return response()->json(['message' => 'Campaign deleted successfully']);
    }

    public function toggleStatus($id)
    {
        $campaign = Campaign::findOrFail($id);
        $campaign->status = $campaign->status === 'active' ? 'paused' : 'active';
        $campaign->save();

        AuditLog::log('update', 'campaign', "Toggled campaign status: {$campaign->name}");

        return response()->json($campaign->load('branches'));
    }
    public function uploadImage(Request $request, $id)
    {
        $this->validate($request, [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
        ]);

        $campaign = Campaign::findOrFail($id);

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('campaigns', 'public');
            $campaign->image = $imagePath;
            $campaign->save();

            AuditLog::log('update', 'campaign', "Uploaded image for campaign: {$campaign->name}");
        }

        return response()->json($campaign->load('branches'));
    }

    public function previewAudience(Request $request)
    {
        $targetAudience = $request->query('target_audience');
        $branchId = $request->query('branch_id');

        $query = Customer::query();

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        // Apply filters based on target audience
        switch ($targetAudience) {
            case 'active':
                $query->whereHas('bookings', function ($q) {
                    $q->where('created_at', '>=', \Carbon\Carbon::now()->subMonths(3));
                });
                break;
            case 'inactive':
                $query->whereDoesntHave('bookings', function ($q) {
                    $q->where('created_at', '>=', \Carbon\Carbon::now()->subMonths(3));
                });
                break;
            case 'vip':
                // Assuming you have a total_spent or points column
                // Example: just get those with bookings > 5
                $query->whereHas('bookings', function ($q) {}, '>=', 5);
                break;
            case 'new':
                $query->where('created_at', '>=', \Carbon\Carbon::now()->subMonth());
                break;
            case 'birthday':
                $query->whereMonth('dob', \Carbon\Carbon::now()->month);
                break;
            case 'all':
            default:
                // No additional filters
                break;
        }

        $count = $query->count();

        return response()->json(['count' => $count]);
    }
}
