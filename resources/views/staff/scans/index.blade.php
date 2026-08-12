@extends('layouts.staff')

@section('title', 'Scan review')

@section('content')

<div class="mb-6 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Scan review</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $queue->total() }} scans pending or disputed</p>
    </div>
</div>

@if($queue->isEmpty())
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center shadow-sm">
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <p class="mt-3 font-semibold text-slate-800">Queue is clear</p>
        <p class="mt-1 text-sm text-slate-500">No scans pending review.</p>
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($queue as $scan)
        <article class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-shadow hover:shadow-md">

            {{-- Scan image --}}
            <div class="relative aspect-video overflow-hidden bg-slate-100">
                <img src="{{ route('staff.scans.image', $scan) }}"
                     alt="Crop scan #{{ $scan->id }}"
                     class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                {{-- State badge --}}
                <div class="absolute left-3 top-3">
                    @if($scan->verification_state === 'disputed')
                        <span class="rounded-full bg-orange-500 px-2.5 py-1 text-xs font-bold text-white shadow">Disputed</span>
                    @else
                        <span class="rounded-full bg-amber-500 px-2.5 py-1 text-xs font-bold text-white shadow">Pending</span>
                    @endif
                </div>
                {{-- Confidence --}}
                @if($scan->normalized_confidence !== null)
                <div class="absolute right-3 top-3">
                    <span class="rounded-full bg-slate-900/70 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur-sm">
                        {{ number_format($scan->normalized_confidence * 100, 1) }}%
                    </span>
                </div>
                @endif
            </div>

            {{-- Card body --}}
            <div class="p-4">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-slate-800">Scan #{{ $scan->id }}</p>
                        <p class="text-sm text-slate-500">
                            {{ $scan->farmField?->name ?? 'Unknown field' }}
                            @if($scan->farmField?->crop) · {{ $scan->farmField->crop }} @endif
                        </p>
                    </div>
                    <p class="shrink-0 text-xs text-slate-400">{{ $scan->created_at?->diffForHumans() }}</p>
                </div>

                <div class="mb-4 rounded-lg bg-slate-50 px-3 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Predicted disease</p>
                    <p class="mt-0.5 font-medium text-slate-800">
                        {{ $scan->predictedDiseaseLabel?->name ?? $scan->disease_name ?? 'No canonical match' }}
                    </p>
                </div>

                <a href="{{ route('staff.scans.show', $scan) }}"
                   class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Open &amp; review
                </a>
            </div>

        </article>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($queue->hasPages())
    <div class="mt-6">
        {{ $queue->links() }}
    </div>
    @endif
@endif

@endsection
