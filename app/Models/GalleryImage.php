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
