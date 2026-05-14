<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'DMF Dental Training Center')</title>
    <meta name="description" content="@yield('meta_description', 'Your Pathway to Dental Excellence.')">
    
    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    {{-- Tailwind CSS via CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f4f6fc',
                            100: '#e5ebf8',
                            200: '#c6d4ee',
                            300: '#9db4e1',
                            400: '#6f8ed0',
                            500: '#4c6ebb',
                            600: '#3c559c',
                            700: '#32457e',
                            800: '#2b3967',
                            900: '#263255',
                            950: '#151b32',
                        },
                        accent: {
                            50: '#fffcf0',
                            100: '#fff8db',
                            200: '#fff0b5',
                            300: '#fee285',
                            400: '#fccc4c',
                            500: '#fab21b', /* Gold */
                            600: '#f4940c',
                            700: '#cc7000',
                            800: '#a35706',
                            900: '#85470b',
                            950: '#4d2500',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    borderRadius: {
                        'xl': '0.875rem',
                        '2xl': '1.25rem',
                    },
                    boxShadow: {
                        'soft': '0 2px 16px 0 rgba(43,57,103,0.08)',
                        'card': '0 4px 32px 0 rgba(0,0,0,0.07)',
                    },
                },
            },
        }
    </script>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Enrollment stylesheet --}}
    <link rel="stylesheet" href="{{ asset('css/enrollment.css') }}">

    @yield('head')
</head>
<body class="bg-slate-50 text-gray-800 antialiased min-h-screen flex flex-col">

    {{-- ── Top Navigation Bar ── --}}
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-sm border-b border-gray-100 shadow-soft">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">

            {{-- Logo --}}
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 group">
                <img src="{{ asset('images/logo.png') }}" alt="DMF Logo" class="w-12 h-12 object-contain drop-shadow-sm transition-transform duration-200 group-hover:scale-105 rounded">
                <span class="font-extrabold text-brand-900 text-lg leading-tight tracking-tight relative top-0.5">
                    Dental Training Center
                    <span class="block text-[10px] font-semibold text-accent-600 tracking-widest uppercase mt-0.5">Your Pathway to Dental Excellence</span>
                </span>
            </a>

            {{-- Nav links (desktop) --}}
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium" id="desktop-nav">
                <a href="{{ url('/#hero') }}"       
                   class="nav-link transition-colors duration-150 text-gray-500 hover:text-brand-600">
                   Home
                </a>
                <a href="{{ url('/#programs') }}" 
                   class="nav-link transition-colors duration-150 text-gray-500 hover:text-brand-600">
                   Programs
                </a>
                <a href="{{ url('/#about') }}"    
                   class="nav-link transition-colors duration-150 text-gray-500 hover:text-brand-600">
                   Why DMF
                </a>
                <a href="{{ url('/#stories') }}"    
                   class="nav-link transition-colors duration-150 text-gray-500 hover:text-brand-600">
                   Stories
                </a>
                <a href="{{ url('/enroll') }}" 
                   class="ml-2 px-4 py-2 rounded-xl text-sm font-bold transition-colors duration-200 shadow-sm {{ request()->is('enroll*') ? 'bg-accent-500 text-brand-950 hover:bg-accent-400' : 'bg-brand-600 text-white hover:bg-brand-700' }}">
                    Enroll Now
                </a>
            </nav>

            {{-- Mobile: enroll button only --}}
            <a href="{{ url('/enroll') }}" class="md:hidden px-4 py-2 rounded-xl text-sm font-bold transition-colors duration-200 {{ request()->is('enroll*') ? 'bg-accent-500 text-brand-950' : 'bg-brand-600 text-white' }}">
                Enroll
            </a>
        </div>
    </header>

    {{-- ── Main Content ── --}}
    <main class="flex-1 page-fade-in">
        @yield('content')
    </main>

    @stack('portals')

    {{-- ── Footer ── --}}
    <footer class="bg-brand-900 text-brand-100 border-t border-brand-800 mt-auto">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8 border-b border-brand-800 pb-8">
                <div>
                    <span class="font-bold text-lg text-white mb-4 block">DMF Dental Training Center</span>
                    <p class="text-sm text-brand-300 leading-relaxed mb-4">Your Pathway to Dental Excellence.</p>
                </div>
                <div>
                    <span class="font-semibold text-white mb-4 block">Contact Us</span>
                    <p class="text-sm text-brand-300">Room 218-220, P&S Building<br>Aurora Blvd., Brgy. Mariana, Quezon City</p>
                    <p class="text-sm text-brand-300 mt-2">📱 09973580654</p>
                </div>
                <div>
                    <span class="font-semibold text-white mb-4 block">Follow Us</span>
                    <div class="flex flex-col gap-2">
                        <a href="https://www.facebook.com/DMFDentalTrainingCenter/" target="_blank" class="text-sm text-brand-300 hover:text-accent-400 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988 C18.343 21.128 22 16.991 22 12z"/></svg>
                            DMF Dental Training Center
                        </a>
                        <a href="https://www.facebook.com/mickhailfrancisco/" target="_blank" class="text-sm text-brand-300 hover:text-accent-400 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988 C18.343 21.128 22 16.991 22 12z"/></svg>
                            Mickhail Francisco
                        </a>
                    </div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-brand-400">
                <p>© {{ date('Y') }} DMF Dental Training Center. All rights reserved.</p>
                <div class="flex gap-4">
                    <a href="#" class="hover:text-white transition-colors duration-150">Privacy Policy</a>
                    <a href="#" class="hover:text-white transition-colors duration-150">Terms of Use</a>
                </div>
            </div>
        </div>
    </footer>

    @yield('scripts')

    {{-- Enrollment layout JS (scrollspy) --}}
    <script src="{{ asset('js/enrollment.js') }}"></script>
</body>
</html>
