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
    protected $cachedNotifSettings = null;

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
            if ($branchId) {
                $config = NotificationSetting::where('type', 'whatsapp_config')
                    ->where('branch_id', $branchId)
                    ->first();

                if ($config && isset($config->settings['baseUrl']) && isset($config->settings['token'])) {
                    $this->baseUrl = $config->settings['baseUrl'];
                    $this->token   = $config->settings['token'];
                    return;
                }
            }

            $config = NotificationSetting::where('type', 'whatsapp_config')
                ->whereNull('branch_id')
                ->first();

            if ($config && isset($config->settings['baseUrl']) && isset($config->settings['token'])) {
                $this->baseUrl = $config->settings['baseUrl'];
                $this->token   = $config->settings['token'];
                return;
            }
        } catch (\Exception $e) {
            Log::warning("WhatsAppService: Failed to load config from DB. Falling back to .env.");
        }

        $this->baseUrl = env('WHATSAPP_API_BASE_URL', 'https://apinaqu.zafarangroupindonesia.com');
        $this->token   = env('WHATSAPP_API_TOKEN');
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
     * Ambil semua notif settings dari Node API (cached per request)
     */
    protected function getNotifSettings(): array
    {
        if ($this->cachedNotifSettings !== null) {
            return $this->cachedNotifSettings;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(5)
                ->get($this->baseUrl . '/api/notif-settings');

            if ($response->successful()) {
                $this->cachedNotifSettings = $response->json('settings') ?? [];
                return $this->cachedNotifSettings;
            }
        } catch (\Exception $e) {
            Log::warning("WhatsAppService: Gagal ambil notif settings: " . $e->getMessage());
        }

        $this->cachedNotifSettings = [];
        return [];
    }

    /**
     * Ambil setting untuk 1 event key
     */
    protected function getEventTemplateSetting(string $eventKey): ?array
    {
        $settings = $this->getNotifSettings();
        $setting  = $settings[$eventKey] ?? null;

        if ($setting && !empty($setting['template_name']) && ($setting['enabled'] ?? true)) {
            return $setting;
        }

        return null;
    }

    /**
     * Resolve semua variabel sistem dari booking
     */
    protected function resolveSystemVars($booking, array $extra = []): array
    {
        $vars = [
            'therapist_name' => $booking->therapist->name ?? '-',
            'customer_name'  => $booking->user
                ? $booking->user->name
                : ($booking->guest_name ?? 'Pelanggan'),
            'service_name'   => $booking->service ? $booking->service->name : 'Treatment',
            'booking_date'   => $this->formatIndonesianDate($booking->booking_date),
            'booking_time'   => substr($booking->start_time, 0, 5) . ' WIB',
            'branch_name'    => $booking->branch ? $booking->branch->name : '-',
            'booking_ref'    => $booking->booking_ref ?? '-',
            'refund_amount'  => 'Rp ' . number_format($booking->refund_amount ?? 0, 0, ',', '.'),
            'review_link'    => env('APP_URL') . '/review/' . ($booking->booking_ref ?? $booking->id),
            'payment_link'   => env('APP_URL') . '/payment/' . ($booking->booking_ref ?? $booking->id),
        ];

        return array_merge($vars, $extra);
    }

    /**
     * Kirim template dengan mapping variabel dari settings
     */
    protected function sendMappedTemplate(string $phone, $booking, array $setting, array $extra = []): bool
    {
        $phone      = $this->formatPhone($phone);
        $sysVars    = $this->resolveSystemVars($booking, $extra);
        $mapping    = $setting['mapping'] ?? [];
        $bodyParams = array_map(fn($varKey) => $sysVars[$varKey] ?? '-', $mapping);

        try {
            $response = Http::withToken($this->token)
                ->post($this->baseUrl . '/api/messages/send-template', [
                    'phone'         => $phone,
                    'template_name' => $setting['template_name'],
                    'body_params'   => $bodyParams,
                ]);

            Log::info("WA Template [{$setting['template_name']}] → {$phone}: " . $response->body());
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WA Template Failed [{$setting['template_name']}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send general text message
     */
    public function sendMessage($phone, $message, $branchId = null)
    {
        if (empty($phone)) return false;
        $phone = $this->formatPhone($phone);
        $this->loadConfig($branchId);

        try {
            $response = Http::withToken($this->token)
                ->post($this->baseUrl . '/api/messages/send-text', [
                    'phone'   => $phone,
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
        if (empty($phone)) return false;
        $phone = $this->formatPhone($phone);
        $this->loadConfig();

        try {
            $response = Http::withToken($this->token)
                ->post($this->baseUrl . '/api/messages/otp', [
                    'phone'        => $phone,
                    'otp_code'     => (string) $otp,
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
        if (empty($phone)) return false;
        $phone = $this->formatPhone($phone);
        $this->loadConfig();

        try {
            $response = Http::withToken($this->token)
                ->post($this->baseUrl . '/api/messages/welcome', [
                    'phone'          => $phone,
                    'nama_spa'       => $branchName,
                    'nama_pelanggan' => $customerName,
                    'app_url'        => env('CUSTOMER_APP_URL', 'https://zafara-spa-salon.vercel.app')
                ]);

            Log::info("WhatsApp Welcome Response: " . $response->body());
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("WhatsApp Welcome Failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Reservation Success / Customer Confirmation
     */
    public function sendBookingSuccess($phone, $data)
    {
        if (empty($phone)) return false;
        $phone    = $this->formatPhone($phone);
        $branchId = $data['branch_id'] ?? null;
        $this->loadConfig($branchId);

        // Cek template setting customer_confirmation
        $setting = $this->getEventTemplateSetting('customer_confirmation');
        if ($setting) {
            $sysVars = [
                'therapist_name' => $data['therapist_name'] ?? '-',
                'customer_name'  => $data['customer_name'] ?? 'Pelanggan',
                'service_name'   => $data['service'] ?? '-',
                'booking_date'   => $this->formatIndonesianDate($data['date'] ?? null),
                'booking_time'   => $data['time'] ?? '-',
                'branch_name'    => $data['branch_name'] ?? '-',
                'booking_ref'    => $data['booking_ref'] ?? '-',
                'payment_link'   => $data['payment_link'] ?? env('APP_URL', '-'),
                'refund_amount'  => '-',
                'review_link'    => '-',
            ];
            $mapping    = $setting['mapping'] ?? [];
            $bodyParams = array_map(fn($k) => $sysVars[$k] ?? '-', $mapping);

            try {
                $res = Http::withToken($this->token)
                    ->post($this->baseUrl . '/api/messages/send-template', [
                        'phone'         => $phone,
                        'template_name' => $setting['template_name'],
                        'body_params'   => $bodyParams,
                    ]);
                Log::info("WA Customer Confirmation Template: " . $res->body());
                return $res->successful();
            } catch (\Exception $e) {
                Log::warning("WA Customer Confirmation Template Failed, fallback: " . $e->getMessage());
            }
        }

        // Fallback original endpoint
        try {
            $response = Http::withToken($this->token)
                ->post($this->baseUrl . '/api/messages/reservation-success', [
                    'phone'          => $phone,
                    'nama_pelanggan' => $data['customer_name'],
                    'nama_spa'       => $data['branch_name'] ?? 'Zafaran Spa',
                    'tanggal'        => $this->formatIndonesianDate($data['date'] ?? null),
                    'waktu'          => $data['time'],
                    'layanan'        => $data['service'],
                    'lokasi'         => $data['location']
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
        if (empty($phone)) return false;
        $phone      = $this->formatPhone($phone);
        $expiryDate = $this->formatIndonesianDate($expiryDate ?: Carbon::now()->addDays(30));

        try {
            $response = Http::withToken($this->token)
                ->post($this->baseUrl . '/api/messages/birthday', [
                    'phone'          => $phone,
                    'nama_pelanggan' => $customerName,
                    'nama_spa'       => 'Zafaran Spa',
                    'persentase'     => $discount,
                    'expired_date'   => $expiryDate,
                    'login_param'    => 'login'
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
        if (empty($phone)) return false;
        $phone = $this->formatPhone($phone);

        try {
            $response = Http::withToken($this->token)
                ->post($this->baseUrl . '/api/messages/discount-reservation', [
                    'phone'          => $phone,
                    'nama_pelanggan' => $data['customer_name'],
                    'persentase'     => $data['discount_rate'] ?? '50',
                    'nama_layanan'   => $data['service_name']
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

    /**
     * Staff: Booking Baru
     */
    public function sendStaffBookingNotification($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId = $booking->branch_id ?? null;
        $this->loadConfig($branchId);

        $setting = $this->getEventTemplateSetting('staff_booking_new');
        if ($setting) return $this->sendMappedTemplate($phone, $booking, $setting);

        // Fallback teks default
        $date         = $this->formatIndonesianDate($booking->booking_date);
        $startTime    = substr($booking->start_time, 0, 5);
        $customerName = $booking->user ? $booking->user->name : 'Pelanggan';
        $serviceName  = $booking->service ? $booking->service->name : 'Treatment';

        $message  = "Halo {$booking->therapist->name},\n\n";
        $message .= "Ada pesanan BARU untuk Anda:\n";
        $message .= "---------------------------\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal: {$date} jam {$startTime} WIB\n";
        $message .= "---------------------------\n\n";
        $message .= "Silakan persiapkan diri Anda. Terima kasih!";

        return $this->sendMessage($phone, $message, $branchId);
    }

    /**
     * Staff: Cancellation
     */
    public function sendStaffCancellationNotification($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId = $booking->branch_id ?? null;
        $this->loadConfig($branchId);

        $setting = $this->getEventTemplateSetting('staff_cancellation');
        if ($setting) return $this->sendMappedTemplate($phone, $booking, $setting);

        // Fallback
        $date         = $this->formatIndonesianDate($booking->booking_date);
        $startTime    = substr($booking->start_time, 0, 5);
        $customerName = $booking->user ? $booking->user->name : 'Customer';
        $serviceName  = $booking->service ? $booking->service->name : 'Layanan';

        $message  = "Halo {$booking->therapist->name},\n\n";
        $message .= "Pesanan berikut telah DIBATALKAN:\n";
        $message .= "---------------------------\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal Semula: {$date} jam {$startTime}\n";
        $message .= "---------------------------\n\n";
        $message .= "Jadwal Anda kini kosong kembali.";

        return $this->sendMessage($phone, $message, $branchId);
    }

    /**
     * Staff: Reschedule
     */
    public function sendStaffRescheduleNotification($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId = $booking->branch_id ?? null;
        $this->loadConfig($branchId);

        $setting = $this->getEventTemplateSetting('staff_reschedule');
        if ($setting) return $this->sendMappedTemplate($phone, $booking, $setting);

        // Fallback
        $date         = $this->formatIndonesianDate($booking->booking_date);
        $startTime    = substr($booking->start_time, 0, 5);
        $customerName = $booking->user ? $booking->user->name : 'Customer';
        $serviceName  = $booking->service ? $booking->service->name : 'Layanan';

        $message  = "Halo {$booking->therapist->name},\n\n";
        $message .= "Pesanan berikut telah DIJADWAL ULANG (Reschedule):\n";
        $message .= "---------------------------\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal Baru: {$date} jam {$startTime}\n";
        $message .= "---------------------------\n\n";
        $message .= "Silakan periksa jadwal terbaru Anda. Terima kasih!";

        return $this->sendMessage($phone, $message, $branchId);
    }

    /**
     * Customer: Reminder H-1 & 2 Jam
     */
    public function sendReminder($phone, $booking, $type = 'H-1')
    {
        if (empty($phone)) return false;
        $branchId = $booking->branch_id ?? null;
        $this->loadConfig($branchId);

        $eventKey = ($type === 'H-1') ? 'customer_reminder_h1' : 'customer_reminder_2h';
        $setting  = $this->getEventTemplateSetting($eventKey);
        if ($setting) return $this->sendMappedTemplate($phone, $booking, $setting);

        // Fallback ke NotificationTemplateService
        $templateType = ($type === 'H-1') ? 'reminder_h1' : 'reminder_h2';
        $data = [
            'customer' => $booking->user ?? (object) ['name' => 'Customer'],
            'booking'  => $booking,
            'branch'   => $booking->branch
        ];
        return $this->sendCustomerNotification($phone, $templateType, $data, $branchId);
    }

    /**
     * Customer: Review Request
     */
    public function sendReviewRequest($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId = $booking->branch_id ?? null;
        $this->loadConfig($branchId);

        $setting = $this->getEventTemplateSetting('customer_review');
        if ($setting) {
            return $this->sendMappedTemplate($phone, $booking, $setting, [
                'review_link' => env('APP_URL') . '/review/' . ($booking->booking_ref ?? $booking->id)
            ]);
        }

        // Fallback
        $reviewLink = env('APP_URL') . "/review/" . ($booking->booking_ref ?? $booking->id);
        $data = [
            'customer'    => $booking->user ?? (object) ['name' => 'Customer'],
            'booking'     => $booking,
            'branch'      => $booking->branch,
            'review_link' => $reviewLink
        ];
        return $this->sendCustomerNotification($phone, 'review_request', $data, $branchId);
    }

    /**
     * Customer: Cancellation
     */
    public function sendCustomerCancellationNotification($phone, $booking, $reason = null)
    {
        if (empty($phone)) return false;
        $branchId = $booking->branch_id ?? null;
        $this->loadConfig($branchId);

        $setting = $this->getEventTemplateSetting('customer_cancellation');
        if ($setting) {
            return $this->sendMappedTemplate($phone, $booking, $setting, [
                'cancel_reason' => $reason ?? '-'
            ]);
        }

        // Fallback
        $date         = $this->formatIndonesianDate($booking->booking_date);
        $startTime    = substr($booking->start_time, 0, 5);
        $serviceName  = $booking->service ? $booking->service->name : 'Layanan';
        $customerName = $booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan');

        $message  = "Halo {$customerName},\n\n";
        $message .= "Kami mengonfirmasi bahwa booking Anda telah DIBATALKAN:\n";
        $message .= "---------------------------\n";
        $message .= "Referensi: {$booking->booking_ref}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal: {$date} jam {$startTime} WIB\n";
        if ($reason) $message .= "Alasan: {$reason}\n";
        $message .= "---------------------------\n\n";
        $message .= "Jika Anda telah melakukan pembayaran, silakan ajukan refund melalui menu Riwayat di aplikasi kami.\n\nTerima kasih.";

        return $this->sendMessage($phone, $message, $branchId);
    }

    /**
     * Customer: Reschedule
     */
    public function sendCustomerRescheduleNotification($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId = $booking->branch_id ?? null;
        $this->loadConfig($branchId);

        $setting = $this->getEventTemplateSetting('customer_reschedule');
        if ($setting) return $this->sendMappedTemplate($phone, $booking, $setting);

        // Fallback
        $date         = $this->formatIndonesianDate($booking->booking_date);
        $startTime    = substr($booking->start_time, 0, 5);
        $serviceName  = $booking->service ? $booking->service->name : 'Layanan';
        $customerName = $booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan');

        $message  = "Halo {$customerName},\n\n";
        $message .= "Jadwal booking Anda telah DIUBAH:\n";
        $message .= "---------------------------\n";
        $message .= "Referensi: {$booking->booking_ref}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal Baru: {$date} jam {$startTime} WIB\n";
        $message .= "---------------------------\n\nTerima kasih.";

        return $this->sendMessage($phone, $message, $branchId);
    }

    /**
     * Customer: Refund
     */
    public function sendCustomerRefundNotification($phone, $booking, $amount, $status = 'requested')
    {
        if (empty($phone)) return false;
        $branchId = $booking->branch_id ?? null;
        $this->loadConfig($branchId);

        $setting = $this->getEventTemplateSetting('customer_refund');
        if ($setting) {
            return $this->sendMappedTemplate($phone, $booking, $setting, [
                'refund_amount' => 'Rp ' . number_format($amount, 0, ',', '.')
            ]);
        }

        // Fallback
        $statusLabel  = $status === 'requested' ? '*TELAH DIAJUKAN*' : '*TELAH DIPROSES*';
        $amountFmt    = 'Rp ' . number_format($amount, 0, ',', '.');
        $customerName = $booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan');

        $message  = "Halo {$customerName},\n\n";
        $message .= "Informasi mengenai refund booking Anda:\n";
        $message .= "---------------------------\n";
        $message .= "Referensi: {$booking->booking_ref}\n";
        $message .= "Status Refund: {$statusLabel}\n";
        $message .= "Jumlah: {$amountFmt}\n";
        $message .= "---------------------------\n\n";
        $message .= $status === 'requested'
            ? "Pengajuan refund Anda telah kami terima dan akan segera diproses. Terimakasih."
            : "Refund Anda telah berhasil diproses. Silakan cek rekening Anda dalam 1-3 hari kerja. Terimakasih.";

        return $this->sendMessage($phone, $message, $branchId);
    }

    /**
     * Helper: Format tanggal ke Bahasa Indonesia
     */
    private function formatIndonesianDate($date)
    {
        if (empty($date)) return '-';

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        $months = [
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember'
        ];

        return $carbon->format('d') . ' ' . $months[(int) $carbon->format('m')] . ' ' . $carbon->format('Y');
    }
}