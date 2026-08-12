@extends('layouts.staff')

@section('title', 'Evaluations')

@section('content')

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Evaluations</h1>
        <p class="mt-1 text-sm text-slate-500">Model accuracy datasets and benchmark runs.</p>
    </div>
    @if($runs->count() >= 2)
    <a href="{{ route('staff.evaluations.compare', ['runs' => $runs->take(2)->pluck('id')->toArray()]) }}"
       class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100">
        Compare latest 2 runs
    </a>
    @endif
</div>

<div class="grid gap-6 lg:grid-cols-2">

    {{-- Datasets --}}
    <div>
        <h2 class="mb-3 font-semibold text-slate-800">Datasets</h2>
        @if($datasets->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white py-8 text-center text-sm text-slate-500">
                No datasets imported yet. Use <code class="rounded bg-slate-100 px-1">agroaide:evaluation:import</code>.
            </div>
        @else
            <div class="space-y-2">
                @foreach($datasets as $dataset)
                <a href="{{ route('staff.evaluations.datasets.show', $dataset) }}"
                   class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
                    <div>
                        <p class="font-semibold text-slate-800">{{ $dataset->name }}</p>
                        <p class="text-xs text-slate-400">v{{ $dataset->version }} · {{ $dataset->items_count }} items</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($dataset->locked_at)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Locked</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Not ready</span>
                        @endif
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Recent runs --}}
    <div>
        <h2 class="mb-3 font-semibold text-slate-800">Recent runs</h2>
        @if($runs->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white py-8 text-center text-sm text-slate-500">
                No runs yet. Queue a run from a locked dataset.
            </div>
        @else
            <div class="space-y-2">
                @foreach($runs as $run)
                @php($runAccuracy = $run->metrics ? data_get(json_decode($run->metrics, true), 'accuracy') : null)
                <a href="{{ route('staff.evaluations.runs.show', $run) }}"
                   class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm transition-all hover:border-emerald-300 hover:shadow-md">
                    <div>
                        <p class="font-semibold text-slate-800">Run #{{ $run->id }}</p>
                        <p class="text-xs text-slate-400">{{ $run->sample_count }} samples · model {{ $run->model_version_id }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @php($runColor = match($run->status) {
                            'completed' => 'bg-emerald-100 text-emerald-800',
                            'failed'    => 'bg-red-100 text-red-800',
                            'running'   => 'bg-blue-100 text-blue-800',
                            default     => 'bg-slate-100 text-slate-700',
                        })
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $runColor }}">
                            {{ ucfirst($run->status) }}
                        </span>
                        @if($runAccuracy !== null)
                        <span class="text-xs font-bold text-slate-700">{{ number_format($runAccuracy * 100, 1) }}%</span>
                        @endif
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </a>
                @endforeach
            </div>

            {{-- Compare form --}}
            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Compare runs</h3>
                <form method="get" action="{{ route('staff.evaluations.compare') }}" class="space-y-2">
                    @foreach($runs as $run)
                    <label class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm hover:bg-slate-100 cursor-pointer">
                        <input type="checkbox" name="runs[]" value="{{ $run->id }}" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="font-medium text-slate-700">Run #{{ $run->id }}</span>
                        <span class="text-slate-400">· {{ $run->status }} · {{ $run->sample_count }} samples</span>
                    </label>
                    @endforeach
                    <button type="submit"
                            class="mt-2 w-full rounded-lg border border-slate-300 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100">
                        Compare selected
                    </button>
                </form>
            </div>
        @endif
    </div>

</div>

@endsection
