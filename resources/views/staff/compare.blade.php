@extends('layouts.staff')

@section('title', 'Run comparison')

@section('content')

<nav class="mb-5 flex items-center gap-2 text-sm text-slate-500">
    <a href="{{ route('staff.evaluations.index') }}" class="font-medium text-emerald-700 hover:underline">Evaluations</a>
    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    <span>Compare</span>
</nav>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Run comparison</h1>
    <p class="mt-1 text-sm text-slate-500">Side-by-side summary of {{ $runs->count() }} selected runs.</p>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Run</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Samples</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Model</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Prompt</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Policy</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Accuracy</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Coverage</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">P95 latency</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($runs as $run)
                @php($metrics = $run->metrics ?? [])
                @php($runColor = match($run->status) {
                    'completed' => 'bg-emerald-100 text-emerald-800',
                    'failed'    => 'bg-red-100 text-red-800',
                    'running'   => 'bg-blue-100 text-blue-800',
                    default     => 'bg-slate-100 text-slate-700',
                })
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <a href="{{ route('staff.evaluations.runs.show', $run) }}"
                           class="font-semibold text-emerald-700 hover:underline">#{{ $run->id }}</a>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $runColor }}">
                            {{ ucfirst($run->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-700">{{ $run->sample_count }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $run->model_version_id }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $run->prompt_version_id }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $run->confidence_policy_id }}</td>
                    <td class="px-4 py-3 font-bold text-slate-900">
                        {{ data_get($metrics, 'accuracy') === null ? '—' : number_format(data_get($metrics, 'accuracy') * 100, 1).'%' }}
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                        {{ data_get($metrics, 'coverage') === null ? '—' : number_format(data_get($metrics, 'coverage') * 100, 1).'%' }}
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                        {{ data_get($metrics, 'latency.p95_ms') === null ? '—' : number_format(data_get($metrics, 'latency.p95_ms')).' ms' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 flex justify-end">
    <a href="{{ route('staff.evaluations.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">← Back to evaluations</a>
</div>

@endsection
