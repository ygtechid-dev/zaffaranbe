<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\NotificationSetting;
use Carbon\Carbon;

class WhatsAppService
{
    protected $baseUrl;
    protected $token;
    protected $notificationTemplateService;

    public function __construct(NotificationTemplateService $notificationTemplateService)
    {
        $this->notificationTemplateService = $notificationTemplateService;
        $this->loadConfig();
    }

    /**
     * Load API configuration from database or env fallback
     */
    protected function loadConfig($branchId = null)
    {
        try {
            // 1. Try branch-specific config first
            if ($branchId) {
                $config = NotificationSetting::where('type', 'whatsapp_config')
                    ->where('branch_id', $branchId)
                    ->first();

                if ($config && isset($config->settings['baseUrl']) && isset($config->settings['token'])) {
                    $this->baseUrl = $config->settings['baseUrl'];
                    $this->token = $config->settings['token'];
                    return;
                }
            }

            // 2. Try global setting (branch_id is null)
            $config = NotificationSetting::where('type', 'whatsapp_config')
                ->whereNull('branch_id')
                ->first();

            if ($config && isset($config->settings['baseUrl']) && isset($config->settings['token'])) {
                $this->baseUrl = $config->settings['baseUrl'];
                $this->token = $config->settings['token'];
                return;
            }
        } catch (\Exception $e) {
            // Table might not exist during migration
            Log::warning("WhatsAppService: Failed to load config from DB. Falling back to .env.");
        }

        // Fallback to .env
        $this->baseUrl = env('WHATSAPP_API_BASE_URL', 'https://apinaqu.zafarangroupindonesia.com');
        $this->token = env('WHATSAPP_API_TOKEN');
    }

