<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BalanceAlreadySettledException;
use App\Http\Requests\EnrollBalancePayRequest;
use App\Services\EnrollmentBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Student-facing controller for the downpayment balance payment flow.
 *
 * All business logic (enrollment lookup, ledger recalculation, PayMongo
 * checkout orchestration) lives in EnrollmentBalanceService. This
 * controller only bridges the HTTP layer to the service and returns
 * the appropriate response.
 *
 * @author CKD
 *
 * @created 2026-03-26
 *
 * @modified 2026-04-20 CKD — Thin controller refactor; moved logic to EnrollmentBalanceService.
 */
class EnrollmentBalanceController extends Controller
{
    public function __construct(
        protected EnrollmentBalanceService $balanceService,
    ) {}

    /**
     * Signed entry point for paying remaining tuition (downpayment enrollments).
     *
     * @param  string  $reference_number  From the signed route.
     *
     * @author CKD
     *
     * @created 2026-03-26
     *
     * @modified 2026-04-20 CKD
     */
    public function show(Request $request, string $reference_number): RedirectResponse|View
    {
        try {
            $data = $this->balanceService->getBalancePageData($reference_number);
        } catch (BalanceAlreadySettledException $e) {
            return redirect()->route('enroll.success', ['ref' => $e->referenceNumber])
                ->with('success', 'Your tuition balance is fully settled. Thank you!');
        } catch (\RuntimeException $e) {
            return redirect()->route('enroll.form')->with('error', $e->getMessage());
        }

        $request->session()->put('balance_checkout_enrollment_id', $data['enrollment']->id);

        return view('enrollment.balance', $data);
    }

    /**
     * Start PayMongo checkout for the remaining tuition balance.
     *
     * Route is protected by the signed middleware; reference_number from
     * the route is the source of truth — session is kept as a UX fallback.
     *
     * @param  string  $reference_number  From the signed route.
     *
     * @author CKD
     *
     * @created 2026-03-26
     *
     * @modified 2026-04-20 CKD
     */
    public function pay(EnrollBalancePayRequest $request, string $reference_number): RedirectResponse
    {
        try {
            return $this->balanceService->startBalanceCheckout(
                $reference_number,
                $request->validated('payment_method'),
            );
        } catch (\Throwable $e) {
            Log::error('Balance payment checkout failed.', [
                'reference_number' => $reference_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Payment gateway is temporarily unavailable. Please try again.');
        }
    }
}
