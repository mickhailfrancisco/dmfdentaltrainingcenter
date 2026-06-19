# DMF Dental — Deployment & Hosting Guide

Internal guide for developers deploying the Laravel 11 + Filament admin application to production.

---

## 1. Application requirements

| Component | Minimum |
|-----------|---------|
| PHP | **8.2+** (8.3/8.4 recommended) |
| Extensions | `pdo_mysql` or `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `intl`, `zip`, `gd`, `pcntl` |
| Database | **PostgreSQL** (preferred in `.env.example`) or **MySQL 8+** |
| Node.js | 20+ (build time only — assets compiled via Vite) |
| Composer | 2.x |
| SSL | Required for production (HTTPS) |
| Queue worker | **Required** for Filament CSV exports (`QUEUE_CONNECTION=database`) |
| Scheduler | **Required** for hourly enrollment balance recalculation |

### External services

| Service | Purpose | Env vars |
|---------|---------|----------|
| **Paymongo** | Card/online payments + webhooks | `PAYMONGO_SK`, `PAYMONGO_PK`, `PAYMONGO_WEBHOOK_SECRET` |
| **Email** (optional) | Transactional mail | `MAIL_*` |

---

## 2. Recommended hosting options

### Tier A — Best fit for this app (recommended)

These support PHP 8.2+, databases, queues, cron, and SSL with minimal friction.

| Provider | Why it fits | Notes |
|----------|-------------|-------|
| **[Laravel Cloud](https://cloud.laravel.com/)** | Official Laravel hosting; queues, scheduler, deploys built-in | Lowest ops overhead for Laravel teams |
| **[Render](https://render.com/)** | Repo includes `Dockerfile` + `docker/entrypoint.sh` tuned for Render | Web service + **separate worker service** for queue |
| **[Laravel Forge](https://forge.laravel.com/) + VPS** | Forge manages Nginx, SSL, queues, scheduler on DigitalOcean/Hetzner/etc. | Best long-term control |
| **[Railway](https://railway.app/)** | Docker deploy, Postgres add-on, worker process | Good middle ground |
| **[DigitalOcean App Platform](https://www.digitalocean.com/products/app-platform)** | Docker or buildpack, managed DB | Similar to Render |

**Primary recommendation:** Use **Render** (Docker already configured) or **Forge + DigitalOcean VPS** if you want traditional VPS control.

### Tier B — GoDaddy and similar shared hosts

| Option | Verdict |
|--------|---------|
| **GoDaddy Shared Web Hosting** | **Not recommended** for this app — often lacks PHP 8.2, `pcntl`, reliable SSH, queue workers, and long-running processes |
| **GoDaddy VPS / Dedicated Server** | **Possible** with manual Nginx + PHP-FPM setup — you manage everything |
| **GoDaddy cPanel Business / Deluxe with SSH** | **Marginal** — verify PHP 8.2+, Composer, cron, and ability to run `queue:work` as a supervised process |

If the client already pays for GoDaddy:

1. **Upgrade to GoDaddy VPS** (not shared), **or**
2. Keep domain at GoDaddy → point DNS to Render/Forge/DigitalOcean (domain-only at GoDaddy is fine)

### Tier C — Other Philippines-friendly options

| Provider | Notes |
|----------|-------|
| **Hostinger VPS** | Affordable VPS; manual Laravel setup |
| **Vultr / Linode** | Cheap VPS; pair with Forge or manual config |
| **AWS Lightsail** | Simple VPS; more setup than Render |

---

## 3. Architecture overview

```
                    ┌─────────────────┐
   Students ───────►│  Web service   │  Laravel (public + /admin)
                    │  HTTPS :443    │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
        ┌──────────┐  ┌──────────┐  ┌─────────────┐
        │ Postgres │  │  Queue   │  │  Scheduler  │
        │ or MySQL │  │  worker  │  │  (cron)     │
        └──────────┘  └──────────┘  └─────────────┘
                             │
                    Filament CSV exports
                    Hourly: enrollments:recalculate-stale-financials
```

**Paymongo webhook:** `POST https://your-domain.com/webhooks/paymongo`

---

## 4. Environment variables (production)

Copy `.env.example` to `.env` on the server and set:

```env
APP_NAME="DMF Dental Training Center"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_DISPLAY_TIMEZONE=Asia/Manila

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=database
CACHE_STORE=file

TRUSTED_PROXIES=*
# Production: replace * with your load balancer CIDRs when known

ADMIN_INITIAL_PASSWORD=your-strong-bootstrap-password

PAYMONGO_SK=sk_live_...
PAYMONGO_PK=pk_live_...
PAYMONGO_WEBHOOK_SECRET=whsec_...

# Signed enrollment agreement (PDF or DOCX on server — see section 8)
ENROLLMENT_AGREEMENT_EMAIL=enrollment@dmfdental.com
ENROLLMENT_AGREEMENT_FILENAME=DMF-Undertaking-December-2025-Lecture.docx
# ENROLLMENT_AGREEMENT_PATH=   # optional; default storage/app/enrollment-agreements/DMF-Undertaking-December-2025-Lecture.docx

RUN_OPTIMIZE_ON_BOOT=true
```

