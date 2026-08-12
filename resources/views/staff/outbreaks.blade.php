@extends('layouts.staff')

@section('title', 'Outbreaks')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Outbreak trends</h1>
    <p class="mt-1 text-sm text-slate-500">Privacy-safe aggregates — events with fewer than 3 distinct farms are suppressed.</p>
</div>

{{-- Level summary --}}
@if($levelSummary->isNotEmpty())
<div class="mb-6 grid gap-3 sm:grid-cols-3">
    @foreach($levelSummary as $row)
    @php($levelColor = match($row->level) {
        'alert'   => 'border-red-200 bg-red-50',
        'warning' => 'border-amber-200 bg-amber-50',
        default   => 'border-emerald-200 bg-emerald-50',
    })
    @php($textColor = match($row->level) {
        'alert'   => 'text-red-700',
        'warning' => 'text-amber-700',
        default   => 'text-emerald-700',
    })
    <div class="rounded-xl border p-4 {{ $levelColor }}">
        <p class="text-xs font-semibold uppercase tracking-wide {{ $textColor }}">{{ ucfirst($row->level) }}</p>
        <p class="mt-1 text-2xl font-bold {{ $textColor }}">{{ $row->total }}</p>
        <p class="text-xs {{ $textColor }} opacity-70">active aggregate{{ $row->total !== 1 ? 's' : '' }}</p>
    </div>
    @endforeach
</div>
@endif

@if($outbreaks->isEmpty())
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <p class="mt-3 font-semibold text-slate-800">No visible outbreaks</p>
        <p class="mt-1 text-sm text-slate-500">All events are below the k≥3 threshold.</p>
    </div>
@else
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Crop</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Disease</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Grid</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Level</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Farms</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Scans</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Period</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($outbreaks as $event)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $event->crop_key }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $event->label_name ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $event->grid_key }}</td>
                    <td class="px-4 py-3">
                        @php($pillColor = match($event->level) {
                            'alert'   => 'bg-red-100 text-red-800',
                            'warning' => 'bg-amber-100 text-amber-800',
                            default   => 'bg-emerald-100 text-emerald-800',
                        })
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $pillColor }}">
                            {{ ucfirst($event->level) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $event->distinct_farmer_count }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $event->eligible_scan_count }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($event->period_start)->format('d M') }} – {{ \Carbon\Carbon::parse($event->period_end)->format('d M Y') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($outbreaks->hasPages())
    <div class="mt-5">
        {{ $outbreaks->links() }}
    </div>
    @endif
@endif

@endsection
