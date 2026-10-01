@extends('layouts.staff')

@section('title', 'Users & Farmers')

@section('content')

{{-- Header --}}
<div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Users &amp; Farmers</h1>
        <p class="mt-0.5 text-sm text-slate-500">Directory of all registered farmers, agronomists, and system administrators.</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800">
            {{ number_format($roleCounts['all'] ?? 0) }} total registered
        </span>
    </div>
</div>

{{-- Filter Tabs & Search Bar --}}
<div class="mb-6 space-y-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        {{-- Role filter tabs --}}
        <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            @php($currentRole = request('role'))
            <a href="{{ route('staff.users.index', array_filter(['search' => request('search')])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ empty($currentRole) ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                All ({{ $roleCounts['all'] ?? 0 }})
            </a>
            <a href="{{ route('staff.users.index', array_filter(['role' => 'farmer', 'search' => request('search')])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $currentRole === 'farmer' ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Farmers ({{ $roleCounts['farmer'] ?? 0 }})
            </a>
            <a href="{{ route('staff.users.index', array_filter(['role' => 'agronomist', 'search' => request('search')])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $currentRole === 'agronomist' ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Agronomists ({{ $roleCounts['agronomist'] ?? 0 }})
            </a>
            <a href="{{ route('staff.users.index', array_filter(['role' => 'admin', 'search' => request('search')])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $currentRole === 'admin' ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Admins ({{ $roleCounts['admin'] ?? 0 }})
            </a>
        </div>

        {{-- Search Form --}}
        <form method="get" action="{{ route('staff.users.index') }}" class="flex items-center gap-2">
            @if(request('role'))
                <input type="hidden" name="role" value="{{ request('role') }}">
            @endif
            <div class="relative w-full sm:w-64">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search name, email, farm…"
                       class="w-full rounded-lg border border-slate-300 py-1.5 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                <svg class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button type="submit" class="rounded-lg bg-slate-800 px-3.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-slate-900">
                Search
            </button>
            @if(request('search') || request('role'))
                <a href="{{ route('staff.users.index') }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </form>

    </div>
</div>

{{-- Users Table --}}
<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50">
                <tr>
                    <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-400">User</th>
                    <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Role</th>
                    <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Farm / Location</th>
                    <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Crop Scans</th>
                    <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Fields</th>
                    <th class="px-6 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Joined</th>
                    <th class="px-6 py-3.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $user)
                <tr class="transition-colors hover:bg-slate-50/80">
                    {{-- User avatar & email --}}
                    <td class="px-6 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700 uppercase"
                                 style="{{ $user->avatar_color ? 'background-color: '.$user->avatar_color.'20; color: '.$user->avatar_color : '' }}">
                                {{ substr($user->name, 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('staff.users.show', $user) }}" class="truncate font-semibold text-slate-900 hover:text-emerald-700 hover:underline">
                                    {{ $user->name }}
                                </a>
                                <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>

                    {{-- Role --}}
                    <td class="px-6 py-3.5 whitespace-nowrap">
                        @php($roleStyle = match($user->role) {
                            'admin'      => 'bg-amber-100 text-amber-800 border-amber-200',
                            'agronomist' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            default      => 'bg-slate-100 text-slate-700 border-slate-200',
                        })
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $roleStyle }}">
                            {{ ucfirst($user->role) }}
                        </span>
                    </td>

                    {{-- Farm Name & Location --}}
                    <td class="px-6 py-3.5">
                        @if($user->farm_name || $user->farm_location)
                            <p class="font-medium text-slate-800">{{ $user->farm_name ?: 'Farm' }}</p>
                            <p class="text-xs text-slate-400">{{ $user->farm_location ?: 'Location unset' }}</p>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>

                    {{-- Crop Scans Count --}}
                    <td class="px-6 py-3.5 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            </svg>
                            {{ number_format($user->farm_image_analyses_count) }}
                        </span>
                    </td>

                    {{-- Fields Count --}}
                    <td class="px-6 py-3.5 whitespace-nowrap text-slate-700">
                        {{ number_format($user->farm_fields_count) }}
                    </td>

                    {{-- Joined --}}
                    <td class="px-6 py-3.5 text-xs text-slate-400 whitespace-nowrap">
                        {{ $user->created_at?->format('d M Y') ?? '—' }}
                    </td>

                    {{-- Details Button --}}
                    <td class="px-6 py-3.5 text-right whitespace-nowrap">
                        <a href="{{ route('staff.users.show', $user) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800">
                            View details
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">
                        No users found matching your criteria.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
    <div class="border-t border-slate-100 px-6 py-4">
        {{ $users->links() }}
    </div>
    @endif
</div>

@endsection
