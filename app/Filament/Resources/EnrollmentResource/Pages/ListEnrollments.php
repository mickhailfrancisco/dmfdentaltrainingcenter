<?php

namespace App\Filament\Resources\EnrollmentResource\Pages;

use App\Enums\EnrollmentStatus;
use App\Filament\Exports\EnrollmentExporter;
use App\Filament\Resources\EnrollmentResource;
use App\Models\Payment;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEnrollments extends ListRecords
{
    protected static string $resource = EnrollmentResource::class;

    public function getHeading(): string
    {
        return 'Enrollment Records';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        if (! EnrollmentResource::currentUserCanExportEnrollments()) {
            return [];
        }

        return [
            Actions\ExportAction::make()
                ->exporter(EnrollmentExporter::class)
                ->label('Export CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray'),
        ];
    }

    /**
     * @return array<string | int, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'needs_action' => Tab::make('Needs action')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyNeedsActionScope($query)),
            'awaiting_payment' => Tab::make('Awaiting payment')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyAwaitingPaymentScope($query)),
            'pending_verification' => Tab::make('Pending verification')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyPendingVerificationScope($query)),
            'balance_due' => Tab::make('Balance due')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyBalanceDueScope($query)),
        ];
    }

    private function applyAwaitingPaymentScope(Builder $query): Builder
    {
        return $query
            ->where('status', EnrollmentStatus::PENDING->value)
            ->where('amount_paid_tuition', '<=', 0)
            ->whereDoesntHave('payments', fn (Builder $paymentQuery): Builder => $this->submittedInitialBankTransferScope($paymentQuery));
    }

    private function applyPendingVerificationScope(Builder $query): Builder
    {
        return $query
            ->where('status', EnrollmentStatus::PENDING->value)
            ->whereHas('payments', fn (Builder $paymentQuery): Builder => $this->submittedInitialBankTransferScope($paymentQuery));
    }

    private function applyBalanceDueScope(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
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

    private function applyNeedsActionScope(Builder $query): Builder
    {
        return $query->where(function (Builder $outer): void {
            $outer
                ->where(function (Builder $awaiting): void {
                    $this->applyAwaitingPaymentScope($awaiting);
                })
                ->orWhere(function (Builder $verification): void {
                    $this->applyPendingVerificationScope($verification);
                })
                ->orWhere(function (Builder $balance): void {
                    $this->applyBalanceDueScope($balance);
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
