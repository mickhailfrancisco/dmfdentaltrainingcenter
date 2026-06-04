<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TopbarSkyPreview;
use Illuminate\Http\Request;
use Tests\TestCase;

class TopbarSkyPreviewTest extends TestCase
{
    public function test_is_disabled_when_app_debug_is_false(): void
    {
        config(['app.debug' => false]);

        $this->assertFalse(TopbarSkyPreview::isEnabled());
        $this->assertSame('live', TopbarSkyPreview::resolve()['mode']);
    }

    public function test_parses_cycle_query_parameter(): void
    {
        config(['app.debug' => true]);

        $this->app->instance('request', Request::create('/admin', 'GET', ['sky_sim' => 'cycle']));

        $preview = TopbarSkyPreview::resolve();

        $this->assertTrue($preview['enabled']);
        $this->assertSame('cycle', $preview['mode']);
        $this->assertSame(240, $preview['minutes']);
    }

    public function test_parses_fixed_time_query_parameter(): void
    {
        config(['app.debug' => true]);

        $this->app->instance('request', Request::create('/admin', 'GET', ['sky_sim' => '18:15']));

        $preview = TopbarSkyPreview::resolve();

        $this->assertTrue($preview['enabled']);
        $this->assertSame('fixed', $preview['mode']);
        $this->assertSame(1095, $preview['minutes']);
    }

    public function test_resolves_sky_state_for_preset_minutes(): void
    {
        $state = TopbarSkyPreview::skyStateForMinutes(345);

        $this->assertSame('Good Morning', $state['greeting']);
        $this->assertSame(0.5, $state['moon_opacity']);
        $this->assertSame(1.0, $state['sunset_opacity']);
        $this->assertSame(0.0, $state['sun_opacity']);
    }
}
