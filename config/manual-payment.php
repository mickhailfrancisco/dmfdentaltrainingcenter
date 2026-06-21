<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Manual Payment Channel Storage
    |--------------------------------------------------------------------------
    |
    | Uses the dmf_s3 disk (matches Laravel Cloud LARAVEL_CLOUD_DISK_CONFIG). Configure
    | AWS_* in .env locally; Cloud injects credentials for dmf_s3 at boot.
    |
    */

    'disk' => env('MANUAL_PAYMENT_DISK', 'dmf_s3'),

    'qr_directory' => env('MANUAL_PAYMENT_QR_DIRECTORY', 'manual-payment/qr'),

    'logo_directory' => env('MANUAL_PAYMENT_LOGO_DIRECTORY', 'manual-payment/logos'),

    /*
    | Bundled repo assets under public/images/banks/ use asset() until synced
    | or replaced via admin upload.
    */
    'legacy_public_prefix' => 'images/banks/',

    /*
    | Private S3 objects should be served via pre-signed temporary URLs.
    | Disable only when the bucket/object policy is intentionally public.
    */
    'use_signed_urls' => env('MANUAL_PAYMENT_USE_SIGNED_URLS', true),

    'signed_url_minutes' => (int) env('MANUAL_PAYMENT_SIGNED_URL_MINUTES', 15),
];
