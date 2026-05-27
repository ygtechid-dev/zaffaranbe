<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\PromoService;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function index(Request $request)
    {
        $query = Promo::with('promoServices');

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
            'title'                           => 'required|string|max:255',
            'type'                            => 'required|in:percent,nominal,service,featured',
            'discount'                        => 'required_unless:type,service|required_unless:type,featured|numeric|min:0',
            'code'                            => 'required|string|unique:promos,code',
            'quota'                           => 'required|integer|min:1',
            'start_date'                      => 'required|date',
            'end_date'                        => 'required|date|after_or_equal:start_date',
            'promo_services'                  => 'required_if:type,service|required_if:type,featured|array|min:1',
            'promo_services.*.service_id'     => 'nullable|integer|exists:services,id',
            'promo_services.*.discount_type'  => 'required_with:promo_services|in:percent,nominal',
            'promo_services.*.discount_value' => 'required_with:promo_services|numeric|min:0',
        ]);

        $promo = Promo::create([
            'title'               => $request->title,
            'type'                => $request->type,
            'discount'            => in_array($request->type, ['service', 'featured']) ? 0 : $request->discount,
            'code'                => $request->code,
            'quota'               => $request->quota,
            'start_date'          => $request->start_date,
            'end_date'            => $request->end_date,
            'description'         => $request->description,
            'branch_id'           => $request->branch_id,
            'service_category_id' => $request->service_category_id,
            'status'              => 'active',
        ]);

        if (in_array($request->type, ['service', 'featured']) && $request->has('promo_services')) {
            foreach ($request->promo_services as $ps) {
                $promo->promoServices()->create([
                    'service_id'          => $ps['service_id'] ?? null,
                    'service_category_id' => $ps['service_category_id'] ?? null,
                    'discount_type'       => $ps['discount_type'] ?? 'percent',
                    'discount_value'      => $ps['discount_value'] ?? 0,
                ]);
            }
        }

        $promo->load('promoServices');

        AuditLog::log('create', 'promo', "Created promo: {$promo->title}");

        return response()->json($promo, 201);
    }

    public function show($id)
    {
        $promo = Promo::with('promoServices')->findOrFail($id);
        return response()->json($promo);
    }

    public function update(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);

        $this->validate($request, [
            'title'                           => 'sometimes|string|max:255',
            'type'                            => 'sometimes|in:percent,nominal,service,featured',
            'discount'                        => 'sometimes|numeric|min:0',
            'code'                            => 'sometimes|string|unique:promos,code,' . $id,
            'quota'                           => 'sometimes|integer|min:1',
            'start_date'                      => 'sometimes|date',
            'end_date'                        => 'sometimes|date|after_or_equal:start_date',
            'promo_services'                  => 'sometimes|array',
            'promo_services.*.service_id'     => 'nullable|integer|exists:services,id',
            'promo_services.*.discount_type'  => 'required_with:promo_services|in:percent,nominal',
            'promo_services.*.discount_value' => 'required_with:promo_services|numeric|min:0',
        ]);

        $type = $request->input('type', $promo->type);

        $promo->update([
            'title'               => $request->input('title', $promo->title),
            'type'                => $type,
            'discount'            => in_array($type, ['service', 'featured']) ? 0 : $request->input('discount', $promo->discount),
            'code'                => $request->input('code', $promo->code),
            'quota'               => $request->input('quota', $promo->quota),
            'start_date'          => $request->input('start_date', $promo->start_date),
            'end_date'            => $request->input('end_date', $promo->end_date),
            'description'         => $request->input('description', $promo->description),
            'branch_id'           => $request->input('branch_id', $promo->branch_id),
            'service_category_id' => $request->input('service_category_id', $promo->service_category_id),
        ]);

        if ($request->has('promo_services')) {
            // Replace all existing promo services
            $promo->promoServices()->delete();

            if (in_array($type, ['service', 'featured'])) {
                foreach ($request->promo_services as $ps) {
                    $promo->promoServices()->create([
                        'service_id'          => $ps['service_id'] ?? null,
                        'service_category_id' => $ps['service_category_id'] ?? null,
                        'discount_type'       => $ps['discount_type'] ?? 'percent',
                        'discount_value'      => $ps['discount_value'] ?? 0,
                    ]);
                }
            }
        }

        $promo->load('promoServices');

        AuditLog::log('update', 'promo', "Updated promo: {$promo->title}");

        return response()->json($promo);
    }

    public function destroy($id)
    {
        $promo = Promo::findOrFail($id);
        $title = $promo->title;

        $promo->promoServices()->delete();
        $promo->delete();

        AuditLog::log('delete', 'promo', "Deleted promo: {$title}");

        return response()->json(['message' => 'Promo deleted successfully']);
    }

    public function toggleFeatured(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);

        $isFeatured = $request->input('is_featured', false);

        $promo->update([
            'is_featured' => (bool) $isFeatured,
        ]);

        $promo->load('promoServices');

        AuditLog::log('update', 'promo', ($isFeatured ? 'Set' : 'Unset') . " featured promo: {$promo->title}");

        return response()->json($promo);
    }

    public function validateCode(Request $request)
    {
        $this->validate($request, [
            'code' => 'required|string'
        ]);

        $promo = Promo::with('promoServices')->where('code', $request->code)->first();

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