# PayMongo Dynamic Fee Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded ₱50 convenience fee with a dynamic PayMongo fee: 3.125% of the base amount + ₱13 for card payments, and ₱0 for bank transfers.

**Architecture:** Add a `convenienceFeeForPaymentMethod(string $paymentMethod, int $baseAmountPesos): int` static method to `EnrollmentPricingService` that encapsulates the fee formula. All callers in PaymongoService, BankTransferService, and EnrollmentBalanceService switch to this method. The payment and balance views update their fee display using Alpine.js to reactively swap between the card and bank-transfer fee based on the currently selected radio button.

**Tech Stack:** PHP 8.4, Laravel 11, Blade, Alpine.js (already used in views), PHPUnit 11.

## Global Constraints

- Do not add new database columns or migrations — work with existing schema.
- Fee stored in `enrollments.convenience_fee` is a snapshot; update it to the card fee (worst-case default) for new card checkouts; for bank transfer, set it to 0 when the Payment record is created.
- Keep `CONVENIENCE_FEE_PESOS = 50` constant on `EnrollmentPricingService` **only** as the legacy fallback for the SQL back-calculation in `EnrollmentFinancialService` (covers old payment rows where `tuition_amount = 0`). Do **not** use it in any new fee logic.
- PHP rounding for card fee: `(int) ceil($baseAmountPesos * 0.03125) + 13`.
- Run `vendor/bin/pint --dirty --format agent` after every PHP change.
- Run `php artisan test --compact` tests with a filter after each task.

---

## File Map

| File | Change |
|------|--------|
| `app/Services/EnrollmentPricingService.php` | Add `convenienceFeeForPaymentMethod()` static method |
| `app/Services/PaymongoService.php` | Use new method; update `enrollment.convenience_fee` after checkout session creation |
| `app/Services/BankTransferService.php` | Use new method (returns 0); update `enrollment.convenience_fee` to 0 |
| `app/Services/EnrollmentBalanceService.php` | Pass both card fee and bank-transfer fee (0) to view |
| `app/Http/Controllers/EnrollmentController.php` | Pass both fees to payment view |
| `resources/views/enrollment/payment.blade.php` | Alpine.js: reactively toggle fee display, total, and Pay Now button label |
| `resources/views/enrollment/balance.blade.php` | Alpine.js: reactively toggle fee display and total |
| `tests/Unit/EnrollmentPricingServiceTest.php` | Add tests for `convenienceFeeForPaymentMethod()` |
| `tests/Feature/EnrollmentFinancialLedgerTest.php` | Verify tuition credited correctly for card and bank-transfer payments |

---

### Task 1: Add `convenienceFeeForPaymentMethod()` to `EnrollmentPricingService`

**Files:**
- Modify: `app/Services/EnrollmentPricingService.php`
- Test: `tests/Unit/EnrollmentPricingServiceTest.php`

**Interfaces:**
- Produces: `EnrollmentPricingService::convenienceFeeForPaymentMethod(string $paymentMethod, int $baseAmountPesos): int`
  - `'card'` → `(int) ceil($baseAmountPesos * 0.03125) + 13`
  - `'bank_transfer'` → `0`
  - any other value → `0`

- [ ] **Step 1: Write failing tests**

Add these test methods to `tests/Unit/EnrollmentPricingServiceTest.php`, inside the existing class (before the last `}`):

```php
public function test_card_fee_is_3125_percent_plus_13(): void
{
    // 10_000 * 0.03125 = 312.5 → ceil = 313, + 13 = 326
    $this->assertSame(326, EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 10_000));
}

public function test_card_fee_rounds_up_fractional_cents(): void
{
    // 1_000 * 0.03125 = 31.25 → ceil = 32, + 13 = 45
    $this->assertSame(45, EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 1_000));
}

public function test_card_fee_on_exact_amount(): void
{
    // 16_000 * 0.03125 = 500.0 → ceil = 500, + 13 = 513
    $this->assertSame(513, EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 16_000));
}

public function test_bank_transfer_fee_is_zero(): void
{
    $this->assertSame(0, EnrollmentPricingService::convenienceFeeForPaymentMethod('bank_transfer', 10_000));
}

public function test_unknown_payment_method_fee_is_zero(): void
{
    $this->assertSame(0, EnrollmentPricingService::convenienceFeeForPaymentMethod('gcash', 10_000));
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter=test_card_fee_is_3125_percent_plus_13
```

