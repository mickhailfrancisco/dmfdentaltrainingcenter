<?php

declare(strict_types=1);

namespace App\Filament\Resources\FeedbackImageResource\Pages;

use App\Filament\Resources\FeedbackImageResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Models\FeedbackImage;
use App\Services\LandingMediaService;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CreateFeedbackImage extends CreateRecord
{
    protected static string $resource = FeedbackImageResource::class;

    public function form(Form $form): Form
    {
        $service = app(LandingMediaService::class);

        return $form->schema([
            Forms\Components\FileUpload::make('image_path')
                ->label('Images')
                ->helperText('Upload one or more images. Each becomes its own feedback image.')
                ->image()
                ->multiple()
                ->maxParallelUploads(1)
                ->panelLayout('grid')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->imagePreviewHeight('150')
                ->disk($service->disk())
                ->directory($service->feedbackDirectory())
                ->visibility($service->uploadVisibility())
                ->maxSize(5120)
                ->required()
                ->moveFiles()
                ->fetchFileInformation(false)
                ->getUploadedFileUsing(fn (Forms\Components\FileUpload $component, string $file, string|array|null $storedFileNames): array => $service->uploadedFileMetadata($file, $storedFileNames, $component->isMultiple()))
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

        $records = $paths->map(fn (string $path): FeedbackImage => FeedbackImage::create([
            'image_path' => $path,
            'is_active' => $data['is_active'],
        ]));

        return $records->last();
    }

    /**
     * One submission can create several records — redirect to the list instead of
     * Filament's default single-record Edit page, which only shows the last one.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
