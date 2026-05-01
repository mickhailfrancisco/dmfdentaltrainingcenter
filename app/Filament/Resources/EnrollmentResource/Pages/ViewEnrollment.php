<?php

declare(strict_types=1);

namespace App\Filament\Resources\EnrollmentResource\Pages;

use App\Filament\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Services\EnrollmentFinancialService;
use App\Support\PermissionCodes;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;

class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected ?string $subheading = null;

    /**
     * Sync tuition ledger from paid payments so the infolist shows correct cumulative tuition (legacy rows may have had `tuition_amount` = 0).
     *
     * @author CKD
     *
     * @created 2026-03-26
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var Enrollment $enrollment */
        $enrollment = $this->getRecord();
        app(EnrollmentFinancialService::class)->recalculateEnrollmentFinancials($enrollment);
        $enrollment->refresh();
    }

    public function getTitle(): string
    {
        $record = $this->getRecord();

        $fullName = trim(sprintf(
            '%s %s %s',
            (string) ($record->first_name ?? ''),
            (string) ($record->middle_name ?? ''),
            (string) ($record->surname ?? ''),
        ));

        $label = $fullName !== '' ? $fullName : 'Student';

        return "Enrollment Record — {$label}";
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $purpose = $this->resolveNextPaymentPurpose();
        if ($purpose !== null && $this->viewerMayCopyPaymongoLinkForPurpose($purpose)) {
            $actions[] = Actions\Action::make('copyPaymentLink')
                ->label('Copy payment link')
                ->icon('heroicon-m-clipboard-document')
                ->color('warning')
                ->tooltip($purpose === Payment::PURPOSE_INITIAL
                    ? 'Copy payment link to complete initial checkout'
                    : 'Copy payment link to pay remaining tuition')
                ->action(function () use ($purpose): void {
                    /** @var Enrollment $record */
                    $record = $this->getRecord();

                    $url = $purpose === Payment::PURPOSE_BALANCE
                        ? URL::temporarySignedRoute(
                            'enroll.balance',
                            now()->addYears(5),
                            ['reference_number' => $record->reference_number],
                        )
                        : URL::temporarySignedRoute(
                            'enroll.checkout',
                            now()->addYears(5),
                            ['reference_number' => $record->reference_number],
                        );

                    $this->js('window.navigator.clipboard.writeText('.Js::from($url).')');

                    Notification::make()
                        ->title('Payment link copied')
                        ->body('Paste it into SMS, Messenger, Viber, or email for the student.')
                        ->success()
                        ->send();
                });
        }

        // Note: The legacy "Copy payment link" (balance page) action remains in `EnrollmentResource` index table.
        // The record page uses a single consolidated action above to reduce link clutter.

        $actions[] = Actions\Action::make('back')
            ->label('Back to Enrollments')
            ->icon('heroicon-m-arrow-left')
            ->color('gray')
            ->url(fn () => static::getResource()::getUrl('index'));

        return $actions;
    }

    private function resolvePayBalanceSignedUrl(): ?string
    {
        /** @var Enrollment $record */
        $record = $this->getRecord();

        if ($record->payment_type !== 'downpayment' || $record->computed_balance_tuition_due <= 0) {
            return null;
        }

        return URL::temporarySignedRoute(
            'enroll.balance',
            now()->addYears(5),
            ['reference_number' => $record->reference_number],
        );
    }

    private function resolveBankTransferSignedUrl(string $purpose): ?string
    {
        /** @var Enrollment $record */
        $record = $this->getRecord();

        return URL::temporarySignedRoute(
            'enroll.bank-transfer.show',
            now()->addYears(5),
            [
                'reference_number' => $record->reference_number,
                'purpose' => $purpose,
            ],
        );
    }

    private function viewerMayCopyPayBalanceLink(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermission(PermissionCodes::ENROLLMENT_ACTION_COPY_PAY_BALANCE_LINK);
    }

    private function viewerMayCopyInitialPayLink(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Unified permission: "Copy payment link (table & record)" covers both initial and balance.
        return $user->hasPermission(PermissionCodes::ENROLLMENT_ACTION_COPY_PAY_BALANCE_LINK);
    }

    private function viewerMayCopyBankTransferLink(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermission(PermissionCodes::ENROLLMENT_ACTION_COPY_BANK_TRANSFER_LINK);
    }

    private function resolveNextPaymentPurpose(): ?string
    {
        /** @var Enrollment $record */
        $record = $this->getRecord();

        // If the student already submitted bank transfer proof for the initial payment,
        // we should not present an "initial checkout" link anymore.
        $hasSubmittedInitialBankTransfer = Payment::query()
            ->where('enrollment_id', $record->getKey())
            ->where('purpose', Payment::PURPOSE_INITIAL)
            ->where('payment_method', 'bank_transfer')
            ->where('status', 'submitted')
            ->exists();

        if ((int) $record->amount_paid_tuition <= 0 && ! $hasSubmittedInitialBankTransfer) {
            return Payment::PURPOSE_INITIAL;
        }

        // Only allow balance payment links once an initial payment is actually settled.
        if ((int) $record->amount_paid_tuition > 0 && $record->payment_type === 'downpayment' && $record->computed_balance_tuition_due > 0) {
            return Payment::PURPOSE_BALANCE;
        }

        return null;
    }

    private function viewerMayCopyPaymongoLinkForPurpose(string $purpose): bool
    {
        return match ($purpose) {
            Payment::PURPOSE_INITIAL => $this->viewerMayCopyInitialPayLink(),
            Payment::PURPOSE_BALANCE => $this->viewerMayCopyPayBalanceLink(),
            default => false,
        };
    }

    // Bank transfer links are now accessed via the unified resume checkout page,
    // so we no longer expose a separate "copy bank transfer link" action.
}
