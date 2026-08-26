<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FeedbackImage;
use App\Models\GalleryImage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingMediaPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dmf_s3');
        config(['landing-media.disk' => 'dmf_s3']);
    }

    public function test_feedback_page_lists_only_active_images(): void
    {
        $active = FeedbackImage::factory()->create(['image_path' => 'landing/feedback/active.jpg', 'is_active' => true]);
        FeedbackImage::factory()->create(['image_path' => 'landing/feedback/inactive.jpg', 'is_active' => false]);
        Storage::disk('dmf_s3')->put('landing/feedback/active.jpg', 'fake');
        Storage::disk('dmf_s3')->put('landing/feedback/inactive.jpg', 'fake');

        $response = $this->get(route('feedback'));

        $response->assertOk();
        $response->assertSee('landing/feedback/active.jpg', false);
        $response->assertDontSee('landing/feedback/inactive.jpg', false);
        $this->assertTrue($active->exists);
    }

    public function test_feedback_page_shows_empty_state_when_no_images(): void
    {
        $response = $this->get(route('feedback'));

        $response->assertOk();
        $response->assertSee('No feedback screenshots yet.');
    }

    public function test_gallery_page_lists_only_active_images(): void
    {
        GalleryImage::factory()->create(['image_path' => 'landing/gallery/active.jpg', 'is_active' => true]);
        GalleryImage::factory()->create(['image_path' => 'landing/gallery/inactive.jpg', 'is_active' => false]);
        Storage::disk('dmf_s3')->put('landing/gallery/active.jpg', 'fake');
        Storage::disk('dmf_s3')->put('landing/gallery/inactive.jpg', 'fake');

        $response = $this->get(route('gallery'));

        $response->assertOk();
        $response->assertSee('landing/gallery/active.jpg', false);
        $response->assertDontSee('landing/gallery/inactive.jpg', false);
    }

    public function test_gallery_page_shows_empty_state_when_no_images(): void
    {
        $response = $this->get(route('gallery'));

        $response->assertOk();
        $response->assertSee('No gallery photos yet.');
    }
}
