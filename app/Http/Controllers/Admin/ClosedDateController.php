<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClosedDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;

class ClosedDateController extends Controller
{
    public function index(Request $request)
    {
        $query = ClosedDate::query();

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                  ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                  ->orWhere(function($sub) use ($request) {
                      $sub->where('start_date', '<=', $request->start_date)
                          ->where('end_date', '>=', $request->end_date);
                  });
            });
        }

        $closedDates = $query->orderBy('start_date', 'desc')->get();

        // Map for frontend compatibility if needed
        $closedDates = $closedDates->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'startDate' => $item->start_date->format('Y-m-d'),
                'endDate' => $item->end_date ? $item->end_date->format('Y-m-d') : $item->start_date->format('Y-m-d'),
                'reason' => $item->reason,
                'branchId' => $item->branch_id
            ];
        });

        return response()->json($closedDates);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'startDate' => 'required|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $closedDate = ClosedDate::create([
            'name' => $request->name,
            'branch_id' => $request->branch_id,
            'start_date' => $request->startDate,
            'end_date' => $request->endDate ?: $request->startDate,
            'reason' => $request->reason,
        ]);

        AuditLog::log('create', 'Settings', "Added closed date: {$closedDate->name} for branch ID: {$closedDate->branch_id}");

        return response()->json([
            'message' => 'Closed date added successfully',
            'closed_date' => $closedDate,
        ], 201);
    }

    public function destroy($id)
    {
        $closedDate = ClosedDate::findOrFail($id);
        $name = $closedDate->name;
        $closedDate->delete();

        AuditLog::log('delete', 'Settings', "Deleted closed date: {$name}");

        return response()->json([
            'message' => 'Closed date deleted successfully',
        ]);
    }
}
