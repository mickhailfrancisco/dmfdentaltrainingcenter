<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enrollment Agreement
    |--------------------------------------------------------------------------
    |
    | Static agreement file (PDF or Word) served on the enrollment success page.
    | Upload the client's document to storage/app/enrollment-agreements/ on
    | deploy and set the submission email where signed copies should be sent.
    |
    */

    'agreement' => [
        'path' => env('ENROLLMENT_AGREEMENT_PATH', storage_path('app/enrollment-agreements/DMF-Undertaking-December-2025-Lecture.docx')),
        'submission_email' => env('ENROLLMENT_AGREEMENT_EMAIL', 'enrollment@example.com'),
        'download_filename' => env('ENROLLMENT_AGREEMENT_FILENAME', 'DMF-Undertaking-December-2025-Lecture.docx'),
    ],
];
