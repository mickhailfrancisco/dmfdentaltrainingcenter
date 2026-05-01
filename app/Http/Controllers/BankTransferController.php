<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SubmitBankTransferProofRequest;
use App\Services\BankTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BankTransferController extends Controller
{
    public function __construct(
        protected BankTransferService $bankTransferService,
    ) {}

    public function show(Request $request, string $reference_number, string $purpose): View|RedirectResponse
    {
        try {
            $data = $this->bankTransferService->getStudentPageData($reference_number, $purpose);
        } catch (\Throwable $e) {
            Log::warning('Bank transfer page load failed.', [
                'reference_number' => $reference_number,
                'purpose' => $purpose,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('enroll.form')->with('error', 'Bank transfer session not found.');
        }

        return view('enrollment.bank-transfer', $data);
    }

    public function submit(SubmitBankTransferProofRequest $request, string $reference_number, string $purpose): RedirectResponse
    {
        try {
            $this->bankTransferService->submitProof(
                $reference_number,
                $purpose,
                $request->validated('transfer_reference'),
                $request->validated('manual_method'),
                $request->validated('channel_code'),
                $request->file('photo_1'),
                $request->file('photo_2'),
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('enroll.success', ['ref' => $reference_number]);
    }
}
