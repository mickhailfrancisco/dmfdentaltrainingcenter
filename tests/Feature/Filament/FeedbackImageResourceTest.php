<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\FeedbackImageResource;
use App\Filament\Resources\FeedbackImageResource\Pages\CreateFeedbackImage;
use App\Filament\Resources\FeedbackImageResource\Pages\ListFeedbackImages;
use App\Models\FeedbackImage;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Exceptions\Halt;
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
                'image_path' => [$upload],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $image = FeedbackImage::query()->latest('id')->firstOrFail();

        $this->assertStringStartsWith('landing/feedback/', (string) $image->image_path);
        Storage::disk('dmf_s3')->assertExists((string) $image->image_path);
    }

    public function test_admin_can_bulk_upload_multiple_feedback_images_in_one_submission(): void
    {
        $admin = $this->makeAdmin();
        $uploads = [
            UploadedFile::fake()->image('feedback-1.jpg'),
            UploadedFile::fake()->image('feedback-2.jpg'),
            UploadedFile::fake()->image('feedback-3.jpg'),
        ];

        $this->actingAs($admin);

        Livewire::test(CreateFeedbackImage::class)
            ->fillForm([
                'image_path' => $uploads,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(FeedbackImageResource::getUrl('index'));

        $this->assertSame(3, FeedbackImage::query()->count());

        FeedbackImage::query()->get()->each(function (FeedbackImage $image): void {
            $this->assertStringStartsWith('landing/feedback/', (string) $image->image_path);
            Storage::disk('dmf_s3')->assertExists((string) $image->image_path);
            $this->assertTrue($image->is_active);
            $this->assertFalse($image->is_featured);
        });
    }

    public function test_uploading_more_than_six_images_at_once_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $uploads = array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->image("feedback-{$index}.jpg"),
            range(1, 7),
        );

        $this->actingAs($admin);

        Livewire::test(CreateFeedbackImage::class)
            ->fillForm([
                'image_path' => $uploads,
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified();

        $this->assertSame(0, FeedbackImage::query()->count());
        $this->assertEmpty(Storage::disk('dmf_s3')->allFiles('landing/feedback'));
    }

    public function test_uploading_exactly_six_images_at_once_succeeds(): void
    {
        $admin = $this->makeAdmin();
        $uploads = array_map(
            fn (int $index): UploadedFile => UploadedFile::fake()->image("feedback-{$index}.jpg"),
            range(1, 6),
        );

        $this->actingAs($admin);

        Livewire::test(CreateFeedbackImage::class)
            ->fillForm([
                'image_path' => $uploads,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(6, FeedbackImage::query()->count());
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

    public function test_only_successfully_uploaded_images_are_saved_when_one_upload_silently_fails(): void
    {
        // Simulates moveFiles() "succeeding" from Filament's perspective (it has a path
        // string) while the actual S3 write silently failed — e.g. a transient error
        // swallowed by the dmf_s3 disk's 'throw' => false config. 'ok-1'/'ok-2' are put
        // on the fake disk directly (as if their uploads genuinely landed); 'missing' is
        // deliberately never put there.
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        Storage::disk('dmf_s3')->put('landing/feedback/ok-1.jpg', 'fake-image');
        Storage::disk('dmf_s3')->put('landing/feedback/ok-2.jpg', 'fake-image');

        $component = Livewire::test(CreateFeedbackImage::class);

        $method = new \ReflectionMethod($component->instance(), 'handleRecordCreation');
        $method->setAccessible(true);

        $method->invoke($component->instance(), [
            'image_path' => ['landing/feedback/ok-1.jpg', 'landing/feedback/missing.jpg', 'landing/feedback/ok-2.jpg'],
            'is_active' => true,
        ]);

        $this->assertSame(2, FeedbackImage::query()->count());
        $this->assertDatabaseMissing('feedback_images', ['image_path' => 'landing/feedback/missing.jpg']);
        $component->assertNotified();
    }

    public function test_no_records_are_created_when_every_upload_silently_fails(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $component = Livewire::test(CreateFeedbackImage::class);

        $method = new \ReflectionMethod($component->instance(), 'handleRecordCreation');
        $method->setAccessible(true);

        try {
            $method->invoke($component->instance(), [
                'image_path' => ['landing/feedback/missing-1.jpg', 'landing/feedback/missing-2.jpg'],
                'is_active' => true,
            ]);
            $this->fail('Expected a Halt exception to be thrown.');
        } catch (Halt) {
            // expected — matches the existing over-limit rejection pattern.
        }

        $this->assertSame(0, FeedbackImage::query()->count());
        $component->assertNotified();
    }

    public function test_admin_can_preview_a_feedback_image_from_the_list_page(): void
    {
        $image = FeedbackImage::factory()->create(['image_path' => 'landing/feedback/preview-test.jpg']);
        Storage::disk('dmf_s3')->put($image->image_path, 'fake-image');

        $html = FeedbackImageResource::previewImageModalView($image)->render();

        $this->assertStringContainsString('landing/feedback/preview-test.jpg', $html);
    }

    public function test_admin_can_toggle_active_directly_from_the_list_page(): void
    {
        $admin = $this->makeAdmin();
        $image = FeedbackImage::factory()->create(['is_active' => true]);

        $this->actingAs($admin);

        Livewire::test(ListFeedbackImages::class)
            ->call('updateTableColumnState', 'is_active', $image->getKey(), false);

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
