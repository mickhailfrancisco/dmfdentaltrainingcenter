<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

    /* Match resources/views/layouts/enrollment.blade.php header brand (1:1) */
    .fi-topbar {
        background-color: #ffffff !important;
        border-bottom: 1px solid #f3f4f6 !important;
        box-shadow: 0 2px 16px 0 rgba(43, 57, 103, 0.08) !important;
        width: 100% !important;
        z-index: 50 !important;
    }

    .fi-topbar>nav {
        background: transparent !important;
        box-shadow: none !important;
        border: none !important;
        width: 100% !important;
    }

    /* Filament hides logo wrapper below lg; keep visible (same block as landing on mobile) */
    .fi-topbar nav>div.me-6.hidden {
        display: flex !important;
        align-items: center;
        flex-shrink: 0;
    }

    /* = landing: flex items-center gap-2.5 */
    .brand-container {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    }

    /* = landing: w-12 h-12 object-contain rounded + drop-shadow-sm */
    .brand-logo {
        width: 3rem;
        height: 3rem;
        object-fit: contain;
        border-radius: 0.25rem;
        filter: drop-shadow(0 1px 1px rgb(0 0 0 / 0.05));
    }

    /* = landing: font-extrabold text-brand-900 text-lg leading-tight tracking-tight relative top-0.5 */
    .brand-title-wrapper {
        font-weight: 800;
        color: #263255;
        font-size: 1.125rem;
        line-height: 1.25;
        letter-spacing: -0.025em;
        position: relative;
        top: 0.125rem;
    }

    /* = landing: block text-[10px] font-semibold text-accent-600 tracking-widest uppercase mt-0.5 */
    .brand-tagline {
        display: block;
        font-size: 10px;
        font-weight: 600;
        color: #f4940c;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        margin-top: 0.125rem;
    }

    /* Login: same visual scale as public header (1:1 with landing) */
    main.fi-simple-main .brand-logo {
        width: 3rem !important;
        height: 3rem !important;
    }

    main.fi-simple-main .brand-title-wrapper {
        font-size: 1.125rem !important;
        line-height: 1.25 !important;
        top: 0.125rem !important;
    }

    main.fi-simple-main .brand-tagline {
        font-size: 10px !important;
        margin-top: 0.125rem !important;
    }

    main.fi-simple-main form button[type="submit"] {
        background: linear-gradient(135deg, #f4940c 0%, #e08300 100%) !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        letter-spacing: 0.05em !important;
        text-transform: uppercase !important;
        border-radius: 0.5rem !important;
        border: none !important;
        box-shadow: 0 4px 14px rgba(244, 148, 12, 0.35) !important;
        transition: all 0.3s ease !important;
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
        position: relative;
        overflow: hidden;
    }

    main.fi-simple-main form button[type="submit"]:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(244, 148, 12, 0.45) !important;
        background: linear-gradient(135deg, #fba11b 0%, #f08d00 100%) !important;
    }

    main.fi-simple-main form button[type="submit"]::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.2) 50%, rgba(255, 255, 255, 0) 100%);
        transition: all 0.5s ease;
    }

    main.fi-simple-main form button[type="submit"]:hover::after {
        left: 100%;
    }
</style>

<div class="brand-container">
    <img src="{{ asset('images/logo.png') }}" alt="DMF Logo" class="brand-logo">
    <span class="brand-title-wrapper">
        Dental Training Center
        <span class="brand-tagline">Your Pathway to Dental Excellence</span>
    </span>
</div>
