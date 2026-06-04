<?php

namespace App\Filament\Exports;

use App\Filament\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentExporter extends Exporter
{
    protected static ?string $model = Enrollment::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->withExists([
            'payments as has_submitted_initial_bank_transfer' => function (Builder $paymentQuery): void {
                $paymentQuery
                    ->where('purpose', Payment::PURPOSE_INITIAL)
                    ->where('payment_method', 'bank_transfer')
                    ->where('status', 'submitted');
            },
        ]);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_number')
                ->label('Reference #'),
            ExportColumn::make('student_name')
                ->label('Student name')
                ->state(fn (Enrollment $record): string => trim("{$record->first_name} {$record->surname}")),
            ExportColumn::make('email'),
            ExportColumn::make('purchasable_name_snapshot')
                ->label('Program Enrolled'),
            ExportColumn::make('payment_type')
                ->label('Payment type')
                ->formatStateUsing(fn (?string $state): string => match ($state) {
                    'full' => 'Full payment',
                    'downpayment' => 'Downpayment',
                    default => ucfirst((string) $state),
                }),
            ExportColumn::make('status')
                ->formatStateUsing(fn (?string $state, Enrollment $record): string => EnrollmentResource::formatStatusLabel($record)),
            ExportColumn::make('created_at')
                ->label('Enrolled date')
                ->formatStateUsing(fn ($state): ?string => $state?->timezone(config('app.display_timezone'))->format('Y-m-d H:i:s')),
            ExportColumn::make('total_amount')
                ->label('First payment'),
            ExportColumn::make('amount_paid_tuition')
                ->label('Tuition paid'),
            ExportColumn::make('computed_balance_tuition_due')
                ->label('Remaining balance')
                ->state(fn (Enrollment $record): int => $record->computed_balance_tuition_due),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your enrollment export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
