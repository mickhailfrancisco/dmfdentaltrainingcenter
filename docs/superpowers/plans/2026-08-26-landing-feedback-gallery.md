# Landing Page Feedback & Gallery Sections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the landing page's feedback section from a static, filesystem-scanned gallery into a database + S3-backed system showing 3 featured images with a "See more" link to a dedicated feedback page, and add a brand-new, separate gallery section (3 featured images + CTA to a dedicated gallery page) — both manageable from the Filament admin panel with S3-backed image uploads.

**Architecture:** Two independent Eloquent models/tables (`FeedbackImage`, `GalleryImage` — intentionally not merged, per client requirement), each with its own Filament resource for CRUD + a "feature" toggle capped at 3 featured images. Images upload to S3 (`dmf_s3` disk) into dedicated per-type folders via a shared `LandingMediaService` for URL resolution and cleanup. The landing page queries only the 3 featured+active images per type; two new dedicated public pages list all active images with pagination.

**Tech Stack:** Laravel 11, Filament v3, Livewire v3, PHPUnit 11, Tailwind v3, Alpine.js (CDN), `league/flysystem-aws-s3-v3` (already installed), `dmf_s3` filesystem disk (already configured).

**Spec:** Client request (Taglish, paraphrased): landing page feedback section shows only 3 images + "see more" button linking to a dedicated feedback page; landing page gets a new, separate gallery section (3 images + CTA button linking to a dedicated gallery page); admin panel gets image upload for both feedback and gallery, with the ability to select which 3 images are featured per type; images save to S3 in dedicated folders; feedback and gallery must NOT be combined into one system since they serve different purposes.

## Global Constraints

