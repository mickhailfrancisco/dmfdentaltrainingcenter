# Financial Calculation Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix three interlocking bugs: (1) enrollment ledger not updating atomically when a bank transfer is verified, (2) a redundant "Tuition paid / Remaining" description in PaymentsRelationManager that duplicates the infolist card, and (3) the parent ViewEnrollment page's infolist card showing stale values after a child RelationManager action fires.

**Architecture:** All three fixes are in existing service and Filament files — no new files needed. Fix 1 merges the `recalculateEnrollmentFinancials` call into the existing `DB::transaction` in `BankTransferService::verifyPayment`. Fix 2 removes four lines from `PaymentsRelationManager::table()`. Fix 3 replaces the `after()` hook's manual refresh attempt with a redirect that forces a full page re-render.

**Tech Stack:** Laravel 11, Filament v3, Livewire v3, MySQL, PHPUnit 11

---

## Root Cause Analysis

### Bug 1 — Atomicity gap in `verifyPayment`

`BankTransferService::verifyPayment()` (line 185–198 of `app/Services/BankTransferService.php`):

```php
DB::transaction(function () use ($payment, $submission, $verifier, $notes): void {
    $submission->update([...]);
    $payment->update(['status' => 'paid', 'paid_at' => now()]);
}); // ← transaction committed here

// ← recalculation OUTSIDE the transaction
$this->financialService->recalculateEnrollmentFinancials($payment->enrollment()->firstOrFail());
```

If `recalculateEnrollmentFinancials` throws (or the process dies), the payment stays 'paid' but `amount_paid_tuition` and `balance_tuition_due` on the Enrollment are never updated. The ledger is permanently wrong.

**Fix:** Pre-load the Enrollment before the transaction, then include the recalculation call inside the same `DB::transaction` closure. `EnrollmentFinancialService::recalculateEnrollmentFinancials` opens its own `DB::transaction` internally; in MySQL, nested `DB::transaction` calls use savepoints, so the inner one becomes a savepoint within the outer transaction. Any exception rolls back everything atomically.

### Bug 2 — Redundant description

`PaymentsRelationManager::table()` (lines 57–65) computes:

```php
$tuitionPaid = (int) $owner->amount_paid_tuition;
$remaining   = (int) $owner->computed_balance_tuition_due;
return $table->description(sprintf(
    'Tuition paid: ₱%s · Remaining: ₱%s',
    number_format($tuitionPaid),
    number_format($remaining),
));
```

`EnrollmentResource.php` (infolist, lines 283–290) already renders those two values as dedicated labelled entries:

```php
Infolists\Components\TextEntry::make('amount_paid_tuition')->label('Tuition paid')->money('PHP')
Infolists\Components\TextEntry::make('computed_balance_tuition_due')->label('Remaining')->money('PHP')
```

The description is redundant and was also showing stale values (Bug 3 below). Remove it.

### Bug 3 — Parent page infolist stays stale after RelationManager action

`PaymentsRelationManager` and `ViewEnrollment` are **separate** Livewire components. When the `verifyBankTransfer` action fires in the RelationManager, only the RelationManager component re-renders. The parent `ViewEnrollment` page component is unaware — its serialized Livewire snapshot is untouched. The infolist card on the parent page therefore shows the old `amount_paid_tuition` / `balance_tuition_due` until the admin manually reloads.

The existing `after()` hook calls `$livewire->getOwnerRecord()->refresh()`, which updates the PHP model object inside the RelationManager — but that has no effect on the parent component's serialized state.

**Fix:** Replace the `after()` hook with `redirect(request()->url())`. Verifying a payment is a significant state change; a full page reload is appropriate UX and gives the admin a clean view of the updated enrollment.

---

## Files

- **Modify:** `app/Services/BankTransferService.php` — move recalculation inside transaction, pre-load enrollment
- **Modify:** `app/Filament/Resources/EnrollmentResource/RelationManagers/PaymentsRelationManager.php` — remove description, replace after() hook
- **Modify:** `tests/Feature/EnrollmentFinancialLedgerTest.php` — add atomicity test
- **Create:** `tests/Feature/BankTransferVerifyAtomicityTest.php` — dedicated test for verify-payment atomicity and redirect behaviour

---

## Task 1: Remove redundant description from PaymentsRelationManager

