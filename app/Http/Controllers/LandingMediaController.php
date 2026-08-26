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
