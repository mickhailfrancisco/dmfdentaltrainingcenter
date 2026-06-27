<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EnrollmentAgreementSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EnrollmentAgreementSettingService
{
    public function disk(): string
    {
        return (string) config('enrollment.agreement.disk', 'dmf_s3');
    }

    public function directory(): string
    {
        return (string) config('enrollment.agreement.storage_directory', 'enrollment-agreements');
    }

    public function uploadVisibility(): string
    {
        return config("filesystems.disks.{$this->disk()}.driver") === 's3' ? 'private' : 'public';
    }

    public function current(): ?EnrollmentAgreementSetting
    {
        return EnrollmentAgreementSetting::query()->first();
    }

    public function effectiveSubmissionEmail(): string
    {
        $configured = trim((string) ($this->current()?->submission_email ?? ''));

        if ($configured !== '') {
            return $configured;
        }

        return (string) config('enrollment.agreement.default_submission_email', 'enrollment@dmfdental.com');
    }

    public function effectiveDownloadBasename(): string
    {
        $setting = $this->current();

        if (filled($setting?->download_filename)) {
            return $this->normalizeDownloadBasename((string) $setting->download_filename);
        }

        $configured = trim((string) config('enrollment.agreement.download_filename'));

        if ($configured !== '') {
            return $this->normalizeDownloadBasename($configured);
        }

        $path = $setting?->file_path ?? (string) config('enrollment.agreement.path');

        return $this->normalizeDownloadBasename(basename((string) $path));
    }

    public function effectiveDownloadFilename(): string
    {
        $setting = $this->current();
        $extensionSource = filled($setting?->file_path)
            ? (string) $setting->file_path
            : (string) config('enrollment.agreement.path');
        $extension = strtolower(pathinfo($extensionSource, PATHINFO_EXTENSION));
        $basename = $this->effectiveDownloadBasename();

        if ($basename === '') {
            return basename($extensionSource);
        }

        if ($extension === '') {
            return $basename;
        }

        return "{$basename}.{$extension}";
    }

    public function normalizeDownloadBasename(string $filename): string
    {
        $filename = trim($filename);

        if ($filename === '') {
            return '';
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if ($extension === '') {
            return $filename;
        }

        return (string) pathinfo($filename, PATHINFO_FILENAME);
    }

    public function hasStoredFile(): bool
    {
        $path = $this->current()?->file_path;

        if (blank($path)) {
            return false;
        }

        try {
            return Storage::disk($this->disk())->exists((string) $path);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, User $user): EnrollmentAgreementSetting
    {
        $setting = EnrollmentAgreementSetting::query()->firstOrCreate([]);
        $previousPath = $setting->file_path;

        $newPath = filled($data['file_path'] ?? null)
            ? (string) $data['file_path']
            : $previousPath;

        $downloadBasename = filled($data['download_filename'] ?? null)
            ? $this->normalizeDownloadBasename((string) $data['download_filename'])
            : ($setting->download_filename
                ? $this->normalizeDownloadBasename((string) $setting->download_filename)
                : $this->normalizeDownloadBasename(basename($newPath ?? '')));

        if ($downloadBasename === '') {
            $downloadBasename = $this->effectiveDownloadBasename();
        }

        $setting->fill([
            'file_path' => $newPath,
            'download_filename' => $downloadBasename,
            'submission_email' => filled($data['submission_email'] ?? null)
                ? (string) $data['submission_email']
                : $setting->submission_email,
            'updated_by_user_id' => $user->getKey(),
        ]);
        $setting->save();

        if (filled($previousPath) && $previousPath !== $newPath) {
            $this->deleteStoredFile((string) $previousPath);
        }

        return $setting->refresh();
    }

    public function deleteStoredFile(string $path): void
    {
        if (blank($path)) {
            return;
        }

        try {
            if (Storage::disk($this->disk())->exists($path)) {
                Storage::disk($this->disk())->delete($path);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete previous enrollment agreement file.', [
                'disk' => $this->disk(),
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
