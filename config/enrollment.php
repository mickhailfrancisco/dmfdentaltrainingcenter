<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enrollment Agreement
    |--------------------------------------------------------------------------
    |
    | Agreement file served on the enrollment success page. Admins upload via
    | Filament (Administration → Enrollment agreement). Falls back to the legacy
    | local path below when no admin upload exists yet.
    |
    */

    'agreement' => [
        'disk' => env('ENROLLMENT_AGREEMENT_DISK', 'dmf_s3'),
        'storage_directory' => env('ENROLLMENT_AGREEMENT_DIRECTORY', 'enrollment-agreements'),
        'path' => env('ENROLLMENT_AGREEMENT_PATH', storage_path('app/enrollment-agreements/DMF-Undertaking-December-2025-Lecture.docx')),
        'default_submission_email' => 'enrollment@dmfdental.com',
        'download_filename' => env('ENROLLMENT_AGREEMENT_FILENAME', 'DMF-Undertaking-December-2025-Lecture.docx'),
    ],
];
