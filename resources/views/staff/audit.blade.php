@extends('layouts.staff')

@section('title', 'Audit log')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Audit log</h1>
    <p class="mt-1 text-sm text-slate-500">Privacy-safe record of staff actions. Actor IDs only — no personal data.</p>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Time</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Actor</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Action</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Subject</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Context</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($events as $event)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($event->created_at)->format('d M Y, H:i') }}
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-600">
                        {{ $event->actor_user_id ? '#'.$event->actor_user_id : 'system' }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">
                            {{ $event->action }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500">
                        {{ class_basename($event->subject_type) }} #{{ $event->subject_id }}
                    </td>
                    <td class="px-4 py-3 max-w-xs">
                        <code class="break-all text-xs text-slate-500">{{ $event->safe_context }}</code>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No audit events recorded.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-100 px-4 py-3">
        {{ $events->links() }}
    </div>
</div>

@endsection
