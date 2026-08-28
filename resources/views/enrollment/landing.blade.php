@extends('layouts.enrollment')

@section('title', 'DMF Dental Training Center — Dentistry Board Exam Review Program')
@section('meta_description', 'Enroll in our Dentistry Board Exam Review Program. High passing rates, expert faculty, and flexible online & face-to-face schedules.')

@section('head')
    <script>document.documentElement.classList.add('land-scroll-anim')</script>
@endsection

@section('content')

{{-- ════════════════════════════════════════
    HERO SECTION
════════════════════════════════════════ --}}
<section class="hero-gradient relative overflow-hidden">

    {{-- Decorative blobs --}}
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-brand-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-16 -left-16 w-72 h-72 bg-accent-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute inset-0 opacity-[0.06] bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:18px_18px] pointer-events-none"></div>

    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28 lg:py-36">
        <div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-16">

            {{-- Left: Copy --}}
            <div class="flex-1 text-center lg:text-left">

                {{-- Badge --}}
                <span class="land-hero-1 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-800/50 text-accent-300 text-xs font-semibold uppercase tracking-widest mb-6 border border-brand-700 backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-accent-500 animate-pulse"></span>
                    2027 Enrollment Now Open
                </span>

                <h1 class="land-hero-2 text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight tracking-tight mb-6">
                    Your Pathway to
                    <span class="text-accent-400 relative">
                        Dental Excellence
                        <svg class="absolute -bottom-1 left-0 w-full text-accent-600/50" viewBox="0 0 200 8" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                            <path d="M2 6 Q50 2 100 4 Q150 6 198 2" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none" opacity="0.6"/>
                        </svg>
                    </span>
                </h1>

                <p class="land-hero-3 text-lg text-brand-100 font-light leading-relaxed max-w-xl mx-auto lg:mx-0 mb-8 opacity-90">
                    Join thousands of successful dentists who trusted DMF Dental Training Center to guide them to success in the dentistry boards.
                </p>

                {{-- CTAs --}}
                <div class="land-hero-4 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                    <a href="{{ url('/enroll') }}"
                       id="hero-enroll-btn"
                       class="inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-accent-500 text-brand-950 font-extrabold rounded-xl shadow-[0_4px_14px_0_rgba(250,178,27,0.39)] hover:bg-accent-400 hover:shadow-[0_6px_20px_rgba(250,178,27,0.23)] active:scale-[0.98] transition-all duration-200 text-base">
                        Enroll Now
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                    <a href="#programs"
                       class="inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-brand-800/40 text-white font-semibold rounded-xl border border-brand-700 backdrop-blur-sm hover:bg-brand-700 transition-all duration-200 text-base">
                        View Programs
                    </a>
                </div>

            </div>

            {{-- Right: Highlights card --}}
            <div class="flex-shrink-0 w-full max-w-sm lg:max-w-xs">
                <div class="land-hero-6 bg-white rounded-2xl shadow-card border border-gray-100 p-6 space-y-5">
                    <h3 class="text-sm font-bold text-accent-600 uppercase tracking-widest">Why DMF Dental?</h3>

                    {{-- Excellent board performance --}}
                    <div class="flex items-start gap-4">
                        <span class="w-10 h-10 rounded-2xl bg-accent-50 text-accent-700 flex items-center justify-center shadow-sm flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v3a5 5 0 0 1-10 0V4Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 7a3 3 0 0 0 3 3M19 7a3 3 0 0 1-3 3"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-extrabold text-brand-700 leading-snug">Excellent board performance</p>
                            <ul class="mt-1.5 space-y-1 text-xs text-gray-500" role="list">
                                <li class="flex items-center gap-1.5">
                                    <span class="w-1 h-1 rounded-full bg-accent-500 flex-shrink-0" aria-hidden="true"></span>
                                    High passing rate
                                </li>
                                <li class="flex items-center gap-1.5">
                                    <span class="w-1 h-1 rounded-full bg-accent-500 flex-shrink-0" aria-hidden="true"></span>
                                    Multiple topnotchers
                                </li>
                            </ul>
                        </div>
                    </div>

                    {{-- 10 years of excellence --}}
                    <div class="flex items-center gap-4">
                        <span class="w-10 h-10 rounded-2xl bg-brand-50 text-brand-700 flex items-center justify-center shadow-sm flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 2v3M16 2v3M3 8h18M5 5h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg>
                        </span>
                        <p class="text-sm font-extrabold text-brand-700 leading-snug">{{ $yearsOfExcellence }} years of excellence</p>
                    </div>

                    {{-- Topnotch lecturers --}}
                    <div class="flex items-center gap-4">
                        <span class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center shadow-sm flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 21a8 8 0 0 1 16 0"/></svg>
                        </span>
                        <p class="text-sm font-extrabold text-brand-700 leading-snug">Topnotch lecturers</p>
                    </div>

                    {{-- Highly recommended --}}
                    <div class="flex items-center gap-4">
                        <span class="w-10 h-10 rounded-2xl bg-brand-50 text-brand-700 flex items-center justify-center shadow-sm flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                        </span>
                        <p class="text-sm font-extrabold text-brand-700 leading-snug">Highly recommended by previous board takers</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>





