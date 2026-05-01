<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ResumeCheckoutPayRequest;
use App\Services\ResumeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ResumeCheckoutController extends Controller
{
    public function __construct(
        protected ResumeCheckoutService $resumeCheckoutService,
    ) {}

    public function show(Request $request, string $reference_number): View|RedirectResponse
    {
        try {
            $data = $this->resumeCheckoutService->getPageData($reference_number);
        } catch (\Throwable $e) {
            Log::warning('Resume checkout page load failed.', [
                'reference_number' => $reference_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('enroll.form')->with('error', 'Checkout session not found.');
        }

        return view('enrollment.checkout', $data);
    }

    public function pay(ResumeCheckoutPayRequest $request, string $reference_number): RedirectResponse
    {
        try {
            $checkoutUrl = $this->resumeCheckoutService->startInitialCheckout(
                $reference_number,
                $request->validated('payment_method'),
            );
        } catch (\Throwable $e) {
            Log::error('Resume checkout pay failed.', [
                'reference_number' => $reference_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->away($checkoutUrl);
    }
}
