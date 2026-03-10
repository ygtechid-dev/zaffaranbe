<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all()->map(function ($role) {
            $role->user_count = User::where('role', $role->name)->count();
            return $role;
        });

        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'is_global' => 'boolean'
        ]);

        $role = Role::create([
            'name' => strtolower($request->name),
            'description' => $request->description,
            'permissions' => $request->permissions,
            'is_global' => $request->is_global ?? false
        ]);

        AuditLog::log('create', 'Manajemen User', "Created role: {$role->name}");

        return response()->json($role, 201);
    }

    public function show($id)
    {
        $role = Role::findOrFail($id);
        $role->users = User::where('role', $role->name)->get();
        return response()->json($role);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // Prevent modifying owner role
        if ($role->name === 'owner') {
            return response()->json(['message' => 'Cannot modify owner role'], 403);
        }

        $this->validate($request, [
            'name' => 'sometimes|string|unique:roles,name,' . $id,
            'description' => 'nullable|string',
            'permissions' => 'sometimes|array',
            'is_global' => 'sometimes|boolean'
        ]);

        // If role name changes, update users
        if (isset($request->name) && $request->name !== $role->name) {
            User::where('role', $role->name)->update(['role' => $request->name]);
        }

        $role->update($request->all());

        AuditLog::log('update', 'Manajemen User', "Updated role: {$role->name}");

        return response()->json($role);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Prevent deleting system roles
        $protectedRoles = ['super_admin', 'owner', 'admin', 'cashier'];
        if (in_array($role->name, $protectedRoles)) {
            return response()->json(['message' => 'Cannot delete system role'], 403);
        }

        // Check if any users have this role
        $usersWithRole = User::where('role', $role->name)->count();
        if ($usersWithRole > 0) {
            return response()->json([
                'message' => "Cannot delete role. {$usersWithRole} users have this role."
            ], 400);
        }

        $name = $role->name;
        $role->delete();

        AuditLog::log('delete', 'Manajemen User', "Deleted role: {$name}");

        return response()->json(['message' => 'Role deleted successfully']);
    }

    public function getPermissions()
    {
        $permissions = [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'description' => 'View business summary'],
            ['id' => 'pos', 'label' => 'POS', 'description' => 'Access point of sale'],
            ['id' => 'calendar', 'label' => 'Calendar', 'description' => 'View and manage schedules'],
            ['id' => 'bookings', 'label' => 'Bookings', 'description' => 'Manage reservations'],
            ['id' => 'customers', 'label' => 'Customers', 'description' => 'Manage customer data'],
            ['id' => 'services', 'label' => 'Services', 'description' => 'Manage service catalog'],
            ['id' => 'rooms', 'label' => 'Rooms', 'description' => 'Manage rooms'],
            ['id' => 'staff', 'label' => 'Staff', 'description' => 'Manage therapists and staff'],
            ['id' => 'reports', 'label' => 'Reports', 'description' => 'View analytics and reports'],
            ['id' => 'marketing', 'label' => 'Marketing', 'description' => 'Manage promos and campaigns'],
            ['id' => 'settings', 'label' => 'Settings', 'description' => 'System configuration'],
            ['id' => 'users', 'label' => 'Users', 'description' => 'Manage user accounts'],
            ['id' => 'roles', 'label' => 'Roles', 'description' => 'Manage roles and permissions'],
            ['id' => 'audit', 'label' => 'Audit Logs', 'description' => 'View activity logs']
        ];

        return response()->json($permissions);
    }

    public function seedDefaults()
    {
        $defaults = Role::getDefaultRoles();
        $created = [];

        foreach ($defaults as $roleData) {
            $exists = Role::where('name', $roleData['name'])->first();
            if (!$exists) {
                $role = Role::create($roleData);
                $created[] = $role->name;
            }
        }

        return response()->json([
            'message' => 'Default roles seeded',
            'created' => $created
        ]);
    }
}
