<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $branchIdFilter = ($request->has('branch_id') && $request->branch_id !== 'all')
            ? $request->branch_id
            : null;

        $query = Room::with([
            'branches',
            'blocks' => function ($q) use ($branchIdFilter) {
                $q->where('is_active', true)
                    ->where('end_date', '>=', now()->toDateString())
                    ->when($branchIdFilter, function ($branchQuery) use ($branchIdFilter) {
                        $branchQuery->where(function ($nested) use ($branchIdFilter) {
                            $nested->whereNull('branch_id')
                                ->orWhere('branch_id', $branchIdFilter);
                        });
                    })
                    ->orderBy('start_date')
                    ->orderBy('start_time');
            },
        ]);

        if ($branchIdFilter) {
            $branchId = $branchIdFilter;
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                  ->orWhere('branch_id', $branchId)
                  ->orWhereHas('branches', function($bq) use ($branchId) {
                      $bq->where('branches.id', $branchId);
                  })
                  ->orWhere(function ($orphanRoomQuery) {
                      $orphanRoomQuery->whereNull('branch_id')
                          ->whereDoesntHave('branches');
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
        $isGlobal = $request->input('is_global', $room->is_global);
        $branchIds = $request->input('branch_ids', $room->branches()->pluck('branches.id')->toArray());

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
            'type' => 'required|string|in:standard,vvip',
            'capacity' => 'required|integer|in:1,2',
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
        $room = Room::with([
            'branches',
            'blocks' => function ($q) {
                $q->where('is_active', true)
                    ->orderBy('start_date', 'desc')
                    ->orderBy('start_time');
            },
        ])->findOrFail($id);
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
            'type' => 'sometimes|required|string|in:standard,vvip',
            'capacity' => 'sometimes|integer|in:1,2',
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

        $roomData = $data;
        if ($request->has('branch_id') || $request->has('is_global') || $request->has('branch_ids')) {
            $roomData = array_merge($roomData, [
                'is_global' => $isGlobal,
                'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
            ]);
        }

        $room->update($roomData);

        if ($request->has('branch_id') || $request->has('is_global') || $request->has('branch_ids')) {
            if ($isGlobal) {
                $room->branches()->detach();
            } else {
                $room->branches()->sync($branchIds);
            }
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

    public function blocks(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $query = $room->blocks()->orderBy('start_date', 'desc')->orderBy('start_time');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true)
                ->where('end_date', '>=', now()->toDateString());
        }

        return response()->json($query->get());
    }

    public function storeBlock(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'branch_id' => 'nullable|exists:branches,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $block = RoomBlock::create([
            'room_id' => $room->id,
            'branch_id' => $request->input('branch_id'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'reason' => $request->input('reason') ?: 'Digunakan',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Room block created successfully',
            'block' => $block,
        ], 201);
    }

    public function deleteBlock($roomId, $blockId)
    {
        $block = RoomBlock::where('room_id', $roomId)->findOrFail($blockId);
        $block->delete();

        return response()->json([
            'message' => 'Room block deleted successfully',
        ]);
    }
}
