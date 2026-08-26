<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\GalleryImage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dmf_s3');
        config(['landing-media.disk' => 'dmf_s3']);
    }

    public function test_it_casts_boolean_and_integer_columns(): void
    {
        $image = GalleryImage::factory()->create([
            'is_featured' => 1,
            'is_active' => 0,
            'sort_order' => '20',
        ]);

        $this->assertIsBool($image->is_featured);
        $this->assertTrue($image->is_featured);
        $this->assertIsBool($image->is_active);
        $this->assertFalse($image->is_active);
        $this->assertIsInt($image->sort_order);
    }

    public function test_it_auto_assigns_sort_order_when_not_given(): void
    {
        GalleryImage::factory()->create(['sort_order' => 10]);
        $second = GalleryImage::factory()->create(['sort_order' => 0]);

        $this->assertSame(20, $second->sort_order);
    }

    public function test_image_url_resolves_through_landing_media_service(): void
    {
        $image = GalleryImage::factory()->create(['image_path' => 'landing/gallery/sample.jpg']);
        Storage::disk('dmf_s3')->put('landing/gallery/sample.jpg', 'fake-image');

        $this->assertStringContainsString('landing/gallery/sample.jpg', (string) $image->imageUrl());
    }

    public function test_deleting_the_model_removes_its_s3_object(): void
    {
        $image = GalleryImage::factory()->create(['image_path' => 'landing/gallery/to-delete.jpg']);
        Storage::disk('dmf_s3')->put('landing/gallery/to-delete.jpg', 'fake-image');

        $image->delete();

        Storage::disk('dmf_s3')->assertMissing('landing/gallery/to-delete.jpg');
    }
}