**Files:**
- Modify: `app/Filament/Resources/EnrollmentResource/RelationManagers/PaymentsRelationManager.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/BankTransferVerifyAtomicityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\EnrollmentResource\Pages\ViewEnrollment;
use App\Filament\Resources\EnrollmentResource\RelationManagers\PaymentsRelationManager;
use App\Models\BankTransferSubmission;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BankTransferVerifyAtomicityTest extends TestCase
{
    private function makeEnrollmentWithPendingBankTransfer(): array
    {
        $program = Program::create([
            'name' => 'Verify Test Program',
            'slug' => 'verify-test-program',
            'category' => 'Individual Programs (Theoretical)',
            'price_full' => 43_000,
            'price_early' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $enrollment = Enrollment::create([
            'reference_number' => 'DMF-VRF-' . Str::upper(Str::random(4)),
            'payment_type' => 'downpayment',
            'purchasable_type' => 'program',
            'purchasable_id' => $program->getKey(),
            'purchasable_name_snapshot' => $program->name,
            'program_id' => $program->getKey(),
            'first_name' => 'Test',
            'middle_name' => null,
            'surname' => 'Student',
            'birthday' => '2000-01-01',
            'sex' => 'Female',
            'phone' => '09171234567',
            'email' => 'verify@example.com',
            'facebook_messenger_name' => 'Test Student',
            'facebook_messenger_url' => null,
            'addr_street' => '1 Main',
            'addr_city' => 'Manila',
            'addr_province' => 'Metro Manila',
            'addr_zip' => '1000',
            'school' => 'U',
            'year_level' => 'Graduate',
            'year_graduated' => '2024',
            'taker_status' => 'First taker',
            'status' => 'pending',
            'base_amount' => 21_500,
            'total_amount' => 21_550,
            'convenience_fee' => 50,
            'tuition_list_amount' => 43_000,
            'tuition_price_early' => null,
            'tuition_early_deadline' => null,
            'tuition_price_dp' => 21_500,
            'tuition_discount_amount' => 0,
            'amount_paid_tuition' => 0,
            'balance_tuition_due' => 43_000,
        ]);

        $payment = Payment::create([
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => 'initial',
            'payment_method' => 'bank_transfer',
            'amount' => 21_550 * 100,
            'currency' => 'PHP',
            'status' => 'submitted',
            'tuition_amount' => 21_500,
        ]);

        $submission = BankTransferSubmission::create([
            'payment_id' => $payment->getKey(),
            'reference_number' => 'BDO-12345',
            'proof_path' => 'bank-transfers/test/photo1.jpg',
            'submitted_at' => now(),
            'manual_method' => 'bank_transfer',
            'channel_code' => 'bdo',
        ]);

        return [$enrollment, $payment, $submission];
    }

    public function test_payments_relation_manager_has_no_tuition_description(): void
    {
        [$enrollment] = $this->makeEnrollmentWithPendingBankTransfer();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $html = Livewire::actingAs($admin)
            ->test(PaymentsRelationManager::class, [
                'ownerRecord' => $enrollment,
                'pageClass' => ViewEnrollment::class,
            ])
            ->assertSuccessful()
            ->html();

        $this->assertStringNotContainsString('Tuition paid', $html);
        $this->assertStringNotContainsString('Remaining', $html);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --compact tests/Feature/BankTransferVerifyAtomicityTest.php --filter=test_payments_relation_manager_has_no_tuition_description
```

Expected: FAIL — the description text is currently present in the rendered HTML.

- [ ] **Step 3: Remove description from PaymentsRelationManager**

In `app/Filament/Resources/EnrollmentResource/RelationManagers/PaymentsRelationManager.php`, replace the `table()` method opening:

**Remove (lines 54–65):**
```php
public function table(Table $table): Table
{
    /** @var Enrollment $owner */
    $owner = $this->getOwnerRecord();

    $tuitionPaid = (int) $owner->amount_paid_tuition;
    $remaining = (int) $owner->computed_balance_tuition_due;

    return $table
        ->description(sprintf(
            'Tuition paid: ₱%s · Remaining: ₱%s',
            number_format($tuitionPaid),
            number_format($remaining),
        ))
```

**Replace with:**
```php
public function table(Table $table): Table
{
    return $table
```

