@extends('layouts.staff')

@section('title', 'Farmer feedback')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Farmer feedback</h1>
    <p class="mt-1 text-sm text-slate-500">{{ $feedback->total() }} total verdicts</p>
</div>

{{-- Verdict summary pills --}}
@if($verdictSummary->isNotEmpty())
<div class="mb-6 flex flex-wrap gap-3">
    @foreach($verdictSummary as $row)
    @php($color = match($row->verdict) {
        'helpful'     => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'not_helpful' => 'bg-red-50 border-red-200 text-red-800',
        'inaccurate'  => 'bg-orange-50 border-orange-200 text-orange-800',
        default       => 'bg-slate-100 border-slate-200 text-slate-700',
    })
    <div class="flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold {{ $color }}">
        <span>{{ str_replace('_', ' ', ucfirst($row->verdict)) }}</span>
        <span class="rounded-full bg-white/60 px-2 py-0.5 text-xs font-bold">{{ $row->total }}</span>
    </div>
    @endforeach
</div>
@endif

@if($feedback->isEmpty())
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center">
        <p class="font-semibold text-slate-700">No feedback yet</p>
        <p class="mt-1 text-sm text-slate-400">Farmers haven't submitted any verdicts.</p>
    </div>
@else
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Scan</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Thumbnail</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Verdict</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Comment</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Submitted</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($feedback as $item)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs text-slate-500">#{{ $item->farm_image_analysis_id }}</td>
                    <td class="px-4 py-3">
                        @if($item->analysis)
                        <img src="{{ route('staff.scans.image', $item->farm_image_analysis_id) }}"
                             alt="Scan thumbnail"
                             class="h-10 w-14 rounded-lg object-cover border border-slate-200">
                        @else
                        <div class="h-10 w-14 rounded-lg bg-slate-100 border border-slate-200"></div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @php($verdictColor = match($item->verdict) {
                            'helpful'     => 'bg-emerald-100 text-emerald-800',
                            'not_helpful' => 'bg-red-100 text-red-800',
                            'inaccurate'  => 'bg-orange-100 text-orange-800',
                            default       => 'bg-slate-200 text-slate-700',
                        })
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $verdictColor }}">
                            {{ str_replace('_', ' ', $item->verdict) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 max-w-xs text-slate-600">
                        {{ $item->comment ? \Illuminate\Support\Str::limit($item->comment, 80) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($item->created_at)->format('d M Y, H:i') }}
                    </td>
                    <td class="px-4 py-3">
                        @if($item->analysis)
                        <a href="{{ route('staff.scans.show', $item->farm_image_analysis_id) }}"
                           class="text-sm font-medium text-emerald-700 hover:underline whitespace-nowrap">View scan</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($feedback->hasPages())
    <div class="mt-5">
        {{ $feedback->links() }}
    </div>
    @endif
@endif

@endsection