Expected: FAIL with "Call to undefined method ... convenienceFeeForPaymentMethod"

- [ ] **Step 3: Add the method to `EnrollmentPricingService`**

In `app/Services/EnrollmentPricingService.php`, add after the `CONVENIENCE_FEE_PESOS` constant declaration (after line 33):

```php
/**
 * PayMongo processing fee for the given payment method.
 *
 * - card: 3.125% of base amount (rounded up) + ₱13 flat
 * - bank_transfer: ₱0
 */
public static function convenienceFeeForPaymentMethod(string $paymentMethod, int $baseAmountPesos): int
{
    return match ($paymentMethod) {
        'card' => (int) ceil($baseAmountPesos * 0.03125) + 13,
        default => 0,
    };
}
```

- [ ] **Step 4: Run pint and all new tests**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=test_card_fee
php artisan test --compact --filter=test_bank_transfer_fee_is_zero
php artisan test --compact --filter=test_unknown_payment_method_fee_is_zero
```

Expected: All 5 new tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/EnrollmentPricingService.php tests/Unit/EnrollmentPricingServiceTest.php
git commit -m "feat: add convenienceFeeForPaymentMethod() — 3.125%+13 for card, 0 for bank transfer"
```

---

### Task 2: Update `PaymongoService` to use the dynamic card fee

**Files:**
- Modify: `app/Services/PaymongoService.php`

**Interfaces:**
- Consumes: `EnrollmentPricingService::convenienceFeeForPaymentMethod('card', int $baseAmountPesos): int`
- Also updates `enrollment.convenience_fee` after checkout session is created, so the snapshot in the DB matches the actual fee charged.

- [ ] **Step 1: Write a failing test**

Create file `tests/Feature/PaymongoCardFeeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Services\EnrollmentPricingService;
use App\Services\PaymongoService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymongoCardFeeTest extends TestCase
{
    public function test_card_checkout_applies_dynamic_fee(): void
    {
        $program = Program::factory()->create([
            'price_full' => 10_000,
            'price_early' => null,
        ]);

        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'payment_type' => 'full',
            'base_amount' => 10_000,
            'convenience_fee' => 50,
            'total_amount' => 10_050,
            'tuition_list_amount' => 10_000,
        ]);

        $expectedFee = EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 10_000);
        $expectedTotal = 10_000 + $expectedFee;

        Http::fake([
            'https://api.paymongo.com/*' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.com/cs_test_123',
                    ],
                ],
            ], 200),
        ]);

        $service = app(PaymongoService::class);
        $service->createCheckoutSession($enrollment);

        $payment = Payment::where('enrollment_id', $enrollment->getKey())->first();

        $this->assertSame($expectedTotal * 100, $payment->amount);
        $this->assertSame(10_000, $payment->tuition_amount);

        $enrollment->refresh();
        $this->assertSame($expectedFee, (int) $enrollment->convenience_fee);
        $this->assertSame($expectedTotal, (int) $enrollment->total_amount);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=test_card_checkout_applies_dynamic_fee
```

Expected: FAIL — payment amount will be `(10000 + 50) * 100` instead of the new dynamic fee.

- [ ] **Step 3: Update `PaymongoService::createCheckoutSession()`**

In `app/Services/PaymongoService.php`, replace line 29:

```php
$fee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;
```

with:

```php
$fee = EnrollmentPricingService::convenienceFeeForPaymentMethod('card', 0); // will be set per-branch below
```

Then update the two branches inside `createCheckoutSession()`. The `$fee` must use the **tuition portion** as the base. Replace the block that defines `$tuitionPortion` and `$totalPesos` in both branches:

For the `PURPOSE_BALANCE` branch (starting around current line 31), replace:

