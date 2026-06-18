<?php

namespace App\Filament\Resources\EnrollmentResource\Pages;

use App\Filament\Exports\EnrollmentExporter;
use App\Filament\Resources\EnrollmentResource;
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
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->needsAction()),
            'awaiting_payment' => Tab::make('Awaiting payment')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->awaitingPayment()),
            'pending_verification' => Tab::make('Pending verification')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->pendingVerification()),
            'balance_due' => Tab::make('Balance due')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->balanceDue()),
        ];
    }
}
