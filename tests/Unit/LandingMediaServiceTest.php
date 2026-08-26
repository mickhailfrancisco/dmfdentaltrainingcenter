<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\LandingMediaService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingMediaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dmf_s3');
        config([
            'landing-media.disk' => 'dmf_s3',
            'landing-media.feedback_directory' => 'landing/feedback',
            'landing-media.gallery_directory' => 'landing/gallery',
            'landing-media.legacy_feedback_public_prefix' => 'images/feedback/',
        ]);
    }

    public function test_disk_and_directories_read_from_config(): void
    {
        $service = new LandingMediaService;

        $this->assertSame('dmf_s3', $service->disk());
        $this->assertSame('landing/feedback', $service->feedbackDirectory());
        $this->assertSame('landing/gallery', $service->galleryDirectory());
    }

    public function test_url_returns_null_for_blank_path(): void
    {
        $service = new LandingMediaService;

        $this->assertNull($service->url(null));
        $this->assertNull($service->url(''));
    }

    public function test_url_returns_absolute_urls_unchanged(): void
    {
        $service = new LandingMediaService;

        $this->assertSame(
            'https://example.com/already-absolute.jpg',
            $service->url('https://example.com/already-absolute.jpg'),
        );
    }

    public function test_url_resolves_stored_s3_object_path(): void
    {
        Storage::disk('dmf_s3')->put('landing/feedback/sample.jpg', 'fake-image');

        $service = new LandingMediaService;

        $this->assertStringContainsString('landing/feedback/sample.jpg', (string) $service->url('landing/feedback/sample.jpg'));
    }

    public function test_url_falls_back_to_asset_for_legacy_public_path_that_exists_on_disk(): void
    {
        $directory = public_path('images/feedback');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($directory.'/legacy-test.jpg', 'fake-image');

        try {
            $service = new LandingMediaService;

            $this->assertSame(asset('images/feedback/legacy-test.jpg'), $service->url('images/feedback/legacy-test.jpg'));
        } finally {
            @unlink($directory.'/legacy-test.jpg');
        }
    }

    public function test_delete_asset_removes_object_from_configured_disk(): void
    {
        Storage::disk('dmf_s3')->put('landing/gallery/to-delete.jpg', 'fake-image');

        $service = new LandingMediaService;
        $service->deleteAsset('landing/gallery/to-delete.jpg');

        Storage::disk('dmf_s3')->assertMissing('landing/gallery/to-delete.jpg');
    }

    public function test_delete_asset_ignores_legacy_public_paths(): void
    {
        $service = new LandingMediaService;

        // Must not throw even though this path is never on the dmf_s3 disk.
        $service->deleteAsset('images/feedback/legacy-untouched.jpg');

        $this->assertTrue(true);
    }

    public function test_delete_asset_is_a_noop_for_blank_path(): void
    {
        $service = new LandingMediaService;
        $service->deleteAsset(null);

        $this->assertTrue(true);
    }
}
