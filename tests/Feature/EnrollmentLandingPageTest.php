<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FeedbackImage;
use App\Models\GalleryImage;
use App\Support\YearsOfExcellence;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnrollmentLandingPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dmf_s3');
        config(['landing-media.disk' => 'dmf_s3']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_landing_includes_scroll_animation_markup_for_js(): void
    {
        $content = $this->get('/')->getContent() ?: '';

        $this->assertStringContainsString('land-scroll-anim', $content, 'Enables opt-in scroll and hero motion styles.');
        $this->assertStringContainsString('land-hero-1', $content);
        $this->assertStringContainsString('land-stagger', $content);
    }

    public function test_landing_hero_highlights_replace_analytics_stats(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Why DMF Dental?');
        $response->assertSee('Excellent board performance');
        $response->assertSee('High passing rate');
        $response->assertSee('Multiple topnotchers');
        $response->assertSee(YearsOfExcellence::asOf(now()).' years of excellence');
        $response->assertSee('Topnotch lecturers');
        $response->assertSee('Highly recommended by previous board takers');
        $response->assertDontSee('National Passing Rate');
        $response->assertDontSee('Satisfaction Guarantee');
    }

    public function test_landing_years_of_excellence_increments_on_february_first(): void
    {
        Carbon::setTestNow('2026-01-31');
        $this->get('/')->assertSee('9 years of excellence');

        Carbon::setTestNow('2026-02-01');
        $this->get('/')->assertSee('10 years of excellence');
    }

    public function test_landing_hero_shows_2027_enrollment_badge_without_graduates_social_proof(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('2027 Enrollment Now Open');
        $response->assertDontSee('2026 Enrollment Now Open');
        $response->assertDontSee('2,400+');
        $response->assertDontSee('graduates this year');
    }

    public function test_landing_expert_lecturers_copy_mentions_board_topnotchers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Expert Lecturers');
        $response->assertSee('Learn directly from board topnotchers and seasoned professionals');
    }

    public function test_landing_shows_learning_flexibility_copy(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Learning Flexibility');
        $response->assertSee('Multiple programs to choose from based on your preferred schedule and learning style.');
        $response->assertDontSee('Hybrid Flexibility');
    }

    public function test_landing_shows_highest_passing_rate_copy(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Highest Passing Rate');
        $response->assertSee('We produce topnotchers and a lot of successful examinees every board exam');
        $response->assertSee('theoretical classes and practical drills provide a strong foundation');
    }

    public function test_landing_advantage_features_use_equal_height_cards(): void
    {
        $content = $this->get('/')->getContent() ?: '';

        $this->assertStringContainsString('items-stretch', $content);
        $this->assertStringContainsString('flex h-full flex-col', $content);
        $this->assertStringContainsString('Why Choose DMF Dental?', $content);
    }

    public function test_landing_includes_hybrid_intensive_program_overview_copy(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Hybrid Face-to-Face Intensive Lecture Review');
        $response->assertSee('lecturer availability');
        $response->assertSee('Handouts are given at the venue');
        $response->assertSee('short quiz at the end of each session');
        $response->assertSee('Online pre-board exam (3 days)');
        $response->assertSee('DMF shirt');
    }

    public function test_landing_includes_online_comprehensive_lecture_overview_copy(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Online Comprehensive Lecture Review');
        $response->assertSee('Detailed discussion of all board exam subjects');
        $response->assertSee('up to 4 hours per session');
        $response->assertSee('Review book shipped to your address');
        $response->assertSee('Free: ');
        $response->assertSee('Online pre-board exam (3 days)');
    }

    public function test_landing_includes_online_final_coaching_overview_copy(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Online Final Coaching');
        $response->assertSee('rationalization');
        $response->assertSee('BEQs');
        $response->assertSee('video recordings of sessions');
        $response->assertSee('Test-taking and exam-answering strategies');
        $response->assertSee('Free: ');
        $response->assertSee('Online pre-board examination');
    }

    public function test_landing_includes_face_to_face_practical_overview_copy(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Full Course Face-to-Face Practical Review');
        $response->assertSee('2 days of detailed online discussion');
        $response->assertSee('13 days of whole-day, hands-on training with topnotch lecturers');
        $response->assertSee('2 whole days of practical pre-board exam');
        $response->assertSee('Included: ');
        $response->assertSee('DMF shirt and CD kit');
    }

    public function test_landing_shows_only_featured_active_feedback_images_with_see_more_link(): void
    {
        $featured = FeedbackImage::factory()->featured()->count(3)->create();
        $nonFeatured = FeedbackImage::factory()->create(['is_featured' => false]);
        $inactiveFeatured = FeedbackImage::factory()->featured()->create(['is_active' => false]);

        foreach ($featured as $image) {
            Storage::disk('dmf_s3')->put($image->image_path, 'fake-image');
        }
        Storage::disk('dmf_s3')->put($nonFeatured->image_path, 'fake-image');
        Storage::disk('dmf_s3')->put($inactiveFeatured->image_path, 'fake-image');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('What Our Graduates Say');
        $response->assertSee('feedback-gallery-item', false);
        $response->assertSee(route('feedback'), false);
        $response->assertSee('See more feedback');

        foreach ($featured as $image) {
            $response->assertSee($image->image_path, false);
        }

        $response->assertDontSee($nonFeatured->image_path, false);
        $response->assertDontSee($inactiveFeatured->image_path, false);
    }

    public function test_landing_caps_feedback_images_at_three_even_if_more_are_featured(): void
    {
        // Simulates FeedbackImageSeeder's documented edge case where a re-run can push the
        // featured count above 3, bypassing the Filament table-action guard entirely since
        // these rows are inserted directly, not toggled through the admin UI.
        $featured = FeedbackImage::factory()->featured()->count(4)->create();

        foreach ($featured as $image) {
            Storage::disk('dmf_s3')->put($image->image_path, 'fake-image');
        }

        $response = $this->get('/');

        $response->assertOk();

        foreach ($featured->take(3) as $image) {
            $response->assertSee($image->image_path, false);
        }

        $response->assertDontSee($featured->last()->image_path, false);
    }

    public function test_landing_shows_feedback_empty_state_when_no_featured_images(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Feedback screenshots will appear here once uploaded from the admin panel.');
    }

    public function test_landing_shows_only_featured_active_gallery_images_with_cta(): void
    {
        $featured = GalleryImage::factory()->featured()->count(3)->create();
        $nonFeatured = GalleryImage::factory()->create(['is_featured' => false]);
        $inactiveFeatured = GalleryImage::factory()->featured()->create(['is_active' => false]);

        foreach ($featured as $image) {
            Storage::disk('dmf_s3')->put($image->image_path, 'fake-image');
        }
        Storage::disk('dmf_s3')->put($nonFeatured->image_path, 'fake-image');
        Storage::disk('dmf_s3')->put($inactiveFeatured->image_path, 'fake-image');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Inside DMF Dental Review Center');
        $response->assertSee(route('gallery'), false);
        $response->assertSee('View full gallery');
        $response->assertSee('Enlarged gallery photo', false);
        $response->assertSee('aria-label="Gallery photo"', false);

        foreach ($featured as $image) {
            $response->assertSee($image->image_path, false);
        }

        $response->assertDontSee($nonFeatured->image_path, false);
        $response->assertDontSee($inactiveFeatured->image_path, false);
    }

    public function test_landing_caps_gallery_images_at_three_even_if_more_are_featured(): void
    {
        $featured = GalleryImage::factory()->featured()->count(4)->create();

        foreach ($featured as $image) {
            Storage::disk('dmf_s3')->put($image->image_path, 'fake-image');
        }

        $response = $this->get('/');

        $response->assertOk();

        foreach ($featured->take(3) as $image) {
            $response->assertSee($image->image_path, false);
        }

        $response->assertDontSee($featured->last()->image_path, false);
    }

    public function test_landing_shows_gallery_empty_state_when_no_featured_images(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Gallery photos will appear here once uploaded from the admin panel.');
    }
}
