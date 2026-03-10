<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $query = Room::with('branches');

        if ($request->has('branch_id') && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                  ->orWhere('branch_id', $branchId)
                  ->orWhereHas('branches', function($bq) use ($branchId) {
                      $bq->where('branches.id', $branchId);
                  });
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $rooms = $query->orderBy('name')->get();

        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $isGlobal = $request->input('is_global', false);
        $branchIds = $request->input('branch_ids', []);

        if (isset($data['branch_id'])) {
            if ($data['branch_id'] == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$data['branch_id']];
            }
        }

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1',
            'extra_charge' => 'nullable|numeric|min:0',
            'facilities' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:available,occupied,maintenance',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (empty($data['code'])) {
            $data['code'] = 'ROOM-' . strtoupper(uniqid());
        }

        // Prepare room data, ensuring branch_id and is_global are correctly set
        $roomData = array_merge($data, [
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        $room = Room::create($roomData);

        if (!$isGlobal && !empty($branchIds)) {
            $room->branches()->sync($branchIds);
        }

        return response()->json([
            'message' => 'Room created successfully',
            'room' => $room->load('branches'),
        ], 201);
    }

    public function show($id)
    {
        $room = Room::with('branches')->findOrFail($id);
        return response()->json($room);
    }

    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $data = $request->all();
        $isGlobal = $request->input('is_global', false);
        $branchIds = $request->input('branch_ids', []);

        if (isset($data['branch_id'])) {
            if ($data['branch_id'] == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$data['branch_id']];
            }
        }

        $validator = Validator::make($data, [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|nullable|string',
            'capacity' => 'sometimes|integer|min:1',
            'quantity' => 'sometimes|integer|min:1',
            'extra_charge' => 'nullable|numeric|min:0',
            'facilities' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:available,occupied,maintenance',
            'is_active' => 'sometimes|boolean',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Prepare room data, ensuring branch_id and is_global are correctly set
        $roomData = array_merge($data, [
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        $room->update($roomData);

        if ($isGlobal) {
            $room->branches()->detach();
        } else {
            $room->branches()->sync($branchIds);
        }

        return response()->json([
            'message' => 'Room updated successfully',
            'room' => $room->load('branches'),
        ]);
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully',
        ]);
    }
}
