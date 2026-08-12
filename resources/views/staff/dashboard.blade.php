@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')

{{-- Page header --}}
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Dashboard</h1>
    <p class="mt-1 text-sm text-slate-500">AgroAide production overview</p>
</div>

{{-- KPI strip --}}
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Review queue</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ $pendingScans }}</p>
        <p class="mt-1 text-xs text-slate-400">Pending + disputed</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active farms (30 d)</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ $activeFarmCount < 3 ? '<3' : $activeFarmCount }}</p>
        <p class="mt-1 text-xs text-slate-400">Scan, journal, task, or transaction</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Latest accuracy</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ $latestAccuracy === null ? '—' : number_format($latestAccuracy * 100, 1).'%' }}</p>
        <p class="mt-1 text-xs text-slate-400">From evaluation runs</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Outbreak aggregates</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ $outbreakCount }}</p>
        <p class="mt-1 text-xs text-slate-400">k≥3 visible events</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Recent feedback</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ $feedbackCount }}</p>
        <p class="mt-1 text-xs text-slate-400">Verdicts last 7 days</p>
    </div>

</div>

{{-- Shortcuts --}}
<div class="mb-8">
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Quick access</h2>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">

        <a href="{{ route('staff.scans.index') }}"
           class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-slate-800 group-hover:text-emerald-700">Review queue</p>
                <p class="text-xs text-slate-400">{{ $pendingScans }} awaiting review</p>
            </div>
        </a>

        <a href="{{ route('staff.feedback') }}"
           class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-slate-800 group-hover:text-emerald-700">Farmer feedback</p>
                <p class="text-xs text-slate-400">Latest verdicts</p>
            </div>
        </a>

        <a href="{{ route('staff.outbreaks') }}"
           class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100 text-red-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-slate-800 group-hover:text-emerald-700">Outbreaks</p>
                <p class="text-xs text-slate-400">Privacy-safe aggregates</p>
            </div>
        </a>

        <a href="{{ route('staff.health') }}"
           class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-slate-800 group-hover:text-emerald-700">System health</p>
                <p class="text-xs text-slate-400">Provider &amp; job status</p>
            </div>
        </a>

    </div>
</div>

{{-- Recent review queue preview --}}
@if($recentScans->isNotEmpty())
<div>
    <div class="mb-3 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">Pending scans</h2>
        <a href="{{ route('staff.scans.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">View all →</a>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Scan</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Field / Crop</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Predicted disease</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Confidence</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">State</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($recentScans as $scan)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs text-slate-500">#{{ $scan->id }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $scan->farmField?->name ?? '—' }} · {{ $scan->farmField?->crop ?? 'unknown' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $scan->predictedDiseaseLabel?->name ?? $scan->disease_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $scan->normalized_confidence === null ? '—' : number_format($scan->normalized_confidence * 100, 1).'%' }}</td>
                    <td class="px-4 py-3">
                        @if($scan->verification_state === 'disputed')
                            <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-orange-700">Disputed</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Pending</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('staff.scans.show', $scan) }}" class="text-sm font-medium text-emerald-700 hover:underline">Review</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
