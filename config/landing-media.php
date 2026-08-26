<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Landing Page Media Storage (Feedback & Gallery)
    |--------------------------------------------------------------------------
    |
    | Uses the dmf_s3 disk (matches Laravel Cloud LARAVEL_CLOUD_DISK_CONFIG). Configure
    | AWS_* in .env locally; Cloud injects credentials for dmf_s3 at boot.
    | Feedback and gallery images are kept in separate S3 folders — they are
    | unrelated features (testimonials vs. facility/photo gallery) even though
    | they share the same storage plumbing.
    |
    */

    'disk' => env('LANDING_MEDIA_DISK', 'dmf_s3'),

    'feedback_directory' => env('LANDING_FEEDBACK_DIRECTORY', 'landing/feedback'),

    'gallery_directory' => env('LANDING_GALLERY_DIRECTORY', 'landing/gallery'),

    /*
    | Bundled repo assets under public/images/feedback/ (pre-dating S3 uploads)
    | use asset() until replaced via admin upload.
    */
    'legacy_feedback_public_prefix' => 'images/feedback/',
];
