<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksCatalogPermissions;
use App\Filament\Resources\SchoolYearResource\Pages;
use App\Models\SchoolYear;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SchoolYearResource extends Resource
{
    use ChecksCatalogPermissions;

    protected static ?string $model = SchoolYear::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'School Years';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = true;

    protected static function catalogResourceKey(): string
    {
        return 'school_years';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('School Year')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('e.g. "SY 2025–2026"'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\DatePicker::make('start_date')
                        ->required(),

                    Forms\Components\DatePicker::make('end_date')
                        ->required()
                        ->after('start_date'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('start_date')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('schedules_count')
                    ->counts('schedules')
                    ->label('# Schedules')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated')
                    ->sortable(),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (SchoolYear $record): bool => $record->schedules_count === 0),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => false),
            ])
            ->modifyQueryUsing(fn ($query) => $query->withCount('schedules'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchoolYears::route('/'),
            'create' => Pages\CreateSchoolYear::route('/create'),
            'edit' => Pages\EditSchoolYear::route('/{record}/edit'),
        ];
    }
}
