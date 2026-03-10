<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;

class NotificationTemplateService
{
    /**
     * Parse template with variables
     */
    public function parseTemplate($type, $data, $branchId = null)
    {
        // 1. Get Template from Settings
        $setting = NotificationSetting::where('type', 'customer')
            ->where('branch_id', $branchId)
            ->first();

        if (!$setting) {
            // Fallback to global settings if branch specific not found
            $setting = NotificationSetting::where('type', 'customer')
                ->whereNull('branch_id')
                ->first();
        }

        $templates = $setting ? $setting->settings : [];
        $templateData = $templates[$type] ?? null;

        if (!$templateData || !($templateData['enabled'] ?? true)) {
            return null;
        }

        $message = $templateData['message'] ?? '';
        $title = $templateData['title'] ?? '';

        // 2. Replace Variables
        $variables = $this->prepareVariables($data);
        
        foreach ($variables as $key => $value) {
            $message = str_replace($key, $value, $message);
            $title = str_replace($key, $value, $title);
        }

        return [
            'title' => $title,
            'message' => $message
        ];
    }

    /**
     * Prepare variables for replacement
     */
    private function prepareVariables($data)
    {
        $vars = [];

        // Customer Info
        if (isset($data['customer'])) {
            $vars['CUSTOMER_NAME'] = $data['customer']->name;
        } elseif (isset($data['customer_name'])) {
            $vars['CUSTOMER_NAME'] = $data['customer_name'];
        }

        // Booking Info
        if (isset($data['booking'])) {
            $booking = $data['booking'];
            $vars['BOOKING_REFERENCE'] = $booking->booking_ref ?? ('#' . $booking->id);
            $vars['BOOKING_DATE_TIME'] = Carbon::parse($booking->booking_date . ' ' . $booking->start_time)->format('d M Y H:i');
            $vars['SERVICE_NAME'] = $booking->service->name ?? 'Layanan';
        }

        // Branch/Business Info
        if (isset($data['branch'])) {
            $branch = $data['branch'];
            $vars['BUSINESS_NAME'] = $branch->name;
            $vars['LOCATION_NAME'] = $branch->address ?? $branch->name;
            
            $mapUrl = '';
            if ($branch->latitude && $branch->longitude) {
                $mapUrl = "https://www.google.com/maps?q={$branch->latitude},{$branch->longitude}";
            }
            $vars['LOCATION_MAP'] = $mapUrl;
            $vars['LOCATION_PHONE'] = $branch->phone ?? '';
        }

        // Dynamic Settings (DP, etc)
        if (isset($data['dp_amount'])) {
            $vars['DP_AMOUNT'] = number_format($data['dp_amount'], 0, ',', '.');
        }

        // Action specific
        if (isset($data['payment_link'])) {
            $vars['PAYMENT_LINK'] = $data['payment_link'];
        }
        
        if (isset($data['cancellation_reason'])) {
            $vars['CANCELLATION_REASON'] = $data['cancellation_reason'];
        }

        if (isset($data['review_link'])) {
            $vars['REVIEW_LINK'] = $data['review_link'];
        }

        return $vars;
    }
}
