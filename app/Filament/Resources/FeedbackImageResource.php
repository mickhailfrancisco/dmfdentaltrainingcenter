<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FeedbackImageResource\Pages;
use App\Models\FeedbackImage;
use App\Services\LandingMediaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FeedbackImageResource extends Resource
{
    protected static ?string $model = FeedbackImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Feedback images';

    protected static ?string $modelLabel = 'Feedback image';

    protected static ?string $pluralModelLabel = 'Feedback images';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        $service = app(LandingMediaService::class);

        return $form->schema([
            Forms\Components\FileUpload::make('image_path')
                ->label('Image')
                ->image()
                ->imagePreviewHeight('150')
                ->disk($service->disk())
                ->directory($service->feedbackDirectory())
                ->visibility('public')
                ->maxSize(5120)
                ->required()
                ->moveFiles()
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $service = app(LandingMediaService::class);

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk($service->disk())
                    ->visibility('public')
                    ->square(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\Action::make('toggleFeatured')
                    ->label(fn (FeedbackImage $record): string => $record->is_featured ? 'Unfeature' : 'Feature')
                    ->icon('heroicon-o-star')
                    ->color(fn (FeedbackImage $record): string => $record->is_featured ? 'warning' : 'gray')
                    ->action(function (FeedbackImage $record): void {
                        if (! $record->is_featured && FeedbackImage::query()->where('is_featured', true)->count() >= 3) {
                            Notification::make()
                                ->danger()
                                ->title('Only 3 images can be featured')
                                ->body('Unfeature another image first.')
                                ->send();

                            return;
                        }

                        $record->update(['is_featured' => ! $record->is_featured]);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedbackImages::route('/'),
            'create' => Pages\CreateFeedbackImage::route('/create'),
            'edit' => Pages\EditFeedbackImage::route('/{record}/edit'),
        ];
    }
}
