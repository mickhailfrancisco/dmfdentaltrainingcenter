<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentAgreementService
{
    public function __construct(
        private readonly EnrollmentAgreementSettingService $settings,
    ) {}

    /**
     * Resolve an enrollment by its public reference number.
     */
    public function resolveEnrollment(string $referenceNumber): Enrollment
    {
        $enrollment = Enrollment::query()
            ->where('reference_number', $referenceNumber)
            ->first();

        abort_unless($enrollment !== null, 404);

        return $enrollment;
    }

    /**
     * Stream the enrollment agreement file for a valid enrollment reference.
     */
    public function download(string $referenceNumber): StreamedResponse|BinaryFileResponse
    {
        $this->resolveEnrollment($referenceNumber);

        $filename = $this->settings->effectiveDownloadFilename();

        if ($this->settings->hasStoredFile()) {
            $path = (string) $this->settings->current()?->file_path;

            return Storage::disk($this->settings->disk())->download($path, $filename, [
                'Content-Type' => $this->contentTypeForPath($filename),
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $this->downloadLegacyFile($referenceNumber, $filename);
    }

    private function downloadLegacyFile(string $referenceNumber, string $filename): BinaryFileResponse
    {
        $path = (string) config('enrollment.agreement.path');

        if (! is_file($path) || ! is_readable($path)) {
            Log::error('Enrollment agreement file is missing or unreadable.', [
                'path' => $path,
                'reference_number' => $referenceNumber,
            ]);

            abort(503, 'Enrollment agreement is temporarily unavailable. Please contact support.');
        }

        return response()->download($path, $filename, [
            'Content-Type' => $this->contentTypeForPath($path),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function contentTypeForPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            default => 'application/octet-stream',
        };
    }
}
