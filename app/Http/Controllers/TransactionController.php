<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['booking', 'branch:id,name', 'cashier:id,name']);

        // Filter by cashier's active shift
        if ($request->has('shift_only') && $request->shift_only) {
            $activeShift = DB::table('cashier_shifts')
                ->where('cashier_id', auth()->id())
                ->whereNull('clock_out')
                ->first();

            if ($activeShift) {
                $query->where('cashier_id', auth()->id())
                    ->where('transaction_date', '>=', $activeShift->clock_in);
            }
        }

        $perPage = $request->input('per_page', 15);
        if ($perPage === 'none' || $request->input('all') === 'true') {
            $transactions = $query->orderBy('transaction_date', 'desc')->get();
        } else {
            $transactions = $query->orderBy('transaction_date', 'desc')
                ->paginate(is_numeric($perPage) ? (int)$perPage : 15);
        }

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'booking_ids' => 'nullable|array',
            'booking_ids.*' => 'exists:bookings,id',
            'payment_log_id' => 'nullable|exists:payment_logs,id',
            'type' => 'required|in:booking,walk_in',
            'payment_type' => 'nullable|in:full,dp',
            'subtotal' => 'required|numeric|min:0',
            'original_total' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'payment_method' => 'required|string', // Dynamic from payment_methods table
            'cash_received' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'nullable|array', // POS Items
            'customer_id' => 'nullable', // Removed exists check to allow flexibility, handled in logic
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Calculate change if cash payment
        $changeAmount = null;
        if ($request->payment_method === 'cash' && $request->cash_received) {
            $changeAmount = $request->cash_received - $request->total;
        }

        DB::beginTransaction();
        try {
            $bookingId = $request->booking_id;
            $bookingIds = $request->booking_ids;

            // If booking_ids provided, use first one as primary bookingId if not set
            if (!$bookingId && !empty($bookingIds) && is_array($bookingIds)) {
                $bookingId = $bookingIds[0];
            }

            // If booking_id provided but no booking_ids, make array
            if ($bookingId && empty($bookingIds)) {
                $bookingIds = [$bookingId];
            }

            $paymentLogId = $request->payment_log_id;
            $paymentType = $request->payment_type ?? 'full';

            // Handle existing Bookings (from the new direct flow)
            if (!empty($bookingIds)) {
                foreach ($bookingIds as $bId) {
                    $booking = \App\Models\Booking::find($bId);
                    if ($booking) {
                        $paymentStatus = $paymentType === 'dp' ? 'partial' : 'paid';
                        $bookingData = [
                            'payment_status' => $paymentStatus,
                            'expires_at' => null,
                        ];

                        $bookingData['status'] = 'confirmed';
                        $bookingData['confirmed_at'] = \Carbon\Carbon::now();

                        $booking->update($bookingData);
                    }
                }
            }

            // Handle PaymentLog if provided (fallback for backward compatibility)
            if ($paymentLogId) {
                $log = \App\Models\PaymentLog::find($paymentLogId);
                if ($log && $log->status === 'pending') {
                    $log->update(['status' => 'settlement']);
                    $data = $log->booking_data;

                    if (!isset($data['is_pos']) || !$data['is_pos']) {
                        $newBooking = \App\Models\Booking::create([
                            'user_id' => $data['user_id'] ?? null,
                            'branch_id' => $data['branch_id'] ?? null,
                            'service_id' => $data['service_id'] ?? null,
                            'therapist_id' => $data['therapist_id'] ?? null,
                            'room_id' => $data['room_id'] ?? null,
                            'booking_date' => $data['booking_date'] ?? null,
                            'start_time' => $data['start_time'] ?? null,
                            'end_time' => $data['end_time'] ?? null,
                            'duration' => $data['duration'] ?? 0,
                            'service_price' => $data['service_price'] ?? 0,
                            'room_charge' => $data['room_charge'] ?? 0,
                            'total_price' => $data['total_price'] ?? 0,
                            'status' => 'confirmed',
                            'payment_status' => 'paid',
                            'confirmed_at' => \Carbon\Carbon::now(),
                            'notes' => ($data['notes'] ?? '') . "\nPaid via POS",
                        ]);
                        $bookingId = $newBooking->id;
                    }
                }
            }

            // If Walk-in with Items, create Bookings automatically (Consolidated)
            if ($request->type === 'walk_in' && $request->has('items') && is_array($request->items) && count($request->items) > 0) {
                // Filter for service items vs product items
                $serviceItems = array_filter($request->items, function ($item) {
                    $itemType = $item['item_type'] ?? (isset($item['product_id']) ? 'product' : 'service');
                    return $itemType === 'service' || (isset($item['service_id']) && $item['service_id']);
                });

                if (!empty($serviceItems)) {
                    // Determine User (Customer)
                    $userId = $request->customer_id;

                    if (!$userId) {
                        $guestUser = \App\Models\User::where('email', 'guest@naqupos.com')->first();
                        $userId = $guestUser ? $guestUser->id : auth()->id();
                    }

                    // Use first service item for master booking details
                    $firstServiceItem = reset($serviceItems);
                    $service = \App\Models\Service::find($firstServiceItem['service_id'] ?? null);
                    $duration = $service ? ($service->duration ?? 60) : 60;

                    $bookingDate = isset($firstServiceItem['booking_date'])
                        ? \Carbon\Carbon::parse($firstServiceItem['booking_date'])->toDateString()
                        : \Carbon\Carbon::now()->toDateString();
                    $startTime = isset($firstServiceItem['start_time'])
                        ? \Carbon\Carbon::parse($firstServiceItem['start_time'])->format('H:i:s')
                        : \Carbon\Carbon::now()->toTimeString();
                    $endTime = \Carbon\Carbon::parse($bookingDate . ' ' . $startTime)->addMinutes($duration)->toTimeString();

                    // Find or create Room
                    $roomId = $firstServiceItem['room_id'] ?? \App\Models\Room::where('branch_id', $request->branch_id)->value('id') ?? \App\Models\Room::value('id');
                    if (!$roomId) {
                        $defRoom = \App\Models\Room::create(['branch_id' => $request->branch_id, 'name' => 'Default Room', 'capacity' => 1, 'is_active' => true]);
                        $roomId = $defRoom->id;
                    }

                    $newBooking = \App\Models\Booking::create([
                        'booking_ref' => 'B-POS-' . time() . '-' . rand(100, 999),
                        'user_id' => $userId,
                        'branch_id' => $request->branch_id,
                        'service_id' => $firstServiceItem['service_id'] ?? null,
                        'therapist_id' => $firstServiceItem['therapist_id'] ?? null,
                        'room_id' => $roomId,
                        'booking_date' => $bookingDate,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'duration' => $duration,
                        'service_price' => $firstServiceItem['price'] ?? 0,
                        'room_charge' => 0,
                        'total_price' => $request->subtotal, // Use total subtotal for the main booking record
                        'status' => 'confirmed',
                        'payment_status' => 'paid',
                        'confirmed_at' => \Carbon\Carbon::now(),
                        'notes' => $request->notes ?: 'Walk-in POS Transaction'
                    ]);

                    $bookingId = $newBooking->id;
                    if (!is_array($bookingIds)) $bookingIds = [];
                    $bookingIds[] = $newBooking->id;

                    // Create Booking Items for service items only
                    foreach ($serviceItems as $index => $item) {
                        $itemService = \App\Models\Service::find($item['service_id'] ?? null);
                        $itemDuration = $itemService ? ($itemService->duration ?? 60) : 60;
                        $itemStartTime = isset($item['start_time']) ? \Carbon\Carbon::parse($item['start_time'])->format('H:i:s') : $startTime;
                        $itemEndTime = \Carbon\Carbon::parse($bookingDate . ' ' . $itemStartTime)->addMinutes($itemDuration)->toTimeString();

                        \App\Models\BookingItem::create([
                            'booking_id' => $newBooking->id,
                            'service_id' => $item['service_id'] ?? null,
                            'therapist_id' => $item['therapist_id'] ?? null,
                            'room_id' => $item['room_id'] ?? $roomId,
                            'price' => $item['price'] ?? 0,
                            'duration' => $itemDuration,
                            'start_time' => $itemStartTime,
                            'end_time' => $itemEndTime,
                            'status' => 'confirmed',
                            'guest_name' => $item['guest_name'] ?? ($index == 0 ? ($request->notes ? substr($request->notes, 0, 50) : 'Guest') : "Guest $index"),
                        ]);
                    }

                    // If it's a walk-in, it stays confirmed until manually completed
                    if ($newBooking->payment_status === 'paid') {
                        $newBooking->update([
                            'status' => 'confirmed',
                            'confirmed_at' => \Carbon\Carbon::now()
                        ]);
                    }
                }
            }

            $transaction = Transaction::create([
                'branch_id' => $request->branch_id,
                'booking_id' => $bookingId,
                'cashier_id' => auth()->id(),
                'type' => $request->type,
                'subtotal' => $request->subtotal,
                'discount' => $request->discount ?? 0,
                'tax' => $request->tax ?? 0,
                'total' => $request->total,
                'payment_method' => $request->payment_method,
                'cash_received' => $request->cash_received,
                'change_amount' => $changeAmount,
                // 'change_amount' => $changeAmount,
                'notes' => $request->notes . ($request->payment_type === 'dp' ? ' [DP]' : ' [Lunas]'),
                'transaction_date' => \Carbon\Carbon::now(),
            ]);

            // 4. Create Transaction Items & Record Stock Movements
            if ($request->has('items') && is_array($request->items)) {
                foreach ($request->items as $item) {
                    $itemType = $item['item_type'] ?? (isset($item['product_id']) && $item['product_id'] ? 'product' : 'service');
                    $qty = $item['quantity'] ?? 1;

                    \App\Models\TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'type' => $itemType,
                        'service_id' => $item['service_id'] ?? null,
                        'product_id' => $item['product_id'] ?? null,
                        'variant_id' => $item['variant_id'] ?? null,
                        'therapist_id' => $item['therapist_id'] ?? null,
                        'quantity' => $qty,
                        'price' => $item['price'] ?? 0,
                        'subtotal' => ($item['price'] ?? 0) * $qty,
                        'notes' => $item['notes'] ?? null,
                    ]);

                    // Record Stock Movement if item is a product
                    if ($itemType === 'product' && !empty($item['product_id'])) {
                        $this->recordProductSale($transaction, $item['product_id'], $item['variant_id'] ?? null, $qty);
                    }
                }
            }

            // Link PaymentLog to Transaction for traceability
            if ($paymentLogId) {
                $log = \App\Models\PaymentLog::find($paymentLogId);
                if ($log) {
                    // Update booking_data with transaction_id to maintain link
                    $bData = $log->booking_data ?? [];
                    $bData['transaction_id'] = $transaction->id;
                    $log->update(['booking_data' => $bData]);
                }
            }

            // Create Payment record for the booking to ensure it shows up in reports and calculations
            // Create Payment record for the booking(s) to ensure it shows up in reports and calculations
            if (!empty($bookingIds)) {
                $dbPaymentType = $paymentType === 'dp' ? 'down_payment' : 'full_payment';

                // Fetch all bookings first to calculate total expected price
                $bookings = \App\Models\Booking::whereIn('id', $bookingIds)->get();
                $sumBookingPrices = $bookings->sum('total_price');

                // Avoid division by zero
                $ratio = ($sumBookingPrices > 0) ? ($request->total / $sumBookingPrices) : 1;

                // Track remaining to handle rounding errors
                $remainingTotal = $request->total;
                $loopCount = 0;
                $totalCount = $bookings->count();

                foreach ($bookings as $booking) {
                    $loopCount++;

                    // Calculate proportional amount
                    if ($loopCount === $totalCount) {
                        // Last item gets the remainder to ensure exact match
                        $amount = $remainingTotal;
                    } else {
                        $amount = round($booking->total_price * $ratio, 2);
                        $remainingTotal -= $amount;
                    }

                    // Create Payment
                    \App\Models\Payment::create([
                        'booking_id' => $booking->id,
                        'payment_type' => $dbPaymentType,
                        'payment_method' => $request->payment_method,
                        'amount' => $amount,
                        'status' => 'success',
                        'paid_at' => \Carbon\Carbon::now(),
                        'payment_data' => [
                            'transaction_id' => $transaction->id,
                            'cashier_id' => auth()->id(),
                            'notes' => 'Created automatically from POS Transaction (Multi-booking split)'
                        ],
                        'payment_ref' => 'PAY-' . date('Ymd') . '-' . rand(1000, 9999) . '-' . $loopCount
                    ]);
                }
            } elseif ($bookingId) {
                // Fallback for single (should be covered by above loop, but safe keeps)
                $dbPaymentType = $paymentType === 'dp' ? 'down_payment' : 'full_payment';
                \App\Models\Payment::create([
                    'booking_id' => $bookingId,
                    'payment_type' => $dbPaymentType,
                    'payment_method' => $request->payment_method,
                    'amount' => $request->total,
                    'status' => 'success',
                    'paid_at' => \Carbon\Carbon::now(),
                    'payment_data' => [
                        'transaction_id' => $transaction->id,
                        'cashier_id' => auth()->id(),
                        'notes' => 'Created automatically from POS Transaction'
                    ],
                    'payment_ref' => 'PAY-' . date('Ymd') . '-' . rand(1000, 9999)
                ]);
            }
            // Note: If Walk-in without Booking ID but with auto-created booking, 
            // the Logic lines 116-227 sets $bookingId. 
            // We need to ensure that logic populates $bookingIds array too if we want to support multi-walk-in (unlikely for now).
            // Current walk-in logic looped and created bookings but only set $bookingId to the first one.
            // Let's rely on the loop above logic for Explicit Booking IDs passed from Frontend.
            // For Walk-ins (lines 116+), it creates bookings. We should add payments for them too?
            // Existing logic lines 202+ creates bookings with status='paid'. It does NOT create Payment records there.
            // It relies on THIS block to create Payment. 
            // So for Walk-ins, we should update $bookingIds array in that loop!



            // 6. Record commissions based on the transaction items
            $this->recordCommission($transaction);

            DB::commit();

            return response()->json([
                'message' => 'Transaction created successfully',
                'transaction' => $transaction->load(['booking', 'branch', 'cashier', 'items']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Transaction failed: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $transaction = Transaction::with(['booking.service', 'booking.therapist', 'booking.room', 'branch', 'cashier'])
            ->findOrFail($id);

        return response()->json($transaction);
    }

    public function print($id)
    {
        $transaction = Transaction::with(['booking.service', 'booking.user', 'branch', 'cashier'])
            ->findOrFail($id);

        // TODO: Generate PDF or receipt format
        // For now, just return data

        return response()->json([
            'message' => 'Print receipt',
            'transaction' => $transaction,
        ]);
    }
    private function recordCommission(Transaction $transaction)
    {
        // Clear existing commissions for this transaction to prevent duplicates
        \App\Models\StaffCommission::where('transaction_id', $transaction->id)->delete();

        foreach ($transaction->items as $item) {
            if ($item->therapist_id) {
                $this->createCommissionForSingleItem($transaction, $item);
            }
        }
    }

    private function createCommissionForSingleItem(Transaction $transaction, TransactionItem $item)
    {
        $therapist = $item->therapist ?: \App\Models\Therapist::find($item->therapist_id);
        if (!$therapist)
            return;

        // Base price is the line item price (unit price)
        $unitPrice = (float) $item->price;
        $lineSubtotal = (float) $item->subtotal;
        $type = $item->type; // 'service' or 'product'

        $commRule = $therapist->getCommissionForService($item->service_id ?: $item->product_id, $type);

        // Fetch company settings
        $settings = \App\Models\CompanySettings::where('branch_id', $transaction->branch_id)->first()
            ?? \App\Models\CompanySettings::whereNull('branch_id')->first();

        $calculateBeforeDiscount = $settings && $settings->commission_before_discount;
        $calculateAfterDiscount = $settings && $settings->commission_after_discount;

        // Apply Discount Logic (proportional)
        if (!$calculateBeforeDiscount && ($calculateAfterDiscount || !$settings)) {
            if ($transaction->discount > 0 && $transaction->subtotal > 0) {
                // Calculate proportional discount for this entire line item
                $totalSubtotal = (float) $transaction->subtotal;
                $ratio = $lineSubtotal / $totalSubtotal;
                $discountShare = (float) $transaction->discount * $ratio;

                $lineSubtotal -= $discountShare;
                if ($lineSubtotal < 0)
                    $lineSubtotal = 0;
            }
        }

        $amount = 0;
        if ($commRule['type'] === 'percent') {
            $amount = $lineSubtotal * ($commRule['rate'] / 100);
        } else {
            $amount = $commRule['rate'] * $item->quantity;
        }

        if ($amount > 0) {
            \App\Models\StaffCommission::create([
                'staff_id' => $therapist->id,
                'branch_id' => $transaction->branch_id,
                'transaction_id' => $transaction->id,
                'booking_id' => $transaction->booking_id,
                'item_id' => $item->service_id ?: $item->product_id,
                'item_type' => $type,
                'item_name' => $item->service ? $item->service->name : ($item->product ? $item->product->name : 'Item'),
                'sales_amount' => $lineSubtotal,
                'qty' => $item->quantity,
                'commission_percentage' => ($commRule['type'] === 'percent') ? $commRule['rate'] : 0,
                'commission_amount' => $amount,
                'payment_date' => $transaction->transaction_date ?: \Carbon\Carbon::now(),
                'status' => 'pending',
            ]);
        }
    }

    private function recordProductSale($transaction, $productId, $variantId, $quantity)
    {
        $branchId = $transaction->branch_id;
        $userId = auth()->id();

        if ($variantId) {
            $stock = \App\Models\ProductVariantStock::where('product_variant_id', $variantId)
                ->where('branch_id', $branchId)
                ->first();

            if ($stock) {
                $qtyBefore = (int) $stock->quantity;
                $qtyAfter = $qtyBefore - (int) $quantity;
                $stock->update(['quantity' => $qtyAfter]);

                \App\Models\StockMovement::create([
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'user_id' => $userId,
                    'movement_type' => 'out',
                    'quantity' => $quantity,
                    'quantity_before' => $qtyBefore,
                    'quantity_after' => $qtyAfter,
                    'cost_price' => $stock->average_cost,
                    'description' => 'Sale Order ' . $transaction->transaction_ref,
                    'movement_date' => \Carbon\Carbon::now(),
                ]);
            }
        } else {
            $stock = \App\Models\ProductStock::where('product_id', $productId)
                ->where('branch_id', $branchId)
                ->first();

            if ($stock) {
                $qtyBefore = (int) $stock->quantity;
                $qtyAfter = $qtyBefore - (int) $quantity;
                $stock->update(['quantity' => $qtyAfter]);

                \App\Models\StockMovement::create([
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'user_id' => $userId,
                    'movement_type' => 'out',
                    'quantity' => $quantity,
                    'quantity_before' => $qtyBefore,
                    'quantity_after' => $qtyAfter,
                    'cost_price' => $stock->average_cost,
                    'description' => 'Sale Order ' . $transaction->transaction_ref,
                    'movement_date' => \Carbon\Carbon::now(),
                ]);
            }
        }
    }
}
