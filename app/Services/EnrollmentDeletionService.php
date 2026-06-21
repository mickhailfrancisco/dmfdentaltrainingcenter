<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EnrollmentDeletionService
{
    private const PROOF_DISK = 'local';

    /**
     * Whether an enrollment may be permanently removed (abandoned / duplicate cleanup).
     */
    public function canDelete(Enrollment $enrollment): bool
    {
        if ((int) $enrollment->amount_paid_tuition > 0) {
            return false;
        }

        if (array_key_exists('has_blocking_payment', $enrollment->getAttributes())) {
            return ! (bool) $enrollment->getAttribute('has_blocking_payment');
        }

        return ! $enrollment->payments()
            ->whereIn('status', ['paid', 'submitted'])
            ->exists();
    }

    /**
     * Permanently delete an abandoned enrollment and related bank-transfer proof files.
     *
     * @throws RuntimeException when the enrollment is not eligible for deletion
     */
    public function delete(Enrollment $enrollment, User $actor): void
    {
        if (! $this->canDelete($enrollment)) {
            throw new RuntimeException('This enrollment cannot be deleted because it has a confirmed or submitted payment.');
        }

        DB::transaction(function () use ($enrollment, $actor): void {
            $enrollment->load([
                'payments.bankTransferSubmission.files',
            ]);

            $paths = [];
            foreach ($enrollment->payments as $payment) {
                $submission = $payment->bankTransferSubmission;
                if ($submission === null) {
                    continue;
                }

                if (filled($submission->proof_path)) {
                    $paths[] = (string) $submission->proof_path;
                }

                foreach ($submission->files as $file) {
                    if (filled($file->path)) {
                        $paths[] = (string) $file->path;
                    }
                }
            }

            $paths = array_values(array_unique(array_filter($paths)));

            $enrollment->delete();

            foreach ($paths as $path) {
                if (str_starts_with($path, 'bank-transfers/')) {
                    Storage::disk(self::PROOF_DISK)->delete($path);
                }
            }

            Log::info('Enrollment deleted.', [
                'enrollment_id' => $enrollment->getKey(),
                'reference_number' => $enrollment->reference_number,
                'deleted_by' => $actor->getKey(),
            ]);
        });
    }
}
