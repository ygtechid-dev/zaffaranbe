<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->input('branch_id');
        $startDate = $request->input('start_date', Carbon::today()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->endOfMonth()->toDateString());
        $staffId = $request->input('staff_id');
        $search = $request->input('search');

        $query = \App\Models\StaffCommission::with(['staff', 'transaction', 'branch', 'booking.service'])
            ->when($branchId, function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->when($staffId, function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($subQ) use ($search) {
                     $subQ->whereHas('transaction', function ($tQ) use ($search) {
                          $tQ->where('transaction_ref', 'like', "%{$search}%");
                     })
                     ->orWhere('item_name', 'like', "%{$search}%")
                     ->orWhereHas('staff', function ($sQ) use ($search) {
                          $sQ->where('name', 'like', "%{$search}%");
                     });
                });
            })
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        $limit = $request->input('limit', $request->input('per_page', 10));
        $commissions = $query->orderBy('created_at', 'desc')->paginate($limit);

        $data = $commissions->getCollection()->map(function ($comm) {
            return [
                'noFaktur' => $comm->transaction ? $comm->transaction->transaction_ref : '-',
                'tanggal' => $comm->created_at->format('d M Y'),
                'staff' => $comm->staff ? $comm->staff->name : '-',
                'lokasi' => $comm->branch ? $comm->branch->name : '-',
                'barang' => $comm->item_name,
                'jumlah' => $comm->qty,
                'nilai' => $comm->commission_amount,
                'id' => $comm->id,
            ];
        });

        return response()->json([
            'data' => $data,
            'current_page' => $commissions->currentPage(),
            'last_page' => $commissions->lastPage(),
            'total' => $commissions->total(),
            'per_page' => $commissions->perPage(),
        ]);
    }
}
