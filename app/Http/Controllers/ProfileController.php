<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Status booking yang dianggap masih berjalan. Akun tidak boleh dihapus
     * selama masih ada salah satunya, supaya tidak ada jadwal menggantung
     * tanpa pemilik.
     */
    private const ACTIVE_BOOKING_STATUSES = ['pending_payment', 'confirmed', 'in_progress'];

    public function show()
    {
        $user = auth()->user();

        // Calculate total active points
        $totalPoints = $user->loyaltyPoints()
            ->where(function ($q) {
                $q->where('expires_at', '>', date('Y-m-d'))
                    ->orWhereNull('expires_at');
            })
            ->sum('remaining_points');

        $userData = $user->toArray();
        $userData['total_points'] = (int) $totalPoints;

        return response()->json($userData);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|required|string|unique:users,phone,' . $user->id,
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($request->only([
            'name',
            'email',
            'phone',
            'birth_date',
            'address',
            'city_id',
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'error' => 'Current password is incorrect'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Hapus akun permanen — App Store guideline 5.1.1(v).
     *
     * CATATAN PENTING soal metode:
     * `bookings.user_id` memakai onDelete('cascade'), begitu juga transactions,
     * feedbacks, dan loyalty_points. Menghapus baris user akan ikut menghapus
     * seluruh riwayat transaksi pelanggan tersebut — riwayat yang dibutuhkan
     * untuk pembukuan dan laporan omzet.
     *
     * Jadi yang dilakukan di sini adalah ANONIMISASI, bukan DELETE baris:
     * seluruh data pribadi dimusnahkan, kredensial diacak sehingga akun tidak
     * bisa dimasuki lagi, dan email/telepon dibebaskan supaya orang yang sama
     * bisa mendaftar ulang sebagai akun baru. Baris booking tetap ada tapi
     * tidak lagi menunjuk ke identitas siapa pun.
     *
     * Ini berbeda dengan sekadar menonaktifkan akun (`is_active = false`) yang
     * ditolak Apple, karena datanya benar-benar hilang dan tidak dapat
     * dipulihkan.
     */
    public function destroy()
    {
        $user = auth()->user();

        // Endpoint ini hanya untuk akun pelanggan aplikasi. Tabel users dipakai
        // bersama staf/kasir/admin, dan akun mereka tidak boleh bisa dihapus
        // sendiri lewat aplikasi pelanggan.
        if ($user->role !== 'customer') {
            return response()->json([
                'message' => 'Penghapusan akun hanya tersedia untuk akun pelanggan.',
            ], 403);
        }

        $activeBookings = Booking::where('user_id', $user->id)
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->count();

        if ($activeBookings > 0) {
            return response()->json([
                'message' => "Masih ada {$activeBookings} reservasi yang berjalan. "
                    . 'Selesaikan atau batalkan dulu sebelum menghapus akun.',
            ], 409);
        }

        $userId = $user->id;

        try {
            DB::transaction(function () use ($user, $userId) {
                // Notifikasi berisi data pribadi dan tidak punya nilai pembukuan.
                DB::table('notifications')->where('user_id', $userId)->delete();

                // Penanda unik supaya email/telepon lama terbebas dan bisa
                // dipakai mendaftar ulang, tanpa melanggar constraint unique.
                $tombstone = $userId . '_' . Str::lower(Str::random(8));

                $user->forceFill([
                    'name'                => 'Akun Dihapus',
                    'email'               => "deleted_{$tombstone}@deleted.invalid",
                    'phone'               => "deleted_{$tombstone}",
                    'password'            => Hash::make(Str::random(64)),
                    'gender'              => null,
                    'birth_date'          => null,
                    'address'             => null,
                    'notes'               => null,
                    'city_id'             => null,
                    'province_id'         => null,
                    'regency_id'          => null,
                    'district_id'         => null,
                    'village_id'          => null,
                    'otp'                 => null,
                    'otp_expires_at'      => null,
                    'is_active'           => false,
                    'is_verified'         => false,
                    'has_app_account'     => false,
                    'membership_status'   => 'deleted',
                ])->save();
            });
        } catch (\Throwable $e) {
            Log::error('Account deletion failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal menghapus akun. Silakan coba lagi atau hubungi kami.',
            ], 500);
        }

        // Hanya id yang dicatat. Menyimpan email lama di audit log akan
        // menyisakan data pribadi yang justru baru saja diminta untuk dihapus.
        AuditLog::log(
            'delete_account',
            'Profile',
            "Customer account #{$userId} deleted via app"
        );

        // Batalkan JWT yang sedang dipakai supaya token lama tidak bisa dipakai
        // sampai masa berlakunya habis.
        try {
            Auth::guard('api')->logout();
        } catch (\Throwable $e) {
            Log::warning('JWT invalidation after account deletion failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Akun Anda telah dihapus permanen.',
        ], 200);
    }
}
