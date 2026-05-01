@extends('layouts.enrollment')

@section('title', 'Order Summary & Payment — DMF Dental Training Center')
@section('meta_description', 'Review your enrollment details and complete your payment securely.')



@section('content')


<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-16">

    {{-- Back link --}}
    <a href="{{ url('/enroll') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-brand-600 transition-colors mb-6">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back to Enrollment Form
    </a>

    {{-- Page header --}}
    <div class="mb-8">
        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight mb-2">Order Summary</h1>
        <p class="text-gray-500">Review your details before completing payment.</p>
    </div>
    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl border border-red-100 bg-red-50 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- ── Progress Indicator ── --}}
    <div class="flex items-center gap-0 mb-10">
        @php
        $steps = [
            ['num' => 1, 'label' => 'Details', 'state' => 'done'],
            ['num' => 2, 'label' => 'Payment', 'state' => 'active'],
            ['num' => 3, 'label' => 'Confirm', 'state' => 'pending'],
        ];
        @endphp
        @foreach($steps as $i => $step)
        <div class="flex items-center {{ $i < count($steps)-1 ? 'flex-1' : '' }}">
            <div class="flex flex-col items-center gap-1">
                <span class="w-9 h-9 rounded-full border-2 flex items-center justify-center text-sm font-bold transition-all
                             {{ $step['state'] === 'active'  ? 'bg-brand-600 border-brand-600 text-white shadow-md' : '' }}
                             {{ $step['state'] === 'done'    ? 'bg-brand-100 border-brand-300 text-brand-700' : '' }}
                             {{ $step['state'] === 'pending' ? 'bg-white border-gray-200 text-gray-400' : '' }}">
                    @if($step['state'] === 'done')
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @else
                    {{ $step['num'] }}
                    @endif
                </span>
                <span class="text-xs font-medium {{ $step['state'] === 'active' ? 'text-brand-700' : 'text-gray-400' }}">{{ $step['label'] }}</span>
            </div>
            @if($i < count($steps)-1)
            <div class="flex-1 h-0.5 {{ $step['state'] === 'done' ? 'bg-brand-300' : 'bg-gray-100' }} mx-2 mb-4 rounded-full"></div>
            @endif
        </div>
        @endforeach
    </div>


    <form action="{{ route('enroll.pay') }}" method="POST" class="flex flex-col lg:flex-row gap-6 items-start">
        @csrf

        {{-- ═══════════════════════════════
            LEFT: Payment Method Selection
        ═══════════════════════════════ --}}
        <div class="flex-1 space-y-6">

            {{-- Enrollee summary card --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-soft p-6">
                <h2 class="text-base font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Enrollee Details
                </h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Name</span>
                        <span class="font-medium text-gray-800 text-right">{{ $enrollment->full_name }}</span>
                    </div>
                    @if($enrollment->email)
                    <div class="flex justify-between py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Email</span>
                        <span class="font-medium text-gray-800 text-right">{{ $enrollment->email }}</span>
                    </div>
                    @endif
                    @if($enrollment->phone)
                    <div class="flex justify-between py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Phone</span>
                        <span class="font-medium text-gray-800 text-right">{{ $enrollment->phone }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Program / Package</span>
                        <div class="text-right max-w-[65%]">
                            <span class="font-medium text-gray-800 block">{{ $purchasable->name }}</span>
                            @if(method_exists($purchasable, 'isEarlyBirdActive') && $purchasable->isEarlyBirdActive())
                                <span class="text-[9px] text-accent-600 font-bold bg-accent-50 px-1 py-0.5 rounded inline-block mt-0.5 uppercase tracking-wider">Early Bird Validated</span>
                            @elseif(!empty($purchasable->early_bird_label))
                                <span class="text-[9px] text-gray-400 font-medium block mt-0.5 leading-tight">{{ $purchasable->early_bird_label }}</span>
                            @endif
                        </div>
                    </div>
                    @if(!empty($schedule))
                    <div class="flex justify-between py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Batch</span>
                        <div class="text-right max-w-[65%]">
                            <span class="font-medium text-gray-800 block">{{ $schedule->label }}</span>
                            @if(!empty($schedule->mode))
                                <span class="text-[10px] text-gray-400 font-medium block mt-0.5 leading-tight">{{ $schedule->mode }}</span>
                            @endif
                        </div>
                    </div>
                    @endif
                    <div class="flex justify-between py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Payment Type</span>
                        <span class="font-medium text-brand-600">{{ $enrollment->payment_type === 'downpayment' ? 'Downpayment' : 'Full Payment' }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 text-xs">
                        <span class="text-gray-400">Inclusions</span>
                        <ul class="font-medium text-gray-600 text-right max-w-[70%] space-y-1">
                            @forelse(($includedPrograms ?? collect()) as $incProgram)
                                <li>{{ $incProgram->name }}</li>
                            @empty
                                <li>—</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Payment method --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-soft p-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-base font-bold text-gray-700 flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Choose Payment Method
                    </h2>
                </div>
                <p class="text-xs text-gray-400 mb-5">Select a payment option to continue.</p>

                {{-- PayMongo (Card) --}}
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/30 p-4 mb-4">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-bold text-gray-800">Card (Credit / Debit)</p>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-emerald-200 rounded-lg text-xs font-semibold text-emerald-700">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Powered by PayMongo
                        </span>
                    </div>

                    <label class="pay-opt block rounded-xl border-2 border-emerald-100 p-3.5 bg-white" for="pay-card">
                        <input type="radio" id="pay-card" name="payment_method" value="card" class="sr-only" checked>
                        <div class="flex items-center gap-3">
                            <span class="flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center bg-emerald-50">
                                <svg class="w-6 h-6 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </span>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">Pay with card</p>
                                <p class="text-gray-500 text-xs">Secure checkout via PayMongo</p>
                            </div>
                        </div>
                    </label>
                </div>

                {{-- Bank Transfer --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
                    <p class="text-sm font-bold text-gray-800 mb-3">Bank Transfer</p>

                    <label class="pay-opt block rounded-xl border-2 border-slate-200 p-3.5 bg-white" for="pay-bank-transfer">
                        <input type="radio" id="pay-bank-transfer" name="payment_method" value="bank_transfer" class="sr-only">
                        <div class="flex items-center gap-3">
                            <span class="flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center bg-slate-100">
                                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 002-2v-8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </span>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">Pay via bank transfer</p>
                                <p class="text-gray-500 text-xs">Upload your reference number and proof of payment. Subject to verification.</p>
                            </div>
                        </div>
                    </label>
                </div>
                @error('payment_method')
                    <p class="mt-3 text-xs text-red-600">{{ $message }}</p>
                @enderror

                {{-- PayMongo redirect info --}}
                <div class="mt-4 p-4 bg-slate-50 rounded-xl border border-slate-100 flex items-start gap-3">
                    <svg class="w-4 h-4 text-brand-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        After clicking <strong class="text-gray-700">"Pay Now"</strong>, you will continue to the next step. If you selected <strong class="text-gray-700">Card</strong>, you'll be redirected to <strong class="text-gray-700">PayMongo</strong> to complete payment.
                    </p>
                </div>
            </div>
        </div>


        {{-- ═══════════════════════════════
            RIGHT: Order Total & Pay CTA
        ═══════════════════════════════ --}}
        <div class="w-full lg:w-80 flex-shrink-0 space-y-4">

            {{-- Price breakdown card --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-card p-6">
                <h2 class="text-base font-bold text-gray-700 mb-5">Payment Summary</h2>

                <div class="space-y-3 text-sm mb-4">
                    <div class="flex justify-between items-start gap-4">
                        <span class="text-gray-500 flex-1">{{ $purchasable->name }} <span class="text-xs text-brand-600 font-bold ml-1">[{{ $enrollment->payment_type === 'downpayment' ? 'DP' : 'FULL' }}]</span></span>
                        <span class="font-semibold text-gray-800">₱{{ number_format($enrollment->base_amount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Convenience Fee</span>
                        <span class="font-semibold text-gray-800">₱{{ number_format($enrollment->convenience_fee) }}</span>
                    </div>
                    <div class="border-t border-gray-100 pt-3 flex justify-between items-center">
                        <span class="font-bold text-gray-800 text-base">Total</span>
                        <span class="font-extrabold text-brand-700 text-2xl">₱{{ number_format($enrollment->total_amount) }}</span>
                    </div>
                </div>

                {{-- Pay Now CTA --}}
                <button type="submit"
                   id="pay-now-btn"
                   class="flex items-center justify-center gap-2 w-full px-5 py-3.5 bg-accent-500 text-brand-950 font-extrabold rounded-xl shadow-[0_4px_14px_0_rgba(250,178,27,0.39)] hover:bg-accent-400 hover:shadow-[0_6px_20px_rgba(250,178,27,0.23)] active:scale-[0.98] transition-all duration-200 text-base mb-3">
                    <svg class="w-5 h-5 text-brand-900" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Pay Now — ₱{{ number_format($enrollment->total_amount) }}
                </button>

                {{-- PayMongo secure redirect notice --}}
                <div class="flex flex-col items-center gap-1.5 text-center">
                    <div class="flex items-center gap-1.5 text-xs text-gray-400">
                        <svg class="w-3.5 h-3.5 text-emerald-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        If you selected Card, you will be redirected to PayMongo's secure checkout
                    </div>
                    <p class="text-[10px] text-gray-300">Payments are processed by PayMongo — BSP-registered e-money issuer</p>
                </div>
            </div>

            {{-- Trust badges --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-soft p-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="security-badge inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full border border-emerald-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Card payments secured by PayMongo
                    </span>
                </div>
                <ul class="space-y-2 text-xs text-gray-500">
                    <li class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        256-bit SSL encryption
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        BSP-registered e-money issuer
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        PCI-DSS Level 1 compliant
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Instant email confirmation
                    </li>
                </ul>
            </div>

            {{-- Need help --}}
            <div class="text-center text-xs text-gray-400">
                Need help? <a href="tel:+6329973580654" class="text-brand-600 hover:underline font-medium">+63 997 358 0654</a>
            </div>
        </div>
    </form>
</div>
@endsection