- PHP 8.4, Laravel 11, Filament v3, Livewire v3, PHPUnit 11 (all tests are PHPUnit classes, never Pest).
- Every PHP file uses `declare(strict_types=1);` and explicit return types/param type hints (per this repo's existing convention in `App\Services`, `App\Support`, `App\Filament\Concerns`).
- Feedback and gallery are two fully separate models, tables, config directories, S3 folders, and Filament resources — never share a table or resource, per the client's explicit instruction.
- Images are stored on the `dmf_s3` disk (the existing convention disk name — matches Laravel Cloud's injected disk config), in `landing/feedback/` and `landing/gallery/` directories respectively, configurable via `.env`.
- Run `vendor/bin/pint --dirty --format agent` after any PHP file changes, before considering a task done.
- Run only the tests relevant to the task being worked on (`php artisan test --compact --filter=...` or a specific file), not the full suite, until the final task.
- Do not remove `tests/Unit/LandingFeedbackGalleryTest.php` or `app/Support/LandingFeedbackGallery.php` until Task 7 explicitly retires them (they still back the current live landing page until then).

---

## Task 1: Landing media config + `LandingMediaService`

**Files:**
- Create: `config/landing-media.php`
- Create: `app/Services/LandingMediaService.php`
- Test: `tests/Unit/LandingMediaServiceTest.php`
- Modify: `.env.example`

**Interfaces:**
- Produces: `App\Services\LandingMediaService` with public methods `disk(): string`, `feedbackDirectory(): string`, `galleryDirectory(): string`, `url(?string $path): ?string`, `deleteAsset(?string $path): void`. Later tasks (models, Filament resources, controllers, views) all resolve image URLs and clean up files through this service — no other class should call `Storage::disk(...)` directly for feedback/gallery images.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/LandingMediaServiceTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/LandingMediaServiceTest.php`
Expected: FAIL with "Class \"App\Services\LandingMediaService\" not found" (config file also missing, but the class error surfaces first).

- [ ] **Step 3: Create the config file**

Create `config/landing-media.php`:

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Landing Page Media Storage (Feedback & Gallery)
    |--------------------------------------------------------------------------
    |
    | Uses the dmf_s3 disk (matches Laravel Cloud LARAVEL_CLOUD_DISK_CONFIG). Configure
    | AWS_* in .env locally; Cloud injects credentials for dmf_s3 at boot.
    | Feedback and gallery images are kept in separate S3 folders — they are
    | unrelated features (testimonials vs. facility/photo gallery) even though
    | they share the same storage plumbing.
    |
    */

    'disk' => env('LANDING_MEDIA_DISK', 'dmf_s3'),

    'feedback_directory' => env('LANDING_FEEDBACK_DIRECTORY', 'landing/feedback'),

    'gallery_directory' => env('LANDING_GALLERY_DIRECTORY', 'landing/gallery'),

    /*
    | Bundled repo assets under public/images/feedback/ (pre-dating S3 uploads)
    | use asset() until replaced via admin upload.
    */
    'legacy_feedback_public_prefix' => 'images/feedback/',
];
```

- [ ] **Step 4: Implement `LandingMediaService`**

Create `app/Services/LandingMediaService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LandingMediaService
{
    public function disk(): string
    {
        return (string) config('landing-media.disk', 'dmf_s3');
    }

    public function feedbackDirectory(): string
    {
        return (string) config('landing-media.feedback_directory', 'landing/feedback');
    }

    public function galleryDirectory(): string
    {
        return (string) config('landing-media.gallery_directory', 'landing/gallery');
    }

    /**
     * Resolve a display URL for a stored image path.
     * Falls back to asset() for legacy public/images/feedback/* paths bundled in the repo.
     */
    public function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $legacyPrefix = (string) config('landing-media.legacy_feedback_public_prefix', 'images/feedback/');

        if (str_starts_with($path, $legacyPrefix) && is_file(public_path($path))) {
            return asset($path);
        }

        try {
            return Storage::disk($this->disk())->url($path);
        } catch (\Throwable $exception) {
            Log::warning('Failed to resolve landing media asset URL.', [
                'disk' => $this->disk(),
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete a stored image from the configured disk. Legacy bundled repo assets are left alone.
     */
    public function deleteAsset(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $legacyPrefix = (string) config('landing-media.legacy_feedback_public_prefix', 'images/feedback/');

        if (str_starts_with($path, $legacyPrefix)) {
            return;
        }

        $disk = $this->disk();

        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete landing media asset.', [
                'disk' => $disk,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/LandingMediaServiceTest.php`
Expected: PASS (8 tests).

- [ ] **Step 6: Add `.env.example` documentation block**

In `.env.example`, immediately after the existing `# Manual payment channel QR/logo storage ...` block (after `MANUAL_PAYMENT_SIGNED_URL_MINUTES=15`), add:

```
# Landing page feedback/gallery image storage — dmf_s3 disk, separate S3 folders.
# LANDING_MEDIA_DISK=dmf_s3
# LANDING_FEEDBACK_DIRECTORY=landing/feedback
# LANDING_GALLERY_DIRECTORY=landing/gallery
```

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/landing-media.php app/Services/LandingMediaService.php tests/Unit/LandingMediaServiceTest.php .env.example
git commit -m "feat(landing-media): add LandingMediaService and S3 config for feedback/gallery"
```

---

## Task 2: `FeedbackImage` & `GalleryImage` models, migrations, factories

**Files:**
- Create: `database/migrations/2026_08_26_000001_create_feedback_images_table.php`
- Create: `database/migrations/2026_08_26_000002_create_gallery_images_table.php`
- Create: `app/Models/Concerns/CleansUpLandingMediaFile.php`
- Create: `app/Models/FeedbackImage.php`
- Create: `app/Models/GalleryImage.php`
- Create: `database/factories/FeedbackImageFactory.php`
- Create: `database/factories/GalleryImageFactory.php`
- Test: `tests/Unit/FeedbackImageTest.php`
- Test: `tests/Unit/GalleryImageTest.php`

**Interfaces:**
- Consumes: `App\Services\LandingMediaService` (Task 1) for `deleteAsset()`/`url()`. Reuses existing `App\Models\Concerns\AssignsCatalogSortOrder` trait unchanged (auto-assigns `sort_order = max(sort_order) + 10` on create when not explicitly set).
- Produces: `App\Models\FeedbackImage` and `App\Models\GalleryImage`, both with fillable `image_path`, `is_featured`, `is_active`, `sort_order`; casts `is_featured`/`is_active` to bool, `sort_order` to int; public method `imageUrl(): ?string`. `Database\Factories\FeedbackImageFactory` / `GalleryImageFactory` with a `featured()` state. Later tasks (Filament resources, controllers, seeder, views) depend on these exact model/factory names and the `imageUrl()` method.

- [ ] **Step 1: Write the failing test for `FeedbackImage`**

Create `tests/Unit/FeedbackImageTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\FeedbackImage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedbackImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dmf_s3');
        config(['landing-media.disk' => 'dmf_s3']);
    }

    public function test_it_casts_boolean_and_integer_columns(): void
    {
        $image = FeedbackImage::factory()->create([
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
        FeedbackImage::factory()->create(['sort_order' => 10]);
        $second = FeedbackImage::factory()->create(['sort_order' => 0]);

        $this->assertSame(20, $second->sort_order);
    }

    public function test_image_url_resolves_through_landing_media_service(): void
    {
        $image = FeedbackImage::factory()->create(['image_path' => 'landing/feedback/sample.jpg']);
        Storage::disk('dmf_s3')->put('landing/feedback/sample.jpg', 'fake-image');

        $this->assertStringContainsString('landing/feedback/sample.jpg', (string) $image->imageUrl());
    }

    public function test_deleting_the_model_removes_its_s3_object(): void
    {
        $image = FeedbackImage::factory()->create(['image_path' => 'landing/feedback/to-delete.jpg']);
        Storage::disk('dmf_s3')->put('landing/feedback/to-delete.jpg', 'fake-image');

        $image->delete();

        Storage::disk('dmf_s3')->assertMissing('landing/feedback/to-delete.jpg');
    }
}
```

- [ ] **Step 2: Write the failing test for `GalleryImage`**

Create `tests/Unit/GalleryImageTest.php`:

```php
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
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --compact tests/Unit/FeedbackImageTest.php tests/Unit/GalleryImageTest.php`
Expected: FAIL — "Class \"App\Models\FeedbackImage\" not found" (and same for `GalleryImage`).

- [ ] **Step 4: Create the migrations**

Create `database/migrations/2026_08_26_000001_create_feedback_images_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_images', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_images');
    }
};
```

Create `database/migrations/2026_08_26_000002_create_gallery_images_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_images');
    }
};
```

- [ ] **Step 5: Create the shared cleanup trait**

Create `app/Models/Concerns/CleansUpLandingMediaFile.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\LandingMediaService;