Generate app key once:

```bash
php artisan key:generate
```

**Never** commit `.env` to git.

---

## 5. Deploy option A — Render (Docker, already configured)

### 5.1 Create services

1. **PostgreSQL** — Render managed database (or external Supabase/Neon)
2. **Web Service**
   - Runtime: Docker
   - Dockerfile path: `./Dockerfile`
   - Build command: (default — Dockerfile handles build)
   - Start command: (default — `docker/entrypoint.sh`)
   - Env: all production variables above
   - Health check path: `/up`
3. **Background Worker** (required for exports)
   - Same repo + Docker image **or** native worker
   - Start command: `docker/worker-entrypoint.sh`  
     Equivalent: `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`

### 5.2 What entrypoint does automatically

[`docker/entrypoint.sh`](../docker/entrypoint.sh):

- `php artisan migrate --force`
- `config:cache`, `route:cache`, `view:cache`, `filament:optimize`
- Starts `php artisan serve` on `$PORT`

### 5.3 Scheduler on Render

Add a **Cron Job** service (or use Render cron):

```bash
php artisan schedule:run
```

Schedule: every minute (`* * * * *`)

Registered tasks in [`routes/console.php`](../routes/console.php):

- `enrollments:recalculate-stale-financials` — hourly

### 5.4 Paymongo webhook

In Paymongo Dashboard → Webhooks:

- URL: `https://your-domain.com/webhooks/paymongo`
- Copy signing secret → `PAYMONGO_WEBHOOK_SECRET`

### 5.5 First-time admin

**Option 1 — Seeder (with env password set):**

```bash
php artisan db:seed --class=AdminUserSeeder
```

Requires `ADMIN_INITIAL_PASSWORD` in production.

**Option 2 — Manual (safer):**

```bash
php artisan tinker --execute "
\App\Models\User::create([
  'name' => 'DMF Dental Administrator',
  'email' => 'admin@dmfdental.com',
  'password' => 'YOUR_HASH',
  'role' => 'admin',
]);
"
```

Or use `User::factory()` patterns from tests.

Default seeder email: `admin@dmfdental.com`

---

## 6. Deploy option B — VPS (Forge, DigitalOcean, GoDaddy VPS)

### 6.1 Stack

- Ubuntu 22.04+
- Nginx → PHP 8.3-FPM
- PostgreSQL or MySQL
- Redis (optional, recommended for cache/sessions at scale)
- Supervisor (queue worker)
- Cron (scheduler)
- Certbot / Let's Encrypt SSL

### 6.2 Deploy steps

```bash
# On server
cd /var/www/dmf-dental
git pull origin main

composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize

# Permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

### 6.3 Nginx

Point document root to `/public`:

```nginx
root /var/www/dmf-dental/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

Set `TRUSTED_PROXIES` if behind Cloudflare/load balancer.

### 6.4 Supervisor (queue worker)

`/etc/supervisor/conf.d/dmf-dental-worker.conf`:

```ini
[program:dmf-dental-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/dmf-dental/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/dmf-dental/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start dmf-dental-worker:*
```

### 6.5 Cron (scheduler)

```cron
* * * * * cd /var/www/dmf-dental && php artisan schedule:run >> /dev/null 2>&1
```

### 6.6 GoDaddy VPS specifics

If using GoDaddy VPS:

1. Install Ubuntu, enable SSH
2. Install PHP 8.3, Nginx, PostgreSQL/MySQL, Composer, Node (for builds)
3. Follow sections 6.2–6.5 above
4. Point GoDaddy domain DNS **A record** to VPS IP
5. Issue SSL via Certbot

**Do not** use GoDaddy shared hosting for this Laravel app unless PHP 8.2+ and SSH are confirmed and you can run Supervisor + cron.

---

## 7. Deploy option C — Laravel Forge (recommended VPS workflow)

1. Create Forge account → connect DigitalOcean/Hetzner/Vultr
2. Create server (PHP 8.3, PostgreSQL)
3. Create site → point domain
4. Enable **Queue** (Forge installs Supervisor worker)
5. Enable **Scheduler** (Forge installs cron)
6. Deploy script:

```bash
cd /home/forge/your-domain.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci && npm run build

php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan filament:optimize

( flock -w 10 9 || exit 1
  9>&-; 9>/tmp/flock.lock
  php artisan queue:restart
) 9>/tmp/flock.lock
```

