<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\GalleryImageResource;
use App\Filament\Resources\GalleryImageResource\Pages\CreateGalleryImage;
use App\Filament\Resources\GalleryImageResource\Pages\EditGalleryImage;
use App\Filament\Resources\GalleryImageResource\Pages\ListGalleryImages;
use App\Models\GalleryImage;
use App\Models\User;
use App\Services\LandingMediaService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryImageResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Storage::fake('dmf_s3');
        config([
            'landing-media.disk' => 'dmf_s3',
            'landing-media.gallery_directory' => 'landing/gallery',
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_list_gallery_images(): void
    {
        $admin = $this->makeAdmin();
        $images = GalleryImage::factory()->count(3)->create();

        $this->actingAs($admin);

        Livewire::test(ListGalleryImages::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($images);
    }

    public function test_admin_can_upload_a_gallery_image(): void
    {
        $admin = $this->makeAdmin();
        $upload = UploadedFile::fake()->image('gallery.jpg');

        $this->actingAs($admin);

        Livewire::test(CreateGalleryImage::class)
            ->fillForm([
                'image_path' => [$upload],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $image = GalleryImage::query()->latest('id')->firstOrFail();

        $this->assertStringStartsWith('landing/gallery/', (string) $image->image_path);
        Storage::disk('dmf_s3')->assertExists((string) $image->image_path);
    }

    public function test_admin_can_bulk_upload_multiple_gallery_images_in_one_submission(): void
    {
        $admin = $this->makeAdmin();
        $uploads = [
            UploadedFile::fake()->image('gallery-1.jpg'),
            UploadedFile::fake()->image('gallery-2.jpg'),
            UploadedFile::fake()->image('gallery-3.jpg'),
        ];

        $this->actingAs($admin);

        Livewire::test(CreateGalleryImage::class)
            ->fillForm([
                'image_path' => $uploads,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(3, GalleryImage::query()->count());

        GalleryImage::query()->get()->each(function (GalleryImage $image): void {
            $this->assertStringStartsWith('landing/gallery/', (string) $image->image_path);
            Storage::disk('dmf_s3')->assertExists((string) $image->image_path);
            $this->assertTrue($image->is_active);
            $this->assertFalse($image->is_featured);
        });
    }

    public function test_featuring_a_fourth_image_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        GalleryImage::factory()->featured()->count(3)->create();
        $fourth = GalleryImage::factory()->create(['is_featured' => false]);

        $this->actingAs($admin);

        Livewire::test(ListGalleryImages::class)
            ->callTableAction('toggleFeatured', $fourth);

        $this->assertFalse($fourth->fresh()->is_featured);
        $this->assertSame(3, GalleryImage::query()->where('is_featured', true)->count());
    }

    public function test_list_page_displays_a_row_whose_object_is_missing_on_s3(): void
    {
        $admin = $this->makeAdmin();

        // Gallery images have no legacy public-path fallback (unlike feedback images), so
        // this simulates the "S3 object doesn't exist" scenario directly: a path that is
        // never put on the faked dmf_s3 disk. This proves the ImageColumn's getStateUsing
        // resolves through GalleryImage::imageUrl() -> LandingMediaService::url() without
        // erroring or blanking out, even though Storage::disk('dmf_s3')->exists() is false.
        $missingPath = 'landing/gallery/legacy-test.jpg';
        $image = GalleryImage::factory()->create(['image_path' => $missingPath]);

        Storage::disk('dmf_s3')->assertMissing($missingPath);

        $expectedUrl = app(LandingMediaService::class)->url($missingPath);

        $this->actingAs($admin);

        Livewire::test(ListGalleryImages::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$image])
            ->assertSee($expectedUrl, false);
    }

    public function test_editing_a_row_without_reuploading_preserves_the_path_when_object_is_missing_on_s3(): void
    {
        $admin = $this->makeAdmin();

        $missingPath = 'landing/gallery/legacy-test.jpg';
        $image = GalleryImage::factory()->create([
            'image_path' => $missingPath,
            'is_active' => true,
        ]);

        Storage::disk('dmf_s3')->assertMissing($missingPath);

        $this->actingAs($admin);

        Livewire::test(EditGalleryImage::class, ['record' => $image->getRouteKey()])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($missingPath, $image->fresh()->image_path);
        $this->assertFalse($image->fresh()->is_active);
    }

    public function test_assistant_cannot_access_gallery_image_resource(): void
    {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant);

        $this->assertFalse(GalleryImageResource::canViewAny());

        Livewire::test(ListGalleryImages::class)->assertForbidden();
    }
}
