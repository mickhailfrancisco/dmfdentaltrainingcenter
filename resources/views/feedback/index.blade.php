@extends('layouts.enrollment')

@section('title', 'Student Feedback — DMF Dental Training Center')

@section('content')
<section
    class="bg-gray-50 py-16 md:py-20"
    x-data="{
        images: @js($images->map(fn ($image) => $landingMediaService->url($image->image_path))->values()->all()),
        open: false,
        activeIndex: 0,
        openLightbox(index) {
            this.activeIndex = Number(index) || 0;
            this.open = true;
            document.body.classList.add('overflow-hidden');
        },
        closeLightbox() {
            this.open = false;
            document.body.classList.remove('overflow-hidden');
        },
        next() {
            if (this.images.length === 0) { return; }
            this.activeIndex = (this.activeIndex + 1) % this.images.length;
        },
        prev() {
            if (this.images.length === 0) { return; }
            this.activeIndex = (this.activeIndex - 1 + this.images.length) % this.images.length;
        },
        activeSrc() {
            return this.images[this.activeIndex] || '';
        }
    }"
    @keydown.escape.window="if (open) closeLightbox()"
    @keydown.arrow-right.window="if (open) next()"
    @keydown.arrow-left.window="if (open) prev()"
>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10 md:mb-12">
            <a href="{{ route('home') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-800">&larr; Back to home</a>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-4">Student Feedback</h1>
            <p class="text-base text-gray-500 mt-3 max-w-2xl mx-auto">Real Facebook feedback from students and board passers.</p>
        </div>

        @if($images->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-12 text-center">
                <p class="text-sm text-gray-500">No feedback screenshots yet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
                @foreach($images as $index => $image)
                    <button
                        type="button"
                        class="group relative block w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                        @click="openLightbox({{ $index }})"
                        aria-label="View feedback screenshot {{ $index + 1 }}"
                    >
                        <div class="aspect-[4/5] overflow-hidden bg-brand-50">
                            <img
                                src="{{ $landingMediaService->url($image->image_path) }}"
                                alt="Student feedback screenshot"
                                class="h-full w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.03]"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $images->links() }}
            </div>
        @endif
    </div>

    {{-- Lightbox teleported to body so position:fixed isn't trapped by page transform animations --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[200] flex items-center justify-center p-3 sm:p-8"
            role="dialog"
            aria-modal="true"
            aria-label="Feedback screenshot"
        >
            <div class="absolute inset-0 bg-brand-950/85 backdrop-blur-sm" @click="closeLightbox()"></div>

            <div class="relative z-10 flex w-full max-w-4xl flex-col items-center" @click.stop>
                <div class="relative flex w-full items-center justify-center min-h-[40vh]">
                    <p
                        class="absolute top-2 left-2 sm:top-3 sm:left-3 z-20 rounded-full bg-brand-950/70 px-2.5 py-1 text-xs font-semibold text-white"
                        x-text="(activeIndex + 1) + ' / ' + images.length"
                    ></p>

                    <button
                        type="button"
                        class="absolute top-2 right-2 sm:top-3 sm:right-3 z-20 w-10 h-10 rounded-full bg-white text-brand-900 shadow-md flex items-center justify-center hover:bg-accent-500 transition-colors"
                        @click="closeLightbox()"
                        aria-label="Close"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>

                    <button
                        type="button"
                        class="absolute left-0 sm:-left-3 z-20 w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-white text-brand-900 shadow-md flex items-center justify-center hover:bg-accent-500 transition-colors"
                        @click="prev()"
                        aria-label="Previous feedback"
                        x-show="images.length > 1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <img
                        :src="activeSrc()"
                        alt="Enlarged feedback screenshot"
                        class="max-h-[80vh] w-auto max-w-[min(100%,56rem)] rounded-xl shadow-card object-contain bg-white"
                    >

                    <button
                        type="button"
                        class="absolute right-0 sm:-right-3 z-20 w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-white text-brand-900 shadow-md flex items-center justify-center hover:bg-accent-500 transition-colors"
                        @click="next()"
                        aria-label="Next feedback"
                        x-show="images.length > 1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </template>
</section>
@endsection
