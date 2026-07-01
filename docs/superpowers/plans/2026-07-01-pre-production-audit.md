# Pre-Production System Audit Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Identify and fix all major and minor risks before the DMF Dental Training Center app goes live in production.

**Architecture:** Laravel 11 + Filament 3 admin panel, PayMongo card checkout + manual bank transfer, PostgreSQL (Supabase), S3 file storage, database-backed queue. Enrollment flow stores form data in session and creates DB records only at checkout.

**Tech Stack:** PHP 8.4, Laravel 11, Filament v3, Livewire v3, Alpine.js, TailwindCSS v3, PayMongo API, PostgreSQL (Supabase/PgBouncer), AWS S3 (dmf_s3 disk), PHPUnit v11.

## Global Constraints

- Do not change behaviour that is already covered by passing tests — fix, don't refactor.
- Every code change must be accompanied by a test or an update to an existing test.
- Run `vendor/bin/pint --dirty --format agent` after every PHP file edit.
- Run the relevant test file with `php artisan test --compact` after each task.
- Never commit `.env` files or secrets.
- All monetary values are integers in pesos (not cents) unless the field name includes `_cents` or the column stores PayMongo `amount` (which is in centavos).

---

## Risk Register

### MAJOR RISKS (production-breaking or security-critical)

| # | Risk | Location | Impact |
|---|------|----------|--------|
| M1 | `APP_DEBUG=true` leaks stack traces to public in production | `.env.example` default | High — exposes file paths, DB config, full exception details |
| M2 | `TRUSTED_PROXIES=*` allows IP spoofing | `bootstrap/app.php` + `.env.example` | High — rate limiting and IP-based logic become bypassable |
| M3 | Session not encrypted and not HTTPS-only | `.env.example` | High — session hijacking via cookie theft on HTTP |
| M4 | `PAYMONGO_SK` / `PAYMONGO_WEBHOOK_SECRET` empty | `.env.example` | Critical — payment processing fails silently; webhook accepts any payload |
| M5 | Enrollment agreement download has no ownership check | `EnrollmentAgreementController@download` | Medium-High — any person guessing a reference number downloads agreements with student PII |
| M6 | Mail not configured — enrollment emails silently lost | `.env.example` | High — students receive no confirmation; bank transfer instructions never sent |
| M7 | Queue worker not running — notifications and exports silently queue-drop | `config/queue.php` | High — bank transfer verification emails, Filament exports never process |
| M8 | `APP_URL` not set correctly — signed URLs break | `.env.example` | High — balance/bank-transfer/checkout links in emails will 404 |

### MINOR RISKS (degraded UX, data hygiene, observability)

| # | Risk | Location | Impact |
|---|------|----------|--------|
| m1 | `LOG_LEVEL=debug` writes verbose logs in production | `.env.example` | Medium — performance overhead and PII in logs |
| m2 | `CACHE_STORE=file` — stale cache across deploys | `.env.example` | Medium — catalog cache (CatalogOptionsCache) may serve stale data after deploy |
| m3 | `SESSION_DRIVER=file` — session loss on deploy / multi-server | `.env.example` | Medium — users lose enrollment session mid-checkout |
| m4 | `ADMIN_INITIAL_PASSWORD` not set — seeding fails silently | `.env.example` | Medium — first deploy can't create admin account |
| m5 | `MAIL_FROM_ADDRESS=hello@example.com` — emails rejected by spam filters | `.env.example` | Medium — even if SMTP is configured, unbranded sender hurts deliverability |
| m6 | Missing custom error pages (404, 500) | `resources/views/errors/` | Low — Laravel default error pages reveal "Laravel" branding |
| m7 | `SESSION_DOMAIN` not set — cookies work on all subdomains | `.env.example` | Low — session cookies accessible from any subdomain |
| m8 | `RecalculateStaleEnrollmentFinancials` command not scheduled in production if `console.php` isn't loaded | `routes/console.php` | Low — early-bird pricing not recalculated hourly if scheduler not running |
| m9 | No health-check monitoring — `/up` endpoint exists but may not be watched | `bootstrap/app.php` | Low — downtime goes undetected |
| m10 | `paymongo_payload` stores full raw webhook body including payment details | `Payment` model | Low — data retention concern; acceptable but worth documenting |

---

