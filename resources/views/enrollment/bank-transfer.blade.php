@extends('layouts.enrollment')

@section('title', 'Bank Transfer — DMF Dental Training Center')
@section('meta_description', 'Pay via bank transfer and upload proof for verification.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
    <a href="{{ url('/enroll') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-brand-600 transition-colors mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back to Enrollment Form
    </a>

    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight">Bank Transfer</h1>
        <p class="text-sm text-gray-500 mt-1">
            Ref. <span class="font-mono font-semibold text-brand-700">{{ $enrollment->reference_number }}</span>
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
        <div class="order-2 lg:order-1 lg:col-span-9 min-w-0">
            <form action="{{ $submit_url }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="bg-white rounded-2xl border border-gray-100 shadow-soft p-6 sm:p-8">
                @csrf

                <input type="hidden" name="manual_method" id="manual_method" value="bank_transfer">
                <input type="hidden" name="channel_code" id="channel_code" value="">

                @php
                    $banks = (array) config('bank-transfer.banks', []);
                    $remittance = (array) config('bank-transfer.remittance', []);
                @endphp

                <div class="space-y-10">
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-400 mb-3">Where you paid</h2>
                            <div class="grid grid-cols-2 gap-2">
                    <label class="group block min-w-0 cursor-pointer" for="manual-method-bank">
                        <input type="radio" id="manual-method-bank" name="manual_method_pick" value="bank" class="peer sr-only" checked>
                        <div class="flex h-full w-full min-h-[8.5rem] flex-col items-center justify-center gap-2.5 rounded-xl border p-3 bg-slate-50 transition
                                    border-gray-100 hover:border-brand-200
                                    peer-checked:border-brand-600 peer-checked:ring-2 peer-checked:ring-brand-200">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-100 bg-white">
                                <svg class="h-6 w-6 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 10V8a2 2 0 012-2h10a2 2 0 012 2v2M5 10v10h14V10"/></svg>
                            </div>
                            <div class="min-w-0 w-full text-center">
                                <p class="text-sm font-bold leading-tight text-gray-800">Bank transfer</p>
                                <p class="mt-0.5 text-xs leading-snug text-gray-500">BDO, BPI, Chinabank</p>
                                <p class="mt-0.5 hidden text-xs font-semibold text-brand-600 group-has-[:checked]:block">Selected</p>
                            </div>
                        </div>
                    </label>

                    <label class="group block min-w-0 cursor-pointer" for="manual-method-remittance">
                        <input type="radio" id="manual-method-remittance" name="manual_method_pick" value="remittance" class="peer sr-only">
                        <div class="flex h-full w-full min-h-[8.5rem] flex-col items-center justify-center gap-2.5 rounded-xl border p-3 bg-slate-50 transition
                                    border-gray-100 hover:border-brand-200
                                    peer-checked:border-brand-600 peer-checked:ring-2 peer-checked:ring-brand-200">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-100 bg-white">
                                <svg class="h-6 w-6 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-3.314 0-6 1.79-6 4s2.686 4 6 4 6-1.79 6-4-2.686-4-6-4zm0 0V6m0 10v2"/></svg>
                            </div>
                            <div class="min-w-0 w-full text-center">
                                <p class="text-sm font-bold leading-tight text-gray-800">Remittance</p>
                                <p class="mt-0.5 text-xs leading-snug text-gray-500">Palawan Express</p>
                                <p class="mt-0.5 hidden text-xs font-semibold text-brand-600 group-has-[:checked]:block">Selected</p>
                            </div>
                        </div>
                    </label>
                </div>
                        </div>

                <div id="manual-bank" class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
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

                            <label class="group block min-w-0 cursor-pointer" for="bank-code-{{ $bankCode }}">
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
                                <div class="flex min-h-[8.5rem] h-full w-full flex-col items-center justify-center gap-2.5 rounded-xl border p-3 bg-slate-50 transition
                                            border-gray-100 hover:border-brand-200
                                            peer-checked:border-brand-600 peer-checked:ring-2 peer-checked:ring-brand-200">
                                    <div class="w-12 h-12 rounded-lg bg-white border border-gray-100 flex items-center justify-center overflow-hidden shrink-0">
                                        @if(! empty($bank['logo_path'] ?? null))
                                            <img src="{{ asset($bank['logo_path']) }}" alt="{{ $bank['bank_name'] ?? 'Bank' }} logo" class="w-full h-full object-contain" />
                                        @else
                                            <span class="text-xs font-bold text-gray-400">LOGO</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 text-center w-full">
                                        <p class="text-sm font-bold text-gray-800 leading-tight">{{ $bank['bank_name'] ?? 'Bank' }}</p>
                                        <p class="text-xs text-brand-600 font-semibold mt-0.5 hidden group-has-[:checked]:block">Selected</p>
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-800">
                                Bank accounts are not configured yet. Please contact support.
                            </div>
                        @endforelse
                    </div>

                    <div id="selected-bank-details" class="rounded-xl border border-gray-200 bg-slate-50/80 p-3 sm:p-4">
                        <div class="flex items-start gap-2.5">
                            <div class="w-9 h-9 rounded-lg bg-white border border-gray-100 flex items-center justify-center overflow-hidden shrink-0" id="selected-bank-logo-wrap">
                                <span class="text-[9px] font-bold text-gray-400">LOGO</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide">Account to pay</p>
                                <p class="text-sm font-bold text-gray-900" id="selected-bank-name">—</p>
                                <p class="text-xs text-gray-600 mt-0.5" id="selected-account-name">—</p>
                                <p class="font-mono text-sm font-semibold text-gray-900 mt-1 break-all" id="selected-account-number">—</p>
                            </div>
                        </div>

                        <div class="mt-3 border-t border-gray-200 pt-3">
                            <button type="button"
                                    id="open-bank-qr-modal"
                                    class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl border border-brand-200 bg-brand-50 px-4 py-2.5 text-sm font-semibold text-brand-800 shadow-sm transition hover:bg-brand-100 hover:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-300 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-brand-50"
                                    disabled>
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0H9m3 0h3M6 8h2M6 16h2m0-8H6m0 8v-1m12-7h-1"/></svg>
                                Show QR code to scan
                            </button>
                            <p class="mt-2 text-[11px] text-gray-500 leading-snug">
                                Opens a focused view so you can scan comfortably, then close to continue the form.
                            </p>
                        </div>
                    </div>
                </div>

                <div id="manual-remittance" class="space-y-3 hidden">
                    <p class="text-xs text-gray-500">
                        Palawan Express — use the receiver details below.
                    </p>

                    <div class="rounded-xl border border-gray-100 p-3 bg-slate-50">
                        <div class="space-y-1.5 text-sm">
                            <div class="flex justify-between gap-3">
                                <span class="text-gray-500 shrink-0">Receiver</span>
                                <span class="font-semibold text-gray-800 text-right">{{ $remittance['receiver_name'] ?? 'TBD' }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-gray-500 shrink-0">Contact</span>
                                <span class="font-semibold text-gray-800 text-right">{{ $remittance['contact_number'] ?? 'TBD' }}</span>
                            </div>
                        </div>
                    </div>

                    <p class="text-[11px] text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2">
                        Double-check the receiver’s spelling before sending.
                    </p>
                </div>
                    </div>

                    <div class="space-y-3.5 border-t border-gray-100 pt-10">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-400 mb-3">Your proof</h2>
                            <p class="text-xs text-gray-500 mb-4">
                                Reference number and clear photos (JPG/PNG, max 5MB each).
                            </p>
                        </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Transfer reference number</label>
                        <input type="text"
                               name="transfer_reference"
                               value="{{ old('transfer_reference') }}"
                               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-200"
                               placeholder="e.g., 1234567890">
                        @error('transfer_reference')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Upload photo 1</label>
                        <input type="file"
                               name="photo_1"
                               class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                        <p class="mt-1 text-[11px] text-gray-400">JPG or PNG, max 5MB.</p>
                        @error('photo_1')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Upload photo 2 <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="file"
                               name="photo_2"
                               class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                        <p class="mt-1 text-[11px] text-gray-400">JPG or PNG, max 5MB.</p>
                        @error('photo_2')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-[11px] text-gray-600 leading-relaxed bg-slate-50 border border-slate-100 rounded-lg px-3 py-2">
                        Proof should clearly show bank, account, name, date/time, and reference when applicable.
                    </p>

                    <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 bg-brand-600 text-white font-semibold rounded-xl shadow-sm hover:bg-brand-700 transition-all duration-200">
                        Submit for verification
                    </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="order-1 lg:order-2 lg:col-span-3 min-w-0 space-y-4 self-start lg:sticky lg:top-24">
            @php
                $payTotalPesos = (int) round($payment->amount / 100);
                $payTuitionPesos = (int) $payment->tuition_amount;
                $payFeePesos = max(0, $payTotalPesos - $payTuitionPesos);
                $programLabel = $enrollment->purchasable_name_snapshot ?: 'Your program';
                $studentLine = trim(implode(' ', array_filter([
                    $enrollment->first_name,
                    $enrollment->middle_name,
                    $enrollment->surname,
                ])));
                $planLabel = match ((string) ($enrollment->payment_type ?? '')) {
                    'full' => 'Full payment',
                    'downpayment' => 'Down payment',
                    default => $enrollment->payment_type ? ucfirst((string) $enrollment->payment_type) : '—',
                };
                $checkoutScopeLabel = $payment->purpose === \App\Models\Payment::PURPOSE_BALANCE
                    ? 'Remaining tuition (balance checkout)'
                    : 'Tuition (initial checkout)';
            @endphp
            <div class="bg-white rounded-2xl border border-gray-100 shadow-soft p-5 sm:p-6">
                <h2 class="text-base font-bold text-gray-900 mb-1">Payment Summary</h2>
                <p class="text-xs text-gray-500 mb-4">Bill breakdown for this bank transfer.</p>

                <div class="space-y-3 text-sm border-b border-gray-100 pb-4 mb-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Student</p>
                        <p class="font-semibold text-gray-900 mt-0.5 leading-snug">{{ $studentLine !== '' ? $studentLine : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Program</p>
                        <p class="font-semibold text-gray-900 mt-0.5 leading-snug">{{ $programLabel }}</p>
                    </div>
                    <div class="flex justify-between gap-3 text-xs">
                        <span class="text-gray-500 shrink-0">Payment plan</span>
                        <span class="font-medium text-gray-800 text-right">{{ $planLabel }}</span>
                    </div>
                    <div class="flex justify-between gap-3 text-xs">
                        <span class="text-gray-500 shrink-0">This checkout</span>
                        <span class="font-medium text-gray-800 text-right">{{ $checkoutScopeLabel }}</span>
                    </div>
                </div>

                <div class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-600">Tuition (this payment)</span>
                        <span class="font-semibold text-gray-900 tabular-nums">₱{{ number_format($payTuitionPesos) }}</span>
                    </div>
                    @if($payFeePesos > 0)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-600">Convenience fee</span>
                            <span class="font-semibold text-gray-900 tabular-nums">₱{{ number_format($payFeePesos) }}</span>
                        </div>
                    @endif
                    <div class="border-t border-gray-100 pt-3 flex justify-between items-baseline gap-3">
                        <span class="font-bold text-gray-900">Total to pay</span>
                        <span class="font-extrabold text-brand-700 text-xl tabular-nums">₱{{ number_format($payTotalPesos) }}</span>
                    </div>
                </div>

                <div class="mt-4 p-3.5 bg-slate-50 rounded-xl border border-slate-100 text-xs text-gray-500 leading-relaxed">
                    After you submit proof, staff will verify it and mark this payment as received.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('portals')
    {{-- Must be outside <main class="page-fade-in">: that animation leaves transform on main, which breaks position:fixed for descendants. --}}
    <div id="bank-qr-modal"
         class="fixed inset-0 z-[100] hidden overflow-hidden"
         aria-hidden="true"
         role="dialog"
         aria-modal="true"
         aria-labelledby="bank-qr-modal-title">
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-[1px]" data-bank-qr-modal-backdrop></div>
        <div class="qr-modal-shell relative z-10 h-full max-h-full overflow-hidden">
            <div class="qr-modal-panel pointer-events-auto w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5">
                <div class="qr-modal-header flex shrink-0 items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                    <h3 id="bank-qr-modal-title" class="text-base font-bold text-gray-900">Scan QR code</h3>
                    <button type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-200"
                            data-bank-qr-modal-close
                            aria-label="Close QR dialog">
                        <span class="text-2xl leading-none" aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="qr-modal-body">
                    <div id="modal-bank-qr-wrap"></div>
                </div>
            </div>
        </div>
    </div>
@endpush

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
            const modalQrWrap = document.getElementById('modal-bank-qr-wrap');
            const qrModal = document.getElementById('bank-qr-modal');
            const qrModalShell = qrModal ? qrModal.querySelector('.qr-modal-shell') : null;
            const openQrModalBtn = document.getElementById('open-bank-qr-modal');
            let qrModalViewportListenersBound = false;

            function readVisualViewportHeight() {
                if (window.visualViewport && window.visualViewport.height > 0) {
                    return window.visualViewport.height;
                }

                return window.innerHeight;
            }

            /**
             * Size the overlay to the visual viewport (DevTools, mobile chrome).
             * Do not use offsetTop/offsetLeft for position:fixed — those are layout-document offsets
             * and break after page scroll (modal shifts off-screen).
             */
            function syncQrModalVisualViewportBox() {
                if (!qrModal || qrModal.classList.contains('hidden')) {
                    return;
                }

                const vv = window.visualViewport;
                if (!vv) {
                    qrModal.style.removeProperty('top');
                    qrModal.style.removeProperty('left');
                    qrModal.style.removeProperty('right');
                    qrModal.style.removeProperty('bottom');
                    qrModal.style.removeProperty('width');
                    qrModal.style.removeProperty('height');
                    qrModal.style.removeProperty('transform');

                    return;
                }

                qrModal.style.top = '0px';
                qrModal.style.left = '0px';
                qrModal.style.width = vv.width + 'px';
                qrModal.style.height = vv.height + 'px';
                qrModal.style.right = 'auto';
                qrModal.style.bottom = 'auto';
                qrModal.style.transform = '';
            }

            function syncQrModalMaxHeightPx() {
                if (!qrModal || qrModal.classList.contains('hidden')) {
                    return;
                }

                const vvH = readVisualViewportHeight();
                let verticalSlack = 24;

                if (qrModalShell) {
                    const cs = window.getComputedStyle(qrModalShell);
                    const pt = parseFloat(cs.paddingTop) || 0;
                    const pb = parseFloat(cs.paddingBottom) || 0;
                    verticalSlack = Math.ceil(pt + pb + 16);
                }

                const capPx = Math.max(220, Math.floor(vvH - verticalSlack));
                qrModal.style.setProperty('--qr-modal-max-px', capPx + 'px');
            }

            function syncQrModalLayoutToViewport() {
                syncQrModalVisualViewportBox();
                syncQrModalMaxHeightPx();
            }

            function onQrModalViewportChange() {
                syncQrModalLayoutToViewport();
            }

            function clearQrModalViewportInlineStyles() {
                if (!qrModal) {
                    return;
                }

                ['top', 'left', 'right', 'bottom', 'width', 'height', 'transform'].forEach(function (prop) {
                    qrModal.style.removeProperty(prop);
                });
                qrModal.style.removeProperty('--qr-modal-max-px');
            }

            function bindQrModalViewportListeners() {
                if (qrModalViewportListenersBound) {
                    return;
                }

                qrModalViewportListenersBound = true;
                window.addEventListener('resize', onQrModalViewportChange);

                if (window.visualViewport) {
                    window.visualViewport.addEventListener('resize', onQrModalViewportChange);
                    window.visualViewport.addEventListener('scroll', onQrModalViewportChange);
                }
            }

            function unbindQrModalViewportListeners() {
                if (!qrModalViewportListenersBound) {
                    return;
                }

                qrModalViewportListenersBound = false;
                window.removeEventListener('resize', onQrModalViewportChange);

                if (window.visualViewport) {
                    window.visualViewport.removeEventListener('resize', onQrModalViewportChange);
                    window.visualViewport.removeEventListener('scroll', onQrModalViewportChange);
                }
            }

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

            /**
             * Scale the poster/QR image to fit the modal body (object-contain, bounded box).
             * No scrolling — the whole image stays visible within the viewport.
             */
            function setQrWrap(wrap, url) {
                if (!wrap) {
                    return;
                }

                while (wrap.firstChild) {
                    wrap.removeChild(wrap.firstChild);
                }

                if (!url) {
                    const span = document.createElement('span');
                    span.className = 'text-[10px] font-bold text-gray-400 text-center px-2';
                    span.textContent = 'QR';
                    wrap.appendChild(span);
                    return;
                }

                const img = document.createElement('img');
                img.src = url;
                img.alt = 'Bank QR code';
                img.decoding = 'async';
                img.loading = 'eager';
                img.addEventListener('load', function () {
                    if (qrModal && !qrModal.classList.contains('hidden')) {
                        syncQrModalLayoutToViewport();
                    }
                });

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

                setQrWrap(modalQrWrap, checked.getAttribute('data-qr-url') || '');

                if (openQrModalBtn) {
                    const hasQr = Boolean(checked.getAttribute('data-qr-url'));
                    openQrModalBtn.disabled = !hasQr;
                    openQrModalBtn.classList.toggle('hidden', !hasQr);
                }
            }

            function openQrModal() {
                if (!qrModal || !openQrModalBtn || openQrModalBtn.disabled) {
                    return;
                }

                bindQrModalViewportListeners();
                qrModal.classList.remove('hidden');
                qrModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                syncQrModalLayoutToViewport();
                window.requestAnimationFrame(function () {
                    syncQrModalLayoutToViewport();
                    window.requestAnimationFrame(syncQrModalLayoutToViewport);
                });
            }

            function closeQrModal() {
                if (!qrModal) {
                    return;
                }

                qrModal.classList.add('hidden');
                qrModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                clearQrModalViewportInlineStyles();
                unbindQrModalViewportListeners();
            }

            if (openQrModalBtn) {
                openQrModalBtn.addEventListener('click', function () {
                    openQrModal();
                });
            }

            if (qrModal) {
                qrModal.querySelectorAll('[data-bank-qr-modal-close], [data-bank-qr-modal-backdrop]').forEach(function (el) {
                    el.addEventListener('click', function () {
                        closeQrModal();
                    });
                });
            }

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape' || !qrModal || qrModal.classList.contains('hidden')) {
                    return;
                }

                closeQrModal();
            });

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
                    closeQrModal();
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

