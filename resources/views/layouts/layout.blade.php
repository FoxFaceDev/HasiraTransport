<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <link rel="icon" type="image/svg+xml" href="/icons/hasira-mark.svg?v=2">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ config('app.name', 'Hasira Transport') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    x-data="{
        sidebarCollapsed: false,
        init() {
            const savedPreference = localStorage.getItem('sidebar-collapsed');
            this.sidebarCollapsed = window.innerWidth > 900 && (savedPreference === null ? window.innerWidth < 1536 : savedPreference === 'true');
        },
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebar-collapsed', String(this.sidebarCollapsed));
        }
    }"
    class="app-shell font-sans antialiased min-h-screen flex text-sm overflow-x-hidden"
>
    <x-operation-notification />

    <aside :class="sidebarCollapsed ? 'is-collapsed w-20' : 'w-64'" class="app-sidebar flex-shrink-0 m-4 flex flex-col h-[calc(100vh-2rem)]">
        <div class="sidebar-header p-5 flex items-center gap-3 border-b border-slate-100">
            <div class="brand-mark w-11 h-11 shrink-0 flex items-center justify-center text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h11v9H3V7Zm11 3h3.5l3 3v3H14v-6ZM6.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />
                </svg>
            </div>
            <div class="sidebar-brand-copy" x-show="!sidebarCollapsed" x-transition.opacity>
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-blue-600">Hasira</div>
                <div class="text-lg font-bold text-slate-900 leading-tight">Transport</div>
            </div>
            <button type="button" @click="toggleSidebar()" class="sidebar-toggle" :title="sidebarCollapsed ? 'کردنەوەی لیست' : 'داخستنی لیست'" :aria-label="sidebarCollapsed ? 'کردنەوەی لیست' : 'داخستنی لیست'">
                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sidebarCollapsed }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
            </button>
        </div>

        <nav class="flex-1 mt-4 px-3 space-y-1.5 overflow-y-auto">
            @if(auth()->user()->can('view tankers') || auth()->user()->can('view gatekeeper'))
            <a href="{{ route('dashboard') }}" title="داشبۆرد" class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z"/></svg>
                <span>داشبۆرد</span>
            </a>
            @endif
            @can('view drivers')
            <a href="{{ route('drivers.index') }}" title="شۆفێرەکان" class="nav-link {{ request()->routeIs('drivers.*') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m10-10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm10 10v-2a4 4 0 0 0-3-3.87m-2-7.96a4 4 0 0 1 0 7.75"/></svg>
                <span>شۆفێرەکان</span>
            </a>
            @endcan

            @can('view tankers')
            <a href="{{ route('tankers.index') }}" title="خەتەکان" class="nav-link {{ request()->routeIs('tankers.*') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h11v9H3V7Zm11 3h3.5l3 3v3H14v-6ZM6.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
                <span>خەتەکان</span>
            </a>
            @endcan

            @can('view gatekeeper')
            <a href="{{ route('gatekeeper.index') }}" title="کۆنترۆڵی دەروازە" class="nav-link {{ request()->routeIs('gatekeeper.index') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19V9a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10M8 7V4m8 3V4M3 19h18M8 12h2m4 0h2m-8 4h2m4 0h2"/></svg>
                <span>کۆنترۆڵی دەروازە</span>
            </a>
            @endcan

            @can('view reports')
            <a href="{{ route('reports.index') }}" title="ڕاپۆرتەکان" class="nav-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8m-6-6v6h6M8 13h8m-8 4h8"/></svg>
                <span>ڕاپۆرتەکان</span>
            </a>
            @endcan

            @can('manage users')
            <a href="{{ route('users.index') }}" title="بەکارهێنەران" class="nav-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4m10-10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm7-1v6m3-3h-6"/></svg>
                <span>بەکارهێنەران</span>
            </a>
            @endcan

            @can('view audit logs')
            <a href="{{ route('audit-logs.index') }}" title="تۆماری چاودێری" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.6-4.4A10.9 10.9 0 0 1 12 2a10.9 10.9 0 0 1-8.6 3.6A11.8 11.8 0 0 0 3 9c0 5.2 3.8 10 9 11 5.2-1 9-5.8 9-11 0-1.2-.1-2.3-.4-3.4Z"/></svg>
                <span>تۆماری چاودێری</span>
            </a>
            @endcan

            @role('super_admin')
            <a href="{{ route('roles.index') }}" title="ڕۆڵ و دەسەڵاتەکان" class="nav-link {{ request()->routeIs('roles.*') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7.4-3a7.4 7.4 0 0 0-.1-1l2-1.5-2-3.4-2.4 1a8 8 0 0 0-1.8-1L14.8 3h-4l-.4 3.1a8 8 0 0 0-1.8 1l-2.4-1-2 3.4 2 1.5a7.4 7.4 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a8 8 0 0 0 1.8 1l.4 3.1h4l.4-3.1a8 8 0 0 0 1.8-1l2.4 1 2-3.4-2-1.5a7.4 7.4 0 0 0 .1-1Z"/></svg>
                <span>ڕۆڵ و دەسەڵاتەکان</span>
            </a>
            @endrole

            @can('view gatekeeper')
            <a href="{{ route('gatekeeper.history') }}" title="مێژووی خەتەکان" class="nav-link {{ request()->routeIs('gatekeeper.history') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2m6-2a9 9 0 1 1-3-6.7M21 3v6h-6"/></svg>
                <span>مێژووی خەتەکان</span>
            </a>
            <div class="sidebar-section-title pt-4 pb-1 px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">لیستی دۆخەکان</div>
            <a href="{{ route('gatekeeper.schedule') }}" title="لیستی ئەمڕۆ" class="nav-link {{ request()->routeIs('gatekeeper.schedule') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 2v3m8-3v3M3 9h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Zm3 9h3m2 0h3m-8 4h3"/></svg>
                <span>لیستی ئەمڕۆ</span>
            </a>
            <a href="{{ route('gatekeeper.filter', 'green') }}" title="هاتووەکان" class="nav-link is-green {{ request()->is('*green') ? 'is-active' : '' }}">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ml-4 ring-4 ring-emerald-50"></span>
                <span>هاتووەکان</span>
            </a>
            <a href="{{ route('gatekeeper.filter', 'yellow') }}" title="دواخراوەکان" class="nav-link is-yellow {{ request()->is('*yellow') ? 'is-active' : '' }}">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 ml-4 ring-4 ring-amber-50"></span>
                <span>دواخراوەکان</span>
            </a>
            <a href="{{ route('gatekeeper.filter', 'red') }}" title="نەهاتووەکان" class="nav-link is-red {{ request()->is('*red') ? 'is-active' : '' }}">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 ml-4 ring-4 ring-rose-50"></span>
                <span>نەهاتووەکان</span>
            </a>
            <a href="{{ route('gatekeeper.filter', 'departed') }}" title="ڕۆیشتووەکان" class="nav-link is-departed {{ request()->is('*departed') ? 'is-active' : '' }}">
                <span class="w-2.5 h-2.5 rounded-full bg-sky-500 ml-4 ring-4 ring-sky-50"></span>
                <span>ڕۆیشتووەکان</span>
            </a>
            @endcan

            @if(auth()->user()->can('view drivers') || auth()->user()->can('view tankers') || auth()->user()->can('view gatekeeper'))
            <a href="{{ route('blocks.index') }}" title="بلۆککراوەکان" class="nav-link {{ request()->routeIs('blocks.*') ? 'is-active' : '' }}">
                <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.4 5.6a9 9 0 1 1-12.8 0 9 9 0 0 1 12.8 0ZM5.8 18.2 18.2 5.8"/></svg>
                <span>بلۆککراوەکان</span>
            </a>
            @endif
        </nav>

        <div class="p-3 border-t border-slate-100">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="دەرچوون" class="nav-link w-full text-rose-600 hover:text-rose-700 hover:bg-rose-50">
                    <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 17l5-5-5-5m5 5H3m11-9h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg>
                    <span>دەرچوون</span>
                </button>
            </form>
        </div>
    </aside>

    <main class="app-main min-w-0 flex-1 flex flex-col h-screen overflow-y-auto p-3 relative">
        <header class="app-topbar flex justify-between items-center mb-3 px-4 py-2">
            <div class="flex items-center gap-3">
                <div class="user-avatar w-8 h-8 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm4 14a7 7 0 0 0-14 0"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900">{{ auth()->user()->name ?? 'User' }}</h3>
                    <p class="text-[11px] leading-tight text-slate-500">{{ auth()->user()?->roles->pluck('name')->map(fn ($role) => str_replace('_', ' ', $role))->join('، ') }}</p>
                </div>
            </div>

            <div x-data="{ online: navigator.onLine }" @online.window="online = true" @offline.window="online = false" class="flex items-center">
                <div x-show="online" class="status-online flex items-center px-2.5 py-1 rounded-full font-semibold text-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 ml-2"></span> ئۆنلاین
                </div>
                <div x-show="!online" style="display:none;" class="status-offline flex items-center px-2.5 py-1 rounded-full font-semibold text-xs">
                    <span class="w-2 h-2 rounded-full bg-rose-500 ml-2 animate-pulse"></span> ئۆفلاین
                </div>
            </div>
        </header>

        <div class="min-w-0 flex-1 pb-4">
            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>
