<div class="space-y-3">
    @if(! empty($referenceNumber))
        <div class="text-xs text-gray-500">
            Reference: <span class="font-mono font-semibold text-gray-800">{{ $referenceNumber }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4">
        <div>
            <div class="text-xs font-semibold text-gray-600 mb-2">Photo 1</div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 overflow-hidden">
                <div class="flex items-center justify-center p-2">
                    <img
                        src="{{ $proofUrl1 }}"
                        alt="Proof of payment photo 1"
                        class="max-w-full"
                        style="max-height: 70vh;"
                    />
                </div>
            </div>
        </div>

        @if(! empty($hasPhoto2))
            <div>
                <div class="text-xs font-semibold text-gray-600 mb-2">Photo 2</div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 overflow-hidden">
                    <div class="flex items-center justify-center p-2">
                        <img
                            src="{{ $proofUrl2 }}"
                            alt="Proof of payment photo 2"
                            class="max-w-full"
                            style="max-height: 70vh;"
                        />
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