{{-- ════════════════════════════════════════
    FEATURED PROGRAMS SECTION
════════════════════════════════════════ --}}
<section id="programs" class="scroll-mt-20 py-16 md:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Program descriptions (separate section) --}}
        <div class="mb-12">
            <div class="land-reveal text-center mb-6">
                <span class="text-sm font-semibold uppercase tracking-widest text-brand-600">Programs Offered</span>
                <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-2">What these programs cover</h2>
            </div>

            <div class="land-stagger grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-4 gap-6 md:gap-7 2xl:gap-8 items-stretch">
                <div class="flex h-full flex-col rounded-2xl border border-gray-100 shadow-soft bg-white p-6 sm:p-7 2xl:p-8">
                    <div class="w-12 h-12 rounded-2xl bg-brand-50 text-brand-700 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3v1.5a3 3 0 0 1-3 3h-7.5a3 3 0 0 1-3-3v-1.5a3 3 0 0 1 3-3h7.5Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 13.5v3a3 3 0 0 0 3 3h3a3 3 0 0 0 3-3v-3" />
                        </svg>
                    </div>
                    <h3 class="text-base font-extrabold text-gray-900 mb-2 text-balance">Hybrid Face-to-Face Intensive Lecture Review</h3>
                    <ul class="space-y-2.5 2xl:space-y-3 text-sm 2xl:text-[0.9375rem] text-gray-600 leading-relaxed 2xl:leading-7" role="list">
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>The majority of classes are face to face; a few sessions are held online, depending on lecturer availability.</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>Handouts are given at the venue.</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>Whole-day lectures, with a short quiz at the end of each session.</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span><span class="font-semibold text-gray-800">Free: </span>Online pre-board exam (3 days)</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span><span class="font-semibold text-gray-800">Free: </span>DMF shirt</span>
                        </li>
                    </ul>
                </div>

                <div class="flex h-full flex-col rounded-2xl border border-gray-100 shadow-soft bg-white p-6 sm:p-7 2xl:p-8">
                    <div class="w-12 h-12 rounded-2xl bg-accent-50 text-accent-700 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h7.5M8.25 10.5h7.5M8.25 14.25h4.5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v12a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 18V6a2.25 2.25 0 0 1 2.25-2.25Z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-extrabold text-gray-900 mb-2 text-balance">Online Comprehensive Lecture Review</h3>
                    <ul class="space-y-2.5 2xl:space-y-3 text-sm 2xl:text-[0.9375rem] text-gray-600 leading-relaxed 2xl:leading-7" role="list">
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-accent-50 text-accent-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>Detailed discussion of all board exam subjects</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-accent-50 text-accent-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>A slower, thorough pace with up to 4 hours per session, so you have time to absorb the material</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-accent-50 text-accent-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>Review book shipped to your address</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-accent-50 text-accent-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span><span class="font-semibold text-gray-800">Free: </span>Online pre-board exam (3 days)</span>
                        </li>
                    </ul>
                </div>

                <div class="flex h-full flex-col rounded-2xl border border-gray-100 shadow-soft bg-white p-6 sm:p-7 2xl:p-8">
                    <div class="w-12 h-12 rounded-2xl bg-violet-50 text-violet-700 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-extrabold text-gray-900 mb-2 text-balance">Online Final Coaching</h3>
                    <ul class="space-y-2.5 2xl:space-y-3 text-sm 2xl:text-[0.9375rem] text-gray-600 leading-relaxed 2xl:leading-7" role="list">
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>Purely Q&amp;A with rationalization, including new question sets and past board exam questions (BEQs)</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>Access to video recordings of sessions if you are unable to join the live discussion</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>Test-taking and exam-answering strategies to support the way you work through questions</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span><span class="font-semibold text-gray-800">Free: </span>Online pre-board examination</span>
                        </li>
                    </ul>
                </div>

                <div class="flex h-full flex-col rounded-2xl border border-gray-100 shadow-soft bg-white p-6 sm:p-7 2xl:p-8">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21V7.5a2.25 2.25 0 0 1 2.25-2.25h4.5A2.25 2.25 0 0 1 16.5 7.5V21" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 21h12" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 10.5h6M9 13.5h6M9 16.5h6" />
                        </svg>
                    </div>
                    <h3 class="text-base font-extrabold text-gray-900 mb-2 text-balance">Full Course Face-to-Face Practical Review</h3>
                    <ul class="space-y-2.5 2xl:space-y-3 text-sm 2xl:text-[0.9375rem] text-gray-600 leading-relaxed 2xl:leading-7" role="list">
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>2 days of detailed online discussion</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>13 days of whole-day, hands-on training with topnotch lecturers</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span>2 whole days of practical pre-board exam</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600" aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                            <span><span class="font-semibold text-gray-800">Included: </span>DMF shirt and CD kit</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Section header --}}
        <div class="land-reveal text-center mb-12 border-t border-gray-100 pt-12">
            <span class="text-sm font-semibold uppercase tracking-widest text-brand-600">Highly Recommended</span>
            <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-2">Featured Review Packages</h2>
            <p class="text-base text-gray-500 max-w-xl mx-auto mt-2">Our most popular bundles offering complete preparation.</p>
        </div>

        <div class="land-stagger grid grid-cols-1 md:grid-cols-3 gap-6">
            @php
                // Display only the first 3 packages from the main category
                $topPackages = ($packages ?? collect())->take(3);
            @endphp
            
            @foreach($topPackages as $package)
            @php
                $isEarlyBirdActive = $package->isEarlyBirdActive();
            @endphp
            <div class="relative program-card rounded-2xl border border-gray-100 shadow-soft bg-white p-6 flex flex-col h-full hover:shadow-lg transition-all duration-300 hover:-translate-y-1 group">
                <div class="mb-5">
                    @if($package->tag)
                        <span class="inline-block px-3 py-1 bg-brand-50 text-brand-700 text-[11px] font-bold rounded-full mb-3 uppercase tracking-wider">{{ $package->tag }}</span>
                    @endif
                    <h4 class="text-xl font-extrabold text-gray-900 leading-tight mb-5">{{ $package->name }}</h4>
                    
                    <div class="p-4 bg-gray-50 rounded-xl mb-4 group-hover:bg-brand-50 transition-colors duration-300">
                        <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1 font-semibold">Full Price</p>
                        @if($isEarlyBirdActive)
                            <p class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                <span class="line-through text-gray-400 font-normal text-xs">₱{{ number_format($package->price_full) }}</span>
                                <span class="text-2xl text-accent-600 font-extrabold">₱{{ number_format($package->price_early) }}</span>
                            </p>
                            <span class="text-[10px] text-white font-bold bg-accent-500 px-1.5 py-0.5 rounded shadow-sm inline-block mt-2 uppercase tracking-wide">Early Bird Active!</span>
                        @else
                            <p class="text-2xl font-extrabold text-gray-900">₱{{ number_format($package->price_full) }}</p>
                        @endif
                    </div>
                    
                    <div class="flex items-center justify-between text-sm px-1">
                        <span class="text-gray-500 font-medium">Downpayment</span>
                        <span class="font-bold text-gray-900">₱{{ number_format($package->downpayment_amount) }}</span>
                    </div>
                </div>

                <ul class="space-y-3.5 flex-1 mb-8 mt-4 text-sm text-gray-600 border-t border-gray-100 pt-5 pr-2">
                    @foreach($package->programs as $incProgram)
                    <li class="flex items-start gap-3">
                        <span class="flex-shrink-0 w-5 h-5 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mt-0.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <span class="leading-snug font-medium">{{ $incProgram->name }}</span>
                    </li>
                    @endforeach
                </ul>

                <a href="{{ url('/enroll') }}?program={{ $package->slug }}" class="mt-auto block text-center px-6 py-3.5 rounded-xl text-sm font-bold transition-all duration-200 bg-white border-2 border-brand-100 text-brand-700 hover:border-brand-600 hover:bg-brand-600 hover:text-white active:scale-[0.98]">
                    Select Package
                </a>
            </div>
            @endforeach
        </div>

        <div class="land-reveal text-center mt-12 md:mt-16">
            <p class="text-gray-500 mb-5 text-sm md:text-base">Looking for individual subjects, practicals, or online-only courses?</p>
            <a href="{{ url('/enroll') }}" class="inline-flex items-center gap-2 px-8 py-4 bg-brand-950 text-white font-bold rounded-xl shadow-md hover:bg-brand-800 hover:shadow-lg transition-all text-sm md:text-base group">
                View All Programs & Enroll
                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>

    </div>
