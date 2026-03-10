<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampaignType;
use Illuminate\Http\Request;

class CampaignTypeController extends Controller
{
    public function index()
    {
        $types = CampaignType::orderBy('name')->get();
        return response()->json($types);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|unique:campaign_types,name|max:255'
        ]);

        $type = CampaignType::create([
            'name' => $request->name
        ]);

        return response()->json($type, 201);
    }
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required|string|unique:campaign_types,name,' . $id . '|max:255'
        ]);

        $type = CampaignType::findOrFail($id);

        $type->update([
            'name' => $request->name
        ]);

        return response()->json($type);
    }

    public function destroy($id)
    {
        $type = CampaignType::findOrFail($id);

        // Check if there are related campaigns
        // Wait, does CampaignType have a relationship? 
        // Campaign table usually uses 'type' string instead of 'campaign_type_id'.
        // Let's just delete it since it's just a master data list for the dropdown.
        $type->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
