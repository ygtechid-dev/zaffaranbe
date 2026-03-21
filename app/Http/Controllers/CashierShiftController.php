<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;

class CashierShiftController extends Controller
{
    public function clockIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'starting_cash' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if user already has active shift
        $activeShift = CashierShift::where('cashier_id', auth()->id())
            ->whereNull('clock_out')
            ->first();

        if ($activeShift) {
            return response()->json([
                'error' => 'You already have an active shift. Please clock out first.'
            ], 400);
        }

        $shift = CashierShift::create([
            'cashier_id' => auth()->id(),
            'branch_id' => $request->branch_id,
            'clock_in' => \Carbon\Carbon::now(),
            'starting_cash' => $request->starting_cash,
        ]);

        AuditLog::log('clock_in', 'POS', "Staff clocked in. Starting cash: Rp " . number_format($request->starting_cash));

        // Deduct starting cash from branch cash balance
        $cashBalance = \App\Models\CashBalance::firstOrCreate(
            ['branch_id' => $request->branch_id],
            ['current_balance' => 0]
        );

        $cashBalance->update([
            'current_balance' => $cashBalance->current_balance - $request->starting_cash,
            'last_updated' => \Carbon\Carbon::now()
        ]);

        return response()->json([
            'message' => 'Clocked in successfully',
            'shift' => $shift->load(['cashier:id,name', 'branch:id,name']),
        ], 201);
    }

    public function clockOut(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ending_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $shift = CashierShift::where('cashier_id', auth()->id())
            ->whereNull('clock_out')
            ->firstOrFail();

        // Calculate expected cash from transactions
        // Calculate expected cash from transactions
        $cashSales = \DB::table('transactions')
            ->where('cashier_id', auth()->id())
            ->where('payment_method', 'cash')
            ->whereBetween('transaction_date', [$shift->clock_in, \Carbon\Carbon::now()])
            ->sum('total');

        $totalExpenses = \DB::table('expenses')
            ->where('cashier_shift_id', $shift->id)
            ->sum('amount');

        $expectedCash = $shift->starting_cash + $cashSales - $totalExpenses;

        $variance = $request->ending_cash - $expectedCash;

        $shift->update([
            'clock_out' => \Carbon\Carbon::now(),
            'ending_cash' => $request->ending_cash,
            'expected_cash' => $expectedCash,
            'variance' => $variance,
            'notes' => $request->notes,
        ]);

        AuditLog::log('clock_out', 'POS', "Staff clocked out. Ending cash: Rp " . number_format($request->ending_cash) . ". Variance: Rp " . number_format($variance));

        // Update cash balance for the branch
        $cashBalance = \App\Models\CashBalance::firstOrCreate(
            ['branch_id' => $shift->branch_id],
            ['current_balance' => 0]
        );

        // Add ending cash to branch balance
        $cashBalance->update([
            'current_balance' => $cashBalance->current_balance + $request->ending_cash,
            'last_updated' => \Carbon\Carbon::now()
        ]);

        return response()->json([
            'message' => 'Clocked out successfully',
            'shift' => $shift->fresh(['cashier', 'branch']),
            'variance' => $variance,
            'new_cash_balance' => $cashBalance->current_balance,
        ]);
    }

    public function currentShift()
    {
        $shift = CashierShift::with(['cashier:id,name', 'branch:id,name'])
            ->where('cashier_id', auth()->id())
            ->whereNull('clock_out')
            ->first();

        if (!$shift) {
            return response()->json([
                'message' => 'No active shift',
                'shift' => null,
            ]);
        }

        // Calculate Stats
        $transactions = \DB::table('transactions')
            ->where('cashier_id', auth()->id())
            ->whereBetween('transaction_date', [$shift->clock_in, \Carbon\Carbon::now()]);

        // Get cash-type payment method codes
        $cashPaymentCodes = \DB::table('payment_methods')
            ->where('type', 'cash')
            ->pluck('code')
            ->toArray();

        // Also include 'cash' literal for backwards compatibility
        $cashPaymentCodes[] = 'cash';
        $cashPaymentCodes[] = 'CASH';

        $totalSales = (clone $transactions)->sum('total');
        $cashSales = (clone $transactions)->whereIn('payment_method', $cashPaymentCodes)->sum('total');
        $nonCashSales = $totalSales - $cashSales;
        $count = (clone $transactions)->count();

   // GANTI BAGIAN INI di currentShift()
$dpSales = \DB::table('bookings')
    ->where('branch_id', $shift->branch_id)
    ->whereBetween('created_at', [$shift->clock_in, \Carbon\Carbon::now()])
    ->where('nominal_dp', '>', 0)
    ->sum('nominal_dp');

$totalTransactionSum = \DB::table('transactions')
    ->where('branch_id', $shift->branch_id)
    ->where('cashier_id', auth()->id())
    ->whereBetween('transaction_date', [$shift->clock_in, \Carbon\Carbon::now()])
    ->sum('total');

$fullSales = $totalTransactionSum - $dpSales;

// Payment Method Breakdown
$methodTotals = (clone $transactions)
    ->select('payment_method', \DB::raw('SUM(total) as total_amount'))
    ->groupBy('payment_method')
    ->get();

        // Map names from payment_methods table
        $paymentMethods = \DB::table('payment_methods')->get(['code', 'name']);
        $methodBreakdown = $methodTotals->map(function ($item) use ($paymentMethods) {
            $pm = $paymentMethods->where('code', $item->payment_method)->first();
            return [
                'code' => $item->payment_method,
                'name' => $pm ? $pm->name : ucfirst($item->payment_method),
                'total' => (float) $item->total_amount
            ];
        });

        $shift->total_sales = $totalSales;
        $shift->cash_sales = $cashSales;
        $shift->non_cash_sales = $nonCashSales;
        $shift->dp_sales = $dpSales;
        $shift->full_sales = $fullSales;
        $shift->transaction_count = $count;
        $shift->method_breakdown = $methodBreakdown;


        return response()->json([
            'shift' => $shift,
        ]);
    }

    public function index(Request $request)
    {
        $query = CashierShift::with(['cashier:id,name', 'branch:id,name']);

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('date')) {
            $query->whereDate('clock_in', $request->date);
        }

        $shifts = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($shifts);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'ending_cash' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $shift = CashierShift::findOrFail($id);

        $updates = [];

        if ($request->has('ending_cash')) {
            $oldEndingCash = $shift->ending_cash;
            $newEndingCash = $request->ending_cash;

            // Recalculate expected cash
            $cashSales = \DB::table('transactions')
                ->where('cashier_id', $shift->cashier_id)
                ->where('payment_method', 'cash')
                ->whereBetween('transaction_date', [$shift->clock_in, $shift->clock_out])
                ->sum('total');

            $totalExpenses = \DB::table('expenses')
                ->where('cashier_shift_id', $shift->id)
                ->sum('amount');

            $expectedCash = (float) $shift->starting_cash + (float) $cashSales - (float) $totalExpenses;
            $variance = $newEndingCash - $expectedCash;

            $updates['ending_cash'] = $newEndingCash;
            $updates['expected_cash'] = $expectedCash;
            $updates['variance'] = $variance;

            // Update Branch Cash Balance
            $cashBalance = \App\Models\CashBalance::firstOrCreate(
                ['branch_id' => $shift->branch_id],
                ['current_balance' => 0]
            );

            // Correct the balance by removing the old ending cash and adding the new one
            // Note: Since we only track ending_cash in the vault after clock_out,
            // this correction only makes sense if the shift is already finished.
            if ($shift->clock_out) {
                $diff = $newEndingCash - $oldEndingCash;
                $cashBalance->current_balance += $diff;
                $cashBalance->save();
            }
        }

        if ($request->has('notes')) {
            $updates['notes'] = $request->notes;
        }

        $shift->update($updates);

        AuditLog::log('shift_correction', 'POS', "Shift corrected. ID: $id. Updates: " . json_encode($updates));

        return response()->json([
            'message' => 'Shift updated successfully',
            'shift' => $shift->fresh(['cashier', 'branch'])
        ]);
    }
}