trait CleansUpLandingMediaFile
{
    protected static function bootCleansUpLandingMediaFile(): void
    {
        static::deleting(function (self $model): void {
            app(LandingMediaService::class)->deleteAsset($model->image_path);
        });
    }
}
```

- [ ] **Step 6: Create the `FeedbackImage` model**

Create `app/Models/FeedbackImage.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Models\Concerns\CleansUpLandingMediaFile;
use App\Services\LandingMediaService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackImage extends Model
{
    use AssignsCatalogSortOrder, CleansUpLandingMediaFile, HasFactory;

    protected $fillable = [
        'image_path',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function imageUrl(): ?string
    {
        return app(LandingMediaService::class)->url($this->image_path);
    }
}
```

- [ ] **Step 7: Create the `GalleryImage` model**

Create `app/Models/GalleryImage.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Models\Concerns\CleansUpLandingMediaFile;
use App\Services\LandingMediaService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    use AssignsCatalogSortOrder, CleansUpLandingMediaFile, HasFactory;

    protected $fillable = [
        'image_path',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function imageUrl(): ?string
    {
        return app(LandingMediaService::class)->url($this->image_path);
    }
}
```

- [ ] **Step 8: Create the factories**

Create `database/factories/FeedbackImageFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedbackImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackImage>
 */
class FeedbackImageFactory extends Factory
{
    protected $model = FeedbackImage::class;

    public function definition(): array
    {
        return [
            'image_path' => 'landing/feedback/'.$this->faker->uuid().'.jpg',
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_featured' => true,
        ]);
    }
}
```

Create `database/factories/GalleryImageFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    protected $model = GalleryImage::class;

    public function definition(): array
    {
        return [
            'image_path' => 'landing/gallery/'.$this->faker->uuid().'.jpg',
            'is_featured' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_featured' => true,
        ]);
    }
}
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/FeedbackImageTest.php tests/Unit/GalleryImageTest.php`
Expected: PASS (8 tests total).

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_26_000001_create_feedback_images_table.php \
        database/migrations/2026_08_26_000002_create_gallery_images_table.php \
        app/Models/Concerns/CleansUpLandingMediaFile.php \
        app/Models/FeedbackImage.php app/Models/GalleryImage.php \
        database/factories/FeedbackImageFactory.php database/factories/GalleryImageFactory.php \
        tests/Unit/FeedbackImageTest.php tests/Unit/GalleryImageTest.php
git commit -m "feat(landing-media): add FeedbackImage and GalleryImage models"
```

---

## Task 3: Seed existing bundled feedback screenshots into `FeedbackImage`

**Files:**
- Create: `database/seeders/FeedbackImageSeeder.php`
- Test: `tests/Feature/FeedbackImageSeederTest.php`

**Interfaces:**
- Consumes: `App\Models\FeedbackImage` (Task 2).
- Produces: `Database\Seeders\FeedbackImageSeeder` — a one-time, manually-run seeder (`php artisan db:seed --class=FeedbackImageSeeder`) that imports the 18 files already committed at `public/images/feedback/*.jpg` into `feedback_images` rows, so the landing page doesn't go blank when Task 7 switches it to being database-driven. It is intentionally NOT auto-run from `DatabaseSeeder` (would re-run on every deploy reseed) — this is documented in the class docblock. Idempotent: re-running it skips paths that already have a row.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/FeedbackImageSeederTest.php`:

```php
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

        $this->directory = public_path('images/feedback');
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

        (new FeedbackImageSeeder)->run();

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

        (new FeedbackImageSeeder)->run();
        (new FeedbackImageSeeder)->run();

        $this->assertDatabaseCount('feedback_images', 1);
    }

    public function test_it_does_nothing_when_directory_is_missing(): void
    {
        File::deleteDirectory($this->directory);

        (new FeedbackImageSeeder)->run();

        $this->assertDatabaseCount('feedback_images', 0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/FeedbackImageSeederTest.php`
Expected: FAIL with "Class \"Database\Seeders\FeedbackImageSeeder\" not found".

- [ ] **Step 3: Implement the seeder**

Create `database/seeders/FeedbackImageSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FeedbackImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * One-time import of the bundled public/images/feedback/* screenshots into the
 * feedback_images table, so the landing page keeps showing them after the
 * feedback section became database + S3 driven.
 *
 * Not auto-run from DatabaseSeeder — run manually once per environment:
 * php artisan db:seed --class=FeedbackImageSeeder
 */
class FeedbackImageSeeder extends Seeder
{
    private const RELATIVE_DIRECTORY = 'images/feedback';

    /**
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function run(): void
    {
        $directory = public_path(self::RELATIVE_DIRECTORY);

        if (! is_dir($directory)) {
            return;
        }

        $files = collect(File::files($directory))
            ->filter(fn ($file): bool => in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true))
            ->sortBy(fn ($file): string => strtolower($file->getFilename()))
            ->values();

        foreach ($files as $index => $file) {
            $imagePath = self::RELATIVE_DIRECTORY.'/'.$file->getFilename();

            if (FeedbackImage::query()->where('image_path', $imagePath)->exists()) {
                continue;
            }

            FeedbackImage::query()->create([
                'image_path' => $imagePath,
                'is_featured' => $index < 3,
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/FeedbackImageSeederTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/FeedbackImageSeeder.php tests/Feature/FeedbackImageSeederTest.php
git commit -m "feat(landing-media): add one-time seeder for bundled feedback screenshots"
```

---

## Task 4: `FeedbackImageResource` (Filament admin CRUD + feature toggle)

