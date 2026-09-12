<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\GalleryImageResource;
use App\Filament\Resources\GalleryImageResource\Pages\CreateGalleryImage;
use App\Filament\Resources\GalleryImageResource\Pages\ListGalleryImages;
use App\Models\GalleryImage;
use App\Models\User;
use App\Services\LandingMediaService;
use Filament\Facades\Filament;
use Filament\Support\Exceptions\Halt;
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
            ->assertHasNoFormErrors()
            ->assertRedirect(GalleryImageResource::getUrl('index'));

        $this->assertSame(3, GalleryImage::query()->count());

        GalleryImage::query()->get()->each(function (GalleryImage $image): void {
            $this->assertStringStartsWith('landing/gallery/', (string) $image->image_path);
            Storage::disk('dmf_s3')->assertExists((string) $image->image_path);
            $this->assertTrue($image->is_active);
            $this->assertFalse($image->is_featured);
        });
    }

    public function test_uploading_more_than_six_images_at_once_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $uploads = array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->image("gallery-{$index}.jpg"),
            range(1, 7),
        );

        $this->actingAs($admin);

        Livewire::test(CreateGalleryImage::class)
            ->fillForm([
                'image_path' => $uploads,
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified();

        $this->assertSame(0, GalleryImage::query()->count());
        $this->assertEmpty(Storage::disk('dmf_s3')->allFiles('landing/gallery'));
    }

    public function test_uploading_exactly_six_images_at_once_succeeds(): void
    {
        $admin = $this->makeAdmin();
        $uploads = array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->image("gallery-{$index}.jpg"),
            range(1, 6),
        );

        $this->actingAs($admin);

        Livewire::test(CreateGalleryImage::class)
            ->fillForm([
                'image_path' => $uploads,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(6, GalleryImage::query()->count());
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

    public function test_only_successfully_uploaded_images_are_saved_when_one_upload_silently_fails(): void
    {
        // Simulates moveFiles() "succeeding" from Filament's perspective (it has a path
        // string) while the actual S3 write silently failed — e.g. a transient error
        // swallowed by the dmf_s3 disk's 'throw' => false config. 'ok-1'/'ok-2' are put
        // on the fake disk directly (as if their uploads genuinely landed); 'missing' is
        // deliberately never put there.
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        Storage::disk('dmf_s3')->put('landing/gallery/ok-1.jpg', 'fake-image');
        Storage::disk('dmf_s3')->put('landing/gallery/ok-2.jpg', 'fake-image');

        $component = Livewire::test(CreateGalleryImage::class);

        $method = new \ReflectionMethod($component->instance(), 'handleRecordCreation');
        $method->setAccessible(true);

        $method->invoke($component->instance(), [
            'image_path' => ['landing/gallery/ok-1.jpg', 'landing/gallery/missing.jpg', 'landing/gallery/ok-2.jpg'],
            'is_active' => true,
        ]);

        $this->assertSame(2, GalleryImage::query()->count());
        $this->assertDatabaseMissing('gallery_images', ['image_path' => 'landing/gallery/missing.jpg']);
        $component->assertNotified();
    }

    public function test_no_records_are_created_when_every_upload_silently_fails(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $component = Livewire::test(CreateGalleryImage::class);

        $method = new \ReflectionMethod($component->instance(), 'handleRecordCreation');
        $method->setAccessible(true);

        try {
            $method->invoke($component->instance(), [
                'image_path' => ['landing/gallery/missing-1.jpg', 'landing/gallery/missing-2.jpg'],
                'is_active' => true,
            ]);
            $this->fail('Expected a Halt exception to be thrown.');
        } catch (Halt) {
            // expected — matches the existing over-limit rejection pattern.
        }

        $this->assertSame(0, GalleryImage::query()->count());
        $component->assertNotified();
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

    public function test_admin_can_preview_a_gallery_image_from_the_list_page(): void
    {
        $image = GalleryImage::factory()->create(['image_path' => 'landing/gallery/preview-test.jpg']);
        Storage::disk('dmf_s3')->put($image->image_path, 'fake-image');

        $html = GalleryImageResource::previewImageModalView($image)->render();

        $this->assertStringContainsString('landing/gallery/preview-test.jpg', $html);
    }

    public function test_admin_can_toggle_active_directly_from_the_list_page(): void
    {
        $admin = $this->makeAdmin();
        $image = GalleryImage::factory()->create(['is_active' => true]);

        $this->actingAs($admin);

        Livewire::test(ListGalleryImages::class)
            ->call('updateTableColumnState', 'is_active', $image->getKey(), false);

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
