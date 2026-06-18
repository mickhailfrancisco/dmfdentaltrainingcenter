<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Models\Concerns\HasEarlyBirdPricing;
use App\Models\Concerns\SyncsCatalogCategoryString;
use App\Support\Filament\CatalogOptionsCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use AssignsCatalogSortOrder;
    use HasEarlyBirdPricing;
    use HasFactory;
    use SyncsCatalogCategoryString;

    protected $fillable = [
        'name', 'slug', 'category', 'tag',
        'category_id',
        'price_full', 'price_early', 'early_deadline',
        'price_early_2', 'early_deadline_2',
        'early_bird_label',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'early_deadline' => 'date',
        'early_deadline_2' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => CatalogOptionsCache::forgetAll());
        static::deleted(fn () => CatalogOptionsCache::forgetAll());
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_program', 'program_id', 'package_id')
            ->withPivot(['sort_order']);
    }

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->categoryModel?->name ?: (string) $this->category;
    }
}
