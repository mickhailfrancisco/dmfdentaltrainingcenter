<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LandingMediaService
{
    public function disk(): string
    {
        return (string) config('landing-media.disk', 'dmf_s3');
    }

    public function feedbackDirectory(): string
    {
        return (string) config('landing-media.feedback_directory', 'landing/feedback');
    }

    public function galleryDirectory(): string
    {
        return (string) config('landing-media.gallery_directory', 'landing/gallery');
    }

    /**
     * Resolve a display URL for a stored image path.
     * Falls back to asset() for legacy public/images/feedback/* paths bundled in the repo.
     */
    public function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $legacyPrefix = (string) config('landing-media.legacy_feedback_public_prefix', 'images/feedback/');

        if (str_starts_with($path, $legacyPrefix) && is_file(public_path($path))) {
            return asset($path);
        }

        try {
            return Storage::disk($this->disk())->url($path);
        } catch (\Throwable $exception) {
            Log::warning('Failed to resolve landing media asset URL.', [
                'disk' => $this->disk(),
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete a stored image from the configured disk. Legacy bundled repo assets are left alone.
     */
    public function deleteAsset(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $legacyPrefix = (string) config('landing-media.legacy_feedback_public_prefix', 'images/feedback/');

        if (str_starts_with($path, $legacyPrefix)) {
            return;
        }

        $disk = $this->disk();

        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete landing media asset.', [
                'disk' => $disk,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
