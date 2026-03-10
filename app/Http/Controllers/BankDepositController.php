<?php

namespace App\Http\Controllers;

use App\Models\BankDeposit;
use App\Models\CashBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankDepositController extends Controller
{
    /**
     * Get current cash balance for a branch
     */
    public function getCashBalance(Request $request)
    {
        $branchId = $request->get('branch_id');
        
        if (!$branchId) {
            return response()->json(['error' => 'Branch ID required'], 400);
        }

        $balance = CashBalance::firstOrCreate(
            ['branch_id' => $branchId],
            ['current_balance' => 0]
        );

        // Calculate Active Shift Cash (Laci)
        $activeShiftCash = 0;
        $activeShift = \App\Models\CashierShift::where('branch_id', $branchId)
            ->whereNull('clock_out')
            ->first();
            
        if ($activeShift) {
            // Calculate cash sales for this shift
             // Get cash-type payment method codes
            $cashPaymentCodes = \DB::table('payment_methods')
                ->where('type', 'cash')
                ->pluck('code')
                ->toArray();
            
            $cashPaymentCodes[] = 'cash';
            $cashPaymentCodes[] = 'CASH';

            $cashSales = \DB::table('transactions')
                ->where('cashier_id', $activeShift->cashier_id)
                ->where('branch_id', $branchId) // Ensure branch match
                ->whereIn('payment_method', $cashPaymentCodes)
                ->whereBetween('transaction_date', [$activeShift->clock_in, \Carbon\Carbon::now()])
                ->sum('total');

            $expenses = \DB::table('expenses')
                ->where('cashier_shift_id', $activeShift->id)
                ->sum('amount');
                
            $activeShiftCash = $activeShift->starting_cash + $cashSales - $expenses;
        }

        // Total available cash = Safe Balance + Active Drawer Balance
        $totalBalance = $balance->current_balance + $activeShiftCash;

        return response()->json([
            'branch_id' => $balance->branch_id,
            'current_balance' => $totalBalance, // Return the combined dynamic balance
            'safe_balance' => $balance->current_balance,
            'drawer_balance' => $activeShiftCash,
            'last_updated' => $balance->last_updated,
        ]);
    }

    /**
     * Get list of bank deposits
     */
    public function index(Request $request)
    {
        $query = BankDeposit::with(['branch', 'creator']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('deposit_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('deposit_date', '<=', $request->end_date);
        }

        if ($request->get('limit') === 'none' || $request->get('all') === 'true') {
            $deposits = $query->latest('deposit_date')->get();
        } else {
            $perPage = $request->get('limit', 10);
            $deposits = $query->latest('deposit_date')->paginate(is_numeric($perPage) ? (int)$perPage : 10);
        }

        // Add full URL for deposit proof
        $deposits->transform(function ($deposit) {
            if ($deposit->deposit_proof) {
                $deposit->deposit_proof_url = url('storage/' . $deposit->deposit_proof);
            }
            return $deposit;
        });

        return response()->json($deposits);
    }

    /**
     * Create a new bank deposit
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'amount' => 'required|numeric|min:0',
            'bank_name' => 'required|string',
            'account_number' => 'nullable|string',
            'deposit_date' => 'required|date',
            'notes' => 'nullable|string',
            'deposit_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Get or create cash balance for branch
            $cashBalance = CashBalance::firstOrCreate(
                ['branch_id' => $request->branch_id],
                ['current_balance' => 0]
            );

            // Calculate active shift cash for validation
            $activeShiftCash = 0;
            $activeShift = \App\Models\CashierShift::where('branch_id', $request->branch_id)
                ->whereNull('clock_out')
                ->first();
                
            if ($activeShift) {
                 // Get cash-type payment method codes
                $cashPaymentCodes = \DB::table('payment_methods')
                    ->where('type', 'cash')
                    ->pluck('code')
                    ->toArray();
                
                $cashPaymentCodes[] = 'cash';
                $cashPaymentCodes[] = 'CASH';

                $cashSales = \DB::table('transactions')
                    ->where('cashier_id', $activeShift->cashier_id)
                    ->where('branch_id', $request->branch_id)
                    ->whereIn('payment_method', $cashPaymentCodes)
                    ->whereBetween('transaction_date', [$activeShift->clock_in, \Carbon\Carbon::now()])
                    ->sum('total');

                $expenses = \DB::table('expenses')
                    ->where('cashier_shift_id', $activeShift->id)
                    ->sum('amount');
                    
                $activeShiftCash = $activeShift->starting_cash + $cashSales - $expenses;
            }

            $totalAvailable = $cashBalance->current_balance + $activeShiftCash;

            // Validate sufficient balance
            if ($totalAvailable < $request->amount) {
                return response()->json([
                    'error' => 'Saldo cash tidak mencukupi (Safe + Laci)',
                    'current_balance' => $totalAvailable,
                    'safe_balance' => $cashBalance->current_balance,
                    'drawer_balance' => $activeShiftCash,
                    'requested_amount' => $request->amount
                ], 400);
            }

            // If we are dipping into the drawer (amount > safe balance), 
            // we should technically record it.
            // But simply updating the cashBalance (Safe) to negative is a simple way 
            // to track that the Safe "owes" the difference or absorbed the drawer cash.
            // When the shift closes, it will add the full drawer amount to the Safe, 
            // bringing it back to positive/correct level.
            
            $cashBefore = $totalAvailable; // Use total available as the reference for "before" so user sees they had enough?
            // Actually, keep consistency with what we store. We store cashBalance updates.
            // But for the deposit record, we should probably record the 'cash_before' as the total available?
            // Let's record cash_before as the total available to be less confusing to user.
            
            $cashAfter = $cashBefore - $request->amount;

            $data = [
                'branch_id' => $request->branch_id,
                'created_by' => auth()->id(),
                'amount' => $request->amount,
                'cash_before' => $cashBefore,
                'cash_after' => $cashAfter,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'deposit_date' => $request->deposit_date,
                'notes' => $request->notes,
            ];

            // Handle file upload
            if ($request->hasFile('deposit_proof')) {
                $file = $request->file('deposit_proof');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('deposits', $filename, 'public');
                $data['deposit_proof'] = $path;
            }

            // Create deposit record
            $deposit = BankDeposit::create($data);

            // Update cash balance (Safe)
            // We subtract the amount from the SAFE balance. It might go negative if we took from Laci.
            // This is the chosen "simple" implementation.
            $newSafeBalance = $cashBalance->current_balance - $request->amount;
            
            $cashBalance->update([
                'current_balance' => $newSafeBalance,
                'last_updated' => \Carbon\Carbon::now()
            ]);

            DB::commit();

            $deposit->load(['branch', 'creator']);
            if ($deposit->deposit_proof) {
                $deposit->deposit_proof_url = url('storage/' . $deposit->deposit_proof);
            }

            return response()->json([
                'message' => 'Setoran bank berhasil dicatat',
                'data' => $deposit,
                'new_cash_balance' => $cashAfter // Return the "conceptual" new balance (Total - Amount)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Gagal menyimpan setoran',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cash balance manually (for initial setup or corrections)
     */
    public function updateCashBalance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'amount' => 'required|numeric',
            'type' => 'required|in:add,subtract,set', // add: tambah, subtract: kurangi, set: set langsung
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cashBalance = CashBalance::firstOrCreate(
            ['branch_id' => $request->branch_id],
            ['current_balance' => 0]
        );

        $oldBalance = $cashBalance->current_balance;

        switch ($request->type) {
            case 'add':
                $newBalance = $oldBalance + $request->amount;
                break;
            case 'subtract':
                $newBalance = $oldBalance - $request->amount;
                break;
            case 'set':
                $newBalance = $request->amount;
                break;
            default:
                $newBalance = $oldBalance;
        }

        $cashBalance->update([
            'current_balance' => $newBalance,
            'last_updated' => \Carbon\Carbon::now()
        ]);

        return response()->json([
            'message' => 'Saldo cash berhasil diperbarui',
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance
        ]);
    }

    /**
     * Get deposit statistics
     */
    public function getStats(Request $request)
    {
        $branchId = $request->get('branch_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = BankDeposit::query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($startDate) {
            $query->whereDate('deposit_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('deposit_date', '<=', $endDate);
        }

        $totalDeposits = $query->sum('amount');
        $depositCount = $query->count();

        // Get current cash balance
        $cashBalance = 0;
        if ($branchId) {
            $balance = CashBalance::where('branch_id', $branchId)->first();
            $cashBalance = $balance ? $balance->current_balance : 0;
        }

        return response()->json([
            'total_deposits' => $totalDeposits,
            'deposit_count' => $depositCount,
            'current_cash_balance' => $cashBalance,
        ]);
    }
}
