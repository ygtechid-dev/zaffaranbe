<?php

namespace App\Services;

class MidtransStub
{
    public static function charge($params)
    {
        // Return a mock object mimicking the Midtrans response
        $mock = new \stdClass();
        $mock->transaction_id = 'mock-midtrans-' . uniqid();
        $mock->transaction_status = 'pending';
        $mock->expiry_time = date('Y-m-d H:i:s', strtotime('+1 day'));

        // Mock specific fields based on payment type
        if (isset($params['payment_type'])) {
            if ($params['payment_type'] === 'qris') {
                $action = new \stdClass();
                $action->name = 'generate-qr-code';
                $action->url = 'https://mock.midtrans.com/qris/' . $mock->transaction_id;
                $mock->actions = [$action];
            } elseif ($params['payment_type'] === 'bank_transfer') {
                $va = new \stdClass();
                $va->va_number = '888800' . rand(1000, 9999);
                $va->bank = $params['bank_transfer']['bank'] ?? 'bca';
                $mock->va_numbers = [$va];
            }
        }

        return $mock;
    }
}

class MidtransConfigStub
{
    public static $serverKey;
    public static $clientKey;
    public static $isProduction;
    public static $isSanitized;
    public static $is3ds;
}
