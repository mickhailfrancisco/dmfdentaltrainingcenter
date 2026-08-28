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
     * Guess an image's MIME type from its file extension, without touching storage.
     * Used to avoid a live Storage::mimeType() call that Filament's file upload preview
     * would otherwise wait on (and, for a private/unreachable disk, hang on indefinitely).
     */
    public function guessMimeType(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    /**
     * Build the metadata Filament's FileUpload component needs to display an already-stored
     * file, without calling Storage::exists()/size()/mimeType() — those calls are what filter
     * out legacy/missing-on-disk rows during hydration, and what forces the upload widget to
     * fetch the raw file itself (hanging on a private, CORS-unconfigured, or slow disk).
     *
     * @param  string|array<string, string>|null  $storedFileNames
     * @return array{name: string, size: int, type: string, url: ?string}
     */
    public function uploadedFileMetadata(string $path, string|array|null $storedFileNames, bool $isMultiple): array
    {
        $name = $isMultiple
            ? ($storedFileNames[$path] ?? null)
            : $storedFileNames;

        return [
            'name' => $name ?? basename($path),
            'size' => 0,
            'type' => $this->guessMimeType($path),
            'url' => $this->url($path),
        ];
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
