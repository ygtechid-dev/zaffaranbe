<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;
use App\Models\Role;

class UserController extends Controller
{
    private function roleIsGlobal(?string $roleName): bool
    {
        $roleName = strtolower((string) $roleName);
        if (in_array($roleName, ['super_admin', 'super admin', 'owner'])) {
            return true;
        }

        $role = Role::where('name', $roleName)->first();
        return (bool) ($role?->is_global ?? false);
    }

    public function index(Request $request)
    {
        $query = User::with(['therapist', 'branch'])->where('role', '!=', 'customer');

        if ($request->has('branch_id') && $request->branch_id != 'all' && $request->branch_id != '') {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id)
                    ->orWhereNull('branch_id');
            });
        } else {
            // If not super_admin/owner, restrict to own branch
            $user = auth()->user();
            if ($user && !in_array(strtolower($user->role), ['super_admin', 'owner']) && $user->branch_id) {
                $query->where(function ($q) use ($user) {
                    $q->where('branch_id', $user->branch_id)
                        ->orWhereNull('branch_id');
                });
            }
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required_without:staff_id|string|max:255',
            'email' => 'required_without:staff_id|email|unique:users,email',
            'phone' => 'required_without:staff_id|string|unique:users,phone',
            'role' => 'required|string',
            'password' => 'required|string|min:6',
            'staff_id' => 'nullable|exists:therapists,id|unique:users,staff_id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $name = $request->name;
        $email = $request->email;
        $phone = $request->phone;

        if ($request->staff_id) {
            $staff = \App\Models\Therapist::find($request->staff_id);
            if ($staff) {
                $name = $name ?: $staff->name;
                $email = $email ?: $staff->email;
                $phone = $phone ?: $staff->phone;
            }
        }

        $branchId = $this->roleIsGlobal($request->role) ? null : $request->branch_id;

        if (!$this->roleIsGlobal($request->role) && !$branchId) {
            return response()->json(['errors' => ['branch_id' => ['Cabang wajib dipilih untuk role cabang.']]], 422);
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $request->role,
            'branch_id' => $branchId,
            'staff_id' => $request->staff_id,
            'password' => Hash::make($request->password),
            'is_verified' => true,
            'is_active' => true,
        ]);

        AuditLog::log('create', 'Manajemen User', "Created user: {$user->email} with role: {$user->role}");

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    public function show($id)
    {
        $user = User::where('role', '!=', 'customer')
            ->findOrFail($id);

        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::where('role', '!=', 'customer')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required_without:staff_id|string|max:255',
            'email' => 'sometimes|required_without:staff_id|email|unique:users,email,' . $id,
            'phone' => 'sometimes|required_without:staff_id|string|unique:users,phone,' . $id,
            'role' => 'sometimes|required|string',
            'is_active' => 'sometimes|boolean',
            'password' => 'nullable|string|min:6',
            'staff_id' => 'nullable|exists:therapists,id|unique:users,staff_id,' . $id,
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['name', 'email', 'phone', 'role', 'is_active', 'staff_id', 'branch_id']);

        $nextRole = $data['role'] ?? $user->role;
        if ($this->roleIsGlobal($nextRole)) {
            $data['branch_id'] = null;
        } elseif ($request->has('branch_id') && !$request->branch_id) {
            return response()->json(['errors' => ['branch_id' => ['Cabang wajib dipilih untuk role cabang.']]], 422);
        } elseif (!$request->has('branch_id') && !$user->branch_id) {
            return response()->json(['errors' => ['branch_id' => ['Cabang wajib dipilih untuk role cabang.']]], 422);
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        AuditLog::log('update', 'Manajemen User', "Updated user: {$user->email}");

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    public function destroy($id)
    {
        $user = User::whereIn('role', ['super_admin', 'admin', 'cashier', 'owner', 'branch_manager'])
            ->findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'error' => 'You cannot delete your own account'
            ], 400);
        }

        $email = $user->email;
        $user->delete();

        AuditLog::log('delete', 'Manajemen User', "Deleted user: {$email}");

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function getAvailableStaff()
    {
        $staff = \App\Models\Therapist::whereDoesntHave('user')->get(['id', 'name', 'email', 'phone']);
        return response()->json($staff);
    }
}
