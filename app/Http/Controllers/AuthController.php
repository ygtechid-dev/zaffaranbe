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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER: Kirim OTP via Email (Resend lewat WA wrapper)
    // ─────────────────────────────────────────────────────────────────────────

    private function sendOtpEmail(string $email, string $otp, string $name = 'Pelanggan'): bool
    {
        try {
            $wrapperUrl = rtrim(env('WHATSAPP_WRAPPER_URL', 'https://apinaqu.zafarangroupindonesia.com'), '/');

            Http::timeout(10)->post($wrapperUrl . '/smart-send', [
                'event'        => 'auth_forgot_password_otp',
                'phone_number' => '0000000000',
                'phone_name'   => $name,
                'variables'    => [
                    'customer_name' => $name,
                    'otp_code'      => $otp,
                ],
                'email'   => $email,
                'skip_wa' => true,
            ]);

            Log::info("OTP Email sent to {$email}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send OTP email to {$email}: " . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REGISTER
    // ─────────────────────────────────────────────────────────────────────────

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email|max:255|unique:users',
            'phone'       => 'required|string|max:20|unique:users',
            'birth_date'  => 'nullable|date',
            'address'     => 'nullable|string',
            'city_id'     => 'nullable',
            'province_id' => 'nullable|exists:provinces,id',
            'regency_id'  => 'nullable|exists:regencies,id',
            'district_id' => 'nullable|exists:districts,id',
            'village_id'  => 'nullable|exists:villages,id',
            'branch_id'   => 'nullable|exists:branches,id',
            'password'    => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'                => $request->name,
            'email'               => $request->email,
            'phone'               => $request->phone,
            'birth_date'          => $request->birth_date,
            'address'             => $request->address,
            'city_id'             => $request->city_id,
            'province_id'         => $request->province_id,
            'regency_id'          => $request->regency_id,
            'district_id'         => $request->district_id,
            'village_id'          => $request->village_id,
            'branch_id'           => $request->branch_id,
            'password'            => Hash::make($request->password),
            'role'                => 'customer',
            'registration_source' => 'app',
            'has_app_account'     => true,
        ]);

        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->update([
            'otp'            => Hash::make($otp),
            'otp_expires_at' => \Carbon\Carbon::now()->addMinutes(10),
        ]);

        $this->whatsappService->sendOtp($user->phone, $otp);

        // Kirim OTP ke email juga
        if ($user->email) {
            $this->sendOtpEmail($user->email, $otp, $user->name);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User registered successfully. Please verify your phone number.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VERIFY OTP (register)
    // ─────────────────────────────────────────────────────────────────────────

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp'   => 'required|string|size:6',
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
            'is_verified'    => true,
            'otp'            => null,
            'otp_expires_at' => null,
        ]);

        $this->whatsappService->sendWelcome($user->phone, $user->name);

        return response()->json([
            'message' => 'Phone number verified successfully',
            'user'    => $user,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $loginField  = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $credentials = [$loginField => $request->login, 'password' => $request->password];

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $user = Auth::guard('api')->user();

        if (!$user->is_active) {
            return response()->json(['error' => 'Account is deactivated'], 403);
        }

        AuditLog::log('login', 'Auth', "User {$user->email} logged in from " . request()->ip());

        return response()->json([
            'message'    => 'Login successful',
            'user'       => $user,
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ME / LOGOUT / REFRESH
    // ─────────────────────────────────────────────────────────────────────────

    public function me()
    {
        return response()->json(Auth::guard('api')->user());
    }

    public function logout()
    {
        $user = Auth::guard('api')->user();
        if ($user) {
            AuditLog::log('logout', 'Auth', "User {$user->email} logged out");
        }
        Auth::guard('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return response()->json([
            'token'      => Auth::guard('api')->refresh(),
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESEND OTP (register)
    // ─────────────────────────────────────────────────────────────────────────

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
            'otp'            => Hash::make($otp),
            'otp_expires_at' => \Carbon\Carbon::now()->addMinutes(10),
        ]);

        $this->whatsappService->sendOtp($user->phone, $otp);

        // Kirim OTP ke email juga
        if ($user->email) {
            $this->sendOtpEmail($user->email, $otp, $user->name);
        }

        return response()->json(['message' => 'OTP has been resent to WhatsApp and email']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORGOT PASSWORD — kirim OTP ke WA + Email sekaligus
    // ─────────────────────────────────────────────────────────────────────────

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|required_without:phone',
            'phone' => 'nullable|string|required_without:email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Cari user berdasarkan email atau phone yang diinput
        $user = $request->email
            ? User::where('email', $request->email)->first()
            : User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Generate OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        \App\Models\PasswordReset::create([
            'email'      => $user->email,
            'phone'      => $user->phone,
            'token'      => $otp,
            'expires_at' => \Carbon\Carbon::now()->addMinutes(15),
        ]);

        $sent = [];

        // Kirim via WhatsApp — selalu kirim kalau user punya phone
        if ($user->phone) {
            $this->whatsappService->sendOtp($user->phone, $otp);
            $sent[] = 'WhatsApp';
            Log::info("Forgot password OTP sent via WA to {$user->phone}");
        }

        // Kirim via Email — selalu kirim kalau user punya email
        if ($user->email) {
            $this->sendOtpEmail($user->email, $otp, $user->name);
            $sent[] = 'email';
            Log::info("Forgot password OTP sent via Email to {$user->email}");
        }

        return response()->json([
            'message' => 'Kode OTP telah dikirim ke ' . implode(' dan ', $sent) . ' Anda',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VERIFY RESET OTP
    // ─────────────────────────────────────────────────────────────────────────

    public function verifyResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|required_without:phone',
            'phone' => 'nullable|string|required_without:email',
            'otp'   => 'required|string|size:6',
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
            return response()->json(['error' => 'Kode OTP tidak valid atau sudah kadaluarsa'], 400);
        }

        $resetToken = bin2hex(random_bytes(32));
        $reset->update(['token' => $resetToken]);

        return response()->json([
            'message'     => 'OTP verified',
            'reset_token' => $resetToken,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESET PASSWORD
    // ─────────────────────────────────────────────────────────────────────────

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'       => 'nullable|email|required_without:phone',
            'phone'       => 'nullable|string|required_without:email',
            'reset_token' => 'required|string',
            'password'    => 'required|string|min:8|confirmed',
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
            return response()->json(['error' => 'Token reset tidak valid'], 400);
        }

        $user = $request->email
            ? User::where('email', $request->email)->first()
            : User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $reset->update(['used' => true]);

        return response()->json(['message' => 'Kata sandi berhasil direset']);
    }
}