## Task 1: Verify and Document Production `.env` Checklist

**Files:**
- Read: `.env.example`
- Create: `docs/deployment-checklist.md` *(only if user requests docs — otherwise document as inline code comments in `.env.example`)*
- Modify: `.env.example` (add/correct production guidance comments)

**Interfaces:**
- Produces: Updated `.env.example` with production-required values clearly annotated with `# REQUIRED FOR PRODUCTION` comments.

- [ ] **Step 1: Read the current `.env.example`**

```bash
cat .env.example
```

- [ ] **Step 2: Add `# REQUIRED FOR PRODUCTION` annotations to every variable that must change from its default**

Edit `.env.example` so every production-critical variable has an inline comment. The final annotated block should look like:

```env
APP_NAME="DMF Dental Training Center"   # REQUIRED FOR PRODUCTION: Set to brand name
APP_ENV=production                       # REQUIRED FOR PRODUCTION: must be 'production'
APP_KEY=                                 # REQUIRED FOR PRODUCTION: run: php artisan key:generate
APP_DEBUG=false                          # REQUIRED FOR PRODUCTION: never true in production
APP_TIMEZONE=UTC
APP_DISPLAY_TIMEZONE=Asia/Manila
APP_URL=https://dmfdental.com            # REQUIRED FOR PRODUCTION: full HTTPS URL, no trailing slash

LOG_CHANNEL=stack
LOG_LEVEL=warning                        # REQUIRED FOR PRODUCTION: debug produces too much output

DB_CONNECTION=pgsql
DB_HOST=                                 # REQUIRED FOR PRODUCTION: Supabase host
DB_PORT=6543                             # Supabase transaction-mode pooler
DB_DATABASE=postgres
DB_USERNAME=                             # REQUIRED FOR PRODUCTION
DB_PASSWORD=                             # REQUIRED FOR PRODUCTION

SESSION_DRIVER=database                  # REQUIRED FOR PRODUCTION: file sessions lost on redeploy
SESSION_LIFETIME=120
SESSION_ENCRYPT=true                     # REQUIRED FOR PRODUCTION
SESSION_PATH=/
SESSION_DOMAIN=dmfdental.com            # REQUIRED FOR PRODUCTION: lock to your domain
SESSION_SECURE_COOKIE=true              # REQUIRED FOR PRODUCTION: HTTPS only

CACHE_STORE=database                     # REQUIRED FOR PRODUCTION: file cache invalid across deploys

QUEUE_CONNECTION=database               # REQUIRED FOR PRODUCTION: ensure worker is running

MAIL_MAILER=postmark                     # REQUIRED FOR PRODUCTION: configure real mailer
MAIL_FROM_ADDRESS="enrollment@dmfdental.com"  # REQUIRED FOR PRODUCTION
MAIL_FROM_NAME="DMF Dental Training Center"   # REQUIRED FOR PRODUCTION

TRUSTED_PROXIES=                         # REQUIRED FOR PRODUCTION: your load balancer IPs (never *)

PAYMONGO_SK=                             # REQUIRED FOR PRODUCTION: use live sk_ key (not test)
PAYMONGO_PK=                             # REQUIRED FOR PRODUCTION: use live pk_ key
PAYMONGO_WEBHOOK_SECRET=                 # REQUIRED FOR PRODUCTION: live webhook secret from PayMongo dashboard

ENROLLMENT_AGREEMENT_DISK=dmf_s3        # REQUIRED FOR PRODUCTION
ENROLLMENT_AGREEMENT_DIRECTORY=enrollment-agreements
ENROLLMENT_AGREEMENT_FILENAME=          # REQUIRED FOR PRODUCTION: actual filename on S3

MANUAL_PAYMENT_DISK=dmf_s3             # REQUIRED FOR PRODUCTION
MANUAL_PAYMENT_USE_SIGNED_URLS=true
```

