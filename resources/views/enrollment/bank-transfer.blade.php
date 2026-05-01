@extends('layouts.enrollment')

@section('title', 'Bank Transfer — DMF Dental Training Center')
@section('meta_description', 'Pay via bank transfer and upload proof for verification.')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-16">
    <a href="{{ url('/enroll') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-brand-600 transition-colors mb-6">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back to Enrollment Form
    </a>

    <div class="mb-8">
        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight mb-2">Bank Transfer</h1>
        <p class="text-gray-500">
            Reference <span class="font-mono font-semibold text-brand-700">{{ $enrollment->reference_number }}</span>
        </p>
    </div>

    @if(session('error'))
        <div class="mb-6 p-4 rounded-xl border border-red-100 bg-red-50 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl border border-emerald-100 bg-emerald-50 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 space-y-6">
            <form action="{{ $submit_url }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="space-y-6">
                @csrf

                <input type="hidden" name="manual_method" id="manual_method" value="bank_transfer">
                <input type="hidden" name="channel_code" id="channel_code" value="">

                <div class="bg-white rounded-2xl border border-gray-100 shadow-soft p-6">
                    <h2 class="text-base font-bold text-gray-700 mb-3">Step 1 — Choose a payment option</h2>

                @php
                    $banks = (array) config('bank-transfer.banks', []);
                    $remittance = (array) config('bank-transfer.remittance', []);
                @endphp

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5 items-stretch">
                    <label class="block cursor-pointer" for="manual-method-bank">
                        <input type="radio" id="manual-method-bank" name="manual_method_pick" value="bank" class="peer sr-only" checked>
                        <div class="h-full flex items-center gap-3 rounded-xl border-2 p-3.5 bg-white transition
                                    border-gray-100 hover:border-brand-200
                                    peer-checked:border-brand-600 peer-checked:ring-2 peer-checked:ring-brand-200">
                            <span class="flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center bg-slate-50">
                                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 10V8a2 2 0 012-2h10a2 2 0 012 2v2M5 10v10h14V10"/></svg>
                            </span>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">Bank Transfer</p>
                                <p class="text-gray-500 text-xs">Transfer to any bank below</p>
                            </div>
                            <div class="ml-auto hidden peer-checked:flex items-center gap-1 text-xs font-semibold text-brand-700">
                                <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Selected
                            </div>
                        </div>
                    </label>

                    <label class="block cursor-pointer" for="manual-method-remittance">
                        <input type="radio" id="manual-method-remittance" name="manual_method_pick" value="remittance" class="peer sr-only">
                        <div class="h-full flex items-center gap-3 rounded-xl border-2 p-3.5 bg-white transition
                                    border-gray-100 hover:border-brand-200
                                    peer-checked:border-brand-600 peer-checked:ring-2 peer-checked:ring-brand-200">
                            <span class="flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center bg-slate-50">
                                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-3.314 0-6 1.79-6 4s2.686 4 6 4 6-1.79 6-4-2.686-4-6-4zm0 0V6m0 10v2"/></svg>
                            </span>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">Remittance (via Palawan Express)</p>
                                <p class="text-gray-500 text-xs">Send to receiver details</p>
                            </div>
                            <div class="ml-auto hidden peer-checked:flex items-center gap-1 text-xs font-semibold text-brand-700">
                                <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Selected
                            </div>
                        </div>
                    </label>
                </div>

                <div id="manual-bank" class="space-y-4">
                    <p class="text-sm text-gray-500">
                        Transfer to any account below, then upload your photos for verification.
                    </p>

                    <div class="text-xs font-semibold text-gray-600">
                        Select bank
                    </div>
                    <div class="grid grid-cols-1 gap-3">
                        @forelse($banks as $bank)
                            @php
                                $bankCode = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($bank['bank_name'] ?? '')));
                                $bankCode = match ($bankCode) {
                                    'bdo' => 'bdo',
                                    'bpi' => 'bpi',
                                    'chinabank' => 'chinabank',
                                    default => $bankCode,
                                };
                            @endphp

                            <label class="block cursor-pointer" for="bank-code-{{ $bankCode }}">
                                <input
                                    type="radio"
                                    id="bank-code-{{ $bankCode }}"
                                    name="bank_code_pick"
                                    value="{{ $bankCode }}"
                                    class="peer sr-only"
                                    data-bank-name="{{ $bank['bank_name'] ?? 'Bank' }}"
                                    data-account-name="{{ $bank['account_name'] ?? 'Account Name' }}"
                                    data-account-number="{{ $bank['account_number'] ?? '—' }}"
                                    data-logo-url="{{ ! empty($bank['logo_path'] ?? null) ? asset($bank['logo_path']) : '' }}"
                                    data-qr-url="{{ ! empty($bank['qr_path'] ?? null) ? asset($bank['qr_path']) : '' }}"
                                >
                                <div class="flex items-center gap-3 rounded-2xl border p-4 bg-slate-50 transition
                                            border-gray-100 hover:border-brand-200
                                            peer-checked:border-brand-600 peer-checked:ring-2 peer-checked:ring-brand-200">
                                    <div class="w-10 h-10 rounded-xl bg-white border border-gray-100 flex items-center justify-center overflow-hidden">
                                        @if(! empty($bank['logo_path'] ?? null))
                                            <img src="{{ asset($bank['logo_path']) }}" alt="{{ $bank['bank_name'] ?? 'Bank' }} logo" class="w-full h-full object-contain" />
                                        @else
                                            <span class="text-[10px] font-bold text-gray-400">LOGO</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-800">{{ $bank['bank_name'] ?? 'Bank' }}</p>
                                        <p class="text-xs text-gray-500">Select this bank</p>
                                    </div>
                                    <div class="ml-auto hidden peer-checked:flex items-center gap-1 text-xs font-semibold text-brand-700">
                                        <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Selected
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-800">
                                Bank accounts are not configured yet. Please contact support.
                            </div>
                        @endforelse
                    </div>

                    <div class="pt-2">
                        <div class="flex items-center gap-3">
                            <div class="h-px flex-1 bg-gray-100"></div>
                            <div class="text-xs font-semibold text-gray-600">Selected bank details</div>
                            <div class="h-px flex-1 bg-gray-100"></div>
                        </div>
                    </div>

                    <div id="selected-bank-details" class="rounded-2xl border border-gray-200 bg-white p-4">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-gray-100 flex items-center justify-center overflow-hidden" id="selected-bank-logo-wrap">
                                <span class="text-[10px] font-bold text-gray-400">LOGO</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-800" id="selected-bank-name">—</p>
                                <p class="text-xs text-gray-500 mt-0.5" id="selected-account-name">—</p>
                                <p class="font-mono font-semibold text-gray-900 mt-1" id="selected-account-number">—</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="text-xs font-semibold text-gray-600 mb-2">QR code</div>
                            <div class="rounded-2xl bg-white border border-gray-100 flex items-center justify-center overflow-hidden p-3" id="selected-bank-qr-wrap">
                                <span class="text-[10px] font-bold text-gray-400 text-center px-2">QR</span>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">Tip: You can scan this QR code to transfer faster.</p>
                        </div>
                    </div>
                </div>

                <div id="manual-remittance" class="space-y-4 hidden">
                    <p class="text-sm text-gray-500">
                        Send your payment via Palawan Express using the receiver details below.
                    </p>

                    <div class="rounded-2xl border border-gray-100 p-4 bg-slate-50">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-500">Receiver’s name</span>
                                <span class="font-semibold text-gray-800 text-right">{{ $remittance['receiver_name'] ?? 'TBD' }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-500">Address</span>
                                <span class="font-semibold text-gray-800 text-right">{{ $remittance['address'] ?? 'TBD' }}</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-gray-500">Contact number</span>
                                <span class="font-semibold text-gray-800 text-right">{{ $remittance['contact_number'] ?? 'TBD' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-800">
                        Note: please make sure that the spelling of the receiver’s name is correct.
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-soft p-6">
                <h2 class="text-base font-bold text-gray-700 mb-3">Step 2 — Upload proof of payment</h2>
                <p class="text-sm text-gray-500 mb-4">
                    Provide your transfer reference number and upload your proof of payment (JPG/PNG).
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Transfer reference number</label>
                        <input type="text"
                               name="transfer_reference"
                               value="{{ old('transfer_reference') }}"
                               class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-200"
                               placeholder="e.g., 1234567890">
                        @error('transfer_reference')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Upload photo 1</label>
                        <input type="file"
                               name="photo_1"
                               class="block w-full text-sm text-gray-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                        <p class="mt-1 text-xs text-gray-400">Accepted: JPG, PNG. Max 5MB.</p>
                        @error('photo_1')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Upload photo 2 (optional)</label>
                        <input type="file"
                               name="photo_2"
                               class="block w-full text-sm text-gray-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                        <p class="mt-1 text-xs text-gray-400">Accepted: JPG, PNG. Max 5MB.</p>
                        @error('photo_2')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-xs text-gray-600 leading-relaxed">
                        Note: please make sure that the photo is clear and with complete information (bank, account number, account name, date and time of transaction and reference number for verification purposes).
                    </div>

                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3.5 bg-brand-600 text-white font-semibold rounded-xl shadow-sm hover:bg-brand-700 transition-all duration-200">
                        Submit proof of payment for verification
                    </button>
                </div>
            </div>
        </form>
        </div>

        <div class="lg:col-span-1 space-y-4 self-start lg:mt-6 lg:sticky lg:top-24">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-soft p-6">
                <h2 class="text-base font-bold text-gray-700 mb-4">Summary</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Payment purpose</span>
                        <span class="font-semibold text-gray-800">{{ $payment->purpose }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Status</span>
                        <span class="font-semibold text-gray-800">{{ ucfirst($payment->status) }}</span>
                    </div>
                    <div class="border-t border-gray-100 pt-3 flex justify-between items-center">
                        <span class="font-bold text-gray-800">Amount</span>
                        <span class="font-extrabold text-brand-700 text-xl">₱{{ number_format((int) round($payment->amount / 100)) }}</span>
                    </div>
                </div>

                <div class="mt-4 p-4 bg-slate-50 rounded-xl border border-slate-100 text-xs text-gray-500 leading-relaxed">
                    Your payment will be marked as paid after an admin/assistant verifies the uploaded proof.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script>
        (function () {
            const bank = document.getElementById('manual-method-bank');
            const remittance = document.getElementById('manual-method-remittance');
            const bankSection = document.getElementById('manual-bank');
            const remitSection = document.getElementById('manual-remittance');
            const manualMethod = document.getElementById('manual_method');
            const channelCode = document.getElementById('channel_code');
            const bankCodeRadios = document.querySelectorAll('input[name="bank_code_pick"]');
            const bankDetails = document.getElementById('selected-bank-details');
            const bankLogoWrap = document.getElementById('selected-bank-logo-wrap');
            const bankName = document.getElementById('selected-bank-name');
            const accountName = document.getElementById('selected-account-name');
            const accountNumber = document.getElementById('selected-account-number');
            const qrWrap = document.getElementById('selected-bank-qr-wrap');

            function setWrapImage(wrap, url, alt, fallbackText) {
                if (!wrap) {
                    return;
                }

                while (wrap.firstChild) {
                    wrap.removeChild(wrap.firstChild);
                }

                if (!url) {
                    const span = document.createElement('span');
                    span.className = 'text-[10px] font-bold text-gray-400';
                    span.textContent = fallbackText;
                    wrap.appendChild(span);
                    return;
                }

                const img = document.createElement('img');
                img.src = url;
                img.alt = alt;
                img.className = 'w-48 h-48 object-contain';
                wrap.appendChild(img);
            }

            function renderSelectedBankDetails() {
                if (!bankDetails) {
                    return;
                }

                const checked = document.querySelector('input[name="bank_code_pick"]:checked');
                if (!checked) {
                    return;
                }

                if (bankName) bankName.textContent = checked.getAttribute('data-bank-name') || '—';
                if (accountName) accountName.textContent = checked.getAttribute('data-account-name') || '—';
                if (accountNumber) accountNumber.textContent = checked.getAttribute('data-account-number') || '—';

                setWrapImage(
                    bankLogoWrap,
                    checked.getAttribute('data-logo-url') || '',
                    'Bank logo',
                    'LOGO'
                );

                setWrapImage(
                    qrWrap,
                    checked.getAttribute('data-qr-url') || '',
                    'Bank QR',
                    'QR'
                );
            }

            function sync() {
                const isBank = bank && bank.checked;
                if (bankSection) bankSection.classList.toggle('hidden', !isBank);
                if (remitSection) remitSection.classList.toggle('hidden', isBank);

                if (manualMethod) {
                    manualMethod.value = isBank ? 'bank_transfer' : 'remittance';
                }

                if (!channelCode) {
                    return;
                }

                if (!isBank) {
                    channelCode.value = 'palawan_express';
                    return;
                }

                const checked = document.querySelector('input[name="bank_code_pick"]:checked');
                if (checked && checked.value) {
                    channelCode.value = checked.value;
                    renderSelectedBankDetails();
                    return;
                }

                const first = document.querySelector('input[name="bank_code_pick"]');
                if (first && first.value) {
                    first.checked = true;
                    channelCode.value = first.value;
                    renderSelectedBankDetails();
                }
            }

            if (bank) bank.addEventListener('change', sync);
            if (remittance) remittance.addEventListener('change', sync);
            if (bankCodeRadios && bankCodeRadios.length) {
                bankCodeRadios.forEach(radio => radio.addEventListener('change', sync));
            }
            sync();
        })();
    </script>
@endsection

