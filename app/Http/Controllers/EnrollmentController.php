<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnrollPayRequest;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Program;
use App\Services\BankTransferService;
use App\Services\EnrollmentFinancialService;
use App\Services\EnrollmentService;
use App\Services\PaymongoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EnrollmentController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService,
        protected PaymongoService $paymongoService,
        protected EnrollmentFinancialService $enrollmentFinancialService,
        protected BankTransferService $bankTransferService,
    ) {}

    /**
     * Landing / Home page.
     */
    public function landing()
    {
        $packages = Package::query()
            ->where('is_active', true)
            ->with(['programs:id,name,slug'])
            ->orderBy('sort_order')
            ->get();

        return view('enrollment.landing', compact('packages'));
    }

    /**
     * Enrollment form page.
     */
    public function form(Request $request)
    {
        if (! $request->boolean('resume') && ! $request->session()->hasOldInput()) {
            $request->session()->forget('enrollment_data');
        }

        $packages = Package::query()
            ->where('is_active', true)
            ->with(['programs:id,name,slug'])
            ->orderBy('sort_order')
            ->get();

        $programCategories = $this->enrollmentService->getGroupedActivePrograms();
        $oldData = session('enrollment_data', []);

        return view('enrollment.form', compact('packages', 'programCategories', 'oldData'));
    }

    /**
     * Cache form data to session (creates DB record ONLY at checkout step).
     */
    public function store(StoreEnrollmentRequest $request)
    {
        // 1. Validate and store to session temporarily
        $request->session()->put('enrollment_data', $request->validated());

        // 2. Head to payment page (where DB creation happens when clicking Pay Now)
        return redirect()->route('enroll.payment');
    }

    /**
     * Order summary / payment page.
     */
    public function payment(Request $request)
    {
        $oldData = session('enrollment_data');

        // If session expired or they refreshed wildly, send back to form
        if (! $oldData) {
            return redirect()->route('enroll.form');
        }

        $slug = (string) $oldData['program'];
        $purchasable = Package::query()->where('slug', $slug)->first()
            ?? Program::query()->where('slug', $slug)->firstOrFail();

        $schedule = null;
        if ($purchasable instanceof Program && ! empty($oldData['schedule_id'])) {
            $schedule = $purchasable->schedules()
                ->where('id', $oldData['schedule_id'])
                ->first();
        }

        // Pass a mocked transient Enrollment object to the view just so UI won't break
        // because it expects an active $enrollment model structure.
        $enrollment = new Enrollment($oldData);
        $enrollment->first_name = $oldData['first_name']; // ensure name helper fields exist explicitly
        $enrollment->surname = $oldData['surname'];
        $enrollment->payment_type = $oldData['payment_type'] ?? 'full';

        $enrollment->base_amount = ($enrollment->payment_type === 'full') ? $purchasable->active_price : $purchasable->downpayment_amount;
        $enrollment->convenience_fee = 50;
        $enrollment->total_amount = $enrollment->base_amount + $enrollment->convenience_fee;

        return view('enrollment.payment', [
            'enrollment' => $enrollment,
            'purchasable' => $purchasable,
            'schedule' => $schedule,
            'includedPrograms' => $purchasable instanceof Package ? $purchasable->programs : collect(),
        ]);
    }

    /**
     * Create the DB record and process actual checkout (via Webhook/PayMongo or manual)
     */
    public function pay(EnrollPayRequest $request)
    {
        $oldData = session('enrollment_data');

        if (! $oldData) {
            return redirect()->route('enroll.form');
        }

        try {
            $enrollmentId = $request->session()->get('current_enrollment_id');
            $enrollment = $enrollmentId
                ? Enrollment::query()->whereKey($enrollmentId)->first()
                : null;

            if (! $enrollment) {
                $enrollment = $this->enrollmentService->createEnrollment($oldData);
                $request->session()->put('current_enrollment_id', $enrollment->getKey());
            }

            if ($request->validated('payment_method') === 'bank_transfer') {
                $request->session()->put('latest_enrollment_ref', $enrollment->reference_number);

                return $this->bankTransferService->startInitialBankTransfer($enrollment);
            }

            $checkout = $this->paymongoService->createCheckoutSession($enrollment);

            $request->session()->put('latest_enrollment_ref', $enrollment->reference_number);
            $request->session()->put('latest_payment_id', $checkout['payment']->id);

            if (empty($checkout['checkout_url'])) {
                return redirect()->route('enroll.payment')
                    ->with('error', 'Unable to initialize payment checkout. Please try again.');
            }

            return redirect()->away($checkout['checkout_url']);
        } catch (\Throwable $e) {
            Log::error('Enrollment payment checkout failed.', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('enroll.payment')
                ->with('error', 'Payment gateway is temporarily unavailable. Please try again.');
        }
    }

    /**
     * Success / confirmation page.
     */
    public function success(Request $request)
    {
        $enrollment = null;

        $referenceNumber = $request->query('ref') ?: $request->session()->get('latest_enrollment_ref');
        if ($referenceNumber) {
            $enrollment = Enrollment::with(['purchasable', 'payments', 'items.program'])
                ->where('reference_number', $referenceNumber)
                ->first();
        }

        if (! $enrollment) {
            $checkoutSessionId = $request->query('checkout_session_id') ?: $request->query('id');
            if ($checkoutSessionId) {
                $enrollment = $this->paymongoService->syncCheckoutSessionStatus($checkoutSessionId);
            }
        }

        if (! $enrollment) {
            return redirect()->route('enroll.form')
                ->with('error', 'Enrollment session not found. Please try again.');
        }

        // success_url only includes ?ref=… — PayMongo may not have delivered the webhook yet,
        // so the initial payment row can still be "pending". Sync checkout status before ledger math.
        $this->paymongoService->syncPendingCheckoutSessionsForEnrollment($enrollment);
        $enrollment->refresh();
        $enrollment->load('payments');

        $this->enrollmentFinancialService->recalculateEnrollmentFinancials($enrollment);
        $enrollment->refresh();
        $enrollment->load('payments');

        $request->session()->forget(['current_enrollment_id', 'enrollment_data', 'latest_enrollment_ref', 'latest_payment_id', 'balance_checkout_enrollment_id']);

        return view('enrollment.success', [
            'enrollment' => $enrollment,
            'purchasable' => $enrollment->purchasable,
            'items' => $enrollment->items,
        ]);
    }

    public function cancel(Request $request)
    {
        $request->session()->forget(['enrollment_data', 'current_enrollment_id', 'balance_checkout_enrollment_id']);

        return view('enrollment.cancel', [
            'referenceNumber' => $request->query('ref'),
        ]);
    }
}
