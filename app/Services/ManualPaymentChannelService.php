<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ManualPaymentChannel;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ManualPaymentChannelService
{
    private const CACHE_KEY = 'manual_payment_channels.all';

    private const CACHE_TTL_SECONDS = 600;

    public function disk(): string
    {
        return (string) config('manual-payment.disk', 'dmf_s3');
    }

    public function qrDirectory(): string
    {
        return (string) config('manual-payment.qr_directory', 'manual-payment/qr');
    }

    public function logoDirectory(): string
    {
        return (string) config('manual-payment.logo_directory', 'manual-payment/logos');
    }

    /**
     * Storage visibility for newly uploaded QR assets.
     */
    public function uploadVisibility(): string
    {
        return $this->shouldUseSignedUrls($this->disk()) ? 'private' : 'public';
    }

    /**
     * Resolve a display URL for a QR or logo path.
     * Uses pre-signed temporary URLs for private S3 objects.
     */
    public function publicUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $legacyPrefix = (string) config('manual-payment.legacy_public_prefix', 'images/banks/');

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
            Log::warning('Failed to resolve manual payment channel asset URL.', [
                'disk' => $disk,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Persist channel updates and delete replaced QR/logo objects from storage.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateChannel(ManualPaymentChannel $channel, array $data): ManualPaymentChannel
    {
        unset($data['logo_path']);

        $previousQrPath = $channel->qr_path;

        $channel->fill($data);
        $channel->save();

        $channel->refresh();

        if ($previousQrPath !== $channel->qr_path) {
            $this->deletePreviousAsset($previousQrPath);
        }

        $this->forgetCache();

        return $channel;
    }

    /**
     * Delete a replaced asset from the configured storage disk when it exists.
     */
    public function deletePreviousAsset(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $legacyPrefix = (string) config('manual-payment.legacy_public_prefix', 'images/banks/');

        if (str_starts_with($path, $legacyPrefix)) {
            return;
        }

        $disk = $this->disk();

        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete previous manual payment channel asset.', [
                'disk' => $disk,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, ManualPaymentChannel>
     */
    public function allChannels()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return ManualPaymentChannel::query()
                ->orderByRaw("CASE channel_code
                    WHEN 'bdo' THEN 1
                    WHEN 'bpi' THEN 2
                    WHEN 'chinabank' THEN 3
                    WHEN 'palawan_express' THEN 4
                    ELSE 99
                END")
                ->get();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function shouldUseSignedUrls(string $disk): bool
    {
        if (! (bool) config('manual-payment.use_signed_urls', true)) {
            return false;
        }

        return config("filesystems.disks.{$disk}.driver") === 's3';
    }

    private function signedUrlExpiresAt(): DateTimeInterface
    {
        $minutes = max(1, (int) config('manual-payment.signed_url_minutes', 15));

        return now()->addMinutes($minutes);
    }
}
