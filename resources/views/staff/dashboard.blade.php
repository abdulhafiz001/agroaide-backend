<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AgroAide Operations</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-100 text-stone-900">
<header class="sticky top-0 z-10 border-b border-stone-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
        <div><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">AgroAide</p><h1 class="text-xl font-bold">Production operations</h1></div>
        <div class="flex gap-2">@if($isAdmin)<a href="{{ route('staff.admin') }}" class="rounded-lg border px-3 py-2 text-sm">Admin</a>@endif<form method="post" action="{{ route('staff.logout') }}">@csrf<button class="rounded-lg border px-3 py-2 text-sm">Sign out</button></form></div>
    </div>
</header>
<main class="mx-auto max-w-7xl space-y-8 px-4 py-8">
    @if(session('status'))<p class="rounded-lg bg-emerald-100 p-3 text-emerald-900">{{ session('status') }}</p>@endif
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @php($latestAccuracy = $latestRun && $latestRun->metrics ? data_get(json_decode($latestRun->metrics, true), 'accuracy') : null)
        <div class="rounded-xl border bg-white p-5"><p class="text-sm text-stone-500">Latest measured accuracy</p><p class="mt-2 text-3xl font-bold">{{ $latestAccuracy === null ? 'Not measured' : number_format($latestAccuracy * 100, 1).'%' }}</p><p class="mt-2 text-xs text-stone-500">Never populated from production guesses.</p></div>
        <div class="rounded-xl border bg-white p-5"><p class="text-sm text-stone-500">Review queue</p><p class="mt-2 text-3xl font-bold">{{ $queue->count() }}</p></div>
        <div class="rounded-xl border bg-white p-5"><p class="text-sm text-stone-500">Evaluation datasets</p><p class="mt-2 text-3xl font-bold">{{ $datasets->count() }}</p></div>
        <div class="rounded-xl border bg-white p-5"><p class="text-sm text-stone-500">Visible outbreak aggregates</p><p class="mt-2 text-3xl font-bold">{{ $outbreaks->count() }}</p><p class="mt-2 text-xs text-stone-500">k&lt;3 aggregates suppressed.</p></div>
        <div class="rounded-xl border bg-white p-5"><p class="text-sm text-stone-500">Last 30 days</p><p class="mt-2 text-3xl font-bold">{{ $activeFarmCount < 3 ? '<3' : $activeFarmCount }} active farms</p><p class="mt-2 text-xs text-stone-500">Distinct farms with scan, journal, completed task, or transaction activity.</p></div>
    </section>

    <section><h2 class="mb-3 text-lg font-bold">Pending and disputed scans</h2>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">@forelse($queue as $scan)
            <article class="overflow-hidden rounded-xl border bg-white">
                <img src="{{ route('staff.scans.image', $scan) }}" alt="Private crop scan" class="h-48 w-full object-cover">
                <div class="space-y-2 p-4"><div class="flex justify-between"><strong>Scan #{{ $scan->id }}</strong><span class="rounded bg-amber-100 px-2 py-1 text-xs">{{ $scan->verification_state }}</span></div>
                    <p class="text-sm">{{ $scan->farmField?->crop ?? 'Crop unavailable' }} · {{ $scan->predictedDiseaseLabel?->name ?? $scan->disease_name ?? 'No canonical disease' }}</p>
                    <p class="text-xs text-stone-500">Confidence {{ $scan->normalized_confidence === null ? 'n/a' : number_format($scan->normalized_confidence * 100, 1).'%' }} · provisional</p>
                    <form method="post" action="{{ route('staff.scans.review', $scan) }}" class="space-y-2">@csrf
                        <div class="grid grid-cols-2 gap-2">
                            <select name="crop_label_id" class="rounded border p-2 text-xs"><option value="">Effective crop</option>@foreach($labels->where('kind', 'crop') as $label)<option value="{{ $label->id }}">{{ $label->name }}</option>@endforeach</select>
                            <select name="disease_label_id" class="rounded border p-2 text-xs"><option value="">No disease correction</option>@foreach($labels->whereIn('kind', ['disease', 'condition']) as $label)<option value="{{ $label->id }}">{{ $label->name }}</option>@endforeach</select>
                        </div>
                        <textarea name="reason" placeholder="Review note" class="w-full rounded border p-2 text-sm"></textarea>
                        <div class="flex flex-wrap gap-2"><button name="action" value="confirm" class="rounded bg-emerald-700 px-3 py-2 text-xs text-white">Confirm</button><button name="action" value="correct" class="rounded bg-blue-700 px-3 py-2 text-xs text-white">Correct</button><button name="action" value="reject" class="rounded bg-red-700 px-3 py-2 text-xs text-white">Reject</button><button name="action" value="reopen" class="rounded border px-3 py-2 text-xs">Reopen</button></div>
                    </form>
                </div>
            </article>
        @empty<p class="text-stone-500">No scans await review.</p>@endforelse</div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border bg-white p-5"><h2 class="font-bold">Datasets and runs</h2><div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="py-2">Dataset</th><th>Version</th><th>Checksum</th></tr></thead><tbody>@foreach($datasets as $dataset)<tr class="border-t"><td class="py-2"><a class="text-emerald-700 underline" href="{{ route('staff.evaluations.datasets.show', $dataset->id) }}">{{ $dataset->name }}</a></td><td>{{ $dataset->version }}</td><td class="font-mono text-xs">{{ substr($dataset->checksum, 0, 12) }}</td></tr>@endforeach</tbody></table></div>
            <form method="get" action="{{ route('staff.evaluations.compare') }}" class="mt-5 space-y-2">@foreach($runs as $run)<label class="block rounded bg-stone-50 p-2 text-sm"><input type="checkbox" name="runs[]" value="{{ $run->id }}"> <a class="text-emerald-700 underline" href="{{ route('staff.evaluations.runs.show', $run->id) }}">Run #{{ $run->id }}</a> · {{ $run->status }} · {{ $run->sample_count }} samples · model {{ $run->model_version_id }} / prompt {{ $run->prompt_version_id }}</label>@endforeach<button class="rounded border px-3 py-2 text-sm">Compare selected runs</button></form></div>
        <div class="rounded-xl border bg-white p-5"><h2 class="font-bold">Per-class metrics</h2><div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th>Class</th><th>TP</th><th>FP</th><th>FN</th><th>F1</th></tr></thead><tbody>@foreach($classMetrics as $metric)<tr class="border-t"><td class="py-2">{{ $metric->label_name }}</td><td>{{ $metric->tp }}</td><td>{{ $metric->fp }}</td><td>{{ $metric->fn }}</td><td>{{ $metric->f1 === null ? 'n/a' : number_format($metric->f1, 3) }}</td></tr>@endforeach</tbody></table></div></div>
    </section>

    <section class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border bg-white p-5"><h2 class="font-bold">Farmer feedback</h2>@foreach($feedback as $item)<p class="mt-2 border-t pt-2 text-sm">Scan {{ $item->farm_image_analysis_id }} · {{ $item->verdict }}</p>@endforeach</div>
        <div class="rounded-xl border bg-white p-5"><h2 class="font-bold">Queue and provider health</h2>@foreach($jobs as $job)<p class="mt-2 text-sm">{{ class_basename($job->job_type) }} · {{ $job->status }}</p>@endforeach @foreach($health as $snapshot)<p class="mt-2 text-sm">{{ $snapshot->provider }} · {{ $snapshot->status }} · {{ $snapshot->latency_ms ?? 'n/a' }} ms</p>@endforeach</div>
        <div class="rounded-xl border bg-white p-5"><h2 class="font-bold">Privacy-safe outbreak trends</h2>@foreach($outbreaks as $event)<p class="mt-2 text-sm">{{ $event->crop_key }} · {{ $event->level }} · {{ $event->distinct_farmer_count }} farms · {{ $event->period_start }}</p>@endforeach</div>
    </section>
</main>
</body></html>
