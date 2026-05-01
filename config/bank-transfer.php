<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Manual Payment Details
    |--------------------------------------------------------------------------
    |
    | Display details for manual payment methods (bank transfer / remittance).
    |
    */

    'banks' => [
        [
            'bank_name' => 'BDO',
            'account_name' => 'Mickhail Francisco',
            'account_number' => '0000-0000-0000',
            'logo_path' => 'images/banks/logos/bdo.svg',
            'qr_path' => null,
        ],
        [
            'bank_name' => 'BPI',
            'account_name' => 'Mickhail Francisco',
            'account_number' => '0000-0000-0000',
            'logo_path' => 'images/banks/logos/bpi.svg',
            'qr_path' => null,
        ],
        [
            'bank_name' => 'ChinaBank',
            'account_name' => 'Mickhail Francisco',
            'account_number' => '0000-0000-0000',
            'logo_path' => 'images/banks/logos/Chinabank_2024.svg',
            'qr_path' => null,
        ],
    ],

    'remittance' => [
        'receiver_name' => 'Mickhail Francisco',
        'address' => 'TBD',
        'contact_number' => 'TBD',
    ],
];
