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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;

class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected ?string $subheading = null;

    protected function resolveRecord(int|string $key): Model
    {
        return EnrollmentResource::applyViewPageQuery(
            Enrollment::query()->whereKey($key),
        )->firstOrFail();
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

        if ($this->viewerMayRefreshPaymentTotals()) {
            $actions[] = Actions\Action::make('refreshPaymentTotals')
                ->label('Refresh payment totals')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Refresh payment totals?')
                ->modalDescription('This adds up all confirmed payments and updates tuition paid, remaining balance, and enrollment status. Use only if those amounts look incorrect.')
                ->modalSubmitActionLabel('Refresh totals')
                ->action(function (EnrollmentFinancialService $financialService): void {
                    /** @var Enrollment $record */
                    $record = $this->getRecord();

                    $financialService->recalculateEnrollmentFinancials($record);

                    $this->record = EnrollmentResource::applyViewPageQuery(
                        Enrollment::query()->whereKey($record->getKey()),
                    )->firstOrFail();

                    Notification::make()
                        ->title('Payment totals updated')
                        ->body('Tuition paid, remaining balance, and status were refreshed from confirmed payments.')
                        ->success()
                        ->send();
                });
        }

        $actions[] = Actions\Action::make('back')
            ->label('Back to Enrollments')
            ->icon('heroicon-m-arrow-left')
            ->color('gray')
            ->url(fn () => static::getResource()::getUrl('index'));

        return $actions;
    }

    private function viewerMayRefreshPaymentTotals(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermission(PermissionCodes::ENROLLMENT_ACTION_REFRESH_PAYMENT_TOTALS);
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

        if ((int) $record->amount_paid_tuition <= 0
            && ! EnrollmentResource::hasSubmittedInitialBankTransfer($record)) {
            return Payment::PURPOSE_INITIAL;
        }

        if ((int) $record->amount_paid_tuition > 0
            && $record->payment_type === 'downpayment'
            && $record->computed_balance_tuition_due > 0) {
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
}