```php
        if ($purpose === Payment::PURPOSE_BALANCE) {
            if ($enrollment->payment_type !== 'downpayment') {
                throw new RuntimeException('Balance checkout is only available for downpayment enrollments.');
            }

            $this->enrollmentFinancialService->recalculateEnrollmentFinancials($enrollment);
            $enrollment->refresh();

            $tuitionPortion = EnrollmentPricingService::balanceTuitionDue($enrollment);
            if ($tuitionPortion <= 0) {
                throw new RuntimeException('No remaining tuition balance.');
            }

            $totalPesos = $tuitionPortion + $fee;
            $lineSuffix = 'Balance';
        } else {
            $tuitionPortion = (int) $enrollment->base_amount;
            $totalPesos = $tuitionPortion + $fee;
            $lineSuffix = $enrollment->payment_type === 'downpayment' ? 'Downpayment' : 'Full Payment';
        }
```

with:

```php
        if ($purpose === Payment::PURPOSE_BALANCE) {
            if ($enrollment->payment_type !== 'downpayment') {
                throw new RuntimeException('Balance checkout is only available for downpayment enrollments.');
            }

            $this->enrollmentFinancialService->recalculateEnrollmentFinancials($enrollment);
            $enrollment->refresh();

            $tuitionPortion = EnrollmentPricingService::balanceTuitionDue($enrollment);
            if ($tuitionPortion <= 0) {
                throw new RuntimeException('No remaining tuition balance.');
            }

            $fee = EnrollmentPricingService::convenienceFeeForPaymentMethod('card', $tuitionPortion);
            $totalPesos = $tuitionPortion + $fee;
            $lineSuffix = 'Balance';
        } else {
            $tuitionPortion = (int) $enrollment->base_amount;
            $fee = EnrollmentPricingService::convenienceFeeForPaymentMethod('card', $tuitionPortion);
            $totalPesos = $tuitionPortion + $fee;
            $lineSuffix = $enrollment->payment_type === 'downpayment' ? 'Downpayment' : 'Full Payment';
        }
```

Remove the now-unused standalone `$fee` declaration from line 29 entirely (delete that whole line).

After `$payment->update([...])` near the end (the update that sets `paymongo_checkout_session_id`), add:

```php
            $enrollment->update([
                'convenience_fee' => $fee,
                'total_amount' => $tuitionPortion + $fee,
            ]);
```

- [ ] **Step 4: Run pint and test**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=test_card_checkout_applies_dynamic_fee
```

Expected: PASS.

- [ ] **Step 5: Run existing PayMongo-related tests to check for regressions**

```bash
php artisan test --compact tests/Unit/EnrollmentPricingServiceTest.php
php artisan test --compact tests/Feature/EnrollmentFinancialLedgerTest.php
```

Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PaymongoService.php tests/Feature/PaymongoCardFeeTest.php
git commit -m "feat: use dynamic card fee (3.125%+13) in PayMongo checkout session"
```

---

### Task 3: Update `BankTransferService` to apply zero fee

**Files:**
- Modify: `app/Services/BankTransferService.php`

**Interfaces:**
- Consumes: `EnrollmentPricingService::convenienceFeeForPaymentMethod('bank_transfer', int): int` (returns 0)

- [ ] **Step 1: Write a failing test**

Create `tests/Feature/BankTransferZeroFeeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Program;
use App\Services\BankTransferService;
use Tests\TestCase;

class BankTransferZeroFeeTest extends TestCase
{
    public function test_bank_transfer_payment_has_zero_fee(): void
    {
        $program = Program::factory()->create([
            'price_full' => 10_000,
        ]);

        $enrollment = Enrollment::factory()->create([
            'purchasable_type' => Program::class,
            'purchasable_id' => $program->getKey(),
            'payment_type' => 'full',
            'base_amount' => 10_000,
            'convenience_fee' => 50,
            'total_amount' => 10_050,
            'tuition_list_amount' => 10_000,
            'reference_number' => 'DMF-BTZERO-TEST01',
        ]);

        $service = app(BankTransferService::class);
        $service->startInitialBankTransfer($enrollment);

        $payment = Payment::where('enrollment_id', $enrollment->getKey())->first();

        // Amount should equal tuition only (no fee) in cents
        $this->assertSame(10_000 * 100, $payment->amount);
        $this->assertSame(10_000, $payment->tuition_amount);

        $enrollment->refresh();
        $this->assertSame(0, (int) $enrollment->convenience_fee);
        $this->assertSame(10_000, (int) $enrollment->total_amount);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=test_bank_transfer_payment_has_zero_fee
```

Expected: FAIL — amount will include the ₱50 fee.

