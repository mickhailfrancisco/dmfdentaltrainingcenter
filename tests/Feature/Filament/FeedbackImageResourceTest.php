<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\FeedbackImageResource;
use App\Filament\Resources\FeedbackImageResource\Pages\CreateFeedbackImage;
use App\Filament\Resources\FeedbackImageResource\Pages\EditFeedbackImage;
use App\Filament\Resources\FeedbackImageResource\Pages\ListFeedbackImages;
use App\Models\FeedbackImage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FeedbackImageResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Storage::fake('dmf_s3');
        config([
            'landing-media.disk' => 'dmf_s3',
            'landing-media.feedback_directory' => 'landing/feedback',
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_list_feedback_images(): void
    {
        $admin = $this->makeAdmin();
        $images = FeedbackImage::factory()->count(3)->create();

        $this->actingAs($admin);

        Livewire::test(ListFeedbackImages::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($images);
    }

    public function test_admin_can_upload_a_feedback_image(): void
    {
        $admin = $this->makeAdmin();
        $upload = UploadedFile::fake()->image('feedback.jpg');

        $this->actingAs($admin);

        Livewire::test(CreateFeedbackImage::class)
            ->fillForm([
                'image_path' => $upload,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $image = FeedbackImage::query()->latest('id')->firstOrFail();

        $this->assertStringStartsWith('landing/feedback/', (string) $image->image_path);
        Storage::disk('dmf_s3')->assertExists((string) $image->image_path);
    }

    public function test_featuring_a_fourth_image_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        FeedbackImage::factory()->featured()->count(3)->create();
        $fourth = FeedbackImage::factory()->create(['is_featured' => false]);

        $this->actingAs($admin);

        Livewire::test(ListFeedbackImages::class)
            ->callTableAction('toggleFeatured', $fourth);

        $this->assertFalse($fourth->fresh()->is_featured);
        $this->assertSame(3, FeedbackImage::query()->where('is_featured', true)->count());
    }

    public function test_unfeaturing_then_featuring_another_image_succeeds(): void
    {
        $admin = $this->makeAdmin();
        $featured = FeedbackImage::factory()->featured()->count(3)->create();
        $candidate = FeedbackImage::factory()->create(['is_featured' => false]);

        $this->actingAs($admin);

        Livewire::test(ListFeedbackImages::class)
            ->callTableAction('toggleFeatured', $featured->first())
            ->callTableAction('toggleFeatured', $candidate);

        $this->assertFalse($featured->first()->fresh()->is_featured);
        $this->assertTrue($candidate->fresh()->is_featured);
        $this->assertSame(3, FeedbackImage::query()->where('is_featured', true)->count());
    }

    public function test_list_page_displays_a_legacy_public_path_image_not_present_on_s3(): void
    {
        $admin = $this->makeAdmin();

        // Simulates the shape of rows created by FeedbackImageSeeder: an image_path under
        // the legacy public prefix, resolved via public_path() rather than the S3 disk.
        // Deliberately NOT put on the faked dmf_s3 disk, since a legacy row was never
        // uploaded to S3 — Storage::disk('dmf_s3')->exists() for this path is false.
        $legacyPath = 'images/feedback/legacy-list-test.jpg';
        $directory = public_path('images/feedback');
        File::ensureDirectoryExists($directory);
        file_put_contents(public_path($legacyPath), 'fake-legacy-image');

        try {
            $image = FeedbackImage::factory()->create(['image_path' => $legacyPath]);

            Storage::disk('dmf_s3')->assertMissing($legacyPath);

            $this->actingAs($admin);

            Livewire::test(ListFeedbackImages::class)
                ->assertSuccessful()
                ->assertCanSeeTableRecords([$image])
                ->assertSee(asset($legacyPath), false);
        } finally {
            @unlink(public_path($legacyPath));
        }
    }

    public function test_editing_a_legacy_path_row_without_reuploading_preserves_the_path(): void
    {
        $admin = $this->makeAdmin();

        $legacyPath = 'images/feedback/Unknown-10.jpg';
        $image = FeedbackImage::factory()->create([
            'image_path' => $legacyPath,
            'is_active' => true,
        ]);

        Storage::disk('dmf_s3')->assertMissing($legacyPath);

        $this->actingAs($admin);

        Livewire::test(EditFeedbackImage::class, ['record' => $image->getRouteKey()])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($legacyPath, $image->fresh()->image_path);
        $this->assertFalse($image->fresh()->is_active);
    }

    public function test_assistant_cannot_access_feedback_image_resource(): void
    {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant);

        $this->assertFalse(FeedbackImageResource::canViewAny());

        Livewire::test(ListFeedbackImages::class)->assertForbidden();
    }
}