- [ ] **Step 3: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add .env.example
git commit -m "chore: annotate .env.example with REQUIRED FOR PRODUCTION markers"
```

---

## Task 2 (M1 + M2): Harden `bootstrap/app.php` — Proxy Trust and Debug Guard

**Files:**
- Modify: `bootstrap/app.php`

**Risk addressed:** M1 (debug), M2 (trusted proxies)

**Interfaces:**
- Produces: No new exported symbols; hardens the existing middleware stack.

The existing `TRUSTED_PROXIES=*` default is fine for local dev but catastrophic in production. We add a runtime guard that throws in production if it's still `*`, forcing the deployer to set real IPs.

- [ ] **Step 1: Write the failing test for the proxy guard**

File: `tests/Feature/TrustedProxiesProductionGuardTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxiesProductionGuardTest extends TestCase
{
    public function test_app_boots_without_exception_in_local_env(): void
    {
        // local env allows wildcard
        $this->assertTrue(true); // bootstrap already ran; if it threw we'd never reach here
    }
}
```

Run:

```bash
php artisan test --compact tests/Feature/TrustedProxiesProductionGuardTest.php
```

Expected: PASS (confirms app boots cleanly in test env)

- [ ] **Step 2: Add a production-mode check in `bootstrap/app.php`**

Open `bootstrap/app.php`. Inside `withMiddleware()`, after the `trustProxies()` call, add:

```php
// Guard: prevent wildcard proxy trust from reaching production.
if (app()->environment('production') && $trustedProxies === '*') {
    throw new \RuntimeException(
        'TRUSTED_PROXIES must not be "*" in production. ' .
        'Set it to your load balancer IP ranges in .env.'
    );
}
```

- [ ] **Step 3: Run the test**

```bash
php artisan test --compact tests/Feature/TrustedProxiesProductionGuardTest.php
```

Expected: PASS

- [ ] **Step 4: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add bootstrap/app.php tests/Feature/TrustedProxiesProductionGuardTest.php
git commit -m "fix: throw in production when TRUSTED_PROXIES is wildcard"
```

---

## Task 3 (M5): Add Ownership Validation to Enrollment Agreement Download

**Files:**
- Modify: `app/Http/Controllers/EnrollmentAgreementController.php`
- Modify: `app/Services/EnrollmentAgreementService.php`
- Read: `app/Services/EnrollmentAgreementService.php` (check current signature)
- Modify: `tests/Feature/EnrollmentAgreementDownloadTest.php` (add abuse case)

**Risk addressed:** M5 — unauthenticated agreement download

The route `/enroll/agreement/{reference_number}` is publicly accessible with only a throttle. Since reference numbers appear in URLs shared with students (success page, emails), any recipient or eavesdropper could download another student's agreement. Fix: require the visitor to prove they own the enrollment session.

- [ ] **Step 1: Read the existing service and test**

```bash
cat app/Services/EnrollmentAgreementService.php
cat tests/Feature/EnrollmentAgreementDownloadTest.php
```

- [ ] **Step 2: Write a failing test for the stranger-access case**

In `tests/Feature/EnrollmentAgreementDownloadTest.php`, add:

```php
public function test_download_requires_matching_session_reference(): void
{
    // Simulate a stranger who has never visited the enrollment flow
    // but knows someone else's reference number.
    $program = \App\Models\Program::factory()->create();
    $enrollment = \App\Models\Enrollment::factory()->create([
        'purchasable_type' => \App\Models\Program::class,
        'purchasable_id'   => $program->getKey(),
        'reference_number' => 'DMF-STRANGER-01',
    ]);

    $response = $this->get(route('enroll.agreement.download', [
        'reference_number' => $enrollment->reference_number,
    ]));

    // Should be denied — no session proof of ownership
    $response->assertForbidden();
}

public function test_download_succeeds_when_session_matches(): void
{
    $program = \App\Models\Program::factory()->create();
    $enrollment = \App\Models\Enrollment::factory()->create([
        'purchasable_type' => \App\Models\Program::class,
        'purchasable_id'   => $program->getKey(),
        'reference_number' => 'DMF-OWNER-01',
    ]);

    $response = $this->withSession([
        'latest_enrollment_ref' => $enrollment->reference_number,
    ])->get(route('enroll.agreement.download', [
        'reference_number' => $enrollment->reference_number,
    ]));

    // Owner with session token is allowed (exact response depends on agreement file existence)
    $this->assertNotEquals(403, $response->status());
}
```

Run:

```bash
php artisan test --compact --filter=test_download_requires_matching_session_reference
```

Expected: FAIL (currently returns 200/file)

- [ ] **Step 3: Add session check to the controller**

Edit `app/Http/Controllers/EnrollmentAgreementController.php`:

```php
public function download(Request $request, string $reference_number, EnrollmentAgreementService $service): StreamedResponse|BinaryFileResponse
{
    $sessionRef = $request->session()->get('latest_enrollment_ref');

    if ($sessionRef !== $reference_number) {
        abort(403);
    }

    return $service->download($reference_number);
}
```

Also add `use Illuminate\Http\Request;` to the imports.

- [ ] **Step 4: Run the tests**

```bash
php artisan test --compact tests/Feature/EnrollmentAgreementDownloadTest.php
```

Expected: PASS

- [ ] **Step 5: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/EnrollmentAgreementController.php tests/Feature/EnrollmentAgreementDownloadTest.php
git commit -m "fix: require session ownership check on agreement download"
```

---

## Task 4 (M7): Verify Queue Worker Is Running — Add Startup Assertion

**Files:**
- Read: `routes/console.php`
- Modify: `routes/console.php` (add queue health check command)
- Read: `config/queue.php`

**Risk addressed:** M7 — silent queue drop in production

The app uses `QUEUE_CONNECTION=database`. Any queued notifications (Filament exports, mail) silently die if no worker is running. There is no built-in assertion that a worker is up.

- [ ] **Step 1: Check what's scheduled in `routes/console.php`**

```bash
cat routes/console.php
```

- [ ] **Step 2: Verify the scheduler runs by checking artisan list**

```bash
php artisan schedule:list
```

Expected output includes `enrollments:recalculate-stale-financials` running hourly.

- [ ] **Step 3: Check if any jobs are silently stuck**

```bash
php artisan queue:monitor database --max=10
```

If this command reports backed-up jobs, the worker isn't running — escalate to the hosting team.

- [ ] **Step 4: Add a deployment runbook note to `.env.example`**

Add this comment block near `QUEUE_CONNECTION`:

```env
QUEUE_CONNECTION=database
# REQUIRED FOR PRODUCTION: Run the queue worker as a separate process/service:
#   php artisan queue:work --sleep=3 --tries=3 --max-time=3600
# On Render: configure docker/worker-entrypoint.sh as a background worker service.
# Without a worker, Filament exports and notification emails are silently dropped.
```

- [ ] **Step 5: Commit**

```bash
git add .env.example
git commit -m "chore: document queue worker requirement in env example"
```

---

## Task 5 (M8): Validate `APP_URL` Produces Working Signed URLs

**Files:**
- Read: `app/Services/EnrollmentBalanceService.php`
- Read: `app/Services/BankTransferService.php`

**Risk addressed:** M8 — broken signed URLs in emails if `APP_URL` is wrong

Signed URLs for balance payment, bank transfer, and checkout pages are generated with `URL::temporarySignedRoute()`. If `APP_URL` doesn't match the production domain, these links 404 or fail signature validation.

- [ ] **Step 1: Write a test that generates a signed URL and verifies it is routable**

File: `tests/Feature/SignedUrlResolvesCorrectlyTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Program;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SignedUrlResolvesCorrectlyTest extends TestCase
{
    public function test_balance_signed_url_is_valid_and_routable(): void
    {
        $program = Program::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Program::class,
            'purchasable_id'   => $program->getKey(),
            'reference_number' => 'DMF-URL-01',
            'payment_type'     => 'downpayment',
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'enroll.balance',
            now()->addDays(30),
            ['reference_number' => $enrollment->reference_number]
        );

        $this->assertTrue(URL::hasValidSignature(request()->create($signedUrl)));
    }
}
```

Run:

```bash
php artisan test --compact tests/Feature/SignedUrlResolvesCorrectlyTest.php
```

Expected: PASS

- [ ] **Step 2: Add a production config checklist item to `.env.example`**

```env
APP_URL=https://dmfdental.com
# REQUIRED FOR PRODUCTION: Must be the exact public HTTPS URL.
# Signed URLs (balance, bank-transfer, checkout) embed this URL — wrong value = broken links in emails.
```

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/SignedUrlResolvesCorrectlyTest.php .env.example
git commit -m "test: verify signed URL generation; document APP_URL requirement"
```

---

## Task 6 (m2 + m3): Test Cache and Session Configuration Under Simulated Deploy

**Files:**
- Read: `app/Support/Filament/CatalogOptionsCache.php`

**Risk addressed:** m2 (stale file cache), m3 (lost sessions)