</section>

{{-- ════════════════════════════════════════
    WHY CHOOSE US
════════════════════════════════════════ --}}
<section class="py-20 md:py-28 bg-white" id="about">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="land-reveal text-center max-w-3xl mx-auto mb-16">
            <span class="text-sm font-semibold uppercase tracking-widest text-accent-600">The DMF Advantage</span>
            <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-2 mb-3">Why Choose DMF Dental?</h2>
            <p class="text-base text-gray-500 leading-relaxed">We provide the most comprehensive, intensive, and results-driven training programs in the country to ensure your success.</p>
        </div>
        
        <div class="land-stagger grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-7 items-stretch">
            <!-- Feature 1 -->
            <div class="flex h-full flex-col items-center text-center rounded-2xl border border-gray-100 bg-gray-50/60 p-6 sm:p-7 shadow-soft group">
                <div class="w-16 h-16 bg-brand-50 text-brand-600 rounded-2xl flex items-center justify-center mb-5 group-hover:bg-brand-600 group-hover:text-white transition-colors duration-300 shadow-sm">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h4 class="text-xl font-bold text-gray-900 mb-3">Expert Lecturers</h4>
                <p class="text-base text-gray-500 leading-relaxed flex-1">Learn directly from board topnotchers and seasoned professionals with years of active practice and unparalleled teaching experience.</p>
            </div>
            <!-- Feature 2 -->
            <div class="flex h-full flex-col items-center text-center rounded-2xl border border-gray-100 bg-gray-50/60 p-6 sm:p-7 shadow-soft group">
                <div class="w-16 h-16 bg-accent-50 text-accent-600 rounded-2xl flex items-center justify-center mb-5 group-hover:bg-accent-600 group-hover:text-white transition-colors duration-300 shadow-sm">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <h4 class="text-xl font-bold text-gray-900 mb-3">Learning Flexibility</h4>
                <p class="text-base text-gray-500 leading-relaxed flex-1">Multiple programs to choose from based on your preferred schedule and learning style.</p>
            </div>
            <!-- Feature 3 -->
            <div class="flex h-full flex-col items-center text-center rounded-2xl border border-gray-100 bg-gray-50/60 p-6 sm:p-7 shadow-soft group">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mb-5 group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300 shadow-sm">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                </div>
                <h4 class="text-xl font-bold text-gray-900 mb-3">Highest Passing Rate</h4>
                <p class="text-base text-gray-500 leading-relaxed flex-1">We produce topnotchers and a lot of successful examinees every board exam — a proof that DMF’s theoretical classes and practical drills provide a strong foundation.</p>
            </div>
        </div>
    </div>
