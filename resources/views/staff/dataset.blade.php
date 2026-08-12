@extends('layouts.staff')

@section('title', $dataset->name.' v'.$dataset->version)

@section('content')

<nav class="mb-5 flex items-center gap-2 text-sm text-slate-500">
    <a href="{{ route('staff.evaluations.index') }}" class="font-medium text-emerald-700 hover:underline">Evaluations</a>
    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    <span>{{ $dataset->name }}</span>
</nav>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">
        {{ $dataset->name }}
        <span class="text-slate-400">v{{ $dataset->version }}</span>
    </h1>
</div>

<div class="grid gap-6 lg:grid-cols-3">

    {{-- Dataset details --}}
    <div class="lg:col-span-2 space-y-5">

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold text-slate-900">Metadata</h2>
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Source / provenance</dt>
                    <dd class="mt-0.5 text-slate-700">{{ $dataset->source }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">License</dt>
                    <dd class="mt-0.5 text-slate-700">{{ $dataset->license }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">State</dt>
                    <dd class="mt-0.5">
                        @if($dataset->locked_at)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                Locked · ready
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                Not ready
                            </span>
                        @endif
                        <span class="ml-2 text-slate-500">{{ $dataset->items_count }} items</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">SHA-256 checksum</dt>
                    <dd class="mt-0.5 break-all font-mono text-xs text-slate-500">{{ $dataset->checksum }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-2 font-semibold text-slate-900">Import protocol</h2>
            <p class="text-sm text-slate-600">
                Dataset files remain private and are imported through the secure
                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">agroaide:evaluation:import</code>
                Artisan command.
                Required CSV columns: <code class="text-xs">external_id, image, crop, disease, provenance</code>.
                See <code class="text-xs">docs/dataset-protocol.md</code>.
            </p>
        </div>

        {{-- Runs list --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold text-slate-900">Runs</h2>
            @forelse($runs as $run)
            <div class="flex items-center justify-between border-t border-slate-100 py-3 first:border-0 first:pt-0">
                <div>
                    <a href="{{ route('staff.evaluations.runs.show', $run) }}"
                       class="font-medium text-emerald-700 hover:underline">Run #{{ $run->id }}</a>
                    <span class="ml-2 text-sm text-slate-500">{{ $run->sample_count }} samples</span>
                </div>
                @php($runColor = match($run->status) {
                    'completed' => 'bg-emerald-100 text-emerald-800',
                    'failed'    => 'bg-red-100 text-red-800',
                    'running'   => 'bg-blue-100 text-blue-800',
                    default     => 'bg-slate-100 text-slate-700',
                })
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $runColor }}">
                    {{ ucfirst($run->status) }}
                </span>
            </div>
            @empty
            <p class="text-sm text-slate-500">No runs yet.</p>
            @endforelse
        </div>

    </div>

    {{-- Queue run sidebar --}}
    <div>
        @if(auth()->user()->isAdmin())
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-semibold text-slate-900">Queue evaluation run</h2>
            @if(!$dataset->locked_at || $dataset->items_count < 1)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
                Dataset must be locked and have at least one item before queuing.
            </div>
            @else
            <p class="mb-3 text-sm text-slate-500">Uses the currently active model, prompt, and policy.</p>
            <form method="post" action="{{ route('staff.evaluations.runs.store', $dataset) }}">
                @csrf
                <button type="submit"
                        class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800">
                    Queue run
                </button>
            </form>
            @endif
        </div>
        @endif
    </div>

</div>

@endsection
