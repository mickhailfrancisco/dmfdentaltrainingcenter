<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ManualPaymentChannelResource\Pages;
use App\Models\ManualPaymentChannel;
use App\Models\User;
use App\Services\ManualPaymentChannelService;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Admin-only Filament resource for managing manual payment channel details.
 *
 * @author CKD
 *
 * @created 2026-06-21
 */
class ManualPaymentChannelResource extends Resource
{
    protected static ?string $model = ManualPaymentChannel::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Payment channels';

    protected static ?string $modelLabel = 'Payment channel';

    protected static ?string $pluralModelLabel = 'Payment channels';

    protected static ?int $navigationSort = 98;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderByRaw("CASE channel_code
                WHEN 'bdo' THEN 1
                WHEN 'bpi' THEN 2
                WHEN 'chinabank' THEN 3
                WHEN 'palawan_express' THEN 4
                ELSE 99
            END");
    }

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user?->isAdmin()) {
            Log::warning('Unauthorized access attempt to ManualPaymentChannelResource', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
            ]);

            return false;
        }

        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        $disk = app(ManualPaymentChannelService::class)->disk();
        $qrDirectory = app(ManualPaymentChannelService::class)->qrDirectory();
        $uploadVisibility = app(ManualPaymentChannelService::class)->uploadVisibility();

        return $form
            ->schema([
                Forms\Components\Section::make('Channel')
                    ->schema([
                        Forms\Components\TextInput::make('channel_code')
                            ->label('Channel code')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('display_name')
                            ->label('Display name')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn (?ManualPaymentChannel $record): bool => $record?->isBankTransfer() ?? true),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Bank account')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('account_name')
                                    ->label('Account name')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('account_number')
                                    ->label('Account number')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Forms\Components\Placeholder::make('current_qr_file')
                                    ->label('Current QR')
                                    ->content(function (?ManualPaymentChannel $record): HtmlString|string {
                                        if (! filled($record?->qr_path)) {
                                            return '';
                                        }

                                        $service = app(ManualPaymentChannelService::class);
                                        $qrUrl = $service->publicUrl($record->qr_path);
                                        $filename = basename((string) $record->qr_path);

                                        $html = '<div class="dmf-qr-current__body">';
                                        $html .= '<span class="dmf-qr-current__filename">'.e($filename).'</span>';

                                        if ($qrUrl !== null) {
                                            $html .= '<img src="'.e($qrUrl).'" alt="Current QR code" class="dmf-qr-current__thumb" loading="lazy" />';
                                        }

                                        $html .= '</div>';

                                        return new HtmlString($html);
                                    })
                                    ->extraAttributes(['class' => 'dmf-qr-current']),

                                Forms\Components\Actions::make([
                                    static::makePreviewQrFormAction(),
                                ])
                                    ->key('previewCurrentQrActions')
                                    ->visible(fn (?ManualPaymentChannel $record): bool => static::hasQrPreview($record))
                                    ->alignEnd()
                                    ->verticalAlignment(VerticalAlignment::Start),
                            ])
                            ->visible(fn (?ManualPaymentChannel $record): bool => filled($record?->qr_path))
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('qr_path')
                            ->label(fn (?ManualPaymentChannel $record): string => filled($record?->qr_path)
                                ? 'Replace QR image'
                                : 'QR image')
                            ->helperText('JPG or PNG, max 5 MB. Upload a new file to replace the current QR.')
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->image()
                            ->imagePreviewHeight('120')
                            ->placeholder('Drag & drop your QR image here, or click to browse')
                            ->disk($disk)
                            ->directory($qrDirectory)
                            ->visibility($uploadVisibility)
                            ->maxSize(5120)
                            ->moveFiles()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (?ManualPaymentChannel $record): bool => $record?->isBankTransfer() ?? false),

                Forms\Components\Section::make('Remittance details')
                    ->schema([
                        Forms\Components\TextInput::make('receiver_name')
                            ->label('Receiver name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_number')
                            ->label('Contact number')
                            ->required()
                            ->maxLength(32),
                    ])
                    ->visible(fn (?ManualPaymentChannel $record): bool => $record?->isRemittance() ?? false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Channel')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ManualPaymentChannel::TYPE_BANK_TRANSFER => 'Bank transfer',
                        ManualPaymentChannel::TYPE_REMITTANCE => 'Remittance',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('account_summary')
                    ->label('Account / receiver')
                    ->getStateUsing(function (ManualPaymentChannel $record): string {
                        if ($record->isRemittance()) {
                            return trim((string) $record->receiver_name) !== ''
                                ? (string) $record->receiver_name
                                : '—';
                        }

                        return trim(((string) $record->account_name).' · '.((string) $record->account_number));
                    }),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                TableAction::make('previewQr')
                    ->iconButton()
                    ->icon('heroicon-o-qr-code')
                    ->tooltip('Preview QR code')
                    ->visible(fn (ManualPaymentChannel $record): bool => static::hasQrPreview($record))
                    ->modalHeading(fn (ManualPaymentChannel $record): string => 'Scan QR code — '.((string) $record->display_name))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('2xl')
                    ->modalContent(fn (ManualPaymentChannel $record): View => static::qrPreviewModalView($record)),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManualPaymentChannels::route('/'),
            'edit' => Pages\EditManualPaymentChannel::route('/{record}/edit'),
        ];
    }

    public static function hasQrPreview(?ManualPaymentChannel $record): bool
    {
        if (! $record instanceof ManualPaymentChannel || ! $record->isBankTransfer()) {
            return false;
        }

        return app(ManualPaymentChannelService::class)->publicUrl($record->qr_path) !== null;
    }

    /**
     * Legacy public paths (and missing disk objects) break Filament's upload preview UI.
     * Show them via placeholder + modal instead of hydrating the FileUpload widget.
     */
    public static function shouldDeferQrPathToUploadWidget(ManualPaymentChannel $record): bool
    {
        $path = $record->qr_path;

        if (blank($path)) {
            return false;
        }

        $legacyPrefix = (string) config('manual-payment.legacy_public_prefix', 'images/banks/');

        if (str_starts_with((string) $path, $legacyPrefix)) {
            return true;
        }

        $disk = app(ManualPaymentChannelService::class)->disk();

        try {
            return ! Storage::disk($disk)->exists((string) $path);
        } catch (\Throwable) {
            return true;
        }
    }

    public static function makePreviewQrFormAction(): FormAction
    {
        return FormAction::make('previewCurrentQr')
            ->label('Preview QR code')
            ->icon('heroicon-o-qr-code')
            ->button()
            ->outlined()
            ->color('primary')
            ->size(ActionSize::Small)
            ->visible(fn (?ManualPaymentChannel $record): bool => static::hasQrPreview($record))
            ->modalHeading(function (FormAction $action): string {
                $record = static::resolvePreviewRecord($action);

                return 'Scan QR code — '.((string) ($record?->display_name ?? 'Payment channel'));
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('2xl')
            ->modalContent(fn (FormAction $action): View => static::qrPreviewModalView(
                static::resolvePreviewRecord($action),
            ));
    }

    public static function resolvePreviewRecord(FormAction $action): ?ManualPaymentChannel
    {
        $record = $action->getComponent()->getRecord();

        if ($record instanceof ManualPaymentChannel) {
            return $record;
        }

        $livewireRecord = $action->getLivewire()->getRecord();

        return $livewireRecord instanceof ManualPaymentChannel ? $livewireRecord : null;
    }

    public static function qrPreviewModalView(?ManualPaymentChannel $record): View
    {
        $service = app(ManualPaymentChannelService::class);

        return view('filament.modals.payment-channel-qr', [
            'qrUrl' => $service->publicUrl($record?->qr_path),
            'channelName' => $record?->display_name,
            'accountName' => $record?->account_name,
            'accountNumber' => $record?->account_number,
        ]);
    }
}
