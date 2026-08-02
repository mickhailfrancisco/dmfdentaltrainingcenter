<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class EnrollmentLandingPageTest extends TestCase
{
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
        $response->assertSee('10 years of excellence');
        $response->assertSee('Topnotch lecturers');
        $response->assertSee('Highly recommended by previous board takers');
        $response->assertDontSee('National Passing Rate');
        $response->assertDontSee('Satisfaction Guarantee');
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

    public function test_landing_renders_feedback_gallery_when_screenshots_exist(): void
    {
        $directory = public_path('images/feedback');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $created = [];
        for ($i = 1; $i <= 8; $i++) {
            $filename = sprintf('zz-test-feedback-%02d.png', $i);
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            file_put_contents($path, 'fake-image');
            $created[] = $path;
        }

        try {
            $response = $this->get('/');

            $response->assertOk();
            $response->assertSee('What Our Graduates Say');
            $response->assertSee('Tap a card to read it clearly');
            $response->assertSee('real Facebook reviews');
            $response->assertSee('Show more feedback');
            $response->assertSee('feedback-gallery-item', false);
            $response->assertSee('x-teleport="body"', false);
            $response->assertSee(asset('images/feedback/zz-test-feedback-01.png'), false);
            $response->assertDontSee('Dr. Maria Santos');
        } finally {
            foreach ($created as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