- [ ] **Step 3: Update `BankTransferService::createOrUpdatePendingPayment()`**

In `app/Services/BankTransferService.php`, find (around line 229):

```php
$fee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;
```

Replace it with:

```php
$fee = EnrollmentPricingService::convenienceFeeForPaymentMethod('bank_transfer', $tuitionPortion ?? 0);
```

But `$tuitionPortion` is not yet defined at that point in the method. Instead, move the fee calculation to *after* `$tuitionPortion` is resolved. The method body currently looks like:

```php
private function createOrUpdatePendingPayment(Enrollment $enrollment, string $purpose): Payment
{
    $fee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;

    if ($purpose === Payment::PURPOSE_BALANCE) {
        ...
        $tuitionPortion = EnrollmentPricingService::balanceTuitionDue($enrollment);
        ...
    } else {
        $tuitionPortion = (int) $enrollment->base_amount;
    }

    $totalPesos = $tuitionPortion + $fee;
    ...
}
```

Replace the entire method body logic to:

```php
private function createOrUpdatePendingPayment(Enrollment $enrollment, string $purpose): Payment
{
    if ($purpose === Payment::PURPOSE_BALANCE) {
        if ($enrollment->payment_type !== 'downpayment') {
            throw new RuntimeException('Balance checkout is only available for downpayment enrollments.');
        }

        $tuitionPortion = EnrollmentPricingService::balanceTuitionDue($enrollment);
        if ($tuitionPortion <= 0) {
            throw new RuntimeException('No remaining tuition balance.');
        }
    } else {
        $tuitionPortion = (int) $enrollment->base_amount;
    }

    $fee = EnrollmentPricingService::convenienceFeeForPaymentMethod('bank_transfer', $tuitionPortion);
    $totalPesos = $tuitionPortion + $fee;

    $payment = Payment::updateOrCreate(
        [
            'enrollment_id' => $enrollment->getKey(),
            'purpose' => $purpose,
        ],
        [
            'payment_method' => 'bank_transfer',
            'amount' => (int) round($totalPesos * 100),
            'currency' => 'PHP',
            'status' => 'pending',
            'tuition_amount' => $tuitionPortion,
            'paymongo_checkout_session_id' => null,
            'paymongo_payment_intent_id' => null,
            'paymongo_payment_id' => null,
        ],
    );

    $enrollment->update([
        'convenience_fee' => $fee,
        'total_amount' => $tuitionPortion + $fee,
    ]);

    return $payment;
}
```

> Note: check the original method to preserve the exact `Payment::updateOrCreate` call structure and any other logic in the method.

- [ ] **Step 4: Run pint and test**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=test_bank_transfer_payment_has_zero_fee
```

Expected: PASS.

- [ ] **Step 5: Run existing bank-transfer tests**

```bash
php artisan test --compact tests/Feature/BankTransferVerifyAtomicityTest.php
php artisan test --compact tests/Feature/BankTransferProofStreamTest.php
```

Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/BankTransferService.php tests/Feature/BankTransferZeroFeeTest.php
git commit -m "feat: apply zero convenience fee for bank transfer payments"
```

---

### Task 4: Update `EnrollmentBalanceService` to pass per-method fee to view

**Files:**
- Modify: `app/Services/EnrollmentBalanceService.php`

**Context:** The balance page shows the summary *before* the user picks a payment method. `getBalancePageData()` currently returns a single `convenience_fee`. We need to pass both fees so the view can reactively switch between them via Alpine.js.

**Interfaces:**
- Produces (updated return shape for `getBalancePageData()`):
  ```php
  // added keys:
  'card_fee' => int,           // EnrollmentPricingService::convenienceFeeForPaymentMethod('card', $balance)
  'bank_transfer_fee' => 0,    // always 0
  // convenience_fee kept for backwards compat (= card_fee)
  ```

- [ ] **Step 1: Update `getBalancePageData()` in `EnrollmentBalanceService`**

Find the block (around lines 84–103):

```php
        $fee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;

        $payUrl = URL::temporarySignedRoute(
            ...
        );

        return [
            'enrollment' => $enrollment,
            'purchasable_name' => (string) ($enrollment->purchasable_name_snapshot ?? '—'),
            'balance_tuition' => $balance,
            'convenience_fee' => $fee,
            'total_due' => $balance + $fee,
            'pay_url' => $payUrl,
        ];
```

