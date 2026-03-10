<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function index(Request $request)
    {
        $query = AutomationRule::with('branches');

        if ($request->has('trigger')) {
            $query->where('trigger', $request->trigger);
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('status')) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
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

        $limit = $request->input('limit', 10);
        $rules = $query->orderBy('created_at', 'desc')->paginate($limit);

        return response()->json($rules);
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
            'trigger' => 'required|in:birthday,winback,post_visit,anniversary,first_visit',
            'channel' => 'required|in:whatsapp,email,sms',
            'message' => 'required|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        $ruleData = array_merge($request->all(), [
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        $rule = AutomationRule::create($ruleData);

        if (!$isGlobal && !empty($branchIds)) {
            $rule->branches()->sync($branchIds);
        }

        AuditLog::log('create', 'automation', "Created automation rule: {$rule->name}");

        return response()->json($rule->load('branches'), 201);
    }

    public function show($id)
    {
        $rule = AutomationRule::with('branches')->findOrFail($id);
        return response()->json($rule);
    }

    public function update(Request $request, $id)
    {
        $rule = AutomationRule::findOrFail($id);

        $isGlobal = $request->input('is_global', $rule->is_global);
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
            'trigger' => 'sometimes|in:birthday,winback,post_visit,anniversary,first_visit',
            'channel' => 'sometimes|in:whatsapp,email,sms',
            'message' => 'sometimes|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        $ruleData = array_merge($request->all(), [
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        $rule->update($ruleData);

        if ($isGlobal) {
            $rule->branches()->detach();
        } else if (!empty($branchIds)) {
            $rule->branches()->sync($branchIds);
        }

        AuditLog::log('update', 'automation', "Updated automation rule: {$rule->name}");

        return response()->json($rule->load('branches'));
    }

    public function destroy($id)
    {
        $rule = AutomationRule::findOrFail($id);
        $name = $rule->name;
        $rule->branches()->detach();
        $rule->delete();

        AuditLog::log('delete', 'automation', "Deleted automation rule: {$name}");

        return response()->json(['message' => 'Automation rule deleted successfully']);
    }

    public function toggleStatus($id)
    {
        $rule = AutomationRule::findOrFail($id);
        $rule->is_active = !$rule->is_active;
        $rule->save();

        $status = $rule->is_active ? 'activated' : 'deactivated';
        AuditLog::log('update', 'automation', "Rule {$status}: {$rule->name}");

        return response()->json($rule->load('branches'));
    }
}
