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
use App\Support\PermissionCodes;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Enrollment';

    protected static ?string $navigationLabel = 'Enrollment';

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

    public static function canViewAny(): bool
    {
        return Auth::check();
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Grid::make(['default' => 1, 'lg' => 2])->schema([
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
                        ])->columns(['default' => 1, 'md' => 3]),

                    Infolists\Components\Section::make('Home Address')
                        ->description('Primary address details for records and communications.')
                        ->icon('heroicon-o-map-pin')
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_HOME_ADDRESS))
                        ->schema([
                            Infolists\Components\TextEntry::make('addr_street')->label('Street')->columnSpanFull()->weight('semibold'),
                            Infolists\Components\TextEntry::make('addr_city')->label('City')->weight('semibold'),
                            Infolists\Components\TextEntry::make('addr_province')->label('Province')->weight('semibold'),
                            Infolists\Components\TextEntry::make('addr_zip')->label('Zip Code')->weight('semibold'),
                        ])->columns(['default' => 1, 'md' => 3]),
                ])->columnSpan(1),

                Infolists\Components\Group::make([
                    Infolists\Components\Section::make('Plan & checkout')
                        ->description('Purchased item, chosen plan, and first checkout summary.')
                        ->icon('heroicon-o-credit-card')
                        ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)
                            || static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_FINANCIAL))
                        ->schema([
                            Infolists\Components\TextEntry::make('status')
                                ->badge()
                                ->color(fn ($state): string|array => EnrollmentStatus::tryFromMixed($state)?->filamentColor() ?? 'gray')
                                ->formatStateUsing(function ($state, Enrollment $record): string {
                                    $enum = EnrollmentStatus::tryFromMixed($state);

                                    if ($enum?->value === EnrollmentStatus::PENDING->value) {
                                        $hasSubmittedInitialBankTransfer = Payment::query()
                                            ->where('enrollment_id', $record->getKey())
                                            ->where('purpose', Payment::PURPOSE_INITIAL)
                                            ->where('payment_method', 'bank_transfer')
                                            ->where('status', 'submitted')
                                            ->exists();

                                        if ($hasSubmittedInitialBankTransfer) {
                                            return 'Pending verification';
                                        }
                                    }

                                    return $enum?->label() ?? strtoupper((string) $state);
                                })
                                ->columnSpanFull()
                                ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                            Infolists\Components\TextEntry::make('reference_number')
                                ->label('Reference #')
                                ->fontFamily('mono')
                                ->copyable()
                                ->weight('bold')
                                ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                            Infolists\Components\TextEntry::make('purchasable_name_snapshot')
                                ->label('Purchased item')
                                ->weight('bold')
                                ->color('primary')
                                ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_DETAIL_PLAN_CHECKOUT)),
                            Infolists\Components\TextEntry::make('batch_label')
                                ->label('Batch (first item)')
                                ->getStateUsing(function (Enrollment $record): ?string {
                                    $item = $record->items()->with('schedule')->orderBy('id')->first();

                                    return $item?->schedule?->label;
                                })
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
                        ])->columns(['default' => 1, 'md' => 3]),

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
                                ->color('danger')
                                ->columnSpan(['md' => 2]),
                        ])->columns(['default' => 1, 'md' => 3]),
                ])->columnSpan(1),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reference #')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->icon('heroicon-m-hashtag')
                    ->color('primary')
                    ->alignment(Alignment::Start),

                Tables\Columns\TextColumn::make('student_name')
                    ->label('Student')
                    ->getStateUsing(fn ($record) => "{$record->first_name} {$record->surname}")
                    ->description(fn ($record) => $record->email)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('first_name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->weight('bold')
                    ->alignment(Alignment::Start),

                Tables\Columns\TextColumn::make('purchasable_name_snapshot')
                    ->label('Program')
                    ->description(fn ($record) => match ($record->payment_type) {
                        'full' => 'Full payment',
                        'downpayment' => 'Downpayment',
                        default => ucfirst((string) $record->payment_type),
                    })
                    ->searchable()
                    ->wrap()
                    ->alignment(Alignment::Start),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('First payment')
                    ->tooltip(function (Enrollment $record): string {
                        $bal = (int) $record->computed_balance_tuition_due;
                        $first = 'First Paymongo checkout when they enrolled. Tuition line plus one convenience fee. Not the same as remaining tuition.';
                        if ($bal > 0) {
                            return $first.' Remaining tuition: ₱'.number_format($bal).'.';
                        }

                        return $first.' No tuition balance left.';
                    })
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::Start)
                    ->weight('bold')
                    ->visible(fn (): bool => static::viewerCan(PermissionCodes::ENROLLMENT_LIST_FIRST_PAYMENT)),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->alignment(Alignment::Start)
                    ->color(fn ($state): string|array => EnrollmentStatus::tryFromMixed($state)?->filamentColor() ?? 'gray')
                    ->formatStateUsing(function ($state, Enrollment $record): string {
                        $enum = EnrollmentStatus::tryFromMixed($state);

                        if ($enum?->value === EnrollmentStatus::PENDING->value) {
                            $hasSubmittedInitialBankTransfer = Payment::query()
                                ->where('enrollment_id', $record->getKey())
                                ->where('purpose', Payment::PURPOSE_INITIAL)
                                ->where('payment_method', 'bank_transfer')
                                ->where('status', 'submitted')
                                ->exists();

                            if ($hasSubmittedInitialBankTransfer) {
                                return 'Pending verification';
                            }
                        }

                        return $enum?->label() ?? strtoupper((string) $state);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enrolled')
                    ->dateTime('M j, Y', config('app.display_timezone'))
                    ->tooltip(fn (Enrollment $record): string => $record->created_at
                        ->timezone(config('app.display_timezone'))
                        ->diffForHumans())
                    ->sortable()
                    ->alignment(Alignment::Start),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
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
                    ->label('Purchased')
                    ->options(function (): array {
                        $packages = Package::query()
                            ->orderBy('sort_order')
                            ->pluck('name', 'id')
                            ->mapWithKeys(fn ($name, $id) => ["package:{$id}" => "Package — {$name}"]);

                        $programs = Program::query()
                            ->orderBy('sort_order')
                            ->pluck('name', 'id')
                            ->mapWithKeys(fn ($name, $id) => ["program:{$id}" => "Program — {$name}"]);

                        return $packages->merge($programs)->all();
                    })
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
                    ->options(fn (): array => Program::query()
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all())
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
                        $hasSubmittedInitialBankTransfer = Payment::query()
                            ->where('enrollment_id', $record->getKey())
                            ->where('purpose', Payment::PURPOSE_INITIAL)
                            ->where('payment_method', 'bank_transfer')
                            ->where('status', 'submitted')
                            ->exists();

                        $needsInitial = (int) $record->amount_paid_tuition <= 0 && ! $hasSubmittedInitialBankTransfer;
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
