<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Register a new user (Customer)
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'city_id' => 'nullable', // Keep for backward compatibility if needed, though we use regency_id now
            'province_id' => 'nullable|exists:provinces,id',
            'regency_id' => 'nullable|exists:regencies,id',
            'district_id' => 'nullable|exists:districts,id',
            'village_id' => 'nullable|exists:villages,id',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'birth_date' => $request->birth_date,
            'address' => $request->address,
            'city_id' => $request->city_id,
            'province_id' => $request->province_id,
            'regency_id' => $request->regency_id,
            'district_id' => $request->district_id,
            'village_id' => $request->village_id,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'registration_source' => 'app',
            'has_app_account' => true,
        ]);

        // Generate OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update([
            'otp' => Hash::make($otp),
            'otp_expires_at' => \Carbon\Carbon::now()->addMinutes(10),
        ]);

        // Send OTP via WhatsApp
        $this->whatsappService->sendOtp($user->phone, $otp);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User registered successfully. Please verify your phone number.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (!$user->otp || !$user->otp_expires_at) {
            return response()->json(['error' => 'No OTP found. Please request a new one.'], 400);
        }

        if ($user->otp_expires_at < \Carbon\Carbon::now()) {
            return response()->json(['error' => 'OTP has expired'], 400);
        }

        if (!Hash::check($request->otp, $user->otp)) {
            return response()->json(['error' => 'Invalid OTP'], 400);
        }

        $user->update([
            'is_verified' => true,
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        // Send Welcome Message via WhatsApp
        $this->whatsappService->sendWelcome($user->phone, $user->name);

        return response()->json([
            'message' => 'Phone number verified successfully',
            'user' => $user,
        ]);
    }

    /**
     * Login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // Can be email or phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $credentials = [
            $loginField => $request->login,
            'password' => $request->password,
        ];

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = Auth::guard('api')->user();

        if (!$user->is_active) {
            return response()->json(['error' => 'Account is deactivated'], 403);
        }

        AuditLog::log('login', 'Auth', "User {$user->email} logged in from " . request()->ip());

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me()
    {
        return response()->json(Auth::guard('api')->user());
    }

    /**
     * Logout
     */
    public function logout()
    {
        $user = Auth::guard('api')->user();
        if ($user) {
            AuditLog::log('logout', 'Auth', "User {$user->email} logged out");
        }
        Auth::guard('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        return response()->json([
            'token' => Auth::guard('api')->refresh(),
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update([
            'otp' => Hash::make($otp),
            'otp_expires_at' => \Carbon\Carbon::now()->addMinutes(10),
        ]);

        // Send via WhatsApp
        $this->whatsappService->sendOtp($user->phone, $otp);

        return response()->json([
            'message' => 'OTP has been resent',
        ]);
    }

    /**
     * Request password reset
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|required_without:phone',
            'phone' => 'nullable|string|required_without:email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->email
            ? User::where('email', $request->email)->first()
            : User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Generate OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store in password_resets table
        \App\Models\PasswordReset::create([
            'email' => $user->email,
            'phone' => $user->phone,
            'token' => $otp,
            'expires_at' => \Carbon\Carbon::now()->addMinutes(15),
        ]);

        // Send OTP via WhatsApp if phone provided
        if ($request->phone) {
            $this->whatsappService->sendOtp($user->phone, $otp);
        }
        // TODO: Send via email if email provided

        return response()->json([
            'message' => 'Password reset code has been sent',
        ]);
    }

    /**
     * Verify reset OTP
     */
    public function verifyResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|required_without:phone',
            'phone' => 'nullable|string|required_without:email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reset = \App\Models\PasswordReset::where(function ($query) use ($request) {
            if ($request->email) {
                $query->where('email', $request->email);
            } else {
                $query->where('phone', $request->phone);
            }
        })
            ->where('token', $request->otp)
            ->where('used', false)
            ->where('expires_at', '>', \Carbon\Carbon::now())
            ->first();

        if (!$reset) {
            return response()->json(['error' => 'Invalid or expired OTP'], 400);
        }

        // Generate a unique reset token
        $resetToken = bin2hex(random_bytes(32));
        $reset->update(['token' => $resetToken]);

        return response()->json([
            'message' => 'OTP verified',
            'reset_token' => $resetToken,
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|required_without:phone',
            'phone' => 'nullable|string|required_without:email',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reset = \App\Models\PasswordReset::where(function ($query) use ($request) {
            if ($request->email) {
                $query->where('email', $request->email);
            } else {
                $query->where('phone', $request->phone);
            }
        })
            ->where('token', $request->reset_token)
            ->where('used', false)
            ->first();

        if (!$reset) {
            return response()->json(['error' => 'Invalid reset token'], 400);
        }

        // Find user
        $user = $request->email
            ? User::where('email', $request->email)->first()
            : User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Update password
        $user->update(['password' => Hash::make($request->password)]);

        // Mark reset token as used
        $reset->update(['used' => true]);

        return response()->json(['message' => 'Password has been reset successfully']);
    }
}
