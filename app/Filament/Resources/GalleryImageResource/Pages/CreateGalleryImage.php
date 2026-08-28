<?php

declare(strict_types=1);

namespace App\Filament\Resources\GalleryImageResource\Pages;

use App\Filament\Resources\GalleryImageResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Models\GalleryImage;
use App\Services\LandingMediaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CreateGalleryImage extends CreateRecord
{
    protected static string $resource = GalleryImageResource::class;

    /**
     * Each image is uploaded to S3 synchronously when the form is submitted. A batch
     * much larger than this risks the request exceeding the server's gateway timeout.
     */
    private const MAX_FILES_PER_SUBMISSION = 6;

    public function form(Form $form): Form
    {
        $service = app(LandingMediaService::class);

        return $form->schema([
            Forms\Components\FileUpload::make('image_path')
                ->label('Images')
                ->helperText('Upload up to '.self::MAX_FILES_PER_SUBMISSION.' images at a time. Each becomes its own gallery image.')
                ->image()
                ->multiple()
                // Deliberately not ->maxFiles(): Filament wires that to FilePond's own
                // client-side cap too, which silently blocks a 7th file with zero visible
                // feedback in this panel layout, and FileUpload's own validation-rule
                // override ignores ->rules() entirely. The cap is enforced, with a real
                // visible error, in handleRecordCreation() below instead.
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

        if ($paths->count() > self::MAX_FILES_PER_SUBMISSION) {
            $paths->each(fn (string $path) => app(LandingMediaService::class)->deleteAsset($path));

            Notification::make()
                ->danger()
                ->title('Too many images')
                ->body('Upload at most '.self::MAX_FILES_PER_SUBMISSION.' images per submission — try again with fewer.')
                ->send();

            throw new Halt;
        }

        $records = $paths->map(fn (string $path): GalleryImage => GalleryImage::create([
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
