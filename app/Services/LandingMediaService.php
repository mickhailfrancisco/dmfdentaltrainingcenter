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
     * Storage visibility for newly uploaded landing media assets.
     */
    public function uploadVisibility(): string
    {
        return $this->shouldUseSignedUrls($this->disk()) ? 'private' : 'public';
    }

    /**
     * Resolve a display URL for a stored image path.
     * Falls back to asset() for legacy public/images/feedback/* paths bundled in the repo.
     * Uses pre-signed temporary URLs for private disks.
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

        $disk = $this->disk();

        try {
            if ($this->shouldUseSignedUrls($disk)) {
                return Storage::disk($disk)->temporaryUrl(
                    $path,
                    $this->signedUrlExpiresAt(),
                );
            }

            return Storage::disk($disk)->url($path);
        } catch (\Throwable $exception) {
            Log::warning('Failed to resolve landing media asset URL.', [
                'disk' => $disk,
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

    private function shouldUseSignedUrls(string $disk): bool
    {
        if (! (bool) config('landing-media.use_signed_urls', true)) {
            return false;
        }

        return config("filesystems.disks.{$disk}.driver") === 's3';
    }

    private function signedUrlExpiresAt(): \DateTimeInterface
    {
        $minutes = max(1, (int) config('landing-media.signed_url_minutes', 15));

        return now()->addMinutes($minutes);
    }
}
