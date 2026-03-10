<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FacilityController extends Controller
{
    public function index(Request $request)
    {
        $query = Facility::query();

        if ($request->has('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id)
                    ->orWhere('is_global', true);
            });
        }

        $facilities = $query->with('branch')->orderBy('name', 'asc')->get();
        return response()->json($facilities);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'is_global' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $facility = Facility::create($request->all());

        return response()->json($facility->load('branch'), 201);
    }

    public function update(Request $request, $id)
    {
        $facility = Facility::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'is_global' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $facility->update($request->all());

        return response()->json($facility);
    }

    public function destroy($id)
    {
        $facility = Facility::findOrFail($id);
        $facility->delete();

        return response()->json(['message' => 'Facility deleted successfully']);
    }
}