Replace with:

```php
        $cardFee = EnrollmentPricingService::convenienceFeeForPaymentMethod('card', $balance);
        $bankTransferFee = EnrollmentPricingService::convenienceFeeForPaymentMethod('bank_transfer', $balance);

        $payUrl = URL::temporarySignedRoute(
            'enroll.balance.pay',
            now()->addMinutes(self::PAY_URL_TTL_MINUTES),
            ['reference_number' => $referenceNumber],
        );

        return [
            'enrollment' => $enrollment,
            'purchasable_name' => (string) ($enrollment->purchasable_name_snapshot ?? '—'),
            'balance_tuition' => $balance,
            'card_fee' => $cardFee,
            'bank_transfer_fee' => $bankTransferFee,
            'convenience_fee' => $cardFee,
            'total_due' => $balance + $cardFee,
            'pay_url' => $payUrl,
        ];
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Run existing balance-related tests**

```bash
php artisan test --compact tests/Feature/SuccessPageHidesBalanceCtaWhenBankTransferSubmittedTest.php
```

Expected: All pass. (No test directly covers `getBalancePageData()` return shape yet; the new keys are additive.)

- [ ] **Step 4: Commit**

```bash
git add app/Services/EnrollmentBalanceService.php
git commit -m "feat: pass per-method fees from EnrollmentBalanceService to balance view"
```

---

### Task 5: Update `EnrollmentController::payment()` to pass per-method fees

**Files:**
- Modify: `app/Http/Controllers/EnrollmentController.php`

**Context:** The payment preview page (before enrollment creation) also shows a static fee. The controller sets `$enrollment->convenience_fee = 50` on a transient mock object. We need to pass both fee values so the Blade view can react to payment method selection.

- [ ] **Step 1: Update `EnrollmentController::payment()`**

Find (around lines 100–103):

```php
        $enrollment->base_amount = ($enrollment->payment_type === 'full') ? $purchasable->active_price : $purchasable->downpayment_amount;
        $enrollment->convenience_fee = 50;
        $enrollment->total_amount = $enrollment->base_amount + $enrollment->convenience_fee;
```

Replace with:

```php
        $enrollment->base_amount = ($enrollment->payment_type === 'full') ? $purchasable->active_price : $purchasable->downpayment_amount;
        $cardFee = EnrollmentPricingService::convenienceFeeForPaymentMethod('card', (int) $enrollment->base_amount);
        $enrollment->convenience_fee = $cardFee;
        $enrollment->total_amount = $enrollment->base_amount + $cardFee;
```

Add the `use App\Services\EnrollmentPricingService;` import at the top of the file if it is not already present.

Also update the `view()` call to pass the additional fee variables:

```php
        return view('enrollment.payment', [
            'enrollment' => $enrollment,
            'purchasable' => $purchasable,
            'schedule' => $schedule,
            'includedPrograms' => $purchasable instanceof Package ? $purchasable->programs : collect(),
            'cardFee' => $cardFee,
            'bankTransferFee' => EnrollmentPricingService::convenienceFeeForPaymentMethod('bank_transfer', (int) $enrollment->base_amount),
        ]);
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/EnrollmentController.php
git commit -m "feat: pass per-method fees to payment page view"
```

---

### Task 6: Update payment view to reactively show correct fee

**Files:**
- Modify: `resources/views/enrollment/payment.blade.php`

**Context:** The payment summary card currently shows `$enrollment->convenience_fee` (static). Using Alpine.js (already present in the layout), we make the fee and total reactive to whichever payment method radio is selected.

**Design:** Wrap the payment method form and summary card in a single Alpine.js component (`x-data`). Track selected method. Bind fee display and total display to computed values.

- [ ] **Step 1: Add Alpine.js reactive fee to payment view**

Find the outer form tag (the one wrapping both the payment method section and the order summary). It should be something like:

```html
<form method="POST" action="{{ route('enroll.pay') }}">
```

Replace the opening form tag with:

```html
<form method="POST" action="{{ route('enroll.pay') }}"
      x-data="{
          method: 'card',
          cardFee: {{ $cardFee }},
          bankTransferFee: {{ $bankTransferFee }},
          baseAmount: {{ (int) $enrollment->base_amount }},
          get fee() { return this.method === 'card' ? this.cardFee : this.bankTransferFee; },
          get total() { return this.baseAmount + this.fee; },
          formatPeso(n) { return n.toLocaleString('en-PH'); }
      }">
