<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Operations') — AgroAide</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-900">

{{-- Mobile backdrop --}}
<div id="sidebar-backdrop"
     class="fixed inset-0 z-20 bg-slate-900/40 backdrop-blur-sm hidden lg:hidden"></div>

{{-- ═══ Sidebar ═══ --}}
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0">

    {{-- Brand --}}
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-100 px-5">
        <img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide" class="h-9 w-9 shrink-0 rounded-xl object-contain shadow-sm">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">AgroAide</p>
            <p class="text-xs text-slate-400 leading-none">Staff portal</p>
        </div>
    </div>

    {{-- User badge --}}
    <a href="{{ route('staff.profile') }}" class="group flex shrink-0 items-center gap-3 border-b border-slate-100 px-5 py-3 transition-colors hover:bg-slate-50">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800 uppercase">
            {{ substr(auth()->user()->name, 0, 1) }}
        </div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-slate-800 group-hover:text-emerald-700">{{ auth()->user()->name }}</p>
            @if(auth()->user()->role === 'admin')
                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Admin</span>
            @else
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Agronomist</span>
            @endif
        </div>
        <svg class="h-4 w-4 text-slate-400 group-hover:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
    </a>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4">

        <p class="mb-1 px-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Main</p>

        <a href="{{ route('staff.dashboard') }}"
           class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('staff.dashboard') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <a href="{{ route('staff.scans.index') }}"
           class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('staff.scans.*') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Scan review
            @php($pendingCount = \App\Models\FarmImageAnalysis::whereIn('verification_state', ['pending_review','disputed'])->count())
            @if($pendingCount > 0)
                <span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">{{ $pendingCount }}</span>
            @endif
        </a>

        <a href="{{ route('staff.feedback') }}"
           class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('staff.feedback') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
            </svg>
            Farmer feedback
        </a>

        <a href="{{ route('staff.outbreaks') }}"
           class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('staff.outbreaks') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Outbreaks
        </a>

        <a href="{{ route('staff.health') }}"
           class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('staff.health') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
            </svg>
            System health
        </a>

        @if(auth()->user()->isAdmin())
            <p class="mb-1 mt-5 px-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Admin</p>

            <a href="{{ route('staff.users.index') }}"
               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('staff.users.*') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Users &amp; Farmers
            </a>

            <a href="{{ route('staff.policies.index') }}"
               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('staff.policies.*', 'staff.admin') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Confidence policies
            </a>

            <a href="{{ route('staff.audit') }}"
               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('staff.audit') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Audit log
            </a>
        @endif

        <p class="mb-1 mt-5 px-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Account</p>

        <a href="{{ route('staff.profile') }}"
           class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('staff.profile') ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            My Profile
        </a>

    </nav>

    {{-- Logout --}}
    <div class="shrink-0 border-t border-slate-100 p-3">
        <form method="post" action="{{ route('staff.logout') }}">
            @csrf
            <button type="submit"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-500 transition-colors hover:bg-red-50 hover:text-red-700">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Sign out
            </button>
        </form>
    </div>

</aside>

{{-- ═══ Main wrapper ═══ --}}
<div class="flex min-h-full flex-col lg:pl-64">

    {{-- Mobile top bar --}}
    <header class="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:hidden">
        <button id="mobile-sidebar-open"
                type="button"
                class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus:outline-none">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide" class="h-7 w-7 shrink-0 rounded-lg object-contain">
        <p class="font-semibold text-slate-800 text-sm">@yield('title', 'AgroAide Staff')</p>
    </header>

    {{-- Page --}}
    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            @if(session('status'))
                <div class="mb-5 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ session('status') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </div>
    </main>

</div>

</body>
</html>
