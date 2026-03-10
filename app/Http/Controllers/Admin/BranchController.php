<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        $branches = $query->with(['facilities'])
            ->withCount(['therapists', 'rooms', 'bookings'])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($branches);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:100|regex:/[a-zA-Z]/',
            'code' => ['required', 'string', 'unique:branches,code', 'regex:/^[A-Z0-9-]{3,50}$/'],
            'address' => 'required|string|min:10',
            'phone' => ['required', 'string', 'regex:/^(\+62|0)[1-9][0-9]{7,15}$/'],
            'city' => 'required|string|regex:/^[a-zA-Z\s.]+$/',
            'province' => 'required|string',
            'opening_time' => 'required|date_format:H:i',
            'closing_time' => 'required|date_format:H:i|after:opening_time',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $branch = Branch::create($request->all());

        if ($request->has('facilities')) {
            $branch->facilities()->sync($request->facilities);
        }

        AuditLog::log('create', 'Master Data', "Created branch: {$branch->name} ({$branch->code})");

        return response()->json([
            'message' => 'Branch created successfully',
            'branch' => $branch->load('facilities'),
        ], 201);
    }

    public function show($id)
    {
        $branch = Branch::with(['facilities'])
            ->withCount(['therapists', 'rooms', 'bookings'])
            ->findOrFail($id);

        return response()->json($branch);
    }

    public function update(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|min:3|max:100|regex:/[a-zA-Z]/',
            'code' => ['sometimes', 'required', 'string', 'unique:branches,code,' . $id, 'regex:/^[A-Z0-9-]{3,50}$/'],
            'address' => 'sometimes|required|string|min:10',
            'phone' => ['sometimes', 'required', 'string', 'regex:/^(\+62|0)[1-9][0-9]{7,15}$/'],
            'city' => 'sometimes|required|string|regex:/^[a-zA-Z\s.]+$/',
            'province' => 'sometimes|required|string',
            'opening_time' => 'sometimes|required|date_format:H:i',
            'closing_time' => 'sometimes|required|date_format:H:i|after:opening_time',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $branch->update($request->all());

        if ($request->has('facilities')) {
            $branch->facilities()->sync($request->facilities);
        }

        AuditLog::log('update', 'Master Data', "Updated branch: {$branch->name}");

        return response()->json([
            'message' => 'Branch updated successfully',
            'branch' => $branch->load('facilities'),
        ]);
    }

    public function destroy($id)
    {
        $branch = Branch::findOrFail($id);
        $name = $branch->name;
        $branch->delete();

        AuditLog::log('delete', 'Master Data', "Deleted branch: {$name}");

        return response()->json([
            'message' => 'Branch deleted successfully',
        ]);
    }

    public function facilities($id)
    {
        $facilities = \App\Models\Facility::where('branch_id', $id)->get();
        return response()->json($facilities);
    }

    public function updateFacilities(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $facilityIds = $request->input('facility_ids', []);
        if (!is_array($facilityIds)) {
            $facilityIds = [];
        }
        $facilityIds = array_filter($facilityIds, function($id) {
            return is_numeric($id);
        });

        \App\Models\Facility::where('branch_id', $id)->update(['branch_id' => null]);

        if (!empty($facilityIds)) {
            \App\Models\Facility::whereIn('id', $facilityIds)
                ->where('is_global', false)
                ->update(['branch_id' => $id]);
        }

        return response()->json([
            'message' => 'Branch facilities updated successfully'
        ]);
    }
}
