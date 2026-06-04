<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Resolves topbar greeting text and sky icon opacities from local time.
 *
 * Morning (05:00–06:30): moon → sunset → sun crossfade.
 * Evening (17:30–19:00): sun → sunset → moon crossfade.
 *
 * @author CKD
 *
 * @created 2026-05-24
 */
final class TopbarSkyState
{
    private const int MORNING_START = 5 * 60;

    private const int SUNRISE_END = 6 * 60 + 30;

    private const int AFTERNOON_END = 18 * 60;

    private const int SUNSET_START = 17 * 60 + 30;

    private const int SUNSET_END = 19 * 60;

    /**
     * @return array{
     *     greeting: string,
     *     moon_opacity: float,
     *     sunset_opacity: float,
     *     sun_opacity: float,
     * }
     */
    public static function resolve(Carbon $now): array
    {
        $minutes = ($now->hour * 60) + $now->minute;

        return self::resolveFromMinutes($minutes);
    }

    /**
     * @return array{
     *     greeting: string,
     *     moon_opacity: float,
     *     sunset_opacity: float,
     *     sun_opacity: float,
     * }
     */
    public static function resolveFromMinutes(int $minutes): array
    {
        $minutes = max(0, min(1439, $minutes));

        return [
            'greeting' => self::greetingFor($minutes),
            'moon_opacity' => self::moonOpacityFor($minutes),
            'sunset_opacity' => self::sunsetOpacityFor($minutes),
            'sun_opacity' => self::sunOpacityFor($minutes),
        ];
    }

