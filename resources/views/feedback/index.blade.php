@extends('layouts.enrollment')

@section('title', 'Student Feedback — DMF Dental Training Center')

@section('content')
<section class="bg-gray-50 py-16 md:py-20">
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
                @foreach($images as $image)
                    <a
                        href="{{ $landingMediaService->url($image->image_path) }}"
                        target="_blank"
                        rel="noopener"
                        class="block w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft"
                    >
                        <div class="aspect-[4/5] overflow-hidden bg-brand-50">
                            <img
                                src="{{ $landingMediaService->url($image->image_path) }}"
                                alt="Student feedback screenshot"
                                class="h-full w-full object-cover object-top"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $images->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
