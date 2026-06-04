<?php

namespace App\Filament\Pages;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\Payment;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EnrollmentOverview extends Page
{
    protected static ?string $slug = 'enrollment-overview';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Operations';

    protected static ?string $navigationGroup = 'Enrollment';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Operations overview';

    protected static string $view = 'filament.pages.enrollment-overview';

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, int>
     */
    public function getStatCounts(): array
    {
        return [
            'awaiting_payment' => $this->awaitingPaymentQuery()->count(),
            'pending_verification' => $this->pendingVerificationQuery()->count(),
            'balance_due' => $this->balanceDueQuery()->count(),
        ];
    }

    public function getFilteredListUrl(string $tab): string
    {
        return EnrollmentResource::getUrl('index', [
            'activeTab' => $tab,
        ]);
    }

    private function awaitingPaymentQuery(): Builder
    {
        return Enrollment::query()
            ->where('status', EnrollmentStatus::PENDING->value)
            ->where('amount_paid_tuition', '<=', 0)
            ->whereDoesntHave('payments', fn (Builder $paymentQuery): Builder => $this->submittedInitialBankTransferScope($paymentQuery));
    }

    private function pendingVerificationQuery(): Builder
    {
        return Enrollment::query()
            ->where('status', EnrollmentStatus::PENDING->value)
            ->whereHas('payments', fn (Builder $paymentQuery): Builder => $this->submittedInitialBankTransferScope($paymentQuery));
    }

    private function balanceDueQuery(): Builder
    {
        return Enrollment::query()->where(function (Builder $builder): void {
            $builder
                ->where('status', EnrollmentStatus::PARTIALLY_PAID->value)
                ->orWhere(function (Builder $nested): void {
                    $nested
                        ->where('payment_type', 'downpayment')
                        ->where('amount_paid_tuition', '>', 0)
                        ->where('balance_tuition_due', '>', 0);
                });
        });
    }

    private function submittedInitialBankTransferScope(Builder $paymentQuery): Builder
    {
        return $paymentQuery
            ->where('purpose', Payment::PURPOSE_INITIAL)
            ->where('payment_method', 'bank_transfer')
            ->where('status', 'submitted');
    }
}
