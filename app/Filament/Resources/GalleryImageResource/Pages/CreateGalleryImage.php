<?php

declare(strict_types=1);

namespace App\Filament\Resources\GalleryImageResource\Pages;

use App\Filament\Resources\GalleryImageResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Models\GalleryImage;
use App\Services\LandingMediaService;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CreateGalleryImage extends CreateRecord
{
    protected static string $resource = GalleryImageResource::class;

    public function form(Form $form): Form
    {
        $service = app(LandingMediaService::class);

        return $form->schema([
            Forms\Components\FileUpload::make('image_path')
                ->label('Images')
                ->helperText('Upload one or more images. Each becomes its own gallery image.')
                ->image()
                ->multiple()
                ->maxParallelUploads(1)
                ->panelLayout('grid')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->imagePreviewHeight('150')
                ->disk($service->disk())
                ->directory($service->galleryDirectory())
                ->visibility($service->uploadVisibility())
                ->maxSize(5120)
                ->required()
                ->moveFiles()
                ->fetchFileInformation(false)
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $paths = Collection::wrap($data['image_path']);

        $records = $paths->map(fn (string $path): GalleryImage => GalleryImage::create([
            'image_path' => $path,
            'is_active' => $data['is_active'],
        ]));

        return $records->last();
    }
}
