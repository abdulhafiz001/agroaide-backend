<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dataset {{ $dataset->name }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="bg-stone-100 text-stone-900"><main class="mx-auto max-w-5xl space-y-6 px-4 py-8">
<a href="{{ route('staff.dashboard') }}" class="text-emerald-700 underline">Back to operations</a>
<section class="rounded-xl border bg-white p-6"><h1 class="text-2xl font-bold">{{ $dataset->name }} <span class="text-stone-500">v{{ $dataset->version }}</span></h1>
<dl class="mt-5 grid gap-4 sm:grid-cols-2"><div><dt class="text-sm text-stone-500">Source/provenance</dt><dd>{{ $dataset->source }}</dd></div><div><dt class="text-sm text-stone-500">License</dt><dd>{{ $dataset->license }}</dd></div><div><dt class="text-sm text-stone-500">SHA-256 checksum</dt><dd class="break-all font-mono text-xs">{{ $dataset->checksum }}</dd></div><div><dt class="text-sm text-stone-500">State</dt><dd>{{ $dataset->locked_at ? 'Locked/ready' : 'Not ready' }} · {{ $dataset->items_count }} items</dd></div></dl>
@if(auth()->user()->isAdmin())<form method="post" action="{{ route('staff.evaluations.runs.store', $dataset) }}" class="mt-5">@csrf<button class="rounded bg-emerald-700 px-4 py-2 font-semibold text-white" @disabled(!$dataset->locked_at || $dataset->items_count < 1)>Queue evaluation run</button></form>@endif
</section>
<section class="rounded-xl border bg-white p-6"><h2 class="font-bold">Import protocol</h2><p class="mt-2 text-sm">Dataset files remain private and are imported through the secure <code>agroaide:evaluation:import</code> Artisan command. Required CSV columns: external_id, image, crop, disease, provenance. See <code>docs/dataset-protocol.md</code>.</p></section>
<section class="rounded-xl border bg-white p-6"><h2 class="font-bold">Runs</h2>@forelse($runs as $run)<p class="mt-2"><a class="text-emerald-700 underline" href="{{ route('staff.evaluations.runs.show', $run) }}">Run #{{ $run->id }}</a> · {{ $run->status }} · {{ $run->sample_count }} samples</p>@empty<p class="mt-2 text-stone-500">No runs yet.</p>@endforelse</section>
</main></body></html>