```

Find the card payment radio input:

```html
<input type="radio" id="pay-card" name="payment_method" value="card" class="sr-only" checked>
```

Replace with:

```html
<input type="radio" id="pay-card" name="payment_method" value="card" class="sr-only"
       x-model="method" checked>
```

Find the bank transfer radio input:

```html
<input type="radio" id="pay-bank-transfer" name="payment_method" value="bank_transfer" class="sr-only">
```

Replace with:

```html
<input type="radio" id="pay-bank-transfer" name="payment_method" value="bank_transfer" class="sr-only"
       x-model="method">
```

Find the Convenience Fee display line:

```html
                    <div class="flex justify-between">
                        <span class="text-gray-500">Convenience Fee</span>
                        <span class="font-semibold text-gray-800">₱{{ number_format($enrollment->convenience_fee) }}</span>
                    </div>
```

Replace with:

```html
                    <div class="flex justify-between">
                        <span class="text-gray-500">Convenience Fee</span>
                        <span class="font-semibold text-gray-800" x-text="'₱' + formatPeso(fee)">₱{{ number_format($enrollment->convenience_fee) }}</span>
                    </div>
```

Find the Total display line:

```html
                        <span class="font-extrabold text-brand-700 text-2xl">₱{{ number_format($enrollment->total_amount) }}</span>
```

Replace with:

```html
                        <span class="font-extrabold text-brand-700 text-2xl" x-text="'₱' + formatPeso(total)">₱{{ number_format($enrollment->total_amount) }}</span>
```

Find the Pay Now button label:

```html
                    Pay Now — ₱{{ number_format($enrollment->total_amount) }}
```

Replace with:

```html
                    Pay Now — <span x-text="'₱' + formatPeso(total)">₱{{ number_format($enrollment->total_amount) }}</span>
```

- [ ] **Step 2: Run pint (PHP files only) — blade files are already formatted**

No pint needed (Blade file).

- [ ] **Step 3: Commit**

```bash
git add resources/views/enrollment/payment.blade.php
git commit -m "feat: reactively update fee and total on payment page based on payment method"
```

---

### Task 7: Update balance view to reactively show correct fee

**Files:**
- Modify: `resources/views/enrollment/balance.blade.php`

**Context:** Same pattern as Task 6. The balance page has its own form and summary card. Apply the same Alpine.js reactivity using `$card_fee` and `$bank_transfer_fee` passed from `EnrollmentBalanceService::getBalancePageData()`.

- [ ] **Step 1: Add Alpine.js reactive fee to balance view**

Find the opening form tag in the balance view. Replace it with:

```html
<form method="POST" action="{{ $pay_url }}"
      x-data="{
          method: 'card',
          cardFee: {{ $card_fee }},
          bankTransferFee: {{ $bank_transfer_fee }},
          baseAmount: {{ $balance_tuition }},
          get fee() { return this.method === 'card' ? this.cardFee : this.bankTransferFee; },
          get total() { return this.baseAmount + this.fee; },
          formatPeso(n) { return n.toLocaleString('en-PH'); }
      }">
```

Find the card radio in balance view:

```html
<input type="radio" id="bal-pay-card" name="payment_method" value="card" class="sr-only" checked>
```

Replace with:

```html
<input type="radio" id="bal-pay-card" name="payment_method" value="card" class="sr-only"
       x-model="method" checked>
```

Find the bank transfer radio in balance view:

```html
<input type="radio" id="bal-pay-bank-transfer" name="payment_method" value="bank_transfer" class="sr-only">
```

Replace with:

```html
<input type="radio" id="bal-pay-bank-transfer" name="payment_method" value="bank_transfer" class="sr-only"
       x-model="method">
```

Find the fee display:

```html
                        <span class="text-gray-500">Payment processing fee</span>
                        <span class="font-semibold text-gray-800">₱{{ number_format($convenience_fee) }}</span>
```

Replace with:

```html
                        <span class="text-gray-500">Payment processing fee</span>
                        <span class="font-semibold text-gray-800" x-text="'₱' + formatPeso(fee)">₱{{ number_format($convenience_fee) }}</span>
