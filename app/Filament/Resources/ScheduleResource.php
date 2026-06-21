<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksCatalogPermissions;
use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Schedule;
use App\Support\Filament\CatalogOptionsCache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScheduleResource extends Resource
{
    use ChecksCatalogPermissions;

    protected static ?string $model = Schedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Schedules';

    protected static ?int $navigationSort = 30;

    protected static bool $shouldRegisterNavigation = true;

    protected static function catalogResourceKey(): string
    {
        return 'schedules';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Batch / Schedule')
                ->schema([
                    Forms\Components\Select::make('program_id')
                        ->label('Program')
                        ->options(fn (): array => CatalogOptionsCache::programOptions())
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('label')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Human-readable batch text, e.g. “August 2026” or “July–Nov 2026 | Sat & Sun …”.'),

                    Forms\Components\TextInput::make('mode')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\DatePicker::make('start_date')
                        ->nullable(),

                    Forms\Components\DatePicker::make('end_date')
                        ->nullable(),

                    Forms\Components\TextInput::make('slots')
                        ->numeric()
                        ->nullable()
                        ->minValue(0),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('program.name')
                    ->label('Program')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('mode')
                    ->searchable()
                    ->toggleable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('slots')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('enrollments_count')
                    ->counts('enrollmentItems')
                    ->label('# Enrollments')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Schedule $record): array {
                        if (($record->enrollment_items_count ?? 0) > 0) {
                            $data['_has_enrollments'] = true;
                        }

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Schedule $record): bool => (int) ($record->enrollment_items_count ?? -1) === 0),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => false),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('program')->withCount('enrollmentItems'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }
}
