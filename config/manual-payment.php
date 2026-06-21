<?php

declare(strict_types=1);

$filesystemDisk = env('FILESYSTEM_DISK', 'local');

return [
    /*
    |--------------------------------------------------------------------------
    | Manual Payment Channel Storage
    |--------------------------------------------------------------------------
    |
    | Disk used for admin-managed QR codes and bank logos. Defaults to s3 when
    | FILESYSTEM_DISK=s3 (Laravel Cloud). Use MANUAL_PAYMENT_DISK=public for
    | local development without S3 credentials.
    |
    */

    'disk' => env('MANUAL_PAYMENT_DISK', $filesystemDisk === 's3' ? 's3' : 'public'),

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