    /**
     * Clean and format phone number
     */
    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * Send general text message
     */
    public function sendMessage($phone, $message, $branchId = null)
    {
        if (empty($phone))
            return false;
        $phone = $this->formatPhone($phone);

        $this->loadConfig($branchId);

        try {
            $response = Http::withToken($this->token)->post($this->baseUrl . '/api/messages/send-text', [
                'phone' => $phone,
                'message' => $message
            ]);

            Log::info("WhatsApp Send Text Response: " . $response->body());
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp Send Text Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send OTP Message using template
     */
    public function sendOtp($phone, $otp)
    {
        if (empty($phone))
            return false;
        $phone = $this->formatPhone($phone);

        $this->loadConfig();
        try {
            $response = Http::withToken($this->token)->post($this->baseUrl . '/api/messages/otp', [
                'phone' => $phone,
                'otp_code' => (string) $otp,
                'button_param' => (string) $otp
            ]);

            Log::info("WhatsApp OTP Response: " . $response->body());
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp OTP Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Welcome Message using template
     */
    public function sendWelcome($phone, $customerName, $branchName = 'Zafaran Spa')
    {
        if (empty($phone))
            return false;
        $phone = $this->formatPhone($phone);

        $this->loadConfig(); // Default global
        try {
            $response = Http::withToken($this->token)->post($this->baseUrl . '/api/messages/welcome', [
                'phone' => $phone,
                'nama_spa' => $branchName,
                'nama_pelanggan' => $customerName,
                'app_url' => env('CUSTOMER_APP_URL', 'https://zafara-spa-salon.vercel.app')
            ]);

            Log::info("WhatsApp Welcome Response: " . $response->body());
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp Welcome Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Reservation Success Message using template
     */
    public function sendBookingSuccess($phone, $data)
    {
        if (empty($phone))
            return false;
        $phone = $this->formatPhone($phone);

        $branchId = $data['branch_id'] ?? null;
        $this->loadConfig($branchId);
        try {
            $response = Http::withToken($this->token)->post($this->baseUrl . '/api/messages/reservation-success', [
                'phone' => $phone,
                'nama_pelanggan' => $data['customer_name'],
                'nama_spa' => $data['branch_name'] ?? 'Zafaran Spa',
                'tanggal' => $this->formatIndonesianDate($data['date'] ?? null),
                'waktu' => $data['time'],
                'layanan' => $data['service'],
                'lokasi' => $data['location']
            ]);

            Log::info("WhatsApp Booking Success Response: " . $response->body());
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp Booking Success Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Birthday Message using template
     */
    public function sendBirthdayGreeting($phone, $customerName, $discount = '30%', $expiryDate = null)
    {
        if (empty($phone))
            return false;
        $phone = $this->formatPhone($phone);
        $expiryDate = $this->formatIndonesianDate($expiryDate ?: Carbon::now()->addDays(30));

        try {
            $response = Http::withToken($this->token)->post($this->baseUrl . '/api/messages/birthday', [
                'phone' => $phone,
                'nama_pelanggan' => $customerName,
                'nama_spa' => 'Zafaran Spa',
                'persentase' => $discount,
                'expired_date' => $expiryDate,
                'login_param' => 'login'
            ]);

            Log::info("WhatsApp Birthday Response: " . $response->body());
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp Birthday Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Promo/Discount Message using template
     */
    public function sendPromo($phone, $data)
    {
        if (empty($phone))
            return false;
        $phone = $this->formatPhone($phone);

        try {
            $response = Http::withToken($this->token)->post($this->baseUrl . '/api/messages/discount-reservation', [
                'phone' => $phone,
                'nama_pelanggan' => $data['customer_name'],
                'persentase' => $data['discount_rate'] ?? '50',
                'nama_layanan' => $data['service_name']
            ]);

            Log::info("WhatsApp Promo Response: " . $response->body());
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp Promo Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Legacy/Fallback method to use old NotificationTemplateService
     */
    public function sendCustomerNotification($phone, $type, $data, $branchId = null)
    {
        $parsed = $this->notificationTemplateService->parseTemplate($type, $data, $branchId);

        if (!$parsed) {
            Log::info("Notification Template for $type not enabled or found.");
            return false;
        }

        return $this->sendMessage($phone, $parsed['message'], $branchId);
    }

    public function sendStaffBookingNotification($phone, $booking)
    {
        if (empty($phone))
            return false;

        $branchId = $booking->branch_id ?? null;
        $date = $booking->booking_date instanceof \Carbon\Carbon ? $booking->booking_date->format('d M Y') : $booking->booking_date;
        $startTime = substr($booking->start_time, 0, 5);
        $customerName = $booking->user ? $booking->user->name : 'Pelanggan';
        $serviceName = $booking->service ? $booking->service->name : 'Treatment';

        $message = "Halo {$booking->therapist->name},\n\n";
        $message .= "Ada pesanan BARU untuk Anda:\n";
        $message .= "---------------------------\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal: {$date} jam {$startTime} WIB\n";
        $message .= "---------------------------\n\n";
        $message .= "Silakan persiapkan diri Anda. Terima kasih!";

        return $this->sendMessage($phone, $message, $branchId);
    }

    public function sendStaffCancellationNotification($phone, $booking)
    {
        $date = $booking->booking_date instanceof \Carbon\Carbon ? $booking->booking_date->format('d M Y') : $booking->booking_date;
        $startTime = substr($booking->start_time, 0, 5);
        $customerName = $booking->user ? $booking->user->name : 'Customer';
        $serviceName = $booking->service ? $booking->service->name : 'Layanan';

        $message = "Halo {$booking->therapist->name},\n\n";
        $message .= "Pesanan berikut telah DIBATALKAN:\n";
        $message .= "---------------------------\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal Semula: {$date} jam {$startTime}\n";
        $message .= "---------------------------\n\n";
        $message .= "Jadwal Anda kini kosong kembali.";

        return $this->sendMessage($phone, $message);
    }

    public function sendStaffRescheduleNotification($phone, $booking)
    {
        $date = $booking->booking_date instanceof \Carbon\Carbon ? $booking->booking_date->format('d M Y') : $booking->booking_date;
        $startTime = substr($booking->start_time, 0, 5);
        $customerName = $booking->user ? $booking->user->name : 'Customer';
        $serviceName = $booking->service ? $booking->service->name : 'Layanan';

        $message = "Halo {$booking->therapist->name},\n\n";
        $message .= "Pesanan berikut telah DIJADWAL ULANG (Reschedule):\n";
        $message .= "---------------------------\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal Baru: {$date} jam {$startTime}\n";
        $message .= "---------------------------\n\n";
        $message .= "Silakan periksa jadwal terbaru Anda. Terima kasih!";

        return $this->sendMessage($phone, $message);
    }

    public function sendReminder($phone, $booking, $type = 'H-1')
    {
        $templateType = ($type === 'H-1') ? 'reminder_h1' : 'reminder_h2';
        $data = [
            'customer' => $booking->user ?? (object) ['name' => 'Customer'],
            'booking' => $booking,
            'branch' => $booking->branch
        ];

        return $this->sendCustomerNotification($phone, $templateType, $data, $booking->branch_id);
    }

    public function sendReviewRequest($phone, $booking)
    {
        $reviewLink = env('APP_URL') . "/review/" . ($booking->booking_ref ?? $booking->id);

        $data = [
            'customer' => $booking->user ?? (object) ['name' => 'Customer'],
            'booking' => $booking,
            'branch' => $booking->branch,
            'review_link' => $reviewLink
        ];

        return $this->sendCustomerNotification($phone, 'review_request', $data, $booking->branch_id);
    }

    /**
     * Helper to format dates to Indonesian format as shown in documentation examples
     */
    private function formatIndonesianDate($date)
    {
        if (empty($date))
            return '-';

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];

        $day = $carbon->format('d');
        $month = $months[(int) $carbon->format('m')];
        $year = $carbon->format('Y');

        return "$day $month $year";
    }

    /**
     * Send cancellation notification to customer
     */
    public function sendCustomerCancellationNotification($phone, $booking, $reason = null)
    {
        if (empty($phone))
            return false;

        $date = $this->formatIndonesianDate($booking->booking_date);
        $startTime = substr($booking->start_time, 0, 5);
        $serviceName = $booking->service ? $booking->service->name : 'Layanan';

        $message = "Halo " . ($booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan')) . ",\n\n";
        $message .= "Kami mengonfirmasi bahwa booking Anda telah DIBATALKAN:\n";
        $message .= "---------------------------\n";
        $message .= "Referensi: {$booking->booking_ref}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal: {$date} jam {$startTime} WIB\n";
        if ($reason)
            $message .= "Alasan: {$reason}\n";
        $message .= "---------------------------\n\n";
        $message .= "Jika Anda telah melakukan pembayaran, silakan ajukan refund melalui menu Riwayat di aplikasi kami.\n\n";
        $message .= "Terima kasih.";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send refund notification to customer
     */
    public function sendCustomerRefundNotification($phone, $booking, $amount, $status = 'requested')
    {
        if (empty($phone))
            return false;

        $statusLabel = $status === 'requested' ? '*TELAH DIAJUKAN*' : '*TELAH DIPROSES*';
        $amountFmt = "Rp " . number_format($amount);

        $message = "Halo " . ($booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan')) . ",\n\n";
        $message .= "Informasi mengenai refund booking Anda:\n";
        $message .= "---------------------------\n";
        $message .= "Referensi: {$booking->booking_ref}\n";
        $message .= "Status Refund: {$statusLabel}\n";
        $message .= "Jumlah: {$amountFmt}\n";
        $message .= "---------------------------\n\n";

        if ($status === 'requested') {
            $message .= "Pengajuan refund Anda telah kami terima dan akan segera diproses. Terimakasih.";
        } else {
            $message .= "Refund Anda telah berhasil diproses. Silakan cek rekening Anda secara berkala dalam 1-3 hari kerja. Terimakasih.";
        }

        return $this->sendMessage($phone, $message);
    }
}

