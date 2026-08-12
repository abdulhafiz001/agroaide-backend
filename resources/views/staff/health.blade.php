@extends('layouts.staff')

@section('title', 'System health')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">System health</h1>
    <p class="mt-1 text-sm text-slate-500">Provider snapshots and recent job runs</p>
</div>

<div class="grid gap-6 lg:grid-cols-2">

    {{-- Provider health --}}
    <div>
        <h2 class="mb-3 font-semibold text-slate-800">Provider health</h2>
        @if($health->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white py-8 text-center text-sm text-slate-500">
                No provider snapshots recorded.
            </div>
        @else
            <div class="space-y-2">
                @foreach($health as $snapshot)
                @php($isUp = $snapshot->status === 'up' || $snapshot->status === 'ok')
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="flex h-2.5 w-2.5 rounded-full {{ $isUp ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $snapshot->provider }}</p>
                            @if($snapshot->safe_error_code)
                            <p class="text-xs text-red-600">{{ $snapshot->safe_error_code }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $isUp ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                            {{ ucfirst($snapshot->status) }}
                        </span>
                        @if($snapshot->latency_ms !== null)
                        <p class="mt-0.5 text-xs text-slate-400">{{ number_format($snapshot->latency_ms) }} ms</p>
                        @endif
                        <p class="mt-0.5 text-xs text-slate-400">{{ \Carbon\Carbon::parse($snapshot->checked_at)->diffForHumans() }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- System job runs --}}
    <div>
        <h2 class="mb-3 font-semibold text-slate-800">Recent job runs</h2>
        @if($jobs->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-300 bg-white py-8 text-center text-sm text-slate-500">
                No job runs recorded.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Job</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Status</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Finished</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($jobs as $job)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <p class="text-xs font-medium text-slate-800">{{ class_basename($job->job_type) }}</p>
                                @if($job->safe_error_code)
                                <p class="text-xs text-red-600">{{ $job->safe_error_code }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php($jobColor = match($job->status) {
                                    'completed' => 'bg-emerald-100 text-emerald-800',
                                    'failed'    => 'bg-red-100 text-red-800',
                                    'running'   => 'bg-blue-100 text-blue-800',
                                    'queued'    => 'bg-slate-100 text-slate-700',
                                    default     => 'bg-slate-100 text-slate-700',
                                })
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $jobColor }}">
                                    {{ ucfirst($job->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">
                                {{ $job->finished_at ? \Carbon\Carbon::parse($job->finished_at)->diffForHumans() : '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

@endsection