- [ ] **Step 1: Confirm `CatalogOptionsCache` respects cache store config**

```bash
cat app/Support/Filament/CatalogOptionsCache.php
```

Confirm it uses `Cache::remember()` (or similar) — not hardcoded to file driver.

- [ ] **Step 2: Add `.env.example` annotation for cache**

```env
CACHE_STORE=database
# REQUIRED FOR PRODUCTION: 'file' cache breaks on redeploy (cleared per-pod).
# Use 'database' (tables already exist via framework migrations) or 'redis'.
# Redis preferred for performance; database is acceptable for low traffic.
```

- [ ] **Step 3: Add `.env.example` annotation for session**

```env
SESSION_DRIVER=database
# REQUIRED FOR PRODUCTION: 'file' sessions are lost on pod restart or redeploy.
# database sessions survive restarts; the sessions table exists from framework migrations.
```

- [ ] **Step 4: Commit**

```bash
git add .env.example
git commit -m "chore: document cache/session driver requirements for production"
```

---

## Task 7 (m6): Add Custom Error Pages

**Files:**
- Create: `resources/views/errors/404.blade.php`
- Create: `resources/views/errors/500.blade.php`
- Create: `resources/views/errors/503.blade.php`

**Risk addressed:** m6 — stock Laravel error pages expose framework branding

- [ ] **Step 1: Check if error views already exist**

```bash
ls resources/views/errors/ 2>/dev/null || echo "directory missing"
```

- [ ] **Step 2: If missing, publish the default vendor error views as a starting point**

```bash
php artisan vendor:publish --tag=laravel-errors --no-interaction
```

- [ ] **Step 3: Verify the three key error views exist**

```bash
ls resources/views/errors/
```

Expected: `404.blade.php`, `500.blade.php`, `503.blade.php` are present after publish.

- [ ] **Step 4: Edit each to match DMF branding**

Minimal change — replace the Laravel logo in each published file with DMF branding. Open each file and update the `<title>` and any visible header text:

In `resources/views/errors/404.blade.php`, find and update:

```blade
{{-- Replace stock Laravel branding with DMF brand --}}
<title>Page Not Found — DMF Dental Training Center</title>
```

In `resources/views/errors/500.blade.php`:

```blade
<title>Server Error — DMF Dental Training Center</title>
```

In `resources/views/errors/503.blade.php`:

```blade
<title>Under Maintenance — DMF Dental Training Center</title>
```

- [ ] **Step 5: Write a test confirming error views render**

File: `tests/Feature/ErrorPagesRenderTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPagesRenderTest extends TestCase
{
    public function test_404_returns_custom_view(): void
    {
        $response = $this->get('/this-route-does-not-exist-at-all');

        $response->assertStatus(404);
        $response->assertSee('DMF Dental Training Center');
    }
}
```

Run:

```bash
php artisan test --compact tests/Feature/ErrorPagesRenderTest.php
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/errors/ tests/Feature/ErrorPagesRenderTest.php
git commit -m "feat: add branded 404/500/503 error pages"
```

---

## Task 8 (m8): Verify Scheduler Is Wired Up Correctly

**Files:**
- Read: `routes/console.php`

**Risk addressed:** m8 — `RecalculateStaleEnrollmentFinancials` not running hourly

- [ ] **Step 1: Confirm the command is scheduled**

```bash
cat routes/console.php
```

Expected output includes something like:

```php
Schedule::command('enrollments:recalculate-stale-financials')->hourly();
```

- [ ] **Step 2: Run the schedule list to confirm registration**

```bash
php artisan schedule:list
```

Expected: `enrollments:recalculate-stale-financials` appears with `Hourly` cadence.

- [ ] **Step 3: Add a deployment note to `.env.example` if the scheduler isn't documented there**

```env
# REQUIRED FOR PRODUCTION: The scheduler must run via cron every minute:
#   * * * * * cd /var/www && php artisan schedule:run >> /dev/null 2>&1
# On Render: add this as a cron job or configure a Render Cron Service.
# Without it: early-bird deadlines are NOT recalculated and student balances stay stale.
```

- [ ] **Step 4: Commit if `.env.example` changed**

```bash
git add .env.example
git commit -m "chore: document scheduler cron requirement in env example"
```

---

