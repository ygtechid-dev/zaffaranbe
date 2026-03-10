<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Branch;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function financialSummary(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);

        // Monthly revenue across all branches
        $monthlyRevenue = Transaction::select(
            DB::raw('MONTH(transaction_date) as month'),
            DB::raw('SUM(total) as revenue')
        )
            ->whereYear('transaction_date', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Revenue by Branch
        $branchRevenue = Transaction::select(
            'branch_id',
            DB::raw('SUM(total) as revenue')
        )
            ->with('branch:id,name')
            ->whereYear('transaction_date', $year)
            ->groupBy('branch_id')
            ->get();

        return response()->json([
            'year' => $year,
            'monthly_revenue' => $monthlyRevenue,
            'branch_revenue' => $branchRevenue,
            'total_revenue_ytd' => $branchRevenue->sum('revenue')
        ]);
    }

    public function branchComparison(Request $request)
    {
        // Compare Key Metrics between branches
        $branches = Branch::withCount(['bookings', 'therapists'])
            ->get()
            ->map(function ($branch) {
                $revenue = Transaction::where('branch_id', $branch->id)
                    ->whereMonth('transaction_date', Carbon::now()->month)
                    ->sum('total');

                return [
                    'name' => $branch->name,
                    'active_therapists' => $branch->therapists_count,
                    'total_bookings' => $branch->bookings_count,
                    'revenue_this_month' => $revenue
                ];
            });

        return response()->json($branches);
    }
}
