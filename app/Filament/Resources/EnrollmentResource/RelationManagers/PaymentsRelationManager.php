<?php

declare(strict_types=1);

namespace App\Filament\Resources\EnrollmentResource\RelationManagers;

use App\Filament\Resources\EnrollmentResource;
use App\Services\BankTransferService;
use App\Support\PermissionCodes;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        foreach (PermissionCodes::enrollmentPaymentsTabAccessCodes() as $code) {
            if ($user->hasPermission($code)) {
                return true;
            }
        }

        return false;
    }

    public static function canCreateForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'bankTransferSubmission' => fn ($submissionQuery) => $submissionQuery->with('files'),
            ]))
            ->recordTitleAttribute('purpose')
            ->columns([
                Tables\Columns\TextColumn::make('purpose')
                    ->label('Purpose')
                    ->badge()
                    ->size(TextColumnSize::Small)
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'initial' => 'First payment',
                        'balance' => 'Balance',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'initial' => 'warning',
                        'balance' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->size(TextColumnSize::Small)
                    ->placeholder('—')
                    ->html()
                    ->formatStateUsing(function (?string $state, $record): string {
                        $methodLabel = match ((string) ($state ?? '')) {
                            'bank_transfer' => 'Bank transfer',
                            'card' => 'Card',
                            default => (string) ($state ?? '—'),
                        };

                        if ((string) ($state ?? '') !== 'bank_transfer') {
                            return e($methodLabel);
                        }

                        $channel = (string) ($record->bankTransferSubmission?->channel_code ?? '');
                        $channelLabel = match ($channel) {
                            'bdo' => 'BDO',
                            'bpi' => 'BPI',
                            'chinabank' => 'ChinaBank',
                            'palawan_express' => 'Palawan Express',
                            default => '',
                        };

                        $subline = $channelLabel !== '' ? e($channelLabel) : '—';

                        return sprintf(
                            '<div class="leading-tight">%s<div class="text-xs text-gray-500 mt-0.5">%s</div></div>',
                            e($methodLabel),
                            $subline,
                        );
                    }),
                Tables\Columns\TextColumn::make('bankTransferSubmission.reference_number')
                    ->label('Ref #')
                    ->placeholder('—')
                    ->size(TextColumnSize::Small),
                Tables\Columns\TextColumn::make('tuition_amount')
                    ->label('Amount')
                    ->size(TextColumnSize::Small)
                    ->money('PHP'),
                Tables\Columns\TextColumn::make('status')
                    ->size(TextColumnSize::Small)
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid at')
                    ->size(TextColumnSize::Small)
                    ->html()
                    ->formatStateUsing(fn ($state): string => self::formatTimestampCell($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('bankTransferSubmission.submitted_at')
                    ->label('Submitted at')
                    ->size(TextColumnSize::Small)
                    ->html()
                    ->formatStateUsing(fn ($state): string => self::formatTimestampCell($state)),
                Tables\Columns\TextColumn::make('bankTransferSubmission.verified_at')
                    ->label('Verified at')
                    ->size(TextColumnSize::Small)
                    ->html()
                    ->formatStateUsing(fn ($state): string => self::formatTimestampCell($state)),
            ])
            ->actions([
                Tables\Actions\Action::make('viewProof')
                    ->iconButton()
                    ->icon('heroicon-m-eye')
                    ->tooltip('View proof of payment')
                    ->visible(function ($record): bool {
                        return (string) ($record->payment_method ?? '') === 'bank_transfer'
                            && $record->bankTransferSubmission !== null
                            && ! empty($record->bankTransferSubmission->proof_path);
                    })
                    ->modalHeading('Proof of payment')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('7xl')
                    ->modalContent(function ($record) {
                        $url1 = route('admin.bank-transfer-submissions.proof', [
                            'submission' => $record->bankTransferSubmission->getKey(),
                            'slot' => 'photo_1',
                        ]);
                        $url2 = route('admin.bank-transfer-submissions.proof', [
                            'submission' => $record->bankTransferSubmission->getKey(),
                            'slot' => 'photo_2',
                        ]);

                        return view('filament.modals.bank-transfer-proof', [
                            'proofUrl1' => $url1,
                            'proofUrl2' => $url2,
                            'referenceNumber' => (string) ($this->getOwnerRecord()->reference_number ?? ''),
                            'hasPhoto2' => (bool) optional($record->bankTransferSubmission)->files?->contains(fn ($f) => (string) ($f->slot ?? '') === 'photo_2'),
                        ]);
                    }),
                Tables\Actions\Action::make('downloadProof')
                    ->iconButton()
                    ->icon('heroicon-m-arrow-down-tray')
                    ->tooltip('Download proof of payment')
                    ->visible(function ($record): bool {
                        return (string) ($record->payment_method ?? '') === 'bank_transfer'
                            && $record->bankTransferSubmission !== null
                            && ! empty($record->bankTransferSubmission->proof_path);
                    })
                    ->action(function ($record) {
                        return Storage::disk('local')->download($record->bankTransferSubmission->proof_path);
                    }),
                Tables\Actions\Action::make('verifyBankTransfer')
                    ->iconButton()
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->tooltip('Mark as paid')
                    ->visible(function ($record): bool {
                        $user = Auth::user();
                        if (! $user) {
                            return false;
                        }

                        if (! $user->isAdmin() && ! $user->hasPermission(PermissionCodes::ENROLLMENT_ACTION_VERIFY_BANK_TRANSFER)) {
                            return false;
                        }

                        if ((string) ($record->payment_method ?? '') !== 'bank_transfer') {
                            return false;
                        }

                        if ($record->status === 'paid') {
                            return false;
                        }

                        return $record->bankTransferSubmission !== null;
                    })
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $user = Auth::user();

                        if (! $user) {
                            return;
                        }

                        app(BankTransferService::class)->verifyPayment($record, $user);

                        Notification::make()
                            ->title('Payment marked as paid')
                            ->success()
                            ->send();
                    })
                    ->after(function (PaymentsRelationManager $livewire): void {
                        // Livewire POST requests use /livewire/update — never redirect to request()->fullUrl().
                        $livewire->redirect(
                            EnrollmentResource::getUrl('view', ['record' => $livewire->getOwnerRecord()]),
                            navigate: false,
                        );
                    }),
            ])
            ->defaultSort('paid_at', 'desc')
            ->searchable(false)
            ->filters([])
            ->paginated([10, 25, 50]);
    }

    private static function formatTimestampCell(mixed $state): string
    {
        if (blank($state)) {
            return '—';
        }

        $timestamp = Carbon::parse($state)->timezone(config('app.display_timezone'));

        return sprintf(
            '<div class="leading-tight whitespace-nowrap"><div>%s</div><div class="text-xs text-gray-500">%s</div></div>',
            e($timestamp->format('M j, Y')),
            e($timestamp->format('g:i A')),
        );
    }
}
