<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankTransferSubmission;
use App\Models\BankTransferSubmissionFile;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankTransferService
{
    private const PROOF_DISK = 'local';

    /**
     * Signed URL lifetime for bank transfer proof submission (minutes).
     */
    private const BANK_TRANSFER_URL_TTL_MINUTES = 60 * 24 * 30; // 30 days

    public function __construct(
        protected EnrollmentFinancialService $financialService,
    ) {}

    public function startInitialBankTransfer(Enrollment $enrollment): RedirectResponse
    {
        $payment = $this->createOrUpdatePendingPayment($enrollment, Payment::PURPOSE_INITIAL);

        return redirect()->to($this->signedStudentUrl($enrollment->reference_number, Payment::PURPOSE_INITIAL));
    }

    public function startBalanceBankTransfer(string $referenceNumber): RedirectResponse
    {
        $enrollment = Enrollment::query()
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        $this->financialService->recalculateEnrollmentFinancials($enrollment);
        $enrollment->refresh();

        $payment = $this->createOrUpdatePendingPayment($enrollment, Payment::PURPOSE_BALANCE);

        return redirect()->to($this->signedStudentUrl($enrollment->reference_number, Payment::PURPOSE_BALANCE));
    }

    /**
     * @return array{enrollment: Enrollment, payment: Payment, submit_url: string}
     */
    public function getStudentPageData(string $referenceNumber, string $purpose): array
    {
        $enrollment = Enrollment::query()
            ->with('payments')
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        $payment = $enrollment->payments()
            ->where('purpose', $purpose)
            ->first();

        if (! $payment || $payment->payment_method !== 'bank_transfer') {
            throw new RuntimeException('Bank transfer payment session not found.');
        }

        return [
            'enrollment' => $enrollment,
            'payment' => $payment,
            'submit_url' => $this->signedSubmitUrl($enrollment->reference_number, $purpose),
        ];
    }

    public function submitProof(
        string $referenceNumber,
        string $purpose,
        string $transferReference,
        string $manualMethod,
        string $channelCode,
        UploadedFile $photo1,
        ?UploadedFile $photo2 = null
    ): void {
        $enrollment = Enrollment::query()
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        $payment = Payment::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('purpose', $purpose)
            ->where('payment_method', 'bank_transfer')
            ->firstOrFail();

        try {
            $directory = sprintf('bank-transfers/%s/%s', $referenceNumber, $purpose);
            $path1 = $photo1->store($directory, self::PROOF_DISK);

            if (! is_string($path1) || $path1 === '') {
                throw new RuntimeException('Unable to store uploaded file.');
            }

            $path2 = null;
            if ($photo2) {
                $stored = $photo2->store($directory, self::PROOF_DISK);
                if (! is_string($stored) || $stored === '') {
                    throw new RuntimeException('Unable to store uploaded file.');
                }

                $path2 = $stored;
            }

            $submission = BankTransferSubmission::updateOrCreate(
                ['payment_id' => $payment->getKey()],
                [
                    'reference_number' => $transferReference,
                    // Backwards compatible: keep proof_path populated with Photo 1.
                    'proof_path' => $path1,
                    'submitted_at' => now(),
                    'manual_method' => $manualMethod,
                    'channel_code' => $channelCode,
                    'verified_at' => null,
                    'verified_by' => null,
                ]
            );

            BankTransferSubmissionFile::updateOrCreate(
                [
                    'bank_transfer_submission_id' => $submission->getKey(),
                    'slot' => BankTransferSubmissionFile::SLOT_PHOTO_1,
                ],
                [
                    'path' => $path1,
                ]
            );

            if ($path2) {
                BankTransferSubmissionFile::updateOrCreate(
                    [
                        'bank_transfer_submission_id' => $submission->getKey(),
                        'slot' => BankTransferSubmissionFile::SLOT_PHOTO_2,
                    ],
                    [
                        'path' => $path2,
                    ]
                );
            }

            $payment->update([
                'status' => 'submitted',
            ]);
        } catch (\Throwable $e) {
            Log::error('Bank transfer proof submission failed.', [
                'reference_number' => $referenceNumber,
                'purpose' => $purpose,
                'payment_id' => $payment->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Unable to submit proof at the moment. Please try again later.', previous: $e);
        }
    }

    public function downloadProof(BankTransferSubmission $submission): StreamedResponse
    {
        return Storage::disk(self::PROOF_DISK)->download($submission->proof_path);
    }

    public function verifyPayment(Payment $payment, User $verifier, ?string $notes = null): void
    {
        if ($payment->payment_method !== 'bank_transfer') {
            throw new RuntimeException('Only bank transfer payments can be manually verified.');
        }

        /** @var BankTransferSubmission|null $submission */
        $submission = $payment->bankTransferSubmission()->first();
        if (! $submission) {
            throw new RuntimeException('No bank transfer proof has been submitted yet.');
        }

        DB::transaction(function () use ($payment, $submission, $verifier, $notes): void {
            $submission->update([
                'verified_at' => now(),
                'verified_by' => $verifier->getKey(),
                'notes' => $notes,
            ]);

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        });

        $this->financialService->recalculateEnrollmentFinancials($payment->enrollment()->firstOrFail());
    }

    private function signedStudentUrl(string $referenceNumber, string $purpose): string
    {
        return URL::temporarySignedRoute(
            'enroll.bank-transfer.show',
            now()->addMinutes(self::BANK_TRANSFER_URL_TTL_MINUTES),
            [
                'reference_number' => $referenceNumber,
                'purpose' => $purpose,
            ],
        );
    }

    private function signedSubmitUrl(string $referenceNumber, string $purpose): string
    {
        return URL::temporarySignedRoute(
            'enroll.bank-transfer.submit',
            now()->addMinutes(self::BANK_TRANSFER_URL_TTL_MINUTES),
            [
                'reference_number' => $referenceNumber,
                'purpose' => $purpose,
            ],
        );
    }

    private function createOrUpdatePendingPayment(Enrollment $enrollment, string $purpose): Payment
    {
        $fee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;

        if ($purpose === Payment::PURPOSE_BALANCE) {
            if ($enrollment->payment_type !== 'downpayment') {
                throw new RuntimeException('Balance checkout is only available for downpayment enrollments.');
            }

            $tuitionPortion = EnrollmentPricingService::balanceTuitionDue($enrollment);
            if ($tuitionPortion <= 0) {
                throw new RuntimeException('No remaining tuition balance.');
            }
        } else {
            $tuitionPortion = (int) $enrollment->base_amount;
        }

        $totalPesos = $tuitionPortion + $fee;

        return Payment::updateOrCreate(
            [
                'enrollment_id' => $enrollment->getKey(),
                'purpose' => $purpose,
            ],
            [
                'payment_method' => 'bank_transfer',
                'amount' => (int) round($totalPesos * 100),
                'currency' => 'PHP',
                'status' => 'pending',
                'tuition_amount' => $tuitionPortion,
                'paymongo_checkout_session_id' => null,
                'paymongo_payment_intent_id' => null,
                'paymongo_payment_id' => null,
            ],
        );
    }
}
