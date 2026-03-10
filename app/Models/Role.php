<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'description',
        'permissions',
        'is_global'
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_global' => 'boolean'
    ];

    protected $appends = ['user_count'];

    public function users()
    {
        return User::where('role', $this->name)->get();
    }

    public function getUserCountAttribute()
    {
        return User::where('role', $this->name)->count();
    }

    public function hasPermission($permission)
    {
        if (!$this->permissions)
            return false;
        return in_array($permission, $this->permissions);
    }

    public static function getDefaultRoles()
    {
        return [
            [
                'name' => 'owner',
                'description' => 'Full access to all features',
                'permissions' => ['dashboard', 'pos', 'calendar', 'bookings', 'customers', 'services', 'staff', 'reports', 'marketing', 'settings', 'users', 'roles', 'audit']
            ],
            [
                'name' => 'admin',
                'description' => 'Manage operations and staff',
                'permissions' => ['dashboard', 'pos', 'calendar', 'bookings', 'customers', 'services', 'staff', 'reports', 'marketing']
            ],
            [
                'name' => 'cashier',
                'description' => 'Process transactions and bookings',
                'permissions' => ['dashboard', 'pos', 'calendar', 'bookings', 'customers']
            ],
            [
                'name' => 'therapist',
                'description' => 'View schedules and appointments',
                'permissions' => ['dashboard', 'calendar']
            ],
            [
                'name' => 'marketing',
                'description' => 'Manage promotions and content',
                'permissions' => ['dashboard', 'marketing', 'customers']
            ]
        ];
    }
}
