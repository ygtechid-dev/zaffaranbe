<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LoyaltyPoint;
use App\Models\PointRedemption;
use Carbon\Carbon;

class LoyaltyReportController extends Controller
{
    private function genInvoiceRef($id) {
        return 'INV-' . (1000 + $id);
    }

    private function applyFilters($query, Request $request, $dateField = 'created_at') {
        if ($request->filled('date_from')) {
            $query->whereDate($dateField, '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate($dateField, '<=', $request->date_to);
        }
        if ($request->filled('branch_id') && $request->branch_id !== 'all') {
            $query->where('branch_id', $request->branch_id);
        }
        return $query;
    }

    // Penggunaan Point (Free Items)
    public function freeItems(Request $request) {
        $query = PointRedemption::where('type', 'item')->with('user');
        $this->applyFilters($query, $request);
        
        $data = $query->latest()->get()->map(function($r) {
            return [
                'id' => $r->id,
                'tanggal' => $r->created_at->format('d M Y'),
                'nomorFaktur' => $this->genInvoiceRef($r->id * 3), // Mock Invoice ID
                'pelanggan' => $r->user ? $r->user->name : 'Unknown',
                'digunakan' => $r->points_used,
                'jumlah' => 1, // Assumption: 1 item per redemption records
                'item' => $r->item_name
            ];
        });
        return response()->json(['data' => $data]);
    }

    // Penggunaan Point (Diskon)
    public function discounts(Request $request) {
        $query = PointRedemption::where('type', 'discount')->with('user');
        $this->applyFilters($query, $request);
        
        $data = $query->latest()->get()->map(function($r) {
            return [
                'id' => $r->id,
                'tanggal' => $r->created_at->format('d M Y'),
                'nomorFaktur' => $this->genInvoiceRef($r->id * 4), // Mock Invoice ID
                'pelanggan' => $r->user ? $r->user->name : 'Unknown',
                'waktuPenggunaan' => $r->created_at->format('H:i'),
                'digunakan' => $r->points_used,
                'jumlah' => (float)$r->discount_amount
            ];
        });
        return response()->json(['data' => $data]);
    }

    // Point Yang Bisa Digunakan (Log Perolehan & Status)
    public function availablePoints(Request $request) {
        $query = LoyaltyPoint::with('user');
        $this->applyFilters($query, $request); // Filters on created_at (earned date)
        
        $data = $query->latest()->get()->map(function($p) {
            $status = 'Aktif';
            if ($p->remaining_points == 0) {
                // Jika 0, bisa berarti fully used
                $status = 'Digunakan';
            } elseif ($p->expires_at < Carbon::now()) {
                // Jika masih ada sisa tapi expired
                $status = 'Kadaluarsa';
            }
            // Else Aktif (remaining > 0 && not expired)

            return [
                'id' => $p->id,
                'tanggal' => $p->created_at->format('d M Y'),
                'nomorFaktur' => $this->genInvoiceRef($p->id * 2), // Mock Source Invoice
                'pelanggan' => $p->user ? $p->user->name : 'Unknown',
                'tanggalKadaluarsa' => $p->expires_at->format('d M Y'),
                'status' => $status,
                'pointDiperoleh' => $p->points
            ];
        });
        return response()->json(['data' => $data]);
    }
}
