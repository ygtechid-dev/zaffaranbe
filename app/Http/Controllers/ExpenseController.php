<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function download($id)
    {
        $expense = Expense::findOrFail($id);
        
        if (!$expense->receipt_image) {
            return response()->json(['message' => 'No receipt attached to this expense'], 404);
        }

        $path = 'public/' . $expense->receipt_image;
        if (!Storage::disk('local')->exists($path)) {
            // Try absolute path if disk not configured correctly
            $absPath = storage_path('app/public/' . $expense->receipt_image);
            if (!file_exists($absPath)) {
                return response()->json(['message' => 'File not found on server: ' . $path], 404);
            }
            return response()->download($absPath);
        }

        return Storage::disk('local')->download($path);
    }

    public function getCategories()
    {
        $categories = ExpenseCategory::with('branches')
            ->where('is_active', true)
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'category_id' => 'required|exists:expense_categories,id',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'receipt_image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'branch_id' => $request->branch_id,
            'category_id' => $request->category_id,
            'description' => $request->description,
            'amount' => $request->amount,
            'created_by' => auth()->id(),
            'cashier_shift_id' => $request->cashier_shift_id,
        ];

        // Handle file upload
        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('receipts', $filename, 'public');
            $data['receipt_image'] = $path;
        }

        $expense = Expense::create($data);
        $expense->load(['category', 'creator']);

        if ($expense->receipt_image) {
            $expense->receipt_url = url('storage/' . $expense->receipt_image);
        }

        return response()->json([
            'message' => 'Expense created successfully',
            'data' => $expense
        ], 201);
    }

    public function index(Request $request)
    {
        $query = Expense::with(['category', 'creator', 'shift']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->get('limit') === 'none' || $request->get('all') === 'true') {
            $expenses = $query->latest()->get();
        } else {
            $perPage = $request->get('limit', 10);
            $expenses = $query->latest()->paginate(is_numeric($perPage) ? (int)$perPage : 10);
        }

        // Add full URL for receipt images
        $expenses->transform(function ($expense) {
            if ($expense->receipt_image) {
                $expense->receipt_url = url('storage/' . $expense->receipt_image);
            }
            return $expense;
        });

        return response()->json($expenses);
    }

    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:expense_categories,id',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'receipt_image' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $expense->category_id = $request->category_id;
        $expense->description = $request->description;
        $expense->amount = $request->amount;

        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('receipts', $filename, 'public');
            $expense->receipt_image = $path;
        }

        $expense->save();
        $expense->load(['category', 'creator']);

        if ($expense->receipt_image) {
            $expense->receipt_url = url('storage/' . $expense->receipt_image);
        }

        return response()->json([
            'message' => 'Expense updated successfully',
            'data' => $expense
        ]);
    }

    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();
        return response()->json(['message' => 'Expense deleted successfully']);
    }

    // Category CRUD
    public function storeCategory(Request $request)
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:expense_categories,name',
            'type' => 'nullable|string',
            'icon' => 'nullable|string',
            'description' => 'nullable|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = ExpenseCategory::create([
            'name' => $request->name,
            'type' => $request->type ?? 'other',
            'icon' => $request->icon ?? '⚙️',
            'description' => $request->description,
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
            'is_active' => true
        ]);

        if (!$isGlobal && !empty($branchIds)) {
            $category->branches()->sync($branchIds);
        }

        return response()->json($category->load('branches'), 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $category = ExpenseCategory::findOrFail($id);

        $isGlobal = $request->input('is_global', false);
        $branchIds = $request->input('branch_ids', []);

        if ($request->has('branch_id')) {
            if ($request->branch_id == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$request->branch_id];
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:expense_categories,name,' . $id,
            'type' => 'nullable|string',
            'icon' => 'nullable|string',
            'description' => 'nullable|string',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->update([
            'name' => $request->name,
            'type' => $request->type ?? $category->type,
            'icon' => $request->icon ?? $category->icon,
            'description' => $request->description ?? $category->description,
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        if ($isGlobal) {
            $category->branches()->detach();
        } else {
            $category->branches()->sync($branchIds);
        }

        return response()->json($category->load('branches'));
    }

    public function deleteCategory($id)
    {
        $category = ExpenseCategory::findOrFail($id);

        if (Expense::where('category_id', $id)->exists()) {
            return response()->json(['message' => 'Kategori tidak dapat dihapus karena sudah memiliki data pengeluaran.'], 400);
        }

        $category->branches()->detach();
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
