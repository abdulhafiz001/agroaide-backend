<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Audit history</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="bg-stone-100 text-stone-900"><main class="mx-auto max-w-6xl space-y-6 px-4 py-8">
<a href="{{ route('staff.admin') }}" class="text-emerald-700 underline">Back to administration</a>
<section class="rounded-xl border bg-white p-6"><h1 class="text-2xl font-bold">Privacy-safe audit history</h1><div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th>Time</th><th>Actor ID</th><th>Action</th><th>Subject</th><th>Safe context</th></tr></thead><tbody>@foreach($events as $event)<tr class="border-t"><td class="py-2">{{ $event->created_at }}</td><td>{{ $event->actor_user_id ?? 'system' }}</td><td>{{ $event->action }}</td><td>{{ class_basename($event->subject_type) }} #{{ $event->subject_id }}</td><td class="max-w-md break-all font-mono text-xs">{{ $event->safe_context }}</td></tr>@endforeach</tbody></table></div>{{ $events->links() }}</section>
</main></body></html>
