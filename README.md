# DMF Dental Training Center

Enrollment and admin platform for **DMF Dental Training Center** — public student enrollment, Paymongo payments, bank transfer verification, and a Filament admin panel for staff.

| Surface | URL |
|---------|-----|
| Public enrollment | `/enroll` |
| Admin panel | `/admin` |

---

## Tech stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 11, PHP 8.4 |
| Admin UI | Filament v3 (Livewire) |
| Frontend | Blade, Tailwind CSS, Vite |
| Payments | Paymongo (card / checkout) |
| Storage | Local + S3-compatible (`dmf_s3` on Laravel Cloud) |
| Database | PostgreSQL or MySQL |
| Tests | PHPUnit |

---

## Features

### Public (students)

- Program and package enrollment with schedule selection
- Early-bird and full-price tuition logic
- Paymongo checkout (card / online)
- Bank transfer proof upload
- Enrollment success page with agreement download and submission instructions

### Admin (staff)

- **Operations overview** — awaiting payment, pending verification, balance due
- **Enrollments** — search, filters, CSV export, payment verification
- **Catalog** — categories, programs, packages, schedules
- **Assistants** — role-based permissions (admin only)
- **Payment channels** — BDO, BPI, ChinaBank, Palawan Express account details and QR uploads (admin only)
- **Enrollment agreement** — upload/replace agreement file, set download name and submission email (admin only)

---

## Local development

### Requirements

- PHP 8.2+
- Composer 2.x
- Node.js 20+
- PostgreSQL or MySQL

### Setup

```bash
git clone <repo-url> dmf-dental
cd dmf-dental

composer install
cp .env.example .env
php artisan key:generate

# Configure DB_* in .env, then:
php artisan migrate
php artisan db:seed   # if seeders are used in your environment

npm install
npm run dev           # or: npm run build

php artisan serve     # or use Laravel Herd / Valet
```

Admin login is created via seeder or `ADMIN_INITIAL_PASSWORD` on first deploy — see [deployment guide](docs/deployment-guide.md).

### Useful commands

```bash
composer run dev              # app + queue + vite (if configured)
php artisan test --compact    # run tests
vendor/bin/pint --dirty       # format changed PHP files
php artisan manual-payment:sync-assets   # sync legacy QR assets to dmf_s3
```

---

## Object storage (`dmf_s3`)

Manual payment QR codes and enrollment agreement files use the **`dmf_s3`** disk, aligned with [Laravel Cloud](https://cloud.laravel.com/) object storage.

**Local / staging** — set in `.env`:

```env
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=
AWS_ENDPOINT=
```

**Production (Laravel Cloud)** — credentials are injected via `LARAVEL_CLOUD_DISK_CONFIG`; no manual S3 env vars needed on Cloud.

Signed URLs for private QR previews are enabled by default (`MANUAL_PAYMENT_USE_SIGNED_URLS=true`).

---

## Enrollment agreement

Admins manage the agreement under **Administration → Enrollment agreement**:

- Upload PDF or Word (max 10 MB) to `dmf_s3`
- Replacing a file deletes the previous one from storage
- **Download name** — base name only; extension (`.pdf`, `.docx`, etc.) follows the uploaded file
- **Submission email** — shown on the student success page; students email their signed copy from their own inbox (the app does not send email)

Until an admin upload exists, downloads fall back to the legacy file at `storage/app/enrollment-agreements/`.

---

## Documentation

| Document | Audience |
|----------|----------|
| [Admin user guide](docs/client-admin-guide.md) | DMF staff (administrators & assistants) |
| [Deployment guide](docs/deployment-guide.md) | Developers / DevOps |

---

## Testing

```bash
# All tests
php artisan test --compact

# Examples
php artisan test --compact tests/Feature/Filament/ManualPaymentChannelTest.php
php artisan test --compact tests/Feature/Filament/ManageEnrollmentAgreementTest.php
php artisan test --compact tests/Feature/EnrollmentAgreementDownloadTest.php
```

---

## Project structure (high level)

```
app/
  Filament/          # Admin panel (resources, pages)
  Http/Controllers/  # Public enrollment & webhooks
  Services/          # Business logic (payments, enrollment, agreements)
config/
  enrollment.php     # Agreement defaults
  manual-payment.php # Payment channel storage
database/migrations/
resources/views/
  enrollment/        # Public enrollment flow
docs/                # Staff & deployment guides
tests/
```

---

## License

Proprietary — DMF Dental Training Center. All rights reserved unless otherwise agreed with the project owner.
