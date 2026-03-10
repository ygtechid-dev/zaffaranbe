<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send a plain text email
     * 
     * @param string $to
     * @param string $subject
     * @param string $content
     * @return bool
     */
    public function sendEmail($to, $subject, $content)
    {
        try {
            dispatch(new \App\Jobs\SendEmailJob($to, $subject, $content));
            Log::info("Email job dispatched for: " . $to);
            return true;
        } catch (\Exception $e) {
            Log::error("Email Job Dispatch Failed to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send an HTML email using a view template
     * 
     * @param string $to
     * @param string $subject
     * @param string $view
     * @param array $data
     * @return bool
     */
    public function sendHtmlEmail($to, $subject, $view, $data = [])
    {
        try {
            dispatch(new \App\Jobs\SendEmailJob($to, $subject, null, true, $view, $data));
            Log::info("HTML Email job dispatched for: " . $to);
            return true;
        } catch (\Exception $e) {
            Log::error("HTML Email Job Dispatch Failed to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to staff about a new booking
     * 
     * @param string $email
     * @param object|\App\Models\Booking $booking
     * @return bool
     */
    public function sendStaffBookingNotification($email, $booking)
    {
        if (empty($email))
            return false;

        $date = $booking->booking_date instanceof \Carbon\Carbon ? $booking->booking_date->format('d M Y') : $booking->booking_date;
        $startTime = substr($booking->start_time, 0, 5);
        $customerName = $booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan');
        $serviceName = $booking->service ? $booking->service->name : 'Treatment';

        $subject = "[Zafaran] Jadwal Booking Baru - " . $date;

        $message = "Halo {$booking->therapist->name},\n\n";
        $message .= "Ada pesanan BARU untuk Anda:\n";
        $message .= "---------------------------\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal: {$date} jam {$startTime} WIB\n";
        $message .= "---------------------------\n\n";
        $message .= "Silakan persiapkan diri Anda. Terima kasih!";

        return $this->sendEmail($email, $subject, $message);
    }

    /**
     * Send notification to staff about a cancellation
     * 
     * @param string $email
     * @param object|\App\Models\Booking $booking
     * @return bool
     */
    public function sendStaffCancellationNotification($email, $booking)
    {
        if (empty($email))
            return false;

        $date = $booking->booking_date instanceof \Carbon\Carbon ? $booking->booking_date->format('d M Y') : $booking->booking_date;
        $startTime = substr($booking->start_time, 0, 5);
        $customerName = $booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan');
        $serviceName = $booking->service ? $booking->service->name : 'Layanan';

        $subject = "[Zafaran] PEMBATALAN Booking - " . $date;

        $message = "Halo {$booking->therapist->name},\n\n";
        $message .= "Pesanan berikut telah DIBATALKAN:\n";
        $message .= "---------------------------\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal Semula: {$date} jam {$startTime} WIB\n";
        $message .= "---------------------------\n\n";
        $message .= "Jadwal Anda kini kosong kembali. Terima kasih.";

        return $this->sendEmail($email, $subject, $message);
    }

    /**
     * Send notification to staff about a reschedule
     * 
     * @param string $email
     * @param object|\App\Models\Booking $booking
     * @return bool
     */
    public function sendStaffRescheduleNotification($email, $booking)
    {
        if (empty($email))
            return false;

        $date = $booking->booking_date instanceof \Carbon\Carbon ? $booking->booking_date->format('d M Y') : $booking->booking_date;
        $startTime = substr($booking->start_time, 0, 5);
        $customerName = $booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan');
        $serviceName = $booking->service ? $booking->service->name : 'Layanan';

        $subject = "[Zafaran] PERUBAHAN JADWAL Booking - " . $date;

        $message = "Halo {$booking->therapist->name},\n\n";
        $message .= "Pesanan berikut telah DIJADWAL ULANG (Reschedule):\n";
        $message .= "---------------------------\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal Baru: {$date} jam {$startTime} WIB\n";
        $message .= "---------------------------\n\n";
        $message .= "Silakan periksa jadwal terbaru Anda. Terima kasih!";

        return $this->sendEmail($email, $subject, $message);
    }

    /**
     * Send notification to customer about a cancellation
     */
    public function sendCustomerCancellationNotification($email, $booking)
    {
        if (empty($email))
            return false;

        $date = $booking->booking_date instanceof \Carbon\Carbon ? $booking->booking_date->format('d M Y') : $booking->booking_date;
        $startTime = substr($booking->start_time, 0, 5);
        $serviceName = $booking->service ? $booking->service->name : 'Layanan';

        $subject = "[Zafaran] Konfirmasi Pembatalan Booking - " . $date;

        $message = "Halo " . ($booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan')) . ",\n\n";
        $message .= "Kami mengkonfirmasi bahwa booking Anda telah DIBATALKAN:\n";
        $message .= "---------------------------\n";
        $message .= "Referensi: {$booking->booking_ref}\n";
        $message .= "Layanan: {$serviceName}\n";
        $message .= "Jadwal Semula: {$date} jam {$startTime} WIB\n";
        $message .= "---------------------------\n\n";
        $message .= "Jika Anda telah melakukan pembayaran, silakan ajukan refund melalui aplikasi jika sistem belum memprosesnya secara otomatis.\n\n";
        $message .= "Terima kasih.";

        return $this->sendEmail($email, $subject, $message);
    }

    /**
     * Send notification to customer about refund
     */
    public function sendCustomerRefundNotification($email, $booking, $amount, $status = 'requested')
    {
        if (empty($email))
            return false;

        $subject = "[Zafaran] Update Refund Booking - " . $booking->booking_ref;

        $statusLabel = $status === 'requested' ? 'Telah Diajukan' : 'Telah Diproses';

        $message = "Halo " . ($booking->user ? $booking->user->name : ($booking->guest_name ?? 'Pelanggan')) . ",\n\n";
        $message .= "Informasi mengenai refund booking Anda:\n";
        $message .= "---------------------------\n";
        $message .= "Referensi: {$booking->booking_ref}\n";
        $message .= "Status Refund: {$statusLabel}\n";
        $message .= "Jumlah: Rp " . number_format($amount) . "\n";
        $message .= "---------------------------\n\n";

        if ($status === 'requested') {
            $message .= "Pengajuan refund Anda telah kami terima dan akan segera diproses oleh tim kami. Mohon tunggu informasi selanjutnya.\n";
        } else {
            $message .= "Refund Anda telah berhasil diproses. Silakan cek rekening Anda dalam 1-3 hari kerja.\n";
        }

        $message .= "\nTerima kasih atas kesabaran Anda.";

        return $this->sendEmail($email, $subject, $message);
    }
}
