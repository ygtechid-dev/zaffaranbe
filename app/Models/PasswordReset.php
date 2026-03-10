<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $fillable = [
        'email',
        'phone',
        'token',
        'expires_at',
        'used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    public function isValid()
    {
        return !$this->used && $this->expires_at->isFuture();
    }

    public static function generateToken($emailOrPhone, $isPhone = false)
    {
        // Invalidate old tokens
        $query = self::where($isPhone ? 'phone' : 'email', $emailOrPhone);
        $query->update(['used' => true]);

        // Create new token
        $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return self::create([
            'email' => $isPhone ? null : $emailOrPhone,
            'phone' => $isPhone ? $emailOrPhone : null,
            'token' => $token,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public static function verifyToken($emailOrPhone, $token, $isPhone = false)
    {
        $reset = self::where($isPhone ? 'phone' : 'email', $emailOrPhone)
            ->where('token', $token)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        return $reset;
    }
}
