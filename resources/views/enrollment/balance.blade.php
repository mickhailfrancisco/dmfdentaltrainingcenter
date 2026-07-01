@extends('layouts.enrollment')

@section('title', 'Pay Remaining Tuition — DMF Dental Training Center')
@section('meta_description', 'Complete payment for your remaining program tuition.')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-16">
    <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-brand-600 transition-colors mb-6">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back to Home
    </a>

    <div class="mb-8">
        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight mb-2">Pay Remaining Tuition</h1>
        <p class="text-gray-500">Reference <span class="font-mono font-semibold text-brand-700">{{ $enrollment->reference_number }}</span></p>
    </div>

    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl border border-red-100 bg-red-50 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ $pay_url }}" method="POST" class="flex flex-col lg:flex-row gap-6 items-start"
          x-data="{
              method: 'card',
              cardFee: {{ $card_fee }},
              bankTransferFee: {{ $bank_transfer_fee }},
              baseAmount: {{ $balance_tuition }},
              get fee() { return this.method === 'card' ? this.cardFee : this.bankTransferFee; },
              get total() { return this.baseAmount + this.fee; },
              formatPeso(n) { return n.toLocaleString('en-PH'); }
          }">
        @csrf

        <div class="flex-1 space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-soft p-6">
                <h2 class="text-base font-bold text-gray-700 mb-4">Student</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Name</span>
                        <span class="font-medium text-gray-800 text-right">{{ $enrollment->full_name }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-gray-50">
                        <span class="text-gray-400">Program</span>
                        <span class="font-medium text-gray-800 text-right">{{ $purchasable_name }}</span>
                    </div>
                    <div class="flex justify-between py-1.5">
                        <span class="text-gray-400">Tuition paid to date</span>
                        <span class="font-medium text-gray-800">₱{{ number_format($enrollment->amount_paid_tuition) }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-soft p-6">
                <h2 class="text-base font-bold text-gray-700 mb-1">Choose Payment Method</h2>
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

                    <label class="pay-opt block rounded-xl border-2 border-emerald-100 p-3.5 bg-white" for="bal-pay-card">
                        <input type="radio" id="bal-pay-card" name="payment_method" value="card" class="sr-only" x-model="method" checked>
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

                    <label class="pay-opt block rounded-xl border-2 border-slate-200 p-3.5 bg-white" for="bal-pay-bank-transfer">
                        <input type="radio" id="bal-pay-bank-transfer" name="payment_method" value="bank_transfer" class="sr-only" x-model="method">
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
            </div>
        </div>

        <div class="w-full lg:w-80 flex-shrink-0">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-card p-6">
                <h2 class="text-base font-bold text-gray-700 mb-5">Summary</h2>
                <div class="space-y-3 text-sm mb-4">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Remaining tuition</span>
                        <span class="font-semibold text-gray-800">₱{{ number_format($balance_tuition) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Payment processing fee</span>
                        <span class="font-semibold text-gray-800" x-text="'₱' + formatPeso(fee)">₱{{ number_format($convenience_fee) }}</span>
                    </div>
                    <div class="border-t border-gray-100 pt-3 flex justify-between items-center">
                        <span class="font-bold text-gray-800">Total</span>
                        <span class="font-extrabold text-brand-700 text-2xl" x-text="'₱' + formatPeso(total)">₱{{ number_format($total_due) }}</span>
                    </div>
                </div>
                <button type="submit" class="flex items-center justify-center gap-2 w-full px-5 py-3.5 bg-accent-500 text-white font-extrabold rounded-xl shadow-md hover:bg-accent-400 hover:text-white transition-all text-base">
                    Pay remaining tuition
                </button>
                <p class="text-[10px] text-gray-400 text-center mt-3">Early-bird pricing applies if you complete this payment on or before the discount end date. After that date, the regular list price applies.</p>
            </div>
        </div>
    </form>
</div>
@endsection
