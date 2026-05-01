<?php

declare(strict_types=1);

namespace App\Filament\Resources\EnrollmentResource\RelationManagers;

use App\Services\BankTransferService;
use App\Support\PermissionCodes;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
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

        return $user->hasPermission(PermissionCodes::ENROLLMENT_RELATION_PAYMENTS);
    }

    public static function canCreateForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['enrollment', 'bankTransferSubmission']))
            ->recordTitleAttribute('purpose')
            ->columns([
                Tables\Columns\TextColumn::make('enrollment.reference_number')
                    ->label('Reference #')
                    ->fontFamily('mono')
                    ->copyable(),
                Tables\Columns\TextColumn::make('purpose')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'initial' => 'First checkout (enrollment)',
                        'balance' => 'Balance tuition',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'initial' => 'warning',
                        'balance' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_method')
                    ->placeholder('—')
                    ->html()
                    ->formatStateUsing(function (?string $state, $record): string {
                        $method = (string) ($state ?? '—');

                        if ($method !== 'bank_transfer') {
                            return e($method);
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
                            e($method),
                            $subline,
                        );
                    }),
                Tables\Columns\TextColumn::make('bankTransferSubmission.reference_number')
                    ->label('Transfer Ref')
                    ->placeholder('—')
                    ->toggleable()
                    ->visible(fn ($record): bool => (string) ($record->payment_method ?? '') === 'bank_transfer'),
                Tables\Columns\TextColumn::make('bankTransferSubmission.channel_code')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'bdo' => 'BDO',
                        'bpi' => 'BPI',
                        'chinabank' => 'ChinaBank',
                        'palawan_express' => 'Palawan Express',
                        default => $state ?: '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'palawan_express' => 'info',
                        'bdo', 'bpi', 'chinabank' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable()
                    ->visible(fn ($record): bool => (string) ($record->payment_method ?? '') === 'bank_transfer'),
                Tables\Columns\TextColumn::make('tuition_amount')
                    ->label('Tuition (PHP)')
                    ->money('PHP'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime(null, config('app.display_timezone'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('bankTransferSubmission.submitted_at')
                    ->label('Submitted at')
                    ->dateTime(null, config('app.display_timezone'))
                    ->toggleable()
                    ->visible(fn ($record): bool => (string) ($record->payment_method ?? '') === 'bank_transfer'),
                Tables\Columns\TextColumn::make('bankTransferSubmission.verified_at')
                    ->label('Verified at')
                    ->dateTime(null, config('app.display_timezone'))
                    ->toggleable()
                    ->visible(fn ($record): bool => (string) ($record->payment_method ?? '') === 'bank_transfer'),
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
                        $path = (string) $record->bankTransferSubmission->proof_path;
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
                            'referenceNumber' => (string) ($record->enrollment?->reference_number ?? ''),
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
                    ->after(function ($livewire): void {
                        // Force the parent page (infolist/cards) to reflect new ledger + status immediately.
                        if (method_exists($livewire, 'js')) {
                            $livewire->js('window.location.reload()');
                        }
                    }),
            ])
            ->defaultSort('paid_at', 'desc')
            ->paginated(false);
    }
}