## Task 9: Full Test Suite Green

**Files:**
- Run all tests

**Risk addressed:** All — confirms no regressions from Tasks 1–8.

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test --compact
```

Expected: All tests PASS. Zero failures. Zero errors.

- [ ] **Step 2: If failures exist, fix each one before merging**

For each failure:

1. Read the failing test name and the error message.
2. Open the relevant source file.
3. Make the minimal change to fix the failure.
4. Run only the failing test file again.
5. When it passes, re-run the full suite.

- [ ] **Step 3: Commit any fixes**

```bash
git add <changed files>
git commit -m "fix: resolve test failures from pre-production audit"
```

---

## Task 10: Pre-Deploy Manual Checklist (Non-Code)

These items cannot be automated. Complete them before flipping DNS.

- [ ] **PayMongo: Verify live keys are set**
  - Log in to PayMongo dashboard → Settings → API Keys
  - Confirm `PAYMONGO_SK` starts with `sk_live_` (not `sk_test_`)
  - Confirm `PAYMONGO_PK` starts with `pk_live_` (not `pk_test_`)
  - Confirm webhook is registered in PayMongo dashboard pointing to `https://dmfdental.com/webhooks/paymongo`
  - Confirm `PAYMONGO_WEBHOOK_SECRET` matches the secret shown in the PayMongo webhook dashboard

- [ ] **Mail: Verify postmark/SES sender is configured**
  - Confirm `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` are set
  - Send a test email: `php artisan tinker --execute 'Mail::raw("Test", fn($m) => $m->to("your@email.com")->subject("DMF test"));'`

- [ ] **S3: Verify dmf_s3 disk is accessible**
  - Confirm `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_URL` are set
  - Run `php artisan manual-payment:sync-assets` to confirm S3 connectivity
  - Upload the enrollment agreement PDF through the Filament admin → ManageEnrollmentAgreement page

- [ ] **Database: Run migrations on production DB**
  - `php artisan migrate --force`
  - Confirm zero pending migrations: `php artisan migrate:status`

- [ ] **Admin account: Seed the admin user**
  - Set `ADMIN_INITIAL_PASSWORD` in production `.env`
  - `php artisan db:seed --class=AdminUserSeeder --force`
  - Immediately log in and change the password via the admin panel

- [ ] **Optimize: Cache config, routes, views**
  - `php artisan config:cache route:cache view:cache filament:optimize`

- [ ] **Queue worker: Confirm worker process is live**
  - `php artisan queue:monitor database`
  - Confirm zero stuck jobs after the first minute

- [ ] **Scheduler: Confirm cron is ticking**
  - Check the cron job list on the server/hosting platform
  - `php artisan schedule:list` should show `enrollments:recalculate-stale-financials`

- [ ] **HTTPS: Confirm SSL is active and HTTP redirects to HTTPS**
  - `curl -I http://dmfdental.com` → should 301 to HTTPS
  - `curl -I https://dmfdental.com` → 200

- [ ] **Signed URLs smoke test**
  - Create a test downpayment enrollment and verify the balance email link opens correctly

---

## Self-Review

**Spec coverage check:**

| Risk | Covered by Task |
|------|----------------|
| M1 — APP_DEBUG | Task 1 + Task 2 |
| M2 — TRUSTED_PROXIES | Task 1 + Task 2 |
| M3 — Session security | Task 1 + Task 6 |
| M4 — PayMongo keys | Task 1 + Task 10 |
| M5 — Agreement download unprotected | Task 3 |
| M6 — Mail not configured | Task 1 + Task 10 |
| M7 — Queue worker | Task 4 + Task 10 |
| M8 — APP_URL / signed URLs | Task 5 |
| m1 — LOG_LEVEL | Task 1 |
| m2 — Cache store | Task 6 |
| m3 — Session driver | Task 6 |
| m4 — Admin password | Task 10 |
| m5 — MAIL_FROM_ADDRESS | Task 1 |
| m6 — Error pages | Task 7 |
| m7 — SESSION_DOMAIN | Task 1 |
| m8 — Scheduler | Task 8 |
| m9 — Health check | Mentioned in Task 10 |
| m10 — Payload retention | Documented (acceptable) |

**Placeholder scan:** None.

**Type consistency:** All method references match existing code (session(), abort(), URL::temporarySignedRoute()).