    /**
     * Animated sky scene offsets for the topbar celestial viewport.
     *
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
    public static function resolveSceneFromMinutes(float $minutes): array
    {
        $minutes = max(0.0, min(1439.99, $minutes));

        $zenith = -42.0;
        $hidden = 48.0;

        $sunY = $hidden;
        $moonY = $zenith;
        $sunOpacity = 0.0;
        $moonOpacity = 1.0;
        $skyTop = '#1e1b4b';
        $skyBottom = '#4338ca';
        $glowOpacity = 0.0;

        if ($minutes >= self::SUNRISE_END && $minutes < self::SUNSET_START) {
            $dayProgress = ($minutes - self::SUNRISE_END) / (self::SUNSET_START - self::SUNRISE_END);
            $arc = sin($dayProgress * M_PI) * -8.0;

            $sunY = $zenith + $arc;
            $moonY = $hidden;
            $sunOpacity = 1.0;
            $moonOpacity = 0.0;
            $skyTop = '#38bdf8';
            $skyBottom = '#fef08a';
        } elseif ($minutes >= self::MORNING_START && $minutes < self::SUNRISE_END) {
            $progress = ($minutes - self::MORNING_START) / (self::SUNRISE_END - self::MORNING_START);
            $eased = self::easeInOut($progress);

            $sunY = self::lerp($hidden, $zenith, $eased);
            $moonY = self::lerp($zenith, $hidden, $eased);
            $sunOpacity = self::clamp(($progress - 0.35) / 0.3, 0.0, 1.0);
            $moonOpacity = self::clamp((0.65 - $progress) / 0.3, 0.0, 1.0);
            $glowOpacity = sin($progress * M_PI) * 0.85;
            $skyTop = self::lerpColor('#1e1b4b', '#fb923c', min(1.0, $progress * 1.6));
            $skyBottom = self::lerpColor('#4338ca', '#fef08a', min(1.0, max(0.0, ($progress - 0.2) * 1.4)));
        } elseif ($minutes >= self::SUNSET_START && $minutes < self::SUNSET_END) {
            $progress = ($minutes - self::SUNSET_START) / (self::SUNSET_END - self::SUNSET_START);
            $eased = self::easeInOut($progress);

            $sunY = self::lerp($zenith, $hidden, $eased);
            $moonY = self::lerp($hidden, $zenith, $eased);
            $sunOpacity = self::clamp((0.65 - $progress) / 0.3, 0.0, 1.0);
            $moonOpacity = self::clamp(($progress - 0.35) / 0.3, 0.0, 1.0);
            $glowOpacity = sin($progress * M_PI) * 0.85;
            $skyTop = self::lerpColor('#38bdf8', '#fb923c', min(1.0, $progress * 1.6));
            $skyBottom = self::lerpColor('#fef08a', '#4338ca', min(1.0, max(0.0, ($progress - 0.2) * 1.4)));
        }

        return [
            'sun_y' => round($sunY, 2),
            'moon_y' => round($moonY, 2),
            'sun_opacity' => round($sunOpacity, 2),
            'moon_opacity' => round($moonOpacity, 2),
            'sky_top' => $skyTop,
            'sky_bottom' => $skyBottom,
            'glow_opacity' => round($glowOpacity, 2),
        ];
    }

    private static function easeInOut(float $progress): float
    {
        if ($progress <= 0.5) {
            return 2.0 * $progress * $progress;
        }

        return 1.0 - pow(-2.0 * $progress + 2.0, 2.0) / 2.0;
    }

    private static function lerp(float $from, float $to, float $progress): float
    {
        return $from + (($to - $from) * self::clamp($progress, 0.0, 1.0));
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function lerpColor(string $from, string $to, float $progress): string
    {
        $progress = self::clamp($progress, 0.0, 1.0);
        [$r1, $g1, $b1] = self::hexToRgb($from);
        [$r2, $g2, $b2] = self::hexToRgb($to);

        $red = (int) round(self::lerp((float) $r1, (float) $r2, $progress));
        $green = (int) round(self::lerp((float) $g1, (float) $g2, $progress));
        $blue = (int) round(self::lerp((float) $b1, (float) $b2, $progress));

        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

    private static function greetingFor(int $minutes): string
    {
        if ($minutes >= self::MORNING_START && $minutes < 12 * 60) {
            return 'Good Morning';
        }

        if ($minutes >= 12 * 60 && $minutes < self::AFTERNOON_END) {
            return 'Good Afternoon';
        }

        return 'Good Evening';
    }

    private static function moonOpacityFor(int $minutes): float
    {
        if ($minutes >= self::SUNSET_END || $minutes < self::MORNING_START) {
            return 1.0;
        }

        if ($minutes >= self::MORNING_START && $minutes < self::SUNRISE_END) {
            $progress = ($minutes - self::MORNING_START) / (self::SUNRISE_END - self::MORNING_START);

            return round(max(0.0, 1.0 - $progress), 2);
        }

        if ($minutes >= self::SUNSET_START && $minutes < self::SUNSET_END) {
            $progress = ($minutes - (self::SUNSET_START + 30)) / 30;

            return round(min(1.0, max(0.0, $progress)), 2);
        }

        return 0.0;
    }

    private static function sunsetOpacityFor(int $minutes): float
    {
        if ($minutes >= self::MORNING_START && $minutes < self::SUNRISE_END) {
            $progress = ($minutes - self::MORNING_START) / (self::SUNRISE_END - self::MORNING_START);

            if ($progress <= 0.5) {
                return round($progress * 2, 2);
            }

            return round(max(0.0, 2.0 - ($progress * 2)), 2);
        }

        if ($minutes >= self::SUNSET_START && $minutes < self::SUNSET_END) {
            $progress = ($minutes - self::SUNSET_START) / (self::SUNSET_END - self::SUNSET_START);

            if ($progress <= 0.5) {
                return round($progress * 2, 2);
            }

            return round(max(0.0, 2.0 - ($progress * 2)), 2);
        }

        return 0.0;
    }

    private static function sunOpacityFor(int $minutes): float
    {
        if ($minutes >= self::SUNRISE_END && $minutes < self::SUNSET_START) {
            return 1.0;
        }

        if ($minutes >= self::MORNING_START && $minutes < self::SUNRISE_END) {
            $progress = ($minutes - self::MORNING_START) / (self::SUNRISE_END - self::MORNING_START);

            if ($progress <= 0.5) {
                return 0.0;
            }

            return round(min(1.0, ($progress - 0.5) * 2), 2);
        }

        if ($minutes >= self::SUNSET_START && $minutes < self::SUNSET_END) {
            $progress = ($minutes - self::SUNSET_START) / (self::SUNSET_END - self::SUNSET_START);

            if ($progress >= 0.5) {
                return 0.0;
            }

            return round(max(0.0, 1.0 - ($progress * 2)), 2);
        }

        return 0.0;
    }
}