</section>


{{-- ════════════════════════════════════════
    FEEDBACK GALLERY (Facebook screenshots)
════════════════════════════════════════ --}}
@php
    $feedbackTotal = count($feedbackImages ?? []);
@endphp
<section
    class="bg-gray-50 py-16 md:py-20"
    id="stories"
    x-data="{
        images: @js(array_values($feedbackImages ?? [])),
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
        <div class="land-reveal text-center mb-10 md:mb-12">
            <span class="text-sm font-semibold uppercase tracking-widest text-brand-600">Success Stories</span>
            <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-2">What Our Graduates Say</h2>
            <p class="text-base text-gray-500 mt-3 max-w-2xl mx-auto">Real Facebook feedback from students and board passers. Tap a card to read it clearly.</p>
        </div>

        @if($feedbackTotal > 0)
            <div class="land-stagger grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
                @foreach($feedbackImages as $index => $imageUrl)
                    <button
                        type="button"
                        class="feedback-gallery-item group relative block w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                        @click="openLightbox({{ $index }})"
                        aria-label="View feedback screenshot {{ $index + 1 }}"
                    >
                        <div class="aspect-[4/5] overflow-hidden bg-brand-50">
                            <img
                                src="{{ $imageUrl }}"
                                alt="Student feedback screenshot {{ $index + 1 }}"
                                class="h-full w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.03]"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-950/70 via-brand-950/20 to-transparent px-4 pb-3 pt-10">
                            <span class="text-xs font-semibold text-white/95">Tap to enlarge</span>
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="mt-8 flex justify-center">
                <a
                    href="{{ route('feedback') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-brand-950 text-white text-sm font-bold shadow-md hover:bg-brand-800 transition-colors"
                >
                    See more feedback
                </a>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-12 text-center">
                <p class="text-sm text-gray-500">Feedback screenshots will appear here once uploaded from the admin panel.</p>
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


