<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AgroAide — First admin setup</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-stone-900">
<main class="mx-auto flex min-h-screen max-w-md items-center px-5">
    <form method="post" action="{{ route('staff.setup.store') }}" class="w-full rounded-2xl border border-stone-200 bg-white p-8 shadow-sm">
        @csrf
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-700">AgroAide</p>
        <h1 class="mt-2 text-2xl font-bold">Create first admin</h1>
        <p class="mt-2 text-sm text-stone-600">
            This page is only available while no staff accounts exist. After setup, use the staff login page.
        </p>
        <label class="mt-6 block text-sm font-medium">Full name
            <input name="name" type="text" value="{{ old('name') }}" required autofocus class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
        </label>
        <label class="mt-4 block text-sm font-medium">Email
            <input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
        </label>
        <label class="mt-4 block text-sm font-medium">Password
            <input name="password" type="password" required minlength="12" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
        </label>
        <label class="mt-4 block text-sm font-medium">Confirm password
            <input name="password_confirmation" type="password" required minlength="12" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
        </label>
        <p class="mt-2 text-xs text-stone-500">At least 12 characters, with letters and numbers.</p>
        @if ($errors->any())
            <ul class="mt-3 space-y-1 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        <button class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">
            Create admin &amp; continue
        </button>
        <p class="mt-4 text-center text-sm text-stone-500">
            Already set up?
            <a href="{{ route('staff.login') }}" class="font-medium text-emerald-700 hover:underline">Staff sign in</a>
        </p>
    </form>
</main>
</body>
</html>