```

Find the Total display in balance view:

```html
                        <span class="font-extrabold text-brand-700 text-2xl">₱{{ number_format($total_due) }}</span>
```

Replace with:

```html
                        <span class="font-extrabold text-brand-700 text-2xl" x-text="'₱' + formatPeso(total)">₱{{ number_format($total_due) }}</span>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/enrollment/balance.blade.php
git commit -m "feat: reactively update balance page fee and total based on payment method"
```

---

### Task 8: Update `EnrollmentFinancialService` — remove hardcoded fee from SQL fallback

**Files:**
- Modify: `app/Services/EnrollmentFinancialService.php`

**Context:** `sumPaidTuitionForEnrollment()` uses `CONVENIENCE_FEE_PESOS` as a SQL bound parameter to back-calculate tuition for old payments that have `tuition_amount = 0`. This fallback path is legacy and must not be changed to the new dynamic fee (since old payments were charged ₱50). Keep the constant usage here — no change to the SQL logic.

This task is **documentation-only**: verify no change is needed and add a comment explaining why the constant is still used here.

- [ ] **Step 1: Add an explanatory comment**

In `app/Services/EnrollmentFinancialService.php`, find (around line 49):

```php
        $convenienceFee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;
```

Replace with:

```php
        // Legacy fallback: old payments (tuition_amount = 0) were all charged ₱50 flat.
        // New payments always have tuition_amount set; this expression is only a backstop.
        $convenienceFee = EnrollmentPricingService::CONVENIENCE_FEE_PESOS;
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Run existing financial ledger tests**

```bash
php artisan test --compact tests/Feature/EnrollmentFinancialLedgerTest.php
```

Expected: All pass.

- [ ] **Step 4: Commit**

```bash
git add app/Services/EnrollmentFinancialService.php
git commit -m "chore: document legacy fee fallback in EnrollmentFinancialService SQL expression"
```

---

### Task 9: Final regression run

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test --compact
```

Expected: All pass. If any test references `CONVENIENCE_FEE_PESOS = 50` to compute an expected amount and that test now fails, update it to use `EnrollmentPricingService::convenienceFeeForPaymentMethod('card', $baseAmount)` instead.

- [ ] **Step 2: Update `EnrollmentPricingServiceTest` hardcoded fee references**

The test `test_balance_stays_zero_when_early_bird_fully_paid_before_deadline` creates payments with:

```php
'amount' => (21_500 + EnrollmentPricingService::CONVENIENCE_FEE_PESOS) * 100,
```

and:

```php
'amount' => (19_500 + EnrollmentPricingService::CONVENIENCE_FEE_PESOS) * 100,
```

These use the legacy constant as the fee for the *payment amount*, which is correct for these old-style payments (they have explicit `tuition_amount` set so the fallback path is not exercised). Leave them as-is — they test the balance logic, not the fee calculation.

- [ ] **Step 3: Final commit (if any test fixes were needed)**

```bash
git add -p  # stage only test changes
git commit -m "test: update fee assertions to use dynamic fee where needed"
```

---

## Self-Review

**Spec coverage:**
- ✅ Card fee: 3.125% + ₱13 — `convenienceFeeForPaymentMethod('card', n)` → Task 1
- ✅ Bank transfer fee: ₱0 — `convenienceFeeForPaymentMethod('bank_transfer', n)` → Task 1
- ✅ PayMongo checkout uses card fee — Task 2
- ✅ Bank transfer checkout uses zero fee — Task 3
- ✅ Balance page shows reactive fee — Tasks 4 + 7
- ✅ Initial payment page shows reactive fee — Tasks 5 + 6
- ✅ `enrollment.convenience_fee` snapshot updated to actual fee after checkout — Tasks 2 + 3
- ✅ Legacy SQL fallback in `EnrollmentFinancialService` preserved — Task 8
- ✅ No new migrations needed

**Placeholder scan:** All steps contain actual code. No TBDs.

**Type consistency:** `convenienceFeeForPaymentMethod(string $paymentMethod, int $baseAmountPesos): int` is used consistently throughout. Alpine.js uses `cardFee` / `bankTransferFee` variable names consistently in both views.
