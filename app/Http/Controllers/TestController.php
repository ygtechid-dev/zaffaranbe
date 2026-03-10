<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Service;
use Laravel\Lumen\Routing\Controller as BaseController;

class TestController extends BaseController
{
    public function search(Request $request)
    {
        $query = Service::query();

        $category = $request->category;
        if ($category) {
            $query->where('service_category_id', $category);
        }

        $searchTerm = $request->search;
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('description', 'like', '%' . $searchTerm . '%')
                    ->orWhere('code', 'like', '%' . $searchTerm . '%');
            });
        }

        return response()->json($query->get());
    }
}
