<div class="space-y-3">
    @if(! empty($channelName))
        <div class="text-sm font-semibold text-gray-900">{{ $channelName }}</div>
    @endif

    @if(! empty($accountName) || ! empty($accountNumber))
        <div class="text-xs text-gray-500">
            {{ trim(($accountName ?? '').' · '.($accountNumber ?? ''), ' ·') }}
        </div>
    @endif

    <div class="rounded-lg border border-gray-200 bg-gray-50 overflow-hidden">
        <div class="flex items-center justify-center p-4">
            <img
                src="{{ $qrUrl }}"
                alt="QR code for {{ $channelName ?? 'payment channel' }}"
                class="max-w-full"
                style="max-height: 70vh; object-fit: contain;"
            />
        </div>
    </div>
</div>
