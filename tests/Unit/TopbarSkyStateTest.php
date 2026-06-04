<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TopbarSkyState;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TopbarSkyStateTest extends TestCase
{
    #[DataProvider('skyStateProvider')]
    public function test_resolves_greeting_and_icon_opacities(
        string $time,
        string $expectedGreeting,
        float $expectedMoon,
        float $expectedSunset,
        float $expectedSun,
    ): void {
        $state = TopbarSkyState::resolve(Carbon::parse($time, config('app.display_timezone')));

        $this->assertSame($expectedGreeting, $state['greeting']);
        $this->assertSame($expectedMoon, $state['moon_opacity']);
        $this->assertSame($expectedSunset, $state['sunset_opacity']);
        $this->assertSame($expectedSun, $state['sun_opacity']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: float, 3: float, 4: float}>
     */
    public static function skyStateProvider(): array
    {
        return [
            'early morning' => ['2026-05-24 03:20:00', 'Good Evening', 1.0, 0.0, 0.0],
            'late night' => ['2026-05-24 23:15:00', 'Good Evening', 1.0, 0.0, 0.0],
            'dawn start' => ['2026-05-24 05:00:00', 'Good Morning', 1.0, 0.0, 0.0],
            'dawn peak sunset' => ['2026-05-24 05:45:00', 'Good Morning', 0.5, 1.0, 0.0],
            'dawn midpoint' => ['2026-05-24 06:00:00', 'Good Morning', 0.33, 0.67, 0.33],
            'after sunrise' => ['2026-05-24 07:00:00', 'Good Morning', 0.0, 0.0, 1.0],
            'midday' => ['2026-05-24 12:30:00', 'Good Afternoon', 0.0, 0.0, 1.0],
            'dusk peak sunset' => ['2026-05-24 18:15:00', 'Good Evening', 0.5, 1.0, 0.0],
            'dusk midpoint' => ['2026-05-24 18:00:00', 'Good Evening', 0.0, 0.67, 0.33],
            'after sunset' => ['2026-05-24 19:30:00', 'Good Evening', 1.0, 0.0, 0.0],
        ];
    }

    #[DataProvider('skySceneProvider')]
    public function test_resolves_animated_sky_scene(
        string $time,
        float $expectedSunY,
        float $expectedMoonY,
        float $expectedSunOpacity,
        float $expectedMoonOpacity,
    ): void {
        $minutes = Carbon::parse($time, config('app.display_timezone'));
        $totalMinutes = ($minutes->hour * 60) + $minutes->minute + ($minutes->second / 60);
        $scene = TopbarSkyState::resolveSceneFromMinutes($totalMinutes);

        $this->assertSame($expectedSunY, $scene['sun_y']);
        $this->assertSame($expectedMoonY, $scene['moon_y']);
        $this->assertSame($expectedSunOpacity, $scene['sun_opacity']);
        $this->assertSame($expectedMoonOpacity, $scene['moon_opacity']);
    }

    /**
     * @return array<string, array{0: string, 1: float, 2: float, 3: float, 4: float}>
     */
    public static function skySceneProvider(): array
    {
        return [
            'night moon high' => ['2026-05-24 03:20:00', 48.0, -42.0, 0.0, 1.0],
            'midday sun high' => ['2026-05-24 12:30:00', -49.92, 48.0, 1.0, 0.0],
            'sunrise midpoint' => ['2026-05-24 05:45:00', 3.0, 3.0, 0.5, 0.5],
            'sunset midpoint' => ['2026-05-24 18:15:00', 3.0, 3.0, 0.5, 0.5],
        ];
    }
}
