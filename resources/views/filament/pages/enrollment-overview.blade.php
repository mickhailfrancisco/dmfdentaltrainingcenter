<x-filament-panels::page>
    @php
        $counts = $this->getStatCounts();
        $cards = [
            [
                'label' => 'Awaiting payment',
                'count' => $counts['awaiting_payment'],
                'tab' => 'awaiting_payment',
                'description' => 'Students who have not completed initial checkout.',
                'color' => 'warning',
            ],
            [
                'label' => 'Pending verification',
                'count' => $counts['pending_verification'],
                'tab' => 'pending_verification',
                'description' => 'Bank transfers submitted and waiting for staff review.',
                'color' => 'info',
            ],
            [
                'label' => 'Balance due',
                'count' => $counts['balance_due'],
                'tab' => 'balance_due',
                'description' => 'Downpayment enrollments with remaining tuition.',
                'color' => 'danger',
            ],
        ];
    @endphp

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($cards as $card)
            <a
                href="{{ $this->getFilteredListUrl($card['tab']) }}"
                class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $card['label'] }}
                </div>
                <div @class([
                    'mt-2 text-3xl font-bold',
                    'text-warning-600' => $card['color'] === 'warning',
                    'text-info-600' => $card['color'] === 'info',
                    'text-danger-600' => $card['color'] === 'danger',
                ])>
                    {{ number_format($card['count']) }}
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ $card['description'] }}
                </p>
                <p class="mt-3 text-sm font-semibold text-primary-600">
                    View enrollments →
                </p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
            Daily workflow
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            Start here for a quick count, then jump into the enrollment list with the matching tab pre-selected.
            Use date and package filters on the list page to review historical cohorts.
        </p>
        <div class="mt-4">
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\EnrollmentResource::getUrl('index') }}"
                color="gray"
                outlined
            >
                Open all enrollments
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
