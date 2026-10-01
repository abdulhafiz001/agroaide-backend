@extends('layouts.staff')

@section('title', 'Crop Scans')

@section('content')

{{-- Header --}}
<div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Crop Scans Directory</h1>
        <p class="mt-0.5 text-sm text-slate-500">Examine, diagnose, and verify farmer-submitted crop disease scans.</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800">
            {{ number_format($statusCounts['all'] ?? 0) }} total scans
        </span>
    </div>
</div>

{{-- Filters and Search --}}
<div class="mb-6 space-y-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        {{-- Status Filter Tabs --}}
        <div class="inline-flex flex-wrap rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            @php($currStatus = request('status', 'pending'))
            <a href="{{ route('staff.scans.index', array_filter(['status' => 'pending', 'search' => request('search')])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $currStatus === 'pending' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Pending ({{ $statusCounts['pending'] ?? 0 }})
            </a>
            <a href="{{ route('staff.scans.index', array_filter(['status' => 'verified', 'search' => request('search')])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $currStatus === 'verified' ? 'bg-emerald-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Verified ({{ $statusCounts['verified'] ?? 0 }})
            </a>
            <a href="{{ route('staff.scans.index', array_filter(['status' => 'disputed', 'search' => request('search')])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $currStatus === 'disputed' ? 'bg-orange-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Disputed ({{ $statusCounts['disputed'] ?? 0 }})
            </a>
            <a href="{{ route('staff.scans.index', array_filter(['status' => 'rejected', 'search' => request('search')])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $currStatus === 'rejected' ? 'bg-red-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                Rejected ({{ $statusCounts['rejected'] ?? 0 }})
            </a>
            <a href="{{ route('staff.scans.index', array_filter(['status' => 'all', 'search' => request('search')])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $currStatus === 'all' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                All ({{ $statusCounts['all'] ?? 0 }})
            </a>
        </div>

        {{-- Search Form --}}
        <form method="get" action="{{ route('staff.scans.index') }}" class="flex items-center gap-2">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="relative w-full sm:w-64">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search by ID, crop, disease…"
                       class="w-full rounded-lg border border-slate-300 py-1.5 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                <svg class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <button type="submit" class="rounded-lg bg-slate-800 px-3.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-slate-900">
                Search
            </button>
            @if(request('search'))
                <a href="{{ route('staff.scans.index', ['status' => request('status', 'pending')]) }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </form>

    </div>
</div>

@if($queue->isEmpty())
    <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center shadow-sm">
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <p class="mt-3 font-bold text-slate-800">No scans found</p>
        <p class="mt-1 text-sm text-slate-500">
            @if(request('search'))
                No scans match your search criteria "{{ request('search') }}".
            @else
                No scans currently in the "{{ ucfirst($currStatus) }}" filter.
            @endif
        </p>
    </div>
@else
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($queue as $scan)
        <article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">

            {{-- Scan image container --}}
            <div class="relative aspect-video w-full overflow-hidden bg-slate-100">
                <img src="{{ route('staff.scans.image', $scan) }}"
                     alt="Crop scan #{{ $scan->id }}"
                     class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">

                {{-- State badge --}}
                <div class="absolute left-3 top-3">
                    @php($badgeStyle = match($scan->verification_state) {
                        'expert_verified' => 'bg-emerald-600 text-white',
                        'disputed'        => 'bg-orange-600 text-white',
                        'expert_rejected' => 'bg-red-600 text-white',
                        default           => 'bg-amber-600 text-white',
                    })
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wider shadow {{ $badgeStyle }}">
                        {{ str_replace('_', ' ', $scan->verification_state) }}
                    </span>
                </div>

                {{-- Confidence score --}}
                @if($scan->normalized_confidence !== null)
                <div class="absolute right-3 top-3">
                    <span class="rounded-full bg-slate-900/75 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur-sm shadow">
                        {{ number_format($scan->normalized_confidence * 100, 1) }}% conf
                    </span>
                </div>
                @endif
            </div>

            {{-- Card body --}}
            <div class="flex flex-1 flex-col p-5">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <p class="font-bold text-slate-900">Scan #{{ $scan->id }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $scan->farmField?->name ?? 'Unknown Field' }}
                            @if($scan->farmField?->crop) · <span class="font-medium text-emerald-700">{{ $scan->farmField->crop }}</span> @endif
                        </p>
                    </div>
                    <p class="shrink-0 text-xs text-slate-400">{{ $scan->created_at?->diffForHumans() }}</p>
                </div>

                <div class="mb-5 rounded-xl bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Diagnosis</p>
                    <p class="mt-0.5 text-sm font-semibold text-slate-800">
                        {{ $scan->predictedDiseaseLabel?->name ?? $scan->disease_name ?? 'Healthy / Inconclusive' }}
                    </p>
                </div>

                <div class="mt-auto">
                    <a href="{{ route('staff.scans.show', $scan) }}"
                       class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Review &amp; Verify
                    </a>
                </div>
            </div>

        </article>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($queue->hasPages())
    <div class="mt-8">
        {{ $queue->links() }}
    </div>
    @endif
@endif

@endsection
