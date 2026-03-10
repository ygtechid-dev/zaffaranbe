<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $query = Promo::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('branch_id') && $request->branch_id !== '' && $request->branch_id !== 'all') {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id)
                    ->orWhereNull('branch_id')
                    ->orWhere('branch_id', 0)
                    ->orWhere('branch_id', 'all');
            });
        } else if ($request->branch_id === 'all') {
            // No strict filter if 'all', just return all
        }


        if ($request->has('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('end_date', '<=', $request->end_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $limit = $request->input('limit', 10);

        if ($limit > 0) {
            $promos = $query->orderBy('created_at', 'desc')->paginate($limit);
        } else {
            $promos = $query->orderBy('created_at', 'desc')->get();
        }

        return response()->json($promos);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required|string|max:255',
            'type' => 'required|in:percent,nominal',
            'discount' => 'required|numeric|min:0',
            'code' => 'required|string|unique:promos,code',
            'quota' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $promo = Promo::create($request->all());

        AuditLog::log('create', 'promo', "Created promo: {$promo->title}");

        return response()->json($promo, 201);
    }

    public function show($id)
    {
        $promo = Promo::findOrFail($id);
        return response()->json($promo);
    }

    public function update(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);

        $this->validate($request, [
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:percent,nominal',
            'discount' => 'sometimes|numeric|min:0',
            'code' => 'sometimes|string|unique:promos,code,' . $id,
            'quota' => 'sometimes|integer|min:1',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date'
        ]);

        $promo->update($request->all());

        AuditLog::log('update', 'promo', "Updated promo: {$promo->title}");

        return response()->json($promo);
    }

    public function destroy($id)
    {
        $promo = Promo::findOrFail($id);
        $title = $promo->title;
        $promo->delete();

        AuditLog::log('delete', 'promo', "Deleted promo: {$title}");

        return response()->json(['message' => 'Promo deleted successfully']);
    }

    public function validateCode(Request $request)
    {
        $this->validate($request, [
            'code' => 'required|string'
        ]);

        $promo = Promo::where('code', $request->code)->first();

        if (!$promo) {
            return response()->json(['valid' => false, 'message' => 'Kode promo tidak ditemukan'], 404);
        }

        if ($request->has('branch_id') && $request->branch_id !== '' && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;
            if ($promo->branch_id !== null && $promo->branch_id != 0 && $promo->branch_id !== 'all' && $promo->branch_id != $branchId) {
                return response()->json(['valid' => false, 'message' => 'Kode promo tidak tersedia di cabang ini'], 400);
            }
        }

        if (!$promo->is_valid) {
            return response()->json(['valid' => false, 'message' => 'Kode promo sudah tidak berlaku'], 400);
        }

        return response()->json([
            'valid' => true,
            'promo' => $promo
        ]);
    }
}
