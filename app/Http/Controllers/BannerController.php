<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request)
    {
        $query = Banner::with('branches');

        if ($request->has('status')) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }

        if ($request->has('start_date')) {
            $query->whereDate('start_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('end_date', '<=', $request->end_date);
        }

        if ($request->has('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        if ($request->has('branch_id') && $request->branch_id !== 'all') {
            $branchId = $request->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->where('is_global', true)
                    ->orWhereHas('branches', function ($bq) use ($branchId) {
                        $bq->where('branches.id', $branchId);
                    });
            });
        }

        // For customer app, only show active banners
        if (!in_array(auth()->user()->role ?? '', ['admin', 'owner'])) {
            $query->active();
        }

        $banners = $query->orderBy('position')->orderBy('created_at', 'desc')->get();

        return response()->json($banners);
    }

    public function show($id)
    {
        $banner = Banner::with('branches')->findOrFail($id);
        return response()->json($banner);
    }

    public function store(Request $request)
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

        $this->validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'link_url' => 'nullable|url',
            'link_type' => 'nullable|string',
            'type' => 'nullable|in:promo,news,announcement',
            'position' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
        ]);
        $bannerData = array_merge($request->all(), [
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        $banner = Banner::create($bannerData);

        if (!$isGlobal && !empty($branchIds)) {
            $banner->branches()->sync($branchIds);
        }

        return response()->json($banner->load('branches'), 201);
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $isGlobal = $request->input('is_global', $banner->is_global);
        $branchIds = $request->input('branch_ids', []);

        if ($request->has('branch_id')) {
            if ($request->branch_id == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$request->branch_id];
            }
        }

        $this->validate($request, [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string',
            'link_url' => 'nullable|url',
            'link_type' => 'nullable|string',
            'type' => 'nullable|in:promo,news,announcement',
            'position' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
            'is_active' => 'boolean',
        ]);

        $bannerData = array_merge($request->all(), [
            'is_global' => $isGlobal,
            'branch_id' => $isGlobal ? null : ($branchIds[0] ?? null),
        ]);

        $banner->update($bannerData);

        if ($isGlobal) {
            $banner->branches()->detach();
        } else if (!empty($branchIds)) {
            $banner->branches()->sync($branchIds);
        }

        return response()->json($banner->load('branches'));
    }

    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->branches()->detach();
        $banner->delete();
        return response()->json(['message' => 'Banner deleted successfully']);
    }

    public function reorder(Request $request)
    {
        // Expecting an array of IDs in order: [1, 5, 2, 8]
        $ids = $request->input('ids');

        // If it's a direct array payload, handle it.
        if (!is_array($ids)) {
            $ids = $request->all();
        }

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'Invalid data format or empty IDs'], 422);
        }

        foreach ($ids as $index => $id) {
            Banner::where('id', $id)->update(['position' => $index + 1]);
        }

        return response()->json(['message' => 'Banners reordered successfully']);
    }

    public function uploadImage(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $this->validate($request, [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(base_path('public/uploads/banners'), $filename);

            $banner->image_url = url('/uploads/banners/' . $filename);
            $banner->save();

            return response()->json($banner);
        }

        return response()->json(['message' => 'No image provided'], 400);
    }
    public function incrementViews(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);
        $userId = auth()->id();
        $ip = $request->ip();

        $query = \App\Models\BannerInteraction::where('banner_id', $id)
            ->where('type', 'view');

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('ip_address', $ip)->whereNull('user_id');
        }

        if (!$query->exists()) {
            \App\Models\BannerInteraction::create([
                'banner_id' => $id,
                'user_id' => $userId,
                'ip_address' => $ip,
                'type' => 'view'
            ]);
            $banner->increment('views');
        }

        return response()->json(['message' => 'Views processed', 'views' => $banner->views]);
    }

    public function incrementClicks(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);
        $userId = auth()->id();
        $ip = $request->ip();

        $query = \App\Models\BannerInteraction::where('banner_id', $id)
            ->where('type', 'click');

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('ip_address', $ip)->whereNull('user_id');
        }

        if (!$query->exists()) {
            \App\Models\BannerInteraction::create([
                'banner_id' => $id,
                'user_id' => $userId,
                'ip_address' => $ip,
                'type' => 'click'
            ]);
            $banner->increment('clicks');
        }

        return response()->json(['message' => 'Clicks processed', 'clicks' => $banner->clicks]);
    }
}
