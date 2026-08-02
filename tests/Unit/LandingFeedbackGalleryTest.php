<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LandingFeedbackGallery;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LandingFeedbackGalleryTest extends TestCase
{
    private string $tempDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDirectory = storage_path('framework/testing/feedback-gallery-'.uniqid('', true));
        File::ensureDirectoryExists($this->tempDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDirectory);

        parent::tearDown();
    }

    public function test_image_urls_returns_sorted_image_assets_only(): void
    {
        file_put_contents($this->tempDirectory.'/feedback-b.jpg', 'fake');
        file_put_contents($this->tempDirectory.'/feedback-a.png', 'fake');
        file_put_contents($this->tempDirectory.'/ignore.txt', 'nope');

        $urls = LandingFeedbackGallery::imageUrls($this->tempDirectory);

        $this->assertSame(['feedback-a.png', 'feedback-b.jpg'], $urls);
    }
}
