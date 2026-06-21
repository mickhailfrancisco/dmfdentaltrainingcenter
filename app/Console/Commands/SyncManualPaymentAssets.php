<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ManualPaymentChannel;
use App\Services\ManualPaymentChannelService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Copy bundled bank QR/logo assets to the configured manual payment disk.
 *
 * @author CKD
 *
 * @created 2026-06-21
 */
class SyncManualPaymentAssets extends Command
{
    protected $signature = 'manual-payment:sync-assets';

    protected $description = 'Upload bundled bank QR and logo files to the manual payment storage disk';

    public function handle(ManualPaymentChannelService $service): int
    {
        $disk = $service->disk();
        $storage = Storage::disk($disk);
        $synced = 0;

        ManualPaymentChannel::query()
            ->where('type', ManualPaymentChannel::TYPE_BANK_TRANSFER)
            ->each(function (ManualPaymentChannel $channel) use ($service, $storage, &$synced): void {
                $updates = [];

                if (filled($channel->qr_path)) {
                    $newQrPath = $this->syncAsset(
                        $storage,
                        (string) $channel->qr_path,
                        $service->qrDirectory(),
                        $channel->channel_code.'-qr'
                    );

                    if ($newQrPath !== null) {
                        $updates['qr_path'] = $newQrPath;
                    }
                }

                if (filled($channel->logo_path)) {
                    $newLogoPath = $this->syncAsset(
                        $storage,
                        (string) $channel->logo_path,
                        $service->logoDirectory(),
                        $channel->channel_code.'-logo'
                    );

                    if ($newLogoPath !== null) {
                        $updates['logo_path'] = $newLogoPath;
                    }
                }

                if ($updates !== []) {
                    $channel->update($updates);
                    $synced++;
                }
            });

        $service->forgetCache();

        $this->info("Synced assets for {$synced} bank channel(s) on disk [{$disk}].");

        return self::SUCCESS;
    }

    private function syncAsset(
        Filesystem $storage,
        string $sourcePath,
        string $targetDirectory,
        string $basename,
    ): ?string {
        $legacyPrefix = (string) config('manual-payment.legacy_public_prefix', 'images/banks/');

        if (! str_starts_with($sourcePath, $legacyPrefix)) {
            return null;
        }

        $absoluteSource = public_path($sourcePath);

        if (! is_file($absoluteSource)) {
            $this->warn("Source file missing: {$sourcePath}");

            return null;
        }

        $extension = pathinfo($absoluteSource, PATHINFO_EXTENSION) ?: 'bin';
        $targetPath = trim($targetDirectory, '/').'/'.$basename.'.'.$extension;

        $storage->put($targetPath, File::get($absoluteSource), 'public');

        return $targetPath;
    }
}
