<?php

namespace App\Http\Controllers;

use App\Models\Branch;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($branches);
    }

    public function show($id)
    {
        $branch = Branch::where('is_active', true)
            ->findOrFail($id);

        return response()->json($branch);
    }
}
