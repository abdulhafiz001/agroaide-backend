@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')

{{-- Page header --}}
<div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Operations Dashboard</h1>
        <p class="mt-0.5 text-sm text-slate-500">Welcome back, {{ auth()->user()->name }} · AgroAide platform analytics and operations</p>
    </div>
    <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
        <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
        <span>System active · {{ now()->format('D, d M Y') }}</span>
    </div>
</div>

{{-- KPI strip --}}
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">

    {{-- Registered Farmers --}}
    @if(auth()->user()->isAdmin())
    <a href="{{ route('staff.users.index') }}" class="group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
    @else
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    @endif
        <div class="flex items-center justify-between text-slate-400 group-hover:text-emerald-600">
            <span class="text-xs font-semibold uppercase tracking-wide">Farmers</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($registeredFarmers) }}</p>
        <p class="mt-1 text-xs text-slate-400">Registered farmers</p>
    @if(auth()->user()->isAdmin())
    </a>
    @else
    </div>
    @endif

    {{-- Active Farmers (30d) --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between text-slate-400">
            <span class="text-xs font-semibold uppercase tracking-wide">Active farms (30 d)</span>
            <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($activeFarmCount) }}</p>
        <p class="mt-1 text-xs text-slate-400">Active farmers past 30 days</p>
    </div>

    {{-- Total Scans --}}
    <a href="{{ route('staff.scans.index') }}" class="group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
        <div class="flex items-center justify-between text-slate-400 group-hover:text-emerald-600">
            <span class="text-xs font-semibold uppercase tracking-wide">Total Scans</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($totalScans) }}</p>
        <p class="mt-1 flex items-center justify-between text-xs text-slate-400">
            <span>{{ $pendingScans }} pending review</span>
            @if($pendingScans > 0)
            <span class="rounded-full bg-amber-100 px-1.5 py-0.2 font-semibold text-amber-700">Needs action</span>
            @endif
        </p>
    </a>

    {{-- Monitored Fields --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between text-slate-400">
            <span class="text-xs font-semibold uppercase tracking-wide">Farm Fields</span>
            <svg class="h-5 w-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
            </svg>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($monitoredFields) }}</p>
        <p class="mt-1 text-xs text-slate-400">Registered crop fields</p>
    </div>

    {{-- Outbreaks --}}
    <a href="{{ route('staff.outbreaks') }}" class="group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
        <div class="flex items-center justify-between text-slate-400 group-hover:text-red-600">
            <span class="text-xs font-semibold uppercase tracking-wide">Outbreaks</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($outbreakCount) }}</p>
        <p class="mt-1 text-xs text-slate-400">Active regional clusters</p>
    </a>

    {{-- Farmer Feedback --}}
    <a href="{{ route('staff.feedback') }}" class="group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
        <div class="flex items-center justify-between text-slate-400 group-hover:text-blue-600">
            <span class="text-xs font-semibold uppercase tracking-wide">Feedback</span>
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
            </svg>
        </div>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($feedbackCount) }}</p>
        <p class="mt-1 text-xs text-slate-400">Total verdicts submitted</p>
    </a>

</div>

{{-- Analytics charts --}}
<div class="mb-8 grid gap-6 lg:grid-cols-2">

    {{-- User Registration Growth --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Farmer Registration Trend</h2>
                <p class="text-xs text-slate-400">Daily new registered farmer accounts (last 30 days)</p>
            </div>
            <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                +{{ array_sum($chartUserCounts) }} last 30d
            </span>
        </div>
        <div class="relative h-64 w-full">
            <canvas id="usersChart"></canvas>
        </div>
    </div>

    {{-- Daily Scan Usage --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">Crop Scan Activity</h2>
                <p class="text-xs text-slate-400">Daily image scans submitted by farmers (last 30 days)</p>
            </div>
            <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                {{ array_sum($chartScanCounts) }} scans total
            </span>
        </div>
        <div class="relative h-64 w-full">
            <canvas id="scansChart"></canvas>
        </div>
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
                <p class="text-xs text-slate-400">Diagnosis verdict reviews</p>
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
                <p class="text-xs text-slate-400">Spatial cluster intelligence</p>
            </div>
        </a>

        <a href="{{ route('staff.users.index') }}"
           class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-slate-800 group-hover:text-emerald-700">Users &amp; Farmers</p>
                <p class="text-xs text-slate-400">Profiles, scans &amp; accounts</p>
            </div>
        </a>

    </div>
</div>

{{-- Recent review queue preview --}}
@if($recentScans->isNotEmpty())
<div>
    <div class="mb-3 flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-slate-800">Recent Scans Requiring Attention</h2>
            <p class="text-xs text-slate-400">Latest crop scans flagged for expert agronomist verification</p>
        </div>
        <a href="{{ route('staff.scans.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">View all scans →</a>
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
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Submitted</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($recentScans as $scan)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs text-slate-500">#{{ $scan->id }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $scan->farmField?->name ?? '—' }} · <span class="text-slate-500">{{ $scan->farmField?->crop ?? 'unspecified' }}</span></td>
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $scan->predictedDiseaseLabel?->name ?? $scan->disease_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $scan->normalized_confidence === null ? '—' : number_format($scan->normalized_confidence * 100, 1).'%' }}</td>
                    <td class="px-4 py-3">
                        @if($scan->verification_state === 'disputed')
                            <span class="inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-semibold text-orange-700">Disputed</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Pending</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">{{ $scan->created_at?->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('staff.scans.show', $scan) }}" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-100">Review</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    const labels = @json($chartLabels);
    const userCounts = @json($chartUserCounts);
    const scanCounts = @json($chartScanCounts);

    const usersCtx = document.getElementById('usersChart');
    if (usersCtx) {
        new Chart(usersCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'New Farmers',
                    data: userCounts,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#059669'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        intersect: false,
                        mode: 'index',
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 8, font: { size: 11 }, color: '#94a3b8' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 }, color: '#94a3b8' },
                        grid: { color: '#f1f5f9' }
                    }
                }
            }
        });
    }

    const scansCtx = document.getElementById('scansChart');
    if (scansCtx) {
        new Chart(scansCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Scans',
                    data: scanCounts,
                    backgroundColor: '#0284c7',
                    borderRadius: 4,
                    hoverBackgroundColor: '#0369a1',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        intersect: false,
                        mode: 'index',
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 8, font: { size: 11 }, color: '#94a3b8' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 }, color: '#94a3b8' },
                        grid: { color: '#f1f5f9' }
                    }
                }
            }
        });
    }
});
</script>

@endsection
