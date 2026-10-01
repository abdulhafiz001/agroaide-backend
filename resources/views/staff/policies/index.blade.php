@extends('layouts.staff')

@section('title', 'Confidence Policies')

@section('content')

<div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Confidence Policies</h1>
        <p class="mt-0.5 text-sm text-slate-500">Immutable confidence policies governing AI diagnosis retakes, expert reviews, and canonical validation.</p>
    </div>
    <a href="{{ route('staff.audit') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700 hover:underline">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        View policy audit trail →
    </a>
</div>

{{-- Create new policy version --}}
<div class="mb-6 rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="font-semibold text-slate-900">Create New Policy Version</h2>
        <p class="mt-0.5 text-xs text-slate-500">Each policy version is cryptographically hashed with SHA-256 and becomes permanently immutable upon creation.</p>
    </div>
    <div class="p-6">
        <form method="post" action="{{ route('staff.policies.store') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @csrf
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Policy Name</label>
                <input name="name" required placeholder="e.g. production-default" value="{{ old('name') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Version String</label>
                <input name="version" required placeholder="e.g. 2.0" value="{{ old('version') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Retake Below</label>
                <input name="retake_below" value="0.60" readonly
                       title="Retake threshold — fixed at 0.60 by protocol"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 cursor-not-allowed">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Review Below</label>
                <input name="review_below" value="0.85" readonly
                       title="Review threshold — fixed at 0.85 by protocol"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 cursor-not-allowed">
            </div>
            <div class="flex items-end">
                <input type="hidden" name="require_canonical" value="1">
                <button type="submit"
                        class="w-full rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Create version
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Existing policies --}}
<div class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-6 py-4">
        <h2 class="font-semibold text-slate-900">Policy Versions</h2>
        <p class="mt-0.5 text-xs text-slate-500">Current and historical policies. Exactly one policy is active at any time.</p>
    </div>
    @if($policies->isEmpty())
        <div class="px-6 py-12 text-center text-sm text-slate-500">
            No confidence policies defined yet.
        </div>
    @else
        <div class="divide-y divide-slate-100">
            @foreach($policies as $policy)
            <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2.5">
                        <span class="font-semibold text-slate-900 text-base">{{ $policy->name }}</span>
                        <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">v{{ $policy->version }}</span>
                        @if($policy->active)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                Active Version
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                Inactive
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                        <span>Retake threshold: &lt;<strong>{{ $policy->retake_below }}</strong></span>
                        <span>·</span>
                        <span>Review threshold: &lt;<strong>{{ $policy->review_below }}</strong></span>
                        <span>·</span>
                        <span>Canonical required: <strong>{{ $policy->require_canonical ? 'Yes' : 'No' }}</strong></span>
                    </div>
                    @if($policy->checksum)
                    <div class="font-mono text-xs text-slate-400">
                        SHA-256: <span title="{{ $policy->checksum }}">{{ substr($policy->checksum, 0, 20) }}...{{ substr($policy->checksum, -12) }}</span>
                    </div>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if(! $policy->active)
                    <form method="post" action="{{ route('staff.policies.activate', $policy) }}">
                        @csrf
                        <button type="submit"
                                class="rounded-lg border border-emerald-600 bg-white px-3.5 py-1.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-50">
                            Activate this version
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

@endsection
