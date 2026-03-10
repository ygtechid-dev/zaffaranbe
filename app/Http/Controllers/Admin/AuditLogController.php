<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('module')) {
            $query->where('module', $request->module);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('created_at', [$request->from, $request->to]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 50);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Stats (calculated based on filtered query but without pagination)
        $statsQuery = clone $query;
        $total = $statsQuery->count();
        $today = (clone $statsQuery)->whereDate('created_at', date('Y-m-d'))->count();
        $logins = (clone $statsQuery)->where('action', 'login')->count();
        $uniqueUsers = (clone $statsQuery)->distinct('user_id')->count('user_id');

        return response()->json([
            'logs' => $logs,
            'stats' => [
                'total' => $total,
                'today' => $today,
                'logins' => $logins,
                'unique_users' => $uniqueUsers
            ]
        ]);
    }

    public function show($id)
    {
        $log = AuditLog::with('user')->findOrFail($id);
        return response()->json($log);
    }

    public function getModules()
    {
        $modules = [
            'Auth',
            'Dashboard',
            'POS',
            'Kalender',
            'Reservasi',
            'Layanan',
            'Staff',
            'Inventori',
            'Pelanggan',
            'Marketing',
            'Laporan',
            'Master Data',
            'Pembayaran',
            'Berlangganan',
            'Manajemen User',
            'Pengaturan'
        ];
        return response()->json($modules);
    }

    public function getUsers()
    {
        $users = AuditLog::distinct()
            ->select('user_id', 'user_name', 'user_role')
            ->whereNotNull('user_id')
            ->get();
        return response()->json($users);
    }

    public function export(Request $request)
    {
        $query = AuditLog::query();

        if ($request->has('from') && $request->has('to')) {
            $query->whereBetween('created_at', [$request->from, $request->to]);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        // Log the export action
        AuditLog::log('export', 'audit_logs', 'Exported audit logs');

        return response()->json([
            'data' => $logs,
            'count' => $logs->count()
        ]);
    }
}
