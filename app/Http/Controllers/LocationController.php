<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\Regency;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Get all provinces
     */
    public function provinces()
    {
        $provinces = Province::orderBy('name')->get();
        return response()->json($provinces);
    }

    /**
     * Get regencies by province
     */
    public function regencies(Request $request)
    {
        $query = \App\Models\Regency::query();

        if ($request->has('province_id')) {
            $query->where('province_id', $request->province_id);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $regencies = $query->orderBy('name')->get();
        return response()->json($regencies);
    }

    /**
     * Get districts by regency
     */
    public function districts(Request $request)
    {
        $query = \App\Models\District::query();

        if ($request->has('regency_id')) {
            $query->where('regency_id', $request->regency_id);
        }

        $districts = $query->orderBy('name')->get();
        return response()->json($districts);
    }

    /**
     * Get villages by district
     */
    public function villages(Request $request)
    {
        $query = \App\Models\Village::query();

        if ($request->has('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        $villages = $query->orderBy('name')->get();
        return response()->json($villages);
    }
}
