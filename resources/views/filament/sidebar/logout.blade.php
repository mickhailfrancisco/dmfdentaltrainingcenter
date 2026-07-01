@php
    $sidebarCollapsible = filament()->isSidebarCollapsibleOnDesktop();
@endphp

<ul class="-mx-2 mt-auto flex flex-col gap-y-1">
    <li class="fi-sidebar-item">
        <a
            href="{{ route('filament.admin.auth.profile') }}"
            @if ($sidebarCollapsible)
                x-data="{ tooltip: false }"
                x-effect="
                    tooltip = $store.sidebar.isOpen
                        ? false
                        : {
                              content: 'Edit profile',
                              placement: document.dir === 'rtl' ? 'left' : 'right',
                              theme: $store.theme,
                          }
                "
                x-tooltip.html="tooltip"
            @endif
            class="fi-sidebar-item-button relative flex w-full items-center justify-center gap-x-3 rounded-lg px-2 py-2 outline-none transition duration-75 hover:bg-gray-100 focus-visible:bg-gray-100 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
        >
            <x-filament::icon
                icon="heroicon-o-user-circle"
                class="fi-sidebar-item-icon h-6 w-6 text-gray-400 dark:text-gray-500"
            />
            <span
                @if ($sidebarCollapsible)
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="lg:transition lg:delay-100"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                @endif
                class="fi-sidebar-item-label flex-1 truncate text-start text-sm font-medium text-gray-700 dark:text-gray-200"
            >
                Edit profile
            </span>
        </a>
    </li>

    <li class="fi-sidebar-item">
        <form action="{{ filament()->getLogoutUrl() }}" method="post">
            @csrf

            <button
                type="submit"
                @if ($sidebarCollapsible)
                    x-data="{ tooltip: false }"
                    x-effect="
                        tooltip = $store.sidebar.isOpen
                            ? false
                            : {
                                  content: 'Sign out',
                                  placement: document.dir === 'rtl' ? 'left' : 'right',
                                  theme: $store.theme,
                              }
                    "
                    x-tooltip.html="tooltip"
                @endif
                class="fi-sidebar-item-button relative flex w-full items-center justify-center gap-x-3 rounded-lg px-2 py-2 outline-none transition duration-75 hover:bg-gray-100 focus-visible:bg-gray-100 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
            >
                <x-filament::icon
                    icon="heroicon-o-arrow-left-on-rectangle"
                    class="fi-sidebar-item-icon h-6 w-6 text-gray-400 dark:text-gray-500"
                />
                <span
                    @if ($sidebarCollapsible)
                        x-show="$store.sidebar.isOpen"
                        x-transition:enter="lg:transition lg:delay-100"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                    @endif
                    class="fi-sidebar-item-label flex-1 truncate text-start text-sm font-medium text-gray-700 dark:text-gray-200"
                >
                    Sign out
                </span>
            </button>
        </form>
    </li>
</ul>
