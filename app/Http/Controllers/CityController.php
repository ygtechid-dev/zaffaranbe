<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * Get all cities
     */
    public function index(Request $request)
    {
        $query = City::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('province', 'like', "%{$search}%");
        }

        if ($request->has('province')) {
            $query->where('province', $request->province);
        }

        // Return all or paginate? Master data usually ok to return all if not too huge.
        // But 30-300 cities is fine.
        $cities = $query->orderBy('province')->orderBy('name')->get();

        return response()->json($cities);
    }
}
