<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EnrollmentAgreementService
{
    /**
     * Resolve an enrollment by its public reference number.
     *
     * @param  string  $referenceNumber  Enrollment reference from the success page URL.
     *
     * @author CKD
     *
     * @created 2026-06-19
     *
     * @modified 2026-06-19 CKD
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
     * Stream the static enrollment agreement file for a valid enrollment.
     *
     * @param  string  $referenceNumber  Enrollment reference from the success page URL.
     *
     * @author CKD
     *
     * @created 2026-06-19
     *
     * @modified 2026-06-19 CKD
     */
    public function download(string $referenceNumber): BinaryFileResponse
    {
        $this->resolveEnrollment($referenceNumber);

        $path = (string) config('enrollment.agreement.path');
        $filename = trim((string) config('enrollment.agreement.download_filename'));
        if ($filename === '') {
            $filename = basename($path);
        }

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

    /**
     * Resolve the HTTP content type for a supported agreement file extension.
     *
     * @param  string  $path  Absolute path to the agreement file on disk.
     *
     * @author CKD
     *
     * @created 2026-06-19
     *
     * @modified 2026-06-19 CKD
     */
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
