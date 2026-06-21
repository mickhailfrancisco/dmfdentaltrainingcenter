<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksCatalogPermissions;
use App\Filament\Resources\ProgramResource\Pages;
use App\Models\Program;
use App\Support\Filament\CatalogOptionsCache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProgramResource extends Resource
{
    use ChecksCatalogPermissions;

    protected static ?string $model = Program::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Programs';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = true;

    protected static function catalogResourceKey(): string
    {
        return 'programs';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Program')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->label('Program code')
                        ->helperText('Short unique ID for enrollment links (e.g. hybrid-intensive). Use lowercase letters, numbers, and hyphens. Students see the program name, not this code.')
                        ->placeholder('e.g. hybrid-intensive')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('category_id')
                        ->label('Category')
                        ->options(fn (): array => CatalogOptionsCache::categoryOptions())
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('tag')
                        ->maxLength(255)
                        ->nullable(),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])->columns(2),

            Forms\Components\Section::make('Pricing')
                ->schema([
                    Forms\Components\TextInput::make('price_full')
                        ->label('Full Price')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('₱')
                        ->columnSpan(1),

                    Forms\Components\Fieldset::make('1st Early Bird')
                        ->schema([
                            Forms\Components\TextInput::make('price_early')
                                ->label('Price')
                                ->numeric()
                                ->nullable()
                                ->minValue(0)
                                ->prefix('₱')
                                ->live(onBlur: true)
                                ->required(fn (Get $get): bool => filled($get('early_deadline'))),

                            Forms\Components\DatePicker::make('early_deadline')
                                ->label('Deadline')
                                ->helperText('After this date, the 2nd early price (or full price) applies.')
                                ->nullable()
                                ->default(null)
                                ->required(fn (Get $get): bool => filled($get('price_early'))),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),

                    Forms\Components\Fieldset::make('2nd Early Bird')
                        ->schema([
                            Forms\Components\TextInput::make('price_early_2')
                                ->label('Price')
                                ->numeric()
                                ->nullable()
                                ->minValue(0)
                                ->prefix('₱')
                                ->live(onBlur: true)
                                ->required(fn (Get $get): bool => filled($get('early_deadline_2'))),

                            Forms\Components\DatePicker::make('early_deadline_2')
                                ->label('Deadline')
                                ->helperText('After this date, the full price applies.')
                                ->nullable()
                                ->default(null)
                                ->required(fn (Get $get): bool => filled($get('price_early_2'))),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->hidden(fn (Get $get): bool => blank($get('price_early'))),

                    Forms\Components\TextInput::make('early_bird_label')
                        ->label('Early Bird Label')
                        ->nullable()
                        ->placeholder('e.g. "Early Bird – Save ₱2,000!"')
                        ->columnSpanFull()
                        ->hidden(fn (Get $get): bool => blank($get('price_early'))),
                ])->columns(2),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('categoryModel'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Program code')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('category_label')
                    ->label('Category')
                    ->getStateUsing(fn (Program $record) => $record->category_label)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('category', $direction)->orderBy('name', 'asc');
                    }),

                Tables\Columns\TextColumn::make('price_full')
                    ->label('Full')
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::End),

                Tables\Columns\TextColumn::make('price_early')
                    ->label('1st Early')
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::End)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('price_early_2')
                    ->label('2nd Early')
                    ->money('PHP')
                    ->sortable()
                    ->alignment(Alignment::End)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->authorize(fn (): bool => static::currentUserCanCatalogAction('delete')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->authorize(fn (): bool => static::currentUserCanCatalogAction('delete')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrograms::route('/'),
            'create' => Pages\CreateProgram::route('/create'),
            'edit' => Pages\EditProgram::route('/{record}/edit'),
        ];
    }
}
