@extends('layouts.enrollment')

@section('title', 'Enrollment Successful — DMF Dental Training Center')
@section('meta_description', 'Your enrollment at DMF Dental Training Center is confirmed.')



@section('content')

@php
    $hasPaidPayment = $enrollment->payments->contains(fn ($p) => $p->status === 'paid');
    $hasPendingBankTransfer = $enrollment->payments->contains(fn ($p) => $p->payment_method === 'bank_transfer' && $p->status === 'submitted');
    $isPendingVerification = (! $hasPaidPayment) && $hasPendingBankTransfer;

    $pendingInitialBankTransferPayment = $enrollment->payments->first(fn ($p) => $p->payment_method === 'bank_transfer'
        && $p->purpose === \App\Models\Payment::PURPOSE_INITIAL
        && $p->status === 'submitted');

    $hasPendingBalanceBankTransfer = $enrollment->payments->contains(fn ($p) => $p->payment_method === 'bank_transfer'
        && $p->purpose === \App\Models\Payment::PURPOSE_BALANCE
        && $p->status === 'submitted');
@endphp

{{-- ── Progress Indicator ── --}}
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pt-10">
    <div class="flex items-center gap-0 mb-10">
        @php
        $steps = [
            ['num' => 1, 'label' => 'Details', 'state' => 'done'],
            ['num' => 2, 'label' => 'Payment', 'state' => 'done'],
            ['num' => 3, 'label' => 'Confirm', 'state' => 'active'],
        ];
        @endphp
        @foreach($steps as $i => $step)
        <div class="flex items-center {{ $i < count($steps)-1 ? 'flex-1' : '' }}">
            <div class="flex flex-col items-center gap-1">
                <span class="w-9 h-9 rounded-full border-2 flex items-center justify-center text-sm font-bold
                             {{ $step['state'] === 'active' ? 'bg-brand-600 border-brand-600 text-white shadow-md' : '' }}
                             {{ $step['state'] === 'done'   ? 'bg-brand-100 border-brand-300 text-brand-700' : '' }}">
                    @if($step['state'] === 'done')
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @endif
                </span>
                <span class="text-xs font-medium {{ $step['state'] === 'active' ? 'text-brand-700' : 'text-gray-400' }}">{{ $step['label'] }}</span>
            </div>
            @if($i < count($steps)-1)
            <div class="flex-1 h-0.5 bg-brand-300 mx-2 mb-4 rounded-full"></div>
            @endif
        </div>
        @endforeach
    </div>
</div>


@if(session('success'))
<div class="max-w-2xl mx-auto px-4 sm:px-6 pt-4">
    <div class="p-4 rounded-xl border border-emerald-100 bg-emerald-50 text-sm text-emerald-800 text-center font-medium">
        {{ session('success') }}
    </div>
</div>
@endif

