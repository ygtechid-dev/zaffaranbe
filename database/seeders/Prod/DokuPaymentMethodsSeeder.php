<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class DokuPaymentMethodsSeeder extends Seeder
{
    public function run()
    {
        $methods = [
            // 0. Manual/POS Methods
            [
                'code' => 'CASH',
                'name' => 'Tunai (Cash)',
                'type' => 'cash',
                'description' => 'Pembayaran tunai di kasir',
                'icon' => 'banknote',
                'is_active' => true,
                'is_global' => true,
                'is_online' => false,
                'fee' => 0
            ],
            [
                'code' => 'EDC',
                'name' => 'Kartu Debit/Kredit (EDC)',
                'type' => 'card',
                'description' => 'Pembayaran via mesin EDC di outlet',
                'icon' => 'credit-card',
                'is_active' => true,
                'is_global' => true,
                'is_online' => false,
                'fee' => 0
            ],
            [
                'code' => 'TRANSFER_MANUAL',
                'name' => 'Transfer Manual',
                'type' => 'transfer',
                'description' => 'Transfer manual ke rekening outlet',
                'icon' => 'send',
                'is_active' => true,
                'is_global' => true,
                'is_online' => false,
                'fee' => 0
            ],
            // 1. QRIS (Must be first)
            [
                'code' => 'QRIS',
                'name' => 'QRIS (E-Wallet)',
                'type' => 'digital',
                'description' => 'Gopay, OVO, Dana, LinkAja, ShopeePay',
                'icon' => 'qr-code',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],

            // 2. E-Wallets (Specific)
            [
                'code' => 'EMONEY_SHOPEEPAY',
                'name' => 'ShopeePay',
                'type' => 'digital',
                'description' => 'Payment via ShopeePay',
                'icon' => 'wallet',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'EMONEY_OVO',
                'name' => 'OVO',
                'type' => 'digital',
                'description' => 'Payment via OVO',
                'icon' => 'wallet',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'EMONEY_DANA',
                'name' => 'DANA',
                'type' => 'digital',
                'description' => 'Payment via DANA',
                'icon' => 'wallet',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],

            // 3. Virtual Accounts
            [
                'code' => 'VIRTUAL_ACCOUNT_BCA',
                'name' => 'BCA Virtual Account',
                'type' => 'digital',
                'description' => 'Transfer via BCA Virtual Account',
                'icon' => 'bank',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'VIRTUAL_ACCOUNT_MANDIRI',
                'name' => 'Mandiri Virtual Account',
                'type' => 'digital',
                'description' => 'Transfer via Mandiri Virtual Account',
                'icon' => 'bank',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'VIRTUAL_ACCOUNT_BNI',
                'name' => 'BNI Virtual Account',
                'type' => 'digital',
                'description' => 'Transfer via BNI Virtual Account',
                'icon' => 'bank',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'VIRTUAL_ACCOUNT_BRI',
                'name' => 'BRI Virtual Account',
                'type' => 'digital',
                'description' => 'Transfer via BRI Virtual Account',
                'icon' => 'bank',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'VIRTUAL_ACCOUNT_PERMATA',
                'name' => 'Permata Virtual Account',
                'type' => 'digital',
                'description' => 'Transfer via Permata Virtual Account',
                'icon' => 'bank',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'VIRTUAL_ACCOUNT_CIMB',
                'name' => 'CIMB Niaga Virtual Account',
                'type' => 'digital',
                'description' => 'Transfer via CIMB Niaga Virtual Account',
                'icon' => 'bank',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'VIRTUAL_ACCOUNT_DANAMON',
                'name' => 'Danamon Virtual Account',
                'type' => 'digital',
                'description' => 'Transfer via Danamon Virtual Account',
                'icon' => 'bank',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],

            // 4. Credit Card
            [
                'code' => 'CREDIT_CARD',
                'name' => 'Credit Card',
                'type' => 'digital',
                'description' => 'Visa, Mastercard, JCB',
                'icon' => 'credit-card',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],

            // 5. Paylater
            [
                'code' => 'PEER_TO_PEER_AKULAKU',
                'name' => 'Akulaku Paylater',
                'type' => 'digital',
                'description' => 'Buy now pay later with Akulaku',
                'icon' => 'clock',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'PEER_TO_PEER_KREDIVO',
                'name' => 'Kredivo',
                'type' => 'digital',
                'description' => 'Buy now pay later with Kredivo',
                'icon' => 'clock',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
            [
                'code' => 'PEER_TO_PEER_INDODANA',
                'name' => 'Indodana',
                'type' => 'digital',
                'description' => 'Buy now pay later with Indodana',
                'icon' => 'clock',
                'is_active' => true,
                'is_global' => true,
                'is_online' => true,
                'fee' => 0
            ],
        ];

        // First, reset all existing DOKU related methods to inactive to ensure clean slate order or just update
        // Actually updateOrCreate will just update them. Order in DB doesn't determine order in API response unless we sort by ID or something.
        // But usually seeded data is inserted in order if table is truncated. Since we use updateOrCreate, existing IDs remain.
        // To guarantee order for the user, we might want to truncate or just rely on the frontend sorting or backend query.
        // The backend query `PaymentMethod::where('is_active', true)` doesn't specify sort.
        // But `updateOrCreate` is fine.

        foreach ($methods as $m) {
            PaymentMethod::updateOrCreate(
                ['code' => $m['code']],
                $m
            );
        }
    }
}
