<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;

class TaxController extends Controller
{
    public function index()
    {
        return response()->json(Tax::orderBy('name', 'asc')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'percentage' => 'required|numeric|min:0',
            'locations' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tax = Tax::create($request->all());

        AuditLog::log('create', 'Master Data', "Created tax: {$tax->name}");

        return response()->json($tax, 201);
    }

    public function update(Request $request, $id)
    {
        $tax = Tax::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'percentage' => 'sometimes|required|numeric|min:0',
            'locations' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tax->update($request->all());

        AuditLog::log('update', 'Master Data', "Updated tax: {$tax->name}");

        return response()->json($tax);
    }

    public function destroy($id)
    {
        $tax = Tax::findOrFail($id);
        $name = $tax->name;
        $tax->delete();

        AuditLog::log('delete', 'Master Data', "Deleted tax: {$name}");

        return response()->json(['message' => 'Tax deleted successfully']);
    }
}