{{-- ── Main success card ── --}}
<div class="max-w-2xl mx-auto px-4 sm:px-6 pb-16">
    <div class="success-card bg-white rounded-3xl border border-gray-100 shadow-card overflow-hidden">

        {{-- Celebration header --}}
        <div class="relative bg-gradient-to-br from-brand-600 to-brand-800 px-8 py-12 text-center overflow-hidden">

            {{-- Decorative bg circles --}}
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/3"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/3"></div>

            {{-- Animated success icon --}}
            <div class="relative inline-flex items-center justify-center mb-6">
                {{-- Ripple rings --}}
                <span class="absolute w-24 h-24 rounded-full bg-white/20 ripple-ring"></span>
                <span class="absolute w-20 h-20 rounded-full bg-white/15 ripple-ring" style="animation-delay:0.4s"></span>

                {{-- Check circle --}}
                <span class="check-circle relative z-10 w-16 h-16 rounded-full bg-white flex items-center justify-center shadow-lg">
                    <svg class="w-9 h-9 text-brand-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
            </div>

            <h1 class="text-3xl md:text-4xl font-extrabold text-white mb-2 relative z-10">
                {{ $isPendingVerification ? 'Payment Submitted!' : 'Enrollment Successful!' }}
            </h1>
            <p class="text-brand-100/80 relative z-10 text-base">
                @if($isPendingVerification)
                    Please wait while our team verifies your bank transfer, {{ $enrollment->first_name }}.
                @else
                    Welcome to DMF Dental Training Center, {{ $enrollment->first_name }}! 🎉
                @endif
            </p>
        </div>


        {{-- Body: enrollment summary --}}
        <div class="px-6 sm:px-8 py-8 space-y-6">

            {{-- Reference number badge --}}
            <div class="flex items-center justify-center">
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-brand-50 border border-brand-100 rounded-full text-sm">
                    <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                    <span class="text-gray-500">Reference No.</span>
                    <span class="font-mono font-bold text-brand-700 tracking-wider">{{ $enrollment->reference_number }}</span>
                </div>
            </div>

            {{-- Detail rows --}}
            <div class="rounded-2xl border border-gray-100 divide-y divide-gray-50 overflow-hidden">
                @php
                $details = [
                    ['label' => 'Full Name',     'value' => $enrollment->full_name, 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                    ['label' => 'Program / Package', 'value' => $purchasable?->name ?? '—', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
                    ['label' => 'Payment Type',  'value' => $enrollment->payment_type === 'downpayment' ? 'Downpayment' : 'Full Payment', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                    ['label' => 'Date Enrolled', 'value' => $enrollment->created_at->timezone('Asia/Manila')->format('F j, Y'), 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ];
                if ($enrollment->payment_type === 'downpayment') {
                    $bal = $balanceTuitionDue ?? $enrollment->computed_balance_tuition_due;
                    $hasPendingBalanceBankTransfer = $enrollment->payments->contains(fn ($p) => $p->payment_method === 'bank_transfer'
                        && $p->purpose === \App\Models\Payment::PURPOSE_BALANCE
                        && $p->status === 'submitted');

                    $pendingInitialTuition = (int) ($pendingInitialBankTransferPayment?->tuition_amount ?? 0);
                    if ($pendingInitialTuition <= 0 && $pendingInitialBankTransferPayment) {
                        $pendingInitialTuition = max(
                            0,
                            (int) round(((int) ($pendingInitialBankTransferPayment->amount ?? 0)) / 100) - \App\Services\EnrollmentPricingService::CONVENIENCE_FEE_PESOS
                        );
                    }

                    $expectedRemainingAfterVerification = $pendingInitialTuition > 0
                        ? max(0, ($applicableTuitionTotal ?? \App\Services\EnrollmentPricingService::applicableTuitionTotal($enrollment)) - $pendingInitialTuition)
                        : null;

                    if ($pendingInitialBankTransferPayment && $pendingInitialTuition > 0) {
                        array_splice($details, 3, 0, [
                            [
                                'label' => 'Downpayment (pending verification)',
                                'value' => '₱' . number_format($pendingInitialTuition),
                                'hint' => $expectedRemainingAfterVerification !== null
                                    ? ('We received your downpayment proof of payment. Once verified, your remaining tuition will be ₱' . number_format($expectedRemainingAfterVerification) . '.')
                                    : 'We received your downpayment proof of payment. Once verified, your tuition paid and remaining balance will update.',
                                'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                            ],
                        ]);
                    }

                    $remainingLabel = $hasPendingBalanceBankTransfer ? 'Remaining tuition (pending verification)' : 'Remaining tuition';
                    $remainingValue = $hasPendingBalanceBankTransfer ? '₱0' : ('₱' . number_format($bal));
                    $remainingHint = $hasPendingBalanceBankTransfer
                        ? ('We received your balance proof of payment. Once verified, your remaining tuition will be settled (₱' . number_format($bal) . ').')
                        : (($pendingInitialBankTransferPayment && $pendingInitialTuition > 0 && $expectedRemainingAfterVerification !== null)
                            ? ('We received your downpayment proof of payment. Once verified, your remaining tuition will be ₱' . number_format($expectedRemainingAfterVerification) . '.')
                            : null);
                    $remainingHint = $remainingHint ?? ($bal > 0 ? 'Early-bird pricing applies if you complete payment on or before the discount end date. After that, the regular list price applies.' : null);

                    array_splice($details, 3, 0, [
                        ['label' => 'Tuition paid (cumulative)', 'value' => '₱' . number_format($enrollment->amount_paid_tuition), 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['label' => $remainingLabel, 'value' => $remainingValue, 'hint' => $remainingHint, 'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 5h.01M12 12h3m-3 4h.01M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'],
                    ]);
                }
                if($enrollment->email) {
                    array_splice($details, 1, 0, [['label' => 'Email', 'value' => $enrollment->email, 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z']]);
                }
                if($enrollment->phone) {
                    array_splice($details, 2, 0, [['label' => 'Phone', 'value' => $enrollment->phone, 'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z']]);
                }
                @endphp

                @foreach($details as $detail)
                <div class="flex items-start gap-3 px-5 py-3.5 bg-white">
                    <div class="w-8 h-8 rounded-lg bg-brand-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $detail['icon'] }}"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0 flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:items-start sm:gap-4">
                        <span class="text-sm text-gray-400 flex-shrink-0">{{ $detail['label'] }}</span>
                        <div class="min-w-0 sm:text-right">
                            <span class="font-semibold text-gray-800 text-sm break-words block">{{ $detail['value'] }}</span>
                            @if(! empty($detail['hint'] ?? null))
                            <p class="text-xs font-normal text-gray-500 mt-1 sm:max-w-md sm:ml-auto sm:text-right leading-snug">{{ $detail['hint'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Next steps --}}
            <div>
                <h3 class="text-sm font-bold text-gray-700 mb-3">What happens next?</h3>
                <div class="space-y-2.5">
                    @php
                    $agreementMailtoSubject = rawurlencode('Signed Enrollment Agreement — ' . $enrollment->reference_number);
                    $agreementMailtoBody = rawurlencode(
                        "Hello,\n\nPlease find my signed enrollment agreement attached.\n\nReference No.: {$enrollment->reference_number}\nName: {$enrollment->full_name}\n\nThank you."
                    );
                    $agreementMailtoUrl = "mailto:{$agreementSubmissionEmail}?subject={$agreementMailtoSubject}&body={$agreementMailtoBody}";
                    $nextSteps = [
                        ['step' => '1', 'text' => 'Download the enrollment agreement using the button below.'],
                        ['step' => '2', 'html' => 'Sign the form (print and scan, or sign digitally), then email a copy to <a href="' . e($agreementMailtoUrl) . '" class="text-brand-600 hover:underline font-medium">' . e($agreementSubmissionEmail) . '</a>. Use reference no. <span class="font-mono font-semibold text-gray-800">' . e($enrollment->reference_number) . '</span> in the email subject so we can match it to your enrollment.'],
                        ['step' => '3', 'text' => 'Our team will verify your enrollment within 24 hours.'],
                        ['step' => '4', 'text' => 'Join your first session on your scheduled date. Good luck!'],
                    ];
                    @endphp
                    @foreach($nextSteps as $ns)
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">{{ $ns['step'] }}</span>
                        @if(! empty($ns['html'] ?? null))
                        <p class="text-sm text-gray-600 leading-relaxed">{!! $ns['html'] !!}</p>
                        @else
                        <p class="text-sm text-gray-600 leading-relaxed">{{ $ns['text'] }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex flex-col sm:flex-row gap-3 pt-2 sm:items-stretch">
                <a href="{{ route('enroll.agreement.download', ['reference_number' => $enrollment->reference_number]) }}"
                   id="download-agreement-btn"
                   class="success-cta flex flex-1 flex-col items-center justify-center gap-2 px-4 py-4 min-h-[5.25rem] bg-white text-brand-700 font-semibold rounded-xl border border-brand-100 hover:border-brand-300 hover:bg-brand-50 transition-all duration-200 text-center">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="text-sm leading-snug text-center text-balance px-1">Download Agreement</span>
                </a>
                <a href="{{ url('/') }}"
                   id="back-home-btn"
                   class="success-cta flex flex-1 flex-col items-center justify-center gap-2 px-4 py-4 min-h-[5.25rem] bg-brand-600 text-white font-semibold rounded-xl shadow-sm hover:bg-brand-700 transition-all duration-200 text-center">
                    <svg class="w-5 h-5 flex-shrink-0 opacity-95" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span class="text-sm leading-snug">Back to Home</span>
                </a>
            </div>

        </div>

        {{-- Footer note --}}
        <div class="border-t border-gray-100 px-8 py-4 text-center">
            <p class="text-xs text-gray-400">
                Questions? <a href="tel:+6329973580654" class="text-brand-600 hover:underline font-medium">+63 997 358 0654</a>
            </p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/success-confetti.js') }}"></script>
@endsection
