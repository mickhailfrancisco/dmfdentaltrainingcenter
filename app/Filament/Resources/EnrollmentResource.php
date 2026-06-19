<?php

namespace App\Filament\Resources;

use App\Enums\EnrollmentStatus;
use App\Filament\Resources\EnrollmentResource\Pages;
use App\Filament\Resources\EnrollmentResource\RelationManagers;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use App\Support\Filament\CatalogOptionsCache;
use App\Support\PermissionCodes;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Enrollment';

    protected static ?string $navigationLabel = 'Enrollments';

    protected static ?int $navigationSort = 1;

    /**
     * Whether the current user may see a granular enrollment permission (admins always may).
     *
     * @author CKD
     *
     * @created 2026-04-25
     */
    private static function viewerCan(string $permissionCode): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->hasPermission($permissionCode);
    }

    public static function currentUserCanExportEnrollments(): bool
    {
        return self::viewerCan(PermissionCodes::ENROLLMENT_LIST_EXPORT);
    }

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        foreach (PermissionCodes::enrollmentListAccessPermissionCodes() as $code) {
            if ($user->hasPermission($code)) {
                return true;
            }
        }

        return false;
    }

    public static function canView(Model $record): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isAssistant()) {
            return false;
        }

        foreach (PermissionCodes::enrollmentRecordViewPermissionCodes() as $code) {
            if ($user->hasPermission($code)) {
                return true;
            }
        }

        return false;
    }

    // Disable create / edit / delete for all roles — enrollments come from the public form.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getBreadcrumb(): string
    {
        return 'Enrollments';
    }

    public static function hasRecordTitle(): bool
    {
        return true;
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Enrollment) {
            return 'Student';
        }

        $name = trim("{$record->first_name} {$record->surname}");

        return $name !== '' ? $name : 'Student';
    }

    /**
     * Admin-facing status label, including pending bank-transfer verification.
     */
    public static function formatStatusLabel(Enrollment $record): string
    {
        $enum = EnrollmentStatus::tryFromMixed($record->status);

        if ($enum?->value === EnrollmentStatus::PENDING->value) {
            if (static::hasSubmittedInitialBankTransfer($record)) {
                return 'Pending verification';
            }
        }

        return $enum?->label() ?? strtoupper((string) $record->status);
    }

    /**
     * Compact status label for the enrollment list table (full label stays in tooltip/export).
     */
    public static function formatTableStatusLabel(Enrollment $record): string
    {
        $enum = EnrollmentStatus::tryFromMixed($record->status);

        if ($enum?->value === EnrollmentStatus::PENDING->value) {
            if (static::hasSubmittedInitialBankTransfer($record)) {
                return 'Verify transfer';
            }

            return 'Awaiting pay';
        }

        return match ($enum) {
            EnrollmentStatus::PARTIALLY_PAID => 'DP paid',
            EnrollmentStatus::CONFIRMED => 'Fully paid',
            EnrollmentStatus::CANCELLED => 'Cancelled',
            EnrollmentStatus::FAILED => 'Failed',
            default => static::formatStatusLabel($record),
        };
    }

    /**
     * Whether the enrollment has a submitted initial bank-transfer payment.
     *
     * Uses the eager-loaded `has_submitted_initial_bank_transfer` attribute when present
     * (set on the index table query) to avoid N+1 lookups.
     */
    public static function hasSubmittedInitialBankTransfer(Enrollment $record): bool
    {
        if (array_key_exists('has_submitted_initial_bank_transfer', $record->getAttributes())) {
            return (bool) $record->getAttribute('has_submitted_initial_bank_transfer');
        }

        return Payment::query()
            ->where('enrollment_id', $record->getKey())
            ->where('purpose', Payment::PURPOSE_INITIAL)
            ->where('payment_method', 'bank_transfer')
            ->where('status', 'submitted')
            ->exists();
    }

    /**
     * Eager-load relations used on the enrollment view page and bank-transfer exists flag.
     */
    public static function applyViewPageQuery(Builder $query): Builder
    {
        return $query
            ->withExists([
                'payments as has_submitted_initial_bank_transfer' => function (Builder $paymentQuery): void {
                    $paymentQuery
                        ->where('purpose', Payment::PURPOSE_INITIAL)
                        ->where('payment_method', 'bank_transfer')
                        ->where('status', 'submitted');
                },
            ])
            ->with([
                'items' => fn ($itemsQuery) => $itemsQuery->orderBy('id')->with('schedule'),
            ]);
    }

    /**
     * Batch label from the first enrollment line item (requires items.schedule when lazy loading is enforced).
     */
    public static function firstItemBatchLabel(Enrollment $record): ?string
    {
        $record->loadMissing([
            'items' => fn ($itemsQuery) => $itemsQuery->orderBy('id')->with('schedule'),
        ]);

        return $record->items->first()?->schedule?->label;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Payment & enrollment status')
                ->description('Reference number, program, amount paid, remaining balance, and enrollment status.')
                ->icon('heroicon-o-clipboard-document-list')
                ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)
                    || static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_FINANCIAL)
                    || static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_TUITION_BALANCE))
                ->schema([
                    Infolists\Components\TextEntry::make('reference_number')
                        ->label('Reference #')
                        ->fontFamily('mono')
                        ->copyable()
                        ->weight('bold')
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->color(fn ($state): string|array => EnrollmentStatus::tryFromMixed($state)?->filamentColor() ?? 'gray')
                        ->formatStateUsing(fn ($state, Enrollment $record): string => static::formatStatusLabel($record))
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                    Infolists\Components\TextEntry::make('purchasable_name_snapshot')
                        ->label('Program Enrolled')
                        ->weight('bold')
                        ->color('primary')
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                    Infolists\Components\TextEntry::make('total_amount')
                        ->label('First payment')
                        ->money('PHP')
                        ->weight('bold')
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_FINANCIAL)),
                    Infolists\Components\TextEntry::make('amount_paid_tuition')
                        ->label('Tuition paid')
                        ->money('PHP')
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_TUITION_BALANCE)),
                    Infolists\Components\TextEntry::make('computed_balance_tuition_due')
                        ->label('Remaining')
                        ->money('PHP')
                        ->weight('bold')
                        ->color('danger')
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_TUITION_BALANCE)),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Enrolled')
                        ->dateTime('M j, Y', config('app.display_timezone'))
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                ])
                ->columns(['default' => 2, 'md' => 3, 'xl' => 4])
                ->columnSpanFull(),

            Infolists\Components\Grid::make(['default' => 1, 'lg' => 2])
                ->extraAttributes(['class' => 'dmf-enrollment-detail-grid'])
                ->schema([
                    Infolists\Components\Group::make([
                        Infolists\Components\Section::make('Applicant Profile')
                            ->description('Personal information and contact details.')
                            ->icon('heroicon-o-user')
                            ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_APPLICANT_PROFILE))
                            ->schema([
                                Infolists\Components\TextEntry::make('full_name')
                                    ->label('Full Name')
                                    ->getStateUsing(fn ($record) => trim("{$record->first_name} {$record->middle_name} {$record->surname}"))
                                    ->weight('bold')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->columnSpanFull(),
                                Infolists\Components\TextEntry::make('birthday')
                                    ->date('F j, Y', config('app.display_timezone'))
                                    ->icon('heroicon-o-gift')
                                    ->weight('semibold'),
                                Infolists\Components\TextEntry::make('sex')
                                    ->icon('heroicon-o-users')
                                    ->weight('semibold'),
                                Infolists\Components\TextEntry::make('email')
                                    ->icon('heroicon-o-envelope')
                                    ->copyable()
                                    ->weight('semibold'),
                                Infolists\Components\TextEntry::make('phone')
                                    ->icon('heroicon-o-phone')
                                    ->copyable()
                                    ->weight('semibold'),
                                Infolists\Components\TextEntry::make('facebook_messenger_name')
                                    ->label('Facebook / Messenger Name')
                                    ->icon('heroicon-o-link')
                                    ->url(function (Enrollment $record): ?string {
                                        $url = Str::of((string) $record->facebook_messenger_url)->trim();

                                        return $url->isNotEmpty() ? (string) $url : null;
                                    })
                                    ->openUrlInNewTab()
                                    ->placeholder('—')
                                    ->columnSpanFull()
                                    ->weight('semibold'),
                            ])->columns(['default' => 1, 'md' => 2]),

                        Infolists\Components\Section::make('Academic Background')
                            ->description('School and board exam/taker information.')
                            ->icon('heroicon-o-academic-cap')
                            ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_ACADEMIC))
                            ->schema([
                                Infolists\Components\TextEntry::make('school')
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->columnSpanFull()
                                    ->weight('bold'),
                                Infolists\Components\TextEntry::make('year_level')->label('Year Level')->weight('semibold'),
                                Infolists\Components\TextEntry::make('year_graduated')->label('Year Graduated')->placeholder('—')->weight('semibold'),
                                Infolists\Components\TextEntry::make('taker_status')
                                    ->label('Taker Status')
                                    ->badge()
                                    ->color('info'),
                            ])->columns(['default' => 1, 'md' => 2]),

                        Infolists\Components\Section::make('Home Address')
                            ->description('Primary address details for records and communications.')
                            ->icon('heroicon-o-map-pin')
                            ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_HOME_ADDRESS))
                            ->schema([
                                Infolists\Components\TextEntry::make('addr_street')->label('Street')->columnSpanFull()->weight('semibold'),
                                Infolists\Components\TextEntry::make('addr_city')->label('City')->weight('semibold'),
                                Infolists\Components\TextEntry::make('addr_province')->label('Province')->weight('semibold'),
                                Infolists\Components\TextEntry::make('addr_zip')->label('Zip Code')->weight('semibold'),
                            ])->columns(['default' => 1, 'md' => 2]),
                    ])
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_APPLICANT_PROFILE)
                            || static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_ACADEMIC)
                            || static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_HOME_ADDRESS)),

                    Infolists\Components\Group::make([
                        Infolists\Components\Section::make('Plan & checkout')
                            ->description('Program enrolled, chosen plan, and first checkout summary.')
                            ->icon('heroicon-o-credit-card')
                            ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)
                                || static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_FINANCIAL))
                            ->schema([
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state): string|array => EnrollmentStatus::tryFromMixed($state)?->filamentColor() ?? 'gray')
                                    ->formatStateUsing(fn ($state, Enrollment $record): string => static::formatStatusLabel($record))
                                    ->columnSpanFull()
                                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                                Infolists\Components\TextEntry::make('reference_number')
                                    ->label('Reference #')
                                    ->fontFamily('mono')
                                    ->copyable()
                                    ->weight('bold')
                                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                                Infolists\Components\TextEntry::make('purchasable_name_snapshot')
                                    ->label('Program Enrolled')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                                Infolists\Components\TextEntry::make('batch_label')
                                    ->label('Batch (first item)')
                                    ->getStateUsing(fn (Enrollment $record): ?string => static::firstItemBatchLabel($record))
                                    ->placeholder('—')
                                    ->weight('semibold')
                                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                                Infolists\Components\TextEntry::make('payment_type')
                                    ->label('Plan')
                                    ->formatStateUsing(fn ($state) => $state === 'full' ? 'Full' : 'Downpayment')
                                    ->badge()
                                    ->color('gray')
                                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                                Infolists\Components\TextEntry::make('base_amount')
                                    ->label(fn (Enrollment $record): string => match ($record->payment_type) {
                                        'downpayment' => 'Downpayment (excl. convenience fee)',
                                        'full' => 'Program charge (excl. convenience fee)',
                                        default => 'Checkout amount (excl. convenience fee)',
                                    })
                                    ->money('PHP')
                                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_FINANCIAL)),
                                Infolists\Components\TextEntry::make('convenience_fee')
                                    ->label('Convenience fee')
                                    ->money('PHP')
                                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_FINANCIAL)),
                                Infolists\Components\TextEntry::make('total_amount')
                                    ->label('First payment')
                                    ->hintIcon(
                                        'heroicon-o-information-circle',
                                        'First Paymongo checkout at enrollment. Should match the two amounts above. Later tuition is under Tuition & balance.',
                                    )
                                    ->hintColor('gray')
                                    ->money('PHP')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->columnSpanFull()
                                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_FINANCIAL)),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Submitted')
                                    ->dateTime('M j, Y g:i A', config('app.display_timezone'))
                                    ->icon('heroicon-o-clock')
                                    ->columnSpanFull()
                                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                            ])->columns(['default' => 1, 'md' => 2]),

                        Infolists\Components\Section::make('Tuition & balance')
                            ->description('Tuition pricing snapshots, paid amount, and remaining balance.')
                            ->icon('heroicon-o-calculator')
                            ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_TUITION_BALANCE))
                            ->schema([
                                Infolists\Components\TextEntry::make('tuition_list_amount')
                                    ->label('Regular list price')
                                    ->hintIcon(
                                        'heroicon-o-information-circle',
                                        'Standard full tuition (published list price) after the early-bird window.',
                                    )
                                    ->hintColor('gray')
                                    ->money('PHP')
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('tuition_price_early')
                                    ->label('Early-bird price')
                                    ->hintIcon(
                                        'heroicon-o-information-circle',
                                        'Promotional tuition while the early-bird window is open.',
                                    )
                                    ->hintColor('gray')
                                    ->money('PHP')
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('tuition_early_deadline')
                                    ->label('Early-bird discount ends')
                                    ->hintIcon(
                                        'heroicon-o-information-circle',
                                        'Through this date (Asia/Manila), the system uses the early-bird tuition total to calculate the balance; starting the next day, it uses the regular list price. That is only which price tier applies—not a requirement that the student pays the full balance in one payment by this date.',
                                    )
                                    ->hintColor('gray')
                                    ->date('M j, Y', config('app.display_timezone'))
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('amount_paid_tuition')
                                    ->label('Tuition paid')
                                    ->money('PHP'),
                                Infolists\Components\TextEntry::make('computed_balance_tuition_due')
                                    ->label('Remaining')
                                    ->hintIcon(
                                        'heroicon-o-information-circle',
                                        'Outstanding tuition: early-bird total applies until the early-bird end date, then the regular list price; minus tuition paid to date. Convenience fees are per checkout.',
                                    )
                                    ->hintColor('gray')
                                    ->money('PHP')
                                    ->weight('bold')
                                    ->color('danger'),
                            ])->columns(['default' => 1, 'md' => 2]),
                    ])
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)
                            || static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_FINANCIAL)
                            || static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_TUITION_BALANCE)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->filtersFormWidth(MaxWidth::TwoExtraLarge)
            ->filtersFormColumns(2)
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query->withExists([
                    'payments as has_submitted_initial_bank_transfer' => function (Builder $paymentQuery): void {
                        $paymentQuery
                            ->where('purpose', Payment::PURPOSE_INITIAL)
                            ->where('payment_method', 'bank_transfer')
                            ->where('status', 'submitted');
                    },
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Ref #')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->size(TextColumnSize::Small)
                    ->extraHeaderAttributes(['style' => 'padding-inline: 1.5rem'])
                    ->extraCellAttributes(['style' => 'padding-inline: 1.5rem']),

                Tables\Columns\TextColumn::make('student_name')
                    ->label('Student')
                    ->getStateUsing(fn ($record) => "{$record->first_name} {$record->surname}")
                    ->description(fn ($record) => Str::limit((string) $record->email, 26))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('first_name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->lineClamp(1),

                Tables\Columns\TextColumn::make('purchasable_name_snapshot')
                    ->label('Program Enrolled')
                    ->tooltip(fn (Enrollment $record): string => trim(sprintf(
                        '%s · %s',
                        (string) $record->purchasable_name_snapshot,
                        match ($record->payment_type) {
                            'full' => 'Full payment',
                            'downpayment' => 'Downpayment',
                            default => ucfirst((string) $record->payment_type),
                        },
                    )))
                    ->searchable()
                    ->lineClamp(1)
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('1st pay')
                    ->tooltip('First Paymongo checkout when they enrolled. Tuition line plus one convenience fee.')
                    ->money('PHP')
                    ->sortable()
                    ->size(TextColumnSize::Small)
                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_LIST_FIRST_PAYMENT))
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('computed_balance_tuition_due')
                    ->label('Balance')
                    ->money('PHP')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('balance_tuition_due', $direction);
                    })
                    ->size(TextColumnSize::Small)
                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_TUITION_BALANCE))
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string|array => EnrollmentStatus::tryFromMixed($state)?->filamentColor() ?? 'gray')
                    ->formatStateUsing(fn ($state, Enrollment $record): string => static::formatTableStatusLabel($record))
                    ->tooltip(fn (Enrollment $record): string => static::formatStatusLabel($record))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enrolled')
                    ->dateTime('M j, y', config('app.display_timezone'))
                    ->tooltip(fn (Enrollment $record): string => $record->created_at
                        ->timezone(config('app.display_timezone'))
                        ->format('M j, Y g:i A'))
                    ->size(TextColumnSize::Small)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('enrolled_between')
                    ->label('Enrolled date')
                    ->form([
                        Grid::make(2)->schema([
                            DatePicker::make('from')
                                ->label('From')
                                ->placeholder('Enrolled from')
                                ->native(false),
                            DatePicker::make('until')
                                ->label('Until')
                                ->placeholder('Enrolled until')
                                ->native(false),
                        ]),
                    ])
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = Tables\Filters\Indicator::make('From '.(Carbon::parse($data['from'])->format('M j, Y')))
                                ->removeField('from');
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = Tables\Filters\Indicator::make('Until '.(Carbon::parse($data['until'])->format('M j, Y')))
                                ->removeField('until');
                        }

                        return $indicators;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $builder): Builder => $builder->whereDate('created_at', '>=', $data['from']),
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $builder): Builder => $builder->whereDate('created_at', '<=', $data['until']),
                            );
                    }),
                SelectFilter::make('status')
                    ->options(EnrollmentStatus::filterOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (is_string($value) && $value !== '') {
                            return $query->where('status', $value);
                        }

                        return $query;
                    }),
                SelectFilter::make('purchased_item')
                    ->label('Program enrolled')
                    ->options(fn (): array => CatalogOptionsCache::purchasedItemFilterOptions())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! is_string($value) || ! str_contains($value, ':')) {
                            return $query;
                        }

                        [$type, $id] = explode(':', $value, 2);
                        if (! ctype_digit((string) $id)) {
                            return $query;
                        }

                        $class = match ($type) {
                            'package' => Package::class,
                            'program' => Program::class,
                            default => null,
                        };
                        if (! $class) {
                            return $query;
                        }

                        return $query
                            ->where('purchasable_type', $class)
                            ->where('purchasable_id', (int) $id);
                    }),
                SelectFilter::make('included_program_id')
                    ->label('Included Program')
                    ->options(fn (): array => CatalogOptionsCache::programOptions())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! is_numeric($value)) {
                            return $query;
                        }

                        return $query->whereExists(function ($sub) use ($value) {
                            $sub->selectRaw('1')
                                ->from('enrollment_items')
                                ->whereColumn('enrollment_items.enrollment_id', 'enrollments.id')
                                ->where('enrollment_items.program_id', (int) $value);
                        });
                    }),
                SelectFilter::make('payment_type')
                    ->label('Payment Type')
                    ->options([
                        'full' => 'Full Payment',
                        'downpayment' => 'Downpayment',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('copyPaymentLink')
                    ->label('Copy payment link')
                    ->icon('heroicon-m-clipboard-document')
                    ->iconButton()
                    ->color('warning')
                    ->tooltip(function (Enrollment $record): string {
                        if ((int) $record->amount_paid_tuition <= 0) {
                            return 'Copy payment link for student to complete initial checkout';
                        }

                        return 'Copy payment link for student to pay remaining tuition';
                    })
                    ->visible(function (Enrollment $record): bool {
                        $needsInitial = (int) $record->amount_paid_tuition <= 0
                            && ! static::hasSubmittedInitialBankTransfer($record);
                        $needsBalance = $record->payment_type === 'downpayment'
                            && (int) $record->amount_paid_tuition > 0
                            && $record->computed_balance_tuition_due > 0;

                        if (! ($needsInitial || $needsBalance)) {
                            return false;
                        }

                        if ($needsInitial && static::viewerCan(PermissionCodes::ENROLLMENT_ACTION_COPY_PAY_BALANCE_LINK)) {
                            return true;
                        }

                        if ($needsBalance && static::viewerCan(PermissionCodes::ENROLLMENT_ACTION_COPY_PAY_BALANCE_LINK)) {
                            return true;
                        }

                        return false;
                    })
                    ->action(function (Enrollment $record, $livewire): void {
                        $needsInitial = (int) $record->amount_paid_tuition <= 0;
                        $purpose = $needsInitial ? Payment::PURPOSE_INITIAL : Payment::PURPOSE_BALANCE;

                        // Always send the student to a method selection page.
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

                        $livewire->js('window.navigator.clipboard.writeText('.Js::from($url).')');

                        Notification::make()
                            ->title('Payment link copied')
                            ->body('Paste it into SMS, Messenger, Viber, or email for the student.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->tooltip('View Enrollment Record'),
            ])
            ->bulkActions([]);  // No bulk delete
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnrollments::route('/'),
            'view' => Pages\ViewEnrollment::route('/{record}'),
        ];
    }
}
