<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryImageResource\Pages;
use App\Models\GalleryImage;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class GalleryImageResource extends Resource
{
    protected static ?string $model = GalleryImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Gallery images';

    protected static ?string $modelLabel = 'Gallery image';

    protected static ?string $pluralModelLabel = 'Gallery images';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->getStateUsing(fn (GalleryImage $record): ?string => $record->imageUrl())
                    ->checkFileExistence(false)
                    ->square(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\Action::make('previewImage')
                    ->iconButton()
                    ->icon('heroicon-o-magnifying-glass-plus')
                    ->tooltip('Preview image')
                    ->modalHeading('Preview image')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('2xl')
                    ->modalContent(fn (GalleryImage $record): View => static::previewImageModalView($record)),
                Tables\Actions\Action::make('toggleFeatured')
                    ->iconButton()
                    ->tooltip(fn (GalleryImage $record): string => $record->is_featured ? 'Unfeature' : 'Feature')
                    ->icon('heroicon-o-star')
                    ->color(fn (GalleryImage $record): string => $record->is_featured ? 'warning' : 'gray')
                    ->action(function (GalleryImage $record): void {
                        if (! $record->is_featured && GalleryImage::query()->where('is_featured', true)->count() >= 3) {
                            Notification::make()
                                ->danger()
                                ->title('Only 3 images can be featured')
                                ->body('Unfeature another image first.')
                                ->send();

                            return;
                        }

                        $record->update(['is_featured' => ! $record->is_featured]);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGalleryImages::route('/'),
            'create' => Pages\CreateGalleryImage::route('/create'),
        ];
    }

    public static function previewImageModalView(GalleryImage $record): View
    {
        return view('filament.modals.image-preview', [
            'imageUrl' => $record->imageUrl(),
        ]);
    }
}
