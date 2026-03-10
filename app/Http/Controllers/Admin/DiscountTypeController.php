<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;

class DiscountTypeController extends Controller
{
    public function index()
    {
        $discounts = DiscountType::orderBy('name', 'asc')->get();

        // Map snake_case to camelCase for frontend
        return response()->json($discounts->map(function ($discount) {
            $data = $discount->toArray();
            $data['appliesTo'] = $data['applies_to'];
            unset($data['applies_to']);
            return $data;
        }));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'appliesTo' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        if (isset($data['appliesTo'])) {
            $data['applies_to'] = $data['appliesTo'];
            unset($data['appliesTo']);
        }

        $discount = DiscountType::create($data);

        AuditLog::log('create', 'Master Data', "Created discount type: {$discount->name}");

        $response = $discount->toArray();
        $response['appliesTo'] = $response['applies_to'];
        unset($response['applies_to']);

        return response()->json($response, 201);
    }

    public function update(Request $request, $id)
    {
        $discount = DiscountType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'type' => 'sometimes|required|in:fixed,percentage',
            'value' => 'sometimes|required|numeric|min:0',
            'appliesTo' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        if (isset($data['appliesTo'])) {
            $data['applies_to'] = $data['appliesTo'];
            unset($data['appliesTo']);
        }

        $discount->update($data);

        AuditLog::log('update', 'Master Data', "Updated discount type: {$discount->name}");

        $response = $discount->toArray();
        $response['appliesTo'] = $response['applies_to'];
        unset($response['applies_to']);

        return response()->json($response);
    }

    public function destroy($id)
    {
        $discount = DiscountType::findOrFail($id);
        $name = $discount->name;
        $discount->delete();

        AuditLog::log('delete', 'Master Data', "Deleted discount type: {$name}");

        return response()->json(['message' => 'Discount type deleted successfully']);
    }
}
