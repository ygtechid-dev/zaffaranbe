<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $query = News::with(['author', 'branches']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
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

        if ($request->has('start_date')) {
            $query->whereDate('published_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('published_at', '<=', $request->end_date);
        }

        if ($request->has('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        $limit = $request->input('limit', 10);

        if ($limit > 0) {
            $news = $query->orderBy('created_at', 'desc')->paginate($limit);
        } else {
            $news = $query->orderBy('created_at', 'desc')->get();
        }

        return response()->json($news);
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
            'category' => 'required|string',
            'content' => 'nullable|string',
            'status' => 'required|in:published,draft',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        $data = $request->all();
        $data['author_id'] = auth()->id();
        $data['is_global'] = $isGlobal;
        $data['branch_id'] = $isGlobal ? null : ($branchIds[0] ?? null);

        if ($data['status'] === 'published') {
            $data['published_at'] = Carbon::now();
        }

        $news = News::create($data);

        if (!$isGlobal && !empty($branchIds)) {
            $news->branches()->sync($branchIds);
        }

        AuditLog::log('create', 'news', "Created article: {$news->title}");

        return response()->json($news->load('branches'), 201);
    }

    public function show($id)
    {
        $news = News::with(['author', 'branches'])->findOrFail($id);
        return response()->json($news);
    }

    public function update(Request $request, $id)
    {
        $news = News::findOrFail($id);

        $isGlobal = $request->input('is_global', $news->is_global);
        $branchIds = $request->input('branch_ids', []);

        if ($request->has('branch_id')) {
            if ($request->branch_id == 0) {
                $isGlobal = true;
            } else if (empty($branchIds)) {
                $branchIds = [$request->branch_id];
            }
        }

        $this->validate($request, [
            'title' => 'sometimes|string|max:255',
            'category' => 'sometimes|string',
            'content' => 'nullable|string',
            'status' => 'sometimes|in:published,draft',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        $data = $request->all();
        $data['is_global'] = $isGlobal;
        $data['branch_id'] = $isGlobal ? null : ($branchIds[0] ?? null);

        // Set published_at when status changes to published
        if (isset($data['status']) && $data['status'] === 'published' && $news->status !== 'published') {
            $data['published_at'] = Carbon::now();
        }

        $news->update($data);

        if ($isGlobal) {
            $news->branches()->detach();
        } else if (!empty($branchIds)) {
            $news->branches()->sync($branchIds);
        }

        AuditLog::log('update', 'news', "Updated article: {$news->title}");

        return response()->json($news->load('branches'));
    }

    public function destroy($id)
    {
        $news = News::findOrFail($id);
        $title = $news->title;
        $news->branches()->detach();
        $news->delete();

        AuditLog::log('delete', 'news', "Deleted article: {$title}");

        return response()->json(['message' => 'Article deleted successfully']);
    }
}
