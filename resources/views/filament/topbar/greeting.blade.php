@php
    use App\Support\TopbarSkyPreview;
    use App\Support\TopbarSkyState;
    use Illuminate\Support\Carbon;

    $user = auth()->user();
    $firstName = $user?->name ? strtok($user->name, ' ') : null;
    $greetingName = $user?->isAdmin() ? 'Doc' : $firstName;
    $timezone = config('app.display_timezone');
    $now = Carbon::now($timezone);
    $skyPreview = TopbarSkyPreview::resolve();
    $simEnabled = $skyPreview['enabled'];

    if ($simEnabled && $skyPreview['mode'] !== 'live') {
        $sky = TopbarSkyPreview::skyStateForMinutes($skyPreview['minutes']);
        $displayNow = $now->copy()->startOfDay()->addMinutes($skyPreview['minutes']);
        $effectiveMinutes = $skyPreview['minutes'] + ($displayNow->second / 60);
    } else {
        $sky = TopbarSkyState::resolve($now);
        $displayNow = $now;
        $effectiveMinutes = ($now->hour * 60) + $now->minute + ($now->second / 60);
    }

    $scene = TopbarSkyState::resolveSceneFromMinutes($effectiveMinutes);
    $initialGreetingLine = filled($greetingName)
        ? $sky['greeting'].', '.$greetingName
        : $sky['greeting'];
    $initialTimeLabel = $displayNow->format('M j, Y - g:i:s A');
@endphp

