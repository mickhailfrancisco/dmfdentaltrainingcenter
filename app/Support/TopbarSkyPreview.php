<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Dev-only topbar sky preview configuration (APP_DEBUG=true).
 *
 * Supports URL query `sky_sim`:
 * - `cycle` — auto-advance through a full day
 * - `HH:MM` — fixed simulated clock (e.g. `05:45`, `18:15`)
 *
 * @author CKD
 *
 * @created 2026-05-30
 */
final class TopbarSkyPreview
{
    /**
     * @var array<string, array{label: string, minutes: int}>
     */
    public const PRESETS = [
        'night' => ['label' => '3 AM', 'minutes' => 180],
        'dawn' => ['label' => '5:45 AM', 'minutes' => 345],
        'day' => ['label' => '10 AM', 'minutes' => 600],
        'dusk' => ['label' => '6:15 PM', 'minutes' => 1095],
        'evening' => ['label' => '8 PM', 'minutes' => 1200],
    ];

    public static function isEnabled(): bool
    {
        return (bool) config('app.debug');
    }

    /**
     * @return array{
     *     enabled: bool,
     *     mode: 'live'|'fixed'|'cycle',
     *     minutes: int,
     * }
     */
    public static function resolve(): array
    {
        if (! self::isEnabled()) {
            return [
                'enabled' => false,
                'mode' => 'live',
                'minutes' => 0,
            ];
        }

        $param = request()->query('sky_sim');

        if ($param === 'cycle') {
            return [
                'enabled' => true,
                'mode' => 'cycle',
                'minutes' => 240,
            ];
        }

        if (is_string($param) && preg_match('/^(\d{1,2}):(\d{2})$/', $param, $matches)) {
            return [
                'enabled' => true,
                'mode' => 'fixed',
                'minutes' => self::minutesFromClock((int) $matches[1], (int) $matches[2]),
            ];
        }

        return [
            'enabled' => true,
            'mode' => 'live',
            'minutes' => 0,
        ];
    }

    public static function minutesFromClock(int $hour, int $minute): int
    {
        return max(0, min(1439, ($hour * 60) + $minute));
    }

    /**
     * @return array{
     *     greeting: string,
     *     moon_opacity: float,
     *     sunset_opacity: float,
     *     sun_opacity: float,
     * }
     */
    public static function skyStateForMinutes(int $minutes): array
    {
        return TopbarSkyState::resolveFromMinutes($minutes);
    }

    /**
     * @return array{
     *     sun_y: float,
     *     moon_y: float,
     *     sun_opacity: float,
     *     moon_opacity: float,
     *     sky_top: string,
     *     sky_bottom: string,
     *     glow_opacity: float,
     * }
     */
    public static function skySceneForMinutes(float $minutes): array
    {
        return TopbarSkyState::resolveSceneFromMinutes($minutes);
    }
}
