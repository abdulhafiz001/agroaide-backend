@extends('layouts.staff')

@section('title', 'Run #'.$run->id)

@section('content')

<nav class="mb-5 flex items-center gap-2 text-sm text-slate-500">
    <a href="{{ route('staff.evaluations.index') }}" class="font-medium text-emerald-700 hover:underline">Evaluations</a>
    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    <a href="{{ route('staff.evaluations.datasets.show', $run->evaluation_dataset_id) }}" class="hover:underline">Dataset #{{ $run->evaluation_dataset_id }}</a>
    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    <span>Run #{{ $run->id }}</span>
</nav>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Evaluation run #{{ $run->id }}</h1>
        <p class="mt-1 text-sm text-slate-500">
            Model {{ $run->model_version_id }} · Prompt {{ $run->prompt_version_id }} · Policy {{ $run->confidence_policy_id }}
            · {{ $run->sample_count }} samples
        </p>
    </div>
    @php($runColor = match($run->status) {
        'completed' => 'bg-emerald-100 text-emerald-800',
        'failed'    => 'bg-red-100 text-red-800',
        'running'   => 'bg-blue-100 text-blue-800',
        default     => 'bg-slate-100 text-slate-700',
    })
    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $runColor }}">
        {{ ucfirst($run->status) }}
    </span>
</div>

{{-- Summary metrics --}}
@php($summary = $run->metrics ?? [])
<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Accuracy</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">
            {{ data_get($summary, 'accuracy') === null ? '—' : number_format(data_get($summary, 'accuracy') * 100, 1).'%' }}
        </p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Coverage</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">
            {{ data_get($summary, 'coverage') === null ? '—' : number_format(data_get($summary, 'coverage') * 100, 1).'%' }}
        </p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">P95 latency</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">
            {{ data_get($summary, 'latency.p95_ms') === null ? '—' : number_format(data_get($summary, 'latency.p95_ms')).' ms' }}
        </p>
    </div>
</div>

{{-- Per-class metrics table --}}
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="font-semibold text-slate-900">Per-class metrics</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Class</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">TP</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">FP</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">FN</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">TN</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Precision</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Recall</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">F1</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">FPR</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($metrics as $metric)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $metric->label_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $metric->tp }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $metric->fp }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $metric->fn }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $metric->tn }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $metric->precision === null ? '—' : number_format($metric->precision, 3) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $metric->recall === null ? '—' : number_format($metric->recall, 3) }}</td>
                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $metric->f1 === null ? '—' : number_format($metric->f1, 3) }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $metric->fpr === null ? '—' : number_format($metric->fpr, 3) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">No per-class metrics yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