@if ($user && filled($greetingName))
    <div
        class="dmf-topbar-greeting"
        x-data="{
            timezone: @js($timezone),
            greetingName: @js($greetingName),
            greetingLine: @js($initialGreetingLine),
            timeLabel: @js($initialTimeLabel),
            sunY: @js($scene['sun_y']),
            moonY: @js($scene['moon_y']),
            sunOpacity: @js($scene['sun_opacity']),
            moonOpacity: @js($scene['moon_opacity']),
            skyTop: @js($scene['sky_top']),
            skyBottom: @js($scene['sky_bottom']),
            glowOpacity: @js($scene['glow_opacity']),
            simEnabled: @js($simEnabled),
            simOpen: false,
            simMode: @js($skyPreview['mode']),
            simMinutes: @js($skyPreview['minutes']),
            simSeconds: @js($displayNow->second),
            presets: @js(TopbarSkyPreview::PRESETS),
            lastSimTickAt: 0,
            init() {
                this.refresh(false);
                setInterval(() => this.refresh(true), 1000);
            },
            isSimulating() {
                return this.simMode !== 'live';
            },
            useLive() {
                this.simMode = 'live';
                this.refresh(false);
            },
            setPreset(minutes) {
                this.simMode = 'fixed';
                this.simMinutes = minutes;
                this.simSeconds = 0;
                this.refresh(false);
            },
            startCycle() {
                this.simMode = 'cycle';
                this.simMinutes = 240;
                this.simSeconds = 0;
                this.refresh(false);
            },
            minutesSinceMidnight(date) {
                const parts = new Intl.DateTimeFormat('en-US', {
                    timeZone: this.timezone,
                    hour: 'numeric',
                    minute: 'numeric',
                    second: 'numeric',
                    hour12: false,
                }).formatToParts(date);

                const hour = Number(parts.find((part) => part.type === 'hour')?.value ?? 0);
                const minute = Number(parts.find((part) => part.type === 'minute')?.value ?? 0);
                const second = Number(parts.find((part) => part.type === 'second')?.value ?? 0);

                return (hour * 60) + minute + (second / 60);
            },
            greetingFor(minutes) {
                const wholeMinutes = Math.floor(minutes);

                if (wholeMinutes >= 300 && wholeMinutes < 720) {
                    return 'Good Morning';
                }

                if (wholeMinutes >= 720 && wholeMinutes < 1080) {
                    return 'Good Afternoon';
                }

                return 'Good Evening';
            },
            clamp(value, min, max) {
                return Math.max(min, Math.min(max, value));
            },
            lerp(from, to, progress) {
                return from + ((to - from) * this.clamp(progress, 0, 1));
            },
            easeInOut(progress) {
                if (progress <= 0.5) {
                    return 2 * progress * progress;
                }

                return 1 - (Math.pow(-2 * progress + 2, 2) / 2);
            },
            hexToRgb(hex) {
                const normalized = hex.replace('#', '');

                return [
                    parseInt(normalized.slice(0, 2), 16),
                    parseInt(normalized.slice(2, 4), 16),
                    parseInt(normalized.slice(4, 6), 16),
                ];
            },
            lerpColor(from, to, progress) {
                const amount = this.clamp(progress, 0, 1);
                const [r1, g1, b1] = this.hexToRgb(from);
                const [r2, g2, b2] = this.hexToRgb(to);
                const red = Math.round(this.lerp(r1, r2, amount));
                const green = Math.round(this.lerp(g1, g2, amount));
                const blue = Math.round(this.lerp(b1, b2, amount));

                return `#${red.toString(16).padStart(2, '0')}${green.toString(16).padStart(2, '0')}${blue.toString(16).padStart(2, '0')}`;
            },
            resolveScene(minutes) {
                const morningStart = 300;
                const sunriseEnd = 390;
                const sunsetStart = 1050;
                const sunsetEnd = 1140;
                const zenith = -42;
                const hidden = 48;

                let sunY = hidden;
                let moonY = zenith;
                let sunOpacity = 0;
                let moonOpacity = 1;
                let skyTop = '#1e1b4b';
                let skyBottom = '#4338ca';
                let glowOpacity = 0;

                if (minutes >= sunriseEnd && minutes < sunsetStart) {
                    const dayProgress = (minutes - sunriseEnd) / (sunsetStart - sunriseEnd);
                    const arc = Math.sin(dayProgress * Math.PI) * -8;

                    sunY = zenith + arc;
                    moonY = hidden;
                    sunOpacity = 1;
                    moonOpacity = 0;
                    skyTop = '#38bdf8';
                    skyBottom = '#fef08a';
                } else if (minutes >= morningStart && minutes < sunriseEnd) {
                    const progress = (minutes - morningStart) / (sunriseEnd - morningStart);
                    const eased = this.easeInOut(progress);

                    sunY = this.lerp(hidden, zenith, eased);
                    moonY = this.lerp(zenith, hidden, eased);
                    sunOpacity = this.clamp((progress - 0.35) / 0.3, 0, 1);
                    moonOpacity = this.clamp((0.65 - progress) / 0.3, 0, 1);
                    glowOpacity = Math.sin(progress * Math.PI) * 0.85;
                    skyTop = this.lerpColor('#1e1b4b', '#fb923c', Math.min(1, progress * 1.6));
                    skyBottom = this.lerpColor('#4338ca', '#fef08a', Math.min(1, Math.max(0, (progress - 0.2) * 1.4)));
                } else if (minutes >= sunsetStart && minutes < sunsetEnd) {
                    const progress = (minutes - sunsetStart) / (sunsetEnd - sunsetStart);
                    const eased = this.easeInOut(progress);

                    sunY = this.lerp(zenith, hidden, eased);
                    moonY = this.lerp(hidden, zenith, eased);
                    sunOpacity = this.clamp((0.65 - progress) / 0.3, 0, 1);
                    moonOpacity = this.clamp((progress - 0.35) / 0.3, 0, 1);
                    glowOpacity = Math.sin(progress * Math.PI) * 0.85;
                    skyTop = this.lerpColor('#38bdf8', '#fb923c', Math.min(1, progress * 1.6));
                    skyBottom = this.lerpColor('#fef08a', '#4338ca', Math.min(1, Math.max(0, (progress - 0.2) * 1.4)));
                }

                return { sunY, moonY, sunOpacity, moonOpacity, skyTop, skyBottom, glowOpacity };
            },
            effectiveMinutes() {
                if (this.simMode === 'live') {
                    return this.minutesSinceMidnight(new Date());
                }

                return this.simMinutes + (this.simSeconds / 60);
            },
            formatSimTimeLabel() {
                const totalSeconds = (this.simMinutes * 60) + this.simSeconds;
                const hours24 = Math.floor(totalSeconds / 3600) % 24;
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const seconds = totalSeconds % 60;
                const period = hours24 >= 12 ? 'PM' : 'AM';
                const hours12 = hours24 % 12 || 12;

                const now = new Date();
                const datePart = new Intl.DateTimeFormat('en-PH', {
                    timeZone: this.timezone,
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                }).format(now);

                const pad = (value) => String(value).padStart(2, '0');

                return `${datePart} - ${hours12}:${pad(minutes)}:${pad(seconds)} ${period}`;
            },
            tickSimulation() {
                if (this.simMode === 'cycle') {
                    this.simMinutes = (this.simMinutes + 2) % 1440;
                    this.simSeconds = 0;

                    return;
                }

                this.simSeconds += 1;

                if (this.simSeconds >= 60) {
                    this.simSeconds = 0;
                    this.simMinutes = (this.simMinutes + 1) % 1440;
                }
            },
            refresh(shouldTick) {
                if (shouldTick && this.simMode !== 'live') {
                    const now = Date.now();

                    if (now - this.lastSimTickAt >= 1000) {
                        this.tickSimulation();
                        this.lastSimTickAt = now;
                    }
                }

                const minutes = this.effectiveMinutes();
                const scene = this.resolveScene(minutes);
                const greeting = this.greetingFor(minutes);

                this.greetingLine = `${greeting}, ${this.greetingName}`;
                this.sunY = scene.sunY;
                this.moonY = scene.moonY;
                this.sunOpacity = scene.sunOpacity;
                this.moonOpacity = scene.moonOpacity;
                this.skyTop = scene.skyTop;
                this.skyBottom = scene.skyBottom;
                this.glowOpacity = scene.glowOpacity;

                if (this.simMode === 'live') {
                    const now = new Date();
                    const datePart = new Intl.DateTimeFormat('en-PH', {
                        timeZone: this.timezone,
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric',
                    }).format(now);
                    const timePart = new Intl.DateTimeFormat('en-PH', {
                        timeZone: this.timezone,
                        hour: 'numeric',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true,
                    }).format(now);

                    this.timeLabel = `${datePart} - ${timePart}`;
                } else {
                    this.timeLabel = this.formatSimTimeLabel();
                }
            },
            celestialStyle(y, opacity) {
                return {
                    transform: `translate(-50%, calc(-50% + ${y}%))`,
                    opacity: opacity,
                };
            },
            skyBackgroundStyle() {
                return {
                    background: `linear-gradient(180deg, ${this.skyTop} 0%, ${this.skyBottom} 100%)`,
                };
            },
            glowStyle() {
                return {
                    opacity: this.glowOpacity,
                };
            },
        }"
    >
        <div class="dmf-topbar-greeting__sky-scene" aria-hidden="true">
            <div
                class="dmf-topbar-greeting__sky-bg"
                x-bind:style="skyBackgroundStyle()"
                style="background: linear-gradient(180deg, {{ $scene['sky_top'] }} 0%, {{ $scene['sky_bottom'] }} 100%);"
            ></div>

            <div
                class="dmf-topbar-greeting__sky-glow"
                x-bind:style="glowStyle()"
                style="opacity: {{ $scene['glow_opacity'] }}"
            ></div>

            <div
                class="dmf-topbar-greeting__celestial dmf-topbar-greeting__celestial--sun"
                x-bind:style="celestialStyle(sunY, sunOpacity)"
                style="transform: translate(-50%, calc(-50% + {{ $scene['sun_y'] }}%)); opacity: {{ $scene['sun_opacity'] }}"
            >
                @svg('heroicon-s-sun', 'dmf-topbar-greeting__icon')
            </div>

            <div
                class="dmf-topbar-greeting__celestial dmf-topbar-greeting__celestial--moon"
                x-bind:style="celestialStyle(moonY, moonOpacity)"
                style="transform: translate(-50%, calc(-50% + {{ $scene['moon_y'] }}%)); opacity: {{ $scene['moon_opacity'] }}"
            >
                @svg('heroicon-s-moon', 'dmf-topbar-greeting__icon')
            </div>
        </div>

        <div class="dmf-topbar-greeting__text">
            <p class="dmf-topbar-greeting__line" x-text="greetingLine">{{ $initialGreetingLine }}</p>
            <p class="dmf-topbar-greeting__clock" x-text="timeLabel">{{ $initialTimeLabel }}</p>
        </div>

    </div>
@endif
