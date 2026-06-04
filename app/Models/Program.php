<?php

namespace App\Models;

use App\Models\Concerns\AssignsCatalogSortOrder;
use App\Models\Concerns\SyncsCatalogCategoryString;
use App\Support\Filament\CatalogOptionsCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use AssignsCatalogSortOrder;
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

    public function isFirstEarlyBirdActive(): bool
    {
        return $this->price_early !== null
            && $this->early_deadline !== null
            && now()->timezone('Asia/Manila')->startOfDay()->lte($this->early_deadline);
    }

    public function isSecondEarlyBirdActive(): bool
    {
        if ($this->isFirstEarlyBirdActive()) {
            return false;
        }

        return $this->price_early_2 !== null
            && $this->early_deadline_2 !== null
            && now()->timezone('Asia/Manila')->startOfDay()->lte($this->early_deadline_2);
    }

    public function isEarlyBirdActive(): bool
    {
        return $this->isFirstEarlyBirdActive() || $this->isSecondEarlyBirdActive();
    }

    public function getActivePriceAttribute(): int
    {
        if ($this->isFirstEarlyBirdActive()) {
            return (int) $this->price_early;
        }

        if ($this->isSecondEarlyBirdActive()) {
            return (int) $this->price_early_2;
        }

        return (int) $this->price_full;
    }

    public function getDownpaymentAmountAttribute(): int
    {
        return (int) round(((int) $this->price_full) * 0.5);
    }
}
