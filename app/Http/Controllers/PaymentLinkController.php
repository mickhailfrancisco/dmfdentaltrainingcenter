<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PaymentLinkService;
use Illuminate\Http\RedirectResponse;

class PaymentLinkController extends Controller
{
    public function __construct(
        protected PaymentLinkService $paymentLinkService,
    ) {}

    public function redirect(string $reference_number, string $purpose, string $_): RedirectResponse
    {
        $checkoutUrl = $this->paymentLinkService->startPaymongoCheckoutFromLink(
            $reference_number,
            $purpose,
        );

        return redirect()->away($checkoutUrl);
    }
}