7. Set environment variables in Forge UI (never in git)

---

## 8. Post-deploy checklist

### Infrastructure

- [ ] HTTPS works (`APP_URL` uses `https://`)
- [ ] `/up` health check returns OK
- [ ] Database migrations applied
- [ ] `storage/` and `bootstrap/cache/` writable
- [ ] Queue worker running (test CSV export in admin)
- [ ] Cron / scheduler running (verify `schedule:list`)
- [ ] `APP_DEBUG=false`, `LOG_LEVEL=warning`

### Security

- [ ] `ADMIN_INITIAL_PASSWORD` set if using seeder; rotate after first login
- [ ] `TRUSTED_PROXIES` restricted in production (not `*` unless behind unknown proxy)
- [ ] Paymongo **live** keys (not test) in production
- [ ] Webhook secret configured and verified
- [ ] Admin password changed from bootstrap value

### Application smoke test

- [ ] Public site loads: `/` and `/enroll`
- [ ] Admin login: `/admin`
- [ ] Operations overview shows stat cards
- [ ] Create test program → visible on enroll form
- [ ] Test Paymongo checkout (small amount or test mode on staging)
- [ ] Bank transfer proof upload + verify flow
- [ ] Enrollment success page: **Download Agreement** works (`/enroll/agreement/{ref}`)
- [ ] Signed agreement file uploaded to `storage/app/enrollment-agreements/DMF-Undertaking-December-2025-Lecture.docx` (or `ENROLLMENT_AGREEMENT_PATH`)
- [ ] `ENROLLMENT_AGREEMENT_EMAIL` set to the address where students email signed copies
- [ ] Assistant login with limited permissions

### DNS (GoDaddy domain example)

| Type | Name | Value |
|------|------|-------|
| A | `@` | Your server IP (VPS) or Render URL |
| CNAME | `www` | `@` or hosting provider hostname |

If using Render custom domain, follow Render's DNS instructions (often CNAME to `*.onrender.com`).

---

## 9. CI/CD

GitHub Actions runs tests on push/PR:

[`.github/workflows/tests.yml`](../.github/workflows/tests.yml)

Recommended flow:

1. Push to `main` → CI passes
2. Auto-deploy via Render/Forge webhook **or** manual `git pull` on VPS
3. Migrations run on deploy (Render entrypoint or Forge deploy script)

---

## 10. Backups & monitoring

| Item | Recommendation |
|------|----------------|
| Database | Daily automated backups (Render Postgres, Forge, or `pg_dump` cron) |
| Files | `storage/app` (bank transfer proofs) — include in backup plan |
| Logs | Render log stream / `storage/logs/laravel.log` on VPS |
| Uptime | UptimeRobot or Better Stack on `/up` |
| Paymongo | Monitor webhook delivery in Paymongo dashboard |

---

## 11. Staging vs production

Maintain a **staging** environment that mirrors production:

- Separate database
- Paymongo **test** keys
- `APP_ENV=staging`, `APP_DEBUG=false` (still no debug in staging)
- Test migrations here before production

---

## 12. Cost estimate (rough, USD/month)

| Setup | Approx. cost |
|-------|----------------|
| Render Web + Worker + Postgres (starter) | $21–45 |
| DigitalOcean Droplet 2GB + Forge | $17 + $12 |
| GoDaddy VPS 2GB | $10–25 (plus setup time) |
| GoDaddy Shared | $5–15 — **avoid for this app** |
| Laravel Cloud | Varies — check current pricing |

**Domain-only at GoDaddy** (~$15/year) + hosting elsewhere is a common cost-saving pattern.

---

## 13. Quick decision guide

```
Need easiest Laravel deploy?
  → Laravel Cloud or Render (Docker)

Client already on GoDaddy?
  → Keep domain at GoDaddy
  → Host app on Render or Forge VPS
  → Point DNS to new host

Need full server control?
  → Forge + DigitalOcean/Hetzner

Budget minimal but technical?
  → Single VPS + manual Nginx/Supervisor
```

---

## 14. Support runbook (developer)

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| 500 on deploy | Missing `APP_KEY`, bad `.env` | `php artisan key:generate`, check logs |
| Export hangs | No queue worker | Start worker service |
| Webhook not updating payments | Wrong secret / URL | Verify Paymongo webhook + `PAYMONGO_WEBHOOK_SECRET` |
| Balance tabs wrong after deadline | Scheduler not running | Enable cron; run `php artisan enrollments:recalculate-stale-financials` |
| Mixed content / wrong URLs | `APP_URL` http vs https | Set `APP_URL=https://...`, `config:cache` |
| CSRF on admin | Session domain mismatch | Check `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE` |

---

*Document version: 1.0 — aligned with production readiness release (June 2026)*
