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
    protected $wrapperUrl;
    protected $defaultInboxId;
    protected $notificationTemplateService;
    protected $cachedNotifSettings = null;

    public function __construct(NotificationTemplateService $notificationTemplateService)
    {
        $this->notificationTemplateService = $notificationTemplateService;

        // Wrapper Express di apicekat.zafaranspasolo.com
        $this->wrapperUrl     = rtrim(env('CEKAT_WRAPPER_URL', 'https://apicekat.zafaranspasolo.com'), '/');
        $this->defaultInboxId = env('CEKAT_INBOX_ID', '');

        $this->loadConfig();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONFIG
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load config WA lama (untuk fallback sendMessage / OTP / Welcome / dll)
     */
    protected function loadConfig($branchId = null)
    {
        try {
            if ($branchId) {
                $config = NotificationSetting::where('type', 'whatsapp_config')
                    ->where('branch_id', $branchId)
                    ->first();

                if ($config && isset($config->settings['baseUrl'], $config->settings['token'])) {
                    $this->baseUrl = $config->settings['baseUrl'];
                    $this->token   = $config->settings['token'];
                    return;
                }
            }

            $config = NotificationSetting::where('type', 'whatsapp_config')
                ->whereNull('branch_id')
                ->first();

            if ($config && isset($config->settings['baseUrl'], $config->settings['token'])) {
                $this->baseUrl = $config->settings['baseUrl'];
                $this->token   = $config->settings['token'];
                return;
            }
        } catch (\Exception $e) {
            Log::warning("WhatsAppService: Gagal load config dari DB, fallback ke .env");
        }

        $this->baseUrl = env('WHATSAPP_API_BASE_URL', 'https://apinaqu.zafarangroupindonesia.com');
        $this->token   = env('WHATSAPP_API_TOKEN');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

   private function formatPhone($phone)
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = '62' . substr($phone, 1);
    }
    if (!str_starts_with($phone, '62')) {
        $phone = '62' . $phone; // ← kalau nomor "62xxx" sudah benar, ini skip
    }
    return $phone;
}

    private function formatIndonesianDate($date)
    {
        if (empty($date)) return '-';
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $months = [
            1 => 'Januari',   2 => 'Februari', 3 => 'Maret',
            4 => 'April',     5 => 'Mei',       6 => 'Juni',
            7 => 'Juli',      8 => 'Agustus',   9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $carbon->format('d') . ' ' . $months[(int)$carbon->format('m')] . ' ' . $carbon->format('Y');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NOTIF SETTINGS — ambil dari wrapper /notif-settings
    // ─────────────────────────────────────────────────────────────────────────

    protected function getNotifSettings(): array
    {
        if ($this->cachedNotifSettings !== null) {
            return $this->cachedNotifSettings;
        }

        try {
            $response = Http::timeout(5)->get($this->wrapperUrl . '/notif-settings');

            if ($response->successful()) {
                $this->cachedNotifSettings = $response->json('settings') ?? [];
                return $this->cachedNotifSettings;
            }
        } catch (\Exception $e) {
            Log::warning("WhatsAppService: Gagal ambil notif settings dari wrapper: " . $e->getMessage());
        }

        $this->cachedNotifSettings = [];
        return [];
    }

    protected function getEventTemplateSetting(string $eventKey): ?array
    {
        $settings = $this->getNotifSettings();
        $setting  = $settings[$eventKey] ?? null;

        if ($setting && !empty($setting['template_name']) && ($setting['enabled'] ?? true)) {
            return $setting;
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESOLVE SYSTEM VARS
    // ─────────────────────────────────────────────────────────────────────────

    protected function resolveSystemVars($booking, array $extra = []): array
    {
        $serviceName = $booking->service ? $booking->service->name : 'Treatment';

        // Support multi-item booking
        if (
            !$booking->service
            && method_exists($booking, 'items')
            && $booking->items?->isNotEmpty()
        ) {
            $serviceName = $booking->items->map(fn($i) => $i->service?->name)->filter()->join(', ');
        }

        $vars = [
            'therapist_name' => $booking->therapist?->name ?? '-',
            'customer_name'  => $booking->user
                ? $booking->user->name
                : ($booking->guest_name ?? 'Pelanggan'),
            'service_name'   => $serviceName,
            'booking_date'   => $this->formatIndonesianDate($booking->booking_date),
            'booking_time'   => substr($booking->start_time ?? '00:00', 0, 5) . ' WIB',
            'branch_name'    => $booking->branch?->name ?? '-',
            'booking_ref'    => $booking->booking_ref ?? '-',
            'refund_amount'  => 'Rp ' . number_format($booking->refund_amount ?? 0, 0, ',', '.'),
            'review_link'    => env('APP_URL') . '/review/' . ($booking->booking_ref ?? $booking->id),
            'payment_link'   => env('APP_URL') . '/payment/' . ($booking->booking_ref ?? $booking->id),
        ];

        return array_merge($vars, $extra);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEND MAPPED TEMPLATE — via wrapper POST /smart-send
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kirim template berdasarkan event setting dari wrapper.
     * Wrapper akan resolve template_id, mapping vars, dan kirim ke Cekat API.
     *
     * @param string     $eventKey  Key event, contoh: 'customer_confirmation'
     * @param string     $phone     Nomor WA
     * @param string     $name      Nama penerima
     * @param array      $vars      System variables
     * @param string|null $inboxId  Override inbox_id
     */
    protected function sendMappedTemplate(
        string $eventKey,
        string $phone,
        string $name,
        array $vars = [],
        ?string $inboxId = null
    ): bool {
        try {
            $response = Http::timeout(10)->post($this->wrapperUrl . '/smart-send', [
                'event'        => $eventKey,
                'phone_number' => $this->formatPhone($phone),
                'phone_name'   => $name,
                'inbox_id'     => $inboxId ?? $this->defaultInboxId,
                'variables'    => $vars,
            ]);

            $body = $response->json();

            if ($body['skipped'] ?? false) {
                Log::info("[WA] smart-send skipped event={$eventKey}", ['reason' => $body['reason'] ?? '']);
                return false; // caller akan fallback ke text
            }

            if (!($body['success'] ?? false)) {
                Log::warning("[WA] smart-send failed event={$eventKey}", ['body' => $body]);
                return false;
            }

            Log::info("[WA] smart-send ok event={$eventKey} phone={$phone}");
            return true;
        } catch (\Exception $e) {
            Log::error("[WA] smart-send exception event={$eventKey}: " . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEND TEXT (legacy — masih dipakai untuk fallback & OTP/Welcome)
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // OTP & WELCOME (pakai endpoint lama, tidak lewat Cekat)
    // ─────────────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────────────
    // CUSTOMER NOTIFICATIONS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dipanggil dari PaymentController & BookingController
     * $type: 'confirmation' | 'reschedule' | 'cancellation' | 'reminder_h1' | 'reminder_h2' | 'review'
     */
    public function sendCustomerNotification($phone, $type, $data, $branchId = null)
    {
        if (empty($phone)) return false;

        $customer     = $data['customer'] ?? null;
        $booking      = $data['booking']  ?? null;
        $branch       = $data['branch']   ?? null;
        $customerName = $data['customer_name'] ?? ($customer?->name ?? 'Pelanggan');

        $eventMap = [
            'confirmation' => 'customer_confirmation',
            'reschedule'   => 'customer_reschedule',
            'cancellation' => 'customer_cancellation',
            'reminder_h1'  => 'customer_reminder_h1',
            'reminder_h2'  => 'customer_reminder_2h',
            'review_request' => 'customer_review',
        ];

        $eventKey = $eventMap[$type] ?? "customer_{$type}";

        $vars = [];
        if ($booking) {
            $vars = $this->resolveSystemVars($booking, [
                'payment_link' => $data['payment_link'] ?? (env('APP_URL') . '/payment/' . ($booking->booking_ref ?? $booking->id)),
                'review_link'  => $data['review_link']  ?? (env('APP_URL') . '/review/'  . ($booking->booking_ref ?? $booking->id)),
            ]);
        }

        // Merge extra dari data langsung (misal dp_amount)
        if (isset($data['dp_amount'])) {
            $vars['refund_amount'] = 'Rp ' . number_format($data['dp_amount'], 0, ',', '.');
        }

        $inboxId = $branch?->cekat_inbox_id ?? $this->defaultInboxId;

        $sent = $this->sendMappedTemplate($eventKey, $phone, $customerName, $vars, $inboxId);

        // Fallback ke NotificationTemplateService (sistem lama)
        if (!$sent) {
            $parsed = $this->notificationTemplateService->parseTemplate($type, $data, $branchId);
            if ($parsed) {
                return $this->sendMessage($phone, $parsed['message'], $branchId);
            }
        }

        return $sent;
    }

    /**
     * Customer: Booking success / konfirmasi dengan link payment
     * Dipanggil dari PaymentController::initiate
     */
    public function sendBookingSuccess($phone, $data)
    {
        if (empty($phone)) return false;
        $branchId = $data['branch_id'] ?? null;
        $this->loadConfig($branchId);

        $customerName = $data['customer_name'] ?? 'Pelanggan';

        $vars = [
            'customer_name'  => $customerName,
            'therapist_name' => $data['therapist_name'] ?? '-',
            'service_name'   => $data['service'] ?? '-',
            'booking_date'   => $this->formatIndonesianDate($data['date'] ?? null),
            'booking_time'   => $data['time'] ?? '-',
            'branch_name'    => $data['branch_name'] ?? '-',
            'booking_ref'    => $data['booking_ref'] ?? '-',
            'payment_link'   => $data['payment_link'] ?? env('APP_URL', '-'),
            'refund_amount'  => '-',
            'review_link'    => '-',
        ];

        $sent = $this->sendMappedTemplate('customer_confirmation', $phone, $customerName, $vars);

        if (!$sent) {
            // Fallback endpoint lama
            try {
                $response = Http::withToken($this->token)
                    ->post($this->baseUrl . '/api/messages/reservation-success', [
                        'phone'          => $this->formatPhone($phone),
                        'nama_pelanggan' => $customerName,
                        'nama_spa'       => $data['branch_name'] ?? 'Zafaran Spa',
                        'tanggal'        => $this->formatIndonesianDate($data['date'] ?? null),
                        'waktu'          => $data['time'],
                        'layanan'        => $data['service'],
                        'lokasi'         => $data['location'] ?? '-'
                    ]);

                Log::info("WhatsApp Booking Success Fallback: " . $response->body());
                return $response->successful();
            } catch (\Exception $e) {
                Log::error("WhatsApp Booking Success Fallback Failed: " . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Customer: Cancellation
     */
    public function sendCustomerCancellationNotification($phone, $booking, $reason = null)
    {
        if (empty($phone)) return false;
        $branchId     = $booking->branch_id ?? null;
        $customerName = $booking->user?->name ?? ($booking->guest_name ?? 'Pelanggan');

        $vars = $this->resolveSystemVars($booking, [
            'cancellation_reason' => $reason ?? '-',
        ]);

        $sent = $this->sendMappedTemplate('customer_cancellation', $phone, $customerName, $vars);

        if (!$sent) {
            $date        = $this->formatIndonesianDate($booking->booking_date);
            $startTime   = substr($booking->start_time ?? '00:00', 0, 5);
            $serviceName = $booking->service?->name ?? 'Layanan';

            $message  = "Halo {$customerName},\n\n";
            $message .= "Booking Anda telah DIBATALKAN:\n";
            $message .= "---------------------------\n";
            $message .= "Referensi: {$booking->booking_ref}\n";
            $message .= "Layanan: {$serviceName}\n";
            $message .= "Jadwal: {$date} jam {$startTime} WIB\n";
            if ($reason) $message .= "Alasan: {$reason}\n";
            $message .= "---------------------------\n\n";
            $message .= "Jika sudah bayar, ajukan refund melalui menu Riwayat di aplikasi.\n\nTerima kasih.";

            $this->sendMessage($phone, $message, $branchId);
        }
    }

    /**
     * Customer: Reschedule
     */
    public function sendCustomerRescheduleNotification($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId     = $booking->branch_id ?? null;
        $customerName = $booking->user?->name ?? ($booking->guest_name ?? 'Pelanggan');
        $vars         = $this->resolveSystemVars($booking);

        $sent = $this->sendMappedTemplate('customer_reschedule', $phone, $customerName, $vars);

        if (!$sent) {
            $date        = $this->formatIndonesianDate($booking->booking_date);
            $startTime   = substr($booking->start_time ?? '00:00', 0, 5);
            $serviceName = $booking->service?->name ?? 'Layanan';

            $message  = "Halo {$customerName},\n\n";
            $message .= "Jadwal booking Anda telah DIUBAH:\n";
            $message .= "---------------------------\n";
            $message .= "Referensi: {$booking->booking_ref}\n";
            $message .= "Layanan: {$serviceName}\n";
            $message .= "Jadwal Baru: {$date} jam {$startTime} WIB\n";
            $message .= "---------------------------\n\nTerima kasih.";

            $this->sendMessage($phone, $message, $branchId);
        }
    }

    /**
     * Customer: Refund
     */
    public function sendCustomerRefundNotification($phone, $booking, $amount, $status = 'requested')
    {
        if (empty($phone)) return false;
        $branchId     = $booking->branch_id ?? null;
        $customerName = $booking->user?->name ?? ($booking->guest_name ?? 'Pelanggan');
        $amountFmt    = 'Rp ' . number_format($amount, 0, ',', '.');

        $vars = $this->resolveSystemVars($booking, ['refund_amount' => $amountFmt]);

        $sent = $this->sendMappedTemplate('customer_refund', $phone, $customerName, $vars);

        if (!$sent) {
            $statusLabel = $status === 'requested' ? '*TELAH DIAJUKAN*' : '*TELAH DIPROSES*';

            $message  = "Halo {$customerName},\n\n";
            $message .= "Informasi refund booking Anda:\n";
            $message .= "---------------------------\n";
            $message .= "Referensi: {$booking->booking_ref}\n";
            $message .= "Status Refund: {$statusLabel}\n";
            $message .= "Jumlah: {$amountFmt}\n";
            $message .= "---------------------------\n\n";
            $message .= $status === 'requested'
                ? "Pengajuan refund telah kami terima dan segera diproses. Terima kasih."
                : "Refund berhasil diproses. Cek rekening Anda dalam 1-3 hari kerja. Terima kasih.";

            $this->sendMessage($phone, $message, $branchId);
        }
    }

    /**
     * Customer: Reminder H-1 & 2 Jam
     */
  public function sendReminder($phone, $booking, $type = 'H-1')
{
    if (empty($phone)) return false;
    $branchId     = $booking->branch_id ?? null;
    $customerName = $booking->user?->name ?? ($booking->guest_name ?? 'Pelanggan');

    $eventKey = ($type === 'H-1') ? 'customer_reminder_h1' : 'customer_reminder_2h';
    $vars     = $this->resolveSystemVars($booking);

    $sent = $this->sendMappedTemplate($eventKey, $phone, $customerName, $vars);

    if (!$sent) {
        $templateType = ($type === 'H-1') ? 'reminder_h1' : 'reminder_h2';
        $data = [
            'customer' => $booking->user ?? (object)['name' => $customerName],
            'booking'  => $booking,
            'branch'   => $booking->branch
        ];
        $parsed = $this->notificationTemplateService->parseTemplate($templateType, $data, $branchId);
        if ($parsed) {
            $this->sendMessage($phone, $parsed['message'], $branchId);
        }
    }

    return $sent; // TAMBAH INI
}

    /**
     * Customer: Review Request
     */
    public function sendReviewRequest($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId     = $booking->branch_id ?? null;
        $customerName = $booking->user?->name ?? ($booking->guest_name ?? 'Pelanggan');
        $reviewLink   = env('APP_URL') . '/review/' . ($booking->booking_ref ?? $booking->id);

        $vars = $this->resolveSystemVars($booking, ['review_link' => $reviewLink]);

        $sent = $this->sendMappedTemplate('customer_review', $phone, $customerName, $vars);

        if (!$sent) {
            $data = [
                'customer'    => $booking->user ?? (object)['name' => $customerName],
                'booking'     => $booking,
                'branch'      => $booking->branch,
                'review_link' => $reviewLink
            ];
            $parsed = $this->notificationTemplateService->parseTemplate('review_request', $data, $branchId);
            if ($parsed) {
                $this->sendMessage($phone, $parsed['message'], $branchId);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STAFF NOTIFICATIONS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Staff: Booking Baru
     */
    public function sendStaffBookingNotification($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId      = $booking->branch_id ?? null;
        $therapistName = $booking->therapist?->name ?? 'Terapis';
        $vars          = $this->resolveSystemVars($booking);

        $sent = $this->sendMappedTemplate('staff_booking_new', $phone, $therapistName, $vars);

        if (!$sent) {
            $date         = $this->formatIndonesianDate($booking->booking_date);
            $startTime    = substr($booking->start_time ?? '00:00', 0, 5);
            $customerName = $booking->user?->name ?? 'Pelanggan';
            $serviceName  = $booking->service?->name ?? 'Treatment';

            $message  = "Halo {$therapistName},\n\n";
            $message .= "Ada pesanan BARU untuk Anda:\n";
            $message .= "---------------------------\n";
            $message .= "Customer: {$customerName}\n";
            $message .= "Layanan: {$serviceName}\n";
            $message .= "Jadwal: {$date} jam {$startTime} WIB\n";
            $message .= "---------------------------\n\n";
            $message .= "Silakan persiapkan diri Anda. Terima kasih!";

            $this->sendMessage($phone, $message, $branchId);
        }
    }

    /**
     * Staff: Reschedule
     */
    public function sendStaffRescheduleNotification($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId      = $booking->branch_id ?? null;
        $therapistName = $booking->therapist?->name ?? 'Terapis';
        $vars          = $this->resolveSystemVars($booking);

        $sent = $this->sendMappedTemplate('staff_reschedule', $phone, $therapistName, $vars);

        if (!$sent) {
            $date        = $this->formatIndonesianDate($booking->booking_date);
            $startTime   = substr($booking->start_time ?? '00:00', 0, 5);
            $customerName = $booking->user?->name ?? 'Customer';
            $serviceName  = $booking->service?->name ?? 'Layanan';

            $message  = "Halo {$therapistName},\n\n";
            $message .= "Pesanan berikut telah DIJADWAL ULANG:\n";
            $message .= "---------------------------\n";
            $message .= "Customer: {$customerName}\n";
            $message .= "Layanan: {$serviceName}\n";
            $message .= "Jadwal Baru: {$date} jam {$startTime} WIB\n";
            $message .= "---------------------------\n\n";
            $message .= "Silakan periksa jadwal terbaru Anda. Terima kasih!";

            $this->sendMessage($phone, $message, $branchId);
        }
    }

    /**
     * Staff: Cancellation
     */
    public function sendStaffCancellationNotification($phone, $booking)
    {
        if (empty($phone)) return false;
        $branchId      = $booking->branch_id ?? null;
        $therapistName = $booking->therapist?->name ?? 'Terapis';
        $vars          = $this->resolveSystemVars($booking);

        $sent = $this->sendMappedTemplate('staff_cancellation', $phone, $therapistName, $vars);

        if (!$sent) {
            $date         = $this->formatIndonesianDate($booking->booking_date);
            $startTime    = substr($booking->start_time ?? '00:00', 0, 5);
            $customerName = $booking->user?->name ?? 'Customer';
            $serviceName  = $booking->service?->name ?? 'Layanan';

            $message  = "Halo {$therapistName},\n\n";
            $message .= "Pesanan berikut telah DIBATALKAN:\n";
            $message .= "---------------------------\n";
            $message .= "Customer: {$customerName}\n";
            $message .= "Layanan: {$serviceName}\n";
            $message .= "Jadwal Semula: {$date} jam {$startTime} WIB\n";
            $message .= "---------------------------\n\n";
            $message .= "Jadwal Anda kini kosong kembali.";

            $this->sendMessage($phone, $message, $branchId);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROMO & BIRTHDAY (pakai endpoint lama)
    // ─────────────────────────────────────────────────────────────────────────

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
}