**Files:**
- Create: `app/Filament/Resources/FeedbackImageResource.php`
- Create: `app/Filament/Resources/FeedbackImageResource/Pages/ListFeedbackImages.php`
- Create: `app/Filament/Resources/FeedbackImageResource/Pages/CreateFeedbackImage.php`
- Create: `app/Filament/Resources/FeedbackImageResource/Pages/EditFeedbackImage.php`
- Test: `tests/Feature/Filament/FeedbackImageResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\FeedbackImage` (Task 2), `App\Services\LandingMediaService` (Task 1).
- Produces: `FeedbackImageResource` navigable under the "Content" Filament navigation group, admin-only (`Auth::user()?->isAdmin()`), with a table row action `toggleFeatured` that flips `is_featured` and rejects the 4th feature attempt via a Filament notification.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/FeedbackImageResourceTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Filament/FeedbackImageResourceTest.php`
Expected: FAIL with "Class \"App\Filament\Resources\FeedbackImageResource\" not found".

- [ ] **Step 3: Implement the resource**

Create `app/Filament/Resources/FeedbackImageResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FeedbackImageResource\Pages;
use App\Models\FeedbackImage;
use App\Services\LandingMediaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FeedbackImageResource extends Resource
{
    protected static ?string $model = FeedbackImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Feedback images';

    protected static ?string $modelLabel = 'Feedback image';

    protected static ?string $pluralModelLabel = 'Feedback images';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        $service = app(LandingMediaService::class);

        return $form->schema([
            Forms\Components\FileUpload::make('image_path')
                ->label('Image')
                ->image()
                ->imagePreviewHeight('150')
                ->disk($service->disk())
                ->directory($service->feedbackDirectory())
                ->visibility('public')
                ->maxSize(5120)
                ->required()
                ->moveFiles()
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $service = app(LandingMediaService::class);

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk($service->disk())
                    ->visibility('public')
                    ->square(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\Action::make('toggleFeatured')
                    ->label(fn (FeedbackImage $record): string => $record->is_featured ? 'Unfeature' : 'Feature')
                    ->icon('heroicon-o-star')
                    ->color(fn (FeedbackImage $record): string => $record->is_featured ? 'warning' : 'gray')
                    ->action(function (FeedbackImage $record): void {
                        if (! $record->is_featured && FeedbackImage::query()->where('is_featured', true)->count() >= 3) {
                            Notification::make()
                                ->danger()
                                ->title('Only 3 images can be featured')
                                ->body('Unfeature another image first.')
                                ->send();

                            return;
                        }

                        $record->update(['is_featured' => ! $record->is_featured]);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedbackImages::route('/'),
            'create' => Pages\CreateFeedbackImage::route('/create'),
            'edit' => Pages\EditFeedbackImage::route('/{record}/edit'),
        ];
    }
}
```

Create `app/Filament/Resources/FeedbackImageResource/Pages/ListFeedbackImages.php`:

```php
<?php

namespace App\Filament\Resources\FeedbackImageResource\Pages;

use App\Filament\Resources\FeedbackImageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeedbackImages extends ListRecords
{
    protected static string $resource = FeedbackImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
        ];
    }
}
```

Create `app/Filament/Resources/FeedbackImageResource/Pages/CreateFeedbackImage.php`:

```php
<?php

namespace App\Filament\Resources\FeedbackImageResource\Pages;

use App\Filament\Resources\FeedbackImageResource;
use App\Filament\Resources\Pages\CreateRecord;

class CreateFeedbackImage extends CreateRecord
{
    protected static string $resource = FeedbackImageResource::class;
}
```

Create `app/Filament/Resources/FeedbackImageResource/Pages/EditFeedbackImage.php`:

```php
<?php

namespace App\Filament\Resources\FeedbackImageResource\Pages;

use App\Filament\Resources\FeedbackImageResource;
use Filament\Resources\Pages\EditRecord;

class EditFeedbackImage extends EditRecord
{
    protected static string $resource = FeedbackImageResource::class;
}
```

> Note: `App\Filament\Resources\Pages\CreateRecord` is the same base class `CategoryResource\Pages\CreateCategory` extends in this codebase (see `app/Filament/Resources/CategoryResource/Pages/CreateCategory.php`) — confirm the exact namespace matches by checking that file if `composer dump-autoload` reports a class-not-found error; it is a thin app-level wrapper around Filament's own `CreateRecord`, not Filament's class directly.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Filament/FeedbackImageResourceTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Resources/FeedbackImageResource.php app/Filament/Resources/FeedbackImageResource \
        tests/Feature/Filament/FeedbackImageResourceTest.php
git commit -m "feat(admin): add FeedbackImageResource with capped feature toggle"
```

---

## Task 5: `GalleryImageResource` (mirrors Task 4 for gallery images)

**Files:**
- Create: `app/Filament/Resources/GalleryImageResource.php`
- Create: `app/Filament/Resources/GalleryImageResource/Pages/ListGalleryImages.php`
- Create: `app/Filament/Resources/GalleryImageResource/Pages/CreateGalleryImage.php`
- Create: `app/Filament/Resources/GalleryImageResource/Pages/EditGalleryImage.php`
- Test: `tests/Feature/Filament/GalleryImageResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\GalleryImage` (Task 2), `App\Services\LandingMediaService` (Task 1).
- Produces: `GalleryImageResource`, structurally identical to `FeedbackImageResource` but pointed at `GalleryImage`/`galleryDirectory()` — deliberately a separate resource class, not a shared parameterized base, per the client's "don't combine these two" requirement.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/GalleryImageResourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\GalleryImageResource;
use App\Filament\Resources\GalleryImageResource\Pages\CreateGalleryImage;
use App\Filament\Resources\GalleryImageResource\Pages\ListGalleryImages;
use App\Models\GalleryImage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryImageResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Storage::fake('dmf_s3');
        config([
            'landing-media.disk' => 'dmf_s3',
            'landing-media.gallery_directory' => 'landing/gallery',
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_list_gallery_images(): void
    {
        $admin = $this->makeAdmin();
        $images = GalleryImage::factory()->count(3)->create();

        $this->actingAs($admin);

        Livewire::test(ListGalleryImages::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($images);
    }

    public function test_admin_can_upload_a_gallery_image(): void
    {
        $admin = $this->makeAdmin();
        $upload = UploadedFile::fake()->image('gallery.jpg');

        $this->actingAs($admin);

        Livewire::test(CreateGalleryImage::class)
            ->fillForm([
                'image_path' => $upload,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $image = GalleryImage::query()->latest('id')->firstOrFail();

        $this->assertStringStartsWith('landing/gallery/', (string) $image->image_path);
        Storage::disk('dmf_s3')->assertExists((string) $image->image_path);
    }

    public function test_featuring_a_fourth_image_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        GalleryImage::factory()->featured()->count(3)->create();
        $fourth = GalleryImage::factory()->create(['is_featured' => false]);

        $this->actingAs($admin);

        Livewire::test(ListGalleryImages::class)
            ->callTableAction('toggleFeatured', $fourth);

        $this->assertFalse($fourth->fresh()->is_featured);
        $this->assertSame(3, GalleryImage::query()->where('is_featured', true)->count());
    }

    public function test_assistant_cannot_access_gallery_image_resource(): void
    {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant);

        $this->assertFalse(GalleryImageResource::canViewAny());

        Livewire::test(ListGalleryImages::class)->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Filament/GalleryImageResourceTest.php`
Expected: FAIL with "Class \"App\Filament\Resources\GalleryImageResource\" not found".

- [ ] **Step 3: Implement the resource**

Create `app/Filament/Resources/GalleryImageResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryImageResource\Pages;
use App\Models\GalleryImage;
use App\Services\LandingMediaService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class GalleryImageResource extends Resource
{
    protected static ?string $model = GalleryImage::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Gallery images';

    protected static ?string $modelLabel = 'Gallery image';

    protected static ?string $pluralModelLabel = 'Gallery images';

    public static function canViewAny(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        $service = app(LandingMediaService::class);

        return $form->schema([
            Forms\Components\FileUpload::make('image_path')
                ->label('Image')
                ->image()
                ->imagePreviewHeight('150')
                ->disk($service->disk())
                ->directory($service->galleryDirectory())
                ->visibility('public')
                ->maxSize(5120)
                ->required()
                ->moveFiles()
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $service = app(LandingMediaService::class);

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk($service->disk())
                    ->visibility('public')
                    ->square(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\Action::make('toggleFeatured')
                    ->label(fn (GalleryImage $record): string => $record->is_featured ? 'Unfeature' : 'Feature')
                    ->icon('heroicon-o-star')
                    ->color(fn (GalleryImage $record): string => $record->is_featured ? 'warning' : 'gray')
                    ->action(function (GalleryImage $record): void {
                        if (! $record->is_featured && GalleryImage::query()->where('is_featured', true)->count() >= 3) {
                            Notification::make()
                                ->danger()
                                ->title('Only 3 images can be featured')
                                ->body('Unfeature another image first.')
                                ->send();

                            return;
                        }

                        $record->update(['is_featured' => ! $record->is_featured]);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGalleryImages::route('/'),
            'create' => Pages\CreateGalleryImage::route('/create'),
            'edit' => Pages\EditGalleryImage::route('/{record}/edit'),
        ];
    }
}
```

Create `app/Filament/Resources/GalleryImageResource/Pages/ListGalleryImages.php`:

```php
<?php

namespace App\Filament\Resources\GalleryImageResource\Pages;

use App\Filament\Resources\GalleryImageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGalleryImages extends ListRecords
{
    protected static string $resource = GalleryImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus'),
        ];
    }
}
```

Create `app/Filament/Resources/GalleryImageResource/Pages/CreateGalleryImage.php`:

```php
<?php

namespace App\Filament\Resources\GalleryImageResource\Pages;

use App\Filament\Resources\GalleryImageResource;
use App\Filament\Resources\Pages\CreateRecord;

class CreateGalleryImage extends CreateRecord
{
    protected static string $resource = GalleryImageResource::class;
}
```

Create `app/Filament/Resources/GalleryImageResource/Pages/EditGalleryImage.php`:

```php
<?php

namespace App\Filament\Resources\GalleryImageResource\Pages;

use App\Filament\Resources\GalleryImageResource;
use Filament\Resources\Pages\EditRecord;

class EditGalleryImage extends EditRecord
{
    protected static string $resource = GalleryImageResource::class;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Filament/GalleryImageResourceTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Resources/GalleryImageResource.php app/Filament/Resources/GalleryImageResource \
        tests/Feature/Filament/GalleryImageResourceTest.php
git commit -m "feat(admin): add GalleryImageResource with capped feature toggle"
```

---

## Task 6: Dedicated public feedback/gallery pages + routes

**Files:**
- Create: `app/Http/Controllers/LandingMediaController.php`
- Create: `resources/views/feedback/index.blade.php`
- Create: `resources/views/gallery/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/LandingMediaPagesTest.php`

**Interfaces:**
- Consumes: `App\Models\FeedbackImage`, `App\Models\GalleryImage` (Task 2), `App\Services\LandingMediaService` (Task 1).
- Produces: routes named `feedback` (`GET /feedback`) and `gallery` (`GET /gallery`), each rendering all active images (paginated, 24/page). Task 7's landing page "See more"/CTA links point at these route names.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LandingMediaPagesTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/LandingMediaPagesTest.php`
Expected: FAIL — route `feedback`/`gallery` not defined (`RouteNotFoundException`).

- [ ] **Step 3: Add the routes**

In `routes/web.php`, add near the top-level imports:

```php
use App\Http\Controllers\LandingMediaController;
```

Add after the `Route::redirect('/admin', '/admin/enrollments');` line at the end of the file:

```php
Route::get('/feedback', [LandingMediaController::class, 'feedback'])->name('feedback');
Route::get('/gallery', [LandingMediaController::class, 'gallery'])->name('gallery');
```

- [ ] **Step 4: Implement the controller**

Create `app/Http/Controllers/LandingMediaController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FeedbackImage;
use App\Models\GalleryImage;
use App\Services\LandingMediaService;
use Illuminate\Contracts\View\View;

class LandingMediaController extends Controller
{
    public function __construct(protected LandingMediaService $landingMediaService) {}

    public function feedback(): View
    {
        $images = FeedbackImage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(24);

        return view('feedback.index', [
            'images' => $images,
            'landingMediaService' => $this->landingMediaService,
        ]);
    }

    public function gallery(): View
    {
        $images = GalleryImage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(24);

        return view('gallery.index', [
            'images' => $images,
            'landingMediaService' => $this->landingMediaService,
        ]);
    }
}
```

- [ ] **Step 5: Implement the views**

Create `resources/views/feedback/index.blade.php`:

```blade
@extends('layouts.enrollment')

@section('title', 'Student Feedback — DMF Dental Training Center')

@section('content')
<section class="bg-gray-50 py-16 md:py-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10 md:mb-12">
            <a href="{{ route('home') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Back to home</a>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-4">Student Feedback</h1>
            <p class="text-base text-gray-500 mt-3 max-w-2xl mx-auto">Real Facebook feedback from students and board passers.</p>
        </div>

        @if($images->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-12 text-center">
                <p class="text-sm text-gray-500">No feedback screenshots yet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
                @foreach($images as $image)
                    <a
                        href="{{ $landingMediaService->url($image->image_path) }}"
                        target="_blank"
                        rel="noopener"
                        class="block w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft"
                    >
                        <div class="aspect-[4/5] overflow-hidden bg-brand-50">
                            <img
                                src="{{ $landingMediaService->url($image->image_path) }}"
                                alt="Student feedback screenshot"
                                class="h-full w-full object-cover object-top"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $images->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
```

Create `resources/views/gallery/index.blade.php`:

```blade
@extends('layouts.enrollment')

@section('title', 'Gallery — DMF Dental Training Center')

@section('content')
<section class="bg-white py-16 md:py-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10 md:mb-12">
            <a href="{{ route('home') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Back to home</a>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-4">Gallery</h1>
            <p class="text-base text-gray-500 mt-3 max-w-2xl mx-auto">A look at our facilities, training sessions, and student life.</p>
        </div>

        @if($images->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-12 text-center">
                <p class="text-sm text-gray-500">No gallery photos yet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
                @foreach($images as $image)
                    <a
                        href="{{ $landingMediaService->url($image->image_path) }}"
                        target="_blank"
                        rel="noopener"
                        class="block w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft"
                    >
                        <div class="aspect-[4/5] overflow-hidden bg-brand-50">
                            <img
                                src="{{ $landingMediaService->url($image->image_path) }}"
                                alt="Gallery photo"
                                class="h-full w-full object-cover object-top"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $images->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/LandingMediaPagesTest.php`
Expected: PASS (4 tests).

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/LandingMediaController.php resources/views/feedback/index.blade.php \
        resources/views/gallery/index.blade.php routes/web.php tests/Feature/LandingMediaPagesTest.php
git commit -m "feat(landing-media): add dedicated feedback and gallery pages"
```

---

## Task 7: Wire the landing page + retire the old static feedback gallery

**Files:**
- Modify: `app/Http/Controllers/EnrollmentController.php`
- Modify: `resources/views/enrollment/landing.blade.php`
- Modify: `tests/Feature/EnrollmentLandingPageTest.php`
- Delete: `app/Support/LandingFeedbackGallery.php`
- Delete: `tests/Unit/LandingFeedbackGalleryTest.php`

**Interfaces:**
- Consumes: `App\Models\FeedbackImage`, `App\Models\GalleryImage` (Task 2). The landing page's `feedbackImages`/`galleryImages` view variables become plain `list<string>` arrays of already-resolved image URLs (same shape the Alpine `x-data` binding used before), so the existing lightbox JS stays untouched.

- [ ] **Step 1: Write the failing test — replace the old feedback test, add a gallery test**

In `tests/Feature/EnrollmentLandingPageTest.php`, replace the existing `test_landing_renders_feedback_gallery_when_screenshots_exist` method (and its `use` statements) with:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FeedbackImage;
use App\Models\GalleryImage;
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

    // ... existing non-feedback tests stay unchanged above this line ...

    public function test_landing_shows_only_featured_active_feedback_images_with_see_more_link(): void
    {
        $featured = FeedbackImage::factory()->featured()->count(3)->create();
        FeedbackImage::factory()->create(['is_featured' => false]);
        FeedbackImage::factory()->featured()->create(['is_active' => false]);

        foreach ($featured as $image) {
            Storage::disk('dmf_s3')->put($image->image_path, 'fake-image');
        }

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('What Our Graduates Say');
        $response->assertSee('feedback-gallery-item', false);
        $response->assertSee(route('feedback'), false);
        $response->assertSee('See more feedback');

        foreach ($featured as $image) {
            $response->assertSee($image->image_path, false);
        }
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
        GalleryImage::factory()->create(['is_featured' => false]);

        foreach ($featured as $image) {
            Storage::disk('dmf_s3')->put($image->image_path, 'fake-image');
        }

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Inside DMF Dental Review Center');
        $response->assertSee(route('gallery'), false);
        $response->assertSee('View full gallery');

        foreach ($featured as $image) {
            $response->assertSee($image->image_path, false);
        }
    }

    public function test_landing_shows_gallery_empty_state_when_no_featured_images(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Gallery photos will appear here once uploaded from the admin panel.');
    }
}
```

Keep every other existing test method in the file exactly as-is — only the `setUp()` (new), the `use` imports (add `FeedbackImage`, `GalleryImage`, `Storage`), and the one feedback test being replaced change.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/EnrollmentLandingPageTest.php`
Expected: FAIL — `feedback-gallery-item` markup still comes from the old static `LandingFeedbackGallery` scan, `route('feedback')`/`route('gallery')` links and the new gallery section heading don't exist yet on the page.

- [ ] **Step 3: Update `EnrollmentController::landing()`**

In `app/Http/Controllers/EnrollmentController.php`, replace the import `use App\Support\LandingFeedbackGallery;` with:

```php
use App\Models\FeedbackImage;
use App\Models\GalleryImage;
```

Replace the `landing()` method body:

```php
public function landing()
{
    $packages = CatalogOptionsCache::landingPagePackages();

    $feedbackImages = FeedbackImage::query()
        ->where('is_active', true)
        ->where('is_featured', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get()
        ->map(fn (FeedbackImage $image): ?string => $image->imageUrl())
        ->filter()
        ->values()
        ->all();

    $galleryImages = GalleryImage::query()
        ->where('is_active', true)
        ->where('is_featured', true)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get()
        ->map(fn (GalleryImage $image): ?string => $image->imageUrl())
        ->filter()
        ->values()
        ->all();

    return view('enrollment.landing', compact('packages', 'feedbackImages', 'galleryImages'));
}
```

- [ ] **Step 4: Rewrite the feedback section in `landing.blade.php`**

In `resources/views/enrollment/landing.blade.php`, replace lines 401–567 (the entire `FEEDBACK GALLERY` section, from the `{{-- ═══ FEEDBACK GALLERY ... --}}` comment through its closing `</section>`) with:

```blade
{{-- ════════════════════════════════════════
    FEEDBACK GALLERY (Facebook screenshots)
════════════════════════════════════════ --}}
@php
    $feedbackTotal = count($feedbackImages ?? []);
@endphp
<section
    class="bg-gray-50 py-16 md:py-20"
    id="stories"
    x-data="{
        images: @js(array_values($feedbackImages ?? [])),
        open: false,
        activeIndex: 0,
        openLightbox(index) {
            this.activeIndex = Number(index) || 0;
            this.open = true;
            document.body.classList.add('overflow-hidden');
        },
        closeLightbox() {
            this.open = false;
            document.body.classList.remove('overflow-hidden');
        },
        next() {
            if (this.images.length === 0) { return; }
            this.activeIndex = (this.activeIndex + 1) % this.images.length;
        },
        prev() {
            if (this.images.length === 0) { return; }
            this.activeIndex = (this.activeIndex - 1 + this.images.length) % this.images.length;
        },
        activeSrc() {
            return this.images[this.activeIndex] || '';
        }
    }"
    @keydown.escape.window="if (open) closeLightbox()"
    @keydown.arrow-right.window="if (open) next()"
    @keydown.arrow-left.window="if (open) prev()"
>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="land-reveal text-center mb-10 md:mb-12">
            <span class="text-sm font-semibold uppercase tracking-widest text-brand-600">Success Stories</span>
            <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-2">What Our Graduates Say</h2>
            <p class="text-base text-gray-500 mt-3 max-w-2xl mx-auto">Real Facebook feedback from students and board passers. Tap a card to read it clearly.</p>
        </div>

        @if($feedbackTotal > 0)
            <div class="land-stagger grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
                @foreach($feedbackImages as $index => $imageUrl)
                    <button
                        type="button"
                        class="feedback-gallery-item group relative block w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                        @click="openLightbox({{ $index }})"
                        aria-label="View feedback screenshot {{ $index + 1 }}"
                    >
                        <div class="aspect-[4/5] overflow-hidden bg-brand-50">
                            <img
                                src="{{ $imageUrl }}"
                                alt="Student feedback screenshot {{ $index + 1 }}"
                                class="h-full w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.03]"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-950/70 via-brand-950/20 to-transparent px-4 pb-3 pt-10">
                            <span class="text-xs font-semibold text-white/95">Tap to enlarge</span>
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="mt-8 flex justify-center">
                <a
                    href="{{ route('feedback') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-brand-950 text-white text-sm font-bold shadow-md hover:bg-brand-800 transition-colors"
                >
                    See more feedback
                </a>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-12 text-center">
                <p class="text-sm text-gray-500">Feedback screenshots will appear here once uploaded from the admin panel.</p>
            </div>
        @endif
    </div>

    {{-- Lightbox teleported to body so position:fixed isn't trapped by page transform animations --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[200] flex items-center justify-center p-3 sm:p-8"
            role="dialog"
            aria-modal="true"
            aria-label="Feedback screenshot"
        >
            <div class="absolute inset-0 bg-brand-950/85 backdrop-blur-sm" @click="closeLightbox()"></div>

            <div class="relative z-10 flex w-full max-w-4xl flex-col items-center" @click.stop>
                <div class="mb-3 flex w-full items-center justify-between gap-3 px-1">
                    <p class="text-xs font-semibold text-white/80" x-text="(activeIndex + 1) + ' / ' + images.length"></p>
                    <button
                        type="button"
                        class="w-10 h-10 rounded-full bg-white text-brand-900 shadow-md flex items-center justify-center hover:bg-accent-500 transition-colors"
                        @click="closeLightbox()"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="relative flex w-full items-center justify-center min-h-[40vh]">
                    <button
                        type="button"
                        class="absolute left-0 sm:-left-3 z-20 w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-white text-brand-900 shadow-md flex items-center justify-center hover:bg-accent-500 transition-colors"
                        @click="prev()"
                        aria-label="Previous feedback"
                        x-show="images.length > 1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <img
                        :src="activeSrc()"
                        alt="Enlarged feedback screenshot"
                        class="max-h-[80vh] w-auto max-w-[min(100%,56rem)] rounded-xl shadow-card object-contain bg-white"
                    >

                    <button
                        type="button"
                        class="absolute right-0 sm:-right-3 z-20 w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-white text-brand-900 shadow-md flex items-center justify-center hover:bg-accent-500 transition-colors"
                        @click="next()"
                        aria-label="Next feedback"
                        x-show="images.length > 1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </template>
</section>


{{-- ════════════════════════════════════════
    GALLERY SECTION
════════════════════════════════════════ --}}
@php
    $galleryTotal = count($galleryImages ?? []);
@endphp
<section class="bg-white py-16 md:py-20" id="gallery">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="land-reveal text-center mb-10 md:mb-12">
            <span class="text-sm font-semibold uppercase tracking-widest text-brand-600">Gallery</span>
            <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-2">Inside DMF Dental Review Center</h2>
            <p class="text-base text-gray-500 mt-3 max-w-2xl mx-auto">A look at our facilities, training sessions, and student life.</p>
        </div>

        @if($galleryTotal > 0)
            <div class="land-stagger grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
                @foreach($galleryImages as $index => $imageUrl)
                    <div class="relative block w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft">
                        <div class="aspect-[4/5] overflow-hidden bg-brand-50">
                            <img
                                src="{{ $imageUrl }}"
                                alt="Gallery photo {{ $index + 1 }}"
                                class="h-full w-full object-cover object-top"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 flex justify-center">
                <a
                    href="{{ route('gallery') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-brand-950 text-white text-sm font-bold shadow-md hover:bg-brand-800 transition-colors"
                >
                    View full gallery
                </a>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-12 text-center">
                <p class="text-sm text-gray-500">Gallery photos will appear here once uploaded from the admin panel.</p>
            </div>
        @endif
    </div>
</section>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/EnrollmentLandingPageTest.php`
Expected: PASS (all tests in the file, including the 4 new/updated feedback+gallery tests).

- [ ] **Step 6: Retire the old static feedback gallery**

Delete `app/Support/LandingFeedbackGallery.php` and `tests/Unit/LandingFeedbackGalleryTest.php`:

```bash
git rm app/Support/LandingFeedbackGallery.php tests/Unit/LandingFeedbackGalleryTest.php
```

- [ ] **Step 7: Run the full landing/feedback/gallery test surface**

Run: `php artisan test --compact tests/Feature/EnrollmentLandingPageTest.php tests/Feature/LandingMediaPagesTest.php tests/Feature/Filament/FeedbackImageResourceTest.php tests/Feature/Filament/GalleryImageResourceTest.php tests/Unit/FeedbackImageTest.php tests/Unit/GalleryImageTest.php tests/Unit/LandingMediaServiceTest.php tests/Feature/FeedbackImageSeederTest.php`
Expected: PASS (no leftover references to the deleted `LandingFeedbackGallery` class anywhere).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/EnrollmentController.php resources/views/enrollment/landing.blade.php \
        tests/Feature/EnrollmentLandingPageTest.php
git commit -m "feat(landing): show featured feedback/gallery images from the database, retire static scan"
```

- [ ] **Step 9: Ask the user whether to run the entire test suite**

Per the phpunit/core rule in `CLAUDE.md`: once the tests relating to this feature are passing, ask the user if they'd like the entire suite run (`php artisan test --compact`) before considering the feature done.

---

## Post-plan manual step (not part of any task — do not automate)

After Task 3 and Task 7 are deployed to an environment with real `AWS_*`/`dmf_s3` credentials, run once per environment (staging, then production) to preserve the 18 existing bundled feedback screenshots as `FeedbackImage` rows:

```bash
php artisan db:seed --class=FeedbackImageSeeder --force
```

This is deliberately manual (see Task 3's docblock) — it must not run automatically on every deploy/reseed, since it would be redundant after the first run (though it is idempotent and safe to re-run).
