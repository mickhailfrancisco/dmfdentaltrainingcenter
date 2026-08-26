<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\GalleryImageResource;
use App\Filament\Resources\GalleryImageResource\Pages\CreateGalleryImage;
use App\Filament\Resources\GalleryImageResource\Pages\ListGalleryImages;
use App\Models\GalleryImage;
use App\Models\User;
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
                'image_path' => $upload,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $image = GalleryImage::query()->latest('id')->firstOrFail();

        $this->assertStringStartsWith('landing/gallery/', (string) $image->image_path);
        Storage::disk('dmf_s3')->assertExists((string) $image->image_path);
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

    public function test_assistant_cannot_access_gallery_image_resource(): void
    {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant);

        $this->assertFalse(GalleryImageResource::canViewAny());

        Livewire::test(ListGalleryImages::class)->assertForbidden();
    }
}
