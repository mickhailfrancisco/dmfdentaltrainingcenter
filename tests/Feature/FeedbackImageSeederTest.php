<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FeedbackImage;
use Database\Seeders\FeedbackImageSeeder;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FeedbackImageSeederTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/feedback-images-'.uniqid('', true));
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_it_imports_bundled_screenshots_and_features_the_first_three(): void
    {
        foreach (['b.jpg', 'a.jpg', 'c.jpg', 'd.jpg'] as $filename) {
            file_put_contents($this->directory.'/'.$filename, 'fake-image');
        }

        (new FeedbackImageSeeder)->run($this->directory);

        $this->assertDatabaseCount('feedback_images', 4);

        $sorted = FeedbackImage::query()->orderBy('sort_order')->get();

        $this->assertSame('images/feedback/a.jpg', $sorted[0]->image_path);
        $this->assertSame('images/feedback/b.jpg', $sorted[1]->image_path);
        $this->assertSame('images/feedback/c.jpg', $sorted[2]->image_path);
        $this->assertSame('images/feedback/d.jpg', $sorted[3]->image_path);

        $this->assertTrue($sorted[0]->is_featured);
        $this->assertTrue($sorted[1]->is_featured);
        $this->assertTrue($sorted[2]->is_featured);
        $this->assertFalse($sorted[3]->is_featured);
    }

    public function test_it_is_idempotent_and_skips_already_imported_paths(): void
    {
        file_put_contents($this->directory.'/only.jpg', 'fake-image');

        (new FeedbackImageSeeder)->run($this->directory);
        (new FeedbackImageSeeder)->run($this->directory);

        $this->assertDatabaseCount('feedback_images', 1);
    }

    public function test_it_does_nothing_when_directory_is_missing(): void
    {
        File::deleteDirectory($this->directory);

        (new FeedbackImageSeeder)->run($this->directory);

        $this->assertDatabaseCount('feedback_images', 0);
    }
}
