@extends('layouts.staff')

@section('title', 'Scan #'.$scan->id)

@section('content')

{{-- Breadcrumb --}}
<nav class="mb-5 flex items-center gap-2 text-sm text-slate-500">
    <a href="{{ route('staff.scans.index') }}" class="font-medium text-emerald-700 hover:underline">Scan review</a>
    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    <span>Scan #{{ $scan->id }}</span>
</nav>

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">

    {{-- ── Left column: image + AI output ── --}}
    <div class="space-y-5">

        {{-- Large image --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-sm">
            <img src="{{ route('staff.scans.image', $scan) }}"
                 alt="Crop scan #{{ $scan->id }}"
                 class="w-full object-contain max-h-[520px]">
        </div>

        {{-- Scan metadata --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-bold text-slate-900">Scan details</h2>
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Scan ID</dt>
                    <dd class="mt-0.5 font-mono text-slate-700">#{{ $scan->id }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Verification state</dt>
                    <dd class="mt-0.5">
                        @php($stateColors = [
                            'pending_review'       => 'bg-amber-100 text-amber-800',
                            'disputed'             => 'bg-orange-100 text-orange-800',
                            'expert_verified'      => 'bg-emerald-100 text-emerald-800',
                            'expert_rejected'      => 'bg-red-100 text-red-800',
                            'legacy_ineligible'    => 'bg-slate-100 text-slate-600',
                        ])
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $stateColors[$scan->verification_state] ?? 'bg-slate-100 text-slate-600' }}">
                            {{ str_replace('_', ' ', $scan->verification_state) }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Field</dt>
                    <dd class="mt-0.5 text-slate-700">{{ $scan->farmField?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Crop (field)</dt>
                    <dd class="mt-0.5 text-slate-700">{{ $scan->farmField?->crop ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Submitted</dt>
                    <dd class="mt-0.5 text-slate-700">{{ $scan->created_at?->format('d M Y, H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Inference latency</dt>
                    <dd class="mt-0.5 text-slate-700">{{ $scan->inference_latency_ms ? number_format($scan->inference_latency_ms).' ms' : '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- AI diagnosis --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-bold text-slate-900">AI diagnosis</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Predicted crop</p>
                    <p class="mt-1 font-semibold text-slate-800">{{ $scan->predictedCropLabel?->name ?? $scan->condition ?? '—' }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Predicted disease</p>
                    <p class="mt-1 font-semibold text-slate-800">{{ $scan->predictedDiseaseLabel?->name ?? $scan->disease_name ?? '—' }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Confidence</p>
                    <p class="mt-1 font-semibold text-slate-800">
                        {{ $scan->normalized_confidence !== null ? number_format($scan->normalized_confidence * 100, 1).'%' : '—' }}
                    </p>
                </div>
            </div>

            {{-- result_json output --}}
            @if(!empty($scan->result_json))
            <div class="mt-5 space-y-3">
                @foreach($resultFields as $label => $value)
                @if($value !== null && $value !== '')
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-slate-700">{{ $value }}</p>
                </div>
                @endif
                @endforeach
            </div>
            @endif
        </div>

        {{-- Review history --}}
        @if($scan->reviews->isNotEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-bold text-slate-900">Review history</h2>
            <div class="space-y-3">
                @foreach($scan->reviews->sortByDesc('created_at') as $review)
                <div class="flex gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-600 uppercase">
                        {{ substr($review->actor?->name ?? '?', 0, 1) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-slate-800">{{ $review->actor?->name ?? 'System' }}</span>
                            <span class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($review->created_at)->diffForHumans() }}</span>
                            <span class="rounded bg-slate-200 px-1.5 py-0.5 text-xs font-mono text-slate-600">{{ $review->from_state }} → {{ $review->to_state }}</span>
                        </div>
                        @if($review->reason)
                        <p class="mt-1 text-sm text-slate-600">{{ $review->reason }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- ── Right column: review form ── --}}
    <div class="space-y-5">

        {{-- Review actions --}}
        <div class="sticky top-20 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-bold text-slate-900">Review action</h2>

            <form method="post" action="{{ route('staff.scans.review', $scan) }}" class="space-y-4">
                @csrf

                {{-- Crop label --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Crop label (correction only)</label>
                    <select name="crop_label_id"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <option value="">Keep effective crop</option>
                        @foreach($cropLabels as $label)
                            <option value="{{ $label->id }}"
                                {{ old('crop_label_id') == $label->id ? 'selected' : '' }}>
                                {{ $label->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Disease label --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Disease label (correction only)</label>
                    <select name="disease_label_id"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                        <option value="">No disease correction</option>
                        @foreach($diseaseLabels as $label)
                            <option value="{{ $label->id }}"
                                {{ old('disease_label_id') == $label->id ? 'selected' : '' }}>
                                {{ $label->name }}
                                @if($label->kind === 'condition') (condition) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Reason / note --}}
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Review note</label>
                    <textarea name="reason"
                              rows="3"
                              placeholder="Optional note for audit trail…"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 resize-none">{{ old('reason') }}</textarea>
                </div>

                {{-- Action buttons --}}
                <div class="grid grid-cols-2 gap-2">
                    <button name="action" value="confirm"
                            class="flex items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Confirm
                    </button>
                    <button name="action" value="correct"
                            class="flex items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Correct
                    </button>
                    <button name="action" value="reject"
                            class="flex items-center justify-center gap-2 rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        Reject
                    </button>
                    <button name="action" value="reopen"
                            class="flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Reopen
                    </button>
                </div>

            </form>
        </div>

        {{-- Farmer feedback on this scan --}}
        @if($feedback->isNotEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-bold text-slate-900">Farmer feedback</h2>
            <div class="space-y-3">
                @foreach($feedback as $item)
                <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                    <div class="flex items-center gap-2">
                        @php($verdictColor = match($item->verdict) {
                            'helpful'     => 'bg-emerald-100 text-emerald-800',
                            'not_helpful' => 'bg-red-100 text-red-800',
                            'inaccurate'  => 'bg-orange-100 text-orange-800',
                            default       => 'bg-slate-200 text-slate-700',
                        })
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $verdictColor }}">
                            {{ str_replace('_', ' ', $item->verdict) }}
                        </span>
                        <span class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($item->created_at)->diffForHumans() }}</span>
                    </div>
                    @if($item->comment)
                    <p class="mt-1.5 text-sm text-slate-600">{{ $item->comment }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>

@endsection