{{-- ════════════════════════════════════════
    GALLERY SECTION
════════════════════════════════════════ --}}
@php
    $galleryTotal = count($galleryImages ?? []);
@endphp
<section
    class="bg-white py-16 md:py-20"
    id="gallery"
    x-data="{
        images: @js(array_values($galleryImages ?? [])),
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
        <div class="land-reveal text-center mb-10 md:mb-12">
            <span class="text-sm font-semibold uppercase tracking-widest text-brand-600">Gallery</span>
            <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-gray-900 mt-2">Inside DMF Dental Review Center</h2>
            <p class="text-base text-gray-500 mt-3 max-w-2xl mx-auto">A look at our facilities, training sessions, and student life.</p>
        </div>

        @if($galleryTotal > 0)
            <div class="land-stagger grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
                @foreach($galleryImages as $index => $imageUrl)
                    <button
                        type="button"
                        class="group relative block w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-soft text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                        @click="openLightbox({{ $index }})"
                        aria-label="View gallery photo {{ $index + 1 }}"
                    >
                        <div class="aspect-[4/5] overflow-hidden bg-brand-50">
                            <img
                                src="{{ $imageUrl }}"
                                alt="Gallery photo {{ $index + 1 }}"
                                class="h-full w-full object-cover object-top transition-transform duration-300 group-hover:scale-[1.03]"
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="mt-8 flex justify-center">
                <a
                    href="{{ route('gallery') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-brand-950 text-white text-sm font-bold shadow-md hover:bg-brand-800 transition-colors"
                >
                    View full gallery
                </a>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-12 text-center">
                <p class="text-sm text-gray-500">Gallery photos will appear here once uploaded from the admin panel.</p>
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
            aria-label="Gallery photo"
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
                        aria-label="Previous photo"
                        x-show="images.length > 1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <img
                        :src="activeSrc()"
                        alt="Enlarged gallery photo"
                        class="max-h-[80vh] w-auto max-w-[min(100%,56rem)] rounded-xl shadow-card object-contain bg-white"
                    >

                    <button
                        type="button"
                        class="absolute right-0 sm:-right-3 z-20 w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-white text-brand-900 shadow-md flex items-center justify-center hover:bg-accent-500 transition-colors"
                        @click="next()"
                        aria-label="Next photo"
                        x-show="images.length > 1"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </template>
</section>


{{-- ════════════════════════════════════════
    FINAL CTA SECTION
════════════════════════════════════════ --}}
<section class="bg-white py-16 md:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="land-reveal bg-gradient-to-br from-brand-800 to-brand-950 rounded-3xl p-10 md:p-14 shadow-card relative overflow-hidden border border-brand-700/50">
            {{-- Decorative circles --}}
            <div class="absolute top-0 right-0 w-64 h-64 bg-accent-500/10 rounded-full -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/3 pointer-events-none"></div>

            <span class="text-accent-400 text-sm font-semibold uppercase tracking-widest block mb-3">Ready to Start?</span>
            <h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">Your Board Exam Success Starts Here</h2>
            <p class="text-brand-100/80 mb-8 max-w-lg mx-auto">Seats are limited. Secure your spot today and take the first step toward becoming a licensed dentist.</p>

            <a href="{{ url('/enroll') }}"
               id="cta-enroll-btn"
               class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-accent-500 text-brand-950 font-extrabold rounded-xl shadow-[0_4px_14px_0_rgba(250,178,27,0.39)] hover:bg-accent-400 hover:shadow-[0_6px_20px_rgba(250,178,27,0.23)] active:scale-[0.98] transition-all duration-200 text-base">
                Enroll Now
                <svg class="w-4 h-4 text-brand-900" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        </div>
    </div>
</section>

@endsection
