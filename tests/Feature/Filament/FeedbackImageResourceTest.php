<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\FeedbackImageResource;
use App\Filament\Resources\FeedbackImageResource\Pages\CreateFeedbackImage;
use App\Filament\Resources\FeedbackImageResource\Pages\ListFeedbackImages;
use App\Models\FeedbackImage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
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

    public function test_assistant_cannot_access_feedback_image_resource(): void
    {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant);

        $this->assertFalse(FeedbackImageResource::canViewAny());

        Livewire::test(ListFeedbackImages::class)->assertForbidden();
    }
}
