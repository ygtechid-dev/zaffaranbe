<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use App\Models\PointRedemption;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LoyaltyController extends Controller
{
    public function transactions(Request $request)
    {
        $branchId = $request->query('branch_id');
        $customerId = $request->query('customer_id');
        $type = $request->query('type'); // earn, redeem
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $search = $request->query('search');

        $earnings = LoyaltyPoint::with('user:id,name')
            ->when($branchId && $branchId !== 'all', function ($q) use ($branchId) {
                return $q->where('branch_id', $branchId);
            })
            ->when($customerId && $customerId !== 'all', function ($q) use ($customerId) {
                return $q->where('user_id', $customerId);
            })
            ->when($startDate, function ($q) use ($startDate) {
                return $q->whereDate('created_at', '>=', $startDate);
            })
            ->when($endDate, function ($q) use ($endDate) {
                return $q->whereDate('created_at', '<=', $endDate);
            })
            ->when($search, function ($q) use ($search) {
                return $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                });
            })
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'earn_' . $item->id,
                    'customerId' => $item->user_id,
                    'customerName' => $item->user->name ?? 'Unknown',
                    'type' => 'earn',
                    'points' => $item->points,
                    'description' => 'Perolehan poin dari transaksi',
                    'date' => $item->created_at->toDateString(),
                ];
            });

        $redemptions = PointRedemption::with('user:id,name')
            ->when($branchId && $branchId !== 'all', function ($q) use ($branchId) {
                return $q->where('branch_id', $branchId);
            })
            ->when($customerId && $customerId !== 'all', function ($q) use ($customerId) {
                return $q->where('user_id', $customerId);
            })
            ->when($startDate, function ($q) use ($startDate) {
                return $q->whereDate('created_at', '>=', $startDate);
            })
            ->when($endDate, function ($q) use ($endDate) {
                return $q->whereDate('created_at', '<=', $endDate);
            })
            ->when($search, function ($q) use ($search) {
                return $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                });
            })
            ->get()
            ->map(function ($item) {
                return [
                    'id' => 'redeem_' . $item->id,
                    'customerId' => $item->user_id,
                    'customerName' => $item->user->name ?? 'Unknown',
                    'type' => 'redeem',
                    'points' => $item->points_used,
                    'description' => $item->item_name ? "Penukaran item: {$item->item_name}" : "Penukaran diskon",
                    'date' => $item->created_at->toDateString(),
                ];
            });

        $all = $earnings->concat($redemptions);

        if ($type === 'earn') {
            $all = $earnings;
        } elseif ($type === 'redeem') {
            $all = $redemptions;
        }

        return response()->json($all->sortByDesc('date')->values());
    }

    public function members(Request $request)
    {
        $branchId = $request->query('branch_id');
        $search = $request->query('search');
        $tier = $request->query('tier');

        $users = User::where('role', 'customer')
            ->when($branchId && $branchId !== 'all', function ($q) use ($branchId) {
                return $q->where('branch_id', $branchId);
            })
            ->when($search, function ($q) use ($search) {
                return $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->get()
            ->map(function ($user) {
                $totalPoints = LoyaltyPoint::where('user_id', $user->id)->sum('points');
                $usedPoints = PointRedemption::where('user_id', $user->id)->sum('points_used');
                $availablePoints = LoyaltyPoint::where('user_id', $user->id)->where('expires_at', '>=', Carbon::now())->sum('remaining_points');
                
                // Simple tier logic
                $tier = 'bronze';
                if ($totalPoints >= 1000) $tier = 'platinum';
                elseif ($totalPoints >= 500) $tier = 'gold';
                elseif ($totalPoints >= 200) $tier = 'silver';

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'totalPoints' => (int)$totalPoints,
                    'usedPoints' => (int)$usedPoints,
                    'availablePoints' => (int)$availablePoints,
                    'tier' => $tier,
                    'totalSpent' => 0, // Should be calculated from transactions
                    'joinDate' => $user->created_at->toDateString(),
                ];
            });

        if ($tier && $tier !== 'all') {
            $users = $users->filter(function ($u) use ($tier) {
                return $u['tier'] === $tier;
            });
        }

        return response()->json($users->values());
    }

    public function stats(Request $request)
    {
        $branchId = $request->query('branch_id');

        $earned = LoyaltyPoint::when($branchId && $branchId !== 'all', function ($q) use ($branchId) {
            return $q->where('branch_id', $branchId);
        })->sum('points');

        $redeemed = PointRedemption::when($branchId && $branchId !== 'all', function ($q) use ($branchId) {
            return $q->where('branch_id', $branchId);
        })->sum('points_used');

        $activeMembers = User::where('role', 'customer')
            ->when($branchId && $branchId !== 'all', function ($q) use ($branchId) {
                return $q->where('branch_id', $branchId);
            })->count();

        return response()->json([
            'totalEarned' => (int)$earned,
            'totalRedeemed' => (int)$redeemed,
            'activeMembers' => $activeMembers,
            'avgPoints' => $activeMembers > 0 ? round(($earned - $redeemed) / $activeMembers) : 0
        ]);
    }
}