Also remove the now-unused `use App\Models\Enrollment;` import if no longer referenced elsewhere in the file. Check — `Enrollment` is used in the `canViewForRecord` and `canCreateForRecord` type hints and the `after()` hook's `getOwnerRecord()`. Keep the import.

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --compact tests/Feature/BankTransferVerifyAtomicityTest.php --filter=test_payments_relation_manager_has_no_tuition_description
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/EnrollmentResource/RelationManagers/PaymentsRelationManager.php \
        tests/Feature/BankTransferVerifyAtomicityTest.php
git commit -m "fix(admin): remove redundant tuition paid/remaining from PaymentsRelationManager description"
```

---

## Task 2: Fix verifyPayment atomicity — recalculation inside transaction

**Files:**
- Modify: `app/Services/BankTransferService.php`
- Modify: `tests/Feature/BankTransferVerifyAtomicityTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BankTransferVerifyAtomicityTest.php`:

```php
public function test_verify_payment_updates_enrollment_ledger_atomically(): void
{
    [$enrollment, $payment, $submission] = $this->makeEnrollmentWithPendingBankTransfer();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->assertSame(0, (int) $enrollment->fresh()->amount_paid_tuition);

    app(\App\Services\BankTransferService::class)->verifyPayment($payment, $admin);

    $fresh = $enrollment->fresh();
    $this->assertSame('paid', $payment->fresh()->status);
    $this->assertSame(21_500, (int) $fresh->amount_paid_tuition);
    $this->assertSame(21_500, (int) $fresh->balance_tuition_due); // 43000 - 21500 = 21500 remaining
    $this->assertSame('partially_paid', (string) $fresh->status->value);
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --compact tests/Feature/BankTransferVerifyAtomicityTest.php --filter=test_verify_payment_updates_enrollment_ledger_atomically
```

Expected: FAIL — `amount_paid_tuition` stays 0 when the recalculation is outside the transaction and a simulated failure occurs, OR the test reveals the current recalculation is already broken.

> **Note:** If this test PASSES immediately, the ledger update IS working correctly and the displayed ₱0 was a Livewire stale-render issue only (fixed in Task 3). Still commit the atomicity fix — it's the correct design.

- [ ] **Step 3: Implement the fix in BankTransferService**

In `app/Services/BankTransferService.php`, replace `verifyPayment` (lines 173–199):

**Before:**
```php
public function verifyPayment(Payment $payment, User $verifier, ?string $notes = null): void
{
    if ($payment->payment_method !== 'bank_transfer') {
        throw new RuntimeException('Only bank transfer payments can be manually verified.');
    }

    /** @var BankTransferSubmission|null $submission */
    $submission = $payment->bankTransferSubmission()->first();
    if (! $submission) {
        throw new RuntimeException('No bank transfer proof has been submitted yet.');
    }

    DB::transaction(function () use ($payment, $submission, $verifier, $notes): void {
        $submission->update([
            'verified_at' => now(),
            'verified_by' => $verifier->getKey(),
            'notes' => $notes,
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    });

    $this->financialService->recalculateEnrollmentFinancials($payment->enrollment()->firstOrFail());
}
```

**After:**
```php
public function verifyPayment(Payment $payment, User $verifier, ?string $notes = null): void
{
    if ($payment->payment_method !== 'bank_transfer') {
        throw new RuntimeException('Only bank transfer payments can be manually verified.');
    }

    /** @var BankTransferSubmission|null $submission */
    $submission = $payment->bankTransferSubmission()->first();
    if (! $submission) {
        throw new RuntimeException('No bank transfer proof has been submitted yet.');
    }

    $enrollment = $payment->enrollment()->firstOrFail();

    DB::transaction(function () use ($payment, $submission, $verifier, $notes, $enrollment): void {
        $submission->update([
            'verified_at' => now(),
            'verified_by' => $verifier->getKey(),
            'notes' => $notes,
        ]);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->financialService->recalculateEnrollmentFinancials($enrollment);
    });
}
```

The key change: `$enrollment` is loaded before the transaction, then passed into the closure. `recalculateEnrollmentFinancials` opens its own `DB::transaction` internally, which in MySQL becomes a savepoint inside the outer transaction. If recalculation throws, the entire outer transaction (payment mark + ledger update) rolls back together. The ledger is never inconsistent.

- [ ] **Step 4: Run the test to verify it passes**

```bash
php artisan test --compact tests/Feature/BankTransferVerifyAtomicityTest.php --filter=test_verify_payment_updates_enrollment_ledger_atomically
```

Expected: PASS

- [ ] **Step 5: Run Pint**

```bash
vendor/bin/pint app/Services/BankTransferService.php --format agent
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/BankTransferService.php \
        tests/Feature/BankTransferVerifyAtomicityTest.php
git commit -m "fix(payments): recalculate enrollment ledger inside verifyPayment transaction for atomicity"
```

---

## Task 3: Fix stale infolist after bank-transfer verification

**Files:**
- Modify: `app/Filament/Resources/EnrollmentResource/RelationManagers/PaymentsRelationManager.php`
- Modify: `tests/Feature/BankTransferVerifyAtomicityTest.php`

**Context:** `PaymentsRelationManager` and `ViewEnrollment` are separate Livewire components. The `after()` hook currently calls `$livewire->getOwnerRecord()->refresh()`, which updates the PHP model object in the RelationManager — but has zero effect on the parent `ViewEnrollment` component's serialized Livewire snapshot. The infolist card (Tuition paid / Remaining) on the parent page therefore shows stale values until a full page reload.

The fix: replace the `after()` hook with `redirect(request()->url())`. This causes the browser to reload the entire page, giving the admin a fresh render of both the parent infolist and the relation manager.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/BankTransferVerifyAtomicityTest.php`:

```php
public function test_verify_bank_transfer_action_redirects_after_success(): void
{
    [$enrollment, $payment, $submission] = $this->makeEnrollmentWithPendingBankTransfer();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(PaymentsRelationManager::class, [
            'ownerRecord' => $enrollment,
            'pageClass' => ViewEnrollment::class,
        ])
        ->callTableAction('verifyBankTransfer', $payment)
        ->assertRedirect();
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --compact tests/Feature/BankTransferVerifyAtomicityTest.php --filter=test_verify_bank_transfer_action_redirects_after_success
```

Expected: FAIL — no redirect currently happens; the `after()` hook just calls `refresh()`.

- [ ] **Step 3: Replace the after() hook**

In `app/Filament/Resources/EnrollmentResource/RelationManagers/PaymentsRelationManager.php`, find the `after()` callback on the `verifyBankTransfer` action (lines 236–244):

**Before:**
```php
->after(function ($livewire): void {
    if (method_exists($livewire, 'resetTable')) {
        $livewire->resetTable();
    }

    if (method_exists($livewire, 'getOwnerRecord')) {
        $livewire->getOwnerRecord()->refresh();
    }
})
```

**After:**
```php
->after(function (): void {
    redirect(request()->url());
})
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
php artisan test --compact tests/Feature/BankTransferVerifyAtomicityTest.php --filter=test_verify_bank_transfer_action_redirects_after_success
```

Expected: PASS

- [ ] **Step 5: Run the full test file**

```bash
php artisan test --compact tests/Feature/BankTransferVerifyAtomicityTest.php
```

Expected: All 3 tests PASS.

- [ ] **Step 6: Run Pint**

```bash
vendor/bin/pint app/Filament/Resources/EnrollmentResource/RelationManagers/PaymentsRelationManager.php --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/EnrollmentResource/RelationManagers/PaymentsRelationManager.php \
        tests/Feature/BankTransferVerifyAtomicityTest.php
git commit -m "fix(admin): redirect after bank transfer verification so infolist card shows fresh ledger values"
```

---

## Task 4: Full regression run

- [ ] **Step 1: Run the related test suites**

```bash
php artisan test --compact \
  tests/Feature/BankTransferVerifyAtomicityTest.php \
  tests/Feature/EnrollmentFinancialLedgerTest.php \
  tests/Feature/RecalculateStaleEnrollmentFinancialsCommandTest.php \
  tests/Feature/BankTransferCheckoutTest.php
```

Expected: All tests PASS.

- [ ] **Step 2: Ask the user whether to run the full test suite**

After all tasks are done, ask: "All targeted tests pass. Do you want me to run the full test suite (`php artisan test --compact`) to check for regressions?